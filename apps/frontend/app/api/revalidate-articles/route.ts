/**
 * Article Revalidation Endpoint
 * Called from:
 *  - Admin panel after publishing/updating articles
 *  - Backend import commands after batch imports
 * Invalidates the 'articles' cache tag so homepage sections refresh.
 */

import { NextResponse } from 'next/server';
import { revalidateTag } from 'next/cache';

export async function POST() {
  try {
    revalidateTag('articles', 'max');
    return NextResponse.json({ revalidated: true, tag: 'articles' });
  } catch (error) {
    console.error('[revalidate-articles] Error:', error);
    return NextResponse.json({ revalidated: false }, { status: 500 });
  }
}
