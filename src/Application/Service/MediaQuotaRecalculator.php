<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Media\Application\Db\MySQL\Model\MediaQuotaUsageResource;
use Semitexa\Media\Domain\Contract\MediaAssetRepositoryInterface;
use Semitexa\Media\Domain\Contract\MediaQuotaUsageRepositoryInterface;

#[AsService]
final class MediaQuotaRecalculator
{
    #[InjectAsReadonly]
    protected MediaQuotaUsageRepositoryInterface $quotaRepository;

    #[InjectAsReadonly]
    protected MediaAssetRepositoryInterface $assetRepository;

    public function recalculate(string $tenantId, string $quotaBucket): void
    {
        if (!isset($this->assetRepository)) {
            throw new \LogicException('MediaQuotaRecalculator requires MediaAssetRepositoryInterface injection.');
        }

        $totalBytes = $this->assetRepository->sumOriginalBytesByBucket($tenantId, $quotaBucket);
        $totalCount = $this->assetRepository->countByBucket($tenantId, $quotaBucket);

        $resource = $this->quotaRepository->findByBucket($tenantId, $quotaBucket)
            ?? new MediaQuotaUsageResource(tenant_id: $tenantId, quota_bucket: $quotaBucket);

        $this->quotaRepository->save($resource->copyWith([
            'original_bytes' => $totalBytes,
            'asset_count' => $totalCount,
            'last_recalculated_at' => new \DateTimeImmutable(),
        ]));
    }
}
