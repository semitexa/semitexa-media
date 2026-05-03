<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Repository;

abstract class AbstractMediaRepository
{
    abstract protected function getResourceClass(): string;

    protected function assertResourceType(object $resource): object
    {
        $expectedClass = $this->getResourceClass();

        if (!$resource instanceof $expectedClass) {
            throw new \InvalidArgumentException(sprintf(
                'Expected %s, got %s.',
                $expectedClass,
                $resource::class,
            ));
        }

        return $resource;
    }
}
