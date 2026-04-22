<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\Article;
use App\Entity\ExternalArticleMapping;
use App\Enum\ArticleBadge;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Repository\ExternalArticleMappingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * Phase 1: Imports RO articles from the parsed CSV data into the database.
 * Handles deduplication via ExternalArticleMapping, batch flushing, and checkpoint saving.
 */
class LegacyArticleImporter
{
    public const SOURCE_KEY = 'csv_legacy_deschide';

    /** @var array{imported: int, skipped: int, errors: int} */
    private array $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];

    /** @var string[] */
    private array $errorLog = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private readonly ArticleRepository $articleRepository,
        private readonly ExternalArticleMappingRepository $mappingRepository,
        private readonly LegacyCategoryMapper $categoryMapper,
        private readonly LegacyAuthorMapper $authorMapper,
        private readonly ManagerRegistry $doctrine,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Import a single parsed CSV row as an Article.
     * Returns the created Article or null if skipped/failed.
     */
    public function importRow(array $data, bool $dryRun = false): ?Article
    {
        $uuid = $data['uuid'];
        $slug = $data['slug'];

        // Dedup check 1: ExternalArticleMapping (already imported from this CSV)
        if ($this->mappingRepository->existsByExternalId(self::SOURCE_KEY, $uuid)) {
            $this->logger->debug('Skipping already imported article: {uuid}', ['uuid' => $uuid]);
            ++$this->stats['skipped'];

            return null;
        }

        // Dedup check 2: slug collision with existing articles
        $existingBySlug = $this->articleRepository->findOneBy(['slug' => $slug]);
        if ($existingBySlug !== null) {
            $this->logger->debug('Skipping duplicate slug: {slug} (existing ID: {id})', [
                'slug' => $slug,
                'id' => $existingBySlug->getId(),
            ]);
            ++$this->stats['skipped'];

            return null;
        }

        if ($dryRun) {
            $this->logger->info('[DRY] Would import: {title}', [
                'title' => mb_substr($data['title'], 0, 60),
            ]);
            ++$this->stats['imported'];

            return null;
        }

        try {
            $article = $this->createArticle($data);

            // Create ExternalArticleMapping for dedup and tracking
            $mapping = new ExternalArticleMapping();
            $mapping->setArticle($article);
            $mapping->setSource(self::SOURCE_KEY);
            $mapping->setExternalId($uuid);
            $mapping->setMetadata([
                'original_slug' => $slug,
                'csv_category' => $data['category'],
                'csv_author' => $data['author'],
                'main_image_url' => $data['mainImage'],
                'has_ru_translation' => $data['hasRuTranslation'],
            ]);

            $this->entityManager->persist($mapping);

            ++$this->stats['imported'];

            return $article;
        } catch (\Exception $e) {
            $this->logger->error('Failed to import article {uuid}: {error}', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            $this->errorLog[] = sprintf('[%s] %s: %s', $uuid, $data['title'], $e->getMessage());
            ++$this->stats['errors'];

            // Reset EM if closed
            if (!$this->entityManager->isOpen()) {
                $this->resetEntityManager();
            }

            return null;
        }
    }

    /**
     * Flush pending entities and clear the EM + caches.
     * Handles UniqueConstraintViolation gracefully by resetting the EM.
     */
    public function flush(): void
    {
        if (!$this->entityManager->isOpen()) {
            $this->resetEntityManager();

            return;
        }

        try {
            $this->entityManager->flush();
            $this->entityManager->clear();
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            $this->logger->warning('Unique constraint violation during flush, resetting EM: {msg}', [
                'msg' => $e->getMessage(),
            ]);
            $this->errorLog[] = sprintf('[FLUSH] UniqueConstraint: %s', $e->getMessage());
            ++$this->stats['errors'];
            $this->resetEntityManager();

            return;
        } catch (\Exception $e) {
            $this->logger->error('Flush failed, resetting EM: {msg}', ['msg' => $e->getMessage()]);
            $this->errorLog[] = sprintf('[FLUSH] %s', $e->getMessage());
            ++$this->stats['errors'];
            $this->resetEntityManager();

            return;
        }

        $this->categoryMapper->clearCache();
        $this->authorMapper->clearCache();
    }

    /**
     * Get import statistics.
     *
     * @return array{imported: int, skipped: int, errors: int}
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Get detailed error log.
     *
     * @return string[]
     */
    public function getErrorLog(): array
    {
        return $this->errorLog;
    }

    /**
     * Reset statistics (for re-runs).
     */
    public function resetStats(): void
    {
        $this->stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];
        $this->errorLog = [];
    }

    private function createArticle(array $data): Article
    {
        $article = new Article();
        $article->setTranslatableLocale('ro');

        // Title (max 255)
        $article->setTitle($data['title']);

        // Slug
        $article->setSlug($data['slug']);

        // Lead
        if (!empty($data['lead'])) {
            $article->setLead($data['lead']);
        }

        // Content
        if (!empty($data['content'])) {
            $article->setContent($data['content']);
        }

        // Status
        if ($data['archived']) {
            $article->setStatus(ArticleStatus::ARCHIVED);
        } elseif ($data['draft'] || $data['publishedAt'] === null) {
            $article->setStatus(ArticleStatus::NEW);
        } else {
            $article->setStatus(ArticleStatus::PUBLISHED);
        }

        // Badge (priority: breaking > alert > flash)
        if ($data['isBreakingNews']) {
            $article->setBadge(ArticleBadge::BREAKING);
        } elseif ($data['isNewsAlert']) {
            $article->setBadge(ArticleBadge::ALERT);
        } elseif ($data['isFlashNews']) {
            $article->setBadge(ArticleBadge::FLASH);
        }

        // Featured
        $article->setIsFeatured($data['isFeatured']);

        // Dates
        if ($data['publishedAt'] !== null) {
            $article->setPublishedAt($data['publishedAt']);
        }

        // Scheduled publish
        if ($data['scheduledPublishAt'] !== null) {
            $article->setPublishAt($data['scheduledPublishAt']);
        }

        // publishedLocales — start with 'ro'
        $article->setPublishedLocales(['ro']);

        // Category
        if (!empty($data['category'])) {
            $category = $this->categoryMapper->map($data['category']);
            if ($category !== null) {
                $article->setCategory($category);
            }
        }

        // Author
        if (!empty($data['author'])) {
            $author = $this->authorMapper->map($data['author']);
            if ($author !== null) {
                $article->addAuthor($author);
            }
        }

        $this->entityManager->persist($article);

        return $article;
    }

    private function resetEntityManager(): void
    {
        $this->entityManager = $this->doctrine->resetManager();
        $this->categoryMapper->clearCache();
        $this->authorMapper->clearCache();
        $this->logger->warning('EntityManager was reset after closure');
    }
}
