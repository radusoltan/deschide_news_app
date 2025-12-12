'use server';

import { cookies } from 'next/headers';
import { decrypt, encrypt, type SessionPayload } from '@/lib/auth/session';
import { refreshToken, type AuthTokens } from '@/lib/api-client';

// ============================================================================
// Types
// ============================================================================

type RefreshResult =
  | { success: true; accessToken: string }
  | { success: false; error: string };

type UpdateResult =
  | { success: true }
  | { success: false; error: string };

// ============================================================================
// Server Actions
// ============================================================================

/**
 * Server Action to refresh the access token
 * This can only be called from Server Actions or Route Handlers
 * Returns the new access token on success for immediate use
 */
export async function refreshSessionToken(): Promise<RefreshResult> {
  const cookie = (await cookies()).get('session')?.value;

  if (!cookie) {
    return { success: false, error: 'No session found' };
  }

  const session = await decrypt(cookie);

  if (!session?.tokens?.refreshToken) {
    return { success: false, error: 'No refresh token found' };
  }

  try {
    // Refresh the token via API
    const newTokens = await refreshToken(session.tokens.refreshToken);

    // Update session with new tokens
    const updatedSession: SessionPayload = {
      user: session.user,
      tokens: {
        accessToken: newTokens.token,
        refreshToken: newTokens.refresh_token,
        refreshTokenExpiresAt: newTokens.refresh_token_expires_at,
      },
      expiresAt: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000), // 30 days
    };

    // Save updated session to cookie
    const encryptedSession = await encrypt(updatedSession);
    const cookieStore = await cookies();
    cookieStore.set('session', encryptedSession, {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      expires: updatedSession.expiresAt,
      sameSite: 'lax',
      path: '/',
    });

    console.log('[Auth Action] Token refreshed and session cookie updated');
    return { success: true, accessToken: newTokens.token };
  } catch (error) {
    console.error('[Auth Action] Failed to refresh token:', error);
    return { success: false, error: 'Failed to refresh token' };
  }
}

/**
 * Server Action to update session with externally obtained tokens
 * Use this when tokens are refreshed elsewhere and need to be saved to session
 */
export async function updateSessionTokens(newTokens: AuthTokens): Promise<UpdateResult> {
  const cookie = (await cookies()).get('session')?.value;

  if (!cookie) {
    return { success: false, error: 'No session found' };
  }

  const session = await decrypt(cookie);

  if (!session) {
    return { success: false, error: 'Failed to decrypt session' };
  }

  try {
    // Update session with new tokens
    const updatedSession: SessionPayload = {
      user: session.user,
      tokens: {
        accessToken: newTokens.token,
        refreshToken: newTokens.refresh_token,
        refreshTokenExpiresAt: newTokens.refresh_token_expires_at,
      },
      expiresAt: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000), // 30 days
    };

    // Save updated session to cookie
    const encryptedSession = await encrypt(updatedSession);
    const cookieStore = await cookies();
    cookieStore.set('session', encryptedSession, {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      expires: updatedSession.expiresAt,
      sameSite: 'lax',
      path: '/',
    });

    console.log('[Auth Action] Session tokens updated');
    return { success: true };
  } catch (error) {
    console.error('[Auth Action] Failed to update session tokens:', error);
    return { success: false, error: 'Failed to update session tokens' };
  }
}
