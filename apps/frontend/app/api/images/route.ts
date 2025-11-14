import { NextRequest, NextResponse } from 'next/server';
import { getImages, uploadImage } from '@/lib/api/images';

/**
 * GET /api/images
 * Fetch paginated list of images
 */
export async function GET(request: NextRequest) {
  try {
    const searchParams = request.nextUrl.searchParams;
    const page = searchParams.get('page') ? parseInt(searchParams.get('page')!, 10) : 1;
    const itemsPerPage = searchParams.get('itemsPerPage')
      ? parseInt(searchParams.get('itemsPerPage')!, 10)
      : 30;
    const search = searchParams.get('search') || undefined;

    const data = await getImages({ page, itemsPerPage, search });

    return NextResponse.json(data);
  } catch (error: any) {
    console.error('GET /api/images error:', error);
    return NextResponse.json(
      { error: error.message || 'Failed to fetch images' },
      { status: error.status || 500 }
    );
  }
}

/**
 * POST /api/images
 * Upload a new image
 */
export async function POST(request: NextRequest) {
  try {
    const formData = await request.formData();

    const image = await uploadImage(formData);

    return NextResponse.json(image, { status: 201 });
  } catch (error: any) {
    console.error('POST /api/images error:', error);
    return NextResponse.json(
      { error: error.message || 'Failed to upload image' },
      { status: error.status || 500 }
    );
  }
}
