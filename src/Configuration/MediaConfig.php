<?php

declare(strict_types=1);

namespace Semitexa\Media\Configuration;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\Config;

/**
 * Media pipeline configuration. Values arrive through #[Config] property
 * injection — the ONLY scalar channel on container-managed objects (the
 * container instantiates via newInstanceWithoutConstructor(), so a
 * constructor reading env vars would silently never run).
 */
#[AsService]
final class MediaConfig
{
    #[Config(env: 'MEDIA_WORKER_QUEUE', default: 'media')]
    protected string $workerQueue;

    #[Config(env: 'MEDIA_WORKER_TRANSPORT', default: '')]
    protected string $workerTransport;

    #[Config(env: 'MEDIA_VARIANT_MAX_ATTEMPTS', default: 3)]
    protected int $variantMaxAttempts;

    #[Config(env: 'MEDIA_VARIANT_RETRY_DELAY', default: 30)]
    protected int $variantRetryDelay;

    #[Config(env: 'MEDIA_JPEG_QUALITY', default: 85)]
    protected int $defaultJpegQuality;

    #[Config(env: 'MEDIA_WEBP_QUALITY', default: 82)]
    protected int $defaultWebpQuality;

    #[Config(env: 'MEDIA_PUBLIC_BASE_URL', default: '')]
    protected string $publicBaseUrl;

    #[Config(env: 'STORAGE_DRIVER', default: 'local')]
    protected string $storageDriver;

    public function workerQueue(): string
    {
        return $this->workerQueue;
    }

    public function workerTransport(): string
    {
        return $this->workerTransport;
    }

    public function variantMaxAttempts(): int
    {
        return max(1, $this->variantMaxAttempts);
    }

    public function variantRetryDelay(): int
    {
        return max(0, $this->variantRetryDelay);
    }

    public function defaultJpegQuality(): int
    {
        return min(100, max(1, $this->defaultJpegQuality));
    }

    public function defaultWebpQuality(): int
    {
        return min(100, max(1, $this->defaultWebpQuality));
    }

    public function publicBaseUrl(): string
    {
        return rtrim($this->publicBaseUrl, '/');
    }

    public function storageDriver(): string
    {
        return $this->storageDriver;
    }
}
