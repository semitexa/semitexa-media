<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Model;

use Semitexa\Orm\Attribute\AsMapper;
use Semitexa\Orm\Contract\TableModelMapper;

#[AsMapper(resourceModel: MediaAssetTableModel::class, domainModel: MediaAssetResource::class)]
final class MediaAssetMapper implements TableModelMapper
{
    public function toDomain(object $tableModel): object
    {
        $tableModel instanceof MediaAssetTableModel || throw new \InvalidArgumentException('Unexpected table model.');

        $resource = new MediaAssetResource();
        foreach (get_object_vars($tableModel) as $property => $value) {
            $resource->{$property} = $value;
        }

        return $resource;
    }

    public function toTableModel(object $domainModel): object
    {
        $domainModel instanceof MediaAssetResource || throw new \InvalidArgumentException('Unexpected resource model.');

        return new MediaAssetTableModel(...get_object_vars($domainModel));
    }
}
