<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Repository;

use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Attribute\SatisfiesRepositoryContract;
use Semitexa\Media\Application\Db\MySQL\Model\MediaAssetResource;
use Semitexa\Media\Application\Db\MySQL\Model\MediaAssetTableModel;
use Semitexa\Media\Contract\MediaAssetRepositoryInterface;
use Semitexa\Orm\OrmManager;
use Semitexa\Orm\Query\Operator;
use Semitexa\Orm\Repository\DomainRepository;

#[SatisfiesRepositoryContract(of: MediaAssetRepositoryInterface::class)]
class MediaAssetRepository implements MediaAssetRepositoryInterface
{
    use AssertsExpectedResourceType;

    #[InjectAsReadonly]
    protected ?OrmManager $orm = null;

    private ?DomainRepository $repository = null;

    protected function getResourceClass(): string
    {
        return MediaAssetResource::class;
    }

    public function findById(string $id): ?MediaAssetResource
    {
        /** @var MediaAssetResource|null */
        return $this->repository()->findById($id);
    }

    public function save(MediaAssetResource $entity): void
    {
        $resource = $this->assertResourceType($entity);
        $persisted = $resource->id !== '' && $this->repository()->findById($resource->id) !== null
            ? $this->repository()->update($resource)
            : $this->repository()->insert($resource);

        $this->copyIntoMutableResource($persisted, $resource);
    }

    public function findByTenantAndCollection(string $tenantId, string $collectionKey, int $limit = 100): array
    {
        /** @var list<MediaAssetResource> */
        return $this->repository()->query()
            ->where(MediaAssetTableModel::column('tenant_id'), Operator::Equals, $tenantId)
            ->where(MediaAssetTableModel::column('collection_key'), Operator::Equals, $collectionKey)
            ->limit($limit)
            ->fetchAllAs(MediaAssetResource::class, $this->orm()->getMapperRegistry());
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

    private function repository(): DomainRepository
    {
        return $this->repository ??= $this->orm()->repository(
            MediaAssetTableModel::class,
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

    private function copyIntoMutableResource(object $source, MediaAssetResource $target): void
    {
        $source instanceof MediaAssetResource || throw new \InvalidArgumentException('Unexpected persisted resource.');

        foreach (get_object_vars($source) as $property => $value) {
            $target->{$property} = $value;
        }
    }
}
