<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\UrlRedirect;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\UrlRedirectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Slug Lookup Service.
 *
 * Provides fast slug lookup using Elasticsearch (when available)
 * with fallback to database queries. Also handles redirect checking.
 *
 * Features:
 * - Fast article lookup by category slug + article slug
 * - Redirect chain resolution
 * - Elasticsearch-first approach with DB fallback
 * - Multi-locale support (ro, en, ru)
 *
 * @see docs/url-structure-APPROVED.md - URL Structure & Lookup
 */
class SlugLookupService
{
    private const SUPPORTED_LOCALES = ['ro', 'en', 'ru'];

    public function __construct(
        private ElasticService $elasticService,
        private ArticleRepository $articleRepository,
        private CategoryRepository $categoryRepository,
        private UrlRedirectRepository $urlRedirectRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Find article by category slug and article slug.
     *
     * @param string $categorySlug The category slug
     * @param string $articleSlug The article slug
     * @param string $locale The locale (ro, en, ru)
     *
     * @return array{article: ?Article, redirect: ?UrlRedirect, found_via: string} Result with article and metadata
     */
    public function findArticleBySlug(string $categorySlug, string $articleSlug, string $locale = 'ro'): array
    {
        // Validate locale
        if (!\in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = 'ro';
        }

        $this->logger->info('Slug lookup request', [
            'category_slug' => $categorySlug,
            'article_slug' => $articleSlug,
            'locale' => $locale,
        ]);

        // Try Elasticsearch first (fast path)
        if ($this->elasticService->isEnabled()) {
            $result = $this->findViaElasticsearch($categorySlug, $articleSlug, $locale);
            if ($result['article']) {
                $this->logger->info('Article found via Elasticsearch', [
                    'article_id' => $result['article']->getId(),
                ]);

                return $result;
            }
        }

        // Fallback to database query
        $result = $this->findViaDatabase($categorySlug, $articleSlug, $locale);
        if ($result['article']) {
            $this->logger->info('Article found via Database', [
                'article_id' => $result['article']->getId(),
            ]);

            return $result;
        }

        // Check if there's a redirect for this URL
        $localePrefix = $locale === 'ro' ? '' : $locale . '/';
        $requestedUrl = '/' . $localePrefix . $categorySlug . '/' . $articleSlug;

        $redirect = $this->urlRedirectRepository->findByOldUrl($requestedUrl);
        if ($redirect) {
            $this->logger->info('Redirect found for URL', [
                'old_url' => $requestedUrl,
                'new_url' => $redirect->getNewUrl(),
            ]);

            // Increment hit count
            $redirect->incrementHitCount();
            $this->entityManager->flush();

            return [
                'article' => null,
                'redirect' => $redirect,
                'found_via' => 'redirect',
            ];
        }

        // Not found
        $this->logger->warning('Article not found', [
            'category_slug' => $categorySlug,
            'article_slug' => $articleSlug,
            'locale' => $locale,
        ]);

        return [
            'article' => null,
            'redirect' => null,
            'found_via' => 'not_found',
        ];
    }

    /**
     * Check if a slug is available (not used).
     *
     * @param string $slug The slug to check
     * @param string $type The type (article, category)
     * @param string $locale The locale
     * @param int|null $excludeId Exclude this ID from check (for updates)
     *
     * @return bool True if slug is available
     */
    public function isSlugAvailable(string $slug, string $type, string $locale = 'ro', ?int $excludeId = null): bool
    {
        if ($type === 'article') {
            $qb = $this->articleRepository->createQueryBuilder('a')
                ->where('a.slug = :slug')
                ->setParameter('slug', $slug);

            if ($excludeId) {
                $qb->andWhere('a.id != :id')
                    ->setParameter('id', $excludeId);
            }

            $count = (int) $qb->select('COUNT(a.id)')
                ->getQuery()
                ->setHint(
                    \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                    $locale
                )
                ->getSingleScalarResult();

            return $count === 0;
        }

        if ($type === 'category') {
            $qb = $this->categoryRepository->createQueryBuilder('c')
                ->where('c.slug = :slug')
                ->setParameter('slug', $slug);

            if ($excludeId) {
                $qb->andWhere('c.id != :id')
                    ->setParameter('id', $excludeId);
            }

            $count = (int) $qb->select('COUNT(c.id)')
                ->getQuery()
                ->setHint(
                    \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                    $locale
                )
                ->getSingleScalarResult();

            return $count === 0;
        }

        return false;
    }

    /**
     * Get redirect chain for a URL.
     *
     * Resolves redirect chains (A → B → C)
     *
     * @param string $url The URL to check
     *
     * @return array{redirects: UrlRedirect[], final_url: ?string, chain_length: int, circular: bool}
     */
    public function getRedirectChain(string $url): array
    {
        $redirects = [];
        $currentUrl = $url;
        $maxDepth = 10; // Prevent infinite loops
        $depth = 0;
        $circular = false;

        while ($depth < $maxDepth) {
            $redirect = $this->urlRedirectRepository->findByOldUrl($currentUrl);

            if (!$redirect) {
                break;
            }

            $redirects[] = $redirect;
            $currentUrl = $redirect->getNewUrl();
            ++$depth;

            // Check for circular redirect
            if ($currentUrl === $url) {
                $circular = true;
                $this->logger->warning('Circular redirect detected', [
                    'url' => $url,
                    'chain_length' => \count($redirects),
                ]);
                break;
            }
        }

        return [
            'redirects' => $redirects,
            'final_url' => $currentUrl !== $url ? $currentUrl : null,
            'chain_length' => \count($redirects),
            'circular' => $circular,
        ];
    }

    /**
     * Get list of reserved slugs.
     *
     * @return array<string> List of reserved slugs
     */
    public function getReservedSlugs(): array
    {
        return \App\Validator\ReservedSlug::RESERVED_SLUGS;
    }

    /**
     * Check if a slug is reserved.
     *
     * @param string $slug The slug to check
     *
     * @return bool True if slug is reserved
     */
    public function isSlugReserved(string $slug): bool
    {
        $normalizedSlug = strtolower(trim($slug));

        return \in_array($normalizedSlug, $this->getReservedSlugs(), true);
    }

    /**
     * Bulk validate multiple slugs.
     *
     * @param array $slugs Array of slugs to validate
     * @param string $type The type (article, category)
     * @param string $locale The locale
     *
     * @return array Results for each slug
     */
    public function bulkValidate(array $slugs, string $type, string $locale = 'ro'): array
    {
        $results = [];

        foreach ($slugs as $slug) {
            $results[$slug] = [
                'slug' => $slug,
                'available' => $this->isSlugAvailable($slug, $type, $locale),
                'reserved' => $this->isSlugReserved($slug),
                'valid' => !$this->isSlugReserved($slug) && $this->isSlugAvailable($slug, $type, $locale),
            ];
        }

        return $results;
    }

    /**
     * Generate slug suggestions based on a title.
     *
     * @param string $title The title to generate slug from
     * @param string $type The type (article, category)
     * @param string $locale The locale
     * @param int $maxSuggestions Maximum number of suggestions
     *
     * @return array Array of available slug suggestions
     */
    public function generateSlugSuggestions(string $title, string $type, string $locale = 'ro', int $maxSuggestions = 5): array
    {
        $baseSlug = $this->slugify($title);
        $suggestions = [];

        // First suggestion is the base slug
        if (!$this->isSlugReserved($baseSlug) && $this->isSlugAvailable($baseSlug, $type, $locale)) {
            $suggestions[] = [
                'slug' => $baseSlug,
                'available' => true,
                'reserved' => false,
            ];
        } else {
            $suggestions[] = [
                'slug' => $baseSlug,
                'available' => false,
                'reserved' => $this->isSlugReserved($baseSlug),
                'reason' => $this->isSlugReserved($baseSlug) ? 'reserved' : 'taken',
            ];
        }

        // Generate numbered alternatives
        $counter = 1;
        while (\count(array_filter($suggestions, fn ($s) => $s['available'] ?? false)) < $maxSuggestions && $counter <= 20) {
            $alternativeSlug = $baseSlug . '-' . $counter;

            if (!$this->isSlugReserved($alternativeSlug) && $this->isSlugAvailable($alternativeSlug, $type, $locale)) {
                $suggestions[] = [
                    'slug' => $alternativeSlug,
                    'available' => true,
                    'reserved' => false,
                ];
            }

            ++$counter;
        }

        // Add timestamp-based suggestion as fallback
        if (\count(array_filter($suggestions, fn ($s) => $s['available'] ?? false)) < 2) {
            $timestampSlug = $baseSlug . '-' . date('ymd-his');
            $suggestions[] = [
                'slug' => $timestampSlug,
                'available' => true,
                'reserved' => false,
                'type' => 'timestamp',
            ];
        }

        return \array_slice($suggestions, 0, $maxSuggestions);
    }

    /**
     * Find article via Elasticsearch.
     */
    private function findViaElasticsearch(string $categorySlug, string $articleSlug, string $locale): array
    {
        try {
            // Search for article with matching slug and category slug
            $results = $this->elasticService->search('', 0, 1, [
                'slug' => $articleSlug,
                'category.slug' => $categorySlug,
                'locale' => $locale,
            ], [], $locale);

            if (!empty($results['hits'])) {
                $hit = $results['hits'][0];
                $articleId = $hit['_source']['id'] ?? null;

                if ($articleId) {
                    // Load full article from database
                    $article = $this->articleRepository->find($articleId);
                    if ($article) {
                        return [
                            'article' => $article,
                            'redirect' => null,
                            'found_via' => 'elasticsearch',
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            $this->logger->error('Elasticsearch lookup failed', [
                'error' => $e->getMessage(),
                'category_slug' => $categorySlug,
                'article_slug' => $articleSlug,
            ]);
        }

        return [
            'article' => null,
            'redirect' => null,
            'found_via' => 'not_found',
        ];
    }

    /**
     * Find article via database query.
     */
    private function findViaDatabase(string $categorySlug, string $articleSlug, string $locale): array
    {
        try {
            // Find category first
            $category = $this->categoryRepository->createQueryBuilder('c')
                ->where('c.slug = :slug')
                ->setParameter('slug', $categorySlug)
                ->getQuery()
                ->setHint(
                    \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                    $locale
                )
                ->getOneOrNullResult();

            if (!$category) {
                return [
                    'article' => null,
                    'redirect' => null,
                    'found_via' => 'not_found',
                ];
            }

            // Find article in that category
            $article = $this->articleRepository->createQueryBuilder('a')
                ->where('a.slug = :slug')
                ->andWhere('a.category = :category')
                ->setParameter('slug', $articleSlug)
                ->setParameter('category', $category)
                ->getQuery()
                ->setHint(
                    \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                    $locale
                )
                ->getOneOrNullResult();

            if ($article) {
                return [
                    'article' => $article,
                    'redirect' => null,
                    'found_via' => 'database',
                ];
            }
        } catch (Exception $e) {
            $this->logger->error('Database lookup failed', [
                'error' => $e->getMessage(),
                'category_slug' => $categorySlug,
                'article_slug' => $articleSlug,
            ]);
        }

        return [
            'article' => null,
            'redirect' => null,
            'found_via' => 'not_found',
        ];
    }

    /**
     * Simple slugify function.
     *
     * @param string $text Text to slugify
     *
     * @return string Slugified text
     */
    private function slugify(string $text): string
    {
        // Use Behat Transliterator if available
        if (class_exists('\Behat\Transliterator\Transliterator')) {
            return \Behat\Transliterator\Transliterator::transliterate($text);
        }

        // Fallback simple slugify
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');

        return $text;
    }
}
