<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Media\Domain\Model\MediaAsset;
use Semitexa\Media\Application\Db\MySQL\Model\MediaVariantResource;
use Semitexa\Media\Configuration\MediaConfig;
use Semitexa\Media\Domain\Contract\MediaAssetRepositoryInterface;
use Semitexa\Media\Domain\Contract\MediaVariantRepositoryInterface;
use Semitexa\Media\Domain\Enum\MediaVariantStatus;
use Semitexa\Media\Domain\Model\QueuedMediaTransformMessage;
use Semitexa\Core\Queue\QueueConfig;
use Semitexa\Core\Queue\QueueTransportRegistry;
use Semitexa\Tenancy\Application\Service\TenantAwareJobSerializer;
use Symfony\Component\Console\Output\OutputInterface;

#[AsService]
final class MediaWorker
{
    #[InjectAsReadonly]
    protected MediaConfig $config;

    #[InjectAsReadonly]
    protected MediaAssetRepositoryInterface $assetRepository;

    #[InjectAsReadonly]
    protected MediaVariantRepositoryInterface $variantRepository;

    #[InjectAsReadonly]
    protected MediaCollectionPolicyResolver $collectionResolver;

    #[InjectAsReadonly]
    protected MediaTransformationService $transformationService;

    private ?string $currentTransport = null;
    private ?OutputInterface $output = null;
    private string $workerId = '';

