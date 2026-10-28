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
use Semitexa\Orm\Application\Service\Queue\DatabaseQueueTransport;
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
        $transport = QueueTransportRegistry::create($this->config->workerTransport() ?: QueueConfig::defaultTransport());
        $publish = function () use ($transport, $payload): void {
            $transport->publish($this->config->workerQueue(), $payload);
        };

        // Inside the caller's transaction a broker's message goes once that
        // commits: handed to a worker before the asset row was visible, it
        // was dropped as "asset not found", and the variant was never made; a
        // rollback still left it behind. The database queue joins the
        // transaction itself, and stays in it.
        $transaction = isset($this->orm) ? $this->orm->getTransactionManager() : null;
        if ($transaction !== null && $transaction->isActive() && !$transport instanceof DatabaseQueueTransport) {
            $transaction->afterCommit($publish);

            return;
        }
        $publish();
    }
}
