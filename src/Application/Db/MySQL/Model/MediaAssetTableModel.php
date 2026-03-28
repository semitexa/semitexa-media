<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Model;

use Semitexa\Orm\Adapter\MySqlType;
use Semitexa\Orm\Attribute\Column;
use Semitexa\Orm\Attribute\FromTable;
use Semitexa\Orm\Attribute\PrimaryKey;
use Semitexa\Orm\Metadata\HasColumnReferences;
use Semitexa\Orm\Metadata\HasRelationReferences;

#[FromTable(name: 'media_assets')]
final readonly class MediaAssetTableModel
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
        #[Column(type: MySqlType::Varchar, length: 32)]
        public string $storage_driver,
        #[Column(type: MySqlType::Varchar, length: 1024)]
        public string $original_path,
        #[Column(type: MySqlType::Varchar, length: 255)]
        public string $original_filename,
        #[Column(type: MySqlType::Varchar, length: 128)]
        public string $mime_type,
        #[Column(type: MySqlType::Varchar, length: 32)]
        public string $media_kind,
        #[Column(type: MySqlType::Varchar, length: 32)]
        public string $visibility,
        #[Column(type: MySqlType::Varchar, length: 32)]
        public string $status,
        #[Column(type: MySqlType::Bigint)]
        public int $byte_size,
        #[Column(type: MySqlType::Int, nullable: true)]
        public ?int $width,
        #[Column(type: MySqlType::Int, nullable: true)]
        public ?int $height,
        #[Column(type: MySqlType::Varchar, length: 32, nullable: true)]
        public ?string $orientation,
        #[Column(type: MySqlType::Char, length: 64)]
        public string $sha256,
        #[Column(type: MySqlType::Varchar, length: 1024, nullable: true)]
        public ?string $alt_text,
        #[Column(type: MySqlType::LongText, nullable: true)]
        public ?string $metadata_json,
        #[Column(type: MySqlType::Varchar, length: 128, nullable: true)]
        public ?string $created_by,
        #[Column(type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $ready_at,
        #[Column(type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $deleted_at,
        #[Column(type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $created_at,
        #[Column(type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $updated_at,
    ) {}
}
