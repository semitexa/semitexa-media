<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Model;

use Semitexa\Orm\Adapter\MySqlType;
use Semitexa\Orm\Attribute\Column;
use Semitexa\Orm\Attribute\FromTable;
use Semitexa\Orm\Attribute\Index;
use Semitexa\Orm\Metadata\HasColumnReferences;
use Semitexa\Orm\Metadata\HasRelationReferences;
use Semitexa\Orm\Trait\HasTimestamps;
use Semitexa\Orm\Trait\HasUuidV7;

#[FromTable(name: 'media_collections')]
#[Index(columns: ['tenant_id', 'collection_key'], unique: true, name: 'uniq_media_collections_tenant_key')]
#[Index(columns: ['collection_key', 'enabled'], name: 'idx_media_collections_key_enabled')]
#[Index(columns: ['tenant_id', 'quota_bucket', 'enabled'], name: 'idx_media_collections_tenant_bucket')]
class MediaCollectionResource
{
    use HasUuidV7;
    use HasTimestamps;
    use HasColumnReferences;
    use HasRelationReferences;

    #[Column(type: MySqlType::Varchar, length: 64, nullable: true)]
    public ?string $tenant_id = null;

    #[Column(type: MySqlType::Varchar, length: 128)]
    public string $collection_key = '';

    #[Column(type: MySqlType::TinyInt)]
    public int $enabled = 1;

    #[Column(type: MySqlType::Varchar, length: 32)]
    public string $media_kind = 'image';

    #[Column(type: MySqlType::Varchar, length: 32)]
    public string $visibility_default = 'private';

    #[Column(type: MySqlType::Varchar, length: 128)]
    public string $quota_bucket = 'default';

    #[Column(type: MySqlType::LongText)]
    public string $allowed_mime_types_json = '[]';

    #[Column(type: MySqlType::Bigint, nullable: true)]
    public ?int $max_original_bytes = null;

    #[Column(type: MySqlType::Int, nullable: true)]
    public ?int $max_width = null;

    #[Column(type: MySqlType::Int, nullable: true)]
    public ?int $max_height = null;

    #[Column(type: MySqlType::Int, nullable: true)]
    public ?int $max_asset_count = null;

    #[Column(type: MySqlType::LongText)]
    public string $transform_profile_json = '[]';

    #[Column(type: MySqlType::LongText, nullable: true)]
    public ?string $metadata_json = null;
}
