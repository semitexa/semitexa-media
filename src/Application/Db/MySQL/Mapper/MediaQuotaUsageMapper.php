<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Mapper;

use Semitexa\Media\Application\Db\MySQL\Model\MediaQuotaUsageResource;
use Semitexa\Media\Domain\Model\MediaQuotaUsage;
use Semitexa\Orm\Attribute\AsMapper;
use Semitexa\Orm\Domain\Contract\ResourceModelMapperInterface;

/** The bridge between the MySQL row and a bucket's usage. */
#[AsMapper(resourceModel: MediaQuotaUsageResource::class, domainModel: MediaQuotaUsage::class)]
final class MediaQuotaUsageMapper implements ResourceModelMapperInterface
{
    public function toDomain(object $resourceModel): object
    {
        $resourceModel instanceof MediaQuotaUsageResource
            || throw new \InvalidArgumentException('Unexpected resource model.');

        return new MediaQuotaUsage(
            id: $resourceModel->id,
            tenantId: $resourceModel->tenant_id,
            quotaBucket: $resourceModel->quota_bucket,
            assetCount: $resourceModel->asset_count,
            originalBytes: $resourceModel->original_bytes,
            variantBytes: $resourceModel->variant_bytes,
            lastRecalculatedAt: $resourceModel->last_recalculated_at,
            createdAt: $resourceModel->created_at,
            updatedAt: $resourceModel->updated_at,
        );
    }

    public function toSourceModel(object $domainModel): object
    {
        $domainModel instanceof MediaQuotaUsage || throw new \InvalidArgumentException('Unexpected domain model.');

        return new MediaQuotaUsageResource(
            id: $domainModel->getId(),
            tenant_id: $domainModel->getTenantId(),
            quota_bucket: $domainModel->getQuotaBucket(),
            asset_count: $domainModel->getAssetCount(),
            original_bytes: $domainModel->getOriginalBytes(),
            variant_bytes: $domainModel->getVariantBytes(),
            last_recalculated_at: $domainModel->getLastRecalculatedAt(),
            created_at: $domainModel->getCreatedAt(),
            updated_at: $domainModel->getUpdatedAt(),
        );
    }
}
