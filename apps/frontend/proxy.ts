/**
 * Proxy Configuration (Next.js 16)
 * Migrated from middleware.ts to proxy.ts
 *
 * @see NEXTJS_ALIGNMENT_PLAN.md - Phase 3 Data Fetching Optimization
 * @see https://nextjs.org/docs/app/api-reference/file-conventions/proxy
 *
 * Key differences from middleware:
 * - Runs on Node.js runtime (not Edge)
 * - Focus on lightweight routing logic
 * - No Edge runtime limitation
 */

import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';
import { i18nRouter } from 'next-i18n-router';
import i18nConfig from './i18nConfig';
import { decrypt } from '@/lib/auth/session-edge';
import {
  checkUrlRedirect,
  extractRedirectInfo,
  isRedirectLoop,
  logRedirect,
} from '@/lib/middleware/redirect-handler';
import {
  detectLocale,
  getPathWithoutLocale,
} from '@/lib/middleware/locale-handler';
import { handleMiddlewareError } from '@/lib/middleware/error-handler';
import { shouldSkipRedirectCheck } from '@/lib/utils/redirect-utils';

// ============================================================================
// Configuration
// ============================================================================

// Routes that require authentication
const protectedRoutes = ['/dashboard', '/admin', '/profile'];

// Routes that should redirect to home if authenticated
const authRoutes = ['/login'];

// ============================================================================
// Proxy Function (replaces middleware)
// ============================================================================

