import { NextRequest, NextResponse } from 'next/server';
import { getAccessToken } from '@/lib/dal';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

/**
 * GET /api/articles/locks/active
 * Get all active article locks
 */
export async function GET(request: NextRequest) {
  try {
    const token = await getAccessToken();

    if (!token) {
      return NextResponse.json({ error: 'Authentication required' }, { status: 401 });
    }

    const response = await fetch(`${API_BASE_URL}/api/articles/locks/active`, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
      cache: 'no-store',
    });

    const data = await response.json();

    if (!response.ok) {
      return NextResponse.json(data, { status: response.status });
    }

    return NextResponse.json(data);
  } catch (error: any) {
    console.error('GET /api/articles/locks/active error:', error);
    return NextResponse.json(
      { error: error.message || 'Failed to fetch active locks' },
      { status: 500 }
    );
  }
}
