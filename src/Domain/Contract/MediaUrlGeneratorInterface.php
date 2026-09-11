<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Contract;

interface MediaUrlGeneratorInterface
{
    public function url(string $assetId, ?string $variantKey = null): string;

    /**
     * The URL prefix every object of the CURRENT tenant sits under, or `''`
     * when this storage publishes no public URL at all.
     *
     * The inverse question to {@see self::url()}: not "where does this asset
     * live" but "is this address one we serve". It exists for callers holding
     * a URL that is already written into stored content and having to decide
     * whether to keep it — a CMS sanitizing article markup is the case that
     * asked for it, and an address it cannot recognise is an image it deletes.
     *
     * Scoped to the caller's tenant on purpose, and that is the whole reason
     * this is a prefix rather than a hostname: the tenant id is a path segment
     * (`<base>/media/<tenant>/…`), so one tenant's content cannot carry an
     * address inside another tenant's objects and be told it is ours.
     *
     * Ends with `/` so a prefix test cannot match a sibling whose id merely
     * starts the same way. Says nothing about whether any particular object
     * exists — only about the shape of addresses we would answer for.
     */
    public function publicUrlPrefix(): string;
}
