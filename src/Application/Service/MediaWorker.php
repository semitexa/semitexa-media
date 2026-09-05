<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Media\Domain\Model\MediaAsset;
use Semitexa\Media\Domain\Model\MediaVariant;
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

            $asset = $this->assetRepository->findById($variant->getMediaAssetId());
            if ($asset === null) {
                $this->failVariant($variant, 'asset_not_found', "Asset '{$variant->getMediaAssetId()}' not found.");
                $this->log("Asset '{$variant->getMediaAssetId()}' not found — variant '{$variant->getVariantKey()}' failed.", 'warning');
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

        if ($variant->getStatus() === MediaVariantStatus::Ready->value) {
            $this->log("Variant '{$message->variantKey}' for asset '{$message->assetId}' already ready — skipping.", 'info');
            return;
        }

        if ($variant->getAttemptCount() >= $variant->getMaxAttempts()) {
            $this->log("Variant '{$message->variantKey}' for asset '{$message->assetId}' exceeded max attempts — skipping.", 'warning');
            return;
        }

        // Mark as processing (resources are readonly — continue with the row
        // returned by save()).
        $variant = $this->variantRepository->save($variant->with([
            'status' => MediaVariantStatus::Processing->value,
            'leaseOwner' => $this->workerId,
            'leaseExpiresAt' => new \DateTimeImmutable('+5 minutes'),
            'lastAttemptAt' => new \DateTimeImmutable(),
            'attemptCount' => $variant->getAttemptCount() + 1,
            'processingStartedAt' => $variant->getProcessingStartedAt() ?? new \DateTimeImmutable(),
        ]));

        $this->transformVariant($asset, $variant);
    }

    /**
     * Shared transform tail for both the queue path (processMessage) and the
     * DB-claim path (drain): the variant must already be marked processing.
     */
    private function transformVariant(MediaAsset $asset, MediaVariant $variant): bool
    {
        try {
            $collection = $this->collectionResolver->resolve(
                $asset->getCollectionKey(),
                $asset->getTenantId(),
            );
        } catch (\Throwable $e) {
            $this->failVariant($variant, 'collection_not_found', $e->getMessage());
            $this->log("Collection not found for asset '{$variant->getMediaAssetId()}': {$e->getMessage()}", 'error');
            return false;
        }

        try {
            $result = $this->transformationService->generateVariant(
                originalPath: $asset->getOriginalPath(),
                assetId:      $variant->getMediaAssetId(),
                tenantId:     $asset->getTenantId() ?? '',
                variant:      $variant,
                collection:   $collection,
            );
        } catch (\Throwable $e) {
            $this->failVariant($variant, 'processing_error', $e->getMessage());
            $this->log("Transform failed for '{$variant->getMediaAssetId()}/{$variant->getVariantKey()}': {$e->getMessage()}", 'error');
            return false;
        }

        if ($result->success) {
            $this->markVariantReady($variant, $result);
            $this->log("Variant '{$variant->getVariantKey()}' for asset '{$variant->getMediaAssetId()}' generated successfully.", 'success');
            return true;
        }

        $this->failVariant($variant, $result->errorCode ?? 'unknown', $result->errorMessage ?? '');
        $this->log("Variant '{$variant->getVariantKey()}' failed: {$result->errorMessage}", 'error');
        return false;
    }

    private function markVariantReady(MediaVariant $variant, \Semitexa\Media\Domain\Model\VariantGenerationResult $result): void
    {
        $this->variantRepository->save($variant->with([
            'status' => MediaVariantStatus::Ready->value,
            'storagePath' => $result->storagePath,
            'mimeType' => $result->mimeType,
            'byteSize' => $result->byteSize,
            'actualWidth' => $result->actualWidth,
            'actualHeight' => $result->actualHeight,
            'generatedAt' => new \DateTimeImmutable(),
            'leaseOwner' => null,
            'leaseExpiresAt' => null,
            'errorCode' => null,
            'errorMessage' => null,
        ]));
    }

    private function failVariant(MediaVariant $variant, string $errorCode, string $errorMessage): void
    {
        $this->variantRepository->save($variant->with([
            'status' => MediaVariantStatus::Failed->value,
            'errorCode' => $errorCode,
            'errorMessage' => $errorMessage,
            'leaseOwner' => null,
            'leaseExpiresAt' => null,
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
