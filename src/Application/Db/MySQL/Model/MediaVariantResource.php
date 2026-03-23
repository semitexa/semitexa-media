<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Model;

use Semitexa\Orm\Adapter\MySqlType;
use Semitexa\Orm\Attribute\Column;
use Semitexa\Orm\Attribute\FromTable;
use Semitexa\Orm\Attribute\Index;
use Semitexa\Orm\Attribute\TenantScoped;
use Semitexa\Orm\Trait\HasTimestamps;
use Semitexa\Orm\Trait\HasUuidV7;

#[FromTable(name: 'media_variants')]
#[TenantScoped(strategy: 'same_storage')]
#[Index(columns: ['media_asset_id', 'variant_key'], unique: true, name: 'uniq_media_variants_asset_key')]
#[Index(columns: ['tenant_id', 'status', 'queued_at'], name: 'idx_media_variants_tenant_status_queued')]
#[Index(columns: ['status', 'lease_expires_at'], name: 'idx_media_variants_status_lease')]
#[Index(columns: ['tenant_id', 'media_asset_id', 'status'], name: 'idx_media_variants_tenant_asset_status')]
class MediaVariantResource
{
    use HasUuidV7;
    use HasTimestamps;

    #[Column(type: MySqlType::Varchar, length: 64, nullable: true)]
    public ?string $tenant_id = null;

    #[Column(type: MySqlType::Binary, length: 16)]
    public string $media_asset_id = '';

    #[Column(type: MySqlType::Varchar, length: 128)]
    public string $variant_key = '';

    #[Column(type: MySqlType::Varchar, length: 32, nullable: true)]
    public ?string $storage_driver = null;

    #[Column(type: MySqlType::Varchar, length: 1024, nullable: true)]
    public ?string $storage_path = null;

    #[Column(type: MySqlType::Varchar, length: 128, nullable: true)]
    public ?string $mime_type = null;

    #[Column(type: MySqlType::Varchar, length: 32)]
    public string $status = 'queued';

    #[Column(type: MySqlType::Varchar, length: 32)]
    public string $resize_mode = 'fit';

    #[Column(type: MySqlType::Int, nullable: true)]
    public ?int $target_width = null;

    #[Column(type: MySqlType::Int, nullable: true)]
    public ?int $target_height = null;

    #[Column(type: MySqlType::Int, nullable: true)]
    public ?int $actual_width = null;

    #[Column(type: MySqlType::Int, nullable: true)]
    public ?int $actual_height = null;

    #[Column(type: MySqlType::Int, nullable: true)]
    public ?int $quality = null;

    #[Column(type: MySqlType::Bigint, nullable: true)]
    public ?int $byte_size = null;

    #[Column(type: MySqlType::Varchar, length: 128, nullable: true)]
    public ?string $lease_owner = null;

    #[Column(type: MySqlType::Datetime, nullable: true)]
    public ?\DateTimeImmutable $lease_expires_at = null;

    #[Column(type: MySqlType::Int)]
    public int $attempt_count = 0;

    #[Column(type: MySqlType::Int)]
    public int $max_attempts = 3;

    #[Column(type: MySqlType::Datetime, nullable: true)]
    public ?\DateTimeImmutable $queued_at = null;

    #[Column(type: MySqlType::Datetime, nullable: true)]
    public ?\DateTimeImmutable $processing_started_at = null;

    #[Column(type: MySqlType::Datetime, nullable: true)]
    public ?\DateTimeImmutable $generated_at = null;

    #[Column(type: MySqlType::Datetime, nullable: true)]
    public ?\DateTimeImmutable $last_attempt_at = null;

    #[Column(type: MySqlType::Varchar, length: 64, nullable: true)]
    public ?string $error_code = null;

    #[Column(type: MySqlType::LongText, nullable: true)]
    public ?string $error_message = null;

    #[Column(type: MySqlType::LongText, nullable: true)]
    public ?string $metadata_json = null;
}
