<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

final class OriginalStoragePathBuilder
{
    /**
     * Build a deterministic storage path for the original asset.
     *
     * Format: media/{tenantId}/{collectionKey}/{assetId}/original.{ext}
     */
    public function build(string $tenantId, string $collectionKey, string $assetId, string $mimeType): string
    {
        $ext = $this->extensionFromMimeType($mimeType);

        return sprintf(
            'media/%s/%s/%s/original.%s',
            MediaPathSegment::of($tenantId),
            MediaPathSegment::of($collectionKey),
            $assetId,
            $ext,
        );
    }

    private function extensionFromMimeType(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'image/bmp'  => 'bmp',
            'image/tiff' => 'tiff',
            default      => 'bin',
        };
    }
}
