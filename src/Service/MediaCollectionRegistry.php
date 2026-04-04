<?php

declare(strict_types=1);

namespace Semitexa\Media\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Media\Domain\Model\MediaCollection;
use Semitexa\Media\Enum\MediaKind;
use Semitexa\Media\Enum\MediaVisibility;
use Semitexa\Media\Value\ImageTransformPreset;

/**
 * Holds code-defined collection definitions.
 *
 * Collections are registered at boot time by application modules.
 * Each definition is an array that matches the MediaCollection constructor arguments.
 */
#[AsService]
final class MediaCollectionRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $definitions = [];

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
        return isset($this->definitions[$collectionKey]);
    }

    public function get(string $collectionKey): ?MediaCollection
    {
        if (!isset($this->definitions[$collectionKey])) {
            return null;
        }

        return $this->buildFromDefinition($this->definitions[$collectionKey]);
    }

    /** @return MediaCollection[] */
    public function all(): array
    {
        $collections = [];
        foreach ($this->definitions as $definition) {
            $collections[] = $this->buildFromDefinition($definition);
        }

        return $collections;
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
