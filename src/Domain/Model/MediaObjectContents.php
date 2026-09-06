<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Model;

/**
 * An asset's bytes, read out of storage.
 *
 * For deployments whose storage is not publicly addressable — the local driver
 * without `STORAGE_LOCAL_PUBLIC_URL`, which is what a fresh install runs — the
 * application has to serve the object itself, and it needs the content type
 * that was recorded at ingest rather than one guessed from the path.
 */
final readonly class MediaObjectContents
{
    public function __construct(
        public string $contents,
        public string $mimeType,
    ) {}
}
