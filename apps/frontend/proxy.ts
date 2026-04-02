import type { NextRequest } from 'next/server';
import { NextResponse } from 'next/server';

const locales = ['ro', 'en', 'ru'] as const;
const defaultLocale = 'ro';
const localeCookieName = 'NEXT_LOCALE';

const publicPathPrefixes = ['/_next', '/api', '/icons'];
const publicPathnames = new Set([
  '/favicon.ico',
  '/robots.txt',
  '/site.webmanifest',
  '/sw.js',
  '/sitemap.xml',
  '/news-sitemap.xml',
  '/image-sitemap.xml',
  '/sitemap-archive.xml',
]);

const protectedPaths = ['/admin', '/dashboard', '/profile'];
const authPaths = ['/login'];

type Locale = (typeof locales)[number];

function isLocale(value: string | null | undefined): value is Locale {
  return Boolean(value && locales.includes(value as Locale));
}

function getLocaleFromPath(pathname: string): Locale | null {
  const segment = pathname.split('/').filter(Boolean)[0];
  return isLocale(segment) ? segment : null;
}

function getPathWithoutLocale(pathname: string): string {
  const locale = getLocaleFromPath(pathname);
  if (!locale) {
    return pathname;
  }

  const strippedPath = pathname.slice(`/${locale}`.length);
  return strippedPath || '/';
}

function hasFileExtension(pathname: string): boolean {
  return /\.[^/]+$/.test(pathname);
}

function isPublicPath(pathname: string): boolean {
  if (publicPathnames.has(pathname)) {
    return true;
  }

  if (publicPathPrefixes.some((prefix) => pathname.startsWith(prefix))) {
    return true;
  }

  if (pathname.startsWith('/.well-known')) {
    return true;
  }

  const pathnameWithoutLocale = getPathWithoutLocale(pathname);

  return publicPathnames.has(pathnameWithoutLocale) || hasFileExtension(pathnameWithoutLocale);
}

function getLocaleFromHeaders(request: NextRequest): Locale {
  const header = request.headers.get('accept-language') ?? '';

  const parsedLocales = header
    .split(',')
    .map((part) => {
      const [language, qualityPart] = part.trim().split(';');
      const quality = qualityPart ? Number.parseFloat(qualityPart.split('=')[1] ?? '1') : 1;
      return {
        locale: language.split('-')[0]?.toLowerCase() ?? '',
        quality: Number.isFinite(quality) ? quality : 0,
      };
    })
    .sort((left, right) => right.quality - left.quality);

  for (const entry of parsedLocales) {
    if (isLocale(entry.locale)) {
      return entry.locale;
    }
  }

  return defaultLocale;
}

function getPreferredLocale(request: NextRequest): Locale {
  const cookieLocale = request.cookies.get(localeCookieName)?.value;
  if (isLocale(cookieLocale)) {
    return cookieLocale;
  }

  return getLocaleFromHeaders(request);
}

function withLocaleCookie(response: NextResponse, locale: Locale): NextResponse {
  response.cookies.set(localeCookieName, locale, {
    maxAge: 365 * 24 * 60 * 60,
    path: '/',
    sameSite: 'lax',
  });

  return response;
}

function matchesProtectedPath(pathname: string): boolean {
  return protectedPaths.some((route) => pathname === route || pathname.startsWith(`${route}/`));
}

function matchesAuthPath(pathname: string): boolean {
  return authPaths.some((route) => pathname === route || pathname.startsWith(`${route}/`));
}

async function hasValidSession(request: NextRequest): Promise<boolean> {
  const sessionCookie = request.cookies.get('session')?.value;

  if (!sessionCookie) {
    return false;
  }

  try {
    const { decrypt } = await import('@/lib/auth/session-edge');
    return Boolean(await decrypt(sessionCookie));
  } catch {
    return false;
  }
}

export async function proxy(request: NextRequest) {
  const { pathname, search } = request.nextUrl;

  if (isPublicPath(pathname)) {
    return NextResponse.next();
  }

  const localeFromPath = getLocaleFromPath(pathname);

  if (!localeFromPath) {
    const locale = getPreferredLocale(request);
    const redirectUrl = request.nextUrl.clone();
    redirectUrl.pathname = pathname === '/' ? `/${locale}/` : `/${locale}${pathname}`;

    return withLocaleCookie(NextResponse.redirect(redirectUrl), locale);
  }

  const pathWithoutLocale = getPathWithoutLocale(pathname);

  if (matchesProtectedPath(pathWithoutLocale)) {
    const authenticated = await hasValidSession(request);

    if (!authenticated) {
      const loginUrl = request.nextUrl.clone();
      loginUrl.pathname = `/${localeFromPath}/login`;
      loginUrl.searchParams.set('from', `${pathname}${search}`);

      return withLocaleCookie(NextResponse.redirect(loginUrl), localeFromPath);
    }
  }

  if (matchesAuthPath(pathWithoutLocale)) {
    const authenticated = await hasValidSession(request);

    if (authenticated) {
      const adminUrl = request.nextUrl.clone();
      adminUrl.pathname = `/${localeFromPath}/admin`;

      return withLocaleCookie(NextResponse.redirect(adminUrl), localeFromPath);
    }
  }

  const response = NextResponse.next();
  response.headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');

  return withLocaleCookie(response, localeFromPath);
}

export const config = {
  matcher: [
    '/((?!api|_next/static|_next/image|icons|favicon.ico|sw.js|site.webmanifest|robots.txt|sitemap.xml|news-sitemap.xml|image-sitemap.xml|sitemap-archive.xml).*)',
  ],
};
