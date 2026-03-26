<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Repository;

use Semitexa\Core\Attributes\SatisfiesRepositoryContract;
use Semitexa\Media\Application\Db\MySQL\Model\MediaAssetResource;
use Semitexa\Media\Contract\MediaAssetRepositoryInterface;
use Semitexa\Orm\Repository\AbstractRepository;
use Semitexa\Orm\Uuid\Uuid7;

#[SatisfiesRepositoryContract(of: MediaAssetRepositoryInterface::class)]
class MediaAssetRepository extends AbstractRepository implements MediaAssetRepositoryInterface
{
    protected function getResourceClass(): string
    {
        return MediaAssetResource::class;
    }

    public function findById(int|string $id): ?MediaAssetResource
    {
        return $this->select()
            ->where($this->getPkColumn(), '=', Uuid7::toBytes($id))
            ->fetchOneAsResource();
    }

    public function save(object $resource): void
    {
        parent::save($resource);
    }

    public function findByTenantAndCollection(string $tenantId, string $collectionKey, int $limit = 100): array
    {
        return $this->select()
            ->where('tenant_id', '=', $tenantId)
            ->where('collection_key', '=', $collectionKey)
            ->limit($limit)
            ->fetchAll();
    }

    public function sumOriginalBytesByBucket(string $tenantId, string $quotaBucket): int
    {
        $result = $this->raw(
            'SELECT COALESCE(SUM(a.byte_size), 0) AS total
             FROM media_assets a
             INNER JOIN media_collections c ON c.collection_key = a.collection_key
             WHERE a.tenant_id = :tenant_id
               AND c.quota_bucket = :quota_bucket
               AND a.status != \'deleted\'',
            ['tenant_id' => $tenantId, 'quota_bucket' => $quotaBucket],
        );

        return (int) ($result[0]->total ?? 0);
    }

    public function countByBucket(string $tenantId, string $quotaBucket): int
    {
        $result = $this->raw(
            'SELECT COUNT(*) AS total
             FROM media_assets a
             INNER JOIN media_collections c ON c.collection_key = a.collection_key
             WHERE a.tenant_id = :tenant_id
               AND c.quota_bucket = :quota_bucket
               AND a.status != \'deleted\'',
            ['tenant_id' => $tenantId, 'quota_bucket' => $quotaBucket],
        );

        return (int) ($result[0]->total ?? 0);
    }
}
