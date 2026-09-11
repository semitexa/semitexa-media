<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Semitexa\Media\Domain\Contract\MediaUrlGeneratorInterface;
use Semitexa\Storage\Contract\StorageObjectStoreInterface;
use Semitexa\Tenancy\Context\CoroutineContextStore;

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

    public function publicUrlPrefix(): string
    {
        // Built from the same segment rule the ingest path writes with, so the
        // prefix and the objects under it cannot drift apart. Asking storage
        // rather than reading a base URL out of the environment keeps the one
        // driver-shaped answer in the one place that already knows it: '' from
        // a local disk with nothing published, an endpoint from S3.
        return $this->storage->url('media/' . MediaPathSegment::of($this->currentTenantId()) . '/');
    }

    /**
     * The tenant the caller is acting as — '' on an installation that runs
     * without tenancy, which is also what ingest recorded on those assets.
     */
    private function currentTenantId(): string
    {
        return CoroutineContextStore::get()?->getTenantId() ?? '';
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
