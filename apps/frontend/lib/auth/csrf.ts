/**
 * CSRF Protection Module
 * Provides Cross-Site Request Forgery protection for Server Actions
 *
 * @see NEXTJS_ALIGNMENT_PLAN.md - Phase 1 Security
 */

import { cookies, headers } from 'next/headers';
import * as crypto from 'crypto';

// ============================================================================
// Configuration
// ============================================================================

const CSRF_COOKIE_NAME = 'csrf-token';
const CSRF_HEADER_NAME = 'x-csrf-token';
const CSRF_TOKEN_LENGTH = 32; // 32 bytes = 64 hex characters

// ============================================================================
// Types
// ============================================================================

export type CsrfValidationSuccess = { valid: true };
export type CsrfValidationError = { valid: false; error: string };
export type CsrfValidationResult = CsrfValidationSuccess | CsrfValidationError;

// ============================================================================
// Token Generation and Management
// ============================================================================

/**
 * Generate a new CSRF token and store it in an HTTP-only cookie
 * Returns the token for inclusion in forms/headers
 */
export async function generateCsrfToken(): Promise<string> {
  const token = crypto.randomBytes(CSRF_TOKEN_LENGTH).toString('hex');

  const cookieStore = await cookies();
  cookieStore.set(CSRF_COOKIE_NAME, token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'strict',
    path: '/',
    maxAge: 60 * 60 * 24, // 24 hours
  });

  return token;
}

/**
 * Get the current CSRF token from cookie
 * Returns null if no token exists
 */
export async function getCsrfToken(): Promise<string | null> {
  const cookieStore = await cookies();
  return cookieStore.get(CSRF_COOKIE_NAME)?.value ?? null;
}

/**
 * Get or create a CSRF token
 * Returns existing token if valid, or generates a new one
 */
export async function getOrCreateCsrfToken(): Promise<string> {
  const existingToken = await getCsrfToken();

  if (existingToken && existingToken.length === CSRF_TOKEN_LENGTH * 2) {
    return existingToken;
  }

  return generateCsrfToken();
}

/**
 * Delete the CSRF token cookie
 * Call this on logout or when regenerating tokens
 */
export async function deleteCsrfToken(): Promise<void> {
  const cookieStore = await cookies();
  cookieStore.delete(CSRF_COOKIE_NAME);
}

// ============================================================================
// Token Validation
// ============================================================================

/**
 * Validate a CSRF token against the stored cookie token
 * Use constant-time comparison to prevent timing attacks
 */
export async function validateCsrfToken(token: string): Promise<CsrfValidationResult> {
  if (!token || typeof token !== 'string') {
    return { valid: false, error: 'CSRF token is required' };
  }

  if (token.length !== CSRF_TOKEN_LENGTH * 2) {
    return { valid: false, error: 'Invalid CSRF token format' };
  }

  const storedToken = await getCsrfToken();

  if (!storedToken) {
    return { valid: false, error: 'No CSRF token in session' };
  }

  // Use constant-time comparison to prevent timing attacks
  const isValid = crypto.timingSafeEqual(
    Buffer.from(token, 'hex'),
    Buffer.from(storedToken, 'hex')
  );

  if (!isValid) {
    return { valid: false, error: 'CSRF token mismatch' };
  }

  return { valid: true };
}

/**
 * Validate CSRF token from request headers
 * Automatically extracts token from x-csrf-token header
 */
export async function validateCsrfFromHeaders(): Promise<CsrfValidationResult> {
  const headerStore = await headers();
  const token = headerStore.get(CSRF_HEADER_NAME);

  if (!token) {
    return { valid: false, error: `Missing ${CSRF_HEADER_NAME} header` };
  }

  return validateCsrfToken(token);
}

// ============================================================================
// Server Action Wrapper with CSRF Protection
// ============================================================================

/**
 * Wrap a Server Action with CSRF protection
 *
 * @example
 * // In your Server Action file
 * export const deleteItem = withCsrfProtection(async (id: number) => {
 *   // Your action logic here
 *   return { success: true };
 * });
 *
 * // In your component
 * const handleDelete = async () => {
 *   const csrfToken = await getCsrfToken();
 *   const result = await deleteItem(itemId, csrfToken);
 * };
 */
export function withCsrfProtection<TArgs extends unknown[], TReturn>(
  action: (...args: TArgs) => Promise<TReturn>
): (...args: [...TArgs, string]) => Promise<TReturn | { success: false; error: string }> {
  return async (...args) => {
    // Last argument should be the CSRF token
    const csrfToken = args[args.length - 1] as string;
    const actionArgs = args.slice(0, -1) as unknown as TArgs;

    const validation = await validateCsrfToken(csrfToken);

    if (!validation.valid) {
      return { success: false, error: validation.error };
    }

    return action(...actionArgs);
  };
}

// ============================================================================
// React Hook Support (Client Component)
// ============================================================================

/**
 * Server Action to get CSRF token for client components
 * Use this in client components to get the token before form submission
 */
export async function fetchCsrfToken(): Promise<string> {
  'use server';
  return getOrCreateCsrfToken();
}

// ============================================================================
// Additional Security Checks
// ============================================================================

/**
 * Validate request origin matches allowed origins
 * Additional layer of CSRF protection
 */
export async function validateOrigin(allowedOrigins: string[]): Promise<boolean> {
  const headerStore = await headers();
  const origin = headerStore.get('origin');
  const referer = headerStore.get('referer');

  // Check origin header first
  if (origin) {
    return allowedOrigins.some(allowed =>
      origin === allowed || origin.startsWith(allowed)
    );
  }

  // Fall back to referer if no origin (same-origin requests)
  if (referer) {
    try {
      const refererUrl = new URL(referer);
      return allowedOrigins.some(allowed => {
        const allowedUrl = new URL(allowed);
        return refererUrl.origin === allowedUrl.origin;
      });
    } catch {
      return false;
    }
  }

  // No origin or referer - allow for same-origin requests in development
  return process.env.NODE_ENV === 'development';
}

/**
 * Combined CSRF and origin validation for maximum security
 */
export async function validateRequest(
  csrfToken: string,
  allowedOrigins: string[]
): Promise<CsrfValidationResult> {
  // Validate CSRF token
  const csrfResult = await validateCsrfToken(csrfToken);
  if (!csrfResult.valid) {
    return csrfResult;
  }

  // Validate origin
  const originValid = await validateOrigin(allowedOrigins);
  if (!originValid) {
    return { valid: false, error: 'Invalid request origin' };
  }

  return { valid: true };
}
