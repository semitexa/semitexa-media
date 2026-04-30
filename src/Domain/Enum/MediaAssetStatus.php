<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Enum;

enum MediaAssetStatus: string
{
    case Pending = 'pending';
    case Ready   = 'ready';
    case Failed  = 'failed';
    case Deleted = 'deleted';
}
