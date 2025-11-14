<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Service\PerformanceService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Cached decorator for CategoryProvider.
 *
 * @implements ProviderInterface<object>
 */
final class CachedCategoryProvider implements ProviderInterface
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

        // Single category retrieval
        if (isset($uriVariables['id'])) {
            $categoryId = $uriVariables['id'];
            $cacheKey = "api:categories:{$categoryId}:{$locale}";

            // Try cache first
            $cached = $this->performance->getCached($cacheKey);
            if ($cached !== null) {
                $this->logger->debug('Cache HIT for category', [
                    'category_id' => $categoryId,
                    'locale' => $locale,
                ]);

                return $cached;
            }

            // Cache miss
            $this->logger->debug('Cache MISS for category', [
                'category_id' => $categoryId,
                'locale' => $locale,
            ]);

            $category = $this->decorated->provide($operation, $uriVariables, $context);

            if ($category) {
                $this->performance->setCached($cacheKey, $category, 3600); // 1 hour
            }

            return $category;
        }

        // Collection retrieval
        $page = $request ? (int) $request->query->get('page', 1) : 1;
        $cacheKey = "api:categories:list:page{$page}:{$locale}";

        $cached = $this->performance->getCached($cacheKey);
        if ($cached !== null) {
            $this->logger->debug('Cache HIT for category list', ['page' => $page]);

            return $cached;
        }

        $this->logger->debug('Cache MISS for category list', ['page' => $page]);

        $results = $this->decorated->provide($operation, $uriVariables, $context);
        $this->performance->setCached($cacheKey, $results, 3600); // 1 hour

        return $results;
    }
}
