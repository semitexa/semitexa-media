<?php

declare(strict_types=1);

namespace Semitexa\Media\Console;

use Semitexa\Core\Attribute\AsCommand;
use Semitexa\Core\Container\ContainerFactory;
use Semitexa\Media\Contract\MediaAssetRepositoryInterface;
use Semitexa\Media\Contract\MediaServiceInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'media:regenerate', description: 'Queue variant regeneration for one asset or all assets in a collection')]
final class MediaRegenerateCommand extends Command
{
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
        $assetId    = $input->getArgument('asset-id');
        $variantKey = $input->getOption('variant');
        $collection = $input->getOption('collection');
        $tenantId   = $input->getOption('tenant');
        $batchSize  = (int) $input->getOption('batch');

        $io->title('Media regeneration');

        try {
            $container    = ContainerFactory::get();
            $mediaService = $container->get(MediaServiceInterface::class);

            if ($assetId !== null) {
                $mediaService->queueRegeneration($assetId, $variantKey);
                $io->success("Queued regeneration for asset '{$assetId}'" . ($variantKey ? " variant '{$variantKey}'" : ' (all variants)'));
                return Command::SUCCESS;
            }

            if ($collection !== null) {
                if ($tenantId === null) {
                    $io->error('--tenant is required when using --collection');
                    return Command::FAILURE;
                }

                $assetRepo = $container->get(MediaAssetRepositoryInterface::class);
                $assets    = $assetRepo->findByTenantAndCollection($tenantId, $collection, $batchSize);

                $queued = 0;
                foreach ($assets as $asset) {
                    $mediaService->queueRegeneration($asset->id, $variantKey);
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
