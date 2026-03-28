<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Model;

use Semitexa\Orm\Attribute\AsMapper;
use Semitexa\Orm\Contract\TableModelMapper;

#[AsMapper(tableModel: MediaVariantTableModel::class, domainModel: MediaVariantResource::class)]
final class MediaVariantMapper implements TableModelMapper
{
    public function toDomain(object $tableModel): object
    {
        $tableModel instanceof MediaVariantTableModel || throw new \InvalidArgumentException('Unexpected table model.');

        $resource = new MediaVariantResource();
        foreach (get_object_vars($tableModel) as $property => $value) {
            $resource->{$property} = $value;
        }

        return $resource;
    }

    public function toTableModel(object $domainModel): object
    {
        $domainModel instanceof MediaVariantResource || throw new \InvalidArgumentException('Unexpected resource model.');

        return new MediaVariantTableModel(...get_object_vars($domainModel));
    }
}
