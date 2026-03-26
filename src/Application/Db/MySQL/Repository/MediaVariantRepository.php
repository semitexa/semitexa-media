<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Repository;

use Semitexa\Core\Attributes\SatisfiesRepositoryContract;
use Semitexa\Media\Application\Db\MySQL\Model\MediaVariantResource;
use Semitexa\Media\Contract\MediaVariantRepositoryInterface;
use Semitexa\Orm\Repository\AbstractRepository;
use Semitexa\Orm\Uuid\Uuid7;

#[SatisfiesRepositoryContract(of: MediaVariantRepositoryInterface::class)]
class MediaVariantRepository extends AbstractRepository implements MediaVariantRepositoryInterface
{
    use AssertsExpectedResourceType;

    protected function getResourceClass(): string
    {
        return MediaVariantResource::class;
    }

    public function findByAssetAndKey(string $assetId, string $variantKey): ?MediaVariantResource
    {
        return $this->select()
            ->where('media_asset_id', '=', Uuid7::toBytes($assetId))
            ->where('variant_key', '=', $variantKey)
            ->fetchOneAsResource();
    }

    public function findByAssetId(string $assetId): array
    {
        return $this->select()
            ->where('media_asset_id', '=', Uuid7::toBytes($assetId))
            ->fetchAll();
    }

    public function save(object $resource): void
    {
        parent::save($this->assertResourceType($resource));
    }

    public function claimNext(string $leaseOwner, int $leaseDurationSeconds = 300): ?MediaVariantResource
    {
        $now          = date('Y-m-d H:i:s');
        $leaseExpires = date('Y-m-d H:i:s', time() + $leaseDurationSeconds);

        // Atomic claim: UPDATE the next eligible row and then SELECT it back by lease_owner
        $this->getAdapter()->execute(
            'UPDATE media_variants
             SET status = \'processing\',
                 lease_owner = :lease_owner,
                 lease_expires_at = :lease_expires_at,
                 last_attempt_at = :now,
                 attempt_count = attempt_count + 1,
                 processing_started_at = CASE WHEN processing_started_at IS NULL THEN :now ELSE processing_started_at END
             WHERE id = (
                 SELECT id FROM (
                     SELECT id FROM media_variants
                     WHERE (status = \'queued\' OR (status = \'processing\' AND lease_expires_at < :now))
                       AND attempt_count < max_attempts
                     ORDER BY queued_at ASC
                     LIMIT 1
                 ) AS sub
             )',
            [
                'lease_owner'      => $leaseOwner,
                'lease_expires_at' => $leaseExpires,
                'now'              => $now,
            ],
        );

        return $this->select()
            ->where('lease_owner', '=', $leaseOwner)
            ->where('status', '=', 'processing')
            ->fetchOneAsResource();
    }

    public function findFailed(int $limit = 100): array
    {
        return $this->select()
            ->where('status', '=', 'failed')
            ->limit($limit)
            ->fetchAll();
    }

    public function findFailedByAssetId(string $assetId): array
    {
        return $this->select()
            ->where('media_asset_id', '=', Uuid7::toBytes($assetId))
            ->where('status', '=', 'failed')
            ->fetchAll();
    }
}
