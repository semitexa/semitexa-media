<?php

declare(strict_types=1);

namespace Semitexa\Media\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Media\Application\Db\MySQL\Model\MediaAssetResource;
use Semitexa\Media\Domain\Model\MediaCollection;
use Semitexa\Media\Enum\MediaAssetStatus;
use Semitexa\Media\Value\ImageMetadata;
use Semitexa\Orm\Uuid\Uuid7;

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
        $resource                   = new MediaAssetResource();
        $resource->tenant_id        = $tenantId;
        $resource->collection_key   = $collection->collectionKey;
        $resource->storage_driver   = $storageDriver;
        $resource->original_path    = $storagePath;
        $resource->original_filename = $originalFilename;
        $resource->mime_type        = $metadata->mimeType;
        $resource->media_kind       = $collection->mediaKind->value;
        $resource->visibility       = $collection->visibilityDefault->value;
        $resource->status           = MediaAssetStatus::Pending->value;
        $resource->byte_size        = $metadata->byteSize;
        $resource->width            = $metadata->width;
        $resource->height           = $metadata->height;
        $resource->orientation      = $metadata->orientation;
        $resource->sha256           = $metadata->sha256;
        $resource->created_by       = $createdBy;

        if ($metadata->extra !== []) {
            $resource->metadata_json = json_encode($metadata->extra, JSON_THROW_ON_ERROR);
        }

        return $resource;
    }
}
