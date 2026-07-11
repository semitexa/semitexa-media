<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Container\ContainerFactory;
use Semitexa\Core\Discovery\ClassDiscovery;
use Semitexa\Media\Attribute\AsMediaCollectionProvider;
use Semitexa\Media\Domain\Contract\MediaCollectionProviderInterface;
use Semitexa\Media\Domain\Model\MediaCollection;
use Semitexa\Media\Domain\Enum\MediaKind;
use Semitexa\Media\Domain\Enum\MediaVisibility;
use Semitexa\Media\Domain\Model\ImageTransformPreset;

/**
 * Holds code-defined collection definitions.
 *
 * Two registration channels:
 *  - imperative register() calls (e.g. from server lifecycle listeners);
 *  - lazy discovery of #[AsMediaCollectionProvider] classes on first read,
 *    which works in EVERY process type — console commands and queue workers
 *    never see server lifecycle events, and previously started with an
 *    empty registry (every variant failed with «collection not found»).
 *
 * Each definition is an array that matches the MediaCollection constructor arguments.
 */
#[AsService]
final class MediaCollectionRegistry
{
    #[InjectAsReadonly]
    protected ClassDiscovery $classDiscovery;

    /** @var array<string, array<string, mixed>> */
    private array $definitions = [];

    private bool $providersLoaded = false;

    /**
     * Register a collection definition.
     *
     * @param array<string, mixed> $definition Must contain: collectionKey, mediaKind, visibilityDefault,
     *                                         quotaBucket, allowedMimeTypes, transformPresets.
     *                                         Optional: maxOriginalBytes, maxWidth, maxHeight, maxAssetCount.
     */
    public function register(array $definition): void
    {
        $key = $definition['collectionKey'] ?? throw new \InvalidArgumentException('Collection definition must include collectionKey.');

        $this->definitions[$key] = $definition;
    }

    public function has(string $collectionKey): bool
    {
        $this->loadProviders();

        return isset($this->definitions[$collectionKey]);
    }

    public function get(string $collectionKey): ?MediaCollection
    {
        $this->loadProviders();

        if (!isset($this->definitions[$collectionKey])) {
            return null;
        }

        return $this->buildFromDefinition($this->definitions[$collectionKey]);
    }

    /** @return MediaCollection[] */
    public function all(): array
    {
        $this->loadProviders();

        $collections = [];
        foreach ($this->definitions as $definition) {
            $collections[] = $this->buildFromDefinition($definition);
        }

        return $collections;
    }

    private function loadProviders(): void
    {
        if ($this->providersLoaded || !isset($this->classDiscovery)) {
            return;
        }
        $this->providersLoaded = true;

        foreach ($this->classDiscovery->findClassesWithAttribute(AsMediaCollectionProvider::class) as $class) {
            // Providers are usually pure definition holders; #[AsService]
            // is only needed when the provider wants injected dependencies.
            try {
                $provider = ContainerFactory::get()->get($class);
            } catch (\Throwable) {
                $provider = new $class();
            }
            if (!$provider instanceof MediaCollectionProviderInterface) {
                continue;
            }
            foreach ($provider->collections() as $definition) {
                // Imperative register() wins over provider definitions.
                $key = $definition['collectionKey'] ?? null;
                if ($key !== null && !isset($this->definitions[$key])) {
                    $this->register($definition);
                }
            }
        }
    }

    private function buildFromDefinition(array $def): MediaCollection
    {
        $presets = [];
        foreach ($def['transformPresets'] ?? [] as $variantKey => $presetData) {
            $presets[] = ImageTransformPreset::fromArray(
                is_string($variantKey) ? $variantKey : $presetData['variantKey'],
                is_string($variantKey) ? $presetData : $presetData,
            );
        }

        return new MediaCollection(
            collectionKey:    $def['collectionKey'],
            mediaKind:        MediaKind::from($def['mediaKind'] ?? MediaKind::Image->value),
            visibilityDefault: MediaVisibility::from($def['visibilityDefault'] ?? MediaVisibility::Private->value),
            quotaBucket:      $def['quotaBucket'] ?? 'default',
            allowedMimeTypes: $def['allowedMimeTypes'] ?? [],
            transformPresets: $presets,
            maxOriginalBytes: $def['maxOriginalBytes'] ?? null,
            maxWidth:         $def['maxWidth'] ?? null,
            maxHeight:        $def['maxHeight'] ?? null,
            maxAssetCount:    $def['maxAssetCount'] ?? null,
            tenantId:         $def['tenantId'] ?? null,
        );
    }
}
