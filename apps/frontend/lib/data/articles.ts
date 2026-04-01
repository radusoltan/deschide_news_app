/**
 * Articles Data Access Layer
 * Cached data fetching with Next.js 16 `use cache` directive
 *
 * @see NEXTJS_ALIGNMENT_PLAN.md - Phase 2 Caching Alignment
 */

import { cacheTag } from 'next/cache';
import { cache } from 'react';
import { CACHE_TAGS } from './cache-config';
import type { Article, ArticleListResponse, ImportantArticlesListResponse } from '@/lib/types/article';

// ============================================================================
// Configuration
// ============================================================================

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

// ============================================================================
// Types
// ============================================================================

export interface GetArticlesOptions {
  locale?: string;
  limit?: number;
  page?: number;
  categoryId?: number;
  authorId?: number;
  tagId?: number;
  status?: 'published' | 'draft' | 'scheduled';
  orderBy?: 'publishedAt' | 'createdAt' | 'updatedAt';
  orderDirection?: 'asc' | 'desc';
}

// Re-export Article type for convenience
export type { Article } from '@/lib/types/article';

// ============================================================================
// Core Data Fetching Functions
// ============================================================================

/**
 * Get latest published articles
 * Uses `use cache` directive for automatic caching with tags
 */
export async function getLatestArticles(
  locale: string = 'ro',
  limit: number = 10
): Promise<ArticleListResponse> {
  'use cache';
  cacheTag(CACHE_TAGS.articles, CACHE_TAGS.locale(locale), 'latest');

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const url = new URL(`${API_BASE_URL}/api/articles`);
  url.searchParams.set('status', 'published');
  url.searchParams.set('order[publishedAt]', 'desc');
  url.searchParams.set('itemsPerPage', limit.toString());

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch latest articles: ${response.status}`);
  }

  return response.json();
}

/**
 * Get articles by category
 * Uses `use cache` directive with category-specific tag
 */
export async function getArticlesByCategory(
  categoryId: number,
  locale: string = 'ro',
  limit: number = 6
): Promise<ArticleListResponse> {
  'use cache';
  cacheTag(
    CACHE_TAGS.articlesByCategory(categoryId),
    CACHE_TAGS.locale(locale)
  );

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const url = new URL(`${API_BASE_URL}/api/articles`);
  url.searchParams.set('categoryId', categoryId.toString());
  url.searchParams.set('status', 'published');
  url.searchParams.set('itemsPerPage', limit.toString());

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch articles by category: ${response.status}`);
  }

  return response.json();
}

/**
 * Get single article by ID
 * Uses `use cache` directive with article-specific tag
 */
export async function getArticleById(
  id: number,
  locale: string = 'ro'
): Promise<Article | null> {
  'use cache';
  cacheTag(CACHE_TAGS.articleById(id), CACHE_TAGS.locale(locale));

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const response = await fetch(`${API_BASE_URL}/api/articles/${id}`, {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    if (response.status === 404) {
      return null;
    }
    throw new Error(`Failed to fetch article: ${response.status}`);
  }

  return response.json();
}

/**
 * Get important articles (hero section)
 * Uses `use cache` directive with important-articles tag
 */
export async function getImportantArticles(
  locale: string = 'ro'
): Promise<ImportantArticlesListResponse> {
  'use cache';
  cacheTag(
    CACHE_TAGS.importantArticles,
    CACHE_TAGS.homepage,
    CACHE_TAGS.locale(locale)
  );

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const response = await fetch(`${API_BASE_URL}/api/important_articles`, {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch important articles: ${response.status}`);
  }

  return response.json();
}

/**
 * Get special articles (breaking, alert, flash badges)
 * Uses `use cache` directive with special-articles tag
 */
export async function getSpecialArticles(
  locale: string = 'ro',
  limit: number = 5
): Promise<ArticleListResponse> {
  'use cache';
  cacheTag(
    CACHE_TAGS.specialArticles,
    CACHE_TAGS.homepage,
    CACHE_TAGS.locale(locale)
  );

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const url = new URL(`${API_BASE_URL}/api/articles`);
  url.searchParams.set('status', 'published');
  url.searchParams.set('exists[badge]', 'true');
  url.searchParams.set('order[publishedAt]', 'desc');
  url.searchParams.set('itemsPerPage', limit.toString());

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch special articles: ${response.status}`);
  }

  return response.json();
}

/**
 * Get trending articles
 * Uses `use cache` directive with trending tag
 */
export async function getTrendingArticles(
  locale: string = 'ro',
  limit: number = 5
): Promise<ArticleListResponse> {
  'use cache';
  cacheTag(
    CACHE_TAGS.trendingArticles,
    CACHE_TAGS.locale(locale)
  );

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  // Trending is based on view count in the last 7 days
  const url = new URL(`${API_BASE_URL}/api/articles`);
  url.searchParams.set('status', 'published');
  url.searchParams.set('order[viewCount]', 'desc');
  url.searchParams.set('itemsPerPage', limit.toString());

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch trending articles: ${response.status}`);
  }

  return response.json();
}

/**
 * Get articles by author
 * Uses `use cache` directive with author-specific tag
 */
export async function getArticlesByAuthor(
  authorId: number,
  locale: string = 'ro',
  limit: number = 10
): Promise<ArticleListResponse> {
  'use cache';
  cacheTag(
    CACHE_TAGS.articlesByAuthor(authorId),
    CACHE_TAGS.locale(locale)
  );

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const url = new URL(`${API_BASE_URL}/api/articles`);
  url.searchParams.set('authors.id', authorId.toString());
  url.searchParams.set('status', 'published');
  url.searchParams.set('order[publishedAt]', 'desc');
  url.searchParams.set('itemsPerPage', limit.toString());

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch articles by author: ${response.status}`);
  }

  return response.json();
}

