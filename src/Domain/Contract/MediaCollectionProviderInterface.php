<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Contract;

interface MediaCollectionProviderInterface
{
    /**
     * Collection definitions to register. Each entry matches
     * MediaCollectionRegistry::register() input: collectionKey, mediaKind,
     * visibilityDefault, quotaBucket, allowedMimeTypes, transformPresets,
     * and the optional limits.
     *
     * @return list<array<string, mixed>>
     */
    public function collections(): array;
}
