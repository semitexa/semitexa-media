<?php

declare(strict_types=1);

namespace Semitexa\Media\Value;

final readonly class VariantGenerationResult
{
    public function __construct(
        public bool $success,
        public ?string $storagePath = null,
        public ?string $mimeType = null,
        public ?int $byteSize = null,
        public ?int $actualWidth = null,
        public ?int $actualHeight = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {}

    public static function success(
        string $storagePath,
        string $mimeType,
        int $byteSize,
        int $actualWidth,
        int $actualHeight,
    ): self {
        return new self(
            success:      true,
            storagePath:  $storagePath,
            mimeType:     $mimeType,
            byteSize:     $byteSize,
            actualWidth:  $actualWidth,
            actualHeight: $actualHeight,
        );
    }

    public static function failure(string $errorCode, string $errorMessage): self
    {
        return new self(
            success:      false,
            errorCode:    $errorCode,
            errorMessage: $errorMessage,
        );
    }
}
