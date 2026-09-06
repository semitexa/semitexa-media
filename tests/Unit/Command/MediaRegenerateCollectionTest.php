<?php

declare(strict_types=1);

namespace Semitexa\Media\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Media\Application\Console\Command\MediaRegenerateCommand;

/**
 * The --collection branch reads an asset's id through its accessor.
 *
 * MediaAsset became a business model with private properties, and this branch
 * kept reaching for `$asset->id`. Nothing failed in CI because nothing ran it:
 * the collaborator was resolved from the container INSIDE the method, so the
 * branch only died when an operator actually passed --collection —
 * "Cannot access private property Semitexa\Media\Domain\Model\MediaAsset::$id",
 * MEASURED 2026-09-06 by running the command.
 *
 * Source assertions rather than execution: driving this command needs a
 * container, a database and a tenant, while the defect is a single symbol.
 */
final class MediaRegenerateCollectionTest extends TestCase
{
    #[Test]
    public function the_collection_branch_never_touches_a_private_property(): void
    {
        self::assertStringNotContainsString(
            '$asset->id',
            $this->source(),
            'MediaAsset::$id is private; the id must be read through getId()',
        );
        self::assertStringContainsString('$asset->getId()', $this->source());
    }

    /**
     * Resolving collaborators inside the method is what hid the crash — the
     * branch had to be reached before anything was wired. Injection moves that
     * to boot, where a missing binding is loud.
     */
    #[Test]
    public function the_command_takes_its_collaborators_by_injection(): void
    {
        $reflection = new \ReflectionClass(MediaRegenerateCommand::class);

        $injected = [];
        foreach ($reflection->getProperties() as $property) {
            if ($property->getAttributes(InjectAsReadonly::class) !== []) {
                $injected[] = $property->getName();
            }
        }

        sort($injected);

        self::assertSame(['assetRepository', 'mediaService'], $injected);
        self::assertStringNotContainsString('ContainerFactory', $this->source());
    }

    private function source(): string
    {
        return (string) file_get_contents(
            (string) (new \ReflectionClass(MediaRegenerateCommand::class))->getFileName(),
        );
    }
}
