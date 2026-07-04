<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Model;

use Semitexa\Orm\Adapter\MySqlType;
use Semitexa\Orm\Attribute\Column;
use Semitexa\Orm\Attribute\FromTable;
use Semitexa\Orm\Attribute\Index;
use Semitexa\Orm\Attribute\PrimaryKey;
use Semitexa\Orm\Attribute\TenantScoped;
use Semitexa\Orm\Metadata\HasColumnReferences;
use Semitexa\Orm\Metadata\HasRelationReferences;

#[FromTable(name: 'media_assets')]
#[TenantScoped(strategy: 'same_storage', column: 'tenant_id')]
#[Index(columns: ['tenant_id', 'collection_key', 'created_at'], name: 'idx_media_assets_tenant_collection')]
#[Index(columns: ['tenant_id', 'status', 'created_at'], name: 'idx_media_assets_tenant_status')]
#[Index(columns: ['tenant_id', 'sha256'], name: 'idx_media_assets_tenant_sha256')]
#[Index(columns: ['collection_key', 'status', 'created_at'], name: 'idx_media_assets_collection_status')]
final readonly class MediaAssetResource
{
    use HasColumnReferences;
    use HasRelationReferences;

    public function __construct(
        #[PrimaryKey(strategy: 'uuid')]
        #[Column(type: MySqlType::Binary, length: 16)]
        public string $id = '',

        #[Column(type: MySqlType::Varchar, length: 64, nullable: true)]
        public ?string $tenant_id = null,

        #[Column(type: MySqlType::Varchar, length: 128)]
        public string $collection_key = '',

        #[Column(type: MySqlType::Varchar, length: 32)]
        public string $storage_driver = '',

        #[Column(type: MySqlType::Varchar, length: 1024)]
        public string $original_path = '',

        #[Column(type: MySqlType::Varchar, length: 255)]
        public string $original_filename = '',

        #[Column(type: MySqlType::Varchar, length: 128)]
        public string $mime_type = '',

        #[Column(type: MySqlType::Varchar, length: 32)]
        public string $media_kind = 'image',

        #[Column(type: MySqlType::Varchar, length: 32)]
        public string $visibility = 'private',

        #[Column(type: MySqlType::Varchar, length: 32)]
        public string $status = 'pending',

        #[Column(type: MySqlType::Bigint)]
        public int $byte_size = 0,

        #[Column(type: MySqlType::Int, nullable: true)]
        public ?int $width = null,

        #[Column(type: MySqlType::Int, nullable: true)]
        public ?int $height = null,

        #[Column(type: MySqlType::Varchar, length: 32, nullable: true)]
        public ?string $orientation = null,

        #[Column(type: MySqlType::Char, length: 64)]
        public string $sha256 = '',

        #[Column(type: MySqlType::Varchar, length: 1024, nullable: true)]
        public ?string $alt_text = null,

        #[Column(type: MySqlType::LongText, nullable: true)]
        public ?string $metadata_json = null,

        #[Column(type: MySqlType::Varchar, length: 128, nullable: true)]
        public ?string $created_by = null,

        #[Column(type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $ready_at = null,

        #[Column(type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $deleted_at = null,

        #[Column(type: MySqlType::Datetime)]
        public ?\DateTimeImmutable $created_at = null,

        #[Column(type: MySqlType::Datetime)]
        public ?\DateTimeImmutable $updated_at = null,
    ) {
    }

    /**
     * Rebuild the row with the given property overrides (property => value).
     * `updated_at` refreshes automatically unless overridden.
     *
     * @param array<string, mixed> $overrides
     */
    public function copyWith(array $overrides): self
    {
        $overrides += ['updated_at' => new \DateTimeImmutable()];

        return new self(...array_merge(get_object_vars($this), $overrides));
    }

}
