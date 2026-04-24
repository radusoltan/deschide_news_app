<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Service\Cache\CacheService;
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
        private readonly CacheService $performance,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // NOTE: Manual serialization-based caching has been removed due to inability
        // to serialize Doctrine entities (which contain EntityManager references).
        //
        // Caching is now handled by Doctrine Second Level Cache (L2 Cache):
        // - Configured in config/packages/doctrine.yaml
        // - Entity-level cache annotations on Article, Category, Author, Image
        // - Automatic cache invalidation on entity updates
        // - Redis backend via doctrine.result_cache_pool
        //
        // This approach provides:
        // ✅ Automatic entity serialization (stores scalar values)
        // ✅ Built-in invalidation on persist/update/remove
        // ✅ Relationship caching (category, author, images)
        // ✅ Multi-locale support via Gedmo Translatable
        //
        // Performance impact (Phase 2E):
        // - Cache hit ratio: 14% → 85%+ (expected)
        // - API p95 latency: 4.6s → <500ms (expected)
        // - Database load: -70% (expected)

        return $this->decorated->provide($operation, $uriVariables, $context);
    }
}
