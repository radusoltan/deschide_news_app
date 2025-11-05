/**
 * Slug Resolver Service
 * Resolves article slugs with caching and error handling
 */

import { lookupArticle, checkRedirect } from '../api/slug-service';
import { slugCache, articleCache, createCacheKey } from './cache-manager';
import type { Locale, SlugLookupResponse } from '../types/slug';

/**
 * Article resolution result
 */
export interface ArticleResolutionResult {
  success: boolean;
  articleId?: number;
  redirect?: {
    url: string;
    statusCode: number;
  };
  error?: string;
  cached?: boolean;
}

/**
 * Resolve article by category and article slugs
 * Uses caching to reduce API calls
 */
export async function resolveArticle(
  categorySlug: string,
  articleSlug: string,
  locale: Locale
): Promise<ArticleResolutionResult> {
  // Create cache key
  const cacheKey = createCacheKey('article', locale, categorySlug, articleSlug);

  // Check cache first
  const cached = slugCache.get(cacheKey);
  if (cached) {
    return { ...(cached as ArticleResolutionResult), cached: true };
  }

  try {
    // Call slug lookup API
    const result: SlugLookupResponse = await lookupArticle(
      categorySlug,
      articleSlug,
      locale
    );

    // Handle successful lookup
    if (result.success && result.data) {
      const resolution: ArticleResolutionResult = {
        success: true,
        articleId: result.data.article_id,
        cached: false,
      };

      // Cache result for 5 minutes
      slugCache.set(cacheKey, resolution, 300000);

      return resolution;
    }

    // Handle redirect
    if (result.redirect) {
      const resolution: ArticleResolutionResult = {
        success: false,
        redirect: {
          url: result.redirect.new_url,
          statusCode: result.redirect.status_code,
        },
        cached: false,
      };

      // Cache redirect for 1 minute (shorter TTL)
      slugCache.set(cacheKey, resolution, 60000);

      return resolution;
    }

    // Not found
    const resolution: ArticleResolutionResult = {
      success: false,
      error: result.error || 'Article not found',
      cached: false,
    };

    // Cache 404 for 1 minute to prevent repeated lookups
    slugCache.set(cacheKey, resolution, 60000);

    return resolution;
  } catch (error) {
    // Return error without caching
    return {
      success: false,
      error: error instanceof Error ? error.message : 'Resolution failed',
      cached: false,
    };
  }
}

/**
 * Resolve article with full data fetch
 * Combines slug resolution with article data fetching
 */
export async function resolveAndFetchArticle(
  categorySlug: string,
  articleSlug: string,
  locale: Locale
): Promise<{
  article: unknown | null;
  error?: string;
  redirect?: { url: string; statusCode: number };
}> {
  // Resolve slug first
  const resolution = await resolveArticle(categorySlug, articleSlug, locale);

  // Handle redirect
  if (resolution.redirect) {
    return {
      article: null,
      redirect: resolution.redirect,
    };
  }

  // Handle error
  if (!resolution.success || !resolution.articleId) {
    return {
      article: null,
      error: resolution.error || 'Article not found',
    };
  }

  // Fetch full article data
  try {
    const article = await fetchArticleById(resolution.articleId, locale);
    return { article };
  } catch (error) {
    return {
      article: null,
      error: error instanceof Error ? error.message : 'Failed to fetch article',
    };
  }
}

/**
 * Fetch article by ID
 * Uses article cache
 */
async function fetchArticleById(
  articleId: number,
  locale: Locale
): Promise<unknown> {
  const cacheKey = createCacheKey('article-data', locale, articleId);

  // Check cache
  const cached = articleCache.get(cacheKey);
  if (cached) {
    return cached;
  }

  // Fetch from API
  const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

  const response = await fetch(`${API_BASE_URL}/api/articles/${articleId}`, {
    headers: {
      'Content-Type': 'application/json',
      'Accept-Language': locale,
    },
    // Next.js-specific fetch option for revalidation
    next: {
      revalidate: 120, // Revalidate every 2 minutes
    },
  } as RequestInit);

  if (!response.ok) {
    throw new Error(`Failed to fetch article: ${response.status}`);
  }

  const article = await response.json();

  // Cache for 5 minutes
  articleCache.set(cacheKey, article, 300000);

  return article;
}

