<?php

declare(strict_types=1);

namespace App\Command\Editorial;

use App\Service\Editorial\WeeklySummaryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:editorial:weekly-summary',
    description: 'Generate the weekly editorial summary and persist as GeneratedContent',
)]
final class WeeklySummaryCommand extends Command
{
    public function __construct(
        private readonly WeeklySummaryService $summaryService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('week', 'w', InputOption::VALUE_REQUIRED, 'Specific week (e.g., 2026-W14). Defaults to current week')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $weekStr = $input->getOption('week');
        if ($weekStr !== null) {
            $weekEnd = new \DateTimeImmutable($weekStr . ' Sunday');
        } else {
            $weekEnd = new \DateTimeImmutable('Sunday this week');
        }

        $weekStart = $weekEnd->modify('-6 days');
        $weekNumber = $weekEnd->format('Y-\WW');

        $io->title("Weekly Summary: {$weekStart->format('d.m.Y')} — {$weekEnd->format('d.m.Y')} ({$weekNumber})");

        // Fetch articles for the period
        $articles = $this->summaryService->getArticlesForPeriod($weekStart, $weekEnd);
        $io->info(\count($articles) . ' published articles found');

        if ($articles === []) {
            $io->note('No articles found for this period');

            return Command::SUCCESS;
        }

        if ($input->getOption('dry-run')) {
            $io->table(
                ['Category', 'Count'],
                $this->categoryCounts($articles),
            );
            $io->note('Dry run — would generate summary with ' . \count($articles) . ' articles');

            return Command::SUCCESS;
        }

        // Generate summary
        $io->section('Generating summary via Gemini...');
        $summaryContent = $this->summaryService->generateWeeklySummary($weekEnd);

        if ($summaryContent === null) {
            $io->error('Failed to generate weekly summary');

            return Command::FAILURE;
        }

        // Persist to DB
        $gc = $this->summaryService->saveSummary(
            $summaryContent,
            $weekStart,
            $weekEnd,
            \count($articles),
        );

        $io->success("Weekly summary saved as GeneratedContent #{$gc->getId()}");

        return Command::SUCCESS;
    }

    /**
     * @param list<\App\Entity\Article> $articles
     * @return list<array{string, int}>
     */
    private function categoryCounts(array $articles): array
    {
        $counts = [];
        foreach ($articles as $article) {
            $cat = $article->getCategory()?->getTitle() ?? 'Necategorizat';
            $counts[$cat] = ($counts[$cat] ?? 0) + 1;
        }
        arsort($counts);

        $result = [];
        foreach ($counts as $cat => $count) {
            $result[] = [$cat, $count];
        }

        return $result;
    }
}
