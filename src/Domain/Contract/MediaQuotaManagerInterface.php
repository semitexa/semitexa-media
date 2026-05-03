<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Contract;

use Semitexa\Media\Domain\Model\MediaCollection;

interface MediaQuotaManagerInterface
{
    public function checkAndReserve(string $tenantId, MediaCollection $collection, int $byteSize): void;

    public function release(string $tenantId, string $quotaBucket, int $byteSize): void;

    public function recalculate(string $tenantId, string $quotaBucket): void;
}
