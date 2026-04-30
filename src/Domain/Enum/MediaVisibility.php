<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Enum;

enum MediaVisibility: string
{
    case Private = 'private';
    case Public  = 'public';
}
