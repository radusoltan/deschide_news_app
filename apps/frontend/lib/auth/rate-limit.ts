/**
 * Rate Limiting Module for Server Actions
 * Provides in-memory rate limiting with sliding window algorithm
 *
 * @see NEXTJS_ALIGNMENT_PLAN.md - Phase 1 Security
 *
 * Note: For production with multiple server instances, consider using
 * Redis-based rate limiting instead of in-memory storage.
 */

import { headers } from 'next/headers';

// ============================================================================
// Configuration
// ============================================================================

export interface RateLimitConfig {
  /** Maximum number of requests allowed in the window */
  maxRequests: number;
  /** Time window in milliseconds */
  windowMs: number;
  /** Optional identifier prefix for different limiters */
  prefix?: string;
}

export interface RateLimitResult {
  /** Whether the request is allowed */
  allowed: boolean;
  /** Number of requests remaining in current window */
  remaining: number;
  /** Time in milliseconds until the limit resets */
  resetIn: number;
  /** Current request count */
  current: number;
  /** Maximum requests allowed */
  limit: number;
}

interface RateLimitEntry {
  count: number;
  resetAt: number;
}

// ============================================================================
// Preset Configurations
// ============================================================================

export const RATE_LIMITS = {
  /** Standard API requests: 100 requests per minute */
  STANDARD: {
    maxRequests: 100,
    windowMs: 60 * 1000,
    prefix: 'standard',
  },
  /** Authentication actions: 5 requests per minute */
  AUTH: {
    maxRequests: 5,
    windowMs: 60 * 1000,
    prefix: 'auth',
  },
  /** Form submissions: 10 requests per minute */
  FORM: {
    maxRequests: 10,
    windowMs: 60 * 1000,
    prefix: 'form',
  },
  /** Write operations: 30 requests per minute */
  WRITE: {
    maxRequests: 30,
    windowMs: 60 * 1000,
    prefix: 'write',
  },
  /** Search/heavy operations: 20 requests per minute */
  SEARCH: {
    maxRequests: 20,
    windowMs: 60 * 1000,
    prefix: 'search',
  },
  /** Strict limit for sensitive operations: 3 requests per 5 minutes */
  STRICT: {
    maxRequests: 3,
    windowMs: 5 * 60 * 1000,
    prefix: 'strict',
  },
} as const;

// ============================================================================
// In-Memory Storage
// ============================================================================

// Using Map for O(1) lookups
const rateLimitStore = new Map<string, RateLimitEntry>();

// Cleanup interval to prevent memory leaks
const CLEANUP_INTERVAL_MS = 60 * 1000; // 1 minute
let cleanupInterval: NodeJS.Timeout | null = null;

/**
 * Start the cleanup interval if not already running
 */
function ensureCleanupRunning(): void {
  if (cleanupInterval) return;

  cleanupInterval = setInterval(() => {
    const now = Date.now();
    const entries = Array.from(rateLimitStore.entries());
    for (const [key, entry] of entries) {
      if (entry.resetAt <= now) {
        rateLimitStore.delete(key);
      }
    }
  }, CLEANUP_INTERVAL_MS);

  // Don't prevent Node.js from exiting
  if (cleanupInterval.unref) {
    cleanupInterval.unref();
  }
}

// ============================================================================
// Rate Limiting Functions
// ============================================================================

/**
 * Get client identifier from request headers
 * Uses IP address or falls back to a placeholder for development
 */
export async function getClientIdentifier(): Promise<string> {
  const headerStore = await headers();

  // Try various headers for client IP (in order of preference)
  const forwardedFor = headerStore.get('x-forwarded-for');
  const realIp = headerStore.get('x-real-ip');
  const cfConnectingIp = headerStore.get('cf-connecting-ip');

  if (cfConnectingIp) return cfConnectingIp;
  if (realIp) return realIp;
  if (forwardedFor) return forwardedFor.split(',')[0].trim();

  // Fallback for development
  return 'localhost';
}

/**
 * Check rate limit for an identifier
 *
 * @example
 * const result = await checkRateLimit(userId, RATE_LIMITS.AUTH);
 * if (!result.allowed) {
 *   return { error: `Rate limited. Try again in ${Math.ceil(result.resetIn / 1000)}s` };
 * }
 */
