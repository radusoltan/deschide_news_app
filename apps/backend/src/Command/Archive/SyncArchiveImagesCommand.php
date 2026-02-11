<?php

declare(strict_types=1);

namespace App\Command\Archive;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:archive:sync-images',
    description: 'Sync archive images from external HDD to server using rsync',
)]
class SyncArchiveImagesCommand extends Command
{
    private const SOURCE_BASE = '/mnt/d/ext-hdd';
    private const TARGET_BASE = '/var/www/deschide_news_app/apps/backend/public/uploads/images';
    private const ALPHA_SOURCE = self::SOURCE_BASE . '/alpha/';
    private const BETA_SOURCE = self::SOURCE_BASE . '/beta/images/';
    private const ALPHA_TARGET = self::TARGET_BASE . '/alpha/';
    private const BETA_TARGET = self::TARGET_BASE . '/beta/';

    protected function configure(): void
    {
        $this
            ->addOption(
                'source',
                's',
                InputOption::VALUE_REQUIRED,
                'Which source to sync (alpha, beta, or all)',
                'all'
            )
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Preview changes without copying files'
            )
            ->addOption(
                'verify',
                null,
                InputOption::VALUE_NONE,
                'Verify file integrity after copy using checksums'
            )
            ->addOption(
                'verbose-rsync',
                null,
                InputOption::VALUE_NONE,
                'Show detailed rsync progress'
            )
            ->addOption(
                'stats',
                null,
                InputOption::VALUE_NONE,
                'Show detailed rsync statistics'
            )
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command syncs archive images from external HDD to the server.

<comment>Usage:</comment>

  # Preview alpha sync without copying
  <info>php %command.full_name% --source=alpha --dry-run</info>

  # Sync beta images
  <info>php %command.full_name% --source=beta</info>

  # Sync all images with verification
  <info>php %command.full_name% --source=all --verify</info>

  # Sync with detailed progress
  <info>php %command.full_name% --source=alpha --verbose-rsync --stats</info>

<comment>Source locations:</comment>
  - Alpha (Newscoop): /mnt/d/ext-hdd/alpha/ → public/uploads/images/alpha/
  - Beta: /mnt/d/ext-hdd/beta/images/ → public/uploads/images/beta/

<comment>Note:</comment> Files are COPIED, not moved. Source files remain intact on the HDD.
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sourceType = $input->getOption('source');
        $dryRun = $input->getOption('dry-run');
        $verify = $input->getOption('verify');
        $verboseRsync = $input->getOption('verbose-rsync');
        $showStats = $input->getOption('stats');

        // Validate source type
        if (!\in_array($sourceType, ['alpha', 'beta', 'all'], true)) {
            $io->error('Invalid source type. Must be: alpha, beta, or all');
            return Command::FAILURE;
        }

        // Check if rsync is installed
        if (!$this->isRsyncInstalled()) {
            $io->error('rsync is not installed. Install with: sudo apt-get install rsync');
            return Command::FAILURE;
        }

