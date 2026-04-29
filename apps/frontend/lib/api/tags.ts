/**
 * Tags API Service
 * Provides functions for fetching and managing tags from the backend API
 */

import {
  Tag,
  TagCollection,
  GetTagsParams,
  RelatedTagsResponse,
  TagStatistics,
} from '../types/tag';
import type { Article } from '../types/article';
import { CACHE_TAGS } from '../data/cache-config';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

/**
 * Fetch tags with optional filters and pagination
 * GET /api/tags
 *
 * @param locale - Language locale (ro, en, ru)
 * @param params - Query parameters (pagination, filters, ordering)
 * @returns Tag collection with pagination
 */
export async function fetchTags(
  locale?: string,
  params: GetTagsParams = {}
): Promise<TagCollection> {
  const headers: HeadersInit = {
    'Content-Type': 'application/ld+json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const url = new URL(`${API_BASE_URL}/api/tags`);

  // Pagination
  if (params.page) {
    url.searchParams.set('page', params.page.toString());
  }
  if (params.itemsPerPage) {
    url.searchParams.set('itemsPerPage', params.itemsPerPage.toString());
  }

  // Filters
  if (params.name) {
    url.searchParams.set('name', params.name);
  }
  if (params.slug) {
    url.searchParams.set('slug', params.slug);
  }
  if (params.minUsageCount !== undefined) {
    url.searchParams.set('usageCount[gte]', params.minUsageCount.toString());
  }

  // Ordering
  if (params.order) {
    Object.entries(params.order).forEach(([field, direction]) => {
      url.searchParams.set(`order[${field}]`, direction);
    });
  }

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    next: {
      revalidate: 300, // Revalidate every 5 minutes
      tags: [CACHE_TAGS.tags, CACHE_TAGS.locale(locale ?? 'ro')],
    },
  });

  if (!response.ok) {
    throw new Error(
      `Failed to fetch tags: ${response.status} ${response.statusText}`
    );
  }

  return response.json();
}

/**
 * Fetch a single tag by ID
 * GET /api/tags/{id}
 *
 * @param id - Tag ID
 * @param locale - Language locale (ro, en, ru)
 * @returns Single tag object
 */
export async function fetchTag(id: number, locale?: string): Promise<Tag> {
  const headers: HeadersInit = {
    'Content-Type': 'application/ld+json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const response = await fetch(`${API_BASE_URL}/api/tags/${id}`, {
    method: 'GET',
    headers,
    next: {
      revalidate: 300,
      tags: [CACHE_TAGS.tags, CACHE_TAGS.tagById(id), CACHE_TAGS.locale(locale ?? 'ro')],
    },
  });

  if (!response.ok) {
    throw new Error(
      `Failed to fetch tag: ${response.status} ${response.statusText}`
    );
  }

  return response.json();
}

/**
 * Fetch popular tags ordered by usage count
 * GET /api/tags/popular
 *
 * @param locale - Language locale (ro, en, ru)
 * @param limit - Number of tags to return (default: 20, max: 100)
 * @returns Array of popular tags
 */
export async function fetchPopularTags(
  locale?: string,
  limit: number = 20
): Promise<{ 'hydra:member': Tag[]; 'hydra:totalItems': number }> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const url = new URL(`${API_BASE_URL}/api/tags/popular`);
  url.searchParams.set('limit', Math.min(100, Math.max(1, limit)).toString());

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    next: {
      revalidate: 600, // Cache for 10 minutes (same as backend)
      tags: [CACHE_TAGS.tags, CACHE_TAGS.locale(locale ?? 'ro')],
    },
  });

  if (!response.ok) {
    throw new Error(
      `Failed to fetch popular tags: ${response.status} ${response.statusText}`
    );
  }

  return response.json();
}

/**
 * Search tags by name (autocomplete)
 * GET /api/tags/search?q=...
 *
 * @param query - Search query
 * @param locale - Language locale (ro, en, ru)
 * @param limit - Number of results (default: 10, max: 50)
 * @returns Array of matching tags
 */
export async function searchTags(
  query: string,
  locale?: string,
  limit: number = 10
): Promise<{ 'hydra:member': Tag[]; 'hydra:totalItems': number }> {
  if (!query || query.trim().length === 0) {
    return { 'hydra:member': [], 'hydra:totalItems': 0 };
  }

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const url = new URL(`${API_BASE_URL}/api/tags/search`);
  url.searchParams.set('q', query.trim());
  url.searchParams.set('limit', Math.min(50, Math.max(1, limit)).toString());

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    next: {
      revalidate: 300, // Cache for 5 minutes
      tags: [CACHE_TAGS.tags, CACHE_TAGS.locale(locale ?? 'ro')],
    },
  });

  if (!response.ok) {
    throw new Error(
      `Failed to search tags: ${response.status} ${response.statusText}`
    );
  }

  return response.json();
}

