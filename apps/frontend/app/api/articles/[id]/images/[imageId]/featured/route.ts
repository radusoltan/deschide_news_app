import { NextRequest, NextResponse } from 'next/server';
import { setFeaturedImage } from '@/lib/api/article-images';

/**
 * PUT /api/articles/[id]/images/[imageId]/featured
 * Set an image as the featured image for an article
 */
export async function PUT(
  request: NextRequest,
  { params }: { params: Promise<{ id: string; imageId: string }> }
) {
  try {
    const { id, imageId } = await params;
    const articleId = parseInt(id, 10);
    const articleImageId = parseInt(imageId, 10);

    if (isNaN(articleId) || isNaN(articleImageId)) {
      return NextResponse.json({ error: 'Invalid article or image ID' }, { status: 400 });
    }

    await setFeaturedImage(articleId, articleImageId);

    return NextResponse.json({ success: true }, { status: 200 });
  } catch (error: unknown) {
    const errMsg = error instanceof Error ? error.message : String(error);
    console.error('PUT /api/articles/[id]/images/[imageId]/featured error:', error);
    return NextResponse.json(
      { error: errMsg || 'Failed to set featured image' },
      { status: 500 }
    );
  }
}
