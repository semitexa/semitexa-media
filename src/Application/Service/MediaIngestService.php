<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Log\LoggerInterface;
use Semitexa\Media\Domain\Model\MediaAsset;
use Semitexa\Media\Configuration\MediaConfig;
use Semitexa\Media\Domain\Contract\MediaAssetRepositoryInterface;
use Semitexa\Media\Domain\Contract\MediaQuotaManagerInterface;
use Semitexa\Media\Domain\Exception\MediaIngestException;
use Semitexa\Media\Domain\Model\MediaCollection;
use Semitexa\Media\Domain\Enum\MediaAssetStatus;
use Semitexa\Media\Domain\Model\MediaAssetReference;
use Semitexa\Storage\Contract\StorageObjectStoreInterface;
use Semitexa\Storage\Value\StoredObjectDescriptor;

#[AsService]
final class MediaIngestService
{
    #[InjectAsReadonly]
    protected MediaConfig $config;

    #[InjectAsReadonly]
    protected MediaCollectionPolicyResolver $collectionResolver;

    #[InjectAsReadonly]
    protected MediaMetadataExtractor $metadataExtractor;

    #[InjectAsReadonly]
    protected MediaAssetFactory $assetFactory;

    #[InjectAsReadonly]
    protected MediaAssetRepositoryInterface $assetRepository;

    #[InjectAsReadonly]
    protected MediaVariantPlanner $variantPlanner;

    #[InjectAsReadonly]
    protected MediaQueueDispatcher $queueDispatcher;

    #[InjectAsReadonly]
    protected MediaQuotaManagerInterface $quotaManager;

    #[InjectAsReadonly]
    protected StorageObjectStoreInterface $storage;

    #[InjectAsReadonly]
    protected LoggerInterface $logger;

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
            storageDriver:   $this->config->storageDriver(),
            collection:      $collection,
            tenantId:        $tenantId,
            createdBy:       $createdBy,
        );
        // Pin the pre-generated ID (readonly resource — rebuild the row).
        $resource = $this->assetRepository->save($resource->with(['id' => $assetId]));

        $this->markReady($resource);

        $variantResources = $this->variantPlanner->plan(
            $assetId,
            $tenantId,
            $collection,
            $this->config->variantMaxAttempts(),
        );

        foreach ($variantResources as $variant) {
            try {
                $this->queueDispatcher->dispatch($assetId, $variant);
            } catch (\Throwable $e) {
                // Log dispatch failure but do not fail ingest — variants can be retried
                $this->logger->error('Media queue dispatch failed', [
                    'asset_id' => $assetId,
                    'variant_key' => $variant->getVariantKey(),
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
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
        $resource = $this->assetRepository->save($resource->with(['id' => $assetId]));

        $this->markReady($resource);

        $variantResources = $this->variantPlanner->plan(
            $assetId,
            $tenantId,
            $collection,
            $this->config->variantMaxAttempts(),
        );

        foreach ($variantResources as $variant) {
            try {
                $this->queueDispatcher->dispatch($assetId, $variant);
            } catch (\Throwable $e) {
                $this->logger->error('Media queue dispatch failed', [
                    'asset_id' => $assetId,
                    'variant_key' => $variant->getVariantKey(),
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
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
                "MIME type '{$mimeType}' is not allowed in collection '{$collection->getCollectionKey()}'."
            );
        }
    }

    private function validateByteSize(int $byteSize, MediaCollection $collection): void
    {
        if ($collection->getMaxOriginalBytes() !== null && $byteSize > $collection->getMaxOriginalBytes()) {
            throw new MediaIngestException(
                "File size {$byteSize} bytes exceeds the maximum of {$collection->getMaxOriginalBytes()} bytes for collection '{$collection->getCollectionKey()}'."
            );
        }
    }

    private function validateDimensions(int $width, int $height, MediaCollection $collection): void
    {
        if ($collection->getMaxWidth() !== null && $width > $collection->getMaxWidth()) {
            throw new MediaIngestException(
                "Image width {$width}px exceeds the maximum of {$collection->getMaxWidth()}px for collection '{$collection->getCollectionKey()}'."
            );
        }

        if ($collection->getMaxHeight() !== null && $height > $collection->getMaxHeight()) {
            throw new MediaIngestException(
                "Image height {$height}px exceeds the maximum of {$collection->getMaxHeight()}px for collection '{$collection->getCollectionKey()}'."
            );
        }
    }

    private function markReady(MediaAsset $resource): void
    {
        $this->assetRepository->save($resource->with([
            'status' => MediaAssetStatus::Ready->value,
            'readyAt' => new \DateTimeImmutable(),
        ]));
    }

    private function generateAssetId(): string
    {
        return \Semitexa\Orm\Application\Service\Uuid7::generate();
    }
}
