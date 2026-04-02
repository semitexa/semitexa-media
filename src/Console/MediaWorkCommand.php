<?php

declare(strict_types=1);

namespace Semitexa\Media\Console;

use Semitexa\Core\Attributes\AsCommand;
use Semitexa\Core\Container\ContainerFactory;
use Semitexa\Media\Configuration\MediaConfig;
use Semitexa\Media\Contract\MediaAssetRepositoryInterface;
use Semitexa\Media\Contract\MediaCollectionRepositoryInterface;
use Semitexa\Media\Contract\MediaVariantRepositoryInterface;
use Semitexa\Media\Image\ImagickImageProcessor;
use Semitexa\Media\Service\MediaCollectionPolicyResolver;
use Semitexa\Media\Service\MediaCollectionRegistry;
use Semitexa\Media\Service\MediaTransformationService;
use Semitexa\Media\Service\MediaWorker;
use Semitexa\Media\Service\VariantStoragePathBuilder;
use Semitexa\Storage\Contract\StorageObjectStoreInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'media:work', description: 'Run the dedicated media variant generation worker')]
final class MediaWorkCommand extends Command
{
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
            $container = ContainerFactory::get();

            $config              = $container->get(MediaConfig::class);
            $assetRepository     = $container->get(MediaAssetRepositoryInterface::class);
            $variantRepository   = $container->get(MediaVariantRepositoryInterface::class);
            $collectionRepository = $container->get(MediaCollectionRepositoryInterface::class);
            $storage             = $container->get(StorageObjectStoreInterface::class);

            $collectionRegistry  = new MediaCollectionRegistry();
            $collectionResolver  = new MediaCollectionPolicyResolver($collectionRegistry, $collectionRepository);

            $imageProcessor       = new ImagickImageProcessor();
            $pathBuilder          = new VariantStoragePathBuilder();
            $transformationService = new MediaTransformationService($imageProcessor, $pathBuilder, $storage);

            $worker = new MediaWorker(
                config:               $config,
                assetRepository:      $assetRepository,
                variantRepository:    $variantRepository,
                collectionResolver:   $collectionResolver,
                transformationService: $transformationService,
            );
            $worker->setOutput($output);
            $worker->run($transport, $queue);
        } catch (\Throwable $e) {
            $io->error('Media worker failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
