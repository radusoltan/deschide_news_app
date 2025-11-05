/**
 * Slug API Service
 * Handles slug lookup, validation, and suggestion requests
 * Based on Sprint 1 backend API contracts
 */

import { post, get } from './client';
import type {
  Locale,
  SlugType,
  SlugLookupRequest,
  SlugLookupResponse,
  SlugValidationRequest,
  SlugValidationResponse,
  RedirectCheckRequest,
  RedirectCheckResponse,
  ReservedSlugsResponse,
  CheckReservedRequest,
  CheckReservedResponse,
  BulkValidationRequest,
  BulkValidationResponse,
  SlugSuggestionsRequest,
  SlugSuggestionsResponse,
} from '../types/slug';

// ============================================================================
// Slug Lookup
// ============================================================================

/**
 * Lookup article by category and article slugs
 * POST /api/slug/lookup
 *
 * This is the main function for resolving article pages. It will:
 * - Return article data if found
 * - Return redirect info if slug has been redirected
 * - Return error if not found
 *
 * @param categorySlug - Category slug (e.g., "politica")
 * @param articleSlug - Article slug (e.g., "declaratii-premierului")
 * @param locale - Language locale (ro, en, ru)
 * @returns Article data, redirect info, or error
 *
 * @example
 * const result = await lookupArticle("politica", "declaratii-premierului", "ro");
 * if (result.success && result.data) {
 *   // Article found - render page
 * } else if (result.redirect) {
 *   // Slug has been redirected - middleware should handle this
 * } else {
 *   // Not found - show 404
 * }
 */
export async function lookupArticle(
  categorySlug: string,
  articleSlug: string,
  locale: Locale
): Promise<SlugLookupResponse> {
  const request: SlugLookupRequest = {
    category_slug: categorySlug,
    article_slug: articleSlug,
    locale,
  };

  return post<SlugLookupResponse>('/api/slug/lookup', request, {
    locale,
    skipRetry: true, // Don't retry 404s
  });
}

// ============================================================================
// Slug Validation
// ============================================================================

/**
 * Validate if slug is available for use
 * POST /api/slug/validate
 *
 * Used in admin panel to check if a slug can be used before saving.
 *
 * @param slug - Slug to validate
 * @param type - Type of entity ("category" or "article")
 * @param locale - Language locale
 * @param excludeId - Optional ID to exclude from check (for updates)
 * @returns Validation result with availability and conflicts
 *
 * @example
 * const result = await validateSlug("politica", "category", "ro");
 * if (result.is_available) {
 *   // Slug is available
 * } else {
 *   // Slug conflicts with existing entity
 *   console.log(result.conflicts);
 * }
 */
export async function validateSlug(
  slug: string,
  type: SlugType,
  locale: Locale,
  excludeId?: number
): Promise<SlugValidationResponse> {
  const request: SlugValidationRequest = {
    slug,
    type,
    locale,
    exclude_id: excludeId,
  };

  return post<SlugValidationResponse>('/api/slug/validate', request, {
    locale,
    skipRetry: true,
  });
}

/**
 * Convenience function to check if slug is available
 * Returns boolean for simple availability check
 */
export async function isSlugAvailable(
  slug: string,
  type: SlugType,
  locale: Locale,
  excludeId?: number
): Promise<boolean> {
  try {
    const result = await validateSlug(slug, type, locale, excludeId);
    return result.is_available;
  } catch {
    return false;
  }
}

// ============================================================================
// Redirect Check
// ============================================================================

/**
 * Check if URL has any redirects
 * POST /api/slug/check-redirect
 *
 * Used by middleware to check for redirects before rendering page.
 * Detects redirect chains and resolves to final URL.
 *
 * @param url - URL path to check (e.g., "/politica/old-article")
 * @returns Redirect information with chain and final URL
 *
 * @example
 * const result = await checkRedirect("/politica/old-article");
 * if (result.has_redirect) {
 *   // Redirect to result.final_url
 *   // Use status code from first redirect in chain
 * }
 */
export async function checkRedirect(
  url: string
): Promise<RedirectCheckResponse> {
  const request: RedirectCheckRequest = { url };

  return post<RedirectCheckResponse>('/api/slug/check-redirect', request, {
    skipRetry: true,
    timeout: 3000, // Fast timeout for middleware
  });
}