    public function setOutput(?OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function run(?string $transportName, ?string $queueName = null): void
    {
        $this->initializeWorkerId();
        $this->currentTransport = $transportName ?: QueueConfig::defaultTransport();
        $queue = $queueName ?: $this->config->workerQueue();

        $transport = QueueTransportRegistry::create($this->currentTransport);

        $this->log("Media worker started (transport={$this->currentTransport}, queue={$queue}, worker={$this->workerId})");

        $transport->consume($queue, function (string $payload): void {
            $this->processPayload($payload);
        });
    }

    /**
     * Broker-less mode: claim queued (or lease-expired) variants straight from
     * the database and transform them until the backlog is empty. The bridge
     * for projects without a queue broker — pairs with 'media:drain' and
     * 'media:import --sync'.
     *
     * @return array{processed: int, failed: int}
     */
    public function drain(?int $limit = null): array
    {
        $this->initializeWorkerId();

        $processed = 0;
        $failed    = 0;

        while ($limit === null || ($processed + $failed) < $limit) {
            $variant = $this->variantRepository->claimNext($this->workerId);
            if ($variant === null) {
                break;
            }

            $asset = $this->assetRepository->findById($variant->media_asset_id);
            if ($asset === null) {
                $this->failVariant($variant, 'asset_not_found', "Asset '{$variant->media_asset_id}' not found.");
                $this->log("Asset '{$variant->media_asset_id}' not found — variant '{$variant->variant_key}' failed.", 'warning');
                $failed++;
                continue;
            }

            $this->transformVariant($asset, $variant) ? $processed++ : $failed++;
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    public function processPayload(string $payload): void
    {
        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->log("Failed to decode media queue message: {$e->getMessage()}", 'error');
            return;
        }

        $data = TenantAwareJobSerializer::unwrapAndRestore($data);

        if (($data['type'] ?? '') !== QueuedMediaTransformMessage::TYPE) {
            $this->log("Unexpected message type '{$data['type']}' on media queue — skipping.", 'warning');
            return;
        }

        try {
            $message = QueuedMediaTransformMessage::fromJson(json_encode($data, JSON_THROW_ON_ERROR));
        } catch (\Throwable $e) {
            $this->log("Failed to parse QueuedMediaTransformMessage: {$e->getMessage()}", 'error');
            return;
        }

        $this->processMessage($message);
    }

    private function processMessage(QueuedMediaTransformMessage $message): void
    {
        $this->initializeWorkerId();
        $asset = $this->assetRepository->findById($message->assetId);

        if ($asset === null) {
            $this->log("Asset '{$message->assetId}' not found — discarding variant '{$message->variantKey}'.", 'warning');
            return;
        }

        $variant = $this->variantRepository->findByAssetAndKey($message->assetId, $message->variantKey);

        if ($variant === null) {
            $this->log("Variant '{$message->variantKey}' for asset '{$message->assetId}' not found — discarding.", 'warning');
            return;
        }

        if ($variant->status === MediaVariantStatus::Ready->value) {
            $this->log("Variant '{$message->variantKey}' for asset '{$message->assetId}' already ready — skipping.", 'info');
            return;
        }

        if ($variant->attempt_count >= $variant->max_attempts) {
            $this->log("Variant '{$message->variantKey}' for asset '{$message->assetId}' exceeded max attempts — skipping.", 'warning');
            return;
        }

        // Mark as processing (resources are readonly — continue with the row
        // returned by save()).
        $variant = $this->variantRepository->save($variant->copyWith([
            'status' => MediaVariantStatus::Processing->value,
            'lease_owner' => $this->workerId,
            'lease_expires_at' => new \DateTimeImmutable('+5 minutes'),
            'last_attempt_at' => new \DateTimeImmutable(),
            'attempt_count' => $variant->attempt_count + 1,
            'processing_started_at' => $variant->processing_started_at ?? new \DateTimeImmutable(),
        ]));

        $this->transformVariant($asset, $variant);
    }

    /**
     * Shared transform tail for both the queue path (processMessage) and the
     * DB-claim path (drain): the variant must already be marked processing.
     */
    private function transformVariant(MediaAsset $asset, MediaVariantResource $variant): bool
    {
        try {
            $collection = $this->collectionResolver->resolve(
                $asset->getCollectionKey(),
                $asset->getTenantId(),
            );
        } catch (\Throwable $e) {
            $this->failVariant($variant, 'collection_not_found', $e->getMessage());
            $this->log("Collection not found for asset '{$variant->media_asset_id}': {$e->getMessage()}", 'error');
            return false;
        }

        try {
            $result = $this->transformationService->generateVariant(
                originalPath: $asset->getOriginalPath(),
                assetId:      $variant->media_asset_id,
                tenantId:     $asset->getTenantId() ?? '',
                variant:      $variant,
                collection:   $collection,
            );
        } catch (\Throwable $e) {
            $this->failVariant($variant, 'processing_error', $e->getMessage());
            $this->log("Transform failed for '{$variant->media_asset_id}/{$variant->variant_key}': {$e->getMessage()}", 'error');
            return false;
        }

        if ($result->success) {
            $this->markVariantReady($variant, $result);
            $this->log("Variant '{$variant->variant_key}' for asset '{$variant->media_asset_id}' generated successfully.", 'success');
            return true;
        }

        $this->failVariant($variant, $result->errorCode ?? 'unknown', $result->errorMessage ?? '');
        $this->log("Variant '{$variant->variant_key}' failed: {$result->errorMessage}", 'error');
        return false;
    }

    private function markVariantReady(MediaVariantResource $variant, \Semitexa\Media\Domain\Model\VariantGenerationResult $result): void
    {
        $this->variantRepository->save($variant->copyWith([
            'status' => MediaVariantStatus::Ready->value,
            'storage_path' => $result->storagePath,
            'mime_type' => $result->mimeType,
            'byte_size' => $result->byteSize,
            'actual_width' => $result->actualWidth,
            'actual_height' => $result->actualHeight,
            'generated_at' => new \DateTimeImmutable(),
            'lease_owner' => null,
            'lease_expires_at' => null,
            'error_code' => null,
            'error_message' => null,
        ]));
    }

    private function failVariant(MediaVariantResource $variant, string $errorCode, string $errorMessage): void
    {
        $this->variantRepository->save($variant->copyWith([
            'status' => MediaVariantStatus::Failed->value,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'lease_owner' => null,
            'lease_expires_at' => null,
        ]));
    }

    private function log(string $message, string $level = 'info'): void
    {
        if ($this->output !== null) {
            $tag = match ($level) {
                'error'   => 'error',
                'warning' => 'comment',
                'success' => 'info',
                default   => 'info',
            };
            $this->output->writeln("<{$tag}>{$message}</{$tag}>");
        } else {
            echo "{$message}\n";
        }
    }

    private function initializeWorkerId(): void
    {
        if ($this->workerId !== '') {
            return;
        }

        $hostname = gethostname();
        $this->workerId = ($hostname !== false ? $hostname : 'unknown-host') . ':' . getmypid();
    }
}
