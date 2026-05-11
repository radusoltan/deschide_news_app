/**
 * Slug Lookup API Service
 * Uses the /api/{resource}/by-slug/{slug} endpoints
 *
 * T60.8.2 hardening (2026-05-08): unexpected exceptions in lookup functions
 * are reported to Sentry with structured context, not silently swallowed.
 * The `return null` flow is preserved so consumers (page.tsx) still hit
 * the notFound() path; the breadcrumb gives us "WHY" upstream.
 *
 * Origin: 2026-04-29 RO 404 incident — production 404s with no upstream
 * signal beyond a console.error nobody read. Recreated post-archive of
 * orphan branch feature/T60.8-harden-slug-lookup (3d8f894) per Sprint 60
 * Phase D Stage 2.3+ orchestration.
 */

import * as Sentry from '@sentry/nextjs';
import type { Locale } from '../types';
import type { Article, Category, Author } from '../types/article';
import { CACHE_TAGS } from '../data/cache-config';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

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
): Promise<Article | null> {
  // T60.8.2 hardening: declare url/headers OUTSIDE try so the catch block
  // can include the constructed apiUrl in Sentry context. Block-scoping
  // them inside try would hide the URL from the failure breadcrumb —
  // exactly the observability gap we're fixing.
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };
  const url = new URL(`${API_BASE_URL}/api/articles/by-slug/${encodeURIComponent(slug)}`);
  url.searchParams.set('locale', locale);

  try {
    const response = await fetch(url.toString(), {
      method: 'GET',
      headers,
      next: {
        revalidate: 120, // Cache for 2 minutes
        tags: [CACHE_TAGS.articles, CACHE_TAGS.locale(locale)],
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
    // T60.8.2 hardening: unexpected throws (parse failures, network errors,
    // type-coercion crashes upstream of the response.ok branch) used to
    // be silently swallowed here. Now they surface as Sentry events so
    // production 404 mysteries don't repeat the 2026-04-29 incident.
    Sentry.captureException(error, {
      tags: {
        component: 'slug-lookup',
        operation: 'lookupArticle',
      },
      extra: {
        slug,
        locale,
        apiUrl: url.toString(),
      },
    });
    console.error('[slug-lookup:article] lookup failed', { slug, locale, error });
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
): Promise<Category | null> {
  // T60.8.2 hardening: see lookupArticle for the scoping rationale.
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept-Language': locale,
  };
  const url = new URL(`${API_BASE_URL}/api/categories/by-slug/${encodeURIComponent(slug)}`);
  url.searchParams.set('locale', locale);

  try {
    const response = await fetch(url.toString(), {
      method: 'GET',
      headers,
      next: {
        revalidate: 300, // Cache for 5 minutes
        tags: [CACHE_TAGS.categories, CACHE_TAGS.categoryBySlug(slug), CACHE_TAGS.locale(locale)],
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
    // T60.8.2 hardening: see lookupArticle catch for rationale.
    Sentry.captureException(error, {
      tags: {
        component: 'slug-lookup',
        operation: 'lookupCategory',
      },
      extra: {
        slug,
        locale,
        apiUrl: url.toString(),
      },
    });
    console.error('[slug-lookup:category] lookup failed', { slug, locale, error });
    return null;
  }
}

/**
 * Author lookup result interface
 */
interface AuthorLookupResult {
  found: boolean;
  entity: Author | null;
}

/**
 * Lookup author by slug
 * Note: Authors are not translatable, so no locale parameter needed
 *
 * @param slug - Author slug
 * @returns Object with found flag and entity data
 */
export async function lookupAuthor(
  slug: string
): Promise<AuthorLookupResult> {
  // T60.8.2 hardening: see lookupArticle for the scoping rationale.
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };
  const url = new URL(`${API_BASE_URL}/api/authors/by-slug/${encodeURIComponent(slug)}`);

  try {
    const response = await fetch(url.toString(), {
      method: 'GET',
      headers,
      next: {
        revalidate: 300, // Cache for 5 minutes
        tags: [CACHE_TAGS.authors, CACHE_TAGS.authorBySlug(slug)],
      },
    });

    if (!response.ok) {
      if (response.status === 404) {
        console.error(`Author with slug "${slug}" not found`);
        return { found: false, entity: null };
      }
      throw new Error(`Failed to lookup author: ${response.status} ${response.statusText}`);
    }

    const entity = await response.json();
    return { found: true, entity };
  } catch (error) {
    // T60.8.2 hardening: see lookupArticle catch for rationale.
    Sentry.captureException(error, {
      tags: {
        component: 'slug-lookup',
        operation: 'lookupAuthor',
      },
      extra: {
        slug,
        apiUrl: url.toString(),
      },
    });
    console.error('[slug-lookup:author] lookup failed', { slug, error });
    return { found: false, entity: null };
  }
}
