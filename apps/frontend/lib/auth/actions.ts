'use server';

import { cookies } from 'next/headers';
import { decrypt, encrypt, type SessionPayload } from '@/lib/auth/session';
import { refreshToken } from '@/lib/api-client';

/**
 * Server Action to refresh the access token
 * This can only be called from Server Actions or Route Handlers
 */
export async function refreshSessionToken() {
  const cookie = (await cookies()).get('session')?.value;

  if (!cookie) {
    return { success: false, error: 'No session found' };
  }

  const session = await decrypt(cookie);

  if (!session?.tokens?.refreshToken) {
    return { success: false, error: 'No refresh token found' };
  }

  try {
    // Refresh the token
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

    // Save updated session
    const encryptedSession = await encrypt(updatedSession);
    const cookieStore = await cookies();
    cookieStore.set('session', encryptedSession, {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      expires: updatedSession.expiresAt,
      sameSite: 'lax',
      path: '/',
    });

    console.log('Token refreshed successfully');
    return { success: true };
  } catch (error) {
    console.error('Failed to refresh token:', error);
    return { success: false, error: 'Failed to refresh token' };
  }
}
