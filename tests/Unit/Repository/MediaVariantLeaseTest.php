<?php

declare(strict_types=1);

namespace Semitexa\Media\Tests\Unit\Repository;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Media\Application\Db\MySQL\Repository\MediaVariantRepository;
use Semitexa\Orm\Domain\Model\ConnectionConfig;
use Semitexa\Orm\OrmManager;

/**
 * The lease, which is the only thing stopping two workers from generating the
 * same variant at once.
 *
 * Written before converting this repository to a business model, because there
 * was nothing else to say the conversion had not changed what claiming means:
 * this package ships no tests, and a broken lease is silent — two workers do
 * the same work, the second overwrites the first, and everything looks fine.
 */
final class MediaVariantLeaseTest extends TestCase
{
    private MediaVariantRepository $repository;
    private OrmManager $orm;

    protected function setUp(): void
    {
        $this->orm = new OrmManager(config: new ConnectionConfig(driver: 'sqlite', sqliteMemory: true));
        $this->orm->getAdapter()->execute(
            'CREATE TABLE media_variants (
                id TEXT PRIMARY KEY, tenant_id TEXT, media_asset_id TEXT NOT NULL, variant_key TEXT NOT NULL,
                storage_driver TEXT, storage_path TEXT, mime_type TEXT,
                status TEXT NOT NULL DEFAULT "queued", resize_mode TEXT NOT NULL DEFAULT "fit",
                target_width INTEGER, target_height INTEGER, actual_width INTEGER, actual_height INTEGER,
                quality INTEGER, byte_size INTEGER, lease_owner TEXT, lease_expires_at TEXT,
                attempt_count INTEGER NOT NULL DEFAULT 0, max_attempts INTEGER NOT NULL DEFAULT 3,
                queued_at TEXT, processing_started_at TEXT, generated_at TEXT, last_attempt_at TEXT,
                error_code TEXT, error_message TEXT, metadata_json TEXT,
                created_at TEXT, updated_at TEXT
            )',
        );

        $this->repository = new MediaVariantRepository();
        (new \ReflectionProperty(MediaVariantRepository::class, 'orm'))->setValue($this->repository, $this->orm);
    }

    #[Test]
    public function a_queued_variant_is_claimed_and_stamped_with_its_owner(): void
    {
        $this->seed('v-1', queuedAt: '2026-09-05 10:00:00');

        $claimed = $this->repository->claimNext('worker-a');

        self::assertNotNull($claimed);
        self::assertSame('v-1', $this->idOf($claimed));
        self::assertSame('processing', $this->row('v-1')['status']);
        self::assertSame('worker-a', $this->row('v-1')['lease_owner']);
        self::assertSame(1, (int) $this->row('v-1')['attempt_count']);
    }

    /**
     * The whole point. Two workers, one queued variant: exactly one may get it.
     */
    #[Test]
    public function two_workers_never_hold_the_same_variant(): void
    {
        $this->seed('v-1', queuedAt: '2026-09-05 10:00:00');

        $first = $this->repository->claimNext('worker-a');
        $second = (new MediaVariantRepository());
        (new \ReflectionProperty(MediaVariantRepository::class, 'orm'))->setValue($second, $this->orm);
        $secondClaim = $second->claimNext('worker-b');

        self::assertNotNull($first);
        self::assertNull($secondClaim, 'the second worker must come away with nothing');
        self::assertSame('worker-a', $this->row('v-1')['lease_owner']);
    }

    #[Test]
    public function the_oldest_queued_variant_goes_first(): void
    {
        $this->seed('v-late', queuedAt: '2026-09-05 12:00:00');
        $this->seed('v-early', queuedAt: '2026-09-05 09:00:00');

        self::assertSame('v-early', $this->idOf($this->repository->claimNext('worker-a')));
    }

    /**
     * A worker that died mid-transform leaves a lease behind. Once it expires
     * the variant must become claimable again, or a crash would strand it.
     */
    #[Test]
    public function an_expired_lease_is_reclaimed(): void
    {
        // Claimed once by a worker that then died: its attempt is already counted.
        $this->seed('v-1', queuedAt: '2026-09-05 10:00:00', status: 'processing',
            leaseOwner: 'worker-dead', leaseExpiresAt: '2020-01-01 00:00:00', attemptCount: 1);

        $claimed = $this->repository->claimNext('worker-b');

        self::assertNotNull($claimed, 'a stranded variant must not stay stranded');
        self::assertSame('worker-b', $this->row('v-1')['lease_owner']);
        self::assertSame(2, (int) $this->row('v-1')['attempt_count'], 'the retry counts');
    }

    #[Test]
    public function a_live_lease_is_left_alone(): void
    {
        $this->seed('v-1', queuedAt: '2026-09-05 10:00:00', status: 'processing',
            leaseOwner: 'worker-a', leaseExpiresAt: date('Y-m-d H:i:s', time() + 3600));

        self::assertNull($this->repository->claimNext('worker-b'));
        self::assertSame('worker-a', $this->row('v-1')['lease_owner']);
    }

    /** A variant that has failed its budget must stop coming back. */
    #[Test]
    public function a_variant_out_of_attempts_is_not_claimed(): void
    {
        $this->seed('v-1', queuedAt: '2026-09-05 10:00:00', attemptCount: 3, maxAttempts: 3);

        self::assertNull($this->repository->claimNext('worker-a'));
    }

    /**
     * The one that matters most for a busy worker: with nothing left to claim,
     * claimNext must say so — not hand back the variant this worker is already
     * holding, which it never re-queued and whose attempt count never moved.
     */
    #[Test]
    public function an_empty_queue_returns_nothing_even_to_a_worker_already_holding_one(): void
    {
        $this->seed('v-1', queuedAt: '2026-09-05 10:00:00');
        $held = $this->repository->claimNext('worker-a');
        self::assertNotNull($held);

        $again = $this->repository->claimNext('worker-a');

        self::assertNull($again, 'nothing is queued; the worker must not be handed its own in-flight variant');
    }

    private function idOf(?object $variant): ?string
    {
        if ($variant === null) {
            return null;
        }

        return method_exists($variant, 'getId') ? $variant->getId() : $variant->id;
    }

    /** @return array<string, mixed> */
    private function row(string $id): array
    {
        // The adapter's query() takes no parameters — only execute() binds —
        // so the row is picked out here rather than in SQL.
        foreach ($this->orm->getAdapter()->query('SELECT * FROM media_variants')->fetchAll() as $row) {
            if (($row['id'] ?? null) === $id) {
                return $row;
            }
        }

        return [];
    }

    private function seed(
        string $id,
        string $queuedAt,
        string $status = 'queued',
        ?string $leaseOwner = null,
        ?string $leaseExpiresAt = null,
        int $attemptCount = 0,
        int $maxAttempts = 3,
    ): void {
        $this->orm->getAdapter()->execute(
            'INSERT INTO media_variants
             (id, tenant_id, media_asset_id, variant_key, status, resize_mode, lease_owner, lease_expires_at,
              attempt_count, max_attempts, queued_at, created_at, updated_at)
             VALUES (:id, :tenant, :asset, :key, :status, "fit", :owner, :expires, :attempts, :max, :queued, :queued, :queued)',
            [
                'id' => $id, 'tenant' => 'acme', 'asset' => 'a-1', 'key' => 'thumb',
                'status' => $status, 'owner' => $leaseOwner, 'expires' => $leaseExpiresAt,
                'attempts' => $attemptCount, 'max' => $maxAttempts, 'queued' => $queuedAt,
            ],
        );
    }
}
