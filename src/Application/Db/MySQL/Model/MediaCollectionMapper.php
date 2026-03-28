<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Model;

use Semitexa\Orm\Attribute\AsMapper;
use Semitexa\Orm\Contract\TableModelMapper;

#[AsMapper(tableModel: MediaCollectionTableModel::class, domainModel: MediaCollectionResource::class)]
final class MediaCollectionMapper implements TableModelMapper
{
    public function toDomain(object $tableModel): object
    {
        $tableModel instanceof MediaCollectionTableModel || throw new \InvalidArgumentException('Unexpected table model.');

        $resource = new MediaCollectionResource();
        foreach (get_object_vars($tableModel) as $property => $value) {
            $resource->{$property} = $value;
        }

        return $resource;
    }

    public function toTableModel(object $domainModel): object
    {
        $domainModel instanceof MediaCollectionResource || throw new \InvalidArgumentException('Unexpected resource model.');

        return new MediaCollectionTableModel(...get_object_vars($domainModel));
    }
}
