<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Model;

use Semitexa\Orm\Attribute\AsMapper;
use Semitexa\Orm\Domain\Contract\ResourceModelMapperInterface;

#[AsMapper(resourceModel: MediaCollectionResource::class, domainModel: MediaCollectionResource::class)]
final class MediaCollectionMapper implements ResourceModelMapperInterface
{
    public function toDomain(object $resourceModel): object
    {
        $resourceModel instanceof MediaCollectionResource || throw new \InvalidArgumentException('Unexpected resource model.');
        return clone $resourceModel;
    }

    public function toSourceModel(object $domainModel): object
    {
        $domainModel instanceof MediaCollectionResource || throw new \InvalidArgumentException('Unexpected domain model.');
        return clone $domainModel;
    }
}
