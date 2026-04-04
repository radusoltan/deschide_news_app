<?php

declare(strict_types=1);

namespace App\Command\Editorial;

use App\Service\Editorial\DailyBriefingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:editorial:daily-briefing',
    description: 'Generate the daily press briefing and persist as GeneratedContent',
)]
final class DailyBriefingCommand extends Command
{
    public function __construct(
        private readonly DailyBriefingService $briefingService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('date', 'd', InputOption::VALUE_REQUIRED, 'Specific date (YYYY-MM-DD). Defaults to today')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dateStr = $input->getOption('date');
        $date = $dateStr !== null
            ? new \DateTimeImmutable($dateStr)
            : new \DateTimeImmutable('today');

        $io->title("Daily Briefing: {$date->format('d.m.Y')}");

        $nextDay = $date->modify('+1 day');
        $articles = $this->briefingService->getArticlesForDate($date, $nextDay);
        $io->info(\count($articles) . ' articles found');

        if ($articles === []) {
            $io->note('No articles found for this date');

            return Command::SUCCESS;
        }

        if ($input->getOption('dry-run')) {
            foreach ($articles as $article) {
                $io->text("  - [{$article->getCategory()?->getTitle()}] {$article->getTitle()}");
            }
            $io->note('Dry run — would generate briefing with ' . \count($articles) . ' articles');

            return Command::SUCCESS;
        }

        // Generate briefing
        $io->section('Generating briefing via Gemini...');
        $briefingContent = $this->briefingService->generateDailyBriefing($date);

        if ($briefingContent === null) {
            $io->error('Failed to generate daily briefing');

            return Command::FAILURE;
        }

        // Persist to DB
        $gc = $this->briefingService->saveBriefing(
            $briefingContent,
            $date,
            \count($articles),
        );

        $io->success("Daily briefing saved as GeneratedContent #{$gc->getId()}");

        return Command::SUCCESS;
    }
}
