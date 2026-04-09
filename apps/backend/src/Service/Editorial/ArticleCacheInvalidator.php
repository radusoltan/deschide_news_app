<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Service\Cache\CacheService;
use Psr\Cache\CacheItemPoolInterface;

class ArticleCacheInvalidator
{
    public function __construct(
        private readonly CacheService $cacheService,
        private readonly CacheItemPoolInterface $doctrineResultCachePool,
    ) {
    }

    public function invalidate(int $articleId): void
    {
        $this->cacheService->invalidateArticle($articleId);

        $locales = ['ro', 'en', 'ru'];
        foreach ($locales as $locale) {
            $cacheKey = \sprintf('article_%d_%s', $articleId, $locale);
            $this->doctrineResultCachePool->deleteItem($cacheKey);
        }

        $this->clearArticleListCaches();
    }

    private function clearArticleListCaches(): void
    {
        $locales = ['ro', 'en', 'ru'];
        $itemsPerPage = [10, 20, 30, 50, 100];

        foreach ($locales as $locale) {
            for ($page = 1; $page <= 10; ++$page) {
                foreach ($itemsPerPage as $ipp) {
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
}
