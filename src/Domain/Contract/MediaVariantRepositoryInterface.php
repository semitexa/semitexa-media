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

    /** Claim this variant for $leaseOwner, or null when another worker holds it, it is ready, or out of attempts. */
    public function claim(string $variantId, string $leaseOwner, int $leaseDurationSeconds = 300): ?MediaVariant;

    /**
     * Ready, while $leaseOwner still holds it; false when another worker took
     * it over meanwhile (its own outcome stands).
     */
    public function markReadyIfOwned(string $variantId, string $leaseOwner, string $storagePath, string $mimeType, int $byteSize, int $width, int $height): bool;

    /** Failed, while $leaseOwner still holds it; false when another worker took it over meanwhile. */
    public function markFailedIfOwned(string $variantId, string $leaseOwner, string $errorCode, string $errorMessage): bool;

    /**
     * @return MediaVariant[]
     */
    public function findFailed(int $limit = 100): array;

    /**
     * @return MediaVariant[]
     */
    public function findFailedByAssetId(string $assetId): array;
}
