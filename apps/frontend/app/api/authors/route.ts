import { NextResponse } from 'next/server';
import { getAuthors } from '@/lib/api/authors';

/**
 * GET /api/authors
 * Fetch all authors
 */
export async function GET() {
  try {
    const authors = await getAuthors();
    return NextResponse.json(authors);
  } catch (error: unknown) {
    const errMsg = error instanceof Error ? error.message : String(error);
    console.error('GET /api/authors error:', error);
    return NextResponse.json(
      { error: errMsg || 'Failed to fetch authors' },
      { status: 500 }
    );
  }
}