/**
 * Get articles by tag
 * Uses `use cache` directive with tag-specific tag
 */
export async function getArticlesByTag(
  tagId: number,
  locale: string = 'ro',
  limit: number = 10
): Promise<ArticleListResponse> {
  'use cache';
  cacheTag(
    CACHE_TAGS.articlesByTag(tagId),
    CACHE_TAGS.locale(locale)
  );

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const url = new URL(`${API_BASE_URL}/api/articles`);
  url.searchParams.set('tags.id', tagId.toString());
  url.searchParams.set('status', 'published');
  url.searchParams.set('order[publishedAt]', 'desc');
  url.searchParams.set('itemsPerPage', limit.toString());

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch articles by tag: ${response.status}`);
  }

  return response.json();
}

/**
 * Get archive articles by year and optional month
 * Uses `use cache` directive with archive-specific tag
 */
export async function getArchiveArticles(
  year: number,
  month?: number,
  locale: string = 'ro',
  limit: number = 20,
  page: number = 1
): Promise<ArticleListResponse> {
  'use cache';
  cacheTag(
    month
      ? CACHE_TAGS.archiveMonth(year, month)
      : CACHE_TAGS.archiveYear(year),
    CACHE_TAGS.locale(locale)
  );

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const url = new URL(`${API_BASE_URL}/api/articles`);
  url.searchParams.set('status', 'published');
  url.searchParams.set('order[publishedAt]', 'desc');
  url.searchParams.set('itemsPerPage', limit.toString());
  url.searchParams.set('page', page.toString());

  // Add date filters for archive
  if (month) {
    const startDate = new Date(year, month - 1, 1);
    const endDate = new Date(year, month, 0, 23, 59, 59);
    url.searchParams.set('publishedAt[after]', startDate.toISOString());
    url.searchParams.set('publishedAt[before]', endDate.toISOString());
  } else {
    const startDate = new Date(year, 0, 1);
    const endDate = new Date(year, 11, 31, 23, 59, 59);
    url.searchParams.set('publishedAt[after]', startDate.toISOString());
    url.searchParams.set('publishedAt[before]', endDate.toISOString());
  }

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch archive articles: ${response.status}`);
  }

  return response.json();
}

// ============================================================================
// React cache() for Request-Level Deduplication
// ============================================================================

/**
 * Get article by ID with request-level deduplication
 * Use this when the same article might be fetched multiple times in one request
 */
export const getArticleByIdCached = cache(async (
  id: number,
  locale: string = 'ro'
): Promise<Article | null> => {
  return getArticleById(id, locale);
});

/**
 * Get important articles with request-level deduplication
 */
export const getImportantArticlesCached = cache(async (
  locale: string = 'ro'
): Promise<ImportantArticlesListResponse> => {
  return getImportantArticles(locale);
});

// ============================================================================
// Utility Functions
// ============================================================================

/**
 * Get related articles for a given article
 * Fetches articles from same category, excluding current article
 */
export async function getRelatedArticles(
  articleId: number,
  categoryId: number,
  locale: string = 'ro',
  limit: number = 6
): Promise<ArticleListResponse['member']> {
  const result = await getArticlesByCategory(categoryId, locale, limit + 1);

  // Filter out the current article
  const relatedArticles = result.member.filter(
    (article) => article.id !== articleId
  );

  // Return only the requested limit
  return relatedArticles.slice(0, limit);
}
