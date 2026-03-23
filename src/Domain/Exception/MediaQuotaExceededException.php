<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Exception;

final class MediaQuotaExceededException extends \RuntimeException
{
    public function __construct(string $tenantId, string $quotaBucket, string $reason)
    {
        parent::__construct("Quota exceeded for tenant '{$tenantId}' in bucket '{$quotaBucket}': {$reason}");
    }
}
