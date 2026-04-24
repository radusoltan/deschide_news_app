<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ArticleRepository;
use App\Service\Analytics\AnalyticsService;
use App\Service\Cache\CacheService;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:stats:trending',
    description: 'Update trending articles list'
)]
class TrendingArticlesCommand extends Command
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly CacheService $cache,
        private readonly ArticleRepository $articleRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('hours', null, InputOption::VALUE_OPTIONAL, 'Time window in hours', '24')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Number of trending articles', '10');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hours = (int) $input->getOption('hours');
        $limit = (int) $input->getOption('limit');

        $io->title("Updating trending articles (last {$hours} hours)");

        // Get trending articles from Redis
        $trending = $this->analytics->getTrendingArticles($limit);

        if (empty($trending)) {
            $io->warning('No trending articles found');

            return Command::SUCCESS;
        }

        // Enrich with article details
        $articles = [];
        foreach ($trending as $item) {
            $article = $this->articleRepository->find($item['article_id']);
            if ($article) {
                $category = $article->getCategory();
                $categoryName = 'N/A';

                if ($category) {
                    try {
                        // Handle both regular objects and Doctrine proxies
                        $categoryName = method_exists($category, '__load') ?
                            ($category->__load() ? $category->__toString() : 'N/A') :
                            (string) $category;
                    } catch (Exception $e) {
                        $categoryName = 'N/A';
                    }
                }

                $articles[] = [
                    'ID' => $article->getId(),
                    'Title' => mb_substr($article->getTitle(), 0, 50) . (mb_strlen($article->getTitle()) > 50 ? '...' : ''),
                    'Category' => $categoryName,
                    'Views (24h)' => $item['views'],
                ];
            }
        }

        $io->table(['ID', 'Title', 'Category', 'Views (24h)'], $articles);

        // Store in cache for quick retrieval
        $this->cache->setCached('api:trending', $trending, 300); // 5 min

        $io->success('Trending articles updated successfully');

        return Command::SUCCESS;
    }
}