export async function proxy(request: NextRequest) {
  try {
    const path = request.nextUrl.pathname;

    // ========================================================================
    // 0. SKIP FOR SPECIAL PATHS
    // ========================================================================
    // Skip proxy for .well-known paths (used by browsers/tools)
    if (path.startsWith('/.well-known')) {
      return NextResponse.next();
    }

    // Remove locale prefix for route matching
    const pathWithoutLocale = getPathWithoutLocale(path);

    // ========================================================================
    // 1. REDIRECT CHECK (if not skipped)
    // ========================================================================
    // Detect locale first for redirect check
    const locale = detectLocale(request);

    if (!shouldSkipRedirectCheck(pathWithoutLocale)) {
      const redirectResult = await checkUrlRedirect(pathWithoutLocale, locale);

      if (redirectResult) {
        const redirectInfo = extractRedirectInfo(redirectResult);

        if (redirectInfo.shouldRedirect && redirectInfo.finalUrl) {
          // Check for redirect loop
          if (isRedirectLoop(pathWithoutLocale, redirectInfo.finalUrl)) {
            console.error('[Proxy] Redirect loop detected:', {
              from: pathWithoutLocale,
              to: redirectInfo.finalUrl,
            });
          } else {
            // Log redirect
            logRedirect(
              pathWithoutLocale,
              redirectInfo.finalUrl,
              redirectInfo.statusCode,
              redirectInfo.chainLength
            );

            // Perform redirect
            const redirectUrl = new URL(redirectInfo.finalUrl, request.url);

            // Preserve query parameters
            request.nextUrl.searchParams.forEach((value, key) => {
              redirectUrl.searchParams.set(key, value);
            });

            return NextResponse.redirect(redirectUrl, {
              status: redirectInfo.statusCode,
            });
          }
        }
      }
    }

    // ========================================================================
    // 2. AUTHENTICATION CHECK (lightweight - no DB calls)
    // ========================================================================
    const isProtectedRoute = protectedRoutes.some((route) =>
      pathWithoutLocale.startsWith(route)
    );

    const isAuthRoute = authRoutes.some((route) =>
      pathWithoutLocale.startsWith(route)
    );

    // Get session cookie (lightweight check)
    const cookie = request.cookies.get('session')?.value;
    const hasSession = !!cookie;

    // For protected routes, do a quick session existence check
    // Full session validation happens in the route handler
    if (isProtectedRoute && !hasSession) {
      const loginUrl = new URL(`/${locale}/login`, request.url);
      loginUrl.searchParams.set('from', `/${locale}${pathWithoutLocale}`);
      return NextResponse.redirect(loginUrl);
    }

    // Optional: Decrypt session for more accurate auth check
    // Only do this if needed, as it adds latency
    if (isProtectedRoute && hasSession) {
      const session = await decrypt(cookie);
      if (!session) {
        const loginUrl = new URL(`/${locale}/login`, request.url);
        loginUrl.searchParams.set('from', `/${locale}${pathWithoutLocale}`);
        return NextResponse.redirect(loginUrl);
      }
    }

    // ========================================================================
    // 3. LOCALE HANDLING (i18n)
    // ========================================================================
    const response = i18nRouter(request, i18nConfig);

    // Set locale cookie if not already set
    if (!request.cookies.get('NEXT_LOCALE')) {
      response.cookies.set('NEXT_LOCALE', locale, {
        maxAge: 365 * 24 * 60 * 60, // 1 year
        path: '/',
        sameSite: 'lax',
      });
    }

    // ========================================================================
    // 4. CACHE HEADERS & PERFORMANCE OPTIMIZATION
    // ========================================================================

    // Static assets from Next.js - cache for 1 year (immutable)
    if (path.startsWith('/_next/static/')) {
      response.headers.set('Cache-Control', 'public, max-age=31536000, immutable');
    }
    // Images - cache for 30 days with stale-while-revalidate
    else if (path.match(/\.(jpg|jpeg|png|webp|avif|gif|svg|ico)$/)) {
      response.headers.set('Cache-Control', 'public, max-age=2592000, stale-while-revalidate=86400');
    }
    // Fonts - cache for 1 year
    else if (path.match(/\.(woff|woff2|ttf|otf|eot)$/)) {
      response.headers.set('Cache-Control', 'public, max-age=31536000, immutable');
    }
    // API responses - cache for 2 minutes (except auth/vitals)
    else if (path.startsWith('/api/') && !path.includes('/web-vitals') && !path.includes('/auth/')) {
      response.headers.set('Cache-Control', 'public, max-age=120, stale-while-revalidate=60');
    }
    // HTML pages - no browser cache so Mercure-driven router.refresh() always fetches fresh content
    else if (!path.startsWith('/api/') && !path.startsWith('/_next/')) {
      response.headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    // ========================================================================
    // 5. SECURITY HEADERS
    // ========================================================================
    response.headers.set('X-Content-Type-Options', 'nosniff');
    response.headers.set('X-Frame-Options', 'SAMEORIGIN');
    response.headers.set('X-XSS-Protection', '1; mode=block');
    response.headers.set('Referrer-Policy', 'strict-origin-when-cross-origin');

    return response;
  } catch (error) {
    // Handle errors gracefully - don't break the request
    return handleMiddlewareError(error, request);
  }
}

// ============================================================================
// Proxy Configuration
// ============================================================================

/**
 * Proxy Configuration
 * Applies proxy to specific routes only
 *
 * Excludes:
 * - /api/* - API routes
 * - /_next/* - Next.js internal files
 * - /static/* - Static assets
 * - Files with extensions (.jpg, .css, etc.)
 * - /favicon.ico, /robots.txt, /sitemap.xml
 */
export const config = {
  matcher: [
    /*
     * Match all request paths except:
     * - api (API routes)
     * - _next/static (static files)
     * - _next/image (image optimization files)
     * - .well-known (browser/system requests)
     * - favicon.ico, robots.txt, sitemap.xml (static files)
     * - Files with extensions (images, css, js, etc.)
     * - XML sitemaps (sitemap.xml, news-sitemap.xml, image-sitemap.xml)
     */
    '/((?!api|_next/static|_next/image|\\.well-known|favicon.ico|robots.txt|.*\\.xml|.*\\.(?:jpg|jpeg|png|gif|svg|ico|css|js|woff|woff2|ttf|eot)).*)',
  ],
};
