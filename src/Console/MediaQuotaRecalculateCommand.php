<?php

declare(strict_types=1);

namespace Semitexa\Media\Console;

use Semitexa\Core\Attributes\AsCommand;
use Semitexa\Core\Container\ContainerFactory;
use Semitexa\Media\Contract\MediaQuotaManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'media:quota:recalculate', description: 'Recalculate tenant media quota usage from authoritative asset table')]
final class MediaQuotaRecalculateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('media:quota:recalculate')
            ->setDescription('Recalculate tenant media quota usage from authoritative asset table')
            ->addArgument(
                name:        'tenant-id',
                mode:        InputArgument::REQUIRED,
                description: 'Tenant ID to recalculate quota for',
            )
            ->addOption(
                name:        'bucket',
                shortcut:    'b',
                mode:        InputOption::VALUE_REQUIRED,
                description: 'Quota bucket name (default: default)',
                default:     'default',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $tenantId = $input->getArgument('tenant-id');
        $bucket   = $input->getOption('bucket');

        $io->title('Media quota recalculation');
        $io->comment("Recalculating quota for tenant '{$tenantId}' in bucket '{$bucket}'...");

        try {
            $container    = ContainerFactory::get();
            $quotaManager = $container->get(MediaQuotaManagerInterface::class);

            $quotaManager->recalculate($tenantId, $bucket);

            $io->success("Quota recalculated for tenant '{$tenantId}' in bucket '{$bucket}'.");
        } catch (\Throwable $e) {
            $io->error('Quota recalculation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
