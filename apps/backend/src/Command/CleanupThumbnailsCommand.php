<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Thumbnail;
use App\Entity\ThumbnailProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:cleanup-thumbnails',
    description: 'Cleanup orphaned, obsolete, and unused thumbnail files and database records'
)]
class CleanupThumbnailsCommand extends Command
{
    private readonly string $storageRoot;

    private readonly string $thumbnailsDir;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Filesystem $filesystem,
        ParameterBagInterface $params,
    ) {
        parent::__construct();
        $this->storageRoot = $params->get('image.storage.root');
        $this->thumbnailsDir = $params->get('image.storage.thumbnails_dir');
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted without actually deleting')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Type of cleanup: orphaned, broken, all', 'all');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $type = $input->getOption('type');

        $io->title('Cleanup Thumbnails');

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No files or records will be deleted');
        }

        $stats = [
            'orphaned_files' => 0,
            'broken_db_records' => 0,
            'freed_space' => 0,
        ];

        // Get active profile IDs
        $activeProfiles = $this->entityManager->getRepository(ThumbnailProfile::class)->findAll();
        $activeProfileIds = array_map(fn ($p) => $p->getId(), $activeProfiles);

        $io->section('Active Thumbnail Profiles');
        foreach ($activeProfiles as $profile) {
            $io->writeln(\sprintf(
                '  • %s (ID: %d, %dx%d)',
                $profile->getName(),
                $profile->getId(),
                $profile->getWidth(),
                $profile->getHeight()
            ));
        }
        $io->newLine();

        // STEP 1: Find orphaned thumbnail files (exist on disk but not in DB)
        if (\in_array($type, ['orphaned', 'all'], true)) {
            $io->section('Step 1: Finding Orphaned Thumbnail Files');

            $thumbnailsPath = $this->storageRoot . '/' . $this->thumbnailsDir;

            if (!is_dir($thumbnailsPath)) {
                $io->warning(\sprintf('Thumbnails directory does not exist: %s', $thumbnailsPath));
            } else {
                // Get all thumbnail files from disk
                $finder = new Finder();
                $finder->files()->in($thumbnailsPath)->name(['*.webp', '*.jpg', '*.jpeg', '*.png']);

                $diskFiles = [];
                foreach ($finder as $file) {
                    $diskFiles[] = $file->getFilename();
                }

                $io->writeln(\sprintf('Found %d thumbnail files on disk', \count($diskFiles)));

                // Get all thumbnail filenames from DB
                $dbThumbnails = $this->entityManager->getRepository(Thumbnail::class)->findAll();
                $dbFilenames = array_map(fn ($t) => $t->getFilename(), $dbThumbnails);

                $io->writeln(\sprintf('Found %d thumbnail records in database', \count($dbFilenames)));

                // Find orphaned files (on disk but not in DB)
                $orphanedFiles = array_diff($diskFiles, $dbFilenames);

                if (\count($orphanedFiles) > 0) {
                    $io->warning(\sprintf('Found %d orphaned thumbnail files', \count($orphanedFiles)));

                    $totalSize = 0;
                    foreach ($orphanedFiles as $filename) {
                        $filePath = $thumbnailsPath . '/' . $filename;
                        $fileSize = filesize($filePath);
                        $totalSize += $fileSize;

                        $io->writeln(
                            \sprintf('  [DELETE] %s (%.2f KB)', $filename, $fileSize / 1024),
                            OutputInterface::VERBOSITY_VERBOSE
                        );

                        if (!$dryRun) {
                            $this->filesystem->remove($filePath);
                        }
                    }

                    $stats['orphaned_files'] = \count($orphanedFiles);
                    $stats['freed_space'] += $totalSize;

                    $io->success(\sprintf(
                        '%s %d orphaned files (%.2f MB)',
                        $dryRun ? 'Would delete' : 'Deleted',
                        \count($orphanedFiles),
                        $totalSize / 1024 / 1024
                    ));
                } else {
                    $io->success('No orphaned thumbnail files found');
                }
            }
        }

        // STEP 2: Find broken thumbnail DB records (in DB but file missing or image missing)
        if (\in_array($type, ['broken', 'all'], true)) {
            $io->section('Step 2: Finding Broken Thumbnail Database Records');

            $thumbnailsPath = $this->storageRoot . '/' . $this->thumbnailsDir;
            $brokenRecords = [];

            $allThumbnails = $this->entityManager->getRepository(Thumbnail::class)->findAll();

            foreach ($allThumbnails as $thumbnail) {
                $broken = false;
                $reason = '';

                // Check 1: Image still exists
                if (!$thumbnail->getImage()) {
                    $broken = true;
                    $reason = 'Image deleted';
                }

                // Check 2: Profile still exists
                elseif (!$thumbnail->getProfile()) {
                    $broken = true;
                    $reason = 'Profile deleted';
                }

                // Check 3: Physical file exists
                else {
                    $filePath = $thumbnailsPath . '/' . $thumbnail->getFilename();
                    if (!file_exists($filePath)) {
                        $broken = true;
                        $reason = 'File missing';
                    }
                }

                if ($broken) {
                    $brokenRecords[] = ['thumbnail' => $thumbnail, 'reason' => $reason];

                    $io->writeln(
                        \sprintf(
                            '  [DELETE] Thumbnail ID %d: %s (%s)',
                            $thumbnail->getId(),
                            $thumbnail->getFilename(),
                            $reason
                        ),
                        OutputInterface::VERBOSITY_VERBOSE
                    );
                }
            }

            if (\count($brokenRecords) > 0) {
                $io->warning(\sprintf('Found %d broken thumbnail records', \count($brokenRecords)));

                if (!$dryRun) {
                    foreach ($brokenRecords as $record) {
                        $this->entityManager->remove($record['thumbnail']);
                    }
                    $this->entityManager->flush();
                }

                $stats['broken_db_records'] = \count($brokenRecords);

                $io->success(\sprintf(
                    '%s %d broken thumbnail records',
                    $dryRun ? 'Would delete' : 'Deleted',
                    \count($brokenRecords)
                ));
            } else {
                $io->success('No broken thumbnail records found');
            }
        }

        // STEP 3: Summary
        $io->section('Cleanup Summary');

        $io->table(
            ['Category', 'Count', 'Status'],
            [
                ['Orphaned Files', $stats['orphaned_files'], $stats['orphaned_files'] > 0 ? '✓ Cleaned' : '✓ Clean'],
                ['Broken DB Records', $stats['broken_db_records'], $stats['broken_db_records'] > 0 ? '✓ Cleaned' : '✓ Clean'],
                ['Freed Disk Space', \sprintf('%.2f MB', $stats['freed_space'] / 1024 / 1024), $stats['freed_space'] > 0 ? '✓ Freed' : '-'],
            ]
        );

        $totalCleaned = $stats['orphaned_files'] + $stats['broken_db_records'];

        if ($totalCleaned === 0) {
            $io->success('No cleanup needed - everything is clean! 🎉');
        } else {
            $message = $dryRun
                ? \sprintf('Would clean %d items (%.2f MB). Run without --dry-run to apply changes.', $totalCleaned, $stats['freed_space'] / 1024 / 1024)
                : \sprintf('Successfully cleaned %d items (%.2f MB freed)!', $totalCleaned, $stats['freed_space'] / 1024 / 1024);

            $io->success($message);
        }

        return Command::SUCCESS;
    }
}
