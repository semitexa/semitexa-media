<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Model;

final class MediaQuotaUsage
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $quotaBucket,
        public int $assetCount,
        public int $originalBytes,
        public int $variantBytes,
        public ?\DateTimeImmutable $lastRecalculatedAt = null,
    ) {}
}
