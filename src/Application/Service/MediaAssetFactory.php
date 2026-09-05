<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Media\Domain\Model\MediaAsset;
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
    ): MediaAsset {
        return new MediaAsset(
            id: '',
            tenantId: $tenantId,
            collectionKey: $collection->getCollectionKey(),
            storageDriver: $storageDriver,
            originalPath: $storagePath,
            originalFilename: $originalFilename,
            mimeType: $metadata->mimeType,
            mediaKind: $collection->getMediaKind()->value,
            visibility: $collection->getVisibilityDefault()->value,
            status: MediaAssetStatus::Pending->value,
            byteSize: $metadata->byteSize,
            width: $metadata->width,
            height: $metadata->height,
            orientation: $metadata->orientation,
            sha256: $metadata->sha256,
            createdBy: $createdBy,
            metadata: $this->stringKeyed($metadata->extra),
        );
    }

    /**
     * @param array<mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function stringKeyed(array $extra): array
    {
        $out = [];
        foreach ($extra as $key => $value) {
            if (is_string($key)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
