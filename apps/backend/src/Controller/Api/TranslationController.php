<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\TranslatableListener;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Translation API Controller.
 *
 * Provides endpoints for fetching all locale translations of entities.
 * This enables proper language switching with translated slugs in the frontend.
 *
 * Sprint 2 - Frontend Integration Enhancement
 * Task: Backend Translation Endpoints
 */
#[Route('/api', name: 'api_translation_')]
class TranslationController extends AbstractController
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Get all locale translations for an article.
     *
     * Returns the article slug and category slug in all available locales (ro, en, ru)
     *
     * @param int $id Article ID
     *
     * @return JsonResponse
     *
     * Success Response (200):
     * {
     *   "success": true,
     *   "article_id": 123,
     *   "translations": {
     *     "ro": {
     *       "article_slug": "reforma-guvernului",
     *       "category_slug": "politica",
     *       "category_id": 5
     *     },
     *     "en": {
     *       "article_slug": "government-reform",
     *       "category_slug": "politics",
     *       "category_id": 5
     *     },
     *     "ru": {
     *       "article_slug": "reforma-pravitelstva",
     *       "category_slug": "politika",
     *       "category_id": 5
     *     }
     *   }
     * }
     *
     * Error Response (404):
     * {
     *   "success": false,
     *   "error": "Article not found",
     *   "article_id": 123
     * }
     */
    #[Route('/articles/{id}/translations', name: 'article_translations', methods: ['GET'])]
    public function getArticleTranslations(int $id): JsonResponse
    {
        // Available locales
        $locales = ['ro', 'en', 'ru'];
        $translations = [];

        // Fetch article in each locale
        foreach ($locales as $locale) {
            // Create query to fetch article with category
            $query = $this->articleRepository->createQueryBuilder('a')
                ->leftJoin('a.category', 'c')
                ->addSelect('c')
                ->where('a.id = :id')
                ->setParameter('id', $id)
                ->getQuery();

            // Set translatable locale hint
            $query->setHint(TranslatableListener::HINT_TRANSLATABLE_LOCALE, $locale);
            $query->setHint(TranslatableListener::HINT_INNER_JOIN, false);

            $article = $query->getOneOrNullResult();

            if (!$article) {
                // Article doesn't exist
                if ($locale === 'ro') {
                    // If not found in default locale, article doesn't exist
                    return $this->json([
                        'success' => false,
                        'error' => 'Article not found',
                        'article_id' => $id,
                    ], Response::HTTP_NOT_FOUND);
                }
                // Skip this locale if article not found
                continue;
            }

            // Refresh entity to load translations
            $article->setTranslatableLocale($locale);
            $this->entityManager->refresh($article);

            // Refresh category as well
            $category = $article->getCategory();
            if ($category) {
                $category->setTranslatableLocale($locale);
                $this->entityManager->refresh($category);
            }

            // Store translation
            $translations[$locale] = [
                'article_slug' => $article->getSlug(),
                'category_slug' => $category?->getSlug(),
                'category_id' => $category?->getId(),
            ];
        }

        return $this->json([
            'success' => true,
            'article_id' => $id,
            'translations' => $translations,
        ]);
    }

    /**
     * Get all locale translations for a category.
     *
     * Returns the category slug in all available locales (ro, en, ru)
     *
     * @param int $id Category ID
     *
     * @return JsonResponse
     *
     * Success Response (200):
     * {
     *   "success": true,
     *   "category_id": 5,
     *   "translations": {
     *     "ro": {
     *       "category_slug": "politica"
     *     },
     *     "en": {
     *       "category_slug": "politics"
     *     },
     *     "ru": {
     *       "category_slug": "politika"
     *     }
     *   }
     * }
     *
     * Error Response (404):
     * {
     *   "success": false,
     *   "error": "Category not found",
     *   "category_id": 5
     * }
     */
    #[Route('/categories/{id}/translations', name: 'category_translations', methods: ['GET'])]
    public function getCategoryTranslations(int $id): JsonResponse
    {
        // Available locales
        $locales = ['ro', 'en', 'ru'];
        $translations = [];

        // Fetch category in each locale
        foreach ($locales as $locale) {
            // Create query to fetch category
            $query = $this->categoryRepository->createQueryBuilder('c')
                ->where('c.id = :id')
                ->setParameter('id', $id)
                ->getQuery();

            // Set translatable locale hint
            $query->setHint(TranslatableListener::HINT_TRANSLATABLE_LOCALE, $locale);
            $query->setHint(TranslatableListener::HINT_INNER_JOIN, false);

            $category = $query->getOneOrNullResult();

            if (!$category) {
                // Category doesn't exist
                if ($locale === 'ro') {
                    // If not found in default locale, category doesn't exist
                    return $this->json([
                        'success' => false,
                        'error' => 'Category not found',
                        'category_id' => $id,
                    ], Response::HTTP_NOT_FOUND);
                }
                // Skip this locale if category not found
                continue;
            }

            // Refresh entity to load translations
            $category->setTranslatableLocale($locale);
            $this->entityManager->refresh($category);

            // Store translation
            $translations[$locale] = [
                'category_slug' => $category->getSlug(),
            ];
        }

        return $this->json([
            'success' => true,
            'category_id' => $id,
            'translations' => $translations,
        ]);
    }

    /**
     * Get all locale translations for an author.
     *
     * Note: Authors are not translatable, so this returns the same slug for all locales
     * This endpoint exists for API consistency
     *
     * @param int $id Author ID
     */
    #[Route('/authors/{id}/translations', name: 'author_translations', methods: ['GET'])]
    public function getAuthorTranslations(int $id): JsonResponse
    {
        $author = $this->entityManager->getRepository(\App\Entity\Author::class)->find($id);

        if (!$author) {
            return $this->json([
                'success' => false,
                'error' => 'Author not found',
                'author_id' => $id,
            ], Response::HTTP_NOT_FOUND);
        }

        // Authors are not translatable, return same slug for all locales
        $slug = $author->getSlug();
        $translations = [
            'ro' => ['author_slug' => $slug],
            'en' => ['author_slug' => $slug],
            'ru' => ['author_slug' => $slug],
        ];

        return $this->json([
            'success' => true,
            'author_id' => $id,
            'translations' => $translations,
            'note' => 'Authors are not translatable - same slug used for all locales',
        ]);
    }
}
