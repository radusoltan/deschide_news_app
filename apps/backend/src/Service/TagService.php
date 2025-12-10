<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Entity\Tag;
use App\Repository\TagRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing tags and their relationships with articles.
 */
class TagService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TagRepository $tagRepository
    ) {
    }

    /**
     * Sync article tags by names.
     *
     * This method removes all existing tags from the article and adds new ones.
     * It also updates the usage count for all affected tags.
     *
     * @param Article $article The article to sync tags for
     * @param array<string> $tagNames Array of tag names
     * @param string $locale The locale for tag names (ro, en, ru)
     */
    public function syncArticleTags(Article $article, array $tagNames, string $locale = 'ro'): void
    {
        // Remove all existing tags and decrement their usage count
        foreach ($article->getTags() as $tag) {
            $article->removeTag($tag);
            $tag->setUsageCount(max(0, $tag->getUsageCount() - 1));
        }

        // Add new tags
        foreach ($tagNames as $tagName) {
            if ($tagName === null || $tagName === '') {
                continue;
            }

            $tagName = trim($tagName);
            if (empty($tagName)) {
                continue;
            }

            $tag = $this->tagRepository->findOrCreateByName($tagName, $locale);
            $article->addTag($tag);
            $tag->setUsageCount($tag->getUsageCount() + 1);
        }

        $this->entityManager->flush();
    }

    /**
     * Add tags to an article without removing existing ones.
     *
     * @param Article $article The article to add tags to
     * @param array<string> $tagNames Array of tag names
     * @param string $locale The locale for tag names (ro, en, ru)
     */
    public function addTagsToArticle(Article $article, array $tagNames, string $locale = 'ro'): void
    {
        foreach ($tagNames as $tagName) {
            if ($tagName === null || $tagName === '') {
                continue;
            }

            $tagName = trim($tagName);
            if (empty($tagName)) {
                continue;
            }

            $tag = $this->tagRepository->findOrCreateByName($tagName, $locale);

            // Only add if not already present
            if (!$article->getTags()->contains($tag)) {
                $article->addTag($tag);
                $tag->setUsageCount($tag->getUsageCount() + 1);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Remove tags from an article.
     *
     * @param Article $article The article to remove tags from
     * @param array<string|int> $tagIdentifiers Array of tag IDs or names
     * @param string $locale The locale for tag names if using names (ro, en, ru)
     */
    public function removeTagsFromArticle(Article $article, array $tagIdentifiers, string $locale = 'ro'): void
    {
        foreach ($tagIdentifiers as $identifier) {
            $tag = null;

            if (\is_int($identifier)) {
                // Find by ID
                $tag = $this->tagRepository->find($identifier);
            } else {
                // Find by name
                $tags = $this->tagRepository->findByNameSearch($identifier, $locale, 1);
                $tag = $tags[0] ?? null;
            }

            if ($tag && $article->getTags()->contains($tag)) {
                $article->removeTag($tag);
                $tag->setUsageCount(max(0, $tag->getUsageCount() - 1));
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Get popular tags ordered by usage count.
     *
     * @param string $locale The locale for tag names (ro, en, ru)
     * @param int $limit Maximum number of tags to return
     *
     * @return Tag[]
     */
    public function getPopularTags(string $locale = 'ro', int $limit = 20): array
    {
        return $this->tagRepository->findPopularTags($limit, $locale);
    }

    /**
     * Search tags by name for auto-complete functionality.
     *
     * @param string $query The search query
     * @param string $locale The locale for tag names (ro, en, ru)
     * @param int $limit Maximum number of results
     *
     * @return Tag[]
     */
    public function searchTags(string $query, string $locale = 'ro', int $limit = 10): array
    {
        return $this->tagRepository->findByNameSearch($query, $locale, $limit);
    }

    /**
     * Recalculate usage count for all tags.
     *
     * This is useful for fixing inconsistencies in usage counts.
     *
     * @return int Number of tags updated
     */
    public function recalculateUsageCounts(): int
    {
        $tags = $this->tagRepository->findAll();
        $updated = 0;

        foreach ($tags as $tag) {
            $count = $tag->getArticles()->count();
            if ($tag->getUsageCount() !== $count) {
                $tag->setUsageCount($count);
                ++$updated;
            }
        }

        $this->entityManager->flush();

        return $updated;
    }

    /**
     * Cleanup unused tags older than specified days.
     *
     * @param int $daysOld Remove tags with zero usage older than this many days
     * @param bool $dryRun If true, only count tags without deleting them
     *
     * @return int Number of tags deleted (or would be deleted in dry run)
     */
    public function cleanupUnusedTags(int $daysOld = 30, bool $dryRun = false): int
    {
        $date = new DateTimeImmutable("-{$daysOld} days");
        $unusedTags = $this->tagRepository->findUnusedTags($date);

        if ($dryRun) {
            return \count($unusedTags);
        }

        foreach ($unusedTags as $tag) {
            $this->entityManager->remove($tag);
        }

        $this->entityManager->flush();

        return \count($unusedTags);
    }

    /**
     * Get or create a tag by name.
     *
     * @param string $name The tag name
     * @param string $locale The locale for the tag name (ro, en, ru)
     */
    public function getOrCreateTag(string $name, string $locale = 'ro'): Tag
    {
        return $this->tagRepository->findOrCreateByName($name, $locale);
    }

    /**
     * Get tag statistics.
     *
     * @return array{totalTags: int, totalUsages: int, averageUsage: float, tagsInUse: int, unusedTags: int, mostUsedTag: ?Tag}
     */
    public function getTagStatistics(): array
    {
        $allTags = $this->tagRepository->findAll();
        $total = \count($allTags);
        $used = 0;
        $totalUsage = 0;
        $mostUsedTag = null;
        $maxUsage = 0;

        foreach ($allTags as $tag) {
            $usageCount = $tag->getUsageCount();

            if ($usageCount > 0) {
                ++$used;
            }

            $totalUsage += $usageCount;

            if ($usageCount > $maxUsage) {
                $maxUsage = $usageCount;
                $mostUsedTag = $tag;
            }
        }

        return [
            'totalTags' => $total,
            'totalUsages' => $totalUsage,
            'averageUsage' => $total > 0 ? round($totalUsage / $total, 2) : 0.0,
            'tagsInUse' => $used,
            'unusedTags' => $total - $used,
            'mostUsedTag' => $mostUsedTag,
        ];
    }

    /**
     * Merge two tags into one.
     *
     * All articles with the source tag will be updated to use the target tag.
     * The source tag will be deleted.
     *
     * @param Tag $sourceTag The tag to merge from (will be deleted)
     * @param Tag $targetTag The tag to merge into (will keep all articles)
     */
    public function mergeTags(Tag $sourceTag, Tag $targetTag): void
    {
        // Move all articles from source to target
        foreach ($sourceTag->getArticles() as $article) {
            if (!$article->getTags()->contains($targetTag)) {
                $article->addTag($targetTag);
            }
            $article->removeTag($sourceTag);
        }

        // Recalculate usage counts
        $targetTag->setUsageCount($targetTag->getArticles()->count());

        // Remove source tag
        $this->entityManager->remove($sourceTag);
        $this->entityManager->flush();
    }

    /**
     * Find a tag by its ID.
     *
     * @param int $id The tag ID
     *
     * @return Tag|null The tag or null if not found
     */
    public function findTagById(int $id): ?Tag
    {
        return $this->tagRepository->find($id);
    }

    /**
     * Get tags related to a given tag (tags that appear together on articles).
     *
     * @param Tag $tag The tag to find related tags for
     * @param string $locale The locale for translations
     * @param int $limit Maximum number of related tags to return
     *
     * @return array<Tag> Array of related tags
     */
    public function getRelatedTags(Tag $tag, string $locale = 'ro', int $limit = 10): array
    {
        // Get all article IDs that have this tag
        $articleIds = [];
        foreach ($tag->getArticles() as $article) {
            $articleIds[] = $article->getId();
        }

        if (empty($articleIds)) {
            return [];
        }

        // Find tags that appear on the same articles, excluding the current tag
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t', 'COUNT(a.id) as co_occurrence')
            ->from(Tag::class, 't')
            ->leftJoin('t.articles', 'a')
            ->where('a.id IN (:articleIds)')
            ->andWhere('t.id != :tagId')
            ->groupBy('t.id')
            ->orderBy('co_occurrence', 'DESC')
            ->setParameter('articleIds', $articleIds)
            ->setParameter('tagId', $tag->getId())
            ->setMaxResults($limit);

        $query = $qb->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        // Extract just the Tag entities from the result
        $results = $query->getResult();
        $tags = [];
        foreach ($results as $result) {
            $tags[] = $result[0]; // First element is the Tag entity
        }

        return $tags;
    }

    /**
     * Get unused tags (tags with usageCount = 0).
     *
     * @param string $locale The locale for translations
     * @param int $limit Maximum number of tags to return
     *
     * @return array<Tag> Array of unused tags
     */
    public function getUnusedTags(string $locale = 'ro', int $limit = 50): array
    {
        $qb = $this->tagRepository->createQueryBuilder('t')
            ->where('t.usageCount = 0')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit);

        $query = $qb->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        return $query->getResult();
    }
}
