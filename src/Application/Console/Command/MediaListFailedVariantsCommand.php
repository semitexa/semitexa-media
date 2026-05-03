<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Console\Command;

use Semitexa\Core\Attribute\AsCommand;
use Semitexa\Core\Container\ContainerFactory;
use Semitexa\Media\Domain\Contract\MediaVariantRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'media:failed-variants', description: 'List failed media variant generation jobs')]
final class MediaListFailedVariantsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('media:failed-variants')
            ->setDescription('List failed media variant generation jobs')
            ->addOption(
                name:        'limit',
                shortcut:    'l',
                mode:        InputOption::VALUE_REQUIRED,
                description: 'Maximum number of failures to list',
                default:     '50',
            )
            ->addOption(
                name:        'asset',
                shortcut:    'a',
                mode:        InputOption::VALUE_REQUIRED,
                description: 'Filter by asset ID',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $limit   = (int) $input->getOption('limit');
        $assetId = $input->getOption('asset');

        try {
            $container = ContainerFactory::get();
            $repo      = $container->get(MediaVariantRepositoryInterface::class);

            $variants = $assetId !== null
                ? $repo->findFailedByAssetId($assetId)
                : $repo->findFailed($limit);

            if ($variants === []) {
                $io->success('No failed variants found.');
                return Command::SUCCESS;
            }

            $rows = [];
            foreach ($variants as $variant) {
                $rows[] = [
                    $variant->media_asset_id,
                    $variant->variant_key,
                    $variant->attempt_count . '/' . $variant->max_attempts,
                    $variant->error_code ?? '-',
                    $variant->last_attempt_at?->format('Y-m-d H:i:s') ?? '-',
                    substr($variant->error_message ?? '', 0, 80),
                ];
            }

            $io->table(
                headers: ['Asset ID', 'Variant Key', 'Attempts', 'Error Code', 'Last Attempt', 'Error Message'],
                rows:    $rows,
            );

            $io->comment(count($variants) . ' failed variant(s) listed.');
        } catch (\Throwable $e) {
            $io->error('Failed to list variants: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
