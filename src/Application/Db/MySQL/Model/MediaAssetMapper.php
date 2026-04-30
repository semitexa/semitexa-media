<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Model;

use Semitexa\Orm\Attribute\AsMapper;
use Semitexa\Orm\Domain\Contract\ResourceModelMapperInterface;

#[AsMapper(resourceModel: MediaAssetResource::class, domainModel: MediaAssetResource::class)]
final class MediaAssetMapper implements ResourceModelMapperInterface
{
    public function toDomain(object $resourceModel): object
    {
        $resourceModel instanceof MediaAssetResource || throw new \InvalidArgumentException('Unexpected resource model.');
        return clone $resourceModel;
    }

    public function toSourceModel(object $domainModel): object
    {
        $domainModel instanceof MediaAssetResource || throw new \InvalidArgumentException('Unexpected domain model.');
        return clone $domainModel;
    }
}
