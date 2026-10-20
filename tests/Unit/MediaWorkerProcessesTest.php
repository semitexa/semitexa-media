<?php

declare(strict_types=1);

namespace Semitexa\Media\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Queue\WorkerProcess;
use Semitexa\Media\Application\Service\MediaWorkerProcesses;
use Semitexa\Media\Configuration\MediaConfig;

/** What the worker container runs for media (tk-worker-runs-mail-work). */
final class MediaWorkerProcessesTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $env = [];

    protected function setUp(): void
    {
        foreach (['EVENTS_ASYNC', 'EVENTS_TRANSPORT'] as $key) {
            $this->env[$key] = getenv($key);
            putenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->env as $key => $value) {
            putenv($value === false ? $key : "{$key}={$value}");
        }
    }

    #[Test]
    public function media_work_reads_the_queue_and_transport_media_publishes_to(): void
    {
        self::assertSame(['5 media:work database thumbs'], $this->lines(transport: 'database', queue: 'thumbs'));
    }

    #[Test]
    public function without_its_own_transport_it_uses_the_default_one(): void
    {
        putenv('EVENTS_TRANSPORT=nats');

        self::assertSame(['5 media:work nats media'], $this->lines(transport: '', queue: 'media'));
    }

    #[Test]
    public function a_queue_only_this_process_can_read_gets_no_worker(): void
    {
        putenv('EVENTS_ASYNC=0');

        self::assertSame([], $this->lines(transport: '', queue: 'media'));
    }

    /** So worker:processes can refuse another kind of worker on the media queue (tk-nats-consumer-name-collision). */
    #[Test]
    public function media_work_says_which_queue_it_reads(): void
    {
        self::assertSame([['database', 'thumbs']], array_map(static fn (WorkerProcess $p): ?array => $p->reads, $this->processes('database', 'thumbs')));
    }

    /** @return list<string> */
    private function lines(string $transport, string $queue): array
    {
        return array_map(static fn (WorkerProcess $p): string => $p->line(), $this->processes($transport, $queue));
    }

    /** @return list<WorkerProcess> */
    private function processes(string $transport, string $queue): array
    {
        $config = (new \ReflectionClass(MediaConfig::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($config, 'workerTransport'))->setValue($config, $transport);
        (new \ReflectionProperty($config, 'workerQueue'))->setValue($config, $queue);
        $source = new MediaWorkerProcesses();
        (new \ReflectionProperty($source, 'config'))->setValue($source, $config);

        return $source->workerProcesses();
    }
}
