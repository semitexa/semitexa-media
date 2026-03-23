<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Exception;

final class MediaIngestException extends \RuntimeException
{
    public function __construct(string $reason, ?\Throwable $previous = null)
    {
        parent::__construct("Media ingest failed: {$reason}", 0, $previous);
    }
}
