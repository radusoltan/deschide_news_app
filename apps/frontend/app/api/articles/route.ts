import { NextRequest, NextResponse } from 'next/server';
import { getAccessToken } from '@/lib/dal';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

/**
 * POST /api/articles
 * Create a new article with minimal data
 */
export async function POST(request: NextRequest) {
  try {
    const token = await getAccessToken();
    if (!token) {
      return NextResponse.json({ error: 'Authentication required' }, { status: 401 });
    }

    const body = await request.json();
    const locale = request.headers.get('Accept-Language') || 'ro';

    // Create article in backend
    const response = await fetch(`${API_BASE_URL}/api/articles`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/ld+json',
        'Accept-Language': locale,
      },
      body: JSON.stringify(body),
    });

    if (!response.ok) {
      const error = await response.json().catch(() => ({ message: 'Failed to create article' }));
      return NextResponse.json(
        { error: error.message || error['hydra:description'] || 'Failed to create article' },
        { status: response.status }
      );
    }

    const article = await response.json();

    return NextResponse.json(article, { status: 201 });
  } catch (error: any) {
    console.error('POST /api/articles error:', error);
    return NextResponse.json(
      { error: error.message || 'Failed to create article' },
      { status: 500 }
    );
  }
}
