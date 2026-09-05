<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Media\Domain\Contract\MediaCollectionRepositoryInterface;
use Semitexa\Media\Domain\Exception\MediaCollectionNotFoundException;
use Semitexa\Media\Domain\Model\MediaCollection;

/**
 * Resolves the active MediaCollection for a given collection key and optional tenant.
 *
 * Resolution order:
 * 1. Code-defined registry (in-memory, always wins if defined)
 * 2. DB-persisted tenant-specific row
 * 3. DB-persisted global row (tenant_id IS NULL)
 */
#[AsService]
final class MediaCollectionPolicyResolver
{
    #[InjectAsReadonly]
    protected MediaCollectionRegistry $registry;

    #[InjectAsReadonly]
    protected MediaCollectionRepositoryInterface $collectionRepository;

    public function resolve(string $collectionKey, ?string $tenantId = null): MediaCollection
    {
        // Code-defined takes priority
        $collection = $this->registry->get($collectionKey);
        if ($collection !== null) {
            return $collection;
        }

        // Fall back to DB-persisted. Decoding the row is the mapper's job now,
        // so this reads as what it is: code wins, else the table, else nothing.
        return $this->collectionRepository->findActive($collectionKey, $tenantId)
            ?? throw new MediaCollectionNotFoundException($collectionKey, $tenantId);
    }
}
