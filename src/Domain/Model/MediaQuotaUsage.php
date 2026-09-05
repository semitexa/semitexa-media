<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Model;

/**
 * How much of a quota bucket a tenant has used.
 *
 * Originals and variants are counted apart on purpose: an original is what
 * someone uploaded and cannot be regenerated, while variants are derived and
 * can be rebuilt, so a bucket close to its limit is a different problem
 * depending on which half is large.
 */
final readonly class MediaQuotaUsage
{
    public function __construct(
        private string $id,
        private string $tenantId,
        private string $quotaBucket = 'default',
        private int $assetCount = 0,
        private int $originalBytes = 0,
        private int $variantBytes = 0,
        private ?\DateTimeImmutable $lastRecalculatedAt = null,
        private ?\DateTimeImmutable $createdAt = null,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getQuotaBucket(): string
    {
        return $this->quotaBucket;
    }

    public function getAssetCount(): int
    {
        return $this->assetCount;
    }

    public function getOriginalBytes(): int
    {
        return $this->originalBytes;
    }

    public function getVariantBytes(): int
    {
        return $this->variantBytes;
    }

    public function getLastRecalculatedAt(): ?\DateTimeImmutable
    {
        return $this->lastRecalculatedAt;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** Everything the bucket holds, uploads and derivatives together. */
    public function getTotalBytes(): int
    {
        return $this->originalBytes + $this->variantBytes;
    }

    /**
     * @param array<string, mixed> $changes
     */
    public function with(array $changes): self
    {
        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            quotaBucket: is_string($changes['quotaBucket'] ?? null) ? $changes['quotaBucket'] : $this->quotaBucket,
            assetCount: is_int($changes['assetCount'] ?? null) ? $changes['assetCount'] : $this->assetCount,
            originalBytes: is_int($changes['originalBytes'] ?? null) ? $changes['originalBytes'] : $this->originalBytes,
            variantBytes: is_int($changes['variantBytes'] ?? null) ? $changes['variantBytes'] : $this->variantBytes,
            lastRecalculatedAt: array_key_exists('lastRecalculatedAt', $changes)
                ? ($changes['lastRecalculatedAt'] instanceof \DateTimeImmutable ? $changes['lastRecalculatedAt'] : null)
                : $this->lastRecalculatedAt,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable(),
        );
    }
}
