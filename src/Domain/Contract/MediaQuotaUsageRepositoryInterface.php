<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Contract;

use Semitexa\Media\Application\Db\MySQL\Model\MediaQuotaUsageResource;

interface MediaQuotaUsageRepositoryInterface
{
    public function findByBucket(string $tenantId, string $quotaBucket): ?MediaQuotaUsageResource;

    public function save(MediaQuotaUsageResource $entity): void;

    public function incrementUsage(string $tenantId, string $quotaBucket, int $byteSize): void;

    public function decrementUsage(string $tenantId, string $quotaBucket, int $byteSize): void;
}
