<?php

declare(strict_types=1);

namespace Semitexa\Media\Configuration;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Environment;

#[AsService]
final class MediaConfig
{
    // Defaults duplicated from the env fallbacks below: the DI container may
    // materialize attribute-style services without running the constructor,
    // and readonly constructor-only props then explode on first access.
    public string $workerQueue = 'media';
    public string $workerTransport = '';
    public int $variantMaxAttempts = 3;
    public int $variantRetryDelay = 30;
    public int $defaultJpegQuality = 85;
    public int $defaultWebpQuality = 82;
    public string $publicBaseUrl = '';
    public string $storageDriver = 'local';

    public function __construct()
    {
        $this->workerQueue       = Environment::getEnvValue('MEDIA_WORKER_QUEUE', 'media');
        $this->workerTransport   = Environment::getEnvValue('MEDIA_WORKER_TRANSPORT', '');
        $this->variantMaxAttempts = (int) Environment::getEnvValue('MEDIA_VARIANT_MAX_ATTEMPTS', '3');
        $this->variantRetryDelay  = (int) Environment::getEnvValue('MEDIA_VARIANT_RETRY_DELAY', '30');
        $this->defaultJpegQuality = (int) Environment::getEnvValue('MEDIA_JPEG_QUALITY', '85');
        $this->defaultWebpQuality = (int) Environment::getEnvValue('MEDIA_WEBP_QUALITY', '82');
        $this->publicBaseUrl      = rtrim(Environment::getEnvValue('MEDIA_PUBLIC_BASE_URL', ''), '/');
        $this->storageDriver      = Environment::getEnvValue('STORAGE_DRIVER', 'local');

        $this->validate();
    }

    private function validate(): void
    {
        if ($this->variantMaxAttempts < 1) {
            throw new \InvalidArgumentException('MEDIA_VARIANT_MAX_ATTEMPTS must be >= 1.');
        }

        if ($this->defaultJpegQuality < 1 || $this->defaultJpegQuality > 100) {
            throw new \InvalidArgumentException('MEDIA_JPEG_QUALITY must be between 1 and 100.');
        }

        if ($this->defaultWebpQuality < 1 || $this->defaultWebpQuality > 100) {
            throw new \InvalidArgumentException('MEDIA_WEBP_QUALITY must be between 1 and 100.');
        }
    }
}
