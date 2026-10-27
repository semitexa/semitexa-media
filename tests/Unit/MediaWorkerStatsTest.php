<?php

declare(strict_types=1);

namespace Semitexa\Media\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Queue\QueueStatsRecorder;
use Semitexa\Core\Queue\QueueStatsStoreInterface;
use Semitexa\Media\Application\Service\MediaCollectionPolicyResolver;
use Semitexa\Media\Application\Service\MediaCollectionRegistry;
use Semitexa\Media\Domain\Contract\MediaCollectionRepositoryInterface;
use Semitexa\Media\Domain\Enum\MediaVariantStatus;
use Semitexa\Media\Domain\Model\MediaCollection;
use Semitexa\Media\Application\Service\MediaWorker;
use Semitexa\Media\Domain\Contract\MediaAssetRepositoryInterface;
use Semitexa\Media\Domain\Contract\MediaVariantRepositoryInterface;
use Semitexa\Media\Domain\Model\MediaAsset;
use Semitexa\Media\Domain\Model\MediaVariant;
use Semitexa\Media\Domain\Model\QueuedMediaTransformMessage;
use Semitexa\Testing\Traits\BuildsContainerManagedObjects;
use Symfony\Component\Console\Output\NullOutput;

/**
 * media:work recorded nothing into the queue counters, so queue:stats and the
 * Queues app listed the media queue with no throughput at all.
 */
final class MediaWorkerStatsTest extends TestCase
{
    use BuildsContainerManagedObjects;

    #[Test]
    public function a_failed_variant_counts_as_dead_lettered_and_a_skipped_message_counts_nothing(): void
    {
        $store = new class implements QueueStatsStoreInterface {
            /** @var list<array<string, int|string>> */
            public array $added = [];
            public function add(string $queue, int $minute, array $deltas): void { $this->added[] = ['queue' => $queue] + $deltas; }
            public function sum(string $queue, int $fromMinute, int $toMinute): array { return []; }
            public function describe(): string { return 'test'; }
        };
        $worker = $this->worker();
        foreach (['stats' => new QueueStatsRecorder($store), 'statsResolved' => true, 'currentQueue' => 'media'] as $name => $value) {
            (new \ReflectionProperty(MediaWorker::class, $name))->setValue($worker, $value);
        }

        $worker->processPayload((new QueuedMediaTransformMessage('gone', 'thumb', 't1'))->toJson());
        $worker->processPayload((new QueuedMediaTransformMessage('a1', 'made', 't1'))->toJson()); // already ready: nothing
        // Its collection cannot be resolved: the variant fails, and is not retried.
        $worker->processPayload((new QueuedMediaTransformMessage('a1', 'thumb', 't1'))->toJson());

        self::assertCount(1, $store->added);
        self::assertSame('media', $store->added[0]['queue']);
        self::assertSame(1, $store->added[0]['dead_lettered'] ?? null);
        self::assertSame(1, $store->added[0]['run_count']);
    }

    /** Thrown past the variant's own handling, a message counted nothing. */
    #[Test]
    public function a_message_whose_processing_throws_counts_as_retried_and_the_error_is_kept(): void
    {
        $store = new class implements QueueStatsStoreInterface {
            /** @var list<array<string, int|string>> */
            public array $added = [];
            public function add(string $queue, int $minute, array $deltas): void { $this->added[] = ['queue' => $queue] + $deltas; }
            public function sum(string $queue, int $fromMinute, int $toMinute): array { return []; }
            public function describe(): string { return 'test'; }
        };
        $worker = $this->worker();
        foreach (['stats' => new QueueStatsRecorder($store), 'statsResolved' => true, 'currentQueue' => 'media'] as $name => $value) {
            (new \ReflectionProperty(MediaWorker::class, $name))->setValue($worker, $value);
        }

        try {
            $worker->processPayload((new QueuedMediaTransformMessage('boom', 'thumb', 't1'))->toJson());
            self::fail('the error goes on to the transport');
        } catch (\RuntimeException) {
        }

        self::assertSame(1, $store->added[0]['retried'] ?? null, 'the transport hands it back: not lost');
        self::assertArrayNotHasKey('dead_lettered', $store->added[0]);
    }

    private function worker(): MediaWorker
    {
        $assets = new class implements MediaAssetRepositoryInterface {
            public function findById(string $id): ?MediaAsset
            {
                if ($id === 'boom') {
                    throw new \RuntimeException('database gone');
                }

                return $id === 'a1' ? new MediaAsset('a1', 't1', 'no-such-collection', 'local', 'a1.jpg', 'a1.jpg', 'image/jpeg', 'image', 'private', 'ready', 10, 'sha') : null;
            }
            public function save(MediaAsset $entity): MediaAsset { return $entity; }
            public function findByTenantAndCollection(string $tenantId, string $collectionKey, int $limit = 100): array { return []; }
            public function findByTenantAndSha256(string $tenantId, string $sha256): ?MediaAsset { return null; }
            public function sumOriginalBytesByBucket(string $tenantId, string $quotaBucket): int { return 0; }
            public function countByBucket(string $tenantId, string $quotaBucket): int { return 0; }
        };
        $variants = new class implements MediaVariantRepositoryInterface {
            public function findByAssetAndKey(string $assetId, string $variantKey): ?MediaVariant
            {
                return new MediaVariant('v1', 't1', $assetId, $variantKey, $variantKey === 'made' ? MediaVariantStatus::Ready->value : 'queued');
            }
            public function findByAssetId(string $assetId): array { return []; }
            public function save(MediaVariant $entity): MediaVariant { return $entity; }
            public function claimNext(string $leaseOwner, int $leaseDurationSeconds = 300): ?MediaVariant { return null; }
            public function claim(string $variantId, string $leaseOwner, int $leaseDurationSeconds = 300): ?MediaVariant
            {
                return new MediaVariant($variantId, 't1', 'a1', 'thumb', MediaVariantStatus::Processing->value);
            }
            public function markReadyIfOwned(string $variantId, string $leaseOwner, string $storagePath, string $mimeType, int $byteSize, int $width, int $height): bool { return true; }
            public function markFailedIfOwned(string $variantId, string $leaseOwner, string $errorCode, string $errorMessage): bool { return true; }
            public function findFailed(int $limit = 100): array { return []; }
            public function findFailedByAssetId(string $assetId): array { return []; }
        };
        // A collection key nobody defined: resolve() throws MediaCollectionNotFoundException.
        $registry = new MediaCollectionRegistry();
        (new \ReflectionProperty(MediaCollectionRegistry::class, 'providersLoaded'))->setValue($registry, true);
        $resolver = $this->createWithDependencies(MediaCollectionPolicyResolver::class, [
            'registry' => $registry,
            'collectionRepository' => new class implements MediaCollectionRepositoryInterface {
                public function findActive(string $collectionKey, ?string $tenantId = null): ?MediaCollection { return null; }
                public function save(MediaCollection $entity): MediaCollection { return $entity; }
                public function findAllEnabled(): array { return []; }
            },
        ]);

        $worker = $this->createWithDependencies(MediaWorker::class, [
            'assetRepository' => $assets,
            'variantRepository' => $variants,
            'collectionResolver' => $resolver,
        ]);
        $worker->setOutput(new NullOutput());

        return $worker;
    }
}
