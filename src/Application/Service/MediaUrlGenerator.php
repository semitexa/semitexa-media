<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Semitexa\Media\Domain\Contract\MediaUrlGeneratorInterface;
use Semitexa\Media\Domain\Contract\MediaAssetRepositoryInterface;
use Semitexa\Media\Domain\Contract\MediaVariantRepositoryInterface;
use Semitexa\Media\Domain\Enum\MediaVariantStatus;
use Semitexa\Storage\Contract\StorageObjectStoreInterface;

#[SatisfiesServiceContract(of: MediaUrlGeneratorInterface::class)]
final class MediaUrlGenerator implements MediaUrlGeneratorInterface
{
    #[InjectAsReadonly]
    protected MediaAssetRepositoryInterface $assetRepository;

    #[InjectAsReadonly]
    protected MediaVariantRepositoryInterface $variantRepository;

    #[InjectAsReadonly]
    protected StorageObjectStoreInterface $storage;

    public function url(string $assetId, ?string $variantKey = null): string
    {
        if ($variantKey !== null) {
            $variant = $this->variantRepository->findByAssetAndKey($assetId, $variantKey);

            if ($variant !== null && $variant->status === MediaVariantStatus::Ready->value && $variant->storage_path !== null) {
                return $this->addVersioning($this->storage->url($variant->storage_path), $variant->generated_at);
            }
        }

        // Fall back to original
        $asset = $this->assetRepository->findById($assetId);

        if ($asset === null) {
            return '';
        }

        return $this->addVersioning($this->storage->url($asset->original_path), $asset->ready_at ?? $asset->created_at ?? null);
    }

    private function addVersioning(string $url, ?\DateTimeImmutable $timestamp): string
    {
        if ($url === '' || $timestamp === null) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'v=' . $timestamp->getTimestamp();
    }
}
