<?php

declare(strict_types=1);

namespace Semitexa\Media\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Media\Application\Db\MySQL\Repository\MediaAssetRepository;
use Semitexa\Media\Application\Db\MySQL\Repository\MediaVariantRepository;
use Semitexa\Media\Application\Service\MediaObjectLocator;
use Semitexa\Orm\Domain\Model\ConnectionConfig;
use Semitexa\Orm\OrmManager;

/**
 * Which stored object an asset id resolves to — and whose asset id it may be.
 *
 * The second half is the reason this test exists. `findById()` carries no
 * tenant predicate, and an asset id is not a secret: it is written into the
 * article markup that names the picture. Without the check here, one tenant's
 * published page hands out addresses that resolve against another tenant's
 * files.
 */
final class MediaObjectLocatorTest extends TestCase
{
    private MediaObjectLocator $locator;
    private OrmManager $orm;

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

        $assets = new MediaAssetRepository();
        (new \ReflectionProperty(MediaAssetRepository::class, 'orm'))->setValue($assets, $this->orm);
        $variants = new MediaVariantRepository();
        (new \ReflectionProperty(MediaVariantRepository::class, 'orm'))->setValue($variants, $this->orm);

        $this->locator = new MediaObjectLocator();
        (new \ReflectionProperty(MediaObjectLocator::class, 'assetRepository'))->setValue($this->locator, $assets);
        (new \ReflectionProperty(MediaObjectLocator::class, 'variantRepository'))->setValue($this->locator, $variants);
    }

    /**
     * No tenant context is set in this process, so the caller's tenant is ''.
     * An asset stamped with anyone else's is not addressable from here.
     */
    #[Test]
    public function another_tenants_asset_is_not_found(): void
    {
        $this->seedAsset('a-1', tenantId: 'acme');

        self::assertNull($this->locator->locate('a-1'));
        self::assertNull($this->locator->locate('a-1', 'content'), 'nor through a variant');
    }

    #[Test]
    public function an_unknown_id_is_not_found(): void
    {
        self::assertNull($this->locator->locate('nothing-by-that-name'));
    }

    #[Test]
    public function the_original_is_the_answer_while_no_variant_is_ready(): void
    {
        $this->seedAsset('a-1');
        $this->seedVariant('v-1', 'a-1', 'content', status: 'queued', path: null);

        $object = $this->locator->locate('a-1', 'content');

        self::assertNotNull($object);
        self::assertSame('originals/a-1.png', $object->path);
        self::assertSame('image/png', $object->mimeType);
        self::assertSame('cms:content', $object->collectionKey);
    }

    #[Test]
    public function a_ready_variant_wins_and_carries_its_own_type(): void
    {
        $this->seedAsset('a-1');
        $this->seedVariant('v-1', 'a-1', 'content', status: 'ready', path: 'variants/a-1.webp', mimeType: 'image/webp');

        $object = $this->locator->locate('a-1', 'content');

        self::assertNotNull($object);
        self::assertSame('variants/a-1.webp', $object->path);
        self::assertSame('image/webp', $object->mimeType);
        // A rendition belongs wherever its asset does.
        self::assertSame('cms:content', $object->collectionKey);
    }

    /** A variant of someone else's asset is still someone else's. */
    #[Test]
    public function a_ready_variant_does_not_bypass_the_tenant_check(): void
    {
        $this->seedAsset('a-1', tenantId: 'acme');
        $this->seedVariant('v-1', 'a-1', 'content', status: 'ready', path: 'variants/a-1.webp', mimeType: 'image/webp');

        self::assertNull($this->locator->locate('a-1', 'content'));
    }

    private function seedAsset(string $id, string $tenantId = ''): void
    {
        $this->orm->getAdapter()->execute(
            'INSERT INTO media_assets (id, tenant_id, collection_key, original_path, mime_type, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $tenantId, 'cms:content', 'originals/' . $id . '.png', 'image/png', 'ready', '2026-09-06 08:00:00'],
        );
    }

    private function seedVariant(
        string $id,
        string $assetId,
        string $key,
        string $status,
        ?string $path,
        ?string $mimeType = null,
    ): void {
        $this->orm->getAdapter()->execute(
            'INSERT INTO media_variants (id, media_asset_id, variant_key, status, storage_path, mime_type, generated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $assetId, $key, $status, $path, $mimeType, '2026-09-06 08:05:00'],
        );
    }
}
