<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Contract;

interface MediaUrlGeneratorInterface
{
    public function url(string $assetId, ?string $variantKey = null): string;
}
