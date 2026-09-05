<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Contract;

use Semitexa\Media\Domain\Model\MediaCollection;

interface MediaCollectionRepositoryInterface
{
    public function findActive(string $collectionKey, ?string $tenantId = null): ?MediaCollection;

    /**
     * Persist and RETURN the stored row — resources are readonly; the write
     * engine's generated id/state comes back on a fresh instance.
     */
    public function save(MediaCollection $entity): MediaCollection;

    /**
     * @return MediaCollection[]
     */
    public function findAllEnabled(): array;
}
