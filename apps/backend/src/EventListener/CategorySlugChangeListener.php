<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\UrlRedirect;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Exception;
use Gedmo\Translatable\TranslatableListener;
use Psr\Log\LoggerInterface;

/**
 * Category Slug Change Listener.
 *
 * Automatically creates URL redirects when a category's slug changes.
 * This is more complex than article slug changes because it affects
 * ALL articles in that category.
 *
 * Example:
 *   Category slug changed: "politica" → "politica-guvernamentala"
 *   Articles in category: 50 articles
 *   Result: 150 redirects created (50 articles × 3 locales)
 *
 * Old URLs: /politica/articol-1, /politica/articol-2, ...
 * New URLs: /politica-guvernamentala/articol-1, /politica-guvernamentala/articol-2, ...
 *
 * @see docs/url-structure-APPROVED.md - URL Migration & Redirect Strategy
 */
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
class CategorySlugChangeListener
{
    private const SUPPORTED_LOCALES = ['ro', 'en', 'ru'];

    private array $pendingRedirects = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Handle category preUpdate event.
     *
     * Detects slug changes and stores them for processing after flush
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $category = $args->getObject();

        // Only process Category entities
        if (!$category instanceof Category) {
            return;
        }

        // Check if slug field has changed
        if (!$args->hasChangedField('slug')) {
            return;
        }

        $oldSlug = $args->getOldValue('slug');
        $newSlug = $args->getNewValue('slug');

        // Both slugs must exist
        if (empty($oldSlug) || empty($newSlug)) {
            return;
        }

        // Don't create redirect if slugs are the same
        if ($oldSlug === $newSlug) {
            return;
        }

        $this->logger->info('Category slug change detected, scheduling redirects', [
            'category_id' => $category->getId(),
            'old_slug' => $oldSlug,
            'new_slug' => $newSlug,
        ]);

        // Store for processing in postFlush
        $this->pendingRedirects[] = [
            'category' => $category,
            'oldSlug' => $oldSlug,
            'newSlug' => $newSlug,
        ];
    }

    /**
     * Handle postFlush event.
     *
     * Creates redirects after the main flush is complete
     */
    public function postFlush(PostFlushEventArgs $args): void
    {
        if (empty($this->pendingRedirects)) {
            return;
        }

        $em = $args->getObjectManager();

        foreach ($this->pendingRedirects as $data) {
            $category = $data['category'];
            $oldSlug = $data['oldSlug'];
            $newSlug = $data['newSlug'];

            // Get all articles in this category
            $articles = $em->getRepository(Article::class)
                ->findBy(['category' => $category]);

            $this->logger->info('Creating redirects for category slug change', [
                'category_id' => $category->getId(),
                'article_count' => \count($articles),
                'total_redirects' => \count($articles) * \count(self::SUPPORTED_LOCALES),
            ]);

            // Create redirects for each article in all locales
            foreach ($articles as $article) {
                foreach (self::SUPPORTED_LOCALES as $locale) {
                    $this->createRedirectForLocale($em, $article, $oldSlug, $newSlug, $locale);
                }
            }
        }

        // Clear pending redirects
        $this->pendingRedirects = [];

        // Flush the new redirects
        if (!$em->getUnitOfWork()->hasPendingInsertions()) {
            return;
        }

        $em->flush();
    }

    /**
     * Create a redirect for a specific locale.
     */
    private function createRedirectForLocale(
        $em,
        Article $article,
        string $oldCategorySlug,
        string $newCategorySlug,
        string $locale
    ): void {
        try {
            // Get article slug for the locale
            $articleSlug = $this->getTranslatedSlug($em, $article, $locale);

            // Build old and new URLs
            $localePrefix = $locale === 'ro' ? '' : $locale . '/';
            $oldUrl = '/' . $localePrefix . $oldCategorySlug . '/' . $articleSlug;
            $newUrl = '/' . $localePrefix . $newCategorySlug . '/' . $articleSlug;

            // Check if redirect already exists for this old URL
            $existingRedirect = $em->getRepository(UrlRedirect::class)
                ->findOneBy(['oldUrl' => $oldUrl]);

            if ($existingRedirect) {
                $this->logger->info('Redirect already exists for old URL, updating it', [
                    'old_url' => $oldUrl,
                    'new_url' => $newUrl,
                    'locale' => $locale,
                ]);

                // Update existing redirect to point to new URL
                $existingRedirect->setNewUrl($newUrl);

                return;
            }

            // Create new redirect
            $redirect = new UrlRedirect();
            $redirect->setOldUrl($oldUrl);
            $redirect->setNewUrl($newUrl);
            $redirect->setLocale($locale);
            $redirect->setType('category'); // Mark as category-triggered redirect
            $redirect->setEntityId($article->getId());
            $redirect->setHttpStatusCode(301); // Permanent redirect

            $em->persist($redirect);

            $this->logger->info('Created redirect for category slug change', [
                'old_url' => $oldUrl,
                'new_url' => $newUrl,
                'locale' => $locale,
                'article_id' => $article->getId(),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to create redirect for category slug change', [
                'error' => $e->getMessage(),
                'locale' => $locale,
                'article_id' => $article->getId(),
            ]);
        }
    }

    /**
     * Get translated slug for an entity.
     *
     * Uses Gedmo Translatable to fetch the slug in the specified locale
     *
     * @param Article $entity The entity to get slug for
     * @param string $locale The locale (ro, en, ru)
     *
     * @return string The translated slug
     */
    private function getTranslatedSlug($em, object $entity, string $locale): string
    {
        // Get the entity's repository
        $repository = $em->getRepository($entity::class);

        // Fetch the entity with the specific locale
        $translatedEntity = $repository->createQueryBuilder('e')
            ->where('e.id = :id')
            ->setParameter('id', $entity->getId())
            ->getQuery()
            ->setHint(
                TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                $locale
            )
            ->getOneOrNullResult();

        if ($translatedEntity && method_exists($translatedEntity, 'getSlug')) {
            return $translatedEntity->getSlug() ?? '';
        }

        // Fallback to current slug if translation not found
        if (method_exists($entity, 'getSlug')) {
            return $entity->getSlug() ?? '';
        }

        return '';
    }
}
