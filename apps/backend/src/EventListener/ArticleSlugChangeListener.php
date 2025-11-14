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
 * Article Slug Change Listener.
 *
 * Automatically creates URL redirects when an article's slug changes.
 * This preserves SEO value by ensuring old URLs redirect to new URLs.
 *
 * Example:
 *   Article slug changed: "reforma-guvernului" → "reforma-economica"
 *   Category: "Politică"
 *   Old URL: /politica/reforma-guvernului
 *   New URL: /politica/reforma-economica
 *   Result: 3 redirects created (one per locale: ro, en, ru)
 *
 * Note: This listener handles slug changes for ALL locales.
 * Gedmo Translatable stores slug translations separately.
 *
 * @see docs/url-structure-APPROVED.md - URL Migration & Redirect Strategy
 */
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
class ArticleSlugChangeListener
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
     * Detects slug changes and stores them for processing after flush
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $article = $args->getObject();

        // Only process Article entities
        if (!$article instanceof Article) {
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

        $this->logger->info('Article slug change detected, scheduling redirects', [
            'article_id' => $article->getId(),
            'old_slug' => $oldSlug,
            'new_slug' => $newSlug,
        ]);

        // Store for processing in postFlush
        $this->pendingRedirects[] = [
            'article' => $article,
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
            $article = $data['article'];
            $oldSlug = $data['oldSlug'];
            $newSlug = $data['newSlug'];

            // Create redirects for all locales
            foreach (self::SUPPORTED_LOCALES as $locale) {
                $this->createRedirectForLocale($em, $article, $oldSlug, $newSlug, $locale);
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
        string $oldSlug,
        string $newSlug,
        string $locale
    ): void {
        try {
            // Get category slug for the locale
            $category = $article->getCategory();
            if (!$category) {
                $this->logger->warning('Article has no category, skipping redirect creation', [
                    'article_id' => $article->getId(),
                    'locale' => $locale,
                ]);

                return;
            }

            $categorySlug = $this->getTranslatedSlug($em, $category, $locale);

            // Build old and new URLs
            $localePrefix = $locale === 'ro' ? '' : $locale . '/';
            $oldUrl = '/' . $localePrefix . $categorySlug . '/' . $oldSlug;
            $newUrl = '/' . $localePrefix . $categorySlug . '/' . $newSlug;

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

            $this->logger->info('Created redirect for article slug change', [
                'old_url' => $oldUrl,
                'new_url' => $newUrl,
                'locale' => $locale,
                'article_id' => $article->getId(),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to create redirect for article slug change', [
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
     * @param Category $entity The entity to get slug for
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
