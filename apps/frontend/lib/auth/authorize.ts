/**
 * Authorization Wrapper for Server Actions
 * Provides secure authorization with Zod validation
 *
 * @see NEXTJS_ALIGNMENT_PLAN.md - Phase 1 Security
 */

import { z } from 'zod';
import { getSession, type SessionPayload } from '@/lib/auth/session';

// ============================================================================
// Types
// ============================================================================

export type AuthorizeSuccess<T> = {
  success: true;
  data: T;
  session: SessionPayload;
};

export type AuthorizeFailure = {
  success: false;
  error: string;
  code: 'UNAUTHORIZED' | 'FORBIDDEN' | 'VALIDATION_ERROR';
  fieldErrors?: Record<string, string[]>;
};

export type AuthorizeResult<T> = AuthorizeSuccess<T> | AuthorizeFailure;

// ============================================================================
// Role Constants
// ============================================================================

export const ROLES = {
  USER: 'ROLE_USER',
  EDITOR: 'ROLE_EDITOR',
  JOURNALIST: 'ROLE_JOURNALIST',
  ADMIN: 'ROLE_ADMIN',
  SUPER_ADMIN: 'ROLE_SUPER_ADMIN',
} as const;

export type Role = typeof ROLES[keyof typeof ROLES];

// ============================================================================
// Authorization Functions
// ============================================================================

/**
 * Authorize a Server Action with optional Zod validation and role checks
 *
 * @example
 * // Simple authorization check (no input)
 * const result = await authorize();
 * if (!result.success) return result;
 * const { session } = result;
 *
 * @example
 * // With input validation
 * const schema = z.object({ id: z.number() });
 * const result = await authorize(schema, { id: 123 });
 * if (!result.success) return result;
 * const { data, session } = result;
 *
 * @example
 * // With role requirements
 * const result = await authorize(schema, data, [ROLES.EDITOR, ROLES.ADMIN]);
 */
export async function authorize(): Promise<AuthorizeResult<undefined>>;
export async function authorize<T>(
  schema: z.ZodSchema<T>,
  data: unknown
): Promise<AuthorizeResult<T>>;
export async function authorize<T>(
  schema: z.ZodSchema<T>,
  data: unknown,
  requiredRoles: Role[]
): Promise<AuthorizeResult<T>>;
export async function authorize<T>(
  schema?: z.ZodSchema<T>,
  data?: unknown,
  requiredRoles?: Role[]
): Promise<AuthorizeResult<T | undefined>> {
  // 1. Get and validate session
  const session = await getSession();

  if (!session) {
    return {
      success: false,
      error: 'Unauthorized: No active session',
      code: 'UNAUTHORIZED',
    };
  }

  // 2. Check session expiration
  if (new Date(session.expiresAt) <= new Date()) {
    return {
      success: false,
      error: 'Unauthorized: Session expired',
      code: 'UNAUTHORIZED',
    };
  }

  // 3. Check required roles if specified
  if (requiredRoles && requiredRoles.length > 0) {
    const hasRequiredRole = requiredRoles.some(role =>
      session.user.roles.includes(role)
    );

    if (!hasRequiredRole) {
      return {
        success: false,
        error: `Forbidden: Requires one of roles: ${requiredRoles.join(', ')}`,
        code: 'FORBIDDEN',
      };
    }
  }

  // 4. Validate input if schema provided
  if (schema && data !== undefined) {
    const result = schema.safeParse(data);

    if (!result.success) {
      const fieldErrors = result.error.flatten().fieldErrors as Record<string, string[]>;
      return {
        success: false,
        error: 'Validation failed',
        code: 'VALIDATION_ERROR',
        fieldErrors,
      };
    }

    return {
      success: true,
      data: result.data,
      session,
    };
  }

  // 5. Return success without data if no schema
  return {
    success: true,
    data: undefined as T,
    session,
  };
}

/**
 * Quick authorization check - just verifies session exists
 */
export async function isAuthorized(): Promise<boolean> {
  const session = await getSession();
  return session !== null && new Date(session.expiresAt) > new Date();
}

/**
 * Check if current user has specific role
 */
export async function hasRole(role: Role): Promise<boolean> {
  const session = await getSession();
  return session?.user.roles.includes(role) ?? false;
}

/**
 * Check if current user has any of the specified roles
 */
export async function hasAnyRole(roles: Role[]): Promise<boolean> {
  const session = await getSession();
  if (!session) return false;
  return roles.some(role => session.user.roles.includes(role));
}

/**
 * Check if current user has all of the specified roles
 */
export async function hasAllRoles(roles: Role[]): Promise<boolean> {
  const session = await getSession();
  if (!session) return false;
  return roles.every(role => session.user.roles.includes(role));
}

/**
 * Require authentication - throws if not authenticated
 * Use in Server Components and Route Handlers
 */
export async function requireAuth(): Promise<SessionPayload> {
  const session = await getSession();

  if (!session) {
    throw new Error('Unauthorized: No active session');
  }

  if (new Date(session.expiresAt) <= new Date()) {
    throw new Error('Unauthorized: Session expired');
  }

  return session;
}

/**
 * Require specific roles - throws if user doesn't have required roles
 */
export async function requireRoles(roles: Role[]): Promise<SessionPayload> {
  const session = await requireAuth();

  const hasRequiredRole = roles.some(role => session.user.roles.includes(role));

  if (!hasRequiredRole) {
    throw new Error(`Forbidden: Requires one of roles: ${roles.join(', ')}`);
  }

  return session;
}

// ============================================================================
// Common Zod Schemas for Reuse
// ============================================================================

export const CommonSchemas = {
  // ID validation
  id: z.number().int().positive(),
  idString: z.string().min(1).transform(Number).pipe(z.number().int().positive()),

  // Pagination
  pagination: z.object({
    page: z.number().int().positive().default(1),
    limit: z.number().int().min(1).max(100).default(20),
  }),

  // Locale
  locale: z.enum(['ro', 'en', 'ru']).default('ro'),

  // Slug
  slug: z.string().min(1).max(255).regex(/^[a-z0-9-]+$/),

  // Search query
  searchQuery: z.string().min(1).max(200).trim(),
} as const;
