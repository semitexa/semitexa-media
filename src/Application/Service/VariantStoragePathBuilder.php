<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Media\Domain\Enum\OutputFormat;

#[AsService]
final class VariantStoragePathBuilder
{
    /**
     * Build a deterministic storage path for a derived variant.
     *
     * Format: media/{tenantId}/{collectionKey}/{assetId}/{variantKey}.{ext}
     */
    public function build(
        string $tenantId,
        string $collectionKey,
        string $assetId,
        string $variantKey,
        OutputFormat $format,
    ): string {
        return sprintf(
            'media/%s/%s/%s/%s.%s',
            $this->sanitizeSegment($tenantId),
            $this->sanitizeSegment($collectionKey),
            $assetId,
            $this->sanitizeSegment($variantKey),
            $format->toExtension(),
        );
    }

    private function sanitizeSegment(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9\-_]/', '_', $value) ?? $value;
    }
}
