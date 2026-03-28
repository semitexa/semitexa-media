<?php

declare(strict_types=1);

namespace Semitexa\Media\Service;

use Semitexa\Core\Attributes\InjectAsReadonly;
use Semitexa\Core\Attributes\SatisfiesServiceContract;
use Semitexa\Media\Contract\MediaServiceInterface;
use Semitexa\Media\Contract\MediaVariantRepositoryInterface;
use Semitexa\Media\Enum\MediaVariantStatus;
use Semitexa\Media\Value\MediaAssetReference;
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

    public function queueRegeneration(string $assetId, ?string $variantKey = null): void
    {
        $variants = $variantKey !== null
            ? array_filter(
                [$this->variantRepository->findByAssetAndKey($assetId, $variantKey)],
                static fn ($v) => $v !== null,
            )
            : $this->variantRepository->findByAssetId($assetId);

        foreach ($variants as $variant) {
            // Reset to queued
            $variant->status      = MediaVariantStatus::Queued->value;
            $variant->queued_at   = new \DateTimeImmutable();
            $variant->error_code  = null;
            $variant->error_message = null;

            $this->variantRepository->save($variant);
            $this->queueDispatcher->dispatch($assetId, $variant);
        }
    }

    private function resolveTenantId(): string
    {
        $context = CoroutineContextStore::get();

        return $context?->getTenantId() ?? '';
    }
}
