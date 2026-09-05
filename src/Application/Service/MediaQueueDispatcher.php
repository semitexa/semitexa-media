<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Queue\QueueConfig;
use Semitexa\Core\Queue\QueueTransportRegistry;
use Semitexa\Media\Domain\Model\MediaVariant;
use Semitexa\Media\Configuration\MediaConfig;
use Semitexa\Media\Domain\Model\QueuedMediaTransformMessage;
use Semitexa\Orm\Application\Service\Uuid7;
use Semitexa\Tenancy\Application\Service\TenantAwareJobSerializer;

#[AsService]
final class MediaQueueDispatcher
{
    #[InjectAsReadonly]
    protected MediaConfig $config;

    public function dispatch(string $assetId, MediaVariant $variant): void
    {
        $message = new QueuedMediaTransformMessage(
            assetId:    $assetId,
            variantKey: $variant->getVariantKey(),
            tenantId:   $variant->getTenantId() ?? '',
        );

        $payload = TenantAwareJobSerializer::wrap($message->jsonSerialize());

        $transport = QueueTransportRegistry::create(
            $this->config->workerTransport() ?: QueueConfig::defaultTransport(),
        );

        $transport->publish($this->config->workerQueue(), json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
