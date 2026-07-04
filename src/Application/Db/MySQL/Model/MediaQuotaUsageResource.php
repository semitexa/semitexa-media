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

#[FromTable(name: 'media_quota_usage')]
#[TenantScoped(strategy: 'same_storage', column: 'tenant_id')]
#[Index(columns: ['tenant_id', 'quota_bucket'], unique: true, name: 'uniq_media_quota_usage_tenant_bucket')]
#[Index(columns: ['tenant_id', 'updated_at'], name: 'idx_media_quota_usage_tenant_updated')]
final readonly class MediaQuotaUsageResource
{
    use HasColumnReferences;
    use HasRelationReferences;

    public function __construct(
        #[PrimaryKey(strategy: 'uuid')]
        #[Column(type: MySqlType::Binary, length: 16)]
        public string $id = '',

        #[Column(type: MySqlType::Varchar, length: 64)]
        public string $tenant_id = '',

        #[Column(type: MySqlType::Varchar, length: 128)]
        public string $quota_bucket = 'default',

        #[Column(type: MySqlType::Bigint)]
        public int $asset_count = 0,

        #[Column(type: MySqlType::Bigint)]
        public int $original_bytes = 0,

        #[Column(type: MySqlType::Bigint)]
        public int $variant_bytes = 0,

        #[Column(type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $last_recalculated_at = null,

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
