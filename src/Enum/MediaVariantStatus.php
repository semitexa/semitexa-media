<?php

declare(strict_types=1);

namespace Semitexa\Media\Enum;

enum MediaVariantStatus: string
{
    case Queued     = 'queued';
    case Processing = 'processing';
    case Ready      = 'ready';
    case Failed     = 'failed';
}
