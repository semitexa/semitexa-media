<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Mapper;

use Semitexa\Media\Application\Db\MySQL\Model\MediaAssetResource;
use Semitexa\Media\Domain\Model\MediaAsset;
use Semitexa\Orm\Attribute\AsMapper;
use Semitexa\Orm\Domain\Contract\ResourceModelMapperInterface;

/**
 * The bridge between the MySQL row and the uploaded original.
 *
 * The probe's extra findings are a JSON string in the column and an array in
 * the asset — decoded once here rather than wherever someone happens to need
 * them.
 */
#[AsMapper(resourceModel: MediaAssetResource::class, domainModel: MediaAsset::class)]
final class MediaAssetMapper implements ResourceModelMapperInterface
{
    public function toDomain(object $resourceModel): object
    {
        $resourceModel instanceof MediaAssetResource || throw new \InvalidArgumentException('Unexpected resource model.');

        $decoded = $resourceModel->metadata_json === null ? null : json_decode($resourceModel->metadata_json, true);
        /** @var array<string, mixed> $metadata */
        $metadata = is_array($decoded) ? $decoded : [];

        return new MediaAsset(
            id: $resourceModel->id,
            tenantId: $resourceModel->tenant_id,
            collectionKey: $resourceModel->collection_key,
            storageDriver: $resourceModel->storage_driver,
            originalPath: $resourceModel->original_path,
            originalFilename: $resourceModel->original_filename,
            mimeType: $resourceModel->mime_type,
            mediaKind: $resourceModel->media_kind,
            visibility: $resourceModel->visibility,
            status: $resourceModel->status,
            byteSize: $resourceModel->byte_size,
            sha256: $resourceModel->sha256,
            width: $resourceModel->width,
            height: $resourceModel->height,
            orientation: $resourceModel->orientation,
            altText: $resourceModel->alt_text,
            metadata: $metadata,
            createdBy: $resourceModel->created_by,
            readyAt: $resourceModel->ready_at,
            deletedAt: $resourceModel->deleted_at,
            createdAt: $resourceModel->created_at,
            updatedAt: $resourceModel->updated_at,
        );
    }

    public function toSourceModel(object $domainModel): object
    {
        $domainModel instanceof MediaAsset || throw new \InvalidArgumentException('Unexpected domain model.');

        return new MediaAssetResource(
            id: $domainModel->getId(),
            tenant_id: $domainModel->getTenantId(),
            collection_key: $domainModel->getCollectionKey(),
            storage_driver: $domainModel->getStorageDriver(),
            original_path: $domainModel->getOriginalPath(),
            original_filename: $domainModel->getOriginalFilename(),
            mime_type: $domainModel->getMimeType(),
            media_kind: $domainModel->getMediaKind(),
            visibility: $domainModel->getVisibility(),
            status: $domainModel->getStatus(),
            byte_size: $domainModel->getByteSize(),
            width: $domainModel->getWidth(),
            height: $domainModel->getHeight(),
            orientation: $domainModel->getOrientation(),
            sha256: $domainModel->getSha256(),
            alt_text: $domainModel->getAltText(),
            metadata_json: $domainModel->getMetadata() === []
                ? null
                : (string) json_encode($domainModel->getMetadata(), JSON_THROW_ON_ERROR),
            created_by: $domainModel->getCreatedBy(),
            ready_at: $domainModel->getReadyAt(),
            deleted_at: $domainModel->getDeletedAt(),
            created_at: $domainModel->getCreatedAt(),
            updated_at: $domainModel->getUpdatedAt(),
        );
    }
}
