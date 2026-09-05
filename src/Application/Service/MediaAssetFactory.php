<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Media\Application\Db\MySQL\Model\MediaAssetResource;
use Semitexa\Media\Domain\Model\MediaCollection;
use Semitexa\Media\Domain\Enum\MediaAssetStatus;
use Semitexa\Media\Domain\Model\ImageMetadata;
use Semitexa\Orm\Application\Service\Uuid7;

#[AsService]
final class MediaAssetFactory
{
    public function createFromUpload(
        ImageMetadata $metadata,
        string $originalFilename,
        string $storagePath,
        string $storageDriver,
        MediaCollection $collection,
        string $tenantId,
        ?string $createdBy,
    ): MediaAssetResource {
        return new MediaAssetResource(
            tenant_id: $tenantId,
            collection_key: $collection->getCollectionKey(),
            storage_driver: $storageDriver,
            original_path: $storagePath,
            original_filename: $originalFilename,
            mime_type: $metadata->mimeType,
            media_kind: $collection->getMediaKind()->value,
            visibility: $collection->getVisibilityDefault()->value,
            status: MediaAssetStatus::Pending->value,
            byte_size: $metadata->byteSize,
            width: $metadata->width,
            height: $metadata->height,
            orientation: $metadata->orientation,
            sha256: $metadata->sha256,
            created_by: $createdBy,
            metadata_json: $metadata->extra !== []
                ? json_encode($metadata->extra, JSON_THROW_ON_ERROR) : null,
        );
    }
}
