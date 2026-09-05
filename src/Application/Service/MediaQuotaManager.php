<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Semitexa\Media\Domain\Contract\MediaQuotaManagerInterface;
use Semitexa\Media\Domain\Contract\MediaQuotaUsageRepositoryInterface;
use Semitexa\Media\Domain\Exception\MediaQuotaExceededException;
use Semitexa\Media\Domain\Model\MediaCollection;

#[SatisfiesServiceContract(of: MediaQuotaManagerInterface::class)]
final class MediaQuotaManager implements MediaQuotaManagerInterface
{
    #[InjectAsReadonly]
    protected MediaQuotaUsageRepositoryInterface $quotaRepository;

    #[InjectAsReadonly]
    protected MediaQuotaRecalculator $quotaRecalculator;

    public function checkAndReserve(string $tenantId, MediaCollection $collection, int $byteSize): void
    {
        $bucket = $collection->getQuotaBucket();
        $usage  = $this->quotaRepository->findByBucket($tenantId, $bucket);

        $currentBytes = $usage?->getOriginalBytes() ?? 0;
        $currentCount = $usage?->getAssetCount() ?? 0;

        if ($collection->getMaxAssetCount() !== null && ($currentCount + 1) > $collection->getMaxAssetCount()) {
            throw new MediaQuotaExceededException(
                $tenantId,
                $bucket,
                "Asset count limit of {$collection->getMaxAssetCount()} would be exceeded.",
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
        $this->quotaRecalculator->recalculate($tenantId, $quotaBucket);
    }
}
