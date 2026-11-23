<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Console\Command;

use Semitexa\Core\Attribute\AsCommand;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Console\BaseCommand;
use Semitexa\Media\Application\Service\MediaVariantRegenerator;
use Semitexa\Media\Domain\Contract\MediaAssetRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'media:regenerate', description: 'Queue variant regeneration for one asset or all assets in a collection')]
final class MediaRegenerateCommand extends BaseCommand
{
    /**
     * The regenerator itself, not MediaServiceInterface::queueRegeneration():
     * that one answers for the caller's tenant, and a console has none, so it
     * would refuse every tenant-owned asset. The operator acts as system, and
     * says so by loading the asset here and handing over the row.
     */
    #[InjectAsReadonly]
    protected MediaVariantRegenerator $regenerator;

    #[InjectAsReadonly]
    protected MediaAssetRepositoryInterface $assetRepository;

    protected function configure(): void
    {
        $this
            ->setName('media:regenerate')
            ->setDescription('Queue variant regeneration for one asset or all assets in a collection')
            ->addArgument(
                name:        'asset-id',
                mode:        InputArgument::OPTIONAL,
                description: 'Asset ID to regenerate variants for',
            )
            ->addOption(
                name:        'variant',
                shortcut:    'k',
                mode:        InputOption::VALUE_REQUIRED,
                description: 'Specific variant key to regenerate (default: all variants)',
            )
            ->addOption(
                name:        'collection',
                shortcut:    'c',
                mode:        InputOption::VALUE_REQUIRED,
                description: 'Regenerate all assets in this collection',
            )
            ->addOption(
                name:        'tenant',
                mode:        InputOption::VALUE_REQUIRED,
                description: 'Tenant ID scope (required when using --collection)',
            )
            ->addOption(
                name:        'batch',
                shortcut:    'b',
                mode:        InputOption::VALUE_REQUIRED,
                description: 'Batch size for collection-level regeneration',
                default:     '50',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io         = new SymfonyStyle($input, $output);
        $assetId    = self::optionText($input->getArgument('asset-id'));
        $variantKey = self::optionText($input->getOption('variant'));
        $variantKey = $variantKey !== '' ? $variantKey : null;
        $collection = self::optionText($input->getOption('collection'));
        $tenantId   = $input->getOption('tenant');
        $batchSize  = (int) self::optionText($input->getOption('batch'));

        $io->title('Media regeneration');

        try {

            if ($assetId !== '') {
                $asset = $this->assetRepository->findById($assetId);

                if ($asset === null) {
                    $io->error("Asset '{$assetId}' not found.");
                    return Command::FAILURE;
                }

                $queued = $this->regenerator->requeue($asset, $variantKey);
                $io->success("Queued regeneration of {$queued} variant(s) for asset '{$assetId}'" . ($variantKey !== null ? " variant '{$variantKey}'" : ' (all variants)'));
                return Command::SUCCESS;
            }

            if ($collection !== '') {
                if (!is_string($tenantId)) {
                    $io->error('--tenant is required when using --collection');
                    return Command::FAILURE;
                }

                $assets = $this->assetRepository->findByTenantAndCollection($tenantId, $collection, $batchSize);

                $queued = 0;
                foreach ($assets as $asset) {
                    $this->regenerator->requeue($asset, $variantKey);
                    $queued++;
                }

                $io->success("Queued regeneration for {$queued} asset(s) in collection '{$collection}'.");
                return Command::SUCCESS;
            }

            $io->error('Provide either an asset-id or --collection option.');
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $io->error('Regeneration failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
