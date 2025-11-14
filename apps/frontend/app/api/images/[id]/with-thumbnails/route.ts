import { NextRequest, NextResponse } from 'next/server';
import { getImageWithThumbnails } from '@/lib/api/images';

/**
 * GET /api/images/[id]/with-thumbnails
 * Fetch single image with expanded thumbnails data
 */
export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  try {
    const { id } = await params;
    const imageId = parseInt(id, 10);

    if (isNaN(imageId)) {
      return NextResponse.json(
        { error: 'Invalid image ID' },
        { status: 400 }
      );
    }

    const image = await getImageWithThumbnails(imageId);

    // Serialize the image data (convert any Date objects or complex types)
    const serializedImage = JSON.parse(JSON.stringify(image));

    return NextResponse.json(serializedImage);
  } catch (error: any) {
    console.error(`GET /api/images/[id]/with-thumbnails error:`, error);
    return NextResponse.json(
      { error: error.message || 'Failed to fetch image with thumbnails' },
      { status: error.status || 500 }
    );
  }
}
