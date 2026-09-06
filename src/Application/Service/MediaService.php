<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Semitexa\Media\Domain\Contract\MediaServiceInterface;
use Semitexa\Media\Domain\Contract\MediaVariantRepositoryInterface;
use Semitexa\Media\Domain\Enum\MediaVariantStatus;
use Semitexa\Media\Domain\Model\MediaAssetReference;
use Semitexa\Media\Domain\Model\MediaObjectContents;
use Semitexa\Storage\Contract\StorageObjectStoreInterface;
use Semitexa\Storage\Value\StoredObjectDescriptor;
use Semitexa\Tenancy\Context\CoroutineContextStore;

#[SatisfiesServiceContract(of: MediaServiceInterface::class)]
final class MediaService implements MediaServiceInterface
{
    #[InjectAsReadonly]
    protected MediaIngestService $ingestService;

    #[InjectAsReadonly]
    protected MediaUrlGenerator $urlGenerator;

    #[InjectAsReadonly]
    protected MediaObjectLocator $objectLocator;

    #[InjectAsReadonly]
    protected StorageObjectStoreInterface $storage;

    #[InjectAsReadonly]
    protected MediaVariantRepositoryInterface $variantRepository;

    #[InjectAsReadonly]
    protected MediaQueueDispatcher $queueDispatcher;

    #[InjectAsReadonly]
    protected MediaVariantPlanner $variantPlanner;

    #[InjectAsReadonly]
    protected MediaCollectionPolicyResolver $collectionResolver;

    public function ingestUploadedImage(
        string $contents,
        string $originalName,
        string $mimeType,
        string $collectionKey,
        ?string $createdBy = null,
    ): MediaAssetReference {
        $tenantId = $this->resolveTenantId();

        return $this->ingestService->ingestUploadedImage(
            contents:      $contents,
            originalName:  $originalName,
            mimeType:      $mimeType,
            collectionKey: $collectionKey,
            tenantId:      $tenantId,
            createdBy:     $createdBy,
        );
    }

    public function ingestStoredObject(
        StoredObjectDescriptor $object,
        string $collectionKey,
        ?string $originalName = null,
        ?string $createdBy = null,
    ): MediaAssetReference {
        $tenantId = $this->resolveTenantId();

        return $this->ingestService->ingestStoredObject(
            object:        $object,
            collectionKey: $collectionKey,
            tenantId:      $tenantId,
            originalName:  $originalName,
            createdBy:     $createdBy,
        );
    }

    public function getUrl(string $assetId, ?string $variantKey = null): string
    {
        return $this->urlGenerator->url($assetId, $variantKey);
    }

    public function readObject(string $assetId, ?string $variantKey = null): ?MediaObjectContents
    {
        $object = $this->objectLocator->locate($assetId, $variantKey);

        if ($object === null) {
            return null;
        }

        $contents = $this->storage->get($object->path);

        if ($contents === null) {
            return null;
        }

        return new MediaObjectContents($contents, $object->mimeType);
    }

    public function belongsToCollection(string $assetId, string $collectionKey): bool
    {
        // Through the locator, so this answers under the same tenant scope
        // the read paths use: an asset another tenant owns belongs to no
        // collection as far as this caller is concerned.
        return $this->objectLocator->locate($assetId)?->collectionKey === $collectionKey;
    }

    public function queueRegeneration(string $assetId, ?string $variantKey = null): void
    {
        $variants = $variantKey !== null
            ? array_filter(
                [$this->variantRepository->findByAssetAndKey($assetId, $variantKey)],
                static fn ($v) => $v !== null,
            )
            : $this->variantRepository->findByAssetId($assetId);

        foreach ($variants as $variant) {
            // Reset to queued (readonly resource — dispatch the saved row).
            $variant = $this->variantRepository->save($variant->with([
                'status' => MediaVariantStatus::Queued->value,
                'queuedAt' => new \DateTimeImmutable(),
                'errorCode' => null,
                'errorMessage' => null,
            ]));
            $this->queueDispatcher->dispatch($assetId, $variant);
        }
    }

    private function resolveTenantId(): string
    {
        $context = CoroutineContextStore::get();

        return $context?->getTenantId() ?? '';
    }
}
