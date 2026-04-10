import { NextRequest, NextResponse } from 'next/server';
import { getAccessToken } from '@/lib/dal';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

/**
 * POST /api/articles/[id]/optimize-seo
 * Proxy to Symfony backend: triggers Gemini AI SEO optimization.
 * Returns metaTitle, metaDescription, and suggested tags.
 */
export async function POST(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  try {
    const { id } = await params;
    const articleId = parseInt(id, 10);

    if (isNaN(articleId)) {
      return NextResponse.json({ error: 'Invalid article ID' }, { status: 400 });
    }

    const token = await getAccessToken();
    if (!token) {
      return NextResponse.json({ error: 'Authentication required' }, { status: 401 });
    }

    // Forward request body to backend
    let body = '{}';
    try {
      const parsed = await request.json();
      body = JSON.stringify(parsed);
    } catch {
      // Empty body is fine — defaults are used
    }

    const response = await fetch(
      `${API_BASE_URL}/api/articles/${articleId}/optimize-seo`,
      {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body,
      }
    );

    const data = await response.json();

    if (!response.ok) {
      return NextResponse.json(
        { error: data.error || 'SEO optimization failed' },
        { status: response.status }
      );
    }

    return NextResponse.json(data);
  } catch (error: unknown) {
    const errMsg = error instanceof Error ? error.message : String(error);
    console.error('POST /api/articles/[id]/optimize-seo error:', error);
    return NextResponse.json(
      { error: errMsg || 'Failed to optimize SEO' },
      { status: 500 }
    );
  }
}
