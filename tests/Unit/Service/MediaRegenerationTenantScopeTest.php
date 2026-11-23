<?php

declare(strict_types=1);

namespace Semitexa\Media\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Queue\QueueTransportFactoryInterface;
use Semitexa\Core\Queue\QueueTransportInterface;
use Semitexa\Core\Queue\QueueTransportRegistry;
use Semitexa\Media\Application\Console\Command\MediaRegenerateCommand;
use Semitexa\Media\Application\Db\MySQL\Repository\MediaAssetRepository;
use Semitexa\Media\Application\Db\MySQL\Repository\MediaVariantRepository;
use Semitexa\Media\Application\Service\MediaObjectLocator;
use Semitexa\Media\Application\Service\MediaQueueDispatcher;
use Semitexa\Media\Application\Service\MediaService;
use Semitexa\Media\Application\Service\MediaVariantRegenerator;
use Semitexa\Media\Configuration\MediaConfig;
use Semitexa\Orm\Domain\Model\ConnectionConfig;
use Semitexa\Orm\OrmManager;
use Semitexa\Tenancy\Context\CoroutineContextStore;
use Semitexa\Tenancy\Context\TenantContext;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Whose variants an asset id may put back on the queue.
 *
 * An asset id is public: it is written into the markup that names the
 * picture. The variant repository works under system scope, so a
 * regeneration that went to it with a bare id reset and re-dispatched the
 * variants of whichever tenant the id belonged to. A caller acting in a
 * tenant now reaches its own assets only; the operator's console, which has
 * no tenant, reaches any asset through the regenerator, on purpose.
 */
final class MediaRegenerationTenantScopeTest extends TestCase
{
    private OrmManager $orm;
    private MediaService $service;
    private MediaRegenerateCommand $command;
    /** @var \ArrayObject<int, string> */
    private \ArrayObject $published;
    /** @var array<string, mixed> */
    private array $registry = [];
    private ?TenantContext $previousContext = null;

