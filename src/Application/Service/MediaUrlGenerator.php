<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Semitexa\Media\Domain\Contract\MediaUrlGeneratorInterface;
use Semitexa\Storage\Contract\StorageObjectStoreInterface;

#[SatisfiesServiceContract(of: MediaUrlGeneratorInterface::class)]
final class MediaUrlGenerator implements MediaUrlGeneratorInterface
{
    #[InjectAsReadonly]
    protected MediaObjectLocator $locator;

    #[InjectAsReadonly]
    protected StorageObjectStoreInterface $storage;

    public function url(string $assetId, ?string $variantKey = null): string
    {
        $object = $this->locator->locate($assetId, $variantKey);

        if ($object === null) {
            return '';
        }

        // Empty when the driver has no public URL for its objects — the local
        // driver without STORAGE_LOCAL_PUBLIC_URL. Callers read that as "not
        // publicly addressable" and serve the bytes themselves.
        return $this->addVersioning($this->storage->url($object->path), $object->version);
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
