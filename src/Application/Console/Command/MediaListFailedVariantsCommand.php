<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Console\Command;

use Semitexa\Core\Attribute\AsCommand;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Console\BaseCommand;
use Semitexa\Media\Domain\Contract\MediaVariantRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'media:failed-variants', description: 'List failed media variant generation jobs')]
final class MediaListFailedVariantsCommand extends BaseCommand
{
    #[InjectAsReadonly]
    protected MediaVariantRepositoryInterface $repo;

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

            $variants = $assetId !== null
                ? $this->repo->findFailedByAssetId($assetId)
                : $this->repo->findFailed($limit);

            if ($variants === []) {
                $io->success('No failed variants found.');
                return Command::SUCCESS;
            }

            $rows = [];
            foreach ($variants as $variant) {
                $rows[] = [
                    $variant->getMediaAssetId(),
                    $variant->getVariantKey(),
                    $variant->getAttemptCount() . '/' . $variant->getMaxAttempts(),
                    $variant->getErrorCode() ?? '-',
                    $variant->getLastAttemptAt()?->format('Y-m-d H:i:s') ?? '-',
                    substr($variant->getErrorMessage() ?? '', 0, 80),
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
