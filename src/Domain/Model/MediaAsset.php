<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Model;

use Semitexa\Media\Enum\MediaAssetStatus;
use Semitexa\Media\Enum\MediaKind;
use Semitexa\Media\Enum\MediaVisibility;

final class MediaAsset
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $collectionKey,
        public readonly string $storageDriver,
        public readonly string $originalPath,
        public readonly string $originalFilename,
        public readonly string $mimeType,
        public readonly MediaKind $mediaKind,
        public readonly MediaVisibility $visibility,
        public MediaAssetStatus $status,
        public readonly int $byteSize,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly ?string $orientation,
        public readonly string $sha256,
        public ?string $altText,
        public array $metadataJson,
        public readonly ?string $createdBy,
        public ?\DateTimeImmutable $readyAt = null,
        public ?\DateTimeImmutable $deletedAt = null,
    ) {}

    public function markReady(): void
    {
        $this->status  = MediaAssetStatus::Ready;
        $this->readyAt = new \DateTimeImmutable();
    }

    public function markFailed(): void
    {
        $this->status = MediaAssetStatus::Failed;
    }

    public function markDeleted(): void
    {
        $this->status    = MediaAssetStatus::Deleted;
        $this->deletedAt = new \DateTimeImmutable();
    }
}
