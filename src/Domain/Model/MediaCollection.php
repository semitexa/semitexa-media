<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Model;

use Semitexa\Media\Domain\Enum\MediaKind;
use Semitexa\Media\Domain\Enum\MediaVisibility;
use Semitexa\Media\Domain\Model\ImageTransformPreset;

final readonly class MediaCollection
{
    /**
     * @param string[] $allowedMimeTypes
     * @param ImageTransformPreset[] $transformPresets
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private string $collectionKey,
        private MediaKind $mediaKind,
        private MediaVisibility $visibilityDefault,
        private string $quotaBucket,
        private array $allowedMimeTypes,
        private array $transformPresets,
        private ?int $maxOriginalBytes = null,
        private ?int $maxWidth = null,
        private ?int $maxHeight = null,
        private ?int $maxAssetCount = null,
        private ?string $tenantId = null,
        /** Null for a collection declared in code rather than stored in a row. */
        private ?string $id = null,
        private bool $enabled = true,
        /**
         * The row's own free-form bag, and its audit stamps. Nothing in this
         * package reads them — they are carried so a save round-trips. The
         * write engine writes every column of the resource it is handed, so a
         * field the mapper cannot rebuild is a field an update erases.
         */
        private array $metadata = [],
        private ?\DateTimeImmutable $createdAt = null,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {}

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getCollectionKey(): string
    {
        return $this->collectionKey;
    }

    public function getMediaKind(): MediaKind
    {
        return $this->mediaKind;
    }

    public function getVisibilityDefault(): MediaVisibility
    {
        return $this->visibilityDefault;
    }

    public function getQuotaBucket(): string
    {
        return $this->quotaBucket;
    }

    /** @return string[] */
    public function getAllowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }

    /** @return ImageTransformPreset[] */
    public function getTransformPresets(): array
    {
        return $this->transformPresets;
    }

    public function getMaxOriginalBytes(): ?int
    {
        return $this->maxOriginalBytes;
    }

    public function getMaxWidth(): ?int
    {
        return $this->maxWidth;
    }

    public function getMaxHeight(): ?int
    {
        return $this->maxHeight;
    }

    public function getMaxAssetCount(): ?int
    {
        return $this->maxAssetCount;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /** True when this collection was declared in code rather than loaded from a row. */
    public function isCodeDefined(): bool
    {
        return $this->id === null;
    }

    public function isMimeTypeAllowed(string $mimeType): bool
    {
        if ($this->allowedMimeTypes === []) {
            return true;
        }

        return in_array($mimeType, $this->allowedMimeTypes, true);
    }

    public function findPreset(string $variantKey): ?ImageTransformPreset
    {
        foreach ($this->transformPresets as $preset) {
            if ($preset->variantKey === $variantKey) {
                return $preset;
            }
        }

        return null;
    }
}
