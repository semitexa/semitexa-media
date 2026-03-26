<?php

declare(strict_types=1);

namespace Semitexa\Media\Contract;

use Semitexa\Media\Application\Db\MySQL\Model\MediaCollectionResource;

interface MediaCollectionRepositoryInterface
{
    public function findActive(string $collectionKey, ?string $tenantId = null): ?MediaCollectionResource;

    public function save(object $entity): void;

    /**
     * @return MediaCollectionResource[]
     */
    public function findAllEnabled(): array;
}
