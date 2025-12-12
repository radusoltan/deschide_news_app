<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\VideoShowRepository;
use App\Service\YouTubeSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:youtube:sync',
    description: 'Sync videos from YouTube channel or playlist',
)]
class YouTubeSyncCommand extends Command
{
    public function __construct(
        private readonly YouTubeSyncService $youtubeSyncService,
        private readonly VideoShowRepository $videoShowRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::REQUIRED, 'YouTube channel URL/ID or playlist ID')
            ->addOption('playlist', 'p', InputOption::VALUE_NONE, 'Source is a playlist ID (not a channel)')
            ->addOption('show', 's', InputOption::VALUE_REQUIRED, 'Video show slug to associate videos with')
            ->addOption('max', 'm', InputOption::VALUE_REQUIRED, 'Maximum number of videos to sync', '50')
            ->addOption('stats-only', null, InputOption::VALUE_NONE, 'Only update statistics for existing videos')
            ->setHelp(<<<'HELP'
                The <info>%command.name%</info> command syncs videos from YouTube.

                Sync from a channel:
                    <info>%command.name% https://www.youtube.com/@deschide.moldova</info>
                    <info>%command.name% @deschide.moldova</info>
                    <info>%command.name% UCxxxxxxxxxxxxxxxxxxxxxxxx</info>

                Sync from a playlist:
                    <info>%command.name% PLxxxxxxxxxxxxxxxxxxxxxxxx -p</info>

                Associate with a video show:
                    <info>%command.name% @deschide.moldova -s deschide-live</info>

                Only update statistics:
                    <info>%command.name% --stats-only</info>
                HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Check if stats-only mode
        if ($input->getOption('stats-only')) {
            return $this->updateStats($io);
        }

        $source = $input->getArgument('source');
        $isPlaylist = $input->getOption('playlist');
        $showSlug = $input->getOption('show');
        $maxResults = (int) $input->getOption('max');

        // Get video show if specified
        $videoShow = null;
        if ($showSlug) {
            $videoShow = $this->videoShowRepository->findBySlug($showSlug);
            if (!$videoShow) {
                $io->error(sprintf('Video show with slug "%s" not found.', $showSlug));
                return Command::FAILURE;
            }
            $io->info(sprintf('Associating videos with show: %s', $videoShow->getName()));
        }

        if ($isPlaylist) {
            $io->info(sprintf('Syncing from playlist: %s', $source));
            $stats = $this->youtubeSyncService->syncFromPlaylist($source, $videoShow, $maxResults);
        } else {
            // Resolve channel ID from URL or handle
            $channelId = $this->youtubeSyncService->resolveChannelId($source);
            if (!$channelId) {
                $io->error(sprintf('Could not resolve channel ID from: %s', $source));
                return Command::FAILURE;
            }

            $io->info(sprintf('Syncing from channel: %s (resolved: %s)', $source, $channelId));
            $stats = $this->youtubeSyncService->syncFromChannel($channelId, $videoShow, $maxResults);
        }

        $io->success(sprintf(
            'Sync completed: %d new, %d updated, %d errors',
            $stats['new'],
            $stats['updated'],
            $stats['errors']
        ));

        return $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function updateStats(SymfonyStyle $io): int
    {
        $io->info('Updating video statistics...');

        $stats = $this->youtubeSyncService->updateVideoStats();

        $io->success(sprintf(
            'Stats update completed: %d updated, %d errors',
            $stats['updated'],
            $stats['errors']
        ));

        return $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
