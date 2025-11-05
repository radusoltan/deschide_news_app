/**
 * Handle .well-known requests
 * These are typically made by browsers and tools for discovery
 * Return 404 for all requests to prevent them from being caught by article routes
 */

import { NextResponse } from 'next/server';

export async function GET() {
  return new NextResponse('Not Found', { status: 404 });
}
