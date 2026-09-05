<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Repository;

use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Attribute\SatisfiesRepositoryContract;
use Semitexa\Media\Application\Db\MySQL\Model\MediaVariantResource;
use Semitexa\Media\Domain\Model\MediaVariant;
use Semitexa\Media\Domain\Contract\MediaVariantRepositoryInterface;
use Semitexa\Orm\OrmManager;
use Semitexa\Orm\Query\Operator;
use Semitexa\Orm\Query\SystemScopeToken;
use Semitexa\Orm\Repository\DomainRepository;

#[SatisfiesRepositoryContract(of: MediaVariantRepositoryInterface::class)]
class MediaVariantRepository extends AbstractMediaRepository implements MediaVariantRepositoryInterface
{
    /** Candidates to try before giving up: a lost race is not an empty queue. */
    private const CLAIM_ATTEMPTS = 3;


    protected function getResourceClass(): string
    {
        return MediaVariantResource::class;
    }

    #[InjectAsReadonly]
    protected OrmManager $orm;

    private ?DomainRepository $repository = null;

    private ?DomainRepository $system = null;

    public function findByAssetAndKey(string $assetId, string $variantKey): ?MediaVariant
    {
        /** @var MediaVariant|null */
        return $this->system()->query()
            ->where(MediaVariantResource::column('media_asset_id'), Operator::Equals, $assetId)
            ->where(MediaVariantResource::column('variant_key'), Operator::Equals, $variantKey)
            ->fetchOneAs(MediaVariant::class, $this->orm()->getMapperRegistry());
    }

    public function findByAssetId(string $assetId): array
    {
        /** @var list<MediaVariant> */
        return $this->system()->query()
            ->where(MediaVariantResource::column('media_asset_id'), Operator::Equals, $assetId)
            ->fetchAllAs(MediaVariant::class, $this->orm()->getMapperRegistry());
    }

    public function save(MediaVariant $entity): MediaVariant
    {
        /** @var MediaVariant */
        return $entity->getId() === ''
            ? $this->system()->insert($entity)
            : $this->system()->update($entity);
    }

    /**
     * Claim one variant for this worker, or nothing.
     *
     * Two statements, on purpose. A single `UPDATE ... WHERE id = (SELECT ...)`
     * picks its candidate from a non-locking snapshot and then locks only that
     * id: two connections can choose the same row and the second overwrites the
     * first worker's lease. Naming the candidate first and repeating the
     * eligibility predicates in the UPDATE closes that — the loser's UPDATE
     * matches nothing, because by then the row is processing with a live lease.
     *
     * Losing that race is not evidence the queue is empty, so a loser tries the
     * next candidate rather than idling while work waits.
     */
    public function claimNext(string $leaseOwner, int $leaseDurationSeconds = 300): ?MediaVariant
    {
        for ($attempt = 0; $attempt < self::CLAIM_ATTEMPTS; $attempt++) {
            $now = date('Y-m-d H:i:s');
            $leaseExpires = date('Y-m-d H:i:s', time() + $leaseDurationSeconds);

            $candidate = $this->adapter()->execute(
                "SELECT id FROM media_variants
                 WHERE (status = 'queued' OR (status = 'processing' AND lease_expires_at < :now_stale))
                   AND attempt_count < max_attempts
                 ORDER BY queued_at ASC
                 LIMIT 1",
                ['now_stale' => $now],
            );

            $id = $candidate->rows[0]['id'] ?? null;
            if (!is_string($id) || $id === '') {
                return null; // nothing claimable at all
            }

            $claimed = $this->adapter()->execute(
                "UPDATE media_variants
                 SET status = 'processing',
                     lease_owner = :lease_owner,
                     lease_expires_at = :lease_expires_at,
                     last_attempt_at = :now_attempt,
                     attempt_count = attempt_count + 1,
                     processing_started_at = CASE WHEN processing_started_at IS NULL THEN :now_started ELSE processing_started_at END
                 WHERE id = :id
                   AND (status = 'queued' OR (status = 'processing' AND lease_expires_at < :now_stale))
                   AND attempt_count < max_attempts",
                [
                    'lease_owner' => $leaseOwner,
                    'lease_expires_at' => $leaseExpires,
                    // Native prepared statements (ATTR_EMULATE_PREPARES=false)
                    // reject a named placeholder bound once but used repeatedly.
                    'now_attempt' => $now,
                    'now_started' => $now,
                    'id' => $id,
                    'now_stale' => $now,
                ],
            );

            if ($claimed->rowCount === 0) {
                continue; // another worker took this one — try the next
            }

            // The id this claim actually won. Re-reading by (owner, processing)
            // instead would hand back whatever else this owner already holds:
            // a worker with v1 in flight, claiming v2, would be given v1 again
            // and do that work twice while v2 sat leased and untouched.
            /** @var MediaVariant|null */
            return $this->system()->query()
                ->where(MediaVariantResource::column('id'), Operator::Equals, $id)
                ->fetchOneAs(MediaVariant::class, $this->orm()->getMapperRegistry());
        }

        return null;
    }

    public function findFailed(int $limit = 100): array
    {
        /** @var list<MediaVariant> */
        return $this->system()->query()
            ->where(MediaVariantResource::column('status'), Operator::Equals, 'failed')
            ->limit($limit)
            ->fetchAllAs(MediaVariant::class, $this->orm()->getMapperRegistry());
    }

    public function findFailedByAssetId(string $assetId): array
    {
        /** @var list<MediaVariant> */
        return $this->system()->query()
            ->where(MediaVariantResource::column('media_asset_id'), Operator::Equals, $assetId)
            ->where(MediaVariantResource::column('status'), Operator::Equals, 'failed')
            ->fetchAllAs(MediaVariant::class, $this->orm()->getMapperRegistry());
    }

    /**
     * SYSTEM-scope view — the media pipeline (worker, planner, recalculator)
     * operates rows by their own ids across tenants; tenant-parameterised
     * finders below scope with forTenant()/explicit predicates. The resources
     * are #[TenantScoped], so any FUTURE finder must pick a posture — unscoped
     * queries fail closed instead of leaking across tenants.
     */
    private function system(): DomainRepository
    {
        return $this->system ??= $this->repository()->withoutTenantScope(SystemScopeToken::issue());
    }

    private function repository(): DomainRepository
    {
        return $this->repository ??= $this->orm()->repository(
            MediaVariantResource::class,
            MediaVariant::class,
        );
    }

    private function orm(): OrmManager
    {
        return $this->orm ??= new OrmManager();
    }

    private function adapter(): \Semitexa\Orm\Adapter\DatabaseAdapterInterface
    {
        return $this->orm()->getAdapter();
    }

}
