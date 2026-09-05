<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Contract;

use Semitexa\Media\Domain\Model\MediaAssetReference;
use Semitexa\Media\Domain\Model\MediaObjectContents;
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

    /**
     * The asset's bytes — the variant when it is ready, the original otherwise.
     *
     * For storage that {@see self::getUrl()} can address publicly this is the
     * wasteful path and the URL is the right answer. It exists for the storage
     * that cannot be addressed publicly at all (the local driver on a default
     * install), where an application that will not read the bytes itself has
     * no way to show the image.
     *
     * Null when the id names nothing, or the object is gone from storage.
     */
    public function readObject(string $assetId, ?string $variantKey = null): ?MediaObjectContents;

    public function queueRegeneration(string $assetId, ?string $variantKey = null): void;
}
