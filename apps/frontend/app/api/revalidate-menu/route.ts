/**
 * Admin Menu Revalidation Endpoint
 * Called from the admin menu builder after mutations.
 * Requires valid admin session (checked via /api/auth/token).
 */

import { NextResponse } from 'next/server';
import { revalidateTag } from 'next/cache';

export async function POST() {
  try {
    revalidateTag('menu', 'max');
    return NextResponse.json({ revalidated: true });
  } catch (error) {
    console.error('[revalidate-menu] Error:', error);
    return NextResponse.json({ revalidated: false }, { status: 500 });
  }
}
