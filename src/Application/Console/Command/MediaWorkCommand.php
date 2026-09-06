<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Console\Command;

use Semitexa\Core\Attribute\AsCommand;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Console\BaseCommand;
use Semitexa\Media\Application\Service\MediaWorker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'media:work', description: 'Run the dedicated media variant generation worker')]
final class MediaWorkCommand extends BaseCommand
{
    #[InjectAsReadonly]
    protected MediaWorker $worker;

    protected function configure(): void
    {
        $this
            ->setName('media:work')
            ->setDescription('Run the dedicated media variant generation worker')
            ->addArgument(
                name:        'transport',
                mode:        InputArgument::OPTIONAL,
                description: 'Queue transport (default from EVENTS_ASYNC)',
                default:     null,
            )
            ->addArgument(
                name:        'queue',
                mode:        InputArgument::OPTIONAL,
                description: 'Queue name (default: media)',
                default:     null,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io        = new SymfonyStyle($input, $output);
        $transport = $input->getArgument('transport');
        $queue     = $input->getArgument('queue');

        $io->title('Media worker');

        try {
            // MediaCollectionPolicyResolver, MediaTransformationService and
            // MediaWorker are #[AsService] attribute-DI services — they have
            // no explicit constructor, so PHPStan's new.noConstructor would
            // (and did) flag any `new X(...)` call against them. Resolve via
            // the container so each gets its dependencies through #[InjectAs*]
            // property injection. As a bonus, MediaCollectionPolicyResolver
            // now sees the boot-time-populated registry rather than the empty
            // freshly-`new`d one the previous code passed in.
            $this->worker->setOutput($output);
            $this->worker->run($transport, $queue);
        } catch (\Throwable $e) {
            $io->error('Media worker failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
