import { NextRequest, NextResponse } from 'next/server';
import { getAccessToken } from '@/lib/dal';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

/**
 * POST /api/images/upload-from-url
 * Download image from URL and upload to backend
 */
export async function POST(request: NextRequest) {
  try {
    const token = await getAccessToken();
    if (!token) {
      return NextResponse.json({ error: 'Authentication required' }, { status: 401 });
    }

    const body = await request.json();
    const { url } = body;

    if (!url) {
      return NextResponse.json({ error: 'URL is required' }, { status: 400 });
    }

    // Validate URL format
    let imageUrl: URL;
    try {
      imageUrl = new URL(url);
    } catch {
      return NextResponse.json({ error: 'Invalid URL format' }, { status: 400 });
    }

    // Download image from URL
    const imageResponse = await fetch(imageUrl.toString());
    if (!imageResponse.ok) {
      return NextResponse.json(
        { error: `Failed to download image: ${imageResponse.statusText}` },
        { status: 400 }
      );
    }

    // Check content type
    const contentType = imageResponse.headers.get('content-type');
    if (!contentType || !contentType.startsWith('image/')) {
      return NextResponse.json(
        { error: 'URL does not point to an image' },
        { status: 400 }
      );
    }

    // Get image data
    const imageBlob = await imageResponse.blob();

    // Extract filename from URL or use default
    const pathname = imageUrl.pathname;
    const filename = pathname.split('/').pop() || 'downloaded-image.jpg';

    // Create FormData for backend upload
    const formData = new FormData();
    formData.append('file', imageBlob, filename);
    formData.append('alt', '');
    formData.append('caption', '');
    formData.append('description', `Downloaded from: ${url}`);

    // Upload to backend
    const uploadResponse = await fetch(`${API_BASE_URL}/api/images`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
      },
      body: formData,
    });

    if (!uploadResponse.ok) {
      const error = await uploadResponse.json().catch(() => ({ message: 'Upload failed' }));
      throw new Error(error.message || 'Upload failed');
    }

    const imageData = await uploadResponse.json();

    return NextResponse.json(imageData, { status: 201 });
  } catch (error: any) {
    console.error('Upload from URL error:', error);
    return NextResponse.json(
      { error: error.message || 'Failed to upload image from URL' },
      { status: 500 }
    );
  }
}
