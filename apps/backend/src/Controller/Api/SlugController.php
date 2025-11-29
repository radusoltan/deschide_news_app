<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\ArticleRepository;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\TranslatableListener;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Slug Lookup API Controller
 * Provides endpoints for looking up entities by slug with locale support.
 *
 * Sprint 1 - Backend Validation
 * Task: Day 6-7 - Slug Lookup API Endpoints
 */
#[Route('/api', name: 'api_slug_')]
class SlugController extends AbstractController
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly AuthorRepository $authorRepository,
        private readonly SerializerInterface $serializer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Get article by slug.
     *
     * @param string $slug Article slug
     * @param Request $request HTTP request (for locale)
     *
     * @return JsonResponse Article data or 404
     *
     * Example: GET /api/articles/by-slug/my-article-title?locale=ro
     */
    #[Route('/articles/by-slug/{slug}', name: 'article_by_slug', methods: ['GET'])]
    public function getArticleBySlug(string $slug, Request $request): JsonResponse
    {
        // Get locale from query parameter or Accept-Language header
        $locale = $request->query->get('locale')
            ?? $request->headers->get('Accept-Language')
            ?? 'ro';

        // Search in translations table for the given locale
        // Join with ext_translations to find the article by slug in the specific locale
        // OPTIMIZATION: Eager load all related entities to avoid N+1 queries
        $query = $this->articleRepository->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->leftJoin('a.authors', 'au')
            ->addSelect('au')
            ->leftJoin('a.articleImages', 'ai')
            ->addSelect('ai')
            ->leftJoin('ai.image', 'img')
            ->addSelect('img')
            ->leftJoin('a.tags', 't')
            ->addSelect('t')
            ->where('a.status = :status')
            ->setParameter('status', 'published');

        // If searching in default locale (ro), use direct field
        if ($locale === 'ro') {
            $query->andWhere('a.slug = :slug')
                ->setParameter('slug', $slug);
        } else {
            // For non-default locales, search in translations table
            // Note: We need to convert ID to string for comparison with foreignKey
            $query->leftJoin(
                'Gedmo\Translatable\Entity\Translation',
                't',
                'WITH',
                't.objectClass = :objectClass AND t.foreignKey = CONCAT(\'\', a.id) AND t.field = :field AND t.locale = :locale AND t.content = :slug'
            )
                ->andWhere('t.id IS NOT NULL')
                ->setParameter('objectClass', 'App\Entity\Article')
                ->setParameter('field', 'slug')
                ->setParameter('locale', $locale)
                ->setParameter('slug', $slug);
        }

        $query = $query->setMaxResults(1)->getQuery();

        // Set translatable locale hint
        $query->setHint(
            TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );
        $query->setHint(
            TranslatableListener::HINT_INNER_JOIN,
            false
        );

        $article = $query->getOneOrNullResult();

        if ($article) {
            // Set translatable locale on entity and refresh to load translations
            $article->setTranslatableLocale($locale);
            $this->entityManager->refresh($article);

            // Refresh related entities with same locale
            if ($article->getCategory()) {
                $article->getCategory()->setTranslatableLocale($locale);
                $this->entityManager->refresh($article->getCategory());
            }
        }

        if (!$article) {
            return new JsonResponse([
                '@context' => '/api/contexts/Error',
                '@type' => 'hydra:Error',
                'hydra:title' => 'An error occurred',
                'hydra:description' => \sprintf('Article with slug "%s" not found', $slug),
                'status' => 404,
            ], 404);
        }

        // Serialize with article:read and article:detail groups to include content
        $json = $this->serializer->serialize($article, 'json', [
            'groups' => ['article:read', 'article:detail'],
            'enable_max_depth' => true,
        ]);

        return new JsonResponse($json, 200, [], true);
    }

    /**
     * Get category by slug.
     *
     * @param string $slug Category slug
     * @param Request $request HTTP request (for locale)
     *
     * @return JsonResponse Category data or 404
     *
     * Example: GET /api/categories/by-slug/politica?locale=ro
     */
    #[Route('/categories/by-slug/{slug}', name: 'category_by_slug', methods: ['GET'])]
    public function getCategoryBySlug(string $slug, Request $request): JsonResponse
    {
        // Get locale from query parameter or Accept-Language header
        $locale = $request->query->get('locale')
            ?? $request->headers->get('Accept-Language')
            ?? 'ro';

        // Search in translations table for the given locale
        $query = $this->categoryRepository->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', 'active');

        // If searching in default locale (ro), use direct field
        if ($locale === 'ro') {
            $query->andWhere('c.slug = :slug')
                ->setParameter('slug', $slug);
        } else {
            // For non-default locales, search in translations table
            // Note: We need to convert ID to string for comparison with foreignKey
            $query->leftJoin(
                'Gedmo\Translatable\Entity\Translation',
                't',
                'WITH',
                't.objectClass = :objectClass AND t.foreignKey = CONCAT(\'\', c.id) AND t.field = :field AND t.locale = :locale AND t.content = :slug'
            )
                ->andWhere('t.id IS NOT NULL')
                ->setParameter('objectClass', 'App\Entity\Category')
                ->setParameter('field', 'slug')
                ->setParameter('locale', $locale)
                ->setParameter('slug', $slug);
        }

        $query = $query->setMaxResults(1)->getQuery();

        // Set translatable locale hint
        $query->setHint(
            TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );
        $query->setHint(
            TranslatableListener::HINT_INNER_JOIN,
            false
        );

        $category = $query->getOneOrNullResult();

        if ($category) {
            // Set translatable locale on entity and refresh to load translations
            $category->setTranslatableLocale($locale);
            $this->entityManager->refresh($category);
        }

        if (!$category) {
            return new JsonResponse([
                '@context' => '/api/contexts/Error',
                '@type' => 'hydra:Error',
                'hydra:title' => 'An error occurred',
                'hydra:description' => \sprintf('Category with slug "%s" not found', $slug),
                'status' => 404,
            ], 404);
        }

        // Serialize with category:read group
        $json = $this->serializer->serialize($category, 'json', [
            'groups' => ['category:read'],
        ]);

        return new JsonResponse($json, 200, [], true);
    }

    /**
     * Get author by slug.
     *
     * @param string $slug Author slug
     *
     * @return JsonResponse Author data or 404
     *
     * Note: Authors are not translatable, so no locale parameter needed
     * Example: GET /api/authors/by-slug/john-doe
     */
    #[Route('/authors/by-slug/{slug}', name: 'author_by_slug', methods: ['GET'])]
    public function getAuthorBySlug(string $slug): JsonResponse
    {
        $author = $this->authorRepository->findOneBy([
            'slug' => $slug,
        ]);

        if (!$author) {
            return new JsonResponse([
                '@context' => '/api/contexts/Error',
                '@type' => 'hydra:Error',
                'hydra:title' => 'An error occurred',
                'hydra:description' => \sprintf('Author with slug "%s" not found', $slug),
                'status' => 404,
            ], 404);
        }

        // Serialize with author:read group
        $json = $this->serializer->serialize($author, 'json', [
            'groups' => ['author:read'],
        ]);

        return new JsonResponse($json, 200, [], true);
    }
}
