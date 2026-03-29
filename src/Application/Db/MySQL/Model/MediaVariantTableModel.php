<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Model;

use Semitexa\Orm\Adapter\MySqlType;
use Semitexa\Orm\Attribute\Column;
use Semitexa\Orm\Attribute\FromTable;
use Semitexa\Orm\Attribute\PrimaryKey;
use Semitexa\Orm\Metadata\HasColumnReferences;
use Semitexa\Orm\Metadata\HasRelationReferences;

#[FromTable(name: 'media_variants')]
final readonly class MediaVariantTableModel
{
    use HasColumnReferences;
    use HasRelationReferences;

    public function __construct(
        #[PrimaryKey(strategy: 'uuid')]
        #[Column(type: MySqlType::Binary, length: 16)]
        public string $id,
        #[Column(type: MySqlType::Varchar, length: 64, nullable: true)]
        public ?string $tenant_id,
        #[Column(type: MySqlType::Binary, length: 16)]
        public string $media_asset_id,
        #[Column(type: MySqlType::Varchar, length: 128)]
        public string $variant_key,
        #[Column(type: MySqlType::Varchar, length: 32, nullable: true)]
        public ?string $storage_driver,
        #[Column(type: MySqlType::Varchar, length: 1024, nullable: true)]
        public ?string $storage_path,
        #[Column(type: MySqlType::Varchar, length: 128, nullable: true)]
        public ?string $mime_type,
        #[Column(type: MySqlType::Varchar, length: 32)]
        public string $status,
        #[Column(type: MySqlType::Varchar, length: 32)]
        public string $resize_mode,
        #[Column(type: MySqlType::Int, nullable: true)]
        public ?int $target_width,
        #[Column(type: MySqlType::Int, nullable: true)]
        public ?int $target_height,
        #[Column(type: MySqlType::Int, nullable: true)]
        public ?int $actual_width,
        #[Column(type: MySqlType::Int, nullable: true)]
        public ?int $actual_height,
        #[Column(type: MySqlType::Int, nullable: true)]
        public ?int $quality,
        #[Column(type: MySqlType::Bigint, nullable: true)]
        public ?int $byte_size,
        #[Column(type: MySqlType::Varchar, length: 128, nullable: true)]
        public ?string $lease_owner,
        #[Column(type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $lease_expires_at,
        #[Column(type: MySqlType::Int)]
        public int $attempt_count,
        #[Column(type: MySqlType::Int)]
        public int $max_attempts,
        #[Column(type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $queued_at,
        #[Column(type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $processing_started_at,
        #[Column(type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $generated_at,
        #[Column(type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $last_attempt_at,
        #[Column(type: MySqlType::Varchar, length: 64, nullable: true)]
        public ?string $error_code,
        #[Column(type: MySqlType::LongText, nullable: true)]
        public ?string $error_message,
        #[Column(type: MySqlType::LongText, nullable: true)]
        public ?string $metadata_json,
        #[Column(type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $created_at,
        #[Column(type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $updated_at,
    ) {}
}
