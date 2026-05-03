<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Contract;

use Semitexa\Media\Domain\Model\MediaAssetReference;
use Semitexa\Storage\Value\StoredObjectDescriptor;

interface MediaServiceInterface
{
    public function ingestUploadedImage(
        string $contents,
        string $originalName,
        string $mimeType,
        string $collectionKey,
        ?string $createdBy = null,
    ): MediaAssetReference;

    public function ingestStoredObject(
        StoredObjectDescriptor $object,
        string $collectionKey,
        ?string $originalName = null,
        ?string $createdBy = null,
    ): MediaAssetReference;

    public function getUrl(string $assetId, ?string $variantKey = null): string;

    public function queueRegeneration(string $assetId, ?string $variantKey = null): void;
}
