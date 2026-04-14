<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Model;

use Semitexa\Orm\Attribute\AsMapper;
use Semitexa\Orm\Contract\ResourceModelMapperInterface;

#[AsMapper(resourceModel: MediaVariantResource::class, domainModel: MediaVariantResource::class)]
final class MediaVariantMapper implements ResourceModelMapperInterface
{
    public function toDomain(object $resourceModel): object
    {
        $resourceModel instanceof MediaVariantResource || throw new \InvalidArgumentException('Unexpected resource model.');
        return clone $resourceModel;
    }

    public function toSourceModel(object $domainModel): object
    {
        $domainModel instanceof MediaVariantResource || throw new \InvalidArgumentException('Unexpected resource model.');
        return clone $domainModel;
    }
}
