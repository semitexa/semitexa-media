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

        $allowed = self::decodeList($resourceModel->allowed_mime_types_json, 'allowed_mime_types_json', $resourceModel->collection_key);
        $profile = self::decodeList($resourceModel->transform_profile_json, 'transform_profile_json', $resourceModel->collection_key);

        return new MediaCollection(
            collectionKey: $resourceModel->collection_key,
            mediaKind: MediaKind::from($resourceModel->media_kind),
            visibilityDefault: MediaVisibility::from($resourceModel->visibility_default),
            quotaBucket: $resourceModel->quota_bucket,
            allowedMimeTypes: array_values(array_filter($allowed, 'is_string')),
            transformPresets: $this->presets($profile),
            maxOriginalBytes: $resourceModel->max_original_bytes,
            maxWidth: $resourceModel->max_width,
            maxHeight: $resourceModel->max_height,
            maxAssetCount: $resourceModel->max_asset_count,
            tenantId: $resourceModel->tenant_id,
            id: $resourceModel->id,
            enabled: $resourceModel->enabled === 1,
            metadata: self::stringKeyed(self::decodeList($resourceModel->metadata_json ?? '', 'metadata_json', $resourceModel->collection_key)),
            createdAt: $resourceModel->created_at,
            updatedAt: $resourceModel->updated_at,
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
            allowed_mime_types_json: (string) json_encode($domainModel->getAllowedMimeTypes(), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES),
            max_original_bytes: $domainModel->getMaxOriginalBytes(),
            max_width: $domainModel->getMaxWidth(),
            max_height: $domainModel->getMaxHeight(),
            max_asset_count: $domainModel->getMaxAssetCount(),
            transform_profile_json: (string) json_encode($profile, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES),
            metadata_json: $domainModel->getMetadata() === []
                ? null
                : (string) json_encode($domainModel->getMetadata(), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES),
            created_at: $domainModel->getCreatedAt(),
            updated_at: $domainModel->getUpdatedAt(),
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

    /**
     * A row's JSON column, or a refusal.
     *
     * Answering [] for malformed JSON is not the safe option it looks like: an
     * empty allowlist reads as "every mime type allowed", an empty profile as
     * "no variants", and the next save writes that invention back over the
     * policy. The row is the thing that is wrong — say so.
     *
     * @return array<mixed>
     */
    private static function decodeList(string $json, string $column, string $collectionKey): array
    {
        if ($json === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException(
                sprintf('Media collection %s carries malformed %s.', $collectionKey, $column),
                0,
                $e,
            );
        }

        if (!is_array($decoded)) {
            throw new \InvalidArgumentException(
                sprintf('Media collection %s carries a non-object %s.', $collectionKey, $column),
            );
        }

        return $decoded;
    }

    /**
     * @param array<mixed> $decoded
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $decoded): array
    {
        $map = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $map[$key] = $value;
            }
        }

        return $map;
    }
}
