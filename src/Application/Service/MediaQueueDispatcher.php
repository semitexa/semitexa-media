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
use Semitexa\Orm\OrmManager;
use Semitexa\Tenancy\Application\Service\TenantAwareJobSerializer;

#[AsService]
final class MediaQueueDispatcher
{
    #[InjectAsReadonly]
    protected MediaConfig $config;

    #[InjectAsReadonly(optional: true)]
    protected OrmManager $orm;

    public function dispatch(string $assetId, MediaVariant $variant): void
    {
        $message = new QueuedMediaTransformMessage(
            assetId:    $assetId,
            variantKey: $variant->getVariantKey(),
            tenantId:   $variant->getTenantId() ?? '',
        );

        // Wrapped now, under the caller's tenant; published below.
        $payload = json_encode(TenantAwareJobSerializer::wrap($message->jsonSerialize()), JSON_THROW_ON_ERROR);
        $publish = function () use ($payload): void {
            QueueTransportRegistry::create($this->config->workerTransport() ?: QueueConfig::defaultTransport())
                ->publish($this->config->workerQueue(), $payload);
        };

        // Inside the caller's transaction it goes once that commits: a broker
        // handed it to a worker before the asset row was visible, the worker
        // dropped it as "asset not found", and the variant was never made; a
        // rollback still left the message behind.
        $transaction = isset($this->orm) ? $this->orm->getTransactionManager() : null;
        if ($transaction !== null && $transaction->isActive()) {
            $transaction->afterCommit($publish);

            return;
        }
        $publish();
    }
}
