<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Contract;

use Semitexa\Media\Application\Db\MySQL\Model\MediaCollectionResource;

interface MediaCollectionRepositoryInterface
{
    public function findActive(string $collectionKey, ?string $tenantId = null): ?MediaCollectionResource;

    /**
     * Persist and RETURN the stored row — resources are readonly; the write
     * engine's generated id/state comes back on a fresh instance.
     */
    public function save(MediaCollectionResource $entity): MediaCollectionResource;

    /**
     * @return MediaCollectionResource[]
     */
    public function findAllEnabled(): array;
}
