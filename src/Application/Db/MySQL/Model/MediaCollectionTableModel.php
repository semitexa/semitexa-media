<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Model;

use Semitexa\Orm\Adapter\MySqlType;
use Semitexa\Orm\Attribute\Column;
use Semitexa\Orm\Attribute\FromTable;
use Semitexa\Orm\Attribute\PrimaryKey;
use Semitexa\Orm\Metadata\HasColumnReferences;
use Semitexa\Orm\Metadata\HasRelationReferences;

#[FromTable(name: 'media_collections')]
final readonly class MediaCollectionTableModel
{
    use HasColumnReferences;
    use HasRelationReferences;

    public function __construct(
        #[PrimaryKey(strategy: 'uuid')]
        #[Column(type: MySqlType::Binary, length: 16)]
        public string $id,
        #[Column(type: MySqlType::Varchar, length: 64, nullable: true)]
        public ?string $tenant_id,
        #[Column(type: MySqlType::Varchar, length: 128)]
        public string $collection_key,
        #[Column(type: MySqlType::TinyInt)]
        public int $enabled,
        #[Column(type: MySqlType::Varchar, length: 32)]
        public string $media_kind,
        #[Column(type: MySqlType::Varchar, length: 32)]
        public string $visibility_default,
        #[Column(type: MySqlType::Varchar, length: 128)]
        public string $quota_bucket,
        #[Column(type: MySqlType::LongText)]
        public string $allowed_mime_types_json,
        #[Column(type: MySqlType::Bigint, nullable: true)]
        public ?int $max_original_bytes,
        #[Column(type: MySqlType::Int, nullable: true)]
        public ?int $max_width,
        #[Column(type: MySqlType::Int, nullable: true)]
        public ?int $max_height,
        #[Column(type: MySqlType::Int, nullable: true)]
        public ?int $max_asset_count,
        #[Column(type: MySqlType::LongText)]
        public string $transform_profile_json,
        #[Column(type: MySqlType::LongText, nullable: true)]
        public ?string $metadata_json,
        #[Column(type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $created_at,
        #[Column(type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $updated_at,
    ) {}
}
