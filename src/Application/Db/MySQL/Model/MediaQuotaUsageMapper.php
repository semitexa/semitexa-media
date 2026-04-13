<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Model;

use Semitexa\Orm\Attribute\AsMapper;
use Semitexa\Orm\Contract\TableModelMapper;

#[AsMapper(resourceModel: MediaQuotaUsageTableModel::class, domainModel: MediaQuotaUsageResource::class)]
final class MediaQuotaUsageMapper implements TableModelMapper
{
    public function toDomain(object $tableModel): object
    {
        $tableModel instanceof MediaQuotaUsageTableModel || throw new \InvalidArgumentException('Unexpected table model.');

        $resource = new MediaQuotaUsageResource();
        foreach (get_object_vars($tableModel) as $property => $value) {
            $resource->{$property} = $value;
        }

        return $resource;
    }

    public function toTableModel(object $domainModel): object
    {
        $domainModel instanceof MediaQuotaUsageResource || throw new \InvalidArgumentException('Unexpected resource model.');

        return new MediaQuotaUsageTableModel(...get_object_vars($domainModel));
    }
}
