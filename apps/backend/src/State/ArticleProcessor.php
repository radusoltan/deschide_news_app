<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use App\Entity\Tag;
use App\Message\CheckOrphanedTagsMessage;
use App\Service\PerformanceService;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<Article>
 */
final class ArticleProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly MessageBusInterface $messageBus,
        private readonly PerformanceService $performanceService,
        private readonly CacheItemPoolInterface $doctrineResultCachePool
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Article
    {
        $request = $this->requestStack->getCurrentRequest();
        $locale = $request?->headers->get('Accept-Language', 'ro') ?? 'ro';

        // Extract just the language code
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }
        if (str_contains($locale, ',')) {
            $locale = explode(',', $locale)[0];
        }

        // Handle DELETE operation
        if ($operation instanceof DeleteOperationInterface) {
            if ($data instanceof Article) {
                $articleId = $data->getId();

                // Collect tag IDs before deletion
                $tagIds = [];
                foreach ($data->getTags() as $tag) {
                    $tagIds[] = $tag->getId();
                    $tag->setUsageCount(max(0, $tag->getUsageCount() - 1));
                }

                $this->entityManager->remove($data);
                $this->entityManager->flush();

                // Invalidate article cache after DELETE
                if ($articleId) {
                    $this->invalidateArticleCache($articleId);
                }

                // Dispatch async message to check for orphaned tags
                if (!empty($tagIds)) {
                    $this->messageBus->dispatch(new CheckOrphanedTagsMessage($tagIds));
                }
            }

            return null;
        }

        // For POST and PUT operations
        if ($data instanceof Article) {
            // Check if this is an update (PUT) by looking at URI variables
            $isUpdate = isset($uriVariables['id']);

            if ($isUpdate) {
                // Load existing entity for updates
                $repository = $this->entityManager->getRepository(Article::class);
                $existingEntity = $repository->find($uriVariables['id']);

                if (!$existingEntity) {
                    throw new RuntimeException('Article not found');
                }

                // CRITICAL: For Gedmo Translatable to work correctly:
                // 1. Set the locale on the entity BEFORE making changes
                // 2. DO NOT refresh before updating - it clears change tracking
                // 3. For default locale (ro), update entity fields directly
                // 4. For non-default locales (en, ru), explicitly persist translations

                // Set translatable locale on the entity
                $existingEntity->setTranslatableLocale($locale);

                // Update translatable fields
                // These changes will be tracked by Doctrine UnitOfWork
                if ($data->getTitle()) {
                    $existingEntity->setTitle($data->getTitle());
                }
                if ($data->getLead() !== null) {
                    $existingEntity->setLead($data->getLead());
                }
                if ($data->getContent() !== null) {
                    $existingEntity->setContent($data->getContent());
                }

                // Update non-translatable fields
                $existingEntity->setStatus($data->getStatus());
                $existingEntity->setBadge($data->getBadge());
                $existingEntity->setIsFeatured($data->isFeatured());

                // Update publishAt if provided
                if ($data->getPublishAt() !== null) {
                    $existingEntity->setPublishAt($data->getPublishAt());
                }

                // Update category if provided (get managed entity)
                if ($data->getCategory()) {
                    $managedCategory = $this->getManagedCategory($data->getCategory());
                    $existingEntity->setCategory($managedCategory);
                }

                // Sync authors collection (using managed entities)
                // Get IDs of incoming authors
                $incomingAuthorIds = [];
                foreach ($data->getAuthors() as $author) {
                    if ($author->getId()) {
                        $incomingAuthorIds[] = $author->getId();
                    }
                }
                // Remove authors that are not in the new list
                foreach ($existingEntity->getAuthors() as $author) {
                    if (!\in_array($author->getId(), $incomingAuthorIds, true)) {
                        $existingEntity->removeAuthor($author);
                    }
                }
                // Add new authors (get managed entities)
                $existingAuthorIds = [];
                foreach ($existingEntity->getAuthors() as $author) {
                    $existingAuthorIds[] = $author->getId();
                }
                foreach ($data->getAuthors() as $author) {
                    if (!\in_array($author->getId(), $existingAuthorIds, true)) {
                        $managedAuthor = $this->getManagedAuthor($author);
                        if ($managedAuthor) {
                            $existingEntity->addAuthor($managedAuthor);
                        }
                    }
                }

                // Sync related articles collection
                // Remove related articles that are not in the new list
                foreach ($existingEntity->getRelatedArticles() as $relatedArticle) {
                    if (!$data->getRelatedArticles()->contains($relatedArticle)) {
                        $existingEntity->removeRelatedArticle($relatedArticle);
                    }
                }
                // Add new related articles
                foreach ($data->getRelatedArticles() as $relatedArticle) {
                    if (!$existingEntity->getRelatedArticles()->contains($relatedArticle)) {
                        $existingEntity->addRelatedArticle($relatedArticle);
                    }
                }

                // Sync tags collection (using managed entities)
                // Get IDs of incoming tags
                $incomingTagIds = [];
                foreach ($data->getTags() as $tag) {
                    if ($tag->getId()) {
                        $incomingTagIds[] = $tag->getId();
                    }
                }
                // Remove tags that are not in the new list and decrement their usage count
                foreach ($existingEntity->getTags() as $tag) {
                    if (!\in_array($tag->getId(), $incomingTagIds, true)) {
                        $existingEntity->removeTag($tag);
                        $tag->setUsageCount(max(0, $tag->getUsageCount() - 1));
                    }
                }
                // Add new tags (get managed entities) and increment their usage count
                $existingTagIds = [];
                foreach ($existingEntity->getTags() as $tag) {
                    $existingTagIds[] = $tag->getId();
                }
                foreach ($data->getTags() as $tag) {
                    if (!\in_array($tag->getId(), $existingTagIds, true)) {
                        $managedTag = $this->getManagedTag($tag);
                        if ($managedTag) {
                            $existingEntity->addTag($managedTag);
                            $managedTag->setUsageCount($managedTag->getUsageCount() + 1);
                        }
                    }
                }

                // Use existing entity instead of deserialized one
                $data = $existingEntity;
            }

            $isNew = !$data->getId();

            if ($isNew) {
                // CREATE: New entity - always save in default locale
                $data->setTranslatableLocale('ro');

                // CRITICAL: Get managed entities for relations before persist
                // The deserialized entity has detached relations that will cause "new entity found" errors
                if ($data->getCategory()) {
                    $managedCategory = $this->getManagedCategory($data->getCategory());
                    $data->setCategory($managedCategory);
                }

                // Handle authors collection
                $managedAuthors = [];
                foreach ($data->getAuthors() as $author) {
                    $managedAuthors[] = $this->getManagedAuthor($author);
                }
                // Clear and re-add with managed entities
                foreach ($data->getAuthors()->toArray() as $author) {
                    $data->removeAuthor($author);
                }
                foreach ($managedAuthors as $managedAuthor) {
                    if ($managedAuthor) {
                        $data->addAuthor($managedAuthor);
                    }
                }

                // Handle tags collection
                $managedTags = [];
                foreach ($data->getTags() as $tag) {
                    $managedTags[] = $this->getManagedTag($tag);
                }
                // Clear and re-add with managed entities
                foreach ($data->getTags()->toArray() as $tag) {
                    $data->removeTag($tag);
                }
                foreach ($managedTags as $managedTag) {
                    if ($managedTag) {
                        $data->addTag($managedTag);
                    }
                }

                $this->entityManager->persist($data);
                $this->entityManager->flush();

                // Increment usage count for all tags on new article
                foreach ($data->getTags() as $tag) {
                    $tag->setUsageCount($tag->getUsageCount() + 1);
                }
                $this->entityManager->flush();

                // If created with non-default locale, also add translation
                if ($locale !== 'ro') {
                    $this->addTranslation($data, $locale);
                }
            } else {
                // UPDATE: Existing entity
                // For default locale (ro), changes are tracked automatically
                // For non-default locales, we need to explicitly save translations
                if ($locale === 'ro') {
                    // Default locale - flush changes directly
                    $this->entityManager->flush();
                } else {
                    // Non-default locale - save as translation
                    // First flush any non-translatable field changes
                    $this->entityManager->flush();

                    // Then persist translatable fields as translations
                    /** @var TranslationRepository $translationRepo */
                    $translationRepo = $this->entityManager->getRepository('Gedmo\Translatable\Entity\Translation');

                    if ($data->getTitle()) {
                        $translationRepo->translate($data, 'title', $locale, $data->getTitle());
                    }

                    if ($data->getLead()) {
                        $translationRepo->translate($data, 'lead', $locale, $data->getLead());
                    }

                    if ($data->getContent()) {
                        $translationRepo->translate($data, 'content', $locale, $data->getContent());
                    }

                    // Flush translations
                    $this->entityManager->flush();
                }

                // NOTE: Do NOT call refresh() here!
                // Gedmo Translatable stores translations separately, and refresh() would
                // reload the entity from DB with the old values before Translatable had
                // a chance to update the main entity fields.

                // Invalidate article cache after UPDATE
                if ($data->getId()) {
                    $this->invalidateArticleCache($data->getId());
                }
            }

            return $data;
        }

        return null;
    }

    /**
     * Invalidate article cache for all locales (individual + list caches)
     * Clears both PerformanceService cache AND Doctrine Result Cache.
     */
    private function invalidateArticleCache(int $articleId): void
    {
        // 1. Clear PerformanceService Redis cache (used by CachedArticleProvider)
        $this->performanceService->invalidateArticle($articleId);

        // 2. Clear Doctrine Result Cache (used by ArticleProvider.enableResultCache())
        // Clear individual article cache for all locales
        $locales = ['ro', 'en', 'ru'];
        foreach ($locales as $locale) {
            $cacheKey = \sprintf('article_%d_%s', $articleId, $locale);
            $this->doctrineResultCachePool->deleteItem($cacheKey);
        }

        // 3. Clear all article list caches (they contain the article)
        // Unfortunately Doctrine cache pool doesn't support pattern deletion,
        // so we clear specific known patterns
        $this->clearArticleListCaches();
    }

    /**
     * Clear article list caches for all locales and common filter combinations.
     */
    private function clearArticleListCaches(): void
    {
        $locales = ['ro', 'en', 'ru'];
        $itemsPerPage = [10, 20, 30, 50, 100];

        foreach ($locales as $locale) {
            for ($page = 1; $page <= 10; ++$page) {
                foreach ($itemsPerPage as $ipp) {
                    // Clear common filter combinations
                    $patterns = [
                        \sprintf('articles_list_%s_p%d_ipp%d_%s_all_all', $locale, $page, $ipp, md5('')),
                        \sprintf('articles_list_%s_p%d_ipp%d_%s_new_all', $locale, $page, $ipp, md5('')),
                        \sprintf('articles_list_%s_p%d_ipp%d_%s_published_all', $locale, $page, $ipp, md5('')),
                    ];
                    foreach ($patterns as $cacheKey) {
                        $this->doctrineResultCachePool->deleteItem($cacheKey);
                    }
                }
            }
        }
    }

    private function addTranslation(Article $article, string $locale): void
    {
        /** @var TranslationRepository $translationRepo */
        $translationRepo = $this->entityManager->getRepository('Gedmo\Translatable\Entity\Translation');

        if ($article->getTitle()) {
            $translationRepo->translate($article, 'title', $locale, $article->getTitle());
        }

        if ($article->getLead()) {
            $translationRepo->translate($article, 'lead', $locale, $article->getLead());
        }

        if ($article->getContent()) {
            $translationRepo->translate($article, 'content', $locale, $article->getContent());
        }

        $this->entityManager->flush();
    }

    /**
     * Get managed Category entity from database.
     */
    private function getManagedCategory(Category $category): ?Category
    {
        if (!$category->getId()) {
            return null;
        }

        return $this->entityManager->getRepository(Category::class)->find($category->getId());
    }

    /**
     * Get managed Author entity from database.
     */
    private function getManagedAuthor(Author $author): ?Author
    {
        if (!$author->getId()) {
            return null;
        }

        return $this->entityManager->getRepository(Author::class)->find($author->getId());
    }

    /**
     * Get managed Tag entity from database.
     */
    private function getManagedTag(Tag $tag): ?Tag
    {
        if (!$tag->getId()) {
            return null;
        }

        return $this->entityManager->getRepository(Tag::class)->find($tag->getId());
    }
}
