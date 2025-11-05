<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Service\PerformanceService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Cached decorator for ArticleProvider.
 * Implements caching layer for article retrieval.
 *
 * @implements ProviderInterface<object>
 */
final class CachedArticleProvider implements ProviderInterface
{
    public function __construct(
        private readonly ProviderInterface $decorated,
        private readonly PerformanceService $performance,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
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

        // Single article retrieval
        if (isset($uriVariables['id'])) {
            $articleId = $uriVariables['id'];
            $cacheKey = "api:articles:{$articleId}:{$locale}";

            // Try cache first
            $cached = $this->performance->getCached($cacheKey);
            if ($cached !== null) {
                $this->logger->debug('Cache HIT for article', [
                    'article_id' => $articleId,
                    'locale' => $locale,
                    'cache_key' => $cacheKey,
                ]);

                return $cached;
            }

            // Cache miss - fetch from database
            $this->logger->debug('Cache MISS for article', [
                'article_id' => $articleId,
                'locale' => $locale,
                'cache_key' => $cacheKey,
            ]);

            $article = $this->decorated->provide($operation, $uriVariables, $context);

            if ($article) {
                // Store in cache with 1 hour TTL
                $this->performance->setCached($cacheKey, $article, 3600);
            }

            return $article;
        }

        // Collection retrieval
        $page = 1;
        if ($request) {
            $page = (int) $request->query->get('page', 1);
        }

        // Build cache key with filters
        $filters = [];
        if ($request) {
            // Include category filter
            $categoryId = null;
            $categoryArray = $request->query->all('category');
            if (\is_array($categoryArray) && isset($categoryArray['id'])) {
                $categoryId = (int) $categoryArray['id'];
            } elseif ($request->query->has('category')) {
                $categoryValue = $request->query->get('category');
                if (is_numeric($categoryValue)) {
                    $categoryId = (int) $categoryValue;
                }
            }

            if ($categoryId) {
                $filters['category'] = $categoryId;
            }

            // Include status filter
            if ($request->query->has('status')) {
                $filters['status'] = $request->query->get('status');
            }

            // Include isFeatured filter
            if ($request->query->has('isFeatured')) {
                $filters['isFeatured'] = $request->query->get('isFeatured');
            }
        }

        $filterStr = empty($filters) ? '' : ':' . md5(json_encode($filters));
        $cacheKey = "api:articles:list:page{$page}:{$locale}{$filterStr}";

        // Try cache first
        $cached = $this->performance->getCached($cacheKey);
        if ($cached !== null) {
            $this->logger->debug('Cache HIT for article list', [
                'page' => $page,
                'locale' => $locale,
                'filters' => $filters,
                'cache_key' => $cacheKey,
            ]);

            return $cached;
        }

        // Cache miss - fetch from database
        $this->logger->debug('Cache MISS for article list', [
            'page' => $page,
            'locale' => $locale,
            'filters' => $filters,
            'cache_key' => $cacheKey,
        ]);

        $results = $this->decorated->provide($operation, $uriVariables, $context);

        // Store in cache with 5 minutes TTL
        $this->performance->setCached($cacheKey, $results, 300);

        return $results;
    }
}
