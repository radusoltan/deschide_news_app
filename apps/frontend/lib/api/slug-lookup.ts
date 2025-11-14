/**
 * Slug Lookup API Service
 * Uses the /api/{resource}/by-slug/{slug} endpoints
 */

import type { Locale } from '../types';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

/**
 * Lookup article by slug
 *
 * @param slug - Article slug
 * @param locale - Language locale (ro, en, ru)
 * @returns Article data or null
 */
export async function lookupArticle(
  slug: string,
  locale: Locale
): Promise<any | null> {
  try {
    const headers: HeadersInit = {
      'Content-Type': 'application/json',
      'Accept-Language': locale,
    };

    const url = new URL(`${API_BASE_URL}/api/articles/by-slug/${encodeURIComponent(slug)}`);
    url.searchParams.set('locale', locale);

    const response = await fetch(url.toString(), {
      method: 'GET',
      headers,
      next: {
        revalidate: 120, // Cache for 2 minutes
      },
    });

    if (!response.ok) {
      if (response.status === 404) {
        console.error(`Article with slug "${slug}" not found in locale "${locale}"`);
        return null;
      }
      throw new Error(`Failed to lookup article: ${response.status} ${response.statusText}`);
    }

    return response.json();
  } catch (error) {
    console.error('Error looking up article:', error);
    return null;
  }
}

/**
 * Lookup category by slug
 *
 * @param slug - Category slug
 * @param locale - Language locale (ro, en, ru)
 * @returns Category data or null
 */
export async function lookupCategory(
  slug: string,
  locale: Locale
): Promise<any | null> {
  try {
    const headers: HeadersInit = {
      'Content-Type': 'application/json',
      'Accept-Language': locale,
    };

    const url = new URL(`${API_BASE_URL}/api/categories/by-slug/${encodeURIComponent(slug)}`);
    url.searchParams.set('locale', locale);

    const response = await fetch(url.toString(), {
      method: 'GET',
      headers,
      next: {
        revalidate: 300, // Cache for 5 minutes
      },
    });

    if (!response.ok) {
      if (response.status === 404) {
        console.error(`Category with slug "${slug}" not found in locale "${locale}"`);
        return null;
      }
      throw new Error(`Failed to lookup category: ${response.status} ${response.statusText}`);
    }

    return response.json();
  } catch (error) {
    console.error('Error looking up category:', error);
    return null;
  }
}

/**
 * Lookup author by slug
 * Note: Authors are not translatable, so no locale parameter needed
 *
 * @param slug - Author slug
 * @returns Author data or null
 */
export async function lookupAuthor(
  slug: string
): Promise<any | null> {
  try {
    const headers: HeadersInit = {
      'Content-Type': 'application/json',
    };

    const url = new URL(`${API_BASE_URL}/api/authors/by-slug/${encodeURIComponent(slug)}`);

    const response = await fetch(url.toString(), {
      method: 'GET',
      headers,
      next: {
        revalidate: 300, // Cache for 5 minutes
      },
    });

    if (!response.ok) {
      if (response.status === 404) {
        console.error(`Author with slug "${slug}" not found`);
        return null;
      }
      throw new Error(`Failed to lookup author: ${response.status} ${response.statusText}`);
    }

    return response.json();
  } catch (error) {
    console.error('Error looking up author:', error);
    return null;
  }
}
