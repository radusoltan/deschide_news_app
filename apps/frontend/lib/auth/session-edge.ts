/**
 * Session utilities for Edge runtime (Middleware)
 * Cannot use Node.js APIs here
 */

import { jwtVerify } from 'jose';
import type { SessionPayload } from './session';

const SECRET_KEY = process.env.SESSION_SECRET || 'your-secret-key-change-in-production';
const key = new TextEncoder().encode(SECRET_KEY);

/**
 * Decrypt and verify session JWT (Edge-compatible)
 */
export async function decrypt(session: string): Promise<SessionPayload | null> {
  try {
    const { payload } = await jwtVerify(session, key, {
      algorithms: ['HS256'],
    });
    return payload as unknown as SessionPayload;
  } catch {
    return null;
  }
}
