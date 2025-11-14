/**
 * Session Management with JWT Tokens
 * Uses encrypted cookies for secure token storage
 */

import { SignJWT, jwtVerify } from 'jose';
import { cookies } from 'next/headers';
import type { AuthTokens } from '../api-client';

// ============================================================================
// Configuration
// ============================================================================

const SESSION_COOKIE_NAME = 'session';
const SESSION_DURATION = 30 * 24 * 60 * 60 * 1000; // 30 days
const SECRET_KEY = process.env.SESSION_SECRET || 'your-secret-key-change-in-production';
const key = new TextEncoder().encode(SECRET_KEY);

// ============================================================================
// Types
// ============================================================================

export interface SessionPayload {
  user: {
    username: string;
    roles: string[];
  };
  tokens: {
    accessToken: string;
    refreshToken: string;
    refreshTokenExpiresAt: number;
  };
  expiresAt: Date;
}

// ============================================================================
// Session Encryption/Decryption
// ============================================================================

/**
 * Encrypt session payload to JWT
 */
export async function encrypt(payload: SessionPayload): Promise<string> {
  return new SignJWT(payload as any)
    .setProtectedHeader({ alg: 'HS256' })
    .setIssuedAt()
    .setExpirationTime(payload.expiresAt)
    .sign(key);
}

/**
 * Decrypt and verify session JWT
 */
export async function decrypt(session: string): Promise<SessionPayload | null> {
  try {
    const { payload } = await jwtVerify(session, key, {
      algorithms: ['HS256'],
    });
    return payload as unknown as SessionPayload;
  } catch (error) {
    console.error('Failed to verify session:', error);
    return null;
  }
}

// ============================================================================
// Session CRUD Operations
// ============================================================================

/**
 * Create new session from auth tokens
 */
export async function createSession(authTokens: AuthTokens, userInfo: { username: string; roles: string[] }) {
  const expiresAt = new Date(Date.now() + SESSION_DURATION);

  const session: SessionPayload = {
    user: userInfo,
    tokens: {
      accessToken: authTokens.token,
      refreshToken: authTokens.refresh_token,
      refreshTokenExpiresAt: authTokens.refresh_token_expires_at,
    },
    expiresAt,
  };

  const encryptedSession = await encrypt(session);

  (await cookies()).set(SESSION_COOKIE_NAME, encryptedSession, {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    expires: expiresAt,
    sameSite: 'lax',
    path: '/',
  });
}

/**
 * Get current session
 */
export async function getSession(): Promise<SessionPayload | null> {
  const cookie = (await cookies()).get(SESSION_COOKIE_NAME)?.value;
  if (!cookie) return null;

  return decrypt(cookie);
}

/**
 * Update session with new tokens
 */
export async function updateSession(authTokens: AuthTokens) {
  const session = await getSession();
  if (!session) return;

  session.tokens.accessToken = authTokens.token;
  session.tokens.refreshToken = authTokens.refresh_token;
  session.tokens.refreshTokenExpiresAt = authTokens.refresh_token_expires_at;

  const encryptedSession = await encrypt(session);

  (await cookies()).set(SESSION_COOKIE_NAME, encryptedSession, {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    expires: session.expiresAt,
    sameSite: 'lax',
    path: '/',
  });
}

/**
 * Delete session (logout)
 */
export async function deleteSession() {
  (await cookies()).delete(SESSION_COOKIE_NAME);
}

/**
 * Check if session is valid and not expired
 */
export async function isSessionValid(): Promise<boolean> {
  const session = await getSession();
  if (!session) return false;

  return new Date(session.expiresAt) > new Date();
}

/**
 * Get access token from session
 */
export async function getAccessToken(): Promise<string | null> {
  const session = await getSession();
  return session?.tokens.accessToken || null;
}

/**
 * Get refresh token from session
 */
export async function getRefreshToken(): Promise<string | null> {
  const session = await getSession();
  return session?.tokens.refreshToken || null;
}

/**
 * Check if user has specific role
 */
export async function hasRole(role: string): Promise<boolean> {
  const session = await getSession();
  return session?.user.roles.includes(role) || false;
}

/**
 * Check if user has any of the specified roles
 */
export async function hasAnyRole(roles: string[]): Promise<boolean> {
  const session = await getSession();
  if (!session) return false;

  return roles.some(role => session.user.roles.includes(role));
}
