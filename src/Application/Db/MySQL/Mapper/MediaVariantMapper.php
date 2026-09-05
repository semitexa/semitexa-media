<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Mapper;

use Semitexa\Media\Application\Db\MySQL\Model\MediaVariantResource;
use Semitexa\Media\Domain\Model\MediaVariant;
use Semitexa\Orm\Attribute\AsMapper;
use Semitexa\Orm\Domain\Contract\ResourceModelMapperInterface;

/**
 * The bridge between the MySQL row and one derivative of an original.
 *
 * What the transform recorded is a JSON string in the column and an array in
 * the variant, decoded once here.
 */
#[AsMapper(resourceModel: MediaVariantResource::class, domainModel: MediaVariant::class)]
final class MediaVariantMapper implements ResourceModelMapperInterface
{
    public function toDomain(object $resourceModel): object
    {
        $resourceModel instanceof MediaVariantResource
            || throw new \InvalidArgumentException('Unexpected resource model.');

        $decoded = $resourceModel->metadata_json === null ? null : json_decode($resourceModel->metadata_json, true);
        /** @var array<string, mixed> $metadata */
        $metadata = is_array($decoded) ? $decoded : [];

        return new MediaVariant(
            id: $resourceModel->id,
            tenantId: $resourceModel->tenant_id,
            mediaAssetId: $resourceModel->media_asset_id,
            variantKey: $resourceModel->variant_key,
            status: $resourceModel->status,
            resizeMode: $resourceModel->resize_mode,
            storageDriver: $resourceModel->storage_driver,
            storagePath: $resourceModel->storage_path,
            mimeType: $resourceModel->mime_type,
            targetWidth: $resourceModel->target_width,
            targetHeight: $resourceModel->target_height,
            actualWidth: $resourceModel->actual_width,
            actualHeight: $resourceModel->actual_height,
            quality: $resourceModel->quality,
            byteSize: $resourceModel->byte_size,
            leaseOwner: $resourceModel->lease_owner,
            leaseExpiresAt: $resourceModel->lease_expires_at,
            attemptCount: $resourceModel->attempt_count,
            maxAttempts: $resourceModel->max_attempts,
            queuedAt: $resourceModel->queued_at,
            processingStartedAt: $resourceModel->processing_started_at,
            generatedAt: $resourceModel->generated_at,
            lastAttemptAt: $resourceModel->last_attempt_at,
            errorCode: $resourceModel->error_code,
            errorMessage: $resourceModel->error_message,
            metadata: $metadata,
            createdAt: $resourceModel->created_at,
            updatedAt: $resourceModel->updated_at,
        );
    }

    public function toSourceModel(object $domainModel): object
    {
        $domainModel instanceof MediaVariant || throw new \InvalidArgumentException('Unexpected domain model.');

        return new MediaVariantResource(
            id: $domainModel->getId(),
            tenant_id: $domainModel->getTenantId(),
            media_asset_id: $domainModel->getMediaAssetId(),
            variant_key: $domainModel->getVariantKey(),
            storage_driver: $domainModel->getStorageDriver(),
            storage_path: $domainModel->getStoragePath(),
            mime_type: $domainModel->getMimeType(),
            status: $domainModel->getStatus(),
            resize_mode: $domainModel->getResizeMode(),
            target_width: $domainModel->getTargetWidth(),
            target_height: $domainModel->getTargetHeight(),
            actual_width: $domainModel->getActualWidth(),
            actual_height: $domainModel->getActualHeight(),
            quality: $domainModel->getQuality(),
            byte_size: $domainModel->getByteSize(),
            lease_owner: $domainModel->getLeaseOwner(),
            lease_expires_at: $domainModel->getLeaseExpiresAt(),
            attempt_count: $domainModel->getAttemptCount(),
            max_attempts: $domainModel->getMaxAttempts(),
            queued_at: $domainModel->getQueuedAt(),
            processing_started_at: $domainModel->getProcessingStartedAt(),
            generated_at: $domainModel->getGeneratedAt(),
            last_attempt_at: $domainModel->getLastAttemptAt(),
            error_code: $domainModel->getErrorCode(),
            error_message: $domainModel->getErrorMessage(),
            metadata_json: $domainModel->getMetadata() === []
                ? null
                : (string) json_encode($domainModel->getMetadata(), JSON_THROW_ON_ERROR),
            created_at: $domainModel->getCreatedAt(),
            updated_at: $domainModel->getUpdatedAt(),
        );
    }
}
