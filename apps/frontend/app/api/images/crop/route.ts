import { NextRequest, NextResponse } from 'next/server';
import { applyCustomCrop, resetCrop, getImageWithThumbnails } from '@/lib/api/images';
import type { CropCoordinates } from '@/lib/types/image';

/**
 * POST /api/images/crop
 * Apply custom crop to generate thumbnail
 *
 * Body:
 * {
 *   imageId: number,
 *   profile: string,
 *   format: 'jpg' | 'webp',
 *   cropData: CropCoordinates
 * }
 */
export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { imageId, profile, format, cropData } = body;

    // Validate required fields
    if (!imageId || !profile || !format || !cropData) {
      return NextResponse.json(
        { error: 'Missing required fields: imageId, profile, format, cropData' },
        { status: 400 }
      );
    }

    // Validate format
    if (format !== 'jpg' && format !== 'webp') {
      return NextResponse.json(
        { error: 'Invalid format. Must be jpg or webp' },
        { status: 400 }
      );
    }

    // Validate cropData structure
    const { x, y, width, height } = cropData as CropCoordinates;
    if (
      typeof x !== 'number' ||
      typeof y !== 'number' ||
      typeof width !== 'number' ||
      typeof height !== 'number'
    ) {
      return NextResponse.json(
        { error: 'Invalid cropData. Must contain x, y, width, height as numbers' },
        { status: 400 }
      );
    }

    const thumbnail = await applyCustomCrop(imageId, profile, format, cropData);

    // Serialize the response
    const serializedResponse = JSON.parse(JSON.stringify({
      success: true,
      message: `Thumbnail generated successfully for profile: ${profile}`,
      thumbnail,
    }));

    return NextResponse.json(serializedResponse);
  } catch (error) {
    console.error('Failed to apply crop:', error);
    return NextResponse.json(
      { error: error instanceof Error ? error.message : 'Failed to apply crop' },
      { status: 500 }
    );
  }
}

/**
 * DELETE /api/images/crop
 * Reset crop to default
 *
 * Body:
 * {
 *   imageId: number,
 *   profile: string,
 *   format: 'jpg' | 'webp'
 * }
 */
export async function DELETE(request: NextRequest) {
  try {
    const body = await request.json();
    const { imageId, profile, format } = body;

    // Validate required fields
    if (!imageId || !profile || !format) {
      return NextResponse.json(
        { error: 'Missing required fields: imageId, profile, format' },
        { status: 400 }
      );
    }

    // Validate format
    if (format !== 'jpg' && format !== 'webp') {
      return NextResponse.json(
        { error: 'Invalid format. Must be jpg or webp' },
        { status: 400 }
      );
    }

    const thumbnail = await resetCrop(imageId, profile, format);

    // Serialize the response
    const serializedResponse = JSON.parse(JSON.stringify({
      success: true,
      message: `Crop reset successfully for profile: ${profile}`,
      thumbnail,
    }));

    return NextResponse.json(serializedResponse);
  } catch (error) {
    console.error('Failed to reset crop:', error);
    return NextResponse.json(
      { error: error instanceof Error ? error.message : 'Failed to reset crop' },
      { status: 500 }
    );
  }
}
