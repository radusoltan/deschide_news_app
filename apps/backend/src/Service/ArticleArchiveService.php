<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Enum\ArchiveReason;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Service for managing article archiving operations.
 *
 * Provides functionality to:
 * - Archive individual articles with specific reasons
 * - Unarchive articles and restore them to published status
 * - Bulk archive old articles in batches
 * - Generate archive statistics (total, by year, by category)
 * - Get available years with article counts for filtering
 */
class ArticleArchiveService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ArticleRepository $articleRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Archive a single article with a specific reason.
     *
     * Sets the article status to ARCHIVED, records the archive timestamp
     * and reason. The operation is persisted immediately.
     *
     * @param Article $article The article to archive
     * @param ArchiveReason $reason The reason for archiving
     *
     * @throws \Doctrine\ORM\ORMException
     */
    public function archiveArticle(Article $article, ArchiveReason $reason): void
    {
        $this->logger->info('Archiving article', [
            'article_id' => $article->getId(),
            'title' => $article->getTitle(),
            'reason' => $reason->value,
        ]);

        $article->archive($reason);
        $this->entityManager->flush();

        $this->logger->info('Article archived successfully', [
            'article_id' => $article->getId(),
            'archived_at' => $article->getArchivedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Unarchive an article and restore it to published status.
     *
     * Restores the article by setting status to PUBLISHED and clearing
     * archive-related fields (archivedAt, archiveReason).
     *
     * @param Article $article The article to unarchive
     *
     * @throws \Doctrine\ORM\ORMException
     */
    public function unarchiveArticle(Article $article): void
    {
        $this->logger->info('Unarchiving article', [
            'article_id' => $article->getId(),
            'title' => $article->getTitle(),
            'previous_reason' => $article->getArchiveReason()?->value,
        ]);

        $article->unarchive();
        $this->entityManager->flush();

        $this->logger->info('Article unarchived successfully', [
            'article_id' => $article->getId(),
            'status' => $article->getStatus()->value,
        ]);
    }

    /**
     * Archive articles older than specified years in batches.
     *
     * Processes articles published before the threshold date (e.g., 4 years ago).
     * Uses batch processing with transaction management to handle large volumes
     * efficiently and prevent memory issues.
     *
     * @param int $yearsOld Number of years threshold (e.g., 4 for articles older than 4 years)
     * @param int $batchSize Number of articles to process per batch (default: 100)
     *
     * @throws Exception
     *
     * @return int Total number of articles archived
     */
    public function archiveOldArticles(int $yearsOld, int $batchSize = 100): int
    {
        $thresholdDate = new DateTimeImmutable("-{$yearsOld} years");

        $this->logger->info('Starting bulk archive of old articles', [
            'years_threshold' => $yearsOld,
            'threshold_date' => $thresholdDate->format('Y-m-d'),
            'batch_size' => $batchSize,
        ]);

        $totalArchived = 0;

        // Use transaction for each batch
        while (true) {
            // Begin transaction for this batch
            $this->entityManager->beginTransaction();

            try {
                // Query articles to archive (limit to batch size)
                $qb = $this->articleRepository->createQueryBuilder('a')
                    ->where('a.status = :published')
                    ->andWhere('a.publishedAt < :threshold')
                    ->setParameter('published', ArticleStatus::PUBLISHED)
                    ->setParameter('threshold', $thresholdDate)
                    ->orderBy('a.publishedAt', 'ASC')
                    ->setMaxResults($batchSize);

                $articles = $qb->getQuery()->getResult();

                // If no more articles, break the loop
                if (empty($articles)) {
                    $this->entityManager->rollback();
                    break;
                }

                // Archive each article in the batch
                foreach ($articles as $article) {
                    $article->archive(ArchiveReason::OLD_CONTENT);
                    ++$totalArchived;
                }

                // Flush and commit the batch
                $this->entityManager->flush();
                $this->entityManager->commit();

                $this->logger->debug('Batch archived', [
                    'batch_count' => \count($articles),
                    'total_archived' => $totalArchived,
                ]);

                // Clear entity manager to free memory
                $this->entityManager->clear();
            } catch (Exception $e) {
                $this->entityManager->rollback();
                $this->logger->error('Error during batch archiving', [
                    'error' => $e->getMessage(),
                    'total_archived_before_error' => $totalArchived,
                ]);

                throw $e;
            }
        }

        $this->logger->info('Bulk archive completed', [
            'total_archived' => $totalArchived,
            'threshold_date' => $thresholdDate->format('Y-m-d'),
        ]);

        return $totalArchived;
    }

    /**
     * Get archive statistics.
     *
     * Returns comprehensive statistics about archived articles including:
     * - total: Total number of archived articles
     * - byYear: Array of year objects with counts
     * - byCategory: Array of category objects with counts
     *
     * @param string $locale The locale for translatable category names (default: 'ro')
     *
     * @return array{total: int, byYear: array, byCategory: array}
     */
    public function getArchiveStats(string $locale = 'ro'): array
    {
        $conn = $this->entityManager->getConnection();

        // Total archived articles
        $totalQb = $this->articleRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.status = :archived')
            ->setParameter('archived', ArticleStatus::ARCHIVED);

        $total = (int) $totalQb->getQuery()->getSingleScalarResult();

        // Articles by publication year using native SQL
        $byYearSql = '
            SELECT EXTRACT(YEAR FROM published_at) as year, COUNT(id) as count
            FROM articles
            WHERE status = :archived AND published_at IS NOT NULL
            GROUP BY year
            ORDER BY year DESC
        ';
        $byYearResult = $conn->executeQuery($byYearSql, ['archived' => ArticleStatus::ARCHIVED->value])->fetchAllAssociative();

        $byYear = [];
        foreach ($byYearResult as $row) {
            $byYear[] = [
                'year' => (int) $row['year'],
                'count' => (int) $row['count'],
            ];
        }

        // Articles by category
        $byCategoryQb = $this->articleRepository->createQueryBuilder('a')
            ->select('c.id as category_id, c.title as category_title, COUNT(a.id) as count')
            ->leftJoin('a.category', 'c')
            ->where('a.status = :archived')
            ->setParameter('archived', ArticleStatus::ARCHIVED)
            ->groupBy('c.id, c.title')
            ->orderBy('count', 'DESC');

        // Set locale hint for translatable category names
        $byCategoryQuery = $byCategoryQb->getQuery();
        $byCategoryQuery->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        $byCategoryResult = $byCategoryQuery->getResult();
        $byCategory = [];
        foreach ($byCategoryResult as $row) {
            $byCategory[] = [
                'id' => $row['category_id'] ? (int) $row['category_id'] : null,
                'name' => $row['category_title'] ?? 'Uncategorized',
                'count' => (int) $row['count'],
            ];
        }

        $this->logger->debug('Archive statistics generated', [
            'total' => $total,
            'years_count' => \count($byYear),
            'categories_count' => \count($byCategory),
        ]);

        return [
            'total' => $total,
            'byYear' => $byYear,
            'byCategory' => $byCategory,
        ];
    }

    /**
     * Get available years with archived article counts.
     *
     * Returns an array of years (publication year) with their respective
     * archived article counts. Useful for year-based filtering in archive views.
     *
     * @param string $locale The locale for translatable fields (default: 'ro')
     *
     * @return array<int, array{year: int, count: int}> Array of year objects, ordered by year descending
     */
    public function getAvailableYears(string $locale = 'ro'): array
    {
        $conn = $this->entityManager->getConnection();

        // Use native SQL for EXTRACT(YEAR FROM ...) which is not available in DQL
        $sql = '
            SELECT EXTRACT(YEAR FROM published_at) as year, COUNT(id) as count
            FROM articles
            WHERE status = :archived AND published_at IS NOT NULL
            GROUP BY year
            ORDER BY year DESC
        ';

        $result = $conn->executeQuery($sql, ['archived' => ArticleStatus::ARCHIVED->value])->fetchAllAssociative();

        $years = [];
        foreach ($result as $row) {
            $years[] = [
                'year' => (int) $row['year'],
                'count' => (int) $row['count'],
            ];
        }

        $this->logger->debug('Available years retrieved', [
            'years_count' => \count($years),
            'locale' => $locale,
        ]);

        return $years;
    }

    /**
     * Get categories that have archived articles.
     *
     * Returns an array of categories with their archived article counts.
     * Only includes categories that have at least one archived article.
     *
     * @param string $locale The locale for translatable category names (default: 'ro')
     *
     * @return array<int, array{id: int, name: string, slug: string, count: int}>
     */
    public function getCategoriesWithArchivedArticles(string $locale = 'ro'): array
    {
        $qb = $this->articleRepository->createQueryBuilder('a')
            ->select('c.id, c.title, c.slug, COUNT(a.id) as count')
            ->leftJoin('a.category', 'c')
            ->where('a.status = :archived')
            ->andWhere('c.id IS NOT NULL')
            ->setParameter('archived', ArticleStatus::ARCHIVED)
            ->groupBy('c.id, c.title, c.slug')
            ->orderBy('count', 'DESC');

        $query = $qb->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        $result = $query->getResult();

        $categories = [];
        foreach ($result as $row) {
            $categories[] = [
                'id' => (int) $row['id'],
                'name' => $row['title'] ?? 'Uncategorized',
                'slug' => $row['slug'] ?? '',
                'count' => (int) $row['count'],
            ];
        }

        $this->logger->debug('Categories with archived articles retrieved', [
            'categories_count' => \count($categories),
            'locale' => $locale,
        ]);

        return $categories;
    }

    /**
     * Bulk archive old articles (admin operation).
     *
     * Archives articles published before the specified threshold and returns
     * detailed results including cutoff date.
     *
     * @param int $yearsOld Number of years threshold
     * @param int $batchSize Number of articles per batch
     *
     * @return array{total_archived: int, cutoff_date: DateTimeImmutable}
     */
    public function bulkArchiveOldArticles(int $yearsOld, int $batchSize = 100): array
    {
        $cutoffDate = new DateTimeImmutable("-{$yearsOld} years");
        $totalArchived = $this->archiveOldArticles($yearsOld, $batchSize);

        return [
            'total_archived' => $totalArchived,
            'cutoff_date' => $cutoffDate,
        ];
    }

    /**
     * Get detailed archive statistics for admin dashboard.
     *
     * Returns comprehensive statistics including:
     * - Total articles count
     * - Archived articles count
     * - Archive percentage
     * - Breakdown by archive reason
     * - Archived this month/year
     * - Oldest and most recent archived articles
     */
    public function getArchiveStatistics(): array
    {
        // Total articles (all statuses)
        $totalArticles = (int) $this->articleRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Archived articles
        $archivedArticles = (int) $this->articleRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.status = :archived')
            ->setParameter('archived', ArticleStatus::ARCHIVED)
            ->getQuery()
            ->getSingleScalarResult();

        // Archive percentage
        $archivePercentage = $totalArticles > 0
            ? round(($archivedArticles / $totalArticles) * 100, 2)
            : 0;

        // By archive reason
        $byReasonQb = $this->articleRepository->createQueryBuilder('a')
            ->select('a.archiveReason as reason, COUNT(a.id) as count')
            ->where('a.status = :archived')
            ->andWhere('a.archiveReason IS NOT NULL')
            ->setParameter('archived', ArticleStatus::ARCHIVED)
            ->groupBy('a.archiveReason');

        $byReasonResult = $byReasonQb->getQuery()->getResult();
        $byReason = [];
        foreach ($byReasonResult as $row) {
            $reasonValue = $row['reason'] instanceof ArchiveReason
                ? $row['reason']->value
                : (string) $row['reason'];
            $byReason[$reasonValue] = (int) $row['count'];
        }

        // Archived this month
        $startOfMonth = new DateTimeImmutable('first day of this month 00:00:00');
        $archivedThisMonth = (int) $this->articleRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.status = :archived')
            ->andWhere('a.archivedAt >= :startOfMonth')
            ->setParameter('archived', ArticleStatus::ARCHIVED)
            ->setParameter('startOfMonth', $startOfMonth)
            ->getQuery()
            ->getSingleScalarResult();

        // Archived this year
        $startOfYear = new DateTimeImmutable('first day of January 00:00:00');
        $archivedThisYear = (int) $this->articleRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.status = :archived')
            ->andWhere('a.archivedAt >= :startOfYear')
            ->setParameter('archived', ArticleStatus::ARCHIVED)
            ->setParameter('startOfYear', $startOfYear)
            ->getQuery()
            ->getSingleScalarResult();

        // Oldest archived
        $oldestArchived = $this->articleRepository->createQueryBuilder('a')
            ->where('a.status = :archived')
            ->andWhere('a.archivedAt IS NOT NULL')
            ->setParameter('archived', ArticleStatus::ARCHIVED)
            ->orderBy('a.archivedAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        // Most recent archived
        $mostRecentArchived = $this->articleRepository->createQueryBuilder('a')
            ->where('a.status = :archived')
            ->andWhere('a.archivedAt IS NOT NULL')
            ->setParameter('archived', ArticleStatus::ARCHIVED)
            ->orderBy('a.archivedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'total_articles' => $totalArticles,
            'archived_articles' => $archivedArticles,
            'archive_percentage' => $archivePercentage,
            'by_reason' => $byReason,
            'archived_this_month' => $archivedThisMonth,
            'archived_this_year' => $archivedThisYear,
            'oldest_archived' => $oldestArchived ? [
                'id' => $oldestArchived->getId(),
                'title' => $oldestArchived->getTitle(),
                'archived_at' => $oldestArchived->getArchivedAt()?->format('c'),
                'reason' => $oldestArchived->getArchiveReason()?->value,
            ] : null,
            'most_recent_archived' => $mostRecentArchived ? [
                'id' => $mostRecentArchived->getId(),
                'title' => $mostRecentArchived->getTitle(),
                'archived_at' => $mostRecentArchived->getArchivedAt()?->format('c'),
                'reason' => $mostRecentArchived->getArchiveReason()?->value,
            ] : null,
        ];
    }
}
