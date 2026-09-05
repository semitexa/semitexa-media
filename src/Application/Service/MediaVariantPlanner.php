<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Media\Domain\Model\MediaVariant;
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
     * @return MediaVariant[]
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

            $planned[] = $this->variantRepository->save(new MediaVariant(
                id: '',
                tenantId: $tenantId,
                mediaAssetId: $assetId,
                variantKey: $preset->variantKey,
                status: MediaVariantStatus::Queued->value,
                resizeMode: $preset->mode->value,
                targetWidth: $preset->width,
                targetHeight: $preset->height,
                quality: $preset->quality,
                maxAttempts: $maxAttempts,
                queuedAt: new \DateTimeImmutable(),
            ));
        }

        return $planned;
    }
}
