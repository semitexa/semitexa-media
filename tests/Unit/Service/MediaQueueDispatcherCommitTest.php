<?php

declare(strict_types=1);

namespace Semitexa\Media\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Queue\QueueTransportFactoryInterface;
use Semitexa\Core\Queue\QueueTransportInterface;
use Semitexa\Core\Queue\QueueTransportRegistry;
use Semitexa\Media\Application\Service\MediaQueueDispatcher;
use Semitexa\Media\Configuration\MediaConfig;
use Semitexa\Media\Domain\Model\MediaVariant;
use Semitexa\Orm\Domain\Model\ConnectionConfig;
use Semitexa\Orm\OrmManager;

/**
 * Published inside the caller's transaction, a broker handed the message to
 * a worker before the asset row was visible: dropped as "asset not found",
 * the variant was never made, and a rollback left the message behind.
 */
final class MediaQueueDispatcherCommitTest extends TestCase
{
    #[Test]
    public function a_variant_message_leaves_once_the_transaction_commits_and_never_after_a_rollback(): void
    {
        $published = new \ArrayObject();
        // Put back, not cleared, at the end: what was registered before is there for the tests after.
        $registry = [];
        foreach ((new \ReflectionClass(QueueTransportRegistry::class))->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
            $registry[$property->getName()] = $property->getValue();
        }
        QueueTransportRegistry::reset();
        QueueTransportRegistry::register('test-broker', new class ($published) implements QueueTransportFactoryInterface {
            public function __construct(private \ArrayObject $published) {}
            public function create(): QueueTransportInterface
            {
                return new class ($this->published) implements QueueTransportInterface {
                    public function __construct(private \ArrayObject $published) {}
                    public function publish(string $queueName, string $payload): void { $this->published->append($queueName); }
                    public function consume(string $queueName, callable $callback): void {}
                };
            }
        });
        $orm = new OrmManager(config: new ConnectionConfig(driver: 'sqlite', sqliteMemory: true));
        $config = new MediaConfig();
        foreach (['workerQueue' => 'media', 'workerTransport' => 'test-broker'] as $name => $value) {
            (new \ReflectionProperty(MediaConfig::class, $name))->setValue($config, $value);
        }
        $dispatcher = new MediaQueueDispatcher();
        (new \ReflectionProperty(MediaQueueDispatcher::class, 'config'))->setValue($dispatcher, $config);
        (new \ReflectionProperty(MediaQueueDispatcher::class, 'orm'))->setValue($dispatcher, $orm);
        $variant = new MediaVariant('v1', 't1', 'a1', 'thumb', 'queued');

        try {
            $orm->getTransactionManager()->run(function () use ($dispatcher, $variant, $published): void {
                $dispatcher->dispatch('a1', $variant);
                self::assertCount(0, $published, 'not before the commit');
            });
            self::assertSame(['media'], $published->getArrayCopy());

            try {
                $orm->getTransactionManager()->run(function () use ($dispatcher, $variant): void {
                    $dispatcher->dispatch('a1', $variant);
                    throw new \RuntimeException('rolled back');
                });
            } catch (\RuntimeException) {
            }
            self::assertCount(1, $published, 'a rollback publishes nothing');

            $dispatcher->dispatch('a1', $variant);
            self::assertCount(2, $published, 'outside a transaction it goes at once');
        } finally {
            foreach ($registry as $name => $value) {
                (new \ReflectionProperty(QueueTransportRegistry::class, $name))->setValue(null, $value);
            }
        }
    }
}
