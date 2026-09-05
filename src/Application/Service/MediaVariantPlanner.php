<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Media\Application\Db\MySQL\Model\MediaVariantResource;
use Semitexa\Media\Domain\Contract\MediaVariantRepositoryInterface;
use Semitexa\Media\Domain\Model\MediaCollection;
use Semitexa\Media\Domain\Enum\MediaVariantStatus;

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

        foreach ($collection->getTransformPresets() as $preset) {
            $existing = $this->variantRepository->findByAssetAndKey($assetId, $preset->variantKey);

            if ($existing !== null) {
                continue;
            }

            $planned[] = $this->variantRepository->save(new MediaVariantResource(
                tenant_id: $tenantId,
                media_asset_id: $assetId,
                variant_key: $preset->variantKey,
                status: MediaVariantStatus::Queued->value,
                resize_mode: $preset->mode->value,
                target_width: $preset->width,
                target_height: $preset->height,
                quality: $preset->quality,
                max_attempts: $maxAttempts,
                queued_at: new \DateTimeImmutable(),
            ));
        }

        return $planned;
    }
}