/**
 * Fetch related tags for a given tag (co-occurring tags)
 * GET /api/tags/{id}/related
 *
 * @param tagId - Tag ID
 * @param locale - Language locale (ro, en, ru)
 * @param limit - Number of related tags (default: 10, max: 50)
 * @returns Array of related tags
 */
export async function fetchRelatedTags(
  tagId: number,
  locale?: string,
  limit: number = 10
): Promise<{ 'hydra:member': Tag[]; 'hydra:totalItems': number }> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const url = new URL(`${API_BASE_URL}/api/tags/${tagId}/related`);
  url.searchParams.set('limit', Math.min(50, Math.max(1, limit)).toString());

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    next: {
      revalidate: 600, // Cache for 10 minutes
      tags: [CACHE_TAGS.tags, CACHE_TAGS.tagById(tagId), CACHE_TAGS.locale(locale ?? 'ro')],
    },
  });

  if (!response.ok) {
    throw new Error(
      `Failed to fetch related tags: ${response.status} ${response.statusText}`
    );
  }

  return response.json();
}

/**
 * Fetch tag statistics
 * GET /api/tags/{id}/stats
 *
 * @param tagId - Tag ID
 * @param locale - Language locale (ro, en, ru)
 * @returns Tag statistics (usage count, article count, timestamps)
 */
export async function fetchTagStats(
  tagId: number,
  locale?: string
): Promise<{
  usageCount: number;
  articleCount: number;
  createdAt: string;
  updatedAt: string;
}> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const response = await fetch(`${API_BASE_URL}/api/tags/${tagId}/stats`, {
    method: 'GET',
    headers,
    next: {
      revalidate: 300, // Cache for 5 minutes
      tags: [CACHE_TAGS.tags, CACHE_TAGS.tagById(tagId)],
    },
  });

  if (!response.ok) {
    throw new Error(
      `Failed to fetch tag stats: ${response.status} ${response.statusText}`
    );
  }

  return response.json();
}

/**
 * Fetch unused tags (usageCount = 0)
 * GET /api/tags/unused
 *
 * @param locale - Language locale (ro, en, ru)
 * @param limit - Number of tags (default: 50, max: 200)
 * @returns Array of unused tags
 */
export async function fetchUnusedTags(
  locale?: string,
  limit: number = 50
): Promise<{ 'hydra:member': Tag[]; 'hydra:totalItems': number }> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const url = new URL(`${API_BASE_URL}/api/tags/unused`);
  url.searchParams.set('limit', Math.min(200, Math.max(1, limit)).toString());

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    next: {
      revalidate: 300, // Cache for 5 minutes
      tags: [CACHE_TAGS.tags, CACHE_TAGS.locale(locale ?? 'ro')],
    },
  });

  if (!response.ok) {
    throw new Error(
      `Failed to fetch unused tags: ${response.status} ${response.statusText}`
    );
  }

  return response.json();
}

/**
 * Fetch articles filtered by tag
 * GET /api/articles?tags.slug=...
 *
 * @param tagSlug - Tag slug
 * @param locale - Language locale (ro, en, ru)
 * @param itemsPerPage - Number of articles (default: 20)
 * @param page - Page number (default: 1)
 * @returns Articles with the specified tag
 */
export async function fetchArticlesByTag(
  tagSlug: string,
  locale?: string,
  itemsPerPage: number = 20,
  page: number = 1
): Promise<{ 'hydra:member': Article[]; 'hydra:totalItems': number; member?: Article[] }> {
  const headers: HeadersInit = {
    'Content-Type': 'application/ld+json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const url = new URL(`${API_BASE_URL}/api/articles`);
  url.searchParams.set('tags.slug', tagSlug);
  url.searchParams.set('status', 'published');
  url.searchParams.set('order[publishedAt]', 'DESC');
  url.searchParams.set('itemsPerPage', itemsPerPage.toString());
  url.searchParams.set('page', page.toString());

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    next: {
      revalidate: 120, // Revalidate every 2 minutes
      tags: [CACHE_TAGS.articles, CACHE_TAGS.tagBySlug(tagSlug), CACHE_TAGS.locale(locale ?? 'ro')],
    },
  });

  if (!response.ok) {
    throw new Error(
      `Failed to fetch articles by tag: ${response.status} ${response.statusText}`
    );
  }

  return response.json();
}
