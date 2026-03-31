/**
 * Article Revalidation Endpoint
 * Called from:
 *  - Admin panel after publishing/updating articles
 *  - Backend import commands after batch imports
 * Invalidates both the 'articles' data cache tag AND the homepage route cache.
 */

import { NextResponse } from 'next/server';
import { revalidateTag, revalidatePath } from 'next/cache';

export async function POST() {
  try {
    // Invalidate data cache (fetch results tagged with 'articles')
    revalidateTag('articles', 'max');

    // Invalidate Full Route Cache for all locale homepages
    revalidatePath('/ro', 'page');
    revalidatePath('/en', 'page');
    revalidatePath('/ru', 'page');

    return NextResponse.json({ revalidated: true, tag: 'articles' });
  } catch (error) {
    console.error('[revalidate-articles] Error:', error);
    return NextResponse.json({ revalidated: false }, { status: 500 });
  }
}
