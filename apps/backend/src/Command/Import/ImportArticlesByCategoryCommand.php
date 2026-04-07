<?php

declare(strict_types=1);

namespace App\Command\Import;

use App\Entity\Article;
use App\Enum\ArticleBadge;
use App\Enum\ArticleStatus;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
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
    name: 'app:import:articles-by-category',
    description: 'Import articles per category until each has at least 1000 articles'
)]
class ImportArticlesByCategoryCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'doctrine.dbal.newscoop_connection')]
        private Connection $newscoopConnection,
        private Connection $defaultConnection,
        private CategoryRepository $categoryRepository,
        private AuthorRepository $authorRepository,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('target', 't', InputOption::VALUE_OPTIONAL, 'Target articles per category', 1000)
            ->addOption('section', 's', InputOption::VALUE_OPTIONAL, 'Import only specific section ID')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $target = (int) $input->getOption('target');
        $specificSection = $input->getOption('section');

        $io->title('Import Articles by Category to reach target');
        $io->info(\sprintf('Target: %d articles per category', $target));

        // Get all section mappings
        $mappingsSql = "
            SELECT m.newscoop_id, m.news_app_id, c.title
            FROM newscoop_id_mapping m
            JOIN categories c ON m.news_app_id = c.id
            WHERE m.entity_type = 'section'
            ORDER BY m.newscoop_id
        ";

        if ($specificSection) {
            $mappingsSql .= \sprintf(' AND m.newscoop_id = %d', (int) $specificSection);
        }

        $mappings = $this->defaultConnection->fetchAllAssociative($mappingsSql);

        $io->section('Categories to process');
        $io->table(
            ['Section ID', 'Category', 'Category ID'],
            array_map(fn ($m) => [$m['newscoop_id'], $m['title'], $m['news_app_id']], $mappings)
        );

        $totalImported = 0;
        $categoriesProcessed = 0;

        foreach ($mappings as $mapping) {
            $sectionId = $mapping['newscoop_id'];
            $categoryId = $mapping['news_app_id'];
            $categoryName = $mapping['title'];

            $io->section(\sprintf('Processing Section #%d → Category: %s', $sectionId, $categoryName));

            // Count current articles in this category
            $currentCount = $this->defaultConnection->fetchOne(
                'SELECT COUNT(*) FROM articles WHERE category_id = ?',
                [$categoryId]
            );

            $needed = max(0, $target - $currentCount);

            $io->text(\sprintf('Current: %d articles, Need: %d more', $currentCount, $needed));

            if ($needed === 0) {
                $io->success(\sprintf('✓ Category "%s" already has %d articles', $categoryName, $currentCount));
                continue;
            }

            // Count available articles in Newscoop for this section
            $availableSql = \sprintf("
                SELECT COUNT(*)
                FROM Articles a
                WHERE a.NrSection = %d
                  AND a.Type = 'stiri'
                  AND a.Published = 'Y'
                  AND a.IdLanguage = 2
            ", $sectionId);

            $available = $this->newscoopConnection->fetchOne($availableSql);

            $io->text(\sprintf('Available in Newscoop: %d articles', $available));

            $toImport = min($needed, $available);

            if ($toImport === 0) {
                $io->warning(\sprintf('⚠ No articles available for section #%d', $sectionId));
                continue;
            }

            $io->text(\sprintf('Will import: %d articles', $toImport));

            // Import articles for this section
            $imported = $this->importArticlesForSection($sectionId, $categoryId, $toImport, $io);

            $totalImported += $imported;
            ++$categoriesProcessed;

            $io->success(\sprintf('✓ Imported %d articles for category "%s"', $imported, $categoryName));
        }

        $io->section('Final Summary');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Categories processed', $categoriesProcessed],
                ['Total articles imported', $totalImported],
            ]
        );

        $io->success('Import completed!');

        return Command::SUCCESS;
    }

    private function importArticlesForSection(int $sectionId, int $categoryId, int $limit, SymfonyStyle $io): int
    {
        // Fetch articles from this section
        $sql = \sprintf("
            SELECT
                a.Number,
                a.IdLanguage,
                a.Name,
                a.PublishDate,
                a.NrSection,
                x.FTitlu,
                x.Fsubtitlu,
                x.Flead,
                x.FContinut,
                x.FBREAKING_NEWS,
                x.FNEWS_ALERT,
                x.FFLASH
            FROM Articles a
            INNER JOIN Xstiri x ON a.Number = x.NrArticle AND a.IdLanguage = x.IdLanguage
            WHERE a.NrSection = %d
              AND a.Type = 'stiri'
              AND a.Published = 'Y'
              AND a.IdLanguage = 2
            ORDER BY a.PublishDate DESC
            LIMIT %d
        ", $sectionId, $limit);

        $articles = $this->newscoopConnection->fetchAllAssociative($sql);

        $category = $this->categoryRepository->find($categoryId);
        $importedCount = 0;

        $io->progressStart(\count($articles));

        foreach ($articles as $row) {
            try {
                $newscoopNumber = $row['Number'];

                // Check if already imported
                $existing = $this->defaultConnection->fetchOne(
                    'SELECT news_app_id FROM newscoop_id_mapping WHERE entity_type = ? AND newscoop_id = ?',
                    ['article', $newscoopNumber]
                );

                if ($existing) {
                    $io->progressAdvance();
                    continue;
                }

                // Create article
                $article = new Article();
                $article->setTitle($row['FTitlu'] ?: $row['Name']);
                $article->setContent($this->decodeBlobContent($row['FContinut']) ?: 'No content');
                $article->setLead($this->decodeBlobContent($row['Flead']));

                if ($row['PublishDate']) {
                    $article->setPublishedAt(new DateTimeImmutable($row['PublishDate']));
                }

                $article->setStatus(ArticleStatus::PUBLISHED);
                $article->setPublishedLocales(['ro']);

                // Set badge
                $badge = null;
                if ($row['FBREAKING_NEWS']) {
                    $badge = ArticleBadge::BREAKING;
                } elseif ($row['FNEWS_ALERT']) {
                    $badge = ArticleBadge::ALERT;
                } elseif ($row['FFLASH']) {
                    $badge = ArticleBadge::FLASH;
                }
                $article->setBadge($badge);

                $article->setCategory($category);

                $this->entityManager->persist($article);
                $this->entityManager->flush();

                // Link authors
                $authorsSql = \sprintf('
                    SELECT fk_author_id
                    FROM ArticleAuthors
                    WHERE fk_article_number = %d
                      AND fk_language_id = %d
                    ORDER BY `order` ASC
                ', $newscoopNumber, $row['IdLanguage']);

                $articleAuthors = $this->newscoopConnection->fetchAllAssociative($authorsSql);

                foreach ($articleAuthors as $authorRow) {
                    $authorId = $this->defaultConnection->fetchOne(
                        'SELECT news_app_id FROM newscoop_id_mapping WHERE entity_type = ? AND newscoop_id = ?',
                        ['author', $authorRow['fk_author_id']]
                    );

                    if ($authorId) {
                        $author = $this->authorRepository->find($authorId);
                        if ($author) {
                            $article->addAuthor($author);
                        }
                    }
                }

                $this->entityManager->flush();

                // Save mapping
                $this->defaultConnection->insert('newscoop_id_mapping', [
                    'entity_type' => 'article',
                    'newscoop_id' => $newscoopNumber,
                    'news_app_id' => $article->getId(),
                ]);

                // Clear every 50 articles
                if ($importedCount % 50 === 0) {
                    $this->entityManager->clear();
                    $category = $this->categoryRepository->find($categoryId);
                }

                ++$importedCount;
                $io->progressAdvance();

            } catch (Exception $e) {
                $this->logger->error('Article import failed', [
                    'article_number' => $row['Number'],
                    'section' => $sectionId,
                    'error' => $e->getMessage(),
                ]);

                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $this->entityManager->create(
                        $this->entityManager->getConnection(),
                        $this->entityManager->getConfiguration()
                    );
                    $category = $this->categoryRepository->find($categoryId);
                }

                $io->progressAdvance();
            }
        }

        $io->progressFinish();

        return $importedCount;
    }

    private function decodeBlobContent(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        if (\is_resource($content)) {
            $content = stream_get_contents($content);
        }

        return trim($content) ?: null;
    }
}
