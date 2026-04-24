import { NextRequest, NextResponse } from 'next/server';
import { getImage, deleteImage, updateImage } from '@/lib/api/images';

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
  } catch (error: unknown) {
    const errMsg = error instanceof Error ? error.message : String(error);
    console.error('GET /api/images/[id] error:', error);
    return NextResponse.json(
      { error: errMsg || 'Failed to fetch image' },
      { status: 500 }
    );
  }
}

/**
 * PUT /api/images/[id]
 * Update image metadata
 */
export async function PUT(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  try {
    const { id } = await params;
    const imageId = parseInt(id, 10);

    if (isNaN(imageId)) {
      return NextResponse.json({ error: 'Invalid image ID' }, { status: 400 });
    }

    const body = await request.json();

    const updatedImage = await updateImage(imageId, {
      alt: body.alt,
      caption: body.caption,
      description: body.description,
    });

    return NextResponse.json(updatedImage);
  } catch (error: unknown) {
    const errMsg = error instanceof Error ? error.message : String(error);
    console.error('PUT /api/images/[id] error:', error);
    return NextResponse.json(
      { error: errMsg || 'Failed to update image' },
      { status: 500 }
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
  } catch (error: unknown) {
    const errMsg = error instanceof Error ? error.message : String(error);
    console.error('DELETE /api/images/[id] error:', error);
    return NextResponse.json(
      { error: errMsg || 'Failed to delete image' },
      { status: 500 }
    );
  }
}
