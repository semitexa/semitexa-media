<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Media\Domain\Contract\MediaAssetRepositoryInterface;
use Semitexa\Media\Domain\Contract\MediaVariantRepositoryInterface;
use Semitexa\Media\Domain\Enum\MediaVariantStatus;
use Semitexa\Media\Domain\Model\LocatedMediaObject;

/**
 * Picks the stored object behind an asset: the variant when it is ready, the
 * original otherwise.
 *
 * A freshly ingested image has no variant yet — the queue makes it — so every
 * caller that wants the smaller rendition has to be willing to take the
 * original in the meantime. Both callers that do (the URL generator and the
 * byte reader) must make that choice identically, and two copies of the rule
 * are two copies that can drift, so it lives here once.
 */
#[AsService]
final class MediaObjectLocator
{
    #[InjectAsReadonly]
    protected MediaAssetRepositoryInterface $assetRepository;

    #[InjectAsReadonly]
    protected MediaVariantRepositoryInterface $variantRepository;

    public function locate(string $assetId, ?string $variantKey = null): ?LocatedMediaObject
    {
        if ($variantKey !== null) {
            $variant = $this->variantRepository->findByAssetAndKey($assetId, $variantKey);

            if (
                $variant !== null
                && $variant->getStatus() === MediaVariantStatus::Ready->value
                && $variant->getStoragePath() !== null
            ) {
                return new LocatedMediaObject(
                    path: $variant->getStoragePath(),
                    // A variant records the type it was encoded to; before the
                    // transform ran there is nothing to record, and the asset's
                    // own type is the honest answer.
                    mimeType: $variant->getMimeType() ?? $this->assetMimeType($assetId),
                    version: $variant->getGeneratedAt(),
                );
            }
        }

        $asset = $this->assetRepository->findById($assetId);

        if ($asset === null) {
            return null;
        }

        return new LocatedMediaObject(
            path: $asset->getOriginalPath(),
            mimeType: $asset->getMimeType(),
            version: $asset->getReadyAt() ?? $asset->getCreatedAt(),
        );
    }

    private function assetMimeType(string $assetId): string
    {
        return $this->assetRepository->findById($assetId)?->getMimeType() ?? 'application/octet-stream';
    }
}
