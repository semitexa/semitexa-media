<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Repository;

use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Attribute\SatisfiesRepositoryContract;
use Semitexa\Media\Application\Db\MySQL\Model\MediaVariantResource;
use Semitexa\Media\Application\Db\MySQL\Model\MediaVariantTableModel;
use Semitexa\Media\Contract\MediaVariantRepositoryInterface;
use Semitexa\Orm\OrmManager;
use Semitexa\Orm\Query\Operator;
use Semitexa\Orm\Repository\DomainRepository;

#[SatisfiesRepositoryContract(of: MediaVariantRepositoryInterface::class)]
class MediaVariantRepository implements MediaVariantRepositoryInterface
{
    use AssertsExpectedResourceType;

    protected function getResourceClass(): string
    {
        return MediaVariantResource::class;
    }

    #[InjectAsReadonly]
    protected ?OrmManager $orm = null;

    private ?DomainRepository $repository = null;

    public function findByAssetAndKey(string $assetId, string $variantKey): ?MediaVariantResource
    {
        /** @var MediaVariantResource|null */
        return $this->repository()->query()
            ->where(MediaVariantTableModel::column('media_asset_id'), Operator::Equals, $assetId)
            ->where(MediaVariantTableModel::column('variant_key'), Operator::Equals, $variantKey)
            ->fetchOneAs(MediaVariantResource::class, $this->orm()->getMapperRegistry());
    }

    public function findByAssetId(string $assetId): array
    {
        /** @var list<MediaVariantResource> */
        return $this->repository()->query()
            ->where(MediaVariantTableModel::column('media_asset_id'), Operator::Equals, $assetId)
            ->fetchAllAs(MediaVariantResource::class, $this->orm()->getMapperRegistry());
    }

    public function save(MediaVariantResource $entity): void
    {
        $resource = $this->assertResourceType($entity);
        $persisted = $resource->id === ''
            ? $this->repository()->insert($resource)
            : $this->repository()->update($resource);

        $this->copyIntoMutableResource($persisted, $resource);
    }

    public function claimNext(string $leaseOwner, int $leaseDurationSeconds = 300): ?MediaVariantResource
    {
        $now = date('Y-m-d H:i:s');
        $leaseExpires = date('Y-m-d H:i:s', time() + $leaseDurationSeconds);

        $this->adapter()->execute(
            "UPDATE media_variants
             SET status = 'processing',
                 lease_owner = :lease_owner,
                 lease_expires_at = :lease_expires_at,
                 last_attempt_at = :now,
                 attempt_count = attempt_count + 1,
                 processing_started_at = CASE WHEN processing_started_at IS NULL THEN :now ELSE processing_started_at END
             WHERE id = (
                 SELECT id FROM (
                     SELECT id FROM media_variants
                     WHERE (status = 'queued' OR (status = 'processing' AND lease_expires_at < :now))
                       AND attempt_count < max_attempts
                     ORDER BY queued_at ASC
                     LIMIT 1
                 ) AS sub
             )",
            [
                'lease_owner' => $leaseOwner,
                'lease_expires_at' => $leaseExpires,
                'now' => $now,
            ],
        );

        /** @var MediaVariantResource|null */
        return $this->repository()->query()
            ->where(MediaVariantTableModel::column('lease_owner'), Operator::Equals, $leaseOwner)
            ->where(MediaVariantTableModel::column('status'), Operator::Equals, 'processing')
            ->fetchOneAs(MediaVariantResource::class, $this->orm()->getMapperRegistry());
    }

    public function findFailed(int $limit = 100): array
    {
        /** @var list<MediaVariantResource> */
        return $this->repository()->query()
            ->where(MediaVariantTableModel::column('status'), Operator::Equals, 'failed')
            ->limit($limit)
            ->fetchAllAs(MediaVariantResource::class, $this->orm()->getMapperRegistry());
    }

    public function findFailedByAssetId(string $assetId): array
    {
        /** @var list<MediaVariantResource> */
        return $this->repository()->query()
            ->where(MediaVariantTableModel::column('media_asset_id'), Operator::Equals, $assetId)
            ->where(MediaVariantTableModel::column('status'), Operator::Equals, 'failed')
            ->fetchAllAs(MediaVariantResource::class, $this->orm()->getMapperRegistry());
    }

    private function repository(): DomainRepository
    {
        return $this->repository ??= $this->orm()->repository(
            MediaVariantTableModel::class,
            MediaVariantResource::class,
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

    private function copyIntoMutableResource(object $source, MediaVariantResource $target): void
    {
        $source instanceof MediaVariantResource || throw new \InvalidArgumentException('Unexpected persisted resource.');

        foreach (get_object_vars($source) as $property => $value) {
            $target->{$property} = $value;
        }
    }
}
