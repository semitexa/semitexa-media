<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Mapper;

use Semitexa\Media\Application\Db\MySQL\Model\MediaCollectionResource;
use Semitexa\Media\Domain\Enum\MediaKind;
use Semitexa\Media\Domain\Enum\MediaVisibility;
use Semitexa\Media\Domain\Model\ImageTransformPreset;
use Semitexa\Media\Domain\Model\MediaCollection;
use Semitexa\Orm\Attribute\AsMapper;
use Semitexa\Orm\Domain\Contract\ResourceModelMapperInterface;

/**
 * The bridge between the MySQL row and a collection's policy.
 *
 * This is the whole reason a mapper exists here rather than a pass-through: the
 * row keeps the allowed types and the transform profile as JSON strings and the
 * kind and visibility as loose strings, while the policy is arrays and enums.
 * That conversion used to sit in MediaCollectionPolicyResolver, which meant a
 * service knew how the table encodes itself.
 */
#[AsMapper(resourceModel: MediaCollectionResource::class, domainModel: MediaCollection::class)]
final class MediaCollectionMapper implements ResourceModelMapperInterface
{
    public function toDomain(object $resourceModel): object
    {
        $resourceModel instanceof MediaCollectionResource
            || throw new \InvalidArgumentException('Unexpected resource model.');

        $allowed = json_decode($resourceModel->allowed_mime_types_json, true);
        $profile = json_decode($resourceModel->transform_profile_json, true);

        return new MediaCollection(
            collectionKey: $resourceModel->collection_key,
            mediaKind: MediaKind::from($resourceModel->media_kind),
            visibilityDefault: MediaVisibility::from($resourceModel->visibility_default),
            quotaBucket: $resourceModel->quota_bucket,
            allowedMimeTypes: is_array($allowed) ? array_values(array_filter($allowed, 'is_string')) : [],
            transformPresets: $this->presets(is_array($profile) ? $profile : []),
            maxOriginalBytes: $resourceModel->max_original_bytes,
            maxWidth: $resourceModel->max_width,
            maxHeight: $resourceModel->max_height,
            maxAssetCount: $resourceModel->max_asset_count,
            tenantId: $resourceModel->tenant_id,
            id: $resourceModel->id,
            enabled: $resourceModel->enabled === 1,
        );
    }

    public function toSourceModel(object $domainModel): object
    {
        $domainModel instanceof MediaCollection || throw new \InvalidArgumentException('Unexpected domain model.');

        $profile = [];
        foreach ($domainModel->getTransformPresets() as $preset) {
            $profile[$preset->variantKey] = $preset->toArray();
        }

        return new MediaCollectionResource(
            id: $domainModel->getId() ?? '',
            tenant_id: $domainModel->getTenantId(),
            collection_key: $domainModel->getCollectionKey(),
            enabled: $domainModel->isEnabled() ? 1 : 0,
            media_kind: $domainModel->getMediaKind()->value,
            visibility_default: $domainModel->getVisibilityDefault()->value,
            quota_bucket: $domainModel->getQuotaBucket(),
            allowed_mime_types_json: (string) json_encode($domainModel->getAllowedMimeTypes(), JSON_UNESCAPED_SLASHES),
            max_original_bytes: $domainModel->getMaxOriginalBytes(),
            max_width: $domainModel->getMaxWidth(),
            max_height: $domainModel->getMaxHeight(),
            max_asset_count: $domainModel->getMaxAssetCount(),
            transform_profile_json: (string) json_encode($profile, JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * @param array<mixed> $profile
     *
     * @return list<ImageTransformPreset>
     */
    private function presets(array $profile): array
    {
        $presets = [];
        foreach ($profile as $variantKey => $presetData) {
            if (!is_array($presetData)) {
                continue;
            }
            $named = $presetData['variantKey'] ?? null;
            $key = is_string($variantKey) ? $variantKey : (is_string($named) ? $named : '');
            if ($key === '') {
                continue;
            }
            $presets[] = ImageTransformPreset::fromArray($key, $presetData);
        }

        return $presets;
    }
}
