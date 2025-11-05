<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Service\PerformanceService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cache:warm',
    description: 'Warm cache with popular content'
)]
class CacheWarmCommand extends Command
{
    public function __construct(
        private readonly PerformanceService $performance,
        private readonly ArticleRepository $articleRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('popular', null, InputOption::VALUE_OPTIONAL, 'Number of popular articles to cache', '100')
            ->addOption('locales', null, InputOption::VALUE_OPTIONAL, 'Locales to warm (comma-separated)', 'ro,en,ru');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $popularCount = (int) $input->getOption('popular');
        $locales = explode(',', $input->getOption('locales'));

        $io->title('Warming cache for popular content');

        // Get popular articles (by views from trending)
        $trending = $this->performance->getTrendingArticles($popularCount);
        $articleIds = array_column($trending, 'article_id');

        if (empty($articleIds)) {
            $io->warning('No trending articles found. Warming cache for latest articles instead.');
            // Fallback to latest published articles
            $latestArticles = $this->articleRepository->createQueryBuilder('a')
                ->where('a.status = :status')
                ->setParameter('status', 'published')
                ->orderBy('a.publishedAt', 'DESC')
                ->setMaxResults($popularCount)
                ->getQuery()
                ->getResult();
            $articleIds = array_map(fn ($a) => $a->getId(), $latestArticles);
        }

        // Warm article cache
        $this->warmArticles($articleIds, $locales, $io);

        // Warm categories cache
        $this->warmCategories($locales, $io);

        $io->success('Cache warming completed successfully');

        $this->logger->info('Cache warmed', [
            'articles_count' => \count($articleIds),
            'locales' => implode(',', $locales),
        ]);

        return Command::SUCCESS;
    }

    private function warmArticles(array $articleIds, array $locales, SymfonyStyle $io): void
    {
        $io->section('Warming article cache');
        $progress = $io->createProgressBar(\count($articleIds) * \count($locales));
        $progress->start();

        $warmed = 0;

        foreach ($articleIds as $articleId) {
            $article = $this->articleRepository->find($articleId);
            if (!$article) {
                $progress->advance(\count($locales));
                continue;
            }

            foreach ($locales as $locale) {
                $cacheKey = "api:articles:{$articleId}:{$locale}";

                // Simple cache - just store the article entity
                // In production, you'd fetch through the proper provider with translations
                $this->performance->setCached($cacheKey, $article, 3600);
                ++$warmed;

                $progress->advance();
            }
        }

        $progress->finish();
        $io->newLine(2);
        $io->info("Warmed {$warmed} article cache entries");
    }

    private function warmCategories(array $locales, SymfonyStyle $io): void
    {
        $io->section('Warming category cache');
        $categories = $this->categoryRepository->findAll();

        $progress = $io->createProgressBar(\count($categories) * \count($locales));
        $progress->start();

        $warmed = 0;

        foreach ($categories as $category) {
            foreach ($locales as $locale) {
                $cacheKey = "api:categories:{$category->getId()}:{$locale}";

                // Simple cache - just store the category entity
                $this->performance->setCached($cacheKey, $category, 3600);
                ++$warmed;

                $progress->advance();
            }
        }

        $progress->finish();
        $io->newLine(2);
        $io->info("Warmed {$warmed} category cache entries");
    }
}
