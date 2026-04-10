import { NextRequest, NextResponse } from 'next/server';
import { detachImageFromArticle } from '@/lib/api/article-images';

/**
 * DELETE /api/articles/[id]/images/[imageId]
 * Detach an image from an article
 */
export async function DELETE(
  request: NextRequest,
  { params }: { params: Promise<{ id: string; imageId: string }> }
) {
  try {
    const { imageId } = await params;
    const articleImageId = parseInt(imageId, 10);

    if (isNaN(articleImageId)) {
      return NextResponse.json({ error: 'Invalid article image ID' }, { status: 400 });
    }

    await detachImageFromArticle(articleImageId);

    return NextResponse.json({ success: true }, { status: 200 });
  } catch (error: unknown) {
    const errMsg = error instanceof Error ? error.message : String(error);
    console.error('DELETE /api/articles/[id]/images/[imageId] error:', error);
    return NextResponse.json(
      { error: errMsg || 'Failed to detach image' },
      { status: 500 }
    );
  }
}
