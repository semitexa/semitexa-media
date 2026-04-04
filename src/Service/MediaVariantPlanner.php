<?php

declare(strict_types=1);

namespace Semitexa\Media\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Media\Application\Db\MySQL\Model\MediaVariantResource;
use Semitexa\Media\Contract\MediaVariantRepositoryInterface;
use Semitexa\Media\Domain\Model\MediaCollection;
use Semitexa\Media\Enum\MediaVariantStatus;

#[AsService]
final class MediaVariantPlanner
{
    #[InjectAsReadonly]
    protected MediaVariantRepositoryInterface $variantRepository;

    /**
     * Create queued variant rows for all presets in the collection.
     * Skips variant keys that already have a row.
     *
     * @return MediaVariantResource[]
     */
    public function plan(
        string $assetId,
        string $tenantId,
        MediaCollection $collection,
        int $maxAttempts,
    ): array {
        $planned = [];

        foreach ($collection->transformPresets as $preset) {
            $existing = $this->variantRepository->findByAssetAndKey($assetId, $preset->variantKey);

            if ($existing !== null) {
                continue;
            }

            $resource                       = new MediaVariantResource();
            $resource->tenant_id            = $tenantId;
            $resource->media_asset_id       = $assetId;
            $resource->variant_key          = $preset->variantKey;
            $resource->status               = MediaVariantStatus::Queued->value;
            $resource->resize_mode          = $preset->mode->value;
            $resource->target_width         = $preset->width;
            $resource->target_height        = $preset->height;
            $resource->quality              = $preset->quality;
            $resource->max_attempts         = $maxAttempts;
            $resource->queued_at            = new \DateTimeImmutable();

            $this->variantRepository->save($resource);

            $planned[] = $resource;
        }

        return $planned;
    }
}
