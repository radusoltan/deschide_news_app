import { redirect } from 'next/navigation';
import { NextRequest, NextResponse } from 'next/server';

/**
 * Short Link Redirect Route Handler
 *
 * Handles short URLs in the format: /s/{code}
 *
 * Performance-optimized:
 * - Server-side redirect (no client rendering)
 * - Backend handles redirect + analytics atomically
 * - Fast response time (< 100ms target)
 *
 * Flow:
 * 1. Extract short code from URL params
 * 2. Call backend /s/{code} endpoint (redirect + analytics)
 * 3. Follow the redirect or handle 404
 */

export async function GET(
  request: NextRequest,
  context: { params: Promise<{ code: string }> }
) {
  const { code } = await context.params;

  // Validate code format (alphanumeric, dashes, underscores, 1-50 chars)
  if (!code || !/^[a-zA-Z0-9_-]{1,50}$/.test(code)) {
    // Return 404 response instead of redirect for invalid codes
    return new NextResponse('Invalid short link code', { status: 404 });
  }

  try {
    // Use internal backend URL (not public)
    const backendUrl = process.env.NEXT_PUBLIC_API_URL ?? '';
    const shortLinkUrl = `${backendUrl}/s/${code}`;

    // Fetch with redirect: 'manual' to handle redirects ourselves
    const response = await fetch(shortLinkUrl, {
      method: 'GET',
      redirect: 'manual', // Don't follow redirects automatically
      headers: {
        'User-Agent': request.headers.get('user-agent') || 'Next.js Frontend',
        'X-Forwarded-For': request.headers.get('x-forwarded-for') || request.headers.get('x-real-ip') || 'unknown',
        'Referer': request.headers.get('referer') || '',
      },
    });

    // Backend returns 301/302 redirect with Location header
    if (response.status === 301 || response.status === 302) {
      const location = response.headers.get('location');

      if (location) {
        // Use Next.js redirect for proper handling
        // This will issue a 307 (temporary) or 308 (permanent) redirect
        redirect(location);
      }
    }

    // If backend returns 404, show 404 page
    if (response.status === 404) {
      return new NextResponse('Short link not found', { status: 404 });
    }

    // Unexpected response, log and return 500
    console.error(`[Short Link] Unexpected response from backend: ${response.status} for code: ${code}`);
    return new NextResponse('Internal server error', { status: 500 });

  } catch (error) {
    // Check if error is from redirect() call
    if (error instanceof Error && error.message === 'NEXT_REDIRECT') {
      throw error; // Re-throw redirect errors
    }

    // Network error or backend unreachable
    console.error(`[Short Link] Error fetching short link for code: ${code}`, error);

    // Return 503 on backend connection error
    return new NextResponse('Service temporarily unavailable', { status: 503 });
  }
}

/**
 * Route Segment Config
 *
 * - dynamic: 'force-dynamic' - Always server-side rendered (no caching)
 * - runtime: 'nodejs' - Use Node.js runtime (not Edge) for fetch compatibility
 */
export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';
