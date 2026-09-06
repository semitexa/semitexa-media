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
     * Null when the id names nothing here — no such asset, another tenant's
     * asset — or the object is gone from storage.
     */
    public function readObject(string $assetId, ?string $variantKey = null): ?MediaObjectContents;

    /**
     * Whether the asset exists, is the caller's tenant's, and belongs to that
     * collection.
     *
     * A route that serves one collection's files has to be able to say so. An
     * asset id is otherwise a bearer token: every other collection — the
     * private ones included — answers to the same route, and the only thing
     * between a reader and someone else's file is not knowing the id. Ids are
     * not secret either: they are written into the article markup that names
     * the picture.
     */
    public function belongsToCollection(string $assetId, string $collectionKey): bool;

    public function queueRegeneration(string $assetId, ?string $variantKey = null): void;
}
