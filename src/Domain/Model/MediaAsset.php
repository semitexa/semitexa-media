<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Model;

use Semitexa\Media\Domain\Enum\MediaAssetStatus;

/**
 * An uploaded original: the file itself, what is known about it, and how far
 * through ingestion it has got.
 *
 * `sha256` is what makes an upload idempotent — the same bytes arriving twice
 * for one tenant is the same asset, not a second one — and `status` is the
 * ingestion story: pending until the bytes are stored and probed, ready once
 * they are, and only then may variants be planned.
 */
final readonly class MediaAsset
{
    /**
     * @param array<string, mixed> $metadata whatever the probe learned beyond the columns
     */
    public function __construct(
        private string $id,
        private ?string $tenantId,
        private string $collectionKey,
        private string $storageDriver,
        private string $originalPath,
        private string $originalFilename,
        private string $mimeType,
        private string $mediaKind,
        private string $visibility,
        private string $status,
        private int $byteSize,
        private string $sha256,
        private ?int $width = null,
        private ?int $height = null,
        private ?string $orientation = null,
        private ?string $altText = null,
        private array $metadata = [],
        private ?string $createdBy = null,
        private ?\DateTimeImmutable $readyAt = null,
        private ?\DateTimeImmutable $deletedAt = null,
        private ?\DateTimeImmutable $createdAt = null,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function getCollectionKey(): string
    {
        return $this->collectionKey;
    }

    public function getStorageDriver(): string
    {
        return $this->storageDriver;
    }

    public function getOriginalPath(): string
    {
        return $this->originalPath;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getMediaKind(): string
    {
        return $this->mediaKind;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getByteSize(): int
    {
        return $this->byteSize;
    }

    public function getSha256(): string
    {
        return $this->sha256;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function getOrientation(): ?string
    {
        return $this->orientation;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function getReadyAt(): ?\DateTimeImmutable
    {
        return $this->readyAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function statusEnum(): MediaAssetStatus
    {
        return MediaAssetStatus::tryFrom($this->status) ?? MediaAssetStatus::Pending;
    }

    /** Stored and probed — only now is it safe to plan variants from it. */
    public function isReady(): bool
    {
        return $this->statusEnum() === MediaAssetStatus::Ready;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * @param array<string, mixed> $changes
     */
    public function with(array $changes): self
    {
        $str = fn (string $k, string $current): string => is_string($changes[$k] ?? null) ? $changes[$k] : $current;
        $date = fn (string $k, ?\DateTimeImmutable $current): ?\DateTimeImmutable => array_key_exists($k, $changes)
            ? ($changes[$k] instanceof \DateTimeImmutable ? $changes[$k] : null)
            : $current;

        return new self(
            id: $str('id', $this->id),
            tenantId: $this->tenantId,
            collectionKey: $this->collectionKey,
            storageDriver: $str('storageDriver', $this->storageDriver),
            originalPath: $str('originalPath', $this->originalPath),
            originalFilename: $this->originalFilename,
            mimeType: $this->mimeType,
            mediaKind: $this->mediaKind,
            visibility: $str('visibility', $this->visibility),
            status: $str('status', $this->status),
            byteSize: $this->byteSize,
            sha256: $this->sha256,
            width: $this->width,
            height: $this->height,
            orientation: $this->orientation,
            altText: array_key_exists('altText', $changes)
                ? (is_string($changes['altText']) ? $changes['altText'] : null)
                : $this->altText,
            metadata: $this->metadata,
            createdBy: $this->createdBy,
            readyAt: $date('readyAt', $this->readyAt),
            deletedAt: $date('deletedAt', $this->deletedAt),
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable(),
        );
    }
}
