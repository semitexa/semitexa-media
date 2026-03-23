<?php

declare(strict_types=1);

namespace Semitexa\Media\Enum;

enum MediaVisibility: string
{
    case Private = 'private';
    case Public  = 'public';
}