    protected function setUp(): void
    {
        $this->orm = new OrmManager(config: new ConnectionConfig(driver: 'sqlite', sqliteMemory: true));
        $this->orm->getAdapter()->execute(
            'CREATE TABLE media_assets (
                id TEXT PRIMARY KEY, tenant_id TEXT, collection_key TEXT NOT NULL DEFAULT "",
                storage_driver TEXT NOT NULL DEFAULT "", original_path TEXT NOT NULL DEFAULT "",
                original_filename TEXT NOT NULL DEFAULT "", mime_type TEXT NOT NULL DEFAULT "",
                media_kind TEXT NOT NULL DEFAULT "image", visibility TEXT NOT NULL DEFAULT "private",
                status TEXT NOT NULL DEFAULT "pending", byte_size INTEGER NOT NULL DEFAULT 0,
                width INTEGER, height INTEGER, orientation TEXT, sha256 TEXT NOT NULL DEFAULT "",
                alt_text TEXT, metadata_json TEXT, created_by TEXT,
                ready_at TEXT, deleted_at TEXT, created_at TEXT, updated_at TEXT
            )',
        );
        $this->orm->getAdapter()->execute(
            'CREATE TABLE media_variants (
                id TEXT PRIMARY KEY, tenant_id TEXT, media_asset_id TEXT NOT NULL, variant_key TEXT NOT NULL,
                storage_driver TEXT, storage_path TEXT, mime_type TEXT,
                status TEXT NOT NULL DEFAULT "queued", resize_mode TEXT NOT NULL DEFAULT "fit",
                target_width INTEGER, target_height INTEGER, actual_width INTEGER, actual_height INTEGER,
                quality INTEGER, byte_size INTEGER, lease_owner TEXT, lease_expires_at TEXT,
                attempt_count INTEGER NOT NULL DEFAULT 0, max_attempts INTEGER NOT NULL DEFAULT 3,
                queued_at TEXT, processing_started_at TEXT, generated_at TEXT, last_attempt_at TEXT,
                error_code TEXT, error_message TEXT, metadata_json TEXT,
                created_at TEXT, updated_at TEXT
            )',
        );

        // Put back, not cleared, at the end: what was registered before is there for the tests after.
        foreach ((new \ReflectionClass(QueueTransportRegistry::class))->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
            $this->registry[$property->getName()] = $property->getValue();
        }
        $this->published = $published = new \ArrayObject();
        QueueTransportRegistry::reset();
        QueueTransportRegistry::register('test-broker', new class ($published) implements QueueTransportFactoryInterface {
            /** @param \ArrayObject<int, string> $published */
            public function __construct(private \ArrayObject $published) {}
            public function create(): QueueTransportInterface
            {
                return new class ($this->published) implements QueueTransportInterface {
                    /** @param \ArrayObject<int, string> $published */
                    public function __construct(private \ArrayObject $published) {}
                    public function publish(string $queueName, string $payload): void { $this->published->append($payload); }
                    public function consume(string $queueName, callable $callback): void {}
                };
            }
        });

        $assets = new MediaAssetRepository();
        $variants = new MediaVariantRepository();
        $config = new MediaConfig();
        $dispatcher = new MediaQueueDispatcher();
        $regenerator = new MediaVariantRegenerator();
        $locator = new MediaObjectLocator();
        $this->service = new MediaService();
        $this->command = new MediaRegenerateCommand();

        self::wire($assets, ['orm' => $this->orm]);
        self::wire($variants, ['orm' => $this->orm]);
        self::wire($config, ['workerQueue' => 'media', 'workerTransport' => 'test-broker']);
        self::wire($dispatcher, ['config' => $config, 'orm' => $this->orm]);
        self::wire($regenerator, ['variantRepository' => $variants, 'queueDispatcher' => $dispatcher]);
        self::wire($locator, ['assetRepository' => $assets, 'variantRepository' => $variants]);
        self::wire($this->service, ['objectLocator' => $locator, 'regenerator' => $regenerator]);
        self::wire($this->command, ['assetRepository' => $assets, 'regenerator' => $regenerator]);

        $this->previousContext = CoroutineContextStore::swapFallback(null);

        $this->seedAsset('asset-a', 'tenant-a');
        $this->seedVariant('va-thumb', 'asset-a', 'thumb', 'tenant-a');
        $this->seedVariant('va-content', 'asset-a', 'content', 'tenant-a');
        $this->seedAsset('asset-b', 'tenant-b');
        $this->seedVariant('vb-thumb', 'asset-b', 'thumb', 'tenant-b');
        $this->seedVariant('vb-content', 'asset-b', 'content', 'tenant-b');
    }

    protected function tearDown(): void
    {
        CoroutineContextStore::swapFallback($this->previousContext);
        foreach ($this->registry as $name => $value) {
            (new \ReflectionProperty(QueueTransportRegistry::class, $name))->setValue(null, $value);
        }
    }

    #[Test]
    public function a_tenant_cannot_requeue_another_tenants_variants(): void
    {
        $this->actAs('tenant-a');

        $this->service->queueRegeneration('asset-b');
        $this->service->queueRegeneration('asset-b', 'thumb');

        self::assertSame(['ready', 'ready'], $this->statuses('asset-b'), 'nothing reset');
        self::assertCount(0, $this->published, 'nothing dispatched');
    }

    /** Refused and unknown look the same: neither says whether the id exists. */
    #[Test]
    public function another_tenants_asset_is_answered_like_an_unknown_id(): void
    {
        $this->actAs('tenant-a');

        $this->service->queueRegeneration('asset-b', 'thumb');
        $foreign = [$this->rows(), $this->published->getArrayCopy()];

        $this->service->queueRegeneration('nothing-by-that-name', 'thumb');

        self::assertSame($foreign, [$this->rows(), $this->published->getArrayCopy()]);
        self::assertCount(0, $this->published);
    }

    #[Test]
    public function a_tenant_requeues_its_own_variants(): void
    {
        $this->actAs('tenant-a');

        $this->service->queueRegeneration('asset-a', 'thumb');

        self::assertSame(['ready', 'queued'], $this->statuses('asset-a'), 'the one asked for');
        self::assertSame([['asset-a', 'thumb', 'tenant-a']], $this->messages());

        $this->service->queueRegeneration('asset-a');

        self::assertSame(['queued', 'queued'], $this->statuses('asset-a'));
        self::assertCount(3, $this->published);
        self::assertSame(['ready', 'ready'], $this->statuses('asset-b'), 'the neighbour is untouched');
    }

    /**
     * The asset check alone is not the whole answer: the variant rows are
     * read under system scope too. One that names this asset but carries
     * another tenant is that tenant's row, and its message would be worked
     * under that tenant's name.
     */
    #[Test]
    public function a_variant_stamped_with_another_tenant_is_left_alone(): void
    {
        $this->seedVariant('va-stray', 'asset-a', 'hero', 'tenant-b');
        $this->actAs('tenant-a');

        $this->service->queueRegeneration('asset-a');
        $this->service->queueRegeneration('asset-a', 'hero');

        self::assertSame(['queued', 'ready', 'queued'], $this->statuses('asset-a'), 'content, hero, thumb');
        self::assertSame(
            [['asset-a', 'content', 'tenant-a'], ['asset-a', 'thumb', 'tenant-a']],
            $this->sorted($this->messages()),
        );
    }

    /**
     * A console has no tenant. Gating it with the caller's tenant, as the
     * request path is gated, would refuse every asset a tenant owns.
     */
    #[Test]
    public function the_operators_command_regenerates_a_tenant_owned_asset(): void
    {
        self::assertNull(CoroutineContextStore::get(), 'no tenant, as on a console');
        $tester = new CommandTester($this->command);

        self::assertSame(0, $tester->execute(['asset-id' => 'asset-b']), $tester->getDisplay());

        self::assertSame(['queued', 'queued'], $this->statuses('asset-b'));
        self::assertSame(
            [['asset-b', 'content', 'tenant-b'], ['asset-b', 'thumb', 'tenant-b']],
            $this->sorted($this->messages()),
        );
        self::assertSame(['ready', 'ready'], $this->statuses('asset-a'), 'only the asset named');
    }

    #[Test]
    public function the_operators_command_regenerates_a_tenants_collection(): void
    {
        $tester = new CommandTester($this->command);

        self::assertSame(
            0,
            $tester->execute(['--collection' => 'cms:content', '--tenant' => 'tenant-b', '--variant' => 'thumb']),
            $tester->getDisplay(),
        );

        self::assertSame(['ready', 'queued'], $this->statuses('asset-b'));
        self::assertSame([['asset-b', 'thumb', 'tenant-b']], $this->messages());
        self::assertSame(['ready', 'ready'], $this->statuses('asset-a'));
    }

    /** It used to report success for an id that named nothing. */
    #[Test]
    public function the_operators_command_says_so_when_the_asset_does_not_exist(): void
    {
        $tester = new CommandTester($this->command);

        self::assertSame(1, $tester->execute(['asset-id' => 'nothing-by-that-name']));
        self::assertStringContainsString('not found', $tester->getDisplay());
        self::assertCount(0, $this->published);
    }

    private function actAs(string $tenantId): void
    {
        CoroutineContextStore::swapFallback(TenantContext::fromResolution($tenantId, 'test'));
    }

    /** @return list<string> statuses of the asset's variants, by variant key */
    private function statuses(string $assetId): array
    {
        $rows = $this->orm->getAdapter()->execute(
            'SELECT status FROM media_variants WHERE media_asset_id = :asset ORDER BY variant_key',
            ['asset' => $assetId],
        )->fetchAll();

        return array_values(array_map(
            static fn (array $row): string => is_string($row['status']) ? $row['status'] : '',
            $rows,
        ));
    }

    /** @return list<array<string, mixed>> every variant row as stored */
    private function rows(): array
    {
        return array_values($this->orm->getAdapter()->execute('SELECT * FROM media_variants ORDER BY id')->fetchAll());
    }

    /** @return list<array{string, string, string}> asset, variant key and tenant of each message sent */
    private function messages(): array
    {
        $messages = [];
        foreach ($this->published as $payload) {
            /** @var array{assetId: string, variantKey: string, tenantId: string} $data */
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $messages[] = [$data['assetId'], $data['variantKey'], $data['tenantId']];
        }

        return $messages;
    }

    /**
     * @param list<array{string, string, string}> $messages
     * @return list<array{string, string, string}>
     */
    private function sorted(array $messages): array
    {
        sort($messages);

        return $messages;
    }

    /** @param array<string, mixed> $properties */
    private static function wire(object $target, array $properties): void
    {
        foreach ($properties as $name => $value) {
            (new \ReflectionProperty($target, $name))->setValue($target, $value);
        }
    }

    private function seedAsset(string $id, string $tenantId): void
    {
        $this->orm->getAdapter()->execute(
            'INSERT INTO media_assets (id, tenant_id, collection_key, original_path, mime_type, status, created_at)
             VALUES (:id, :tenant, :collection, :path, :mime, :status, :created)',
            [
                'id' => $id, 'tenant' => $tenantId, 'collection' => 'cms:content',
                'path' => 'originals/' . $id . '.png', 'mime' => 'image/png',
                'status' => 'ready', 'created' => '2026-09-06 08:00:00',
            ],
        );
    }

    private function seedVariant(string $id, string $assetId, string $key, string $tenantId): void
    {
        $this->orm->getAdapter()->execute(
            'INSERT INTO media_variants (id, tenant_id, media_asset_id, variant_key, status, storage_path, mime_type, generated_at)
             VALUES (:id, :tenant, :asset, :key, :status, :path, :mime, :generated)',
            [
                'id' => $id, 'tenant' => $tenantId, 'asset' => $assetId, 'key' => $key,
                'status' => 'ready', 'path' => 'variants/' . $id . '.webp', 'mime' => 'image/webp',
                'generated' => '2026-09-06 08:05:00',
            ],
        );
    }
}
