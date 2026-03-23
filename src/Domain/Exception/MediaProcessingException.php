<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Exception;

final class MediaProcessingException extends \RuntimeException
{
    public function __construct(string $reason, ?\Throwable $previous = null)
    {
        parent::__construct("Media processing failed: {$reason}", 0, $previous);
    }
}
