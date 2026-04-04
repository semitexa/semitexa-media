<?php

declare(strict_types=1);

namespace Semitexa\Media\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Media\Application\Db\MySQL\Model\MediaQuotaUsageResource;
use Semitexa\Media\Contract\MediaAssetRepositoryInterface;
use Semitexa\Media\Contract\MediaQuotaUsageRepositoryInterface;

#[AsService]
final class MediaQuotaRecalculator
{
    #[InjectAsReadonly]
    protected MediaQuotaUsageRepositoryInterface $quotaRepository;

    #[InjectAsReadonly]
    protected ?MediaAssetRepositoryInterface $assetRepository = null;

    public function recalculate(string $tenantId, string $quotaBucket): void
    {
        $totalBytes = 0;
        $totalCount = 0;

        if ($this->assetRepository !== null) {
            $totalBytes = $this->assetRepository->sumOriginalBytesByBucket($tenantId, $quotaBucket);
            $totalCount = $this->assetRepository->countByBucket($tenantId, $quotaBucket);
        }

        $resource = $this->quotaRepository->findByBucket($tenantId, $quotaBucket);

        if ($resource === null) {
            $resource               = new MediaQuotaUsageResource();
            $resource->tenant_id    = $tenantId;
            $resource->quota_bucket = $quotaBucket;
        }

        $resource->original_bytes       = $totalBytes;
        $resource->asset_count          = $totalCount;
        $resource->last_recalculated_at = new \DateTimeImmutable();

        $this->quotaRepository->save($resource);
    }
}
