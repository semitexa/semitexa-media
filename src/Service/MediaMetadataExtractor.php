<?php

declare(strict_types=1);

namespace Semitexa\Media\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Media\Contract\ImageProcessorInterface;
use Semitexa\Media\Domain\Exception\MediaIngestException;
use Semitexa\Media\Value\ImageMetadata;

#[AsService]
final class MediaMetadataExtractor
{
    #[InjectAsReadonly]
    protected ImageProcessorInterface $imageProcessor;

    /**
     * Extract metadata from raw image bytes.
     *
     * @throws MediaIngestException
     */
    public function extract(string $bytes, string $mimeType): ImageMetadata
    {
        if ($bytes === '') {
            throw new MediaIngestException('Image bytes are empty.');
        }

        try {
            $metadata = $this->imageProcessor->inspect($bytes);
        } catch (\Throwable $e) {
            throw new MediaIngestException("Failed to inspect image: {$e->getMessage()}", $e);
        }

        // Recompute hash if not already set (should be set by processor)
        return $metadata;
    }

    /**
     * Compute SHA-256 hash of raw bytes.
     */
    public function computeHash(string $bytes): string
    {
        return hash('sha256', $bytes);
    }
}
