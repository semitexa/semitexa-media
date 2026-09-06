<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Model;

/**
 * Where an asset's bytes are, once the variant-or-original choice has been made.
 *
 * `version` is the moment those bytes came into being — the variant's
 * generation time, or the asset's own — and is what cache-busts a URL that
 * otherwise never changes.
 *
 * `collectionKey` is always the asset's, never the variant's: a variant is a
 * rendition of its asset and belongs wherever the asset does. It travels with
 * the location so a route that serves one collection can refuse the rest
 * without a second lookup.
 */
final readonly class LocatedMediaObject
{
    public function __construct(
        public string $path,
        public string $mimeType,
        public ?\DateTimeImmutable $version,
        public string $collectionKey,
    ) {}
}
