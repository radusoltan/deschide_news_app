import { NextRequest, NextResponse } from 'next/server';
import { reorderArticleImages } from '@/lib/api/article-images';

/**
 * PUT /api/articles/[id]/images/reorder
 * Reorder images attached to an article
 */
export async function PUT(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  try {
    const { id } = await params;
    const articleId = parseInt(id, 10);

    if (isNaN(articleId)) {
      return NextResponse.json({ error: 'Invalid article ID' }, { status: 400 });
    }

    const body = await request.json();
    const { updates } = body;

    if (!Array.isArray(updates)) {
      return NextResponse.json({ error: 'Updates must be an array' }, { status: 400 });
    }

    await reorderArticleImages(updates);

    return NextResponse.json({ success: true }, { status: 200 });
  } catch (error: any) {
    console.error('PUT /api/articles/[id]/images/reorder error:', error);
    return NextResponse.json(
      { error: error.message || 'Failed to reorder images' },
      { status: error.status || 500 }
    );
  }
}
