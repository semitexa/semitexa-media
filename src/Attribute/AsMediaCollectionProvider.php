<?php

declare(strict_types=1);

namespace Semitexa\Media\Attribute;

use Attribute;

/**
 * Marks a class that contributes media collection definitions. Discovered
 * lazily by MediaCollectionRegistry in EVERY process type — server workers,
 * console commands, queue workers — so applications no longer need a server
 * lifecycle listener (which console processes never see) plus a hand-rolled
 * worker wrapper just to make `media:work` find their collections.
 *
 * The class must implement MediaCollectionProviderInterface and is resolved
 * through the container (property injection available).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AsMediaCollectionProvider
{
}
