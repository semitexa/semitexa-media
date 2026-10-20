<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\AsWorkerProcessSource;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Contract\WorkerProcessSourceInterface;
use Semitexa\Core\Queue\QueueConfig;
use Semitexa\Core\Queue\WorkerProcess;
use Semitexa\Media\Configuration\MediaConfig;

/**
 * media:work on the queue MediaQueueDispatcher publishes variant jobs to.
 * Nothing when that queue is in-memory: no worker can read it there.
 */
#[AsService]
#[AsWorkerProcessSource]
final class MediaWorkerProcesses implements WorkerProcessSourceInterface
{
    #[InjectAsReadonly]
    protected MediaConfig $config;

    public function workerProcesses(): array
    {
        $transport = $this->config->workerTransport() ?: QueueConfig::defaultTransport();
        if (QueueConfig::isProcessLocal($transport)) {
            return [];
        }

        return [new WorkerProcess(['media:work', $transport, $this->config->workerQueue()])];
    }
}
