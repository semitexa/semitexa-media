<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Contract;

use Semitexa\Media\Application\Db\MySQL\Model\MediaAssetResource;

interface MediaAssetRepositoryInterface
{
    public function findById(string $id): ?MediaAssetResource;

    /**
     * Persist and RETURN the stored row — resources are readonly; the write
     * engine's generated id/state comes back on a fresh instance.
     */
    public function save(MediaAssetResource $entity): MediaAssetResource;

    /**
     * @return MediaAssetResource[]
     */
    public function findByTenantAndCollection(string $tenantId, string $collectionKey, int $limit = 100): array;

    /**
     * Content-hash lookup for ingest dedup; deleted assets do not count,
     * so a re-import after deletion produces a fresh asset.
     */
    public function findByTenantAndSha256(string $tenantId, string $sha256): ?MediaAssetResource;

    public function sumOriginalBytesByBucket(string $tenantId, string $quotaBucket): int;

    public function countByBucket(string $tenantId, string $quotaBucket): int;
}
