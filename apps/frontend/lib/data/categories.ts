/**
 * Categories Data Access Layer
 * Cached data fetching with Next.js 16 `use cache` directive
 *
 * @see NEXTJS_ALIGNMENT_PLAN.md - Phase 2 Caching Alignment
 */

import { cacheTag } from 'next/cache';
import { cache } from 'react';
import { CACHE_TAGS } from './cache-config';
import type { Category } from '@/lib/types/article';

// ============================================================================
// Configuration
// ============================================================================

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

// ============================================================================
// Types
// ============================================================================

export interface CategoriesListResponse {
  '@context': string;
  '@id': string;
  '@type': string;
  member: Category[];
  totalItems: number;
}

export interface CategoryWithArticleCount extends Category {
  articleCount?: number;
}

// ============================================================================
// Core Data Fetching Functions
// ============================================================================

/**
 * Get all active categories
 * Uses `use cache` directive for automatic caching with tags
 */
export async function getAllCategories(
  locale: string = 'ro'
): Promise<CategoriesListResponse> {
  'use cache';
  cacheTag(CACHE_TAGS.categories, CACHE_TAGS.locale(locale));

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const url = new URL(`${API_BASE_URL}/api/categories`);
  url.searchParams.set('itemsPerPage', '100');
  url.searchParams.set('status', 'active');

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch categories: ${response.status}`);
  }

  return response.json();
}

/**
 * Get categories for front page display
 * Uses `use cache` directive with homepage tag
 */
export async function getFrontPageCategories(
  locale: string = 'ro'
): Promise<CategoriesListResponse> {
  'use cache';
  cacheTag(
    CACHE_TAGS.categories,
    CACHE_TAGS.homepage,
    CACHE_TAGS.locale(locale)
  );

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const url = new URL(`${API_BASE_URL}/api/categories`);
  url.searchParams.set('onFrontPage', 'true');
  url.searchParams.set('status', 'active');
  url.searchParams.set('itemsPerPage', '50');

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch front page categories: ${response.status}`);
  }

  return response.json();
}

/**
 * Get navigation categories (for header/footer)
 * Uses `use cache` directive with navigation tag
 */
export async function getNavigationCategories(
  locale: string = 'ro'
): Promise<CategoriesListResponse> {
  'use cache';
  cacheTag(
    CACHE_TAGS.navigation,
    CACHE_TAGS.categories,
    CACHE_TAGS.locale(locale)
  );

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const url = new URL(`${API_BASE_URL}/api/categories`);
  url.searchParams.set('inNavigation', 'true');
  url.searchParams.set('status', 'active');
  url.searchParams.set('itemsPerPage', '20');
  url.searchParams.set('order[position]', 'asc');

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch navigation categories: ${response.status}`);
  }

  return response.json();
}

/**
 * Get single category by ID
 * Uses `use cache` directive with category-specific tag
 */
export async function getCategoryById(
  id: number,
  locale: string = 'ro'
): Promise<Category | null> {
  'use cache';
  cacheTag(CACHE_TAGS.categoryById(id), CACHE_TAGS.locale(locale));

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const response = await fetch(`${API_BASE_URL}/api/categories/${id}`, {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    if (response.status === 404) {
      return null;
    }
    throw new Error(`Failed to fetch category: ${response.status}`);
  }

  return response.json();
}

/**
 * Get category by slug
 * Uses `use cache` directive with slug-specific tag
 */
export async function getCategoryBySlug(
  slug: string,
  locale: string = 'ro'
): Promise<Category | null> {
  'use cache';
  cacheTag(CACHE_TAGS.categoryBySlug(slug), CACHE_TAGS.locale(locale));

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const url = new URL(`${API_BASE_URL}/api/categories`);
  url.searchParams.set('slug', slug);
  url.searchParams.set('status', 'active');

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch category by slug: ${response.status}`);
  }

  const data: CategoriesListResponse = await response.json();

  if (data.member.length === 0) {
    return null;
  }

  return data.member[0];
}

// ============================================================================
// React cache() for Request-Level Deduplication
// ============================================================================

/**
 * Get all categories with request-level deduplication
 * Use this when categories might be fetched multiple times in one request
 */
export const getAllCategoriesCached = cache(async (
  locale: string = 'ro'
): Promise<CategoriesListResponse> => {
  return getAllCategories(locale);
});

/**
 * Get navigation categories with request-level deduplication
 */
export const getNavigationCategoriesCached = cache(async (
  locale: string = 'ro'
): Promise<CategoriesListResponse> => {
  return getNavigationCategories(locale);
});

/**
 * Get category by slug with request-level deduplication
 */
export const getCategoryBySlugCached = cache(async (
  slug: string,
  locale: string = 'ro'
): Promise<Category | null> => {
  return getCategoryBySlug(slug, locale);
});

// ============================================================================
// Utility Functions
// ============================================================================

/**
 * Get category color based on slug
 * Returns tailwind-compatible color class
 */
export function getCategoryColor(slug: string): string {
  const colors: Record<string, string> = {
    politica: 'blue',
    economie: 'green',
    societate: 'purple',
    sport: 'red',
    cultura: 'amber',
    external: 'cyan',
    sanatate: 'emerald',
    tehnologie: 'indigo',
    educatie: 'orange',
    justitie: 'rose',
  };

  return colors[slug] || 'gray';
}

/**
 * Get category badge style based on slug
 */
export function getCategoryBadgeClass(slug: string): string {
  const baseClasses = 'inline-flex items-center px-3 py-1 text-xs font-semibold uppercase tracking-wider rounded';

  const colorClasses: Record<string, string> = {
    politica: 'bg-blue-100 text-blue-800',
    economie: 'bg-green-100 text-green-800',
    societate: 'bg-purple-100 text-purple-800',
    sport: 'bg-red-100 text-red-800',
    cultura: 'bg-amber-100 text-amber-800',
    external: 'bg-cyan-100 text-cyan-800',
    sanatate: 'bg-emerald-100 text-emerald-800',
    tehnologie: 'bg-indigo-100 text-indigo-800',
    educatie: 'bg-orange-100 text-orange-800',
    justitie: 'bg-rose-100 text-rose-800',
  };

  const colorClass = colorClasses[slug] || 'bg-gray-100 text-gray-800';

  return `${baseClasses} ${colorClass}`;
}
