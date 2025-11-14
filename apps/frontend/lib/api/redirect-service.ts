/**
 * Redirect API Service
 * Handles redirect statistics, health checks, and chain detection
 * Based on Sprint 1 backend API contracts
 */

import { get, post } from './client';
import type {
  RedirectStatistics,
  EntityRedirectsRequest,
  EntityRedirectsResponse,
  RedirectHealthResponse,
  FindChainsRequest,
  FindChainsResponse,
  RedirectType,
} from '../types/redirect';

// ============================================================================
// Redirect Statistics
// ============================================================================

/**
 * Get comprehensive redirect statistics
 * GET /api/redirects/statistics
 *
 * Returns statistics about all redirects in the system:
 * - Total count
 * - Breakdown by status code, type, locale
 * - Most used redirects
 * - Recent redirects
 * - Unused redirects count
 * - Detected chains
 *
 * @returns Complete redirect statistics
 *
 * @example
 * const stats = await getStatistics();
 * console.log(`Total redirects: ${stats.total_redirects}`);
 * console.log(`Chains detected: ${stats.chains_detected}`);
 */
export async function getStatistics(): Promise<RedirectStatistics> {
  return get<RedirectStatistics>('/api/redirects/statistics');
}

// ============================================================================
// Entity Redirects
// ============================================================================

/**
 * Get all redirects for a specific entity
 * GET /api/redirects/by-entity?entity_type=article&entity_id=123
 *
 * Returns all redirects associated with a specific article, category, or author.
 * Useful for viewing redirect history in admin panel.
 *
 * @param entityType - Type of entity ("article", "category", "author")
 * @param entityId - ID of the entity
 * @returns List of redirects for the entity
 *
 * @example
 * const redirects = await getByEntity("article", 123);
 * redirects.redirects.forEach(r => {
 *   console.log(`${r.old_url} → ${r.new_url} (${r.hit_count} hits)`);
 * });
 */
export async function getByEntity(
  entityType: RedirectType,
  entityId: number
): Promise<EntityRedirectsResponse> {
  const params = new URLSearchParams({
    entity_type: entityType,
    entity_id: entityId.toString(),
  });

  return get<EntityRedirectsResponse>(
    `/api/redirects/by-entity?${params.toString()}`
  );
}

// ============================================================================
// Health Check
// ============================================================================

/**
 * Get redirect system health status
 * GET /api/redirects/health
 *
 * Performs health check on redirect system and identifies issues:
 * - Circular redirects (A→B→A)
 * - Long redirect chains (A→B→C→D→E)
 * - Broken redirects (pointing to non-existent pages)
 * - Outdated redirects (very old, unused)
 *
 * Returns status, issues, statistics, and recommendations.
 *
 * @returns Health check results with issues and recommendations
 *
 * @example
 * const health = await getHealth();
 * if (health.status === 'error') {
 *   console.error('Critical redirect issues found:');
 *   health.issues.forEach(issue => {
 *     console.error(`- ${issue.description} (${issue.severity})`);
 *   });
 * }
 */
export async function getHealth(): Promise<RedirectHealthResponse> {
  return get<RedirectHealthResponse>('/api/redirects/health');
}

/**
 * Check if redirect system is healthy
 * Convenience function that returns boolean
 */
export async function isHealthy(): Promise<boolean> {
  try {
    const health = await getHealth();
    return health.status === 'healthy';
  } catch {
    return false;
  }
}

// ============================================================================
// Redirect Chains
// ============================================================================

/**
 * Find redirect chains in the system
 * POST /api/redirects/find-chains
 *
 * Detects and analyzes redirect chains:
 * - Simple chains: A→B→C
 * - Circular chains: A→B→C→A
 *
 * Long chains are inefficient and should be fixed.
 * Circular chains will cause infinite loops.
 *
 * @param minLength - Minimum chain length to return (default: 2)
 * @param limit - Maximum number of chains to return (default: 50)
 * @returns List of redirect chains
 *
 * @example
 * const chains = await findChains(3, 10);
 * chains.chains.forEach(chain => {
 *   console.log(`Chain length: ${chain.length}`);
 *   console.log(`Circular: ${chain.is_circular}`);
 *   chain.chain.forEach(link => {
 *     console.log(`  ${link.from} → ${link.to}`);
 *   });
 * });
 */
export async function findChains(
  minLength: number = 2,
  limit: number = 50
): Promise<FindChainsResponse> {
  const request: FindChainsRequest = {
    min_length: minLength,
    limit,
  };

  return post<FindChainsResponse>('/api/redirects/find-chains', request);
}

/**
 * Find circular redirects only
 * Convenience function that filters for circular chains
 */
export async function findCircularRedirects(
  limit: number = 50
): Promise<FindChainsResponse> {
  const result = await findChains(2, limit);

  return {
    ...result,
    chains: result.chains.filter((chain) => chain.is_circular),
    total_chains: result.chains.filter((chain) => chain.is_circular).length,
  };
}

/**
 * Find long redirect chains
 * Convenience function that filters for chains longer than threshold
 */
export async function findLongChains(
  minLength: number = 5,
  limit: number = 50
): Promise<FindChainsResponse> {
  return findChains(minLength, limit);
}

// ============================================================================
// Redirect Utilities
// ============================================================================

/**
 * Get redirect summary for dashboard
 * Combines statistics and health check for overview
 */
export async function getRedirectSummary(): Promise<{
  statistics: RedirectStatistics;
  health: RedirectHealthResponse;
}> {
  const [statistics, health] = await Promise.all([
    getStatistics(),
    getHealth(),
  ]);

  return { statistics, health };
}

/**
 * Check if entity has any redirects
 * Quick check without fetching full redirect list
 */
export async function hasRedirects(
  entityType: RedirectType,
  entityId: number
): Promise<boolean> {
  try {
    const result = await getByEntity(entityType, entityId);
    return result.count > 0;
  } catch {
    return false;
  }
}

// ============================================================================
// URL Redirect Lookup
// ============================================================================

export interface RedirectLookupResult {
  success: boolean;
  redirect?: {
    id: number;
    old_url: string;
    new_url: string;
    status_code: number;
    locale: string;
    type: string;
  };
  message?: string;
}

/**
 * Lookup redirect for a specific URL
 * GET /api/redirects/lookup?url=/old-path&locale=en
 *
 * Used by middleware to check if a URL should be redirected.
 * Returns redirect information if found, or null if no redirect exists.
 *
 * @param url - The URL path to check (e.g., "/old-category/article")
 * @param locale - The locale to check for (default: "ro")
 * @returns Redirect information or null
 *
 * @example
 * const redirect = await lookupRedirect("/old-path", "en");
 * if (redirect?.success && redirect.redirect) {
 *   // Redirect to redirect.redirect.new_url with redirect.redirect.status_code
 *   window.location.href = redirect.redirect.new_url;
 * }
 */
export async function lookupRedirect(
  url: string,
  locale: string = 'ro'
): Promise<RedirectLookupResult | null> {
  try {
    const params = new URLSearchParams({
      url,
      locale,
    });

    const result = await get<RedirectLookupResult>(
      `/api/redirects/lookup?${params.toString()}`
    );

    return result;
  } catch (error: any) {
    // 404 means no redirect found - this is expected
    if (error?.response?.status === 404) {
      return null;
    }

    // Other errors should be logged but not break the app
    console.error('Error looking up redirect:', error);
    return null;
  }
}
