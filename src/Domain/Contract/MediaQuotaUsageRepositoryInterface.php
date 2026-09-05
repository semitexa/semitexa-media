<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Contract;

use Semitexa\Media\Domain\Model\MediaQuotaUsage;

interface MediaQuotaUsageRepositoryInterface
{
    public function findByBucket(string $tenantId, string $quotaBucket): ?MediaQuotaUsage;

    /**
     * Persist and RETURN the stored row — resources are readonly; the write
     * engine's generated id/state comes back on a fresh instance.
     */
    public function save(MediaQuotaUsage $entity): MediaQuotaUsage;

    public function incrementUsage(string $tenantId, string $quotaBucket, int $byteSize): void;

    public function decrementUsage(string $tenantId, string $quotaBucket, int $byteSize): void;
}
