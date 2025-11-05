/**
 * Web Vitals API Endpoint
 *
 * Receives and logs Core Web Vitals metrics from the client
 * In production, this would send data to analytics service (Google Analytics, etc.)
 */

import { NextRequest, NextResponse } from 'next/server';

export async function POST(request: NextRequest) {
  try {
    const metric = await request.json();

    // Log metric in development
    if (process.env.NODE_ENV === 'development') {
      console.log('[Web Vitals API]', {
        name: metric.name,
        value: metric.value,
        rating: metric.rating,
        url: metric.url,
      });
    }

    // In production, send to analytics service
    if (process.env.NODE_ENV === 'production') {
      // TODO: Send to Google Analytics, Vercel Analytics, or custom analytics service
      // Example with Google Analytics 4:
      // await sendToGA4(metric);

      // Example with custom endpoint:
      // await fetch(process.env.ANALYTICS_ENDPOINT, {
      //   method: 'POST',
      //   headers: { 'Content-Type': 'application/json' },
      //   body: JSON.stringify(metric),
      // });

      // For now, just log to console in production
      console.log('[Web Vitals]', {
        name: metric.name,
        value: metric.value,
        rating: metric.rating,
      });
    }

    return NextResponse.json({ success: true }, { status: 200 });
  } catch (error) {
    console.error('[Web Vitals API] Error:', error);
    return NextResponse.json({ success: false, error: 'Internal server error' }, { status: 500 });
  }
}

// Handle preflight requests
export async function OPTIONS() {
  return new NextResponse(null, {
    status: 200,
    headers: {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'POST, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type',
    },
  });
}
