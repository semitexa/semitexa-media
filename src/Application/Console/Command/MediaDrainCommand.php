<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Console\Command;

use Semitexa\Core\Attribute\AsCommand;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Console\BaseCommand;
use Semitexa\Media\Application\Service\MediaWorker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'media:drain', description: 'Generate queued media variants directly from the database — no queue broker required')]
final class MediaDrainCommand extends BaseCommand
{
    #[InjectAsReadonly]
    protected MediaWorker $worker;

    protected function configure(): void
    {
        $this
            ->setName('media:drain')
            ->setDescription('Generate queued media variants directly from the database — no queue broker required')
            ->addOption(
                name:        'limit',
                shortcut:    'l',
                mode:        InputOption::VALUE_REQUIRED,
                description: 'Stop after attempting this many variants (default: drain until the backlog is empty)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $limit = $input->getOption('limit') !== null ? max(1, (int) $input->getOption('limit')) : null;

        $io->title('Media drain');

        $this->worker->setOutput($output);

        try {
            $result = $this->worker->drain($limit);
        } catch (\Throwable $e) {
            $io->error('Drain failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->definitionList(
            ['Generated' => $result['processed']],
            ['Failed' => $result['failed']],
        );

        if ($result['failed'] > 0) {
            $io->warning("Some variants failed — inspect with 'media:failed-variants' and retry via 'media:regenerate'.");
            return Command::FAILURE;
        }

        $io->success($result['processed'] > 0 ? "Generated {$result['processed']} variant(s)." : 'Backlog empty — nothing to drain.');

        return Command::SUCCESS;
    }
}