// ============================================================================
// Reserved Slugs
// ============================================================================

/**
 * Get list of reserved slugs
 * GET /api/slug/reserved
 *
 * Returns list of slugs that cannot be used for categories/articles.
 * Should be cached on frontend.
 *
 * @returns List of reserved slugs
 *
 * @example
 * const reserved = await getReservedSlugs();
 * // ["all", "search", "trending", "archive", "about", ...]
 */
export async function getReservedSlugs(): Promise<string[]> {
  const response = await get<ReservedSlugsResponse>('/api/slug/reserved', {
    skipRetry: true,
  });

  return response.slugs;
}

/**
 * Check if specific slug is reserved
 * POST /api/slug/check-reserved
 *
 * @param slug - Slug to check
 * @returns Whether slug is reserved
 *
 * @example
 * const result = await checkReservedSlug("admin");
 * if (result.is_reserved) {
 *   // Cannot use this slug
 * }
 */
export async function checkReservedSlug(
  slug: string
): Promise<CheckReservedResponse> {
  const request: CheckReservedRequest = { slug };

  return post<CheckReservedResponse>('/api/slug/check-reserved', request, {
    skipRetry: true,
  });
}

/**
 * Convenience function to check if slug is reserved
 * Returns boolean for simple check
 */
export async function isSlugReserved(slug: string): Promise<boolean> {
  try {
    const result = await checkReservedSlug(slug);
    return result.is_reserved;
  } catch {
    return false;
  }
}

// ============================================================================
// Bulk Operations
// ============================================================================

/**
 * Validate multiple slugs at once
 * POST /api/slug/bulk-validate
 *
 * Useful for batch operations or pre-validation of multiple slugs.
 *
 * @param slugs - Array of slugs to validate
 * @param type - Type of entity
 * @param locale - Language locale
 * @returns Validation results for all slugs
 *
 * @example
 * const results = await bulkValidate(
 *   ["politica", "economie", "sport"],
 *   "category",
 *   "ro"
 * );
 * results.results.forEach(r => {
 *   console.log(`${r.slug}: ${r.is_available ? 'available' : 'taken'}`);
 * });
 */
export async function bulkValidate(
  slugs: string[],
  type: SlugType,
  locale: Locale
): Promise<BulkValidationResponse> {
  const request: BulkValidationRequest = {
    slugs,
    type,
    locale,
  };

  return post<BulkValidationResponse>('/api/slug/bulk-validate', request, {
    locale,
  });
}

// ============================================================================
// Slug Suggestions
// ============================================================================

/**
 * Generate slug suggestions from title
 * POST /api/slug/suggest
 *
 * Generates slugified versions of title and checks availability.
 * Returns multiple suggestions if base slug is taken.
 *
 * @param title - Title to generate slugs from
 * @param type - Type of entity
 * @param locale - Language locale
 * @param max - Maximum number of suggestions (default: 5)
 * @returns List of suggested slugs with availability status
 *
 * @example
 * const suggestions = await generateSuggestions(
 *   "Declarații Premierului",
 *   "article",
 *   "ro"
 * );
 * // Returns: [
 * //   { slug: "declaratii-premierului", is_available: false },
 * //   { slug: "declaratii-premierului-2", is_available: true },
 * //   ...
 * // ]
 */
export async function generateSuggestions(
  title: string,
  type: SlugType,
  locale: Locale,
  max: number = 5
): Promise<SlugSuggestionsResponse> {
  const request: SlugSuggestionsRequest = {
    title,
    type,
    locale,
    max,
  };

  return post<SlugSuggestionsResponse>('/api/slug/suggest', request, {
    locale,
  });
}

/**
 * Get first available slug suggestion
 * Convenience function that returns only the first available slug
 */
export async function getFirstAvailableSlug(
  title: string,
  type: SlugType,
  locale: Locale
): Promise<string | null> {
  try {
    const result = await generateSuggestions(title, type, locale);
    const available = result.suggestions.find((s) => s.is_available);
    return available?.slug || null;
  } catch {
    return null;
  }
}
