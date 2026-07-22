<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Console\Command;

use Semitexa\Core\Attribute\AsCommand;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Console\BaseCommand;
use Semitexa\Media\Application\Service\MediaCollectionPolicyResolver;
use Semitexa\Media\Application\Service\MediaIngestService;
use Semitexa\Media\Domain\Contract\MediaAssetRepositoryInterface;
use Semitexa\Media\Domain\Model\MediaCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'media:import', description: 'Bulk-backfill a directory of existing files into media assets with queued variants')]
final class MediaImportCommand extends BaseCommand
{
    #[InjectAsReadonly]
    protected MediaIngestService $ingest;

    #[InjectAsReadonly]
    protected MediaCollectionPolicyResolver $collectionResolver;

    #[InjectAsReadonly]
    protected MediaAssetRepositoryInterface $assetRepository;

    protected function configure(): void
    {
        $this
            ->setName('media:import')
            ->setDescription('Bulk-backfill a directory of existing files into media assets with queued variants')
            ->addArgument(
                name:        'source',
                mode:        InputArgument::REQUIRED,
                description: 'Directory containing the files to ingest',
            )
            ->addOption(
                name:        'collection',
                shortcut:    'c',
                mode:        InputOption::VALUE_REQUIRED,
                description: 'Target collection key',
            )
            ->addOption(
                name:        'tenant',
                mode:        InputOption::VALUE_REQUIRED,
                description: 'Tenant ID the assets belong to',
            )
            ->addOption(
                name:        'created-by',
                mode:        InputOption::VALUE_REQUIRED,
                description: 'Attribution recorded on each imported asset',
                default:     'media:import',
            )
            ->addOption(
                name:        'ext',
                mode:        InputOption::VALUE_REQUIRED,
                description: 'Comma-separated extension filter, e.g. jpg,jpeg,png,webp (default: all files, non-allowed MIME types skipped)',
            )
            ->addOption(
                name:        'no-recursive',
                mode:        InputOption::VALUE_NONE,
                description: 'Only import files directly in the source directory',
            )
            ->addOption(
                name:        'limit',
                mode:        InputOption::VALUE_REQUIRED,
                description: 'Stop after ingesting this many files (dedup makes re-runs resume safely)',
            )
            ->addOption(
                name:        'force-duplicates',
                mode:        InputOption::VALUE_NONE,
                description: 'Ingest files even when an asset with the same content hash already exists for the tenant',
            )
            ->addOption(
                name:        'dry-run',
                mode:        InputOption::VALUE_NONE,
                description: 'Report what would be ingested without writing anything',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io            = new SymfonyStyle($input, $output);
        $source        = (string) $input->getArgument('source');
        $collectionKey = $input->getOption('collection') !== null ? (string) $input->getOption('collection') : null;
        $tenantId      = $input->getOption('tenant') !== null ? (string) $input->getOption('tenant') : null;
        $createdBy     = (string) $input->getOption('created-by');
        $extFilter     = $this->parseExtensions($input->getOption('ext'));
        $recursive     = !$input->getOption('no-recursive');
        $limit         = $input->getOption('limit') !== null ? max(1, (int) $input->getOption('limit')) : null;
        $dedup         = !$input->getOption('force-duplicates');
        $dryRun        = (bool) $input->getOption('dry-run');

        $io->title('Media bulk import' . ($dryRun ? ' (dry run)' : ''));

        if ($collectionKey === null) {
            $io->error('--collection is required.');
            return Command::FAILURE;
        }

        if ($tenantId === null) {
            $io->error('--tenant is required (pass --tenant="" for single-tenant installs).');
            return Command::FAILURE;
        }

        if (!is_dir($source) || !is_readable($source)) {
            $io->error("Source '{$source}' is not a readable directory.");
            return Command::FAILURE;
        }

        try {
            $collection = $this->collectionResolver->resolve($collectionKey, $tenantId);
        } catch (\Throwable $e) {
            $io->error('Import setup failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $files = $this->enumerateFiles($source, $recursive, $extFilter);
        if ($files === []) {
            $io->warning('No matching files found — nothing to import.');
            return Command::SUCCESS;
        }

        $io->text(sprintf(
            "Scanning %d file(s) from '%s' into collection '%s' (tenant '%s').",
            count($files),
            $source,
            $collectionKey,
            $tenantId,
        ));

        $finfo       = new \finfo(FILEINFO_MIME_TYPE);
        $ingested    = 0;
        $duplicates  = 0;
        $unsupported = 0;
        $failed      = [];

        $io->progressStart(count($files));

        foreach ($files as $path) {
            $io->progressAdvance();

            $mimeType = $finfo->file($path);
            if ($mimeType === false || !$this->isIngestable($mimeType, $collection)) {
                $unsupported++;
                continue;
            }

            $sha256 = hash_file('sha256', $path);
            if ($sha256 === false) {
                $failed[$path] = 'Could not read file for hashing.';
                continue;
            }

            if ($dedup && $this->assetRepository->findByTenantAndSha256($tenantId, $sha256) !== null) {
                $duplicates++;
                continue;
            }

            if ($dryRun) {
                $ingested++;
            } else {
                try {
                    $contents = file_get_contents($path);
                    if ($contents === false) {
                        throw new \RuntimeException('Could not read file contents.');
                    }

                    $this->ingest->ingestUploadedImage(
                        contents:      $contents,
                        originalName:  basename($path),
                        mimeType:      $mimeType,
                        collectionKey: $collectionKey,
                        tenantId:      $tenantId,
                        createdBy:     $createdBy,
                    );
                    $ingested++;
                } catch (\Throwable $e) {
                    $failed[$path] = $e->getMessage();
                    continue;
                }
            }

            if ($limit !== null && $ingested >= $limit) {
                break;
            }
        }

        $io->progressFinish();

        $verb = $dryRun ? 'Would ingest' : 'Ingested';
        $io->definitionList(
            [$verb => $ingested],
            ['Duplicates skipped' => $duplicates],
            ['Unsupported skipped' => $unsupported],
            ['Failed' => count($failed)],
        );

        if ($failed !== []) {
            foreach ($failed as $path => $reason) {
                $io->text("<error>FAIL</error> {$path} — {$reason}");
            }
            $io->warning('Some files failed; dedup makes it safe to re-run after fixing the cause.');
            return Command::FAILURE;
        }

        if (!$dryRun && $ingested > 0) {
            $io->success("Ingested {$ingested} asset(s); variants are queued — run 'media:work' to generate them.");
        } else {
            $io->success('Done.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param string[] $extFilter lowercase extensions without dots; empty = no filter
     * @return list<string> sorted absolute paths
     */
    private function enumerateFiles(string $source, bool $recursive, array $extFilter): array
    {
        $iterator = $recursive
            ? new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            )
            : new \FilesystemIterator($source, \FilesystemIterator::SKIP_DOTS);

        $files = [];
        /** @var \SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if (!$entry->isFile()) {
                continue;
            }
            if ($extFilter !== [] && !in_array(strtolower($entry->getExtension()), $extFilter, true)) {
                continue;
            }
            $files[] = $entry->getPathname();
        }

        sort($files);

        return $files;
    }

    /**
     * @return string[]
     */
    private function parseExtensions(?string $ext): array
    {
        if ($ext === null || trim($ext) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $e): string => strtolower(ltrim(trim($e), '.')),
            explode(',', $ext),
        ), static fn (string $e): bool => $e !== ''));
    }

    private function isIngestable(string $mimeType, MediaCollection $collection): bool
    {
        // An open collection (no explicit MIME allowlist) still only takes
        // what the imagick pipeline can inspect — images.
        if ($collection->allowedMimeTypes === []) {
            return str_starts_with($mimeType, 'image/');
        }

        return $collection->isMimeTypeAllowed($mimeType);
    }
}
