<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Repository;

use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Attribute\SatisfiesRepositoryContract;
use Semitexa\Media\Application\Db\MySQL\Model\MediaQuotaUsageResource;
use Semitexa\Media\Domain\Model\MediaQuotaUsage;
use Semitexa\Media\Domain\Contract\MediaQuotaUsageRepositoryInterface;
use Semitexa\Orm\OrmManager;
use Semitexa\Orm\Query\Operator;
use Semitexa\Orm\Query\SystemScopeToken;
use Semitexa\Orm\Repository\DomainRepository;

#[SatisfiesRepositoryContract(of: MediaQuotaUsageRepositoryInterface::class)]
class MediaQuotaUsageRepository extends AbstractMediaRepository implements MediaQuotaUsageRepositoryInterface
{

    protected function getResourceClass(): string
    {
        return MediaQuotaUsageResource::class;
    }

    #[InjectAsReadonly]
    protected OrmManager $orm;

    private ?DomainRepository $repository = null;

    private ?DomainRepository $system = null;

    public function findByBucket(string $tenantId, string $quotaBucket): ?MediaQuotaUsage
    {
        /** @var MediaQuotaUsage|null */
        return $this->system()->query()
            ->where(MediaQuotaUsageResource::column('tenant_id'), Operator::Equals, $tenantId)
            ->where(MediaQuotaUsageResource::column('quota_bucket'), Operator::Equals, $quotaBucket)
            ->fetchOneAs(MediaQuotaUsage::class, $this->orm()->getMapperRegistry());
    }

    public function save(MediaQuotaUsage $entity): MediaQuotaUsage
    {
        if ($entity->getId() === '') {
            /** @var MediaQuotaUsage */
            return $this->system()->insert($entity);
        }

        // The recalculator mints an id for a bucket it did NOT find, so a
        // non-empty id is not evidence of a row. Routing on the id alone made
        // the first recalculation a silent 0-row UPDATE: nothing landed, the
        // next read still found nothing, and the bucket never came into being.
        // Same reasoning as MediaAssetRepository::save().
        /** @var MediaQuotaUsage */
        return $this->findByBucket($entity->getTenantId(), $entity->getQuotaBucket()) === null
            ? $this->system()->insert($entity)
            : $this->system()->update($entity);
    }

    public function incrementUsage(string $tenantId, string $quotaBucket, int $byteSize): void
    {
        $this->adapter()->execute(
            'INSERT INTO media_quota_usage (id, tenant_id, quota_bucket, asset_count, original_bytes, variant_bytes)
             VALUES (RANDOM_BYTES(16), :tenant_id, :quota_bucket, 1, :byte_size, 0)
             ON DUPLICATE KEY UPDATE
                 asset_count = asset_count + 1,
                 original_bytes = original_bytes + :byte_size_inc',
            ['tenant_id' => $tenantId, 'quota_bucket' => $quotaBucket, 'byte_size' => $byteSize, 'byte_size_inc' => $byteSize],
        );
    }

    public function decrementUsage(string $tenantId, string $quotaBucket, int $byteSize): void
    {
        $this->adapter()->execute(
            'UPDATE media_quota_usage
             SET asset_count = GREATEST(0, asset_count - 1),
                 original_bytes = GREATEST(0, original_bytes - :byte_size)
             WHERE tenant_id = :tenant_id AND quota_bucket = :quota_bucket',
            ['tenant_id' => $tenantId, 'quota_bucket' => $quotaBucket, 'byte_size' => $byteSize],
        );
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
            MediaQuotaUsageResource::class,
            MediaQuotaUsage::class,
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
