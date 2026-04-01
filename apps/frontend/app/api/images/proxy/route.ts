import { NextRequest, NextResponse } from 'next/server';

const CDN_BASE = process.env.NEXT_PUBLIC_CDN_URL ?? '';

/**
 * GET /api/images/proxy?path=images/originals/filename.png
 * Proxies image from CDN to avoid CORS issues with canvas-based cropper.
 */
export async function GET(request: NextRequest) {
  const imagePath = request.nextUrl.searchParams.get('path');

  if (!imagePath) {
    return NextResponse.json({ error: 'Missing path parameter' }, { status: 400 });
  }

  // Sanitize: only allow paths under uploads/
  const safePath = imagePath.replace(/\.\./g, '');

  try {
    const cdnResponse = await fetch(`${CDN_BASE}/uploads/${safePath}`, {
      cache: 'no-store',
    });

    if (!cdnResponse.ok) {
      return NextResponse.json(
        { error: `Image not found: ${cdnResponse.status}` },
        { status: cdnResponse.status }
      );
    }

    const contentType = cdnResponse.headers.get('content-type') || 'image/jpeg';
    const imageBuffer = await cdnResponse.arrayBuffer();

    return new NextResponse(imageBuffer, {
      status: 200,
      headers: {
        'Content-Type': contentType,
        'Cache-Control': 'public, max-age=3600',
      },
    });
  } catch (error) {
    console.error('Image proxy error:', error);
    return NextResponse.json({ error: 'Failed to proxy image' }, { status: 500 });
  }
}
