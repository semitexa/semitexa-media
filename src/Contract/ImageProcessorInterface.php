<?php

declare(strict_types=1);

namespace Semitexa\Media\Contract;

use Semitexa\Media\Value\ImageMetadata;
use Semitexa\Media\Value\ImageTransformPreset;

interface ImageProcessorInterface
{
    /**
     * Extract image metadata from raw bytes.
     *
     * @throws \Semitexa\Media\Domain\Exception\MediaProcessingException on corrupt or unsupported input
     */
    public function inspect(string $bytes): ImageMetadata;

    /**
     * Apply a transform preset to image bytes and return the resulting bytes.
     *
     * @throws \Semitexa\Media\Domain\Exception\MediaProcessingException on processing failure
     */
    public function transform(string $bytes, ImageTransformPreset $preset): string;

    public function isAvailable(): bool;
}
