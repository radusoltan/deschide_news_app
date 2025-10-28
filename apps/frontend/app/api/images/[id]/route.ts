import { NextRequest, NextResponse } from 'next/server';
import { getImage, deleteImage } from '@/lib/api/images';

/**
 * GET /api/images/[id]
 * Fetch a single image by ID
 */
export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  try {
    const { id } = await params;
    const imageId = parseInt(id, 10);

    if (isNaN(imageId)) {
      return NextResponse.json({ error: 'Invalid image ID' }, { status: 400 });
    }

    const image = await getImage(imageId);

    return NextResponse.json(image);
  } catch (error: any) {
    console.error('GET /api/images/[id] error:', error);
    return NextResponse.json(
      { error: error.message || 'Failed to fetch image' },
      { status: error.status || 500 }
    );
  }
}

/**
 * DELETE /api/images/[id]
 * Delete an image by ID
 */
export async function DELETE(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  try {
    const { id } = await params;
    const imageId = parseInt(id, 10);

    if (isNaN(imageId)) {
      return NextResponse.json({ error: 'Invalid image ID' }, { status: 400 });
    }

    await deleteImage(imageId);

    return NextResponse.json({ success: true }, { status: 200 });
  } catch (error: any) {
    console.error('DELETE /api/images/[id] error:', error);
    return NextResponse.json(
      { error: error.message || 'Failed to delete image' },
      { status: error.status || 500 }
    );
  }
}
