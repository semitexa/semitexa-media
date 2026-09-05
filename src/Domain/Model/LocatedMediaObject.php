<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Model;

/**
 * Where an asset's bytes are, once the variant-or-original choice has been made.
 *
 * `version` is the moment those bytes came into being — the variant's
 * generation time, or the asset's own — and is what cache-busts a URL that
 * otherwise never changes.
 */
final readonly class LocatedMediaObject
{
    public function __construct(
        public string $path,
        public string $mimeType,
        public ?\DateTimeImmutable $version,
    ) {}
}
