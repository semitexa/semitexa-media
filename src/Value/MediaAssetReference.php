<?php

declare(strict_types=1);

namespace Semitexa\Media\Value;

final readonly class MediaAssetReference
{
    /**
     * @param array<string, string> $variants Map of variantKey => URL (may be empty if not yet ready)
     */
    public function __construct(
        public string $assetId,
        public string $collectionKey,
        public string $originalUrl,
        public array $variants = [],
    ) {}
}
