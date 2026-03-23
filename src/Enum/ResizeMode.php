<?php

declare(strict_types=1);

namespace Semitexa\Media\Enum;

enum ResizeMode: string
{
    case Fit     = 'fit';
    case Cover   = 'cover';
    case Contain = 'contain';
}
