<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

/**
 * How a tenant id or a collection key is spelled inside a storage path.
 *
 * Both path builders kept a private copy of this rule, which was harmless
 * while nothing ever read a path back. It stopped being harmless when
 * {@see MediaUrlGenerator::publicUrlPrefix()} began ANSWERING with the prefix
 * a tenant's objects sit under: a third copy that drifted would not produce a
 * wrong filename anybody would notice, it would produce a prefix that quietly
 * stops matching the objects it is meant to admit — and the symptom of that,
 * one layer up, is pictures disappearing from articles rather than an error.
 *
 * Not a service: it is a spelling rule with no collaborators, and the builders
 * that use it are constructed with `new` on the ingest path.
 */
final class MediaPathSegment
{
    /**
     * Everything outside `[A-Za-z0-9-_]` becomes `_`.
     *
     * An empty value stays empty — an installation without tenancy records `''`
     * and its objects really do live under `media//…`, so the prefix has to
     * agree with that rather than tidy it away.
     */
    public static function of(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9\-_]/', '_', $value) ?? $value;
    }
}
