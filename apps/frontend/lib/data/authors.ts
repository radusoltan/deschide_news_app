/**
 * Authors Data Access Layer
 * Cached data fetching with Next.js 16 `use cache` directive
 *
 * @see NEXTJS_ALIGNMENT_PLAN.md - Phase 2 Caching Alignment
 */

import { cacheTag } from 'next/cache';
import { cache } from 'react';
import { CACHE_TAGS } from './cache-config';

// ============================================================================
// Configuration
// ============================================================================

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

// ============================================================================
// Types
// ============================================================================

export interface Author {
  id: number;
  name: string;
  slug: string;
  bio?: string;
  email?: string;
  image?: {
    id: number;
    path: string;
    alt?: string;
  };
  socialLinks?: {
    twitter?: string;
    facebook?: string;
    linkedin?: string;
  };
  articleCount?: number;
}

export interface AuthorsListResponse {
  '@context': string;
  '@id': string;
  '@type': string;
  member: Author[];
  totalItems: number;
}

// ============================================================================
// Core Data Fetching Functions
// ============================================================================

/**
 * Get all authors
 * Uses `use cache` directive for automatic caching with tags
 */
export async function getAllAuthors(
  locale: string = 'ro'
): Promise<AuthorsListResponse> {
  'use cache';
  cacheTag(CACHE_TAGS.authors, CACHE_TAGS.locale(locale));

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const url = new URL(`${API_BASE_URL}/api/authors`);
  url.searchParams.set('itemsPerPage', '100');
  url.searchParams.set('order[name]', 'asc');

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch authors: ${response.status}`);
  }

  return response.json();
}

/**
 * Get single author by ID
 * Uses `use cache` directive with author-specific tag
 */
export async function getAuthorById(
  id: number,
  locale: string = 'ro'
): Promise<Author | null> {
  'use cache';
  cacheTag(CACHE_TAGS.authorById(id), CACHE_TAGS.locale(locale));

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const response = await fetch(`${API_BASE_URL}/api/authors/${id}`, {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    if (response.status === 404) {
      return null;
    }
    throw new Error(`Failed to fetch author: ${response.status}`);
  }

  return response.json();
}

/**
 * Get author by slug
 * Uses `use cache` directive with slug-specific tag
 */
export async function getAuthorBySlug(
  slug: string,
  locale: string = 'ro'
): Promise<Author | null> {
  'use cache';
  cacheTag(CACHE_TAGS.authorBySlug(slug), CACHE_TAGS.locale(locale));

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const url = new URL(`${API_BASE_URL}/api/authors`);
  url.searchParams.set('slug', slug);

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch author by slug: ${response.status}`);
  }

  const data: AuthorsListResponse = await response.json();

  if (data.member.length === 0) {
    return null;
  }

  return data.member[0];
}

/**
 * Get featured/staff authors (for team page)
 * Uses `use cache` directive with authors tag
 */
export async function getFeaturedAuthors(
  locale: string = 'ro',
  limit: number = 10
): Promise<AuthorsListResponse> {
  'use cache';
  cacheTag(CACHE_TAGS.authors, CACHE_TAGS.locale(locale), 'featured');

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };

  const url = new URL(`${API_BASE_URL}/api/authors`);
  url.searchParams.set('isFeatured', 'true');
  url.searchParams.set('itemsPerPage', limit.toString());
  url.searchParams.set('order[position]', 'asc');

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'force-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch featured authors: ${response.status}`);
  }

  return response.json();
}

// ============================================================================
// React cache() for Request-Level Deduplication
// ============================================================================

/**
 * Get all authors with request-level deduplication
 * Use this when authors might be fetched multiple times in one request
 */
export const getAllAuthorsCached = cache(async (
  locale: string = 'ro'
): Promise<AuthorsListResponse> => {
  return getAllAuthors(locale);
});

/**
 * Get author by slug with request-level deduplication
 */
export const getAuthorBySlugCached = cache(async (
  slug: string,
  locale: string = 'ro'
): Promise<Author | null> => {
  return getAuthorBySlug(slug, locale);
});

/**
 * Get author by ID with request-level deduplication
 */
export const getAuthorByIdCached = cache(async (
  id: number,
  locale: string = 'ro'
): Promise<Author | null> => {
  return getAuthorById(id, locale);
});

// ============================================================================
// Utility Functions
// ============================================================================

/**
 * Get author initials for avatar placeholder
 */
export function getAuthorInitials(name: string): string {
  if (!name) return '?';

  const parts = name.trim().split(/\s+/);
  if (parts.length === 1) {
    return parts[0].charAt(0).toUpperCase();
  }

  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

/**
 * Get author avatar URL or placeholder
 */
export function getAuthorAvatarUrl(author: Author): string | null {
  if (author.image?.path) {
    const CDN_URL = process.env.NEXT_PUBLIC_CDN_URL || 'http://127.0.0.1:8082';
    return `${CDN_URL}/uploads/${author.image.path}`;
  }
  return null;
}
