<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Model;

use Semitexa\Orm\Adapter\MySqlType;
use Semitexa\Orm\Attribute\Column;
use Semitexa\Orm\Attribute\FromTable;
use Semitexa\Orm\Attribute\Index;
use Semitexa\Orm\Attribute\TenantScoped;
use Semitexa\Orm\Metadata\HasColumnReferences;
use Semitexa\Orm\Metadata\HasRelationReferences;
use Semitexa\Orm\Trait\HasTimestamps;
use Semitexa\Orm\Trait\HasUuidV7;

#[FromTable(name: 'media_quota_usage')]
#[TenantScoped(strategy: 'same_storage')]
#[Index(columns: ['tenant_id', 'quota_bucket'], unique: true, name: 'uniq_media_quota_usage_tenant_bucket')]
#[Index(columns: ['tenant_id', 'updated_at'], name: 'idx_media_quota_usage_tenant_updated')]
class MediaQuotaUsageResource
{
    use HasUuidV7;
    use HasTimestamps;
    use HasColumnReferences;
    use HasRelationReferences;

    #[Column(type: MySqlType::Varchar, length: 64)]
    public string $tenant_id = '';

    #[Column(type: MySqlType::Varchar, length: 128)]
    public string $quota_bucket = 'default';

    #[Column(type: MySqlType::Bigint)]
    public int $asset_count = 0;

    #[Column(type: MySqlType::Bigint)]
    public int $original_bytes = 0;

    #[Column(type: MySqlType::Bigint)]
    public int $variant_bytes = 0;

    #[Column(type: MySqlType::Datetime, nullable: true)]
    public ?\DateTimeImmutable $last_recalculated_at = null;
}
