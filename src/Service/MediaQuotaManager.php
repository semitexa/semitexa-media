<?php

declare(strict_types=1);

namespace Semitexa\Media\Service;

use Semitexa\Core\Attributes\SatisfiesServiceContract;
use Semitexa\Media\Contract\MediaQuotaManagerInterface;
use Semitexa\Media\Contract\MediaQuotaUsageRepositoryInterface;
use Semitexa\Media\Domain\Exception\MediaQuotaExceededException;
use Semitexa\Media\Domain\Model\MediaCollection;

#[SatisfiesServiceContract(of: MediaQuotaManagerInterface::class)]
final class MediaQuotaManager implements MediaQuotaManagerInterface
{
    public function __construct(
        private readonly MediaQuotaUsageRepositoryInterface $quotaRepository,
    ) {}

    public function checkAndReserve(string $tenantId, MediaCollection $collection, int $byteSize): void
    {
        $bucket = $collection->quotaBucket;
        $usage  = $this->quotaRepository->findByBucket($tenantId, $bucket);

        $currentBytes = $usage?->original_bytes ?? 0;
        $currentCount = $usage?->asset_count ?? 0;

        if ($collection->maxAssetCount !== null && ($currentCount + 1) > $collection->maxAssetCount) {
            throw new MediaQuotaExceededException(
                $tenantId,
                $bucket,
                "Asset count limit of {$collection->maxAssetCount} would be exceeded.",
            );
        }

        $this->quotaRepository->incrementUsage($tenantId, $bucket, $byteSize);
    }

    public function release(string $tenantId, string $quotaBucket, int $byteSize): void
    {
        $this->quotaRepository->decrementUsage($tenantId, $quotaBucket, $byteSize);
    }

    public function recalculate(string $tenantId, string $quotaBucket): void
    {
        (new MediaQuotaRecalculator($this->quotaRepository))->recalculate($tenantId, $quotaBucket);
    }
}
