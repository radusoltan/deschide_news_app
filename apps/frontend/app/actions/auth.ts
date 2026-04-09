/**
 * Server Actions for Authentication
 * Handles login, logout, and token refresh
 */

'use server';

// redirect removed - client handles redirect to preserve locale
import { z } from 'zod';
import { loginUser, refreshToken, getUserFromToken, isTokenExpired, isRefreshTokenExpired, type AuthTokens } from '@/lib/api-client';
import {
  createSession,
  deleteSession,
  getSession,
  updateSession,
} from '@/lib/auth/session';

// ============================================================================
// Validation Schemas
// ============================================================================

const LoginSchema = z.object({
  username: z.string().min(1, 'Username is required'),
  password: z.string().min(1, 'Password is required'),
});

export type LoginFormState = {
  errors?: {
    username?: string[];
    password?: string[];
    _form?: string[];
  };
  message?: string;
  username?: string;
};

// ============================================================================
// Login Action
// ============================================================================

/**
 * Login user with credentials
 * Creates encrypted session cookie
 */
export async function login(
  state: LoginFormState,
  formData: FormData
): Promise<LoginFormState> {
  // Validate form data
  const validatedFields = LoginSchema.safeParse({
    username: formData.get('username'),
    password: formData.get('password'),
  });

  if (!validatedFields.success) {
    return {
      errors: validatedFields.error.flatten().fieldErrors,
      username: formData.get('username') as string,
    };
  }

  const { username, password } = validatedFields.data;

  try {
    // Authenticate with backend
    const authTokens: AuthTokens = await loginUser({ username, password });

    // Get user info from JWT
    const userInfo = getUserFromToken(authTokens.token);
    if (!userInfo) {
      return {
        errors: {
          _form: ['Invalid token received from server'],
        },
      };
    }

    // Create session
    await createSession(authTokens, {
      username: userInfo.username,
      roles: userInfo.roles,
    });

    // Success - redirect will happen in component
    return {
      message: 'Login successful',
    };
  } catch (error: any) {
    console.error('Login error:', error);

    return {
      errors: {
        _form: [error.message || 'Failed to login. Please try again.'],
      },
      username: formData.get('username') as string,
    };
  }
}

// ============================================================================
// Logout Action
// ============================================================================

/**
 * Logout user
 * Deletes session cookie
 * Note: Client handles redirect to preserve locale
 */
export async function logout() {
  await deleteSession();
  // Don't call redirect() here - it throws an exception in server actions
  // which prevents client-side code from executing after await logout()
  // Let the client handle the redirect with the correct locale
}

// ============================================================================
// Token Refresh Action
// ============================================================================

/**
 * Refresh access token using refresh token
 * Updates session with new tokens
 *
 * @returns true if refresh successful, false otherwise
 */
export async function refreshAccessToken(): Promise<boolean> {
  const session = await getSession();
  if (!session) return false;

  const { refreshToken: refreshTok, refreshTokenExpiresAt } = session.tokens;

  // Check if refresh token is still valid
  if (isRefreshTokenExpired(refreshTokenExpiresAt)) {
    console.log('Refresh token expired, logging out');
    await deleteSession();
    return false;
  }

  try {
    // Refresh the token (only needs refresh_token)
    const newTokens = await refreshToken(refreshTok);

    // Update session with new tokens
    await updateSession(newTokens);

    return true;
  } catch (error: any) {
    console.error('Token refresh failed:', error);

    // Only delete session if the refresh token is truly rejected (401/403)
    // For network or transient errors, preserve the session so the user
    // can retry without being forced to re-login
    const status = error?.status ?? error?.code;
    if (status === 401 || status === 403) {
      await deleteSession();
    }

    return false;
  }
}

// ============================================================================
// Session Utilities
// ============================================================================

/**
 * Get current user from session
 */
export async function getCurrentUser() {
  const session = await getSession();
  return session?.user || null;
}

/**
 * Check if user is authenticated
 */
export async function isAuthenticated(): Promise<boolean> {
  const session = await getSession();
  return !!session;
}

/**
 * Ensure tokens are fresh before making API request
 * Automatically refreshes if access token is expired
 */
export async function ensureFreshToken(): Promise<string | null> {
  const session = await getSession();
  if (!session) return null;

  const { accessToken } = session.tokens;

  // If access token is still valid, return it
  if (!isTokenExpired(accessToken)) {
    return accessToken;
  }

  // Token expired, try to refresh
  const refreshed = await refreshAccessToken();
  if (!refreshed) return null;

  // Get new token from updated session
  const newSession = await getSession();
  return newSession?.tokens.accessToken || null;
}
