<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Model;

use Semitexa\Orm\Adapter\MySqlType;
use Semitexa\Orm\Attribute\Column;
use Semitexa\Orm\Attribute\FromTable;
use Semitexa\Orm\Attribute\PrimaryKey;
use Semitexa\Orm\Metadata\HasColumnReferences;
use Semitexa\Orm\Metadata\HasRelationReferences;

#[FromTable(name: 'media_quota_usage')]
final readonly class MediaQuotaUsageTableModel
{
    use HasColumnReferences;
    use HasRelationReferences;

    public function __construct(
        #[PrimaryKey(strategy: 'uuid')]
        #[Column(type: MySqlType::Binary, length: 16)]
        public string $id,
        #[Column(type: MySqlType::Varchar, length: 64)]
        public string $tenant_id,
        #[Column(type: MySqlType::Varchar, length: 128)]
        public string $quota_bucket,
        #[Column(type: MySqlType::Bigint)]
        public int $asset_count,
        #[Column(type: MySqlType::Bigint)]
        public int $original_bytes,
        #[Column(type: MySqlType::Bigint)]
        public int $variant_bytes,
        #[Column(type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $last_recalculated_at,
        #[Column(type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $created_at,
        #[Column(type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $updated_at,
    ) {}
}
