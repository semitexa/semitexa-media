<?php

declare(strict_types=1);

namespace Semitexa\Media\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Media\Application\Service\MediaWorker;
use Semitexa\Media\Domain\Contract\MediaAssetRepositoryInterface;
use Semitexa\Media\Domain\Model\MediaAsset;
use Semitexa\Media\Domain\Model\QueuedMediaTransformMessage;
use Semitexa\Tenancy\Application\Service\TenantAwareJobSerializer;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Context\TenantContextStore;
use Semitexa\Testing\Traits\BuildsContainerManagedObjects;
use Symfony\Component\Console\Output\NullOutput;

/**
 * media:work kept the tenant of the job before: a message queued in the
 * default tenant carries no `_tenant` key, so nothing replaced the context the
 * previous message had restored, and its job ran as that tenant.
 */
final class MediaWorkerTenantResetTest extends TestCase
{
    use BuildsContainerManagedObjects;

    private ?TenantContext $before = null;

    protected function setUp(): void
    {
        $this->before = TenantContext::get();
        TenantContextStore::shared()->clear();
    }

    protected function tearDown(): void
    {
        TenantContextStore::shared()->clear();
        if ($this->before !== null) {
            TenantContextStore::shared()->set($this->before);
        }
    }

    #[Test]
    public function a_job_queued_in_the_default_tenant_does_not_run_in_the_tenant_of_the_job_before(): void
    {
        // The producer's side, as MediaQueueDispatcher::dispatch() wraps.
        TenantContextStore::shared()->set(TenantContext::fromResolution('tenant-a', 'test'));
        $ofTenantA = $this->wrapped(new QueuedMediaTransformMessage('asset-a', 'thumb', 'tenant-a'));
        TenantContextStore::shared()->clear();
        $ofDefault = $this->wrapped(new QueuedMediaTransformMessage('asset-default', 'thumb', ''));

        self::assertStringContainsString('_tenant', $ofTenantA);
        self::assertStringContainsString('asset-default', $ofDefault, 'the default message is there to read');
        self::assertStringNotContainsString('_tenant', $ofDefault, 'the default tenant is not carried');

        // The asset lookup is the first thing a job does: it reports the
        // tenant the job runs in.
        $assets = new class implements MediaAssetRepositoryInterface {
            /** @var array<string, ?string> */
            public array $seen = [];
            public function findById(string $id): ?MediaAsset
            {
                $this->seen[$id] = TenantContext::get()?->getTenantId();

                return null;
            }
            public function save(MediaAsset $entity): MediaAsset { return $entity; }
            public function findByTenantAndCollection(string $tenantId, string $collectionKey, int $limit = 100): array { return []; }
            public function findByTenantAndSha256(string $tenantId, string $sha256): ?MediaAsset { return null; }
            public function sumOriginalBytesByBucket(string $tenantId, string $quotaBucket): int { return 0; }
            public function countByBucket(string $tenantId, string $quotaBucket): int { return 0; }
        };
        $worker = $this->createWithDependencies(MediaWorker::class, ['assetRepository' => $assets]);
        $worker->setOutput(new NullOutput());

        $worker->processPayload($ofTenantA);
        self::assertNull(TenantContext::get(), 'no tenant is left after a job');

        $worker->processPayload($ofDefault);

        self::assertSame('tenant-a', $assets->seen['asset-a'], 'the job runs in the tenant its message carries');
        self::assertArrayHasKey('asset-default', $assets->seen);
        self::assertNull($assets->seen['asset-default'], 'a job that carries no tenant runs in none');
        self::assertNull(TenantContext::get(), 'no tenant is left after the worker');
    }

    /** A job that throws goes back to the transport; its tenant must not stay for the next one. */
    #[Test]
    public function no_tenant_is_left_after_a_job_that_throws(): void
    {
        TenantContextStore::shared()->set(TenantContext::fromResolution('tenant-a', 'test'));
        $ofTenantA = $this->wrapped(new QueuedMediaTransformMessage('asset-a', 'thumb', 'tenant-a'));
        TenantContextStore::shared()->clear();

        $assets = new class implements MediaAssetRepositoryInterface {
            public function findById(string $id): ?MediaAsset { throw new \RuntimeException('database gone'); }
            public function save(MediaAsset $entity): MediaAsset { return $entity; }
            public function findByTenantAndCollection(string $tenantId, string $collectionKey, int $limit = 100): array { return []; }
            public function findByTenantAndSha256(string $tenantId, string $sha256): ?MediaAsset { return null; }
            public function sumOriginalBytesByBucket(string $tenantId, string $quotaBucket): int { return 0; }
            public function countByBucket(string $tenantId, string $quotaBucket): int { return 0; }
        };
        $worker = $this->createWithDependencies(MediaWorker::class, ['assetRepository' => $assets]);
        $worker->setOutput(new NullOutput());

        $thrown = null;
        try {
            $worker->processPayload($ofTenantA);
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        self::assertSame('database gone', $thrown?->getMessage(), 'the error still goes on to the transport');
        self::assertNull(TenantContext::get());
    }

    private function wrapped(QueuedMediaTransformMessage $message): string
    {
        return json_encode(TenantAwareJobSerializer::wrap($message->jsonSerialize()), JSON_THROW_ON_ERROR);
    }
}