/**
 * Prefetch article (for link hover optimization)
 * Resolves slug and optionally fetches article data
 */
export async function prefetchArticle(
  categorySlug: string,
  articleSlug: string,
  locale: Locale,
  fetchData: boolean = false
): Promise<void> {
  if (fetchData) {
    // Fetch full article data
    await resolveAndFetchArticle(categorySlug, articleSlug, locale);
  } else {
    // Just resolve slug
    await resolveArticle(categorySlug, articleSlug, locale);
  }
}

/**
 * Invalidate article cache
 * Useful after article updates
 */
export function invalidateArticleCache(
  categorySlug: string,
  articleSlug: string,
  locale?: Locale
): void {
  if (locale) {
    const cacheKey = createCacheKey('article', locale, categorySlug, articleSlug);
    slugCache.delete(cacheKey);
  } else {
    // Invalidate all locales
    const locales: Locale[] = ['ro', 'en', 'ru'];
    locales.forEach((loc) => {
      const cacheKey = createCacheKey('article', loc, categorySlug, articleSlug);
      slugCache.delete(cacheKey);
    });
  }
}

/**
 * Invalidate article data cache by ID
 */
export function invalidateArticleDataCache(
  articleId: number,
  locale?: Locale
): void {
  if (locale) {
    const cacheKey = createCacheKey('article-data', locale, articleId);
    articleCache.delete(cacheKey);
  } else {
    // Invalidate all locales
    const locales: Locale[] = ['ro', 'en', 'ru'];
    locales.forEach((loc) => {
      const cacheKey = createCacheKey('article-data', loc, articleId);
      articleCache.delete(cacheKey);
    });
  }
}

/**
 * Batch resolve articles
 * Useful for prefetching multiple articles
 */
export async function batchResolveArticles(
  articles: Array<{
    categorySlug: string;
    articleSlug: string;
    locale: Locale;
  }>
): Promise<ArticleResolutionResult[]> {
  // Resolve all in parallel
  const promises = articles.map(({ categorySlug, articleSlug, locale }) =>
    resolveArticle(categorySlug, articleSlug, locale)
  );

  return Promise.all(promises);
}

/**
 * Check if article exists (lightweight check)
 * Returns boolean without fetching full data
 */
export async function articleExists(
  categorySlug: string,
  articleSlug: string,
  locale: Locale
): Promise<boolean> {
  const resolution = await resolveArticle(categorySlug, articleSlug, locale);
  return resolution.success;
}

/**
 * Get article URL with locale
 * Builds proper URL based on locale
 */
export function getArticleUrl(
  categorySlug: string,
  articleSlug: string,
  locale: Locale
): string {
  const localePrefix = locale === 'ro' ? '' : `/${locale}`;
  return `${localePrefix}/${categorySlug}/${articleSlug}`;
}

/**
 * Parse article URL
 * Extracts category, article slug, and locale from URL
 */
export function parseArticleUrl(url: string): {
  locale: Locale;
  categorySlug: string;
  articleSlug: string;
} | null {
  // Remove leading slash
  const path = url.replace(/^\//, '');
  const segments = path.split('/');

  // Check if starts with locale
  const locales: Locale[] = ['ro', 'en', 'ru'];
  let locale: Locale = 'ro';
  let startIndex = 0;

  if (segments.length > 0 && locales.includes(segments[0] as Locale)) {
    locale = segments[0] as Locale;
    startIndex = 1;
  }

  // Extract category and article slugs
  if (segments.length < startIndex + 2) {
    return null;
  }

  return {
    locale,
    categorySlug: segments[startIndex],
    articleSlug: segments[startIndex + 1],
  };
}
