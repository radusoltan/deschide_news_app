<?php

declare(strict_types=1);

namespace App\Command\Topic;

use App\Repository\TopicRepository;
use App\Service\Topic\TopicNotebookSyncService;
use App\ValueObject\DateRange;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:topic:sync-notebooks',
    description: 'Sync topic sources (articles + press releases) to NotebookLM notebooks',
)]
final class TopicNotebookSyncCommand extends Command
{
    public function __construct(
        private readonly TopicNotebookSyncService $syncService,
        private readonly TopicRepository $topicRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be synced without making changes')
            ->addOption('topic', null, InputOption::VALUE_REQUIRED, 'Sync only this topic (by slug)')
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Look back period (e.g. 30d, 7d)', '30d')
            ->addOption('max-sources', null, InputOption::VALUE_REQUIRED, 'Maximum sources per topic notebook', '300')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force sync even if recently synced');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $topicSlug = $input->getOption('topic');
        $sinceStr = $input->getOption('since');
        $maxSources = (int) $input->getOption('max-sources');

        $range = $this->parseDateRange($sinceStr);

        if ($dryRun) {
            $io->note('DRY RUN — no changes will be made');
        }

        $io->title('NotebookLM Topic Sync');
        $io->text(sprintf('Period: %s', $range->format()));
        $io->text(sprintf('Max sources per topic: %d', $maxSources));

        if ($topicSlug !== null) {
            return $this->syncSingleTopic($io, $topicSlug, $range, $maxSources, $dryRun);
        }

        return $this->syncAllTopics($io, $range, $maxSources, $dryRun);
    }

    private function syncSingleTopic(
        SymfonyStyle $io,
        string $slug,
        DateRange $range,
        int $maxSources,
        bool $dryRun,
    ): int {
        $topic = $this->topicRepository->findOneBy(['slug' => $slug]);
        if ($topic === null) {
            $io->error(sprintf('Topic with slug "%s" not found', $slug));

            return Command::FAILURE;
        }

        $io->section(sprintf('Syncing topic: %s (ID: %d)', $topic->getTitle(), $topic->getId()));

        $result = $this->syncService->syncTopic($topic, $range, $maxSources, $dryRun);

        if ($result['skipped']) {
            $io->warning('Topic was skipped (NotebookLM unavailable or notebook creation failed)');

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Sources added: %d | Notebook: %s',
            $result['sources_added'],
            $result['notebook_id'] ?? 'N/A',
        ));

        return Command::SUCCESS;
    }

    private function syncAllTopics(
        SymfonyStyle $io,
        DateRange $range,
        int $maxSources,
        bool $dryRun,
    ): int {
        $summary = $this->syncService->syncAllActiveTopics($range, $maxSources, $dryRun);

        $io->table(
            ['Metric', 'Value'],
            [
                ['Topics synced', (string) $summary['topics_synced']],
                ['Total sources added', (string) $summary['total_sources']],
                ['Errors', (string) $summary['errors']],
            ],
        );

        if ($summary['errors'] > 0) {
            $io->warning(sprintf('%d topic(s) had errors during sync', $summary['errors']));

            return Command::FAILURE;
        }

        $io->success('All active topics synced successfully');

        return Command::SUCCESS;
    }

    private function parseDateRange(string $since): DateRange
    {
        if (preg_match('/^(\d+)d$/', $since, $matches)) {
            return DateRange::lastDays((int) $matches[1]);
        }

        return DateRange::lastDays(30);
    }
}