        // Display configuration
        $io->title('Archive Images Sync');
        $io->table(
            ['Configuration', 'Value'],
            [
                ['Source', $sourceType],
                ['Dry Run', $dryRun ? 'YES' : 'NO'],
                ['Verify', $verify ? 'YES' : 'NO'],
                ['Verbose', $verboseRsync ? 'YES' : 'NO'],
                ['Stats', $showStats ? 'YES' : 'NO'],
            ]
        );

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No files will be copied');
        }

        $successCount = 0;
        $totalSyncs = 0;

        // Sync Alpha
        if (\in_array($sourceType, ['alpha', 'all'], true)) {
            ++$totalSyncs;
            if ($this->syncImages($io, 'Alpha', self::ALPHA_SOURCE, self::ALPHA_TARGET, $dryRun, $verify, $verboseRsync, $showStats)) {
                ++$successCount;
            }
        }

        // Sync Beta
        if (\in_array($sourceType, ['beta', 'all'], true)) {
            ++$totalSyncs;
            if ($this->syncImages($io, 'Beta', self::BETA_SOURCE, self::BETA_TARGET, $dryRun, $verify, $verboseRsync, $showStats)) {
                ++$successCount;
            }
        }

        // Final summary
        $io->newLine();
        $io->section('Sync Summary');

        if ($successCount === $totalSyncs) {
            $io->success(\sprintf('All %d sync(s) completed successfully', $totalSyncs));
            return Command::SUCCESS;
        }

        $io->error(\sprintf('%d of %d sync(s) failed', $totalSyncs - $successCount, $totalSyncs));
        return Command::FAILURE;
    }

    private function syncImages(
        SymfonyStyle $io,
        string $label,
        string $source,
        string $target,
        bool $dryRun,
        bool $verify,
        bool $verboseRsync,
        bool $showStats
    ): bool {
        $io->section(\sprintf('Syncing %s Images', $label));

        // Check source directory
        if (!is_dir($source)) {
            $io->error(\sprintf('%s source directory does not exist: %s', $label, $source));
            return false;
        }
        $io->text(\sprintf('✓ Source exists: <info>%s</info>', $source));

        // Create target directory if needed
        if (!is_dir($target)) {
            $io->text(\sprintf('Creating target directory: <comment>%s</comment>', $target));
            if (!mkdir($target, 0755, true) && !is_dir($target)) {
                $io->error(\sprintf('Failed to create target directory: %s', $target));
                return false;
            }
        } else {
            $io->text(\sprintf('✓ Target exists: <info>%s</info>', $target));
        }

        // Analyze source
        $io->newLine();
        $io->text('Analyzing source files...');
        $sourceStats = $this->getDirectoryStats($source);
        $io->table(
            ['Metric', 'Value'],
            [
                ['Files', number_format($sourceStats['files'])],
                ['Size', $sourceStats['size']],
            ]
        );

        // Build rsync command
        $rsyncOptions = ['-av'];

        if ($dryRun) {
            $rsyncOptions[] = '--dry-run';
        }

        if ($verify) {
            $rsyncOptions[] = '--checksum';
            $io->text('Using checksums for verification (slower but safer)');
        }

        if ($verboseRsync) {
            $rsyncOptions[] = '--progress';
        }

        if ($showStats) {
            $rsyncOptions[] = '--stats';
        }

        $command = array_merge(
            ['rsync'],
            $rsyncOptions,
            [$source, $target]
        );

        $io->newLine();
        $io->text(\sprintf('Executing: <comment>%s</comment>', implode(' ', $command)));
        $io->newLine();

        // Execute rsync
        $process = new Process($command);
        $process->setTimeout(null); // No timeout for large transfers

        try {
            $process->run(function ($type, $buffer) use ($io, $verboseRsync): void {
                if ($verboseRsync) {
                    $io->write($buffer);
                }
            });

            if (!$process->isSuccessful()) {
                $io->error(\sprintf('%s sync failed', $label));
                $io->text($process->getErrorOutput());
                return false;
            }

            $io->success(\sprintf('%s sync completed successfully', $label));

            // Show statistics if requested
            if ($showStats) {
                $io->text($process->getOutput());
            }

            // Count target files (only if not dry-run)
            if (!$dryRun) {
                $io->newLine();
                $io->text('Analyzing target files...');
                $targetStats = $this->getDirectoryStats($target);
                $io->table(
                    ['Metric', 'Value'],
                    [
                        ['Files', number_format($targetStats['files'])],
                        ['Size', $targetStats['size']],
                    ]
                );
            }

            return true;
        } catch (\Exception $e) {
            $io->error(\sprintf('Exception during %s sync: %s', $label, $e->getMessage()));
            return false;
        }
    }

    private function getDirectoryStats(string $directory): array
    {
        // Get directory size first (faster)
        $duProcess = new Process(['du', '-sh', $directory]);
        $duProcess->setTimeout(300); // 5 minutes timeout
        $duProcess->run();
        $size = trim(explode("\t", $duProcess->getOutput())[0] ?? 'Unknown');

        // Count files using a faster approach with find -type f | wc -l
        $countProcess = new Process(['bash', '-c', sprintf("find '%s' -type f | wc -l", $directory)]);
        $countProcess->setTimeout(600); // 10 minutes timeout for large directories
        $countProcess->run();

        $fileCount = (int) trim($countProcess->getOutput());

        return [
            'files' => $fileCount,
            'size' => $size,
        ];
    }

    private function isRsyncInstalled(): bool
    {
        $process = new Process(['which', 'rsync']);
        $process->run();

        return $process->isSuccessful();
    }
}
