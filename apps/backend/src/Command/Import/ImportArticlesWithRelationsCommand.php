<?php

declare(strict_types=1);

namespace App\Command\Import;

use App\Entity\Article;
use App\Entity\Category;
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
    name: 'app:import:articles-with-relations',
    description: 'Import articles from Newscoop with category and author relationships'
)]
class ImportArticlesWithRelationsCommand extends Command
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
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Number of articles to import', 1000)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without persisting data')
            ->addOption('offset', 'o', InputOption::VALUE_OPTIONAL, 'Offset for pagination', 0)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');
        $offset = (int) $input->getOption('offset');
        $dryRun = $input->getOption('dry-run');

        $io->title('Import Articles from Newscoop with Relations');
        $io->info(\sprintf('Importing %d Romanian articles (offset: %d)', $limit, $offset));

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No data will be persisted');
        }

        $io->section('Step 1: Fetching articles from Newscoop');

        // Fetch Romanian articles with content
        $sql = \sprintf("
            SELECT
                a.Number,
                a.IdLanguage,
                a.Name,
                a.PublishDate,
                a.UploadDate,
                a.time_updated,
                a.Keywords,
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
            WHERE a.Type = 'stiri'
              AND a.Published = 'Y'
              AND a.IdLanguage = 2
            ORDER BY a.PublishDate DESC
            LIMIT %d OFFSET %d
        ", $limit, $offset);

        $articles = $this->newscoopConnection->fetchAllAssociative($sql);

        $io->success(\sprintf('Found %d articles in Newscoop', \count($articles)));

        if (empty($articles)) {
            $io->warning('No articles found');

            return Command::SUCCESS;
        }

        $io->section('Step 2: Processing articles with relations');

        $importedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $categoryMissing = 0;
        $authorsAdded = 0;

        $io->progressStart(\count($articles));

        foreach ($articles as $row) {
            try {
                $newscoopNumber = $row['Number'];

                // Check if already imported
                if (!$dryRun) {
                    $existing = $this->defaultConnection->fetchOne(
                        'SELECT news_app_id FROM newscoop_id_mapping WHERE entity_type = ? AND newscoop_id = ?',
                        ['article', $newscoopNumber]
                    );

                    if ($existing) {
                        ++$skippedCount;
                        $io->progressAdvance();
                        continue;
                    }
                }

                // Decode BLOB content
                $content = $this->decodeBlobContent($row['FContinut']);
                $lead = $this->decodeBlobContent($row['Flead']);

                // Create article entity
                $article = new Article();
                $article->setTitle($row['FTitlu'] ?: $row['Name']);
                $article->setContent($content ?: 'No content');
                $article->setLead($lead);

                // Set dates
                if ($row['PublishDate']) {
                    $article->setPublishedAt(new DateTimeImmutable($row['PublishDate']));
                }

                // Set status
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

                // CATEGORY: Map from NrSection using newscoop_id_mapping
                $category = null;
                if ($row['NrSection']) {
                    $categoryId = $this->defaultConnection->fetchOne(
                        'SELECT news_app_id FROM newscoop_id_mapping WHERE entity_type = ? AND newscoop_id = ?',
                        ['section', $row['NrSection']]
                    );

                    if ($categoryId) {
                        $category = $this->categoryRepository->find($categoryId);
                    }
                }

                if (!$category) {
                    // Use fallback category
                    $category = $this->categoryRepository->findOneBy(['status' => 'active']);
                    ++$categoryMissing;
                }

                $article->setCategory($category);

                if (!$dryRun) {
                    $this->entityManager->persist($article);
                    $this->entityManager->flush();

                    // AUTHORS: Fetch from ArticleAuthors and link
                    $authorsSql = \sprintf('
                        SELECT fk_author_id, `order`
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
                                ++$authorsAdded;
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

                    // Clear every 50 articles to avoid memory issues
                    if ($importedCount % 50 === 0) {
                        $this->entityManager->clear();
                    }
                }

                ++$importedCount;
                $io->progressAdvance();

            } catch (Exception $e) {
                ++$errorCount;
                $this->logger->error('Article import failed', [
                    'article_number' => $row['Number'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Reset EntityManager if closed
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $this->entityManager->create(
                        $this->entityManager->getConnection(),
                        $this->entityManager->getConfiguration()
                    );
                }

                $io->progressAdvance();
            }
        }

        $io->progressFinish();

        // Summary
        $io->section('Import Summary');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Articles processed', \count($articles)],
                ['Articles imported', $importedCount],
                ['Authors linked', $authorsAdded],
                ['Skipped (already exist)', $skippedCount],
                ['Category missing (used fallback)', $categoryMissing],
                ['Errors', $errorCount],
            ]
        );

        if ($dryRun) {
            $io->warning('DRY RUN: No data was persisted');
        } else {
            $io->success(\sprintf(
                'Successfully imported %d articles with %d author relationships!',
                $importedCount,
                $authorsAdded
            ));
        }

        return Command::SUCCESS;
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
