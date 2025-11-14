<?php

declare(strict_types=1);

namespace App\Command\Import;

use Doctrine\DBAL\Connection;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:import:fix-article-categories',
    description: 'Fix article categories by re-mapping from Newscoop sections'
)]
class FixArticleCategoriesCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.newscoop_connection')]
        private Connection $newscoopConnection,
        private Connection $defaultConnection,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without updating data')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('Fix Article Categories from Newscoop Sections');

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No data will be updated');
        }

        // Get all imported articles
        $sql = "
            SELECT
                a.id as article_id,
                a.category_id as current_category_id,
                m.newscoop_id as newscoop_article_id,
                c.title as current_category_name
            FROM articles a
            JOIN newscoop_id_mapping m ON m.news_app_id = a.id AND m.entity_type = 'article'
            JOIN categories c ON c.id = a.category_id
            ORDER BY a.id
        ";

        $articles = $this->defaultConnection->fetchAllAssociative($sql);

        $io->success(\sprintf('Found %d articles to check', \count($articles)));

        $io->section('Checking articles against Newscoop sections');
        $io->progressStart(\count($articles));

        $fixedCount = 0;
        $correctCount = 0;
        $errorCount = 0;
        $missingMappingCount = 0;

        foreach ($articles as $article) {
            try {
                $articleId = $article['article_id'];
                $newscoopArticleId = $article['newscoop_article_id'];
                $currentCategoryId = $article['current_category_id'];

                // Get section from Newscoop
                $newscoopSql = \sprintf(
                    'SELECT NrSection FROM Articles WHERE Number = %d',
                    $newscoopArticleId
                );
                $newscoopArticle = $this->newscoopConnection->fetchAssociative($newscoopSql);

                if (!$newscoopArticle) {
                    $this->logger->warning('Article not found in Newscoop', [
                        'article_id' => $articleId,
                        'newscoop_id' => $newscoopArticleId,
                    ]);
                    ++$errorCount;
                    $io->progressAdvance();
                    continue;
                }

                $newscoopSection = $newscoopArticle['NrSection'];

                // Get correct category from mapping
                $mappingSql = "
                    SELECT news_app_id
                    FROM newscoop_id_mapping
                    WHERE entity_type = 'section' AND newscoop_id = ?
                ";
                $correctCategoryId = $this->defaultConnection->fetchOne($mappingSql, [$newscoopSection]);

                if (!$correctCategoryId) {
                    $this->logger->warning('No mapping found for section', [
                        'article_id' => $articleId,
                        'newscoop_section' => $newscoopSection,
                    ]);
                    ++$missingMappingCount;
                    $io->progressAdvance();
                    continue;
                }

                // Check if category needs fixing
                if ($currentCategoryId !== $correctCategoryId) {
                    if (!$dryRun) {
                        $this->defaultConnection->update(
                            'articles',
                            ['category_id' => $correctCategoryId],
                            ['id' => $articleId]
                        );
                    }
                    ++$fixedCount;
                } else {
                    ++$correctCount;
                }

                $io->progressAdvance();

            } catch (Exception $e) {
                ++$errorCount;
                $this->logger->error('Failed to fix article category', [
                    'article_id' => $article['article_id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
                $io->progressAdvance();
            }
        }

        $io->progressFinish();

        // Summary
        $io->section('Fix Summary');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Total articles checked', \count($articles)],
                ['Articles fixed', $fixedCount],
                ['Articles already correct', $correctCount],
                ['Missing section mapping', $missingMappingCount],
                ['Errors', $errorCount],
            ]
        );

        if ($dryRun) {
            $io->warning('DRY RUN: No data was updated');
        } else {
            $io->success(\sprintf('Successfully fixed %d article categories!', $fixedCount));
        }

        return Command::SUCCESS;
    }
}
