/**
 * Categories API Service
 */

import { Category } from '../types/article';
import { CACHE_TAGS } from '../data/cache-config';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

export interface CategoriesListResponse {
  '@context': string;
  '@id': string;
  '@type': string;
  member: Category[];
  totalItems: number;
}

/**
 * Fetch all categories
 * GET /api/categories
 *
 * @param locale - Language locale (ro, en, ru)
 * @returns All categories
 */
export async function fetchCategories(locale?: string): Promise<CategoriesListResponse> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const url = new URL(`${API_BASE_URL}/api/categories`);
  url.searchParams.set('itemsPerPage', '100'); // Get all categories
  url.searchParams.set('status', 'active');

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    next: {
      revalidate: 300, // Revalidate every 5 minutes
      tags: [CACHE_TAGS.categories, CACHE_TAGS.locale(locale ?? 'ro')],
    },
  });

  if (!response.ok) {
    throw new Error(
      `Failed to fetch categories: ${response.status} ${response.statusText}`
    );
  }

  return response.json();
}

/**
 * Fetch categories with onFrontPage flag
 * GET /api/categories?onFrontPage=true
 *
 * @param locale - Language locale (ro, en, ru)
 * @returns Categories that should appear on front page
 */
export async function fetchFrontPageCategories(
  locale?: string
): Promise<CategoriesListResponse> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const url = new URL(`${API_BASE_URL}/api/categories`);
  url.searchParams.set('onFrontPage', 'true');
  url.searchParams.set('status', 'active');
  url.searchParams.set('itemsPerPage', '50'); // Get all front page categories

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    next: {
      revalidate: 300, // Revalidate every 5 minutes
      tags: [CACHE_TAGS.categories, CACHE_TAGS.homepage, CACHE_TAGS.locale(locale ?? 'ro')],
    },
  });

  if (!response.ok) {
    throw new Error(
      `Failed to fetch categories: ${response.status} ${response.statusText}`
    );
  }

  return response.json();
}
