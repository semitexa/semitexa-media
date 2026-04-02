<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Repository;

use Semitexa\Core\Attributes\InjectAsReadonly;
use Semitexa\Core\Attributes\SatisfiesRepositoryContract;
use Semitexa\Media\Application\Db\MySQL\Model\MediaQuotaUsageResource;
use Semitexa\Media\Application\Db\MySQL\Model\MediaQuotaUsageTableModel;
use Semitexa\Media\Contract\MediaQuotaUsageRepositoryInterface;
use Semitexa\Orm\OrmManager;
use Semitexa\Orm\Query\Operator;
use Semitexa\Orm\Repository\DomainRepository;

#[SatisfiesRepositoryContract(of: MediaQuotaUsageRepositoryInterface::class)]
class MediaQuotaUsageRepository implements MediaQuotaUsageRepositoryInterface
{
    use AssertsExpectedResourceType;

    protected function getResourceClass(): string
    {
        return MediaQuotaUsageResource::class;
    }

    #[InjectAsReadonly]
    protected ?OrmManager $orm = null;

    private ?DomainRepository $repository = null;

    public function findByBucket(string $tenantId, string $quotaBucket): ?MediaQuotaUsageResource
    {
        /** @var MediaQuotaUsageResource|null */
        return $this->repository()->query()
            ->where(MediaQuotaUsageTableModel::column('tenant_id'), Operator::Equals, $tenantId)
            ->where(MediaQuotaUsageTableModel::column('quota_bucket'), Operator::Equals, $quotaBucket)
            ->fetchOneAs(MediaQuotaUsageResource::class, $this->orm()->getMapperRegistry());
    }

    public function save(MediaQuotaUsageResource $entity): void
    {
        $resource = $this->assertResourceType($entity);
        $persisted = $resource->id === ''
            ? $this->repository()->insert($resource)
            : $this->repository()->update($resource);

        $this->copyIntoMutableResource($persisted, $resource);
    }

    public function incrementUsage(string $tenantId, string $quotaBucket, int $byteSize): void
    {
        $this->adapter()->execute(
            'INSERT INTO media_quota_usage (tenant_id, quota_bucket, asset_count, original_bytes, variant_bytes)
             VALUES (:tenant_id, :quota_bucket, 1, :byte_size, 0)
             ON DUPLICATE KEY UPDATE
                 asset_count = asset_count + 1,
                 original_bytes = original_bytes + :byte_size',
            ['tenant_id' => $tenantId, 'quota_bucket' => $quotaBucket, 'byte_size' => $byteSize],
        );
    }

    public function decrementUsage(string $tenantId, string $quotaBucket, int $byteSize): void
    {
        $this->adapter()->execute(
            'UPDATE media_quota_usage
             SET asset_count = GREATEST(0, asset_count - 1),
                 original_bytes = GREATEST(0, original_bytes - :byte_size)
             WHERE tenant_id = :tenant_id AND quota_bucket = :quota_bucket',
            ['tenant_id' => $tenantId, 'quota_bucket' => $quotaBucket, 'byte_size' => $byteSize],
        );
    }

    private function repository(): DomainRepository
    {
        return $this->repository ??= $this->orm()->repository(
            MediaQuotaUsageTableModel::class,
            MediaQuotaUsageResource::class,
        );
    }

    private function orm(): OrmManager
    {
        return $this->orm ??= new OrmManager();
    }

    private function adapter(): \Semitexa\Orm\Adapter\DatabaseAdapterInterface
    {
        return $this->orm()->getAdapter();
    }

    private function copyIntoMutableResource(object $source, MediaQuotaUsageResource $target): void
    {
        $source instanceof MediaQuotaUsageResource || throw new \InvalidArgumentException('Unexpected persisted resource.');

        foreach (get_object_vars($source) as $property => $value) {
            $target->{$property} = $value;
        }
    }
}
