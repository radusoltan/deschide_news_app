import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';
import { i18nRouter } from 'next-i18n-router';
import i18nConfig from './i18nConfig';
import { decrypt } from '@/lib/auth/session-edge';

// Routes that require authentication
const protectedRoutes = ['/dashboard', '/admin', '/profile'];

// Routes that should redirect to home if authenticated
const authRoutes = ['/login'];

export async function middleware(request: NextRequest) {
  const path = request.nextUrl.pathname;

  // Remove locale prefix for route matching
  const pathWithoutLocale = path.replace(/^\/(ro|en|ru)/, '') || '/';

  // Check if route is protected
  const isProtectedRoute = protectedRoutes.some((route) =>
    pathWithoutLocale.startsWith(route)
  );

  // Check if route is auth route (login)
  const isAuthRoute = authRoutes.some((route) => pathWithoutLocale.startsWith(route));

  // Get session cookie
  const cookie = request.cookies.get('session')?.value;
  const session = cookie ? await decrypt(cookie) : null;

  // Redirect to login if accessing protected route without session
  if (isProtectedRoute && !session) {
    const loginUrl = new URL('/login', request.url);
    loginUrl.searchParams.set('from', pathWithoutLocale);
    return NextResponse.redirect(loginUrl);
  }

  // Apply i18n routing
  return i18nRouter(request, i18nConfig);
}

// Applies this middleware only to files in the app directory
export const config = {
  matcher: '/((?!api|static|.*\\..*|_next).*)'
};
