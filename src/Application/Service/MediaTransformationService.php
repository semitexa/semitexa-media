<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Media\Domain\Model\MediaVariant;
use Semitexa\Media\Domain\Contract\ImageProcessorInterface;
use Semitexa\Media\Domain\Exception\MediaProcessingException;
use Semitexa\Media\Domain\Model\MediaCollection;
use Semitexa\Media\Domain\Model\VariantGenerationResult;
use Semitexa\Storage\Contract\StorageObjectStoreInterface;

#[AsService]
final class MediaTransformationService
{
    #[InjectAsReadonly]
    protected ImageProcessorInterface $imageProcessor;

    #[InjectAsReadonly]
    protected VariantStoragePathBuilder $pathBuilder;

    #[InjectAsReadonly]
    protected StorageObjectStoreInterface $storage;

    public function generateVariant(
        string $originalPath,
        string $assetId,
        string $tenantId,
        MediaVariant $variant,
        MediaCollection $collection,
    ): VariantGenerationResult {
        $preset = $collection->findPreset($variant->getVariantKey());

        if ($preset === null) {
            return VariantGenerationResult::failure(
                'preset_not_found',
                "Transform preset '{$variant->getVariantKey()}' not found in collection '{$collection->getCollectionKey()}'.",
            );
        }

        $originalBytes = $this->storage->get($originalPath);
        if ($originalBytes === null) {
            return VariantGenerationResult::failure(
                'original_not_found',
                "Original asset not found at path '{$originalPath}'.",
            );
        }

        try {
            $transformedBytes = $this->imageProcessor->transform($originalBytes, $preset);
        } catch (MediaProcessingException $e) {
            return VariantGenerationResult::failure('processing_error', $e->getMessage());
        }

        $storagePath = $this->pathBuilder->build(
            $tenantId,
            $collection->getCollectionKey(),
            $assetId,
            $variant->getVariantKey(),
            $preset->format,
        );

        $this->storage->put($storagePath, $transformedBytes, $preset->format->toMimeType());

        // Detect actual dimensions from the result
        try {
            $resultMeta  = $this->imageProcessor->inspect($transformedBytes);
            $actualWidth  = $resultMeta->width;
            $actualHeight = $resultMeta->height;
        } catch (\Throwable) {
            $actualWidth  = $preset->width;
            $actualHeight = $preset->height;
        }

        return VariantGenerationResult::success(
            storagePath:  $storagePath,
            mimeType:     $preset->format->toMimeType(),
            byteSize:     strlen($transformedBytes),
            actualWidth:  $actualWidth ?? 0,
            actualHeight: $actualHeight ?? 0,
        );
    }
}
