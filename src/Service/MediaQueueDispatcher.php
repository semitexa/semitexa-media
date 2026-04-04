<?php

declare(strict_types=1);

namespace Semitexa\Media\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Queue\QueueConfig;
use Semitexa\Core\Queue\QueueTransportRegistry;
use Semitexa\Media\Application\Db\MySQL\Model\MediaVariantResource;
use Semitexa\Media\Configuration\MediaConfig;
use Semitexa\Media\Queue\Message\QueuedMediaTransformMessage;
use Semitexa\Orm\Uuid\Uuid7;
use Semitexa\Tenancy\Propagation\TenantAwareJobSerializer;

#[AsService]
final class MediaQueueDispatcher
{
    #[InjectAsReadonly]
    protected MediaConfig $config;

    public function dispatch(string $assetId, MediaVariantResource $variant): void
    {
        $message = new QueuedMediaTransformMessage(
            assetId:    $assetId,
            variantKey: $variant->variant_key,
            tenantId:   $variant->tenant_id ?? '',
        );

        $payload = TenantAwareJobSerializer::wrap($message->jsonSerialize());

        $transport = QueueTransportRegistry::create(
            $this->config->workerTransport ?: QueueConfig::defaultTransport(),
        );

        $transport->publish($this->config->workerQueue, json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
