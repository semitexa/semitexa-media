<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Model;

final readonly class ImageMetadata
{
    public function __construct(
        public int $width,
        public int $height,
        public string $mimeType,
        public string $sha256,
        public int $byteSize,
        public ?string $orientation = null,
        public array $extra = [],
    ) {}
}
