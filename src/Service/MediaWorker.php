<?php

declare(strict_types=1);

namespace Semitexa\Media\Service;

use Semitexa\Media\Application\Db\MySQL\Model\MediaVariantResource;
use Semitexa\Media\Configuration\MediaConfig;
use Semitexa\Media\Contract\MediaAssetRepositoryInterface;
use Semitexa\Media\Contract\MediaVariantRepositoryInterface;
use Semitexa\Media\Enum\MediaVariantStatus;
use Semitexa\Media\Queue\Message\QueuedMediaTransformMessage;
use Semitexa\Core\Queue\QueueConfig;
use Semitexa\Core\Queue\QueueTransportRegistry;
use Semitexa\Tenancy\Propagation\TenantAwareJobSerializer;
use Symfony\Component\Console\Output\OutputInterface;

final class MediaWorker
{
    private ?string $currentTransport = null;
    private ?OutputInterface $output = null;
    private string $workerId;

    public function __construct(
        private readonly MediaConfig $config,
        private readonly MediaAssetRepositoryInterface $assetRepository,
        private readonly MediaVariantRepositoryInterface $variantRepository,
        private readonly MediaCollectionPolicyResolver $collectionResolver,
        private readonly MediaTransformationService $transformationService,
    ) {
        $this->workerId = gethostname() . ':' . getmypid();
    }

    public function setOutput(?OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function run(?string $transportName, ?string $queueName = null): void
    {
        $this->currentTransport = $transportName ?: QueueConfig::defaultTransport();
        $queue = $queueName ?: $this->config->workerQueue;

        $transport = QueueTransportRegistry::create($this->currentTransport);

        $this->log("Media worker started (transport={$this->currentTransport}, queue={$queue}, worker={$this->workerId})");

        $transport->consume($queue, function (string $payload): void {
            $this->processPayload($payload);
        });
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

        // Mark as processing
        $variant->status               = MediaVariantStatus::Processing->value;
        $variant->lease_owner          = $this->workerId;
        $variant->lease_expires_at     = new \DateTimeImmutable('+5 minutes');
        $variant->last_attempt_at      = new \DateTimeImmutable();
        $variant->attempt_count       += 1;

        if ($variant->processing_started_at === null) {
            $variant->processing_started_at = new \DateTimeImmutable();
        }

        $this->variantRepository->save($variant);

        try {
            $collection = $this->collectionResolver->resolve(
                $asset->collection_key,
                $asset->tenant_id,
            );
        } catch (\Throwable $e) {
            $this->failVariant($variant, 'collection_not_found', $e->getMessage());
            $this->log("Collection not found for asset '{$message->assetId}': {$e->getMessage()}", 'error');
            return;
        }

        try {
            $result = $this->transformationService->generateVariant(
                originalPath: $asset->original_path,
                assetId:      $message->assetId,
                tenantId:     $asset->tenant_id ?? '',
                variant:      $variant,
                collection:   $collection,
            );
        } catch (\Throwable $e) {
            $this->failVariant($variant, 'processing_error', $e->getMessage());
            $this->log("Transform failed for '{$message->assetId}/{$message->variantKey}': {$e->getMessage()}", 'error');
            return;
        }

        if ($result->success) {
            $this->markVariantReady($variant, $result);
            $this->log("Variant '{$message->variantKey}' for asset '{$message->assetId}' generated successfully.", 'success');
        } else {
            $this->failVariant($variant, $result->errorCode ?? 'unknown', $result->errorMessage ?? '');
            $this->log("Variant '{$message->variantKey}' failed: {$result->errorMessage}", 'error');
        }
    }

    private function markVariantReady(MediaVariantResource $variant, \Semitexa\Media\Value\VariantGenerationResult $result): void
    {
        $variant->status         = MediaVariantStatus::Ready->value;
        $variant->storage_path   = $result->storagePath;
        $variant->mime_type      = $result->mimeType;
        $variant->byte_size      = $result->byteSize;
        $variant->actual_width   = $result->actualWidth;
        $variant->actual_height  = $result->actualHeight;
        $variant->generated_at   = new \DateTimeImmutable();
        $variant->lease_owner    = null;
        $variant->lease_expires_at = null;
        $variant->error_code     = null;
        $variant->error_message  = null;

        $this->variantRepository->save($variant);
    }

    private function failVariant(MediaVariantResource $variant, string $errorCode, string $errorMessage): void
    {
        $variant->status        = MediaVariantStatus::Failed->value;
        $variant->error_code    = $errorCode;
        $variant->error_message = $errorMessage;
        $variant->lease_owner   = null;
        $variant->lease_expires_at = null;

        $this->variantRepository->save($variant);
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
}
