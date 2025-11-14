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
 * Article Category Change Listener.
 *
 * Automatically creates URL redirects when an article's category changes.
 * This preserves SEO value by ensuring old URLs redirect to new URLs.
 *
 * Example:
 *   Article was in "Politică" → moved to "Economie"
 *   Old URL: /politica/reforma-guvernului
 *   New URL: /economie/reforma-guvernului
 *   Result: 3 redirects created (one per locale: ro, en, ru)
 *
 * @see docs/url-structure-APPROVED.md - URL Migration & Redirect Strategy
 */
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
class ArticleCategoryChangeListener
{
    private const SUPPORTED_LOCALES = ['ro', 'en', 'ru'];

    private array $pendingRedirects = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Handle article preUpdate event.
     *
     * Detects category changes and stores them for processing after flush
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $article = $args->getObject();

        // Only process Article entities
        if (!$article instanceof Article) {
            return;
        }

        // Check if category field has changed
        if (!$args->hasChangedField('category')) {
            return;
        }

        $oldCategory = $args->getOldValue('category');
        $newCategory = $args->getNewValue('category');

        // Both categories must exist to create redirects
        if (!$oldCategory instanceof Category || !$newCategory instanceof Category) {
            return;
        }

        // Don't create redirect if categories are the same
        if ($oldCategory->getId() === $newCategory->getId()) {
            return;
        }

        $this->logger->info('Article category change detected, scheduling redirects', [
            'article_id' => $article->getId(),
            'old_category_id' => $oldCategory->getId(),
            'new_category_id' => $newCategory->getId(),
        ]);

        // Store for processing in postFlush
        $this->pendingRedirects[] = [
            'article' => $article,
            'oldCategory' => $oldCategory,
            'newCategory' => $newCategory,
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
            $article = $data['article'];
            $oldCategory = $data['oldCategory'];
            $newCategory = $data['newCategory'];

            // Create redirects for all locales
            foreach (self::SUPPORTED_LOCALES as $locale) {
                $this->createRedirectForLocale($em, $article, $oldCategory, $newCategory, $locale);
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
        Category $oldCategory,
        Category $newCategory,
        string $locale
    ): void {
        try {
            // Get translated slugs for old and new categories
            $oldCategorySlug = $this->getTranslatedSlug($em, $oldCategory, $locale);
            $newCategorySlug = $this->getTranslatedSlug($em, $newCategory, $locale);
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
            $redirect->setType('article');
            $redirect->setEntityId($article->getId());
            $redirect->setHttpStatusCode(301); // Permanent redirect

            $em->persist($redirect);

            $this->logger->info('Created redirect for article category change', [
                'old_url' => $oldUrl,
                'new_url' => $newUrl,
                'locale' => $locale,
                'article_id' => $article->getId(),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to create redirect for article category change', [
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
     * @param Article|Category $entity The entity to get slug for
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
