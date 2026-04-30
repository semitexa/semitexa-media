<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Enum;

enum ResizeMode: string
{
    case Fit     = 'fit';
    case Cover   = 'cover';
    case Contain = 'contain';
}
