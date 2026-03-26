<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Repository;

use Semitexa\Core\Attributes\SatisfiesRepositoryContract;
use Semitexa\Media\Application\Db\MySQL\Model\MediaQuotaUsageResource;
use Semitexa\Media\Contract\MediaQuotaUsageRepositoryInterface;
use Semitexa\Orm\Repository\AbstractRepository;

#[SatisfiesRepositoryContract(of: MediaQuotaUsageRepositoryInterface::class)]
class MediaQuotaUsageRepository extends AbstractRepository implements MediaQuotaUsageRepositoryInterface
{
    protected function getResourceClass(): string
    {
        return MediaQuotaUsageResource::class;
    }

    public function findByBucket(string $tenantId, string $quotaBucket): ?MediaQuotaUsageResource
    {
        return $this->select()
            ->where('tenant_id', '=', $tenantId)
            ->where('quota_bucket', '=', $quotaBucket)
            ->fetchOneAsResource();
    }

    public function save(object $resource): void
    {
        if (!$resource instanceof MediaQuotaUsageResource) {
            throw new \InvalidArgumentException(sprintf(
                'Expected %s, got %s.',
                MediaQuotaUsageResource::class,
                $resource::class,
            ));
        }

        parent::save($resource);
    }

    public function incrementUsage(string $tenantId, string $quotaBucket, int $byteSize): void
    {
        $this->getAdapter()->execute(
            'INSERT INTO media_quota_usage (tenant_id, quota_bucket, asset_count, original_bytes, variant_bytes)
             VALUES (:tenant_id, :quota_bucket, 1, :byte_size, 0)
             ON DUPLICATE KEY UPDATE
                 asset_count    = asset_count + 1,
                 original_bytes = original_bytes + :byte_size',
            ['tenant_id' => $tenantId, 'quota_bucket' => $quotaBucket, 'byte_size' => $byteSize],
        );
    }

    public function decrementUsage(string $tenantId, string $quotaBucket, int $byteSize): void
    {
        $this->getAdapter()->execute(
            'UPDATE media_quota_usage
             SET asset_count    = GREATEST(0, asset_count - 1),
                 original_bytes = GREATEST(0, original_bytes - :byte_size)
             WHERE tenant_id = :tenant_id AND quota_bucket = :quota_bucket',
            ['tenant_id' => $tenantId, 'quota_bucket' => $quotaBucket, 'byte_size' => $byteSize],
        );
    }
}
