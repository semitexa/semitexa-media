<?php

declare(strict_types=1);

namespace Semitexa\Media\Service;

use Semitexa\Media\Contract\MediaCollectionRepositoryInterface;
use Semitexa\Media\Domain\Exception\MediaCollectionNotFoundException;
use Semitexa\Media\Domain\Model\MediaCollection;
use Semitexa\Media\Enum\MediaKind;
use Semitexa\Media\Enum\MediaVisibility;
use Semitexa\Media\Value\ImageTransformPreset;

/**
 * Resolves the active MediaCollection for a given collection key and optional tenant.
 *
 * Resolution order:
 * 1. Code-defined registry (in-memory, always wins if defined)
 * 2. DB-persisted tenant-specific row
 * 3. DB-persisted global row (tenant_id IS NULL)
 */
final class MediaCollectionPolicyResolver
{
    public function __construct(
        private readonly MediaCollectionRegistry $registry,
        private readonly MediaCollectionRepositoryInterface $collectionRepository,
    ) {}

    public function resolve(string $collectionKey, ?string $tenantId = null): MediaCollection
    {
        // Code-defined takes priority
        $collection = $this->registry->get($collectionKey);
        if ($collection !== null) {
            return $collection;
        }

        // Fall back to DB-persisted
        $resource = $this->collectionRepository->findActive($collectionKey, $tenantId);
        if ($resource === null) {
            throw new MediaCollectionNotFoundException($collectionKey, $tenantId);
        }

        $allowedMimeTypes = json_decode($resource->allowed_mime_types_json, true) ?? [];
        $transformProfile = json_decode($resource->transform_profile_json, true) ?? [];

        $presets = [];
        foreach ($transformProfile as $variantKey => $presetData) {
            $presets[] = ImageTransformPreset::fromArray(
                is_string($variantKey) ? $variantKey : ($presetData['variantKey'] ?? ''),
                is_string($variantKey) ? $presetData : $presetData,
            );
        }

        return new MediaCollection(
            collectionKey:    $resource->collection_key,
            mediaKind:        MediaKind::from($resource->media_kind),
            visibilityDefault: MediaVisibility::from($resource->visibility_default),
            quotaBucket:      $resource->quota_bucket,
            allowedMimeTypes: $allowedMimeTypes,
            transformPresets: $presets,
            maxOriginalBytes: $resource->max_original_bytes,
            maxWidth:         $resource->max_width,
            maxHeight:        $resource->max_height,
            maxAssetCount:    $resource->max_asset_count,
            tenantId:         $resource->tenant_id,
        );
    }
}
