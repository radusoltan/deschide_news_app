import { NextRequest, NextResponse } from 'next/server';
import { ensureFreshToken } from '@/app/actions/auth';

export async function GET(request: NextRequest) {
  try {
    const token = await ensureFreshToken();

    if (!token) {
      return NextResponse.json(
        { error: 'Not authenticated' },
        { status: 401 }
      );
    }

    return NextResponse.json({ token });
  } catch (error) {
    console.error('Token fetch error:', error);
    return NextResponse.json(
      { error: 'Failed to get token' },
      { status: 500 }
    );
  }
}
