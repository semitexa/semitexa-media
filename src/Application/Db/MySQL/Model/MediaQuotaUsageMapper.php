<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Model;

use Semitexa\Orm\Attribute\AsMapper;
use Semitexa\Orm\Contract\ResourceModelMapperInterface;

#[AsMapper(resourceModel: MediaQuotaUsageResource::class, domainModel: MediaQuotaUsageResource::class)]
final class MediaQuotaUsageMapper implements ResourceModelMapperInterface
{
    public function toDomain(object $resourceModel): object
    {
        $resourceModel instanceof MediaQuotaUsageResource || throw new \InvalidArgumentException('Unexpected resource model.');
        return clone $resourceModel;
    }

    public function toSourceModel(object $domainModel): object
    {
        $domainModel instanceof MediaQuotaUsageResource || throw new \InvalidArgumentException('Unexpected resource model.');
        return clone $domainModel;
    }
}
