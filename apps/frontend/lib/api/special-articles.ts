/**
 * Special Articles API Service
 * Fetches articles with badges (breaking, alert, flash)
 */

import { Article, ArticleListResponse, ArticleBadge } from '../types/article';
import { CACHE_TAGS } from '../data/cache-config';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

/**
 * Fetch articles with a specific badge type
 * GET /api/articles?badge=breaking&status=published&itemsPerPage=5
 *
 * @param badge - Badge type to filter by (breaking, alert, flash)
 * @param locale - Language locale (ro, en, ru)
 * @param itemsPerPage - Number of articles to fetch (default: 5)
 * @returns Articles with the specified badge
 */
export async function fetchArticlesByBadge(
  badge: ArticleBadge,
  locale?: string,
  itemsPerPage: number = 5
): Promise<ArticleListResponse> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const url = new URL(`${API_BASE_URL}/api/articles`);
  url.searchParams.set('badge', badge);
  url.searchParams.set('status', 'published');
  url.searchParams.set('order[publishedAt]', 'desc');
  url.searchParams.set('itemsPerPage', itemsPerPage.toString());

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    next: {
      revalidate: 30, // Revalidate every 30 seconds (urgent content)
      tags: [CACHE_TAGS.specialArticles, CACHE_TAGS.articles, CACHE_TAGS.locale(locale ?? 'ro')],
    },
  });

  if (!response.ok) {
    throw new Error(
      `Failed to fetch ${badge} articles: ${response.status} ${response.statusText}`
    );
  }

  return response.json();
}

/**
 * Fetch all special articles (breaking, alert, flash) in one call
 * Makes parallel requests for each badge type
 *
 * @param locale - Language locale (ro, en, ru)
 * @param itemsPerBadge - Number of articles per badge type (default: 3)
 * @returns Combined array of all special articles
 */
export async function fetchAllSpecialArticles(
  locale?: string,
  itemsPerBadge: number = 3
): Promise<Article[]> {
  const badges: ArticleBadge[] = ['breaking', 'alert', 'flash'];

  try {
    // Fetch all badge types in parallel
    const results = await Promise.allSettled(
      badges.map(badge => fetchArticlesByBadge(badge, locale, itemsPerBadge))
    );

    // Combine all successful results
    const allArticles: Article[] = [];

    results.forEach((result, index) => {
      if (result.status === 'fulfilled' && result.value.member) {
        // Add badge type to each article if not present
        const articlesWithBadge = result.value.member.map(article => ({
          ...article,
          badge: article.badge || badges[index],
        }));
        allArticles.push(...articlesWithBadge);
      } else if (result.status === 'rejected') {
        console.warn(`Failed to fetch ${badges[index]} articles:`, result.reason);
      }
    });

    return allArticles;
  } catch (error) {
    console.error('Error fetching special articles:', error);
    return [];
  }
}

/**
 * Check if there are any special articles available
 * Useful for conditional rendering of the special section
 *
 * @param locale - Language locale (ro, en, ru)
 * @returns True if any special articles exist
 */
export async function hasSpecialArticles(locale?: string): Promise<boolean> {
  const articles = await fetchAllSpecialArticles(locale, 1);
  return articles.length > 0;
}
