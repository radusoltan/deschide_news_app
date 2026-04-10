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
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Briefing type: morning or evening', 'evening')
            ->addOption('date', 'd', InputOption::VALUE_REQUIRED, 'Specific date (YYYY-MM-DD). Defaults to today')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $type = $input->getOption('type');

        $dateStr = $input->getOption('date');
        $date = $dateStr !== null
            ? new \DateTimeImmutable($dateStr)
            : new \DateTimeImmutable('today');

        try {
            if ($type === 'morning') {
                return $this->executeMorning($io, $date, $input->getOption('dry-run'));
            }

            return $this->executeEvening($io, $date, $input->getOption('dry-run'));
        } catch (\Throwable $e) {
            $io->error('Daily briefing failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function executeMorning(SymfonyStyle $io, \DateTimeImmutable $date, bool $dryRun): int
    {
        $io->title("Morning Briefing: {$date->format('d.m.Y')}");

        $overnightStart = $date->modify('-1 day')->setTime(22, 0);
        $overnightEnd = $date->setTime(6, 0);

        $pressReleases = $this->briefingService->getOvernightPressReleases($overnightStart, $overnightEnd, 3.0);
        $articles = $this->briefingService->getArticlesForDate($overnightStart, $overnightEnd);

        $io->info(sprintf('%d PressReleases + %d articles found overnight', \count($pressReleases), \count($articles)));

        if (\count($pressReleases) + \count($articles) === 0) {
            $io->note('No overnight content found');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            foreach ($pressReleases as $pr) {
                $io->text("  PR - [{$pr->getSourceName()}] {$pr->getTitle()} (score: {$pr->getRelevanceScore()})");
            }
            foreach ($articles as $article) {
                $io->text("  ART - [{$article->getCategory()?->getTitle()}] {$article->getTitle()}");
            }
            $io->note('Dry run — would generate morning briefing');

            return Command::SUCCESS;
        }

        $io->section('Generating morning briefing via Gemini...');
        $content = $this->briefingService->generateMorningBriefing($date);

        if ($content === null) {
            $io->error('Failed to generate morning briefing');

            return Command::FAILURE;
        }

        $gc = $this->briefingService->saveMorningBriefing(
            $content,
            $date,
            \count($pressReleases) + \count($articles),
        );

        $io->success("Morning briefing saved as GeneratedContent #{$gc->getId()}");

        return Command::SUCCESS;
    }

    private function executeEvening(SymfonyStyle $io, \DateTimeImmutable $date, bool $dryRun): int
    {
        $io->title("Evening Briefing: {$date->format('d.m.Y')}");

        $nextDay = $date->modify('+1 day');
        $articles = $this->briefingService->getArticlesForDate($date, $nextDay);
        $io->info(\count($articles) . ' articles found');

        if ($articles === []) {
            $io->note('No articles found for this date');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            foreach ($articles as $article) {
                $io->text("  - [{$article->getCategory()?->getTitle()}] {$article->getTitle()}");
            }
            $io->note('Dry run — would generate evening briefing');

            return Command::SUCCESS;
        }

        $io->section('Generating evening briefing via Gemini...');
        $briefingContent = $this->briefingService->generateDailyBriefing($date);

        if ($briefingContent === null) {
            $io->error('Failed to generate daily briefing');

            return Command::FAILURE;
        }

        $gc = $this->briefingService->saveBriefing(
            $briefingContent,
            $date,
            \count($articles),
        );

        $io->success("Evening briefing saved as GeneratedContent #{$gc->getId()}");

        return Command::SUCCESS;
    }
}
