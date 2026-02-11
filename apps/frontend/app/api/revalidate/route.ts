/**
 * On-Demand Revalidation API Endpoint
 * Allows the Symfony backend to trigger Next.js cache invalidation
 *
 * @see NEXTJS_ALIGNMENT_PLAN.md - Phase 1 Security, Section 4.6
 */

import { NextRequest, NextResponse } from 'next/server';
import { revalidateTag, revalidatePath } from 'next/cache';
import { z } from 'zod';
import { checkRateLimit, RATE_LIMITS, getRateLimitHeaders } from '@/lib/auth/rate-limit';

// ============================================================================
// Configuration
// ============================================================================

const REVALIDATE_SECRET = process.env.REVALIDATE_SECRET;

// ============================================================================
// Zod Schemas
// ============================================================================

const RevalidateRequestSchema = z.object({
  tags: z.array(z.string().min(1).max(100)).optional(),
  paths: z.array(z.string().min(1).max(500)).optional(),
}).refine(
  data => (data.tags && data.tags.length > 0) || (data.paths && data.paths.length > 0),
  { message: 'At least one tag or path must be provided' }
);

// ============================================================================
// Types
// ============================================================================

interface RevalidationLog {
  timestamp: number;
  tags: string[];
  paths: string[];
  success: boolean;
  errors?: string[];
}

// ============================================================================
// Route Handlers
// ============================================================================

/**
 * POST /api/revalidate
 * Trigger cache revalidation for specific tags or paths
 *
 * Headers:
 * - x-revalidate-secret: Secret token for authentication
 *
 * Body:
 * {
 *   "tags": ["article-123", "homepage"],
 *   "paths": ["/ro/article/my-article"]
 * }
 *
 * Response:
 * {
 *   "revalidated": true,
 *   "tags": ["article-123", "homepage"],
 *   "paths": ["/ro/article/my-article"],
 *   "timestamp": 1234567890
 * }
 */
export async function POST(request: NextRequest) {
  // 1. Check if secret is configured
  if (!REVALIDATE_SECRET) {
    console.error('[Revalidate API] REVALIDATE_SECRET not configured');
    return NextResponse.json(
      { error: 'Revalidation not configured' },
      { status: 503 }
    );
  }

  // 2. Validate secret
  const secret = request.headers.get('x-revalidate-secret');
  if (!secret || secret !== REVALIDATE_SECRET) {
    console.warn('[Revalidate API] Invalid or missing secret');
    return NextResponse.json(
      { error: 'Unauthorized' },
      { status: 401 }
    );
  }

  // 3. Rate limiting (using IP or source identifier)
  const sourceIp = request.headers.get('x-forwarded-for')?.split(',')[0].trim()
    || request.headers.get('x-real-ip')
    || 'backend';

  const rateLimitResult = await checkRateLimit(sourceIp, {
    ...RATE_LIMITS.WRITE,
    prefix: 'revalidate',
  });

  if (!rateLimitResult.allowed) {
    const headers = getRateLimitHeaders(rateLimitResult);
    return NextResponse.json(
      { error: 'Rate limit exceeded', retryAfter: Math.ceil(rateLimitResult.resetIn / 1000) },
      { status: 429, headers }
    );
  }

  // 4. Parse and validate request body
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return NextResponse.json(
      { error: 'Invalid JSON body' },
      { status: 400 }
    );
  }

  const validationResult = RevalidateRequestSchema.safeParse(body);
  if (!validationResult.success) {
    return NextResponse.json(
      {
        error: 'Validation failed',
        details: validationResult.error.flatten().fieldErrors,
      },
      { status: 400 }
    );
  }

  const { tags = [], paths = [] } = validationResult.data;

  // 5. Perform revalidation
  const log: RevalidationLog = {
    timestamp: Date.now(),
    tags,
    paths,
    success: true,
    errors: [],
  };

  try {
    // Revalidate tags
    // Next.js 16 requires second argument for cacheLife profile
    // 'max' triggers stale-while-revalidate behavior
    for (const tag of tags) {
      try {
        revalidateTag(tag, 'max');
        console.log(`[Revalidate API] Revalidated tag: ${tag}`);
      } catch (error) {
        const errorMsg = `Failed to revalidate tag ${tag}: ${error instanceof Error ? error.message : 'Unknown error'}`;
        console.error(`[Revalidate API] ${errorMsg}`);
        log.errors?.push(errorMsg);
      }
    }

    // Revalidate paths
    for (const path of paths) {
      try {
        revalidatePath(path, 'page');
        console.log(`[Revalidate API] Revalidated path: ${path}`);
      } catch (error) {
        const errorMsg = `Failed to revalidate path ${path}: ${error instanceof Error ? error.message : 'Unknown error'}`;
        console.error(`[Revalidate API] ${errorMsg}`);
        log.errors?.push(errorMsg);
      }
    }

    // Check if any errors occurred
    if (log.errors && log.errors.length > 0) {
      log.success = false;
    }

    // 6. Return response
    const response: Record<string, unknown> = {
      revalidated: log.success,
      tags,
      paths,
      timestamp: log.timestamp,
    };

    if (!log.success) {
      response.errors = log.errors;
    }

    const headers = getRateLimitHeaders(rateLimitResult);

    return NextResponse.json(response, {
      status: log.success ? 200 : 207, // 207 Multi-Status if partial success
      headers,
    });
  } catch (error) {
    console.error('[Revalidate API] Unexpected error:', error);
    return NextResponse.json(
      { error: 'Internal server error' },
      { status: 500 }
    );
  }
}

/**
 * GET /api/revalidate
 * Health check endpoint for the revalidation service
 */
export async function GET() {
  return NextResponse.json({
    status: 'ok',
    configured: !!REVALIDATE_SECRET,
    timestamp: Date.now(),
  });
}
