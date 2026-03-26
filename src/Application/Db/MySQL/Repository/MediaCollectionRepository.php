<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Repository;

use Semitexa\Core\Attributes\SatisfiesRepositoryContract;
use Semitexa\Media\Application\Db\MySQL\Model\MediaCollectionResource;
use Semitexa\Media\Contract\MediaCollectionRepositoryInterface;
use Semitexa\Orm\Repository\AbstractRepository;

#[SatisfiesRepositoryContract(of: MediaCollectionRepositoryInterface::class)]
class MediaCollectionRepository extends AbstractRepository implements MediaCollectionRepositoryInterface
{
    protected function getResourceClass(): string
    {
        return MediaCollectionResource::class;
    }

    public function findActive(string $collectionKey, ?string $tenantId = null): ?MediaCollectionResource
    {
        // Prefer tenant-specific row; fall back to global (tenant_id IS NULL)
        if ($tenantId !== null) {
            $row = $this->select()
                ->where('collection_key', '=', $collectionKey)
                ->where('tenant_id', '=', $tenantId)
                ->where('enabled', '=', 1)
                ->fetchOneAsResource();

            if ($row !== null) {
                return $row;
            }
        }

        return $this->select()
            ->where('collection_key', '=', $collectionKey)
            ->whereNull('tenant_id')
            ->where('enabled', '=', 1)
            ->fetchOneAsResource();
    }

    public function save(MediaCollectionResource $resource): void
    {
        parent::save($resource);
    }

    public function findAllEnabled(): array
    {
        return $this->select()
            ->where('enabled', '=', 1)
            ->fetchAll();
    }
}
