import { NextRequest, NextResponse } from 'next/server';
import { getArticleImages, attachImageToArticle } from '@/lib/api/article-images';
import type { AttachedImage, ArticleImage, Image } from '@/lib/types/image';

/**
 * GET /api/articles/[id]/images
 * Fetch all images attached to an article
 */
export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  try {
    const { id } = await params;
    const articleId = parseInt(id, 10);

    if (isNaN(articleId)) {
      return NextResponse.json({ error: 'Invalid article ID' }, { status: 400 });
    }

    const data = await getArticleImages(articleId);

    // Handle both Hydra format and plain array
    let articleImages: ArticleImage[] = [];

    if (Array.isArray(data)) {
      // Plain array response
      articleImages = data;
      console.log('Using plain array format, count:', articleImages.length);
    } else if (data && data['hydra:member']) {
      // Hydra collection response with hydra: prefix
      articleImages = data['hydra:member'];
      console.log('Using Hydra format with prefix, count:', articleImages.length);
    } else if (data && (data as unknown as { member?: ArticleImage[] }).member) {
      // API Platform collection response without hydra: prefix
      articleImages = (data as unknown as { member: ArticleImage[] }).member;
      console.log('Using API Platform format without prefix, count:', articleImages.length);
    }

    // Check if we have any article images
    if (articleImages.length === 0) {
      console.log('No article images found, returning empty array');
      return NextResponse.json([]);
    }

    console.log('Processing', articleImages.length, 'article images');

    // Transform to AttachedImage format for easier client-side handling
    // If image is IRI (string), we need to fetch the full object
    const attachedImages: AttachedImage[] = await Promise.all(
      articleImages.map(async (ai: ArticleImage) => {
        let imageData: Image;

        if (typeof ai.image === 'string') {
          // Image is IRI, need to fetch full object
          const imageId = parseInt(ai.image.split('/').pop() || '0', 10);
          const { getImage } = await import('@/lib/api/images');
          imageData = await getImage(imageId);
        } else {
          // Image is already full object
          imageData = ai.image as Image;
        }

        return {
          id: imageData.id,
          articleImageId: ai.id,
          image: imageData,
          position: ai.position,
          isFeatured: ai.isFeatured,
        };
      })
    );

    return NextResponse.json(attachedImages);
  } catch (error: unknown) {
    const errMsg = error instanceof Error ? error.message : String(error);
    console.error('GET /api/articles/[id]/images error:', error);
    return NextResponse.json(
      { error: errMsg || 'Failed to fetch article images' },
      { status: 500 }
    );
  }
}

/**
 * POST /api/articles/[id]/images
 * Attach an image to an article
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

    const body = await request.json();
    const { imageId, position, isFeatured } = body;

    if (!imageId) {
      return NextResponse.json({ error: 'Image ID is required' }, { status: 400 });
    }

    const articleImage = await attachImageToArticle({
      articleId,
      imageId,
      position,
      isFeatured,
    });

    return NextResponse.json(articleImage, { status: 201 });
  } catch (error: unknown) {
    const errMsg = error instanceof Error ? error.message : String(error);
    console.error('POST /api/articles/[id]/images error:', error);
    return NextResponse.json(
      { error: errMsg || 'Failed to attach image' },
      { status: 500 }
    );
  }
}
