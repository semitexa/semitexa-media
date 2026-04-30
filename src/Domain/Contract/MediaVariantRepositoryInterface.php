<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Contract;

use Semitexa\Media\Application\Db\MySQL\Model\MediaVariantResource;

interface MediaVariantRepositoryInterface
{
    public function findByAssetAndKey(string $assetId, string $variantKey): ?MediaVariantResource;

    /**
     * @return MediaVariantResource[]
     */
    public function findByAssetId(string $assetId): array;

    public function save(MediaVariantResource $entity): void;

    /**
     * Atomically claim the next queued variant for processing.
     * Returns null when no claimable row is available.
     */
    public function claimNext(string $leaseOwner, int $leaseDurationSeconds = 300): ?MediaVariantResource;

    /**
     * @return MediaVariantResource[]
     */
    public function findFailed(int $limit = 100): array;

    /**
     * @return MediaVariantResource[]
     */
    public function findFailedByAssetId(string $assetId): array;
}
