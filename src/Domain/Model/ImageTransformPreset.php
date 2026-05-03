<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Model;

use Semitexa\Media\Domain\Enum\OutputFormat;
use Semitexa\Media\Domain\Enum\ResizeMode;

final readonly class ImageTransformPreset
{
    public function __construct(
        public string $variantKey,
        public ResizeMode $mode,
        public ?int $width,
        public ?int $height,
        public OutputFormat $format,
        public int $quality,
        public bool $stripMetadata = true,
        public ?string $backgroundFill = null,
    ) {}

    public static function fromArray(string $variantKey, array $data): self
    {
        return new self(
            variantKey:    $variantKey,
            mode:          ResizeMode::from($data['mode'] ?? ResizeMode::Fit->value),
            width:         isset($data['width']) ? (int) $data['width'] : null,
            height:        isset($data['height']) ? (int) $data['height'] : null,
            format:        OutputFormat::from($data['format'] ?? OutputFormat::Webp->value),
            quality:       isset($data['quality']) ? (int) $data['quality'] : 82,
            stripMetadata: $data['strip_metadata'] ?? true,
            backgroundFill: $data['background_fill'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'mode'             => $this->mode->value,
            'width'            => $this->width,
            'height'           => $this->height,
            'format'           => $this->format->value,
            'quality'          => $this->quality,
            'strip_metadata'   => $this->stripMetadata,
            'background_fill'  => $this->backgroundFill,
        ];
    }
}
