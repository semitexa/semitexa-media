<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Contract;

use Semitexa\Media\Domain\Model\MediaAsset;

interface MediaAssetRepositoryInterface
{
    public function findById(string $id): ?MediaAsset;

    /**
     * Persist and RETURN the stored row — resources are readonly; the write
     * engine's generated id/state comes back on a fresh instance.
     */
    public function save(MediaAsset $entity): MediaAsset;

    /**
     * @return MediaAsset[]
     */
    public function findByTenantAndCollection(string $tenantId, string $collectionKey, int $limit = 100): array;

    /**
     * Content-hash lookup for ingest dedup; deleted assets do not count,
     * so a re-import after deletion produces a fresh asset.
     */
    public function findByTenantAndSha256(string $tenantId, string $sha256): ?MediaAsset;

    public function sumOriginalBytesByBucket(string $tenantId, string $quotaBucket): int;

    public function countByBucket(string $tenantId, string $quotaBucket): int;
}
