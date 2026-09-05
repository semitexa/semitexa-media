<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Contract;

use Semitexa\Media\Domain\Model\MediaVariant;

interface MediaVariantRepositoryInterface
{
    public function findByAssetAndKey(string $assetId, string $variantKey): ?MediaVariant;

    /**
     * @return MediaVariant[]
     */
    public function findByAssetId(string $assetId): array;

    /**
     * Persist and RETURN the stored row — resources are readonly; the write
     * engine's generated id/state comes back on a fresh instance.
     */
    public function save(MediaVariant $entity): MediaVariant;

    /**
     * Atomically claim the next queued variant for processing.
     * Returns null when no claimable row is available.
     */
    public function claimNext(string $leaseOwner, int $leaseDurationSeconds = 300): ?MediaVariant;

    /**
     * @return MediaVariant[]
     */
    public function findFailed(int $limit = 100): array;

    /**
     * @return MediaVariant[]
     */
    public function findFailedByAssetId(string $assetId): array;
}