export async function checkRateLimit(
  identifier: string,
  config: RateLimitConfig
): Promise<RateLimitResult> {
  ensureCleanupRunning();

  const key = config.prefix ? `${config.prefix}:${identifier}` : identifier;
  const now = Date.now();

  const entry = rateLimitStore.get(key);

  // No existing entry - create new one
  if (!entry || entry.resetAt <= now) {
    rateLimitStore.set(key, {
      count: 1,
      resetAt: now + config.windowMs,
    });

    return {
      allowed: true,
      remaining: config.maxRequests - 1,
      resetIn: config.windowMs,
      current: 1,
      limit: config.maxRequests,
    };
  }

  // Existing entry - check limit
  const remaining = config.maxRequests - entry.count - 1;
  const resetIn = entry.resetAt - now;

  if (entry.count >= config.maxRequests) {
    return {
      allowed: false,
      remaining: 0,
      resetIn,
      current: entry.count,
      limit: config.maxRequests,
    };
  }

  // Increment count
  entry.count += 1;
  rateLimitStore.set(key, entry);

  return {
    allowed: true,
    remaining: Math.max(0, remaining),
    resetIn,
    current: entry.count,
    limit: config.maxRequests,
  };
}

/**
 * Check rate limit using client IP from request headers
 */
export async function checkRateLimitByIp(
  config: RateLimitConfig
): Promise<RateLimitResult> {
  const identifier = await getClientIdentifier();
  return checkRateLimit(identifier, config);
}

/**
 * Reset rate limit for a specific identifier
 * Useful after successful authentication to give fresh limit
 */
export function resetRateLimit(
  identifier: string,
  prefix?: string
): void {
  const key = prefix ? `${prefix}:${identifier}` : identifier;
  rateLimitStore.delete(key);
}

// ============================================================================
// Server Action Wrapper with Rate Limiting
// ============================================================================

/**
 * Wrap a Server Action with rate limiting
 *
 * @example
 * export const submitForm = withRateLimit(
 *   async (formData: FormData) => {
 *     // Your action logic
 *     return { success: true };
 *   },
 *   RATE_LIMITS.FORM
 * );
 */
export function withRateLimit<TArgs extends unknown[], TReturn>(
  action: (...args: TArgs) => Promise<TReturn>,
  config: RateLimitConfig
): (
  ...args: TArgs
) => Promise<TReturn | { success: false; error: string; retryAfter: number }> {
  return async (...args) => {
    const result = await checkRateLimitByIp(config);

    if (!result.allowed) {
      const retryAfter = Math.ceil(result.resetIn / 1000);
      return {
        success: false,
        error: `Too many requests. Please try again in ${retryAfter} seconds.`,
        retryAfter,
      };
    }

    return action(...args);
  };
}

/**
 * Create a rate limiter for a specific use case
 * Returns a function that can be called to check the limit
 *
 * @example
 * const loginLimiter = createRateLimiter(RATE_LIMITS.AUTH);
 *
 * export async function loginAction(credentials: Credentials) {
 *   const { allowed, error } = await loginLimiter(credentials.email);
 *   if (!allowed) return { success: false, error };
 *   // ... login logic
 * }
 */
export function createRateLimiter(config: RateLimitConfig) {
  return async (
    identifier?: string
  ): Promise<{ allowed: boolean; error?: string; result: RateLimitResult }> => {
    const id = identifier || (await getClientIdentifier());
    const result = await checkRateLimit(id, config);

    if (!result.allowed) {
      const retryAfter = Math.ceil(result.resetIn / 1000);
      return {
        allowed: false,
        error: `Rate limit exceeded. Try again in ${retryAfter} seconds.`,
        result,
      };
    }

    return { allowed: true, result };
  };
}

// ============================================================================
// Response Headers Helper
// ============================================================================

/**
 * Get rate limit headers to include in API responses
 * Follows standard rate limit header conventions
 */
export function getRateLimitHeaders(result: RateLimitResult): Record<string, string> {
  return {
    'X-RateLimit-Limit': String(result.limit),
    'X-RateLimit-Remaining': String(result.remaining),
    'X-RateLimit-Reset': String(Math.ceil(result.resetIn / 1000)),
  };
}

// ============================================================================
// Development Utilities
// ============================================================================

/**
 * Get current rate limit store size (for debugging)
 */
export function getRateLimitStoreSize(): number {
  return rateLimitStore.size;
}

/**
 * Clear all rate limits (for testing)
 */
export function clearAllRateLimits(): void {
  rateLimitStore.clear();
}

/**
 * Stop cleanup interval (for testing/cleanup)
 */
export function stopCleanupInterval(): void {
  if (cleanupInterval) {
    clearInterval(cleanupInterval);
    cleanupInterval = null;
  }
}
