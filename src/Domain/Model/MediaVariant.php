<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Model;

use Semitexa\Media\Enum\MediaVariantStatus;

final class MediaVariant
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $mediaAssetId,
        public readonly string $variantKey,
        public MediaVariantStatus $status,
        public readonly string $resizeMode,
        public readonly ?int $targetWidth,
        public readonly ?int $targetHeight,
        public readonly ?int $quality,
        public ?string $storageDriver = null,
        public ?string $storagePath = null,
        public ?string $mimeType = null,
        public ?int $byteSize = null,
        public ?int $actualWidth = null,
        public ?int $actualHeight = null,
        public ?string $leaseOwner = null,
        public ?\DateTimeImmutable $leaseExpiresAt = null,
        public int $attemptCount = 0,
        public int $maxAttempts = 3,
        public ?\DateTimeImmutable $queuedAt = null,
        public ?\DateTimeImmutable $processingStartedAt = null,
        public ?\DateTimeImmutable $generatedAt = null,
        public ?\DateTimeImmutable $lastAttemptAt = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public array $metadataJson = [],
    ) {}

    public function hasExceededMaxAttempts(): bool
    {
        return $this->attemptCount >= $this->maxAttempts;
    }
}
