<?php

declare(strict_types=1);

namespace Semitexa\Media\Service;

use Semitexa\Media\Application\Db\MySQL\Model\MediaAssetResource;
use Semitexa\Media\Configuration\MediaConfig;
use Semitexa\Media\Contract\MediaAssetRepositoryInterface;
use Semitexa\Media\Contract\MediaQuotaManagerInterface;
use Semitexa\Media\Domain\Exception\MediaIngestException;
use Semitexa\Media\Domain\Model\MediaCollection;
use Semitexa\Media\Enum\MediaAssetStatus;
use Semitexa\Media\Value\MediaAssetReference;
use Semitexa\Storage\Contract\StorageObjectStoreInterface;
use Semitexa\Storage\Value\StoredObjectDescriptor;

final class MediaIngestService
{
    public function __construct(
        private readonly MediaConfig $config,
        private readonly MediaCollectionPolicyResolver $collectionResolver,
        private readonly MediaMetadataExtractor $metadataExtractor,
        private readonly MediaAssetFactory $assetFactory,
        private readonly MediaAssetRepositoryInterface $assetRepository,
        private readonly MediaVariantPlanner $variantPlanner,
        private readonly MediaQueueDispatcher $queueDispatcher,
        private readonly MediaQuotaManagerInterface $quotaManager,
        private readonly StorageObjectStoreInterface $storage,
    ) {}

    public function ingestUploadedImage(
        string $contents,
        string $originalName,
        string $mimeType,
        string $collectionKey,
        string $tenantId,
        ?string $createdBy = null,
    ): MediaAssetReference {
        if ($contents === '') {
            throw new MediaIngestException('Image contents are empty.');
        }

        $collection = $this->collectionResolver->resolve($collectionKey, $tenantId);

        $this->validateMimeType($mimeType, $collection);
        $this->validateByteSize(strlen($contents), $collection);

        $metadata = $this->metadataExtractor->extract($contents, $mimeType);

        $this->validateDimensions($metadata->width, $metadata->height, $collection);

        $this->quotaManager->checkAndReserve($tenantId, $collection, $metadata->byteSize);

        $pathBuilder  = new OriginalStoragePathBuilder();
        $assetId      = $this->generateAssetId();
        $storagePath  = $pathBuilder->build($tenantId, $collectionKey, $assetId, $mimeType);

        $this->storage->put($storagePath, $contents, $mimeType);

        $resource = $this->assetFactory->createFromUpload(
            metadata:        $metadata,
            originalFilename: $originalName,
            storagePath:     $storagePath,
            storageDriver:   $this->config->storageDriver,
            collection:      $collection,
            tenantId:        $tenantId,
            createdBy:       $createdBy,
        );
        // Set the pre-generated ID
        $resource->id = $assetId;

        $this->assetRepository->save($resource);

        $this->markReady($resource);

        $variantResources = $this->variantPlanner->plan(
            $assetId,
            $tenantId,
            $collection,
            $this->config->variantMaxAttempts,
        );

        foreach ($variantResources as $variant) {
            try {
                $this->queueDispatcher->dispatch($assetId, $variant);
            } catch (\Throwable $e) {
                // Log dispatch failure but do not fail ingest — variants can be retried
                error_log("Media queue dispatch failed for asset {$assetId} variant {$variant->variant_key}: {$e->getMessage()}");
            }
        }

        return new MediaAssetReference(
            assetId:       $assetId,
            collectionKey: $collectionKey,
            originalUrl:   $this->storage->url($storagePath),
        );
    }

    public function ingestStoredObject(
        StoredObjectDescriptor $object,
        string $collectionKey,
        string $tenantId,
        ?string $originalName = null,
        ?string $createdBy = null,
    ): MediaAssetReference {
        $mimeType = $object->mimeType ?? 'application/octet-stream';
        $fileName = $originalName ?? basename($object->path);

        $collection = $this->collectionResolver->resolve($collectionKey, $tenantId);

        $this->validateMimeType($mimeType, $collection);

        $contents = $this->storage->get($object->path);
        if ($contents === null) {
            throw new MediaIngestException("Stored object not found at path '{$object->path}'.");
        }

        $this->validateByteSize(strlen($contents), $collection);

        $metadata = $this->metadataExtractor->extract($contents, $mimeType);

        $this->validateDimensions($metadata->width, $metadata->height, $collection);

        $this->quotaManager->checkAndReserve($tenantId, $collection, $metadata->byteSize);

        $assetId     = $this->generateAssetId();
        $pathBuilder = new OriginalStoragePathBuilder();
        $storagePath = $pathBuilder->build($tenantId, $collectionKey, $assetId, $mimeType);

        // Copy to canonical media path
        $this->storage->put($storagePath, $contents, $mimeType);

        $resource = $this->assetFactory->createFromUpload(
            metadata:        $metadata,
            originalFilename: $fileName,
            storagePath:     $storagePath,
            storageDriver:   $object->driver,
            collection:      $collection,
            tenantId:        $tenantId,
            createdBy:       $createdBy,
        );
        $resource->id = $assetId;

        $this->assetRepository->save($resource);

        $this->markReady($resource);

        $variantResources = $this->variantPlanner->plan(
            $assetId,
            $tenantId,
            $collection,
            $this->config->variantMaxAttempts,
        );

        foreach ($variantResources as $variant) {
            try {
                $this->queueDispatcher->dispatch($assetId, $variant);
            } catch (\Throwable $e) {
                error_log("Media queue dispatch failed for asset {$assetId} variant {$variant->variant_key}: {$e->getMessage()}");
            }
        }

        return new MediaAssetReference(
            assetId:       $assetId,
            collectionKey: $collectionKey,
            originalUrl:   $this->storage->url($storagePath),
        );
    }

    private function validateMimeType(string $mimeType, MediaCollection $collection): void
    {
        if (!$collection->isMimeTypeAllowed($mimeType)) {
            throw new MediaIngestException(
                "MIME type '{$mimeType}' is not allowed in collection '{$collection->collectionKey}'."
            );
        }
    }

    private function validateByteSize(int $byteSize, MediaCollection $collection): void
    {
        if ($collection->maxOriginalBytes !== null && $byteSize > $collection->maxOriginalBytes) {
            throw new MediaIngestException(
                "File size {$byteSize} bytes exceeds the maximum of {$collection->maxOriginalBytes} bytes for collection '{$collection->collectionKey}'."
            );
        }
    }

    private function validateDimensions(int $width, int $height, MediaCollection $collection): void
    {
        if ($collection->maxWidth !== null && $width > $collection->maxWidth) {
            throw new MediaIngestException(
                "Image width {$width}px exceeds the maximum of {$collection->maxWidth}px for collection '{$collection->collectionKey}'."
            );
        }

        if ($collection->maxHeight !== null && $height > $collection->maxHeight) {
            throw new MediaIngestException(
                "Image height {$height}px exceeds the maximum of {$collection->maxHeight}px for collection '{$collection->collectionKey}'."
            );
        }
    }

    private function markReady(MediaAssetResource $resource): void
    {
        $resource->status   = MediaAssetStatus::Ready->value;
        $resource->ready_at = new \DateTimeImmutable();
        $this->assetRepository->save($resource);
    }

    private function generateAssetId(): string
    {
        return \Semitexa\Orm\Uuid\Uuid7::generate();
    }
}
