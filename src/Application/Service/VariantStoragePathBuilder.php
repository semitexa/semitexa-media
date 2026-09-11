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
            MediaPathSegment::of($tenantId),
            MediaPathSegment::of($collectionKey),
            $assetId,
            MediaPathSegment::of($variantKey),
            $format->toExtension(),
        );
    }
}
