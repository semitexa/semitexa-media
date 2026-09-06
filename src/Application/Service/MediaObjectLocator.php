<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Media\Domain\Contract\MediaAssetRepositoryInterface;
use Semitexa\Media\Domain\Contract\MediaVariantRepositoryInterface;
use Semitexa\Media\Domain\Enum\MediaVariantStatus;
use Semitexa\Media\Domain\Model\LocatedMediaObject;
use Semitexa\Tenancy\Context\CoroutineContextStore;

/**
 * Picks the stored object behind an asset: the variant when it is ready, the
 * original otherwise.
 *
 * A freshly ingested image has no variant yet — the queue makes it — so every
 * caller that wants the smaller rendition has to be willing to take the
 * original in the meantime. Both callers that do (the URL generator and the
 * byte reader) must make that choice identically, and two copies of the rule
 * are two copies that can drift, so it lives here once.
 *
 * The asset is looked up before anything else because that lookup is also the
 * authorization: `findById()` carries no tenant predicate, and an asset id is
 * public — it is written into the article markup that names the picture. An
 * unscoped read here would let one tenant's page address another tenant's
 * files. An asset outside the caller's tenant is answered as absent, which is
 * what it is from where the caller stands.
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
        $asset = $this->assetRepository->findById($assetId);

        if ($asset === null || ($asset->getTenantId() ?? '') !== $this->currentTenantId()) {
            return null;
        }

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
                    mimeType: $variant->getMimeType() ?? $asset->getMimeType(),
                    version: $variant->getGeneratedAt(),
                    collectionKey: $asset->getCollectionKey(),
                );
            }
        }

        return new LocatedMediaObject(
            path: $asset->getOriginalPath(),
            mimeType: $asset->getMimeType(),
            version: $asset->getReadyAt() ?? $asset->getCreatedAt(),
            collectionKey: $asset->getCollectionKey(),
        );
    }

    /**
     * The tenant the caller is acting as — '' on an installation that runs
     * without tenancy, which is also what ingest recorded on those assets.
     */
    private function currentTenantId(): string
    {
        return CoroutineContextStore::get()?->getTenantId() ?? '';
    }
}
