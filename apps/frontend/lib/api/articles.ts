/**
 * Articles API Service
 */

import { ArticleListResponse } from '../types/article';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

/**
 * Fetch articles by category
 * GET /api/articles?category.id=X&status=published&itemsPerPage=6
 *
 * @param categoryId - Category ID to filter by
 * @param locale - Language locale (ro, en, ru)
 * @param itemsPerPage - Number of articles to fetch (default: 6)
 * @returns Articles in the specified category
 */
export async function fetchArticlesByCategory(
  categoryId: number,
  locale?: string,
  itemsPerPage: number = 6
): Promise<ArticleListResponse> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const url = new URL(`${API_BASE_URL}/api/articles`);
  url.searchParams.set('categoryId', categoryId.toString());
  url.searchParams.set('status', 'published');
  url.searchParams.set('itemsPerPage', itemsPerPage.toString());
  // Note: order is handled by default in backend (publishedAt DESC)

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    next: { tags: ['articles'] },
  });

  if (!response.ok) {
    throw new Error(
      `Failed to fetch articles: ${response.status} ${response.statusText}`
    );
  }

  return response.json();
}

/**
 * Fetch latest published articles
 * GET /api/articles?status=published&order[publishedAt]=desc&itemsPerPage=7
 *
 * @param locale - Language locale (ro, en, ru)
 * @param itemsPerPage - Number of articles to fetch (default: 7)
 * @returns Latest published articles
 */
export async function fetchLatestArticles(
  locale?: string,
  itemsPerPage: number = 10
): Promise<ArticleListResponse> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const url = new URL(`${API_BASE_URL}/api/articles`);
  url.searchParams.set('status', 'published');
  url.searchParams.set('order[publishedAt]', 'desc');
  url.searchParams.set('itemsPerPage', itemsPerPage.toString());

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    next: { tags: ['articles'] },
  });

  if (!response.ok) {
    throw new Error(
      `Failed to fetch latest articles: ${response.status} ${response.statusText}`
    );
  }

  return response.json();
}

/**
 * Fetch related articles for a given article
 * Gets articles from the same category, excluding the current article
 *
 * @param articleId - Current article ID to exclude
 * @param categoryId - Category ID to filter by
 * @param locale - Language locale (ro, en, ru)
 * @param limit - Number of articles to fetch (default: 6)
 * @returns Related articles
 */
export async function fetchRelatedArticles(
  articleId: number,
  categoryId: number,
  locale?: string,
  limit: number = 6
): Promise<any[]> {
  try {
    const result = await fetchArticlesByCategory(categoryId, locale, limit + 1);

    // Filter out the current article
    const relatedArticles = result.member.filter(
      (article: any) => article.id !== articleId
    );

    // Return only the requested limit
    return relatedArticles.slice(0, limit);
  } catch (error) {
    console.error('Error fetching related articles:', error);
    return [];
  }
}
