<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Model;

use Semitexa\Media\Enum\MediaKind;
use Semitexa\Media\Enum\MediaVisibility;
use Semitexa\Media\Value\ImageTransformPreset;

final readonly class MediaCollection
{
    /**
     * @param string[] $allowedMimeTypes
     * @param ImageTransformPreset[] $transformPresets
     */
    public function __construct(
        public string $collectionKey,
        public MediaKind $mediaKind,
        public MediaVisibility $visibilityDefault,
        public string $quotaBucket,
        public array $allowedMimeTypes,
        public array $transformPresets,
        public ?int $maxOriginalBytes = null,
        public ?int $maxWidth = null,
        public ?int $maxHeight = null,
        public ?int $maxAssetCount = null,
        public ?string $tenantId = null,
    ) {}

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
