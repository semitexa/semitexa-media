<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Repository;

use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Attribute\SatisfiesRepositoryContract;
use Semitexa\Media\Application\Db\MySQL\Model\MediaAssetResource;
use Semitexa\Media\Domain\Contract\MediaAssetRepositoryInterface;
use Semitexa\Orm\OrmManager;
use Semitexa\Orm\Query\Operator;
use Semitexa\Orm\Query\SystemScopeToken;
use Semitexa\Orm\Repository\DomainRepository;

#[SatisfiesRepositoryContract(of: MediaAssetRepositoryInterface::class)]
class MediaAssetRepository extends AbstractMediaRepository implements MediaAssetRepositoryInterface
{

    #[InjectAsReadonly]
    protected OrmManager $orm;

    private ?DomainRepository $repository = null;

    private ?DomainRepository $system = null;

    protected function getResourceClass(): string
    {
        return MediaAssetResource::class;
    }

    public function findById(string $id): ?MediaAssetResource
    {
        /** @var MediaAssetResource|null */
        return $this->system()->findById($id);
    }

    public function save(MediaAssetResource $entity): MediaAssetResource
    {
        if ($entity->id === '') {
            /** @var MediaAssetResource */
            return $this->system()->insert($entity);
        }

        // Ingest pins a pre-generated id on a brand-new resource (readonly
        // model — the row is rebuilt via copyWith). Route by actual
        // existence, otherwise the first save() becomes a silent 0-row
        // UPDATE and the asset never lands.
        /** @var MediaAssetResource */
        return $this->findById($entity->id) === null
            ? $this->system()->insert($entity)
            : $this->system()->update($entity);
    }

    public function findByTenantAndCollection(string $tenantId, string $collectionKey, int $limit = 100): array
    {
        /** @var list<MediaAssetResource> */
        return $this->system()->query()
            ->where(MediaAssetResource::column('tenant_id'), Operator::Equals, $tenantId)
            ->where(MediaAssetResource::column('collection_key'), Operator::Equals, $collectionKey)
            ->limit($limit)
            ->fetchAllAs(MediaAssetResource::class, $this->orm()->getMapperRegistry());
    }

    public function findByTenantAndSha256(string $tenantId, string $sha256): ?MediaAssetResource
    {
        /** @var list<MediaAssetResource> $rows */
        $rows = $this->system()->query()
            ->where(MediaAssetResource::column('tenant_id'), Operator::Equals, $tenantId)
            ->where(MediaAssetResource::column('sha256'), Operator::Equals, $sha256)
            ->where(MediaAssetResource::column('status'), Operator::NotEquals, 'deleted')
            ->limit(1)
            ->fetchAllAs(MediaAssetResource::class, $this->orm()->getMapperRegistry());

        return $rows[0] ?? null;
    }

    public function sumOriginalBytesByBucket(string $tenantId, string $quotaBucket): int
    {
        $result = $this->adapter()->execute(
            "SELECT COALESCE(SUM(a.byte_size), 0) AS total
             FROM media_assets a
             INNER JOIN media_collections c ON c.collection_key = a.collection_key
             WHERE a.tenant_id = :tenant_id
               AND c.quota_bucket = :quota_bucket
               AND a.status != 'deleted'",
            ['tenant_id' => $tenantId, 'quota_bucket' => $quotaBucket],
        );

        return (int) ($result->rows[0]['total'] ?? 0);
    }

    public function countByBucket(string $tenantId, string $quotaBucket): int
    {
        $result = $this->adapter()->execute(
            "SELECT COUNT(*) AS total
             FROM media_assets a
             INNER JOIN media_collections c ON c.collection_key = a.collection_key
             WHERE a.tenant_id = :tenant_id
               AND c.quota_bucket = :quota_bucket
               AND a.status != 'deleted'",
            ['tenant_id' => $tenantId, 'quota_bucket' => $quotaBucket],
        );

        return (int) ($result->rows[0]['total'] ?? 0);
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
            MediaAssetResource::class,
            MediaAssetResource::class,
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
