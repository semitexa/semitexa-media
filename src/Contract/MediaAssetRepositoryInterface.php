<?php

declare(strict_types=1);

namespace Semitexa\Media\Contract;

use Semitexa\Media\Application\Db\MySQL\Model\MediaAssetResource;

interface MediaAssetRepositoryInterface
{
    public function findById(string $id): ?MediaAssetResource;

    public function save(MediaAssetResource $resource): void;

    /**
     * @return MediaAssetResource[]
     */
    public function findByTenantAndCollection(string $tenantId, string $collectionKey, int $limit = 100): array;

    public function sumOriginalBytesByBucket(string $tenantId, string $quotaBucket): int;

    public function countByBucket(string $tenantId, string $quotaBucket): int;
}
