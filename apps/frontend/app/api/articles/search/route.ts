import { NextRequest, NextResponse } from 'next/server';
import { getAccessToken } from '@/lib/dal';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

/**
 * GET /api/articles/search?title=...&locale=ro&itemsPerPage=50
 * Server-side article search via Symfony API
 */
export async function GET(request: NextRequest) {
  try {
    const token = await getAccessToken();

    if (!token) {
      return NextResponse.json({ error: 'Authentication required' }, { status: 401 });
    }

    const { searchParams } = new URL(request.url);
    const title = searchParams.get('title') || '';
    const locale = searchParams.get('locale') || 'ro';
    const itemsPerPage = searchParams.get('itemsPerPage') || '50';

    if (!title.trim()) {
      return NextResponse.json({ 'hydra:member': [], 'hydra:totalItems': 0 });
    }

    const apiParams = new URLSearchParams();
    apiParams.set('title', title);
    apiParams.set('itemsPerPage', itemsPerPage);
    apiParams.set('page', '1');

    const response = await fetch(
      `${API_BASE_URL}/api/articles?${apiParams.toString()}`,
      {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/ld+json',
          'Accept-Language': locale,
        },
        cache: 'no-store',
      }
    );

    const data = await response.json();

    if (!response.ok) {
      return NextResponse.json(data, { status: response.status });
    }

    return NextResponse.json(data);
  } catch (error: unknown) {
    const errMsg = error instanceof Error ? error.message : String(error);
    console.error('GET /api/articles/search error:', error);
    return NextResponse.json(
      { error: errMsg || 'Failed to search articles' },
      { status: 500 }
    );
  }
}
