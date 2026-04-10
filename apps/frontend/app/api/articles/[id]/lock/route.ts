import { NextRequest, NextResponse } from 'next/server';
import { getAccessToken } from '@/lib/dal';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

/**
 * POST /api/articles/[id]/lock
 * Acquire lock on an article
 */
export async function POST(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  try {
    const { id } = await params;
    const token = await getAccessToken();

    if (!token) {
      return NextResponse.json({ error: 'Authentication required' }, { status: 401 });
    }

    const response = await fetch(`${API_BASE_URL}/api/articles/${id}/lock`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    });

    let data;
    try {
      data = await response.json();
    } catch (parseError) {
      console.error('Failed to parse response:', parseError);
      const text = await response.text();
      console.error('Response text:', text);
      return NextResponse.json(
        { error: 'Invalid response from server', details: text },
        { status: 500 }
      );
    }

    if (!response.ok) {
      console.error('Lock acquisition failed:', data);
      return NextResponse.json(data, { status: response.status });
    }

    return NextResponse.json(data);
  } catch (error: unknown) {
    const errMsg = error instanceof Error ? error.message : String(error);
    console.error('POST /api/articles/[id]/lock error:', error);
    return NextResponse.json(
      { error: errMsg || 'Failed to acquire lock' },
      { status: 500 }
    );
  }
}

/**
 * DELETE /api/articles/[id]/lock
 * Release lock on an article
 */
export async function DELETE(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  try {
    const { id } = await params;
    const token = await getAccessToken();

    if (!token) {
      return NextResponse.json({ error: 'Authentication required' }, { status: 401 });
    }

    const response = await fetch(`${API_BASE_URL}/api/articles/${id}/lock`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${token}`,
      },
    });

    if (!response.ok && response.status !== 204) {
      const data = await response.json().catch(() => ({ error: 'Failed to release lock' }));
      return NextResponse.json(data, { status: response.status });
    }

    return new NextResponse(null, { status: 204 });
  } catch (error: unknown) {
    const errMsg = error instanceof Error ? error.message : String(error);
    console.error('DELETE /api/articles/[id]/lock error:', error);
    return NextResponse.json(
      { error: errMsg || 'Failed to release lock' },
      { status: 500 }
    );
  }
}
