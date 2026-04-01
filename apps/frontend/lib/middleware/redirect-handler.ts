/**
 * Redirect Handler for Middleware
 * Handles URL redirect checking using UrlRedirect entity
 * Updated to use /api/redirects/lookup endpoint (DECIZIE #6)
 */

import type { RedirectCheckResponse } from '../types/slug';
import type { RedirectStatusCode } from '../types/redirect';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';
const REDIRECT_CHECK_TIMEOUT = 3000; // 3 seconds for middleware
const MAX_REDIRECT_CHAIN_LENGTH = 5; // Prevent infinite loops

interface RedirectLookupResponse {
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
 * Check if URL needs redirect
 * Uses the new /api/redirects/lookup endpoint
 * Fast API call with timeout for middleware use
 */
export async function checkUrlRedirect(
  url: string,
  locale: string = 'ro'
): Promise<RedirectCheckResponse | null> {
  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), REDIRECT_CHECK_TIMEOUT);

    try {
      const params = new URLSearchParams({
        url,
        locale,
      });

      const response = await fetch(
        `${API_BASE_URL}/api/redirects/lookup?${params.toString()}`,
        {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
          },
          signal: controller.signal,
          cache: 'no-store', // Don't cache redirect checks
        }
      );

      clearTimeout(timeoutId);

      // 404 means no redirect found - this is expected and normal
      if (response.status === 404) {
        return null;
      }

      if (!response.ok) {
        return null;
      }

      const data: RedirectLookupResponse = await response.json();

      // Convert to RedirectCheckResponse format for backwards compatibility
      if (data.success && data.redirect) {
        return {
          success: true,
          has_redirect: true,
          final_url: data.redirect.new_url,
          chain_length: 1, // Direct redirect (no chain)
          chain: [
            {
              from: data.redirect.old_url,
              to: data.redirect.new_url,
              status_code: data.redirect.status_code as RedirectStatusCode,
              type: data.redirect.type,
              hit_count: 0, // Not available in lookup response
              created_at: new Date().toISOString(), // Not available in lookup response
            },
          ],
        };
      }

      return null;
    } finally {
      clearTimeout(timeoutId);
    }
  } catch (error) {
    // Fail silently - if redirect check fails, continue to page
    if (process.env.NODE_ENV === 'development') {
      console.error('[Middleware] Redirect check error:', error);
    }
    return null;
  }
}

/**
 * Extract redirect information from check response
 * Returns the final URL and status code
 */
export function extractRedirectInfo(response: RedirectCheckResponse): {
  shouldRedirect: boolean;
  finalUrl: string | null;
  statusCode: number;
  chainLength: number;
} {
  if (!response.success || !response.has_redirect) {
    return {
      shouldRedirect: false,
      finalUrl: null,
      statusCode: 200,
      chainLength: 0,
    };
  }

  // Check chain length - prevent long chains
  const chainLength = response.chain_length || 0;
  if (chainLength > MAX_REDIRECT_CHAIN_LENGTH) {
    console.warn(
      `[Middleware] Redirect chain too long (${chainLength}), skipping redirect`
    );
    return {
      shouldRedirect: false,
      finalUrl: null,
      statusCode: 200,
      chainLength,
    };
  }

  // Get first redirect status code (for 301 vs 302)
  const statusCode = response.chain?.[0]?.status_code || 301;

  return {
    shouldRedirect: true,
    finalUrl: response.final_url || null,
    statusCode,
    chainLength,
  };
}

/**
 * Check if redirect creates a loop
 * Compares final URL with original URL
 */
export function isRedirectLoop(originalUrl: string, finalUrl: string): boolean {
  const normalizeUrl = (url: string) => {
    return url.replace(/\/$/, '').toLowerCase();
  };

  return normalizeUrl(originalUrl) === normalizeUrl(finalUrl);
}

/**
 * Log redirect for monitoring (development only)
 */
export function logRedirect(
  originalUrl: string,
  finalUrl: string,
  statusCode: number,
  chainLength: number
): void {
  if (process.env.NODE_ENV === 'development') {
    console.log('[Middleware] Redirect:', {
      from: originalUrl,
      to: finalUrl,
      statusCode,
      chainLength,
    });
  }
}

/**
 * Build full URL from path
 */
export function buildFullUrl(path: string, baseUrl: string): string {
  // Remove trailing slash from base URL
  const cleanBaseUrl = baseUrl.replace(/\/$/, '');
  // Ensure path starts with /
  const cleanPath = path.startsWith('/') ? path : `/${path}`;
  return `${cleanBaseUrl}${cleanPath}`;
}
