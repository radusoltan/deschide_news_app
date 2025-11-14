<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ArticleStatsDaily;
use App\Entity\SiteStatsDaily;
use App\Repository\ArticleRepository;
use App\Repository\PageViewRepository;
use App\Repository\SessionRepository;
use App\Service\PerformanceService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Predis\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:stats:aggregate',
    description: 'Aggregate statistics from Redis and page_views to daily tables'
)]
class AggregateStatsCommand extends Command
{
    public function __construct(
        private readonly PerformanceService $performance,
        private readonly PageViewRepository $pageViewRepository,
        private readonly SessionRepository $sessionRepository,
        private readonly ArticleRepository $articleRepository,
        private readonly EntityManagerInterface $em,
        private readonly Client $redis,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('date', null, InputOption::VALUE_OPTIONAL, 'Date to aggregate (Y-m-d)', 'yesterday')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without persisting data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dateString = $input->getOption('date');
        $dryRun = $input->getOption('dry-run');

        // Parse date
        if ($dateString === 'yesterday') {
            $date = new DateTime('yesterday');
        } else {
            $date = DateTime::createFromFormat('Y-m-d', $dateString);
            if (!$date) {
                $io->error('Invalid date format. Use Y-m-d');

                return Command::FAILURE;
            }
        }

        $io->title("Aggregating stats for {$date->format('Y-m-d')}");

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No data will be persisted');
        }

        // Aggregate article stats
        $this->aggregateArticleStats($date, $io, $dryRun);

        // Aggregate site stats
        $this->aggregateSiteStats($date, $io, $dryRun);

        $io->success('Stats aggregation completed successfully');

        return Command::SUCCESS;
    }

    private function aggregateArticleStats(DateTime $date, SymfonyStyle $io, bool $dryRun): void
    {
        $io->section('Aggregating article stats');

        $articles = $this->articleRepository->findAll();
        $progress = $io->createProgressBar(\count($articles));
        $progress->start();

        $aggregated = 0;

        foreach ($articles as $article) {
            $articleId = $article->getId();

            // Get views from page_views table (more reliable than Redis for historical data)
            $views = $this->pageViewRepository->countViewsByArticleAndDate($articleId, $date);

            if ($views > 0) {
                // Get unique visitors from page_views
                $uniqueVisitors = $this->pageViewRepository->countUniqueVisitorsByArticle($articleId, $date);

                // Get avg reading time from page_views
                $avgReadingTime = $this->pageViewRepository->getAvgReadingTimeByArticleAndDate(
                    $articleId,
                    $date
                );

                // Get completion rate from page_views
                $completionRate = $this->pageViewRepository->getCompletionRateByArticleAndDate(
                    $articleId,
                    $date
                );

                // Find or create ArticleStatsDaily
                $stats = $this->em->getRepository(ArticleStatsDaily::class)
                    ->findOneBy(['article' => $article, 'date' => $date]);

                if (!$stats) {
                    $stats = new ArticleStatsDaily();
                    $stats->setArticle($article);
                    $stats->setDate($date);
                }

                $stats->setViews($views);
                $stats->setUniqueVisitors($uniqueVisitors);
                $stats->setAvgReadingTime($avgReadingTime);
                $stats->setCompletionRate($completionRate);

                if (!$dryRun) {
                    $this->em->persist($stats);
                }

                ++$aggregated;
            }

            $progress->advance();
        }

        $progress->finish();
        $io->newLine(2);

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->info("Aggregated stats for {$aggregated} articles");
    }

    private function aggregateSiteStats(DateTime $date, SymfonyStyle $io, bool $dryRun): void
    {
        $io->section('Aggregating site stats');

        $dateStr = $date->format('Y-m-d');

        // Total visits (count page_views)
        $totalVisits = $this->pageViewRepository->countViewsByDate($date);

        // Unique visitors (HyperLogLog from Redis if available, otherwise from page_views)
        $uniqueVisitors = $this->performance->getUniqueVisitorCount($dateStr);
        if ($uniqueVisitors === 0) {
            // Fallback to counting from page_views
            $io->note('Redis HyperLogLog returned 0, falling back to page_views count');
        }

        // New visitors (first-time visitors)
        $newVisitors = $this->pageViewRepository->countNewVisitorsByDate($date);

        // Bounce rate (from sessions)
        $bounceRate = $this->sessionRepository->calculateBounceRate($date);

        // Avg session duration (from sessions)
        $avgSessionDuration = $this->sessionRepository->calculateAvgDuration($date);

        // Find or create SiteStatsDaily
        $stats = $this->em->getRepository(SiteStatsDaily::class)
            ->findOneBy(['date' => $date]);

        if (!$stats) {
            $stats = new SiteStatsDaily();
            $stats->setDate($date);
        }

        $stats->setTotalVisits($totalVisits);
        $stats->setUniqueVisitors($uniqueVisitors > 0 ? $uniqueVisitors : $totalVisits);
        $stats->setNewVisitors($newVisitors);
        $stats->setBounceRate($bounceRate);
        $stats->setAvgSessionDuration($avgSessionDuration);

        $io->table(
            ['Metric', 'Value'],
            [
                ['Total Visits', $totalVisits],
                ['Unique Visitors', $stats->getUniqueVisitors()],
                ['New Visitors', $newVisitors],
                ['Bounce Rate', round($bounceRate, 2) . '%'],
                ['Avg Session Duration', $avgSessionDuration . 's'],
            ]
        );

        if (!$dryRun) {
            $this->em->persist($stats);
            $this->em->flush();
        }

        $this->logger->info('Site stats aggregated', [
            'date' => $dateStr,
            'total_visits' => $totalVisits,
            'unique_visitors' => $stats->getUniqueVisitors(),
        ]);
    }
}
