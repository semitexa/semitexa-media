<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Enum;

enum OutputFormat: string
{
    case Jpeg = 'jpeg';
    case Webp = 'webp';
    case Png  = 'png';

    public function toMimeType(): string
    {
        return match ($this) {
            self::Jpeg => 'image/jpeg',
            self::Webp => 'image/webp',
            self::Png  => 'image/png',
        };
    }

    public function toExtension(): string
    {
        return match ($this) {
            self::Jpeg => 'jpg',
            self::Webp => 'webp',
            self::Png  => 'png',
        };
    }
}
