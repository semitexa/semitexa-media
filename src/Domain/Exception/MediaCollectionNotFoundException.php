<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Exception;

final class MediaCollectionNotFoundException extends \RuntimeException
{
    public function __construct(string $collectionKey, ?string $tenantId = null)
    {
        $scope = $tenantId !== null ? " for tenant '{$tenantId}'" : '';
        parent::__construct("Media collection '{$collectionKey}' not found{$scope}.");
    }
}
