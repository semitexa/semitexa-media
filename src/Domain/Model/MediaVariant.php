<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Model;

use Semitexa\Media\Domain\Enum\MediaVariantStatus;

/**
 * One derivative of an original — a thumbnail, a resized copy — and the state
 * of the attempt to produce it.
 *
 * Half of this is the file: which preset, what size, where it landed. The other
 * half is the lease, which is what stops two workers generating the same file
 * at once: an owner, an expiry, and a bounded number of attempts. A variant
 * whose lease has expired is available again; one out of attempts is not, and
 * neither fact is something a caller should re-derive from four columns.
 */
final readonly class MediaVariant
{
    /**
     * @param array<string, mixed> $metadata whatever the transform recorded
     */
    public function __construct(
        private string $id,
        private ?string $tenantId,
        private string $mediaAssetId,
        private string $variantKey,
        private string $status = 'queued',
        private string $resizeMode = 'fit',
        private ?string $storageDriver = null,
        private ?string $storagePath = null,
        private ?string $mimeType = null,
        private ?int $targetWidth = null,
        private ?int $targetHeight = null,
        private ?int $actualWidth = null,
        private ?int $actualHeight = null,
        private ?int $quality = null,
        private ?int $byteSize = null,
        private ?string $leaseOwner = null,
        private ?\DateTimeImmutable $leaseExpiresAt = null,
        private int $attemptCount = 0,
        private int $maxAttempts = 3,
        private ?\DateTimeImmutable $queuedAt = null,
        private ?\DateTimeImmutable $processingStartedAt = null,
        private ?\DateTimeImmutable $generatedAt = null,
        private ?\DateTimeImmutable $lastAttemptAt = null,
        private ?string $errorCode = null,
        private ?string $errorMessage = null,
        private array $metadata = [],
        private ?\DateTimeImmutable $createdAt = null,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function getMediaAssetId(): string
    {
        return $this->mediaAssetId;
    }

    public function getVariantKey(): string
    {
        return $this->variantKey;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getResizeMode(): string
    {
        return $this->resizeMode;
    }

    public function getStorageDriver(): ?string
    {
        return $this->storageDriver;
    }

    public function getStoragePath(): ?string
    {
        return $this->storagePath;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getTargetWidth(): ?int
    {
        return $this->targetWidth;
    }

    public function getTargetHeight(): ?int
    {
        return $this->targetHeight;
    }

    public function getActualWidth(): ?int
    {
        return $this->actualWidth;
    }

    public function getActualHeight(): ?int
    {
        return $this->actualHeight;
    }

    public function getQuality(): ?int
    {
        return $this->quality;
    }

    public function getByteSize(): ?int
    {
        return $this->byteSize;
    }

    public function getLeaseOwner(): ?string
    {
        return $this->leaseOwner;
    }

    public function getLeaseExpiresAt(): ?\DateTimeImmutable
    {
        return $this->leaseExpiresAt;
    }

    public function getAttemptCount(): int
    {
        return $this->attemptCount;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getQueuedAt(): ?\DateTimeImmutable
    {
        return $this->queuedAt;
    }

    public function getProcessingStartedAt(): ?\DateTimeImmutable
    {
        return $this->processingStartedAt;
    }

    public function getGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function getLastAttemptAt(): ?\DateTimeImmutable
    {
        return $this->lastAttemptAt;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function statusEnum(): MediaVariantStatus
    {
        return MediaVariantStatus::tryFrom($this->status) ?? MediaVariantStatus::Queued;
    }

    /** No attempts left — it will not be picked up again. */
    public function isExhausted(): bool
    {
        return $this->attemptCount >= $this->maxAttempts;
    }

    /** The worker holding this has gone quiet; it is free to claim again. */
    public function isLeaseExpired(?\DateTimeImmutable $now = null): bool
    {
        return $this->leaseExpiresAt !== null
            && $this->leaseExpiresAt < ($now ?? new \DateTimeImmutable());
    }

    /**
     * @param array<string, mixed> $changes
     */
    public function with(array $changes): self
    {
        $str = fn (string $k, ?string $current): ?string => array_key_exists($k, $changes)
            ? (is_string($changes[$k]) ? $changes[$k] : null)
            : $current;
        $int = fn (string $k, ?int $current): ?int => array_key_exists($k, $changes)
            ? (is_int($changes[$k]) ? $changes[$k] : null)
            : $current;
        $date = fn (string $k, ?\DateTimeImmutable $current): ?\DateTimeImmutable => array_key_exists($k, $changes)
            ? ($changes[$k] instanceof \DateTimeImmutable ? $changes[$k] : null)
            : $current;

        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            mediaAssetId: $this->mediaAssetId,
            variantKey: $this->variantKey,
            status: is_string($changes['status'] ?? null) ? $changes['status'] : $this->status,
            resizeMode: is_string($changes['resizeMode'] ?? null) ? $changes['resizeMode'] : $this->resizeMode,
            storageDriver: $str('storageDriver', $this->storageDriver),
            storagePath: $str('storagePath', $this->storagePath),
            mimeType: $str('mimeType', $this->mimeType),
            targetWidth: $int('targetWidth', $this->targetWidth),
            targetHeight: $int('targetHeight', $this->targetHeight),
            actualWidth: $int('actualWidth', $this->actualWidth),
            actualHeight: $int('actualHeight', $this->actualHeight),
            quality: $int('quality', $this->quality),
            byteSize: $int('byteSize', $this->byteSize),
            leaseOwner: $str('leaseOwner', $this->leaseOwner),
            leaseExpiresAt: $date('leaseExpiresAt', $this->leaseExpiresAt),
            attemptCount: is_int($changes['attemptCount'] ?? null) ? $changes['attemptCount'] : $this->attemptCount,
            maxAttempts: is_int($changes['maxAttempts'] ?? null) ? $changes['maxAttempts'] : $this->maxAttempts,
            queuedAt: $date('queuedAt', $this->queuedAt),
            processingStartedAt: $date('processingStartedAt', $this->processingStartedAt),
            generatedAt: $date('generatedAt', $this->generatedAt),
            lastAttemptAt: $date('lastAttemptAt', $this->lastAttemptAt),
            errorCode: $str('errorCode', $this->errorCode),
            errorMessage: $str('errorMessage', $this->errorMessage),
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable(),
        );
    }
}
