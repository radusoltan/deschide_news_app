<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ArticleStatsDaily;
use App\Entity\PageView;
use App\Entity\Session;
use App\Entity\SiteStatsDaily;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Service\Analytics\AnalyticsService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:simulate:traffic',
    description: 'Simulate realistic site traffic for testing (page views, sessions, trending articles)',
)]
class SimulateTrafficCommand extends Command
{
    private const BATCH_SIZE = 500;

    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120.0.0.0',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148',
        'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile',
        'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Safari/604.1',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
    ];

    private const REFERRERS = [
        null,                                    // Direct traffic (~30%)
        null,
        null,
        'https://www.google.com/',               // Search (~25%)
        'https://www.google.com/',
        'https://www.google.com/search?q=stiri+moldova',
        'https://t.me/',                         // Telegram (~15%)
        'https://t.me/deschide_md',
        'https://www.facebook.com/',             // Facebook (~15%)
        'https://www.facebook.com/',
        'https://news.google.com/',              // Google News (~5%)
        'https://ok.ru/',                        // Odnoklassniki (~5%)
        'https://www.yandex.ru/',                // Yandex (~3%)
        'https://twitter.com/',                  // X/Twitter (~2%)
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ArticleRepository $articleRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly AnalyticsService $performanceService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Number of days to simulate', 30)
            ->addOption('daily-visits', null, InputOption::VALUE_OPTIONAL, 'Average daily page views', 800)
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Clear existing simulated data before generating')
            ->addOption('redis-only', null, InputOption::VALUE_NONE, 'Only populate Redis trending (skip DB)')
            ->addOption('seed', null, InputOption::VALUE_OPTIONAL, 'Random seed for reproducible results');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        $dailyVisits = (int) $input->getOption('daily-visits');
        $clear = $input->getOption('clear');
        $redisOnly = $input->getOption('redis-only');
        $seed = $input->getOption('seed');

        if ($seed !== null) {
            mt_srand((int) $seed);
        }

        $io->title('Simulare Trafic - Deschide News');

        try {
        // Load articles and categories
        $articles = $this->loadArticles();
        if (empty($articles)) {
            $io->error('Nu s-au găsit articole publicate.');

            return Command::FAILURE;
        }

        $categories = $this->loadCategoryMap();

        $io->info(sprintf(
            'Parametri: %d zile, ~%d vizite/zi, %d articole disponibile',
            $days,
            $dailyVisits,
            \count($articles)
        ));

        if ($clear) {
            $this->clearExistingData($io);
        }

        // Create weighted article pool (newer articles get more views)
        $weightedPool = $this->buildWeightedPool($articles);

        // Generate unique visitor pool (simulated users)
        $visitorPool = $this->generateVisitorPool((int) ($dailyVisits * 0.6));

        if (!$redisOnly) {
            // Phase 1: Generate page views + sessions
            $io->section('Faza 1: Generare PageViews și Sessions');
            $totalPageViews = $this->generatePageViews(
                $io,
                $output,
                $weightedPool,
                $visitorPool,
                $categories,
                $days,
                $dailyVisits
            );

            // Phase 2: Aggregate to daily stats
            $io->section('Faza 2: Agregare ArticleStatsDaily');
            $this->aggregateArticleStats($io, $output, $days);

            // Phase 3: Generate site stats
            $io->section('Faza 3: Generare SiteStatsDaily');
            $this->generateSiteStats($io, $output, $days, $dailyVisits);
        }

        // Phase 4: Populate Redis trending
        $io->section('Faza 4: Populare Redis trending');
        $this->populateRedisTrending($io, $weightedPool, $dailyVisits);

        // Summary
        $io->success('Simulare completă!');
        $this->printSummary($io, $days);

        return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @return array<int, array{id: int, publishedAt: DateTimeImmutable|null, categoryId: int|null}>
     */
    private function loadArticles(): array
    {
        $conn = $this->em->getConnection();
        $rows = $conn->fetchAllAssociative(
            "SELECT id, published_at, category_id FROM articles WHERE status = 'published' ORDER BY published_at DESC"
        );

        return array_map(fn (array $row) => [
            'id' => (int) $row['id'],
            'publishedAt' => $row['published_at'] ? new DateTimeImmutable($row['published_at']) : null,
            'categoryId' => $row['category_id'] ? (int) $row['category_id'] : null,
        ], $rows);
    }

    /**
     * @return array<int, \App\Entity\Category>
     */
    private function loadCategoryMap(): array
    {
        $categories = $this->categoryRepository->findAll();
        $map = [];
        foreach ($categories as $cat) {
            $map[$cat->getId()] = $cat;
        }

        return $map;
    }

    /**
     * Build weighted pool: recent articles get 5-10x more traffic.
     *
     * @return array<int, array{id: int, weight: float, categoryId: int|null}>
     */
    private function buildWeightedPool(array $articles): array
    {
        $now = new DateTimeImmutable();
        $pool = [];

        foreach ($articles as $article) {
            $publishedAt = $article['publishedAt'] ?? $now->modify('-1 year');
            $daysSincePublished = max(1, (int) $now->diff($publishedAt)->days);

            // Decay formula: newer articles get exponentially more weight
            if ($daysSincePublished <= 1) {
                $weight = 10.0;
            } elseif ($daysSincePublished <= 3) {
                $weight = 7.0;
            } elseif ($daysSincePublished <= 7) {
                $weight = 4.0;
            } elseif ($daysSincePublished <= 30) {
                $weight = 2.0;
            } elseif ($daysSincePublished <= 90) {
                $weight = 1.0;
            } else {
                $weight = 0.3;
            }

            // Add randomness: some articles "go viral"
            if (mt_rand(1, 100) <= 3) {
                $weight *= 5.0;
            }

            $pool[] = [
                'id' => $article['id'],
                'weight' => $weight,
                'categoryId' => $article['categoryId'],
            ];
        }

        return $pool;
    }

    /**
     * @return array<int, array{visitorId: string, ip: string, ua: string}>
     */
    private function generateVisitorPool(int $size): array
    {
        $pool = [];
        for ($i = 0; $i < $size; ++$i) {
            $pool[] = [
                'visitorId' => 'sim_' . bin2hex(random_bytes(8)),
                'ip' => sprintf('%.0f.%.0f.%.0f.%.0f', mt_rand(1, 223), mt_rand(0, 255), mt_rand(0, 255), mt_rand(1, 254)),
                'ua' => self::USER_AGENTS[array_rand(self::USER_AGENTS)],
            ];
        }

        return $pool;
    }

    private function generatePageViews(
        SymfonyStyle $io,
        OutputInterface $output,
        array $weightedPool,
        array $visitorPool,
        array $categories,
        int $days,
        int $dailyVisits,
    ): int {
        $totalPageViews = 0;
        $totalSessions = 0;
        $batchCount = 0;
        $today = new DateTimeImmutable('today');

        // Pre-compute cumulative weights for fast random selection
        $cumWeights = [];
        $totalWeight = 0.0;
        foreach ($weightedPool as $i => $item) {
            $totalWeight += $item['weight'];
            $cumWeights[$i] = $totalWeight;
        }

        $progress = new ProgressBar($output, $days);
        $progress->setFormat(' %current%/%max% zile [%bar%] %percent:3s%% — %message%');
        $progress->setMessage('Se generează...');
        $progress->start();

        for ($dayOffset = $days - 1; $dayOffset >= 0; --$dayOffset) {
            $date = $today->modify("-{$dayOffset} days");
            $dateStr = $date->format('Y-m-d');

            // Vary daily visits: weekdays +20%, weekends -30%, random ±15%
            $dayOfWeek = (int) $date->format('N'); // 1=Mon, 7=Sun
            $modifier = $dayOfWeek >= 6 ? 0.7 : 1.2;
            $modifier *= (mt_rand(85, 115) / 100);
            $visitsToday = (int) ($dailyVisits * $modifier);

            // Track unique visitors per day for sessions
            $dailyVisitors = [];

            for ($v = 0; $v < $visitsToday; ++$v) {
                // Pick weighted random article
                $article = $this->pickWeightedArticle($weightedPool, $cumWeights, $totalWeight);

                // Pick random visitor (with repeat visitors)
                $visitor = $visitorPool[mt_rand(0, \count($visitorPool) - 1)];

                // Random time during the day (peak hours: 8-12, 18-22)
                $hour = $this->pickHour();
                $minute = mt_rand(0, 59);
                $second = mt_rand(0, 59);
                $viewedAt = DateTime::createFromFormat(
                    'Y-m-d H:i:s',
                    sprintf('%s %02d:%02d:%02d', $dateStr, $hour, $minute, $second)
                );

                // Session duration: 30s - 5min (weighted toward shorter)
                $sessionDuration = $this->pickSessionDuration();

                // Create PageView
                $pageView = new PageView();
                $pageView->setVisitorId($visitor['visitorId']);
                $pageView->setIpAddress($visitor['ip']);
                $pageView->setUserAgent($visitor['ua']);
                $pageView->setReferrer(self::REFERRERS[array_rand(self::REFERRERS)]);
                $pageView->setViewedAt($viewedAt);
                $pageView->setSessionDuration($sessionDuration);

                // Set article reference
                $articleRef = $this->em->getReference(\App\Entity\Article::class, $article['id']);
                $pageView->setArticle($articleRef);

                // Set category if available
                if ($article['categoryId'] && isset($categories[$article['categoryId']])) {
                    $pageView->setCategory($categories[$article['categoryId']]);
                }

                $this->em->persist($pageView);
                ++$totalPageViews;
                ++$batchCount;

                // Track for session generation
                if (!isset($dailyVisitors[$visitor['visitorId']])) {
                    $dailyVisitors[$visitor['visitorId']] = [
                        'visitor' => $visitor,
                        'firstView' => $viewedAt,
                        'lastView' => $viewedAt,
                        'pageCount' => 1,
                    ];
                } else {
                    $dailyVisitors[$visitor['visitorId']]['pageCount']++;
                    if ($viewedAt > $dailyVisitors[$visitor['visitorId']]['lastView']) {
                        $dailyVisitors[$visitor['visitorId']]['lastView'] = $viewedAt;
                    }
                    if ($viewedAt < $dailyVisitors[$visitor['visitorId']]['firstView']) {
                        $dailyVisitors[$visitor['visitorId']]['firstView'] = $viewedAt;
                    }
                }

                // Flush in batches
                if ($batchCount >= self::BATCH_SIZE) {
                    $this->em->flush();
                    $this->em->clear();
                    // Re-load category references after clear
                    $categories = $this->loadCategoryMap();
                    $batchCount = 0;
                }
            }

            // Create sessions for this day
            foreach ($dailyVisitors as $visitorData) {
                $session = new Session();
                $session->setVisitorId($visitorData['visitor']['visitorId']);
                $session->setIpAddress($visitorData['visitor']['ip']);
                $session->setUserAgent($visitorData['visitor']['ua']);
                $session->setReferrer(self::REFERRERS[array_rand(self::REFERRERS)]);
                $session->setStartedAt($visitorData['firstView']);
                $session->setPageCount($visitorData['pageCount']);

                if ($visitorData['pageCount'] > 1) {
                    $endedAt = clone $visitorData['lastView'];
                    $endedAt->modify('+' . mt_rand(10, 120) . ' seconds');
                    $session->setEndedAt($endedAt);
                } else {
                    // Bounce: ~45% of single-page sessions end quickly
                    if (mt_rand(1, 100) <= 45) {
                        $endedAt = clone $visitorData['firstView'];
                        $endedAt->modify('+' . mt_rand(5, 30) . ' seconds');
                        $session->setEndedAt($endedAt);
                    }
                }

                $this->em->persist($session);
                ++$totalSessions;
                ++$batchCount;

                if ($batchCount >= self::BATCH_SIZE) {
                    $this->em->flush();
                    $this->em->clear();
                    $categories = $this->loadCategoryMap();
                    $batchCount = 0;
                }
            }

            $progress->setMessage(sprintf('%s — %d vizite', $dateStr, $visitsToday));
            $progress->advance();
        }

        // Final flush
        if ($batchCount > 0) {
            $this->em->flush();
            $this->em->clear();
        }

        $progress->finish();
        $io->newLine(2);
        $io->info(sprintf('Generat: %d page views, %d sesiuni', $totalPageViews, $totalSessions));

        return $totalPageViews;
    }

    private function aggregateArticleStats(SymfonyStyle $io, OutputInterface $output, int $days): void
    {
        $conn = $this->em->getConnection();
        $today = new DateTimeImmutable('today');

        $progress = new ProgressBar($output, $days);
        $progress->setFormat(' %current%/%max% zile [%bar%] %percent:3s%%');
        $progress->start();

        for ($dayOffset = $days - 1; $dayOffset >= 0; --$dayOffset) {
            $date = $today->modify("-{$dayOffset} days");
            $dateStr = $date->format('Y-m-d');

            // Aggregate page views per article for this day using raw SQL
            $sql = <<<'SQL'
                INSERT INTO article_stats_daily (article_id, date, views, unique_visitors, avg_reading_time, completion_rate)
                SELECT
                    pv.article_id,
                    DATE(pv.viewed_at),
                    COUNT(*) as views,
                    COUNT(DISTINCT pv.visitor_id) as unique_visitors,
                    AVG(pv.session_duration) as avg_reading_time,
                    ROUND(CAST(SUM(CASE WHEN pv.session_duration > 120 THEN 1 ELSE 0 END) AS DECIMAL) / NULLIF(COUNT(*), 0) * 100, 2) as completion_rate
                FROM page_views pv
                WHERE DATE(pv.viewed_at) = :date
                  AND pv.article_id IS NOT NULL
                GROUP BY pv.article_id, DATE(pv.viewed_at)
                ON CONFLICT (article_id, date) DO UPDATE SET
                    views = EXCLUDED.views,
                    unique_visitors = EXCLUDED.unique_visitors,
                    avg_reading_time = EXCLUDED.avg_reading_time,
                    completion_rate = EXCLUDED.completion_rate
            SQL;

            $conn->executeStatement($sql, ['date' => $dateStr]);
            $progress->advance();
        }

        $progress->finish();
        $io->newLine(2);

        $count = $conn->fetchOne('SELECT COUNT(*) FROM article_stats_daily');
        $io->info(sprintf('Generat: %d înregistrări article_stats_daily', $count));
    }

    private function generateSiteStats(SymfonyStyle $io, OutputInterface $output, int $days, int $dailyVisits): void
    {
        $conn = $this->em->getConnection();
        $today = new DateTimeImmutable('today');

        $progress = new ProgressBar($output, $days);
        $progress->setFormat(' %current%/%max% zile [%bar%] %percent:3s%%');
        $progress->start();

        for ($dayOffset = $days - 1; $dayOffset >= 0; --$dayOffset) {
            $date = $today->modify("-{$dayOffset} days");
            $dateStr = $date->format('Y-m-d');

            // Get actual counts from page_views
            $stats = $conn->fetchAssociative(
                'SELECT
                    COUNT(*) as total_visits,
                    COUNT(DISTINCT visitor_id) as unique_visitors
                FROM page_views
                WHERE DATE(viewed_at) = :date',
                ['date' => $dateStr]
            );

            // Calculate session-based metrics
            $sessionStats = $conn->fetchAssociative(
                "SELECT
                    COUNT(*) as total_sessions,
                    COUNT(CASE WHEN page_count = 1 THEN 1 END) as bounce_sessions,
                    AVG(duration) as avg_duration
                FROM sessions
                WHERE DATE(started_at) = :date
                  AND ended_at IS NOT NULL",
                ['date' => $dateStr]
            );

            $totalVisits = (int) ($stats['total_visits'] ?? 0);
            $uniqueVisitors = (int) ($stats['unique_visitors'] ?? 0);
            $totalSessions = (int) ($sessionStats['total_sessions'] ?? 1);
            $bounceSessions = (int) ($sessionStats['bounce_sessions'] ?? 0);
            $bounceRate = $totalSessions > 0 ? round($bounceSessions / $totalSessions * 100, 2) : 0;
            $avgDuration = (int) ($sessionStats['avg_duration'] ?? 0);
            $newVisitors = (int) ($uniqueVisitors * (mt_rand(30, 50) / 100)); // ~30-50% new

            $sql = <<<'SQL'
                INSERT INTO site_stats_daily (date, total_visits, unique_visitors, new_visitors, bounce_rate, avg_session_duration)
                VALUES (:date, :total_visits, :unique_visitors, :new_visitors, :bounce_rate, :avg_duration)
                ON CONFLICT (date) DO UPDATE SET
                    total_visits = EXCLUDED.total_visits,
                    unique_visitors = EXCLUDED.unique_visitors,
                    new_visitors = EXCLUDED.new_visitors,
                    bounce_rate = EXCLUDED.bounce_rate,
                    avg_session_duration = EXCLUDED.avg_session_duration
            SQL;

            $conn->executeStatement($sql, [
                'date' => $dateStr,
                'total_visits' => $totalVisits,
                'unique_visitors' => $uniqueVisitors,
                'new_visitors' => $newVisitors,
                'bounce_rate' => $bounceRate,
                'avg_duration' => $avgDuration,
            ]);

            $progress->advance();
        }

        $progress->finish();
        $io->newLine(2);

        $count = $conn->fetchOne('SELECT COUNT(*) FROM site_stats_daily');
        $io->info(sprintf('Generat: %d înregistrări site_stats_daily', $count));
    }

    private function populateRedisTrending(SymfonyStyle $io, array $weightedPool, int $dailyVisits): void
    {
        // Pick top articles for trending based on weight
        usort($weightedPool, fn ($a, $b) => $b['weight'] <=> $a['weight']);

        $topArticles = \array_slice($weightedPool, 0, min(50, \count($weightedPool)));
        $populated = 0;

        foreach ($topArticles as $article) {
            // Simulate views proportional to weight
            $views = (int) ($article['weight'] * mt_rand(20, 80));
            if ($views > 0) {
                for ($i = 0; $i < $views; ++$i) {
                    $this->performanceService->incrementArticleViews($article['id']);
                }
                ++$populated;
            }
        }

        // Also populate site visitors for today in Redis
        $visitorCount = (int) ($dailyVisits * 0.6);
        for ($i = 0; $i < min($visitorCount, 500); ++$i) {
            $this->performanceService->trackSiteVisitor('sim_' . bin2hex(random_bytes(8)));
        }

        $io->info(sprintf('Redis: %d articole în trending, %d vizitatori unici azi', $populated, min($visitorCount, 500)));
    }

    private function clearExistingData(SymfonyStyle $io): void
    {
        $conn = $this->em->getConnection();

        $conn->executeStatement('DELETE FROM page_views');
        $conn->executeStatement('DELETE FROM article_stats_daily');
        $conn->executeStatement('DELETE FROM site_stats_daily');
        $conn->executeStatement('DELETE FROM sessions');

        $io->warning('Date existente șterse din page_views, article_stats_daily, site_stats_daily, sessions.');
    }

    private function pickWeightedArticle(array $pool, array $cumWeights, float $totalWeight): array
    {
        $rand = mt_rand() / mt_getrandmax() * $totalWeight;

        foreach ($cumWeights as $i => $cumWeight) {
            if ($rand <= $cumWeight) {
                return $pool[$i];
            }
        }

        return $pool[array_key_last($pool)];
    }

    /**
     * Peak hours: 8-12, 18-22. Low hours: 0-6.
     */
    private function pickHour(): int
    {
        $roll = mt_rand(1, 100);

        if ($roll <= 5) {
            return mt_rand(0, 5);    // 5% night (0-5)
        }
        if ($roll <= 15) {
            return mt_rand(6, 7);    // 10% early morning
        }
        if ($roll <= 40) {
            return mt_rand(8, 12);   // 25% morning peak
        }
        if ($roll <= 55) {
            return mt_rand(13, 17);  // 15% afternoon
        }
        if ($roll <= 85) {
            return mt_rand(18, 22);  // 30% evening peak
        }

        return mt_rand(22, 23);      // 15% late evening
    }

    /**
     * Session duration: weighted toward shorter sessions.
     * Returns seconds.
     */
    private function pickSessionDuration(): int
    {
        $roll = mt_rand(1, 100);

        if ($roll <= 20) {
            return mt_rand(10, 30);     // 20% bounce (<30s)
        }
        if ($roll <= 50) {
            return mt_rand(30, 90);     // 30% short (30s-1.5min)
        }
        if ($roll <= 80) {
            return mt_rand(90, 180);    // 30% medium (1.5-3min)
        }

        return mt_rand(180, 420);       // 20% long (3-7min)
    }

    private function printSummary(SymfonyStyle $io, int $days): void
    {
        $conn = $this->em->getConnection();

        $pvCount = $conn->fetchOne('SELECT COUNT(*) FROM page_views') ?: 0;
        $sessionCount = $conn->fetchOne('SELECT COUNT(*) FROM sessions') ?: 0;
        $articleStatsCount = $conn->fetchOne('SELECT COUNT(*) FROM article_stats_daily') ?: 0;
        $siteStatsCount = $conn->fetchOne('SELECT COUNT(*) FROM site_stats_daily') ?: 0;

        $trending = $this->performanceService->getTrendingArticles(5);
        $uniqueToday = $this->performanceService->getUniqueVisitorCount(date('Y-m-d'));

        $io->table(
            ['Metric', 'Valoare'],
            [
                ['Page Views (DB)', number_format((int) $pvCount)],
                ['Sessions (DB)', number_format((int) $sessionCount)],
                ['Article Stats Daily (DB)', number_format((int) $articleStatsCount)],
                ['Site Stats Daily (DB)', number_format((int) $siteStatsCount)],
                ['Trending Articles (Redis)', \count($trending)],
                ['Unique Visitors Azi (Redis)', number_format($uniqueToday)],
            ]
        );

        if (!empty($trending)) {
            $io->section('Top 5 Trending (Redis)');
            $rows = [];
            foreach (\array_slice($trending, 0, 5) as $i => $t) {
                $rows[] = [
                    '#' . ($i + 1),
                    'Article ID: ' . $t['article_id'],
                    $t['views'] . ' views',
                ];
            }
            $io->table(['Rank', 'Article', 'Views'], $rows);
        }
    }
}
