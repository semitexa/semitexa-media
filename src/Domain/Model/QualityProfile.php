<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Model;

final readonly class QualityProfile
{
    public function __construct(
        public int $jpeg,
        public int $webp,
    ) {}

    public static function high(): self
    {
        return new self(jpeg: 92, webp: 90);
    }

    public static function balanced(): self
    {
        return new self(jpeg: 85, webp: 82);
    }

    public static function small(): self
    {
        return new self(jpeg: 72, webp: 68);
    }
}
