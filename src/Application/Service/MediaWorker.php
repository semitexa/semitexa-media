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
use Semitexa\Core\Queue\DelayedQueueTransportInterface;
use Semitexa\Core\Queue\QueueConfig;
use Semitexa\Core\Queue\QueueStatsRecorder;
use Semitexa\Core\Queue\QueueStopSignals;
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
    private ?string $currentQueue = null;
    private ?QueueStatsRecorder $stats = null;
    private bool $statsResolved = false;
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
        $this->currentQueue = $queue;

        $transport = QueueTransportRegistry::create($this->currentTransport);
        QueueStopSignals::install($transport, fn (string $message) => $this->log($message));

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

        $startedAt = microtime(true);
        try {
            $done = $this->processMessage($message, $payload);
        } catch (\Throwable $e) {
            // Thrown past the variant's own handling (its database, the queue):
            // nothing counted it, and queue:stats showed a worker doing nothing.
            // Retried, not lost: the transport hands it back.
            $this->recordStats(false, $startedAt, retried: true);
            throw $e;
        }
        $this->recordStats($done, $startedAt);
    }

    /**
     * A variant made or failed, into the per-queue counters queue:work writes
     * (queue:stats, the Queues app). A message skipped (its asset or variant
     * gone, already ready, out of attempts) counts nothing; a failed variant
     * is not retried, so it counts as dead-lettered. No wait: queuedAt has
     * whole seconds, which would put every wait in the 1 s bucket.
     */
    private function recordStats(?bool $done, float $startedAt, bool $retried = false): void
    {
        if ($done === null || $this->currentQueue === null) {
            return;
        }
        if (!$this->statsResolved) {
            $this->statsResolved = true;
            $this->stats = QueueStatsRecorder::fromEnvironment();
        }
        $this->stats?->record(
            $this->currentQueue,
            $done ? 'processed' : 'dead_lettered',
            $retried,
            null,
            (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    /** The message in hand again, $delay seconds later, where the transport can hold it back. */
    private function republishLater(string $raw, int $delay): bool
    {
        if ($this->currentTransport === null || $this->currentQueue === null) {
            return false;
        }
        try {
            $transport = QueueTransportRegistry::create($this->currentTransport);
            if (!$transport instanceof DelayedQueueTransportInterface) {
                return false;
            }
            $transport->publishDelayed($this->currentQueue, $raw, $delay);

            return true;
        } catch (\Throwable $e) {
            $this->log("Could not look at the variant again later: {$e->getMessage()}", 'warning');

            return false;
        }
    }

    /**
     * $raw: the message as it arrived (tenant wrapping included), to publish again.
     *
     * @return bool|null whether the variant was made; null when the message was skipped
     */
    private function processMessage(QueuedMediaTransformMessage $message, ?string $raw = null): ?bool
    {
        $this->initializeWorkerId();
        $asset = $this->assetRepository->findById($message->assetId);

        if ($asset === null) {
            $this->log("Asset '{$message->assetId}' not found — discarding variant '{$message->variantKey}'.", 'warning');
            return null;
        }

        $variant = $this->variantRepository->findByAssetAndKey($message->assetId, $message->variantKey);

        if ($variant === null) {
            $this->log("Variant '{$message->variantKey}' for asset '{$message->assetId}' not found — discarding.", 'warning');
            return null;
        }

        if ($variant->getStatus() === MediaVariantStatus::Ready->value) {
            $this->log("Variant '{$message->variantKey}' for asset '{$message->assetId}' already ready — skipping.", 'info');
            return null;
        }

        if ($variant->getAttemptCount() >= $variant->getMaxAttempts()) {
            $this->log("Variant '{$message->variantKey}' for asset '{$message->assetId}' exceeded max attempts — skipping.", 'warning');
            return null;
        }

        // Claimed, not saved: two workers on one redelivered message both read
        // it as not ready, both saved it processing, and both generated it.
        $claimed = $this->variantRepository->claim($variant->getId(), $this->workerId, 300);
        if ($claimed === null) {
            // Its holder may have died with it: dropped, the variant stayed
            // processing for good. Looked at again once the lease has run out.
            // Read again: two duplicates both read it queued, and the loser skipped
            // without looking again while the winner might yet die.
            $now = $this->variantRepository->findByAssetAndKey($message->assetId, $message->variantKey) ?? $variant;
            $leaseLeft = $now->getLeaseExpiresAt() !== null ? $now->getLeaseExpiresAt()->getTimestamp() - time() : 0;
            $later = $now->getStatus() === MediaVariantStatus::Processing->value && $raw !== null && $this->republishLater($raw, max(1, $leaseLeft) + 5);
            $this->log("Variant '{$message->variantKey}' for asset '{$message->assetId}' is being generated by another worker — " . ($later ? 'looking again after its lease.' : 'skipping.'), 'info');
            return null;
        }

        return $this->transformVariant($asset, $claimed);
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
        // Only while this worker still holds it: saved whole, a worker whose
        // lease ran out overwrote the outcome of the one that took it over.
        if (!$this->variantRepository->markReadyIfOwned($variant->getId(), (string) $variant->getLeaseOwner(), (string) $result->storagePath, (string) $result->mimeType, (int) $result->byteSize, (int) $result->actualWidth, (int) $result->actualHeight)) {
            $this->log("Variant '{$variant->getVariantKey()}' for asset '{$variant->getMediaAssetId()}' was taken over by another worker; its outcome stands.", 'warning');
        }
    }

    private function failVariant(MediaVariant $variant, string $errorCode, string $errorMessage): void
    {
        if (!$this->variantRepository->markFailedIfOwned($variant->getId(), (string) $variant->getLeaseOwner(), $errorCode, $errorMessage)) {
            $this->log("Variant '{$variant->getVariantKey()}' for asset '{$variant->getMediaAssetId()}' was taken over by another worker; its outcome stands.", 'warning');
        }
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
