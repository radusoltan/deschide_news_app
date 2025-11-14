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
  } catch (error: any) {
    console.error('GET /api/authors error:', error);
    return NextResponse.json(
      { error: error.message || 'Failed to fetch authors' },
      { status: error.status || 500 }
    );
  }
}
