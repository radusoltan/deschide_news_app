/**
 * Locale Handler for Middleware
 * Handles locale detection and validation
 */

import type { NextRequest } from 'next/server';

const DEFAULT_LOCALE = 'ro';
const AVAILABLE_LOCALES = ['ro', 'en', 'ru'] as const;
type Locale = (typeof AVAILABLE_LOCALES)[number];

/**
 * Detect user's preferred locale from request
 * Priority: URL > Cookie > Accept-Language header > Default
 */
export function detectLocale(request: NextRequest): Locale {
  const pathname = request.nextUrl.pathname;

  // 1. Check URL path for locale
  const urlLocale = extractLocaleFromPath(pathname);
  if (urlLocale && isValidLocale(urlLocale)) {
    return urlLocale;
  }

  // 2. Check cookie
  const cookieLocale = request.cookies.get('NEXT_LOCALE')?.value;
  if (cookieLocale && isValidLocale(cookieLocale)) {
    return cookieLocale as Locale;
  }

  // 3. Check Accept-Language header
  const acceptLanguage = request.headers.get('accept-language');
  if (acceptLanguage) {
    const headerLocale = parseAcceptLanguage(acceptLanguage);
    if (headerLocale && isValidLocale(headerLocale)) {
      return headerLocale;
    }
  }

  // 4. Default locale
  return DEFAULT_LOCALE;
}

/**
 * Extract locale from URL path
 * Examples: /en/article -> 'en', /article -> null
 */
export function extractLocaleFromPath(pathname: string): string | null {
  const segments = pathname.split('/').filter(Boolean);
  if (segments.length === 0) {
    return null;
  }

  const firstSegment = segments[0];
  return AVAILABLE_LOCALES.includes(firstSegment as Locale)
    ? firstSegment
    : null;
}

/**
 * Check if locale is valid
 */
export function isValidLocale(locale: string): locale is Locale {
  return AVAILABLE_LOCALES.includes(locale as Locale);
}

/**
 * Parse Accept-Language header
 * Format: "en-US,en;q=0.9,ro;q=0.8"
 */
export function parseAcceptLanguage(header: string): Locale | null {
  const languages = header.split(',').map((lang) => {
    const [code, qValue] = lang.trim().split(';');
    const quality = qValue ? parseFloat(qValue.split('=')[1]) : 1.0;
    // Extract primary language code (en-US -> en)
    const primaryCode = code.split('-')[0].toLowerCase();
    return { code: primaryCode, quality };
  });

  // Sort by quality (highest first)
  languages.sort((a, b) => b.quality - a.quality);

  // Find first matching locale
  for (const lang of languages) {
    if (isValidLocale(lang.code)) {
      return lang.code as Locale;
    }
  }

  return null;
}

/**
 * Get path without locale prefix
 * Examples: /en/article -> /article, /article -> /article
 */
export function getPathWithoutLocale(pathname: string): string {
  const locale = extractLocaleFromPath(pathname);
  if (!locale) {
    return pathname;
  }

  // Remove locale prefix
  const pathWithoutLocale = pathname.replace(`/${locale}`, '');
  return pathWithoutLocale || '/';
}

/**
 * Add locale prefix to path
 * Examples: /article + 'en' -> /en/article
 */
export function addLocaleToPath(pathname: string, locale: Locale): string {
  // Don't add prefix for default locale (ro)
  if (locale === DEFAULT_LOCALE) {
    return pathname;
  }

  // Ensure path starts with /
  const cleanPath = pathname.startsWith('/') ? pathname : `/${pathname}`;

  // Don't duplicate locale prefix
  const existingLocale = extractLocaleFromPath(cleanPath);
  if (existingLocale === locale) {
    return cleanPath;
  }

  // Remove existing locale if different
  const pathWithoutLocale = existingLocale
    ? cleanPath.replace(`/${existingLocale}`, '')
    : cleanPath;

  return `/${locale}${pathWithoutLocale || '/'}`;
}

/**
 * Check if path needs locale redirect
 * Returns true if user should be redirected to their preferred locale
 */
export function needsLocaleRedirect(
  pathname: string,
  preferredLocale: Locale
): boolean {
  const urlLocale = extractLocaleFromPath(pathname);

  // If no locale in URL and preferred is not default, redirect
  if (!urlLocale && preferredLocale !== DEFAULT_LOCALE) {
    return true;
  }

  // If URL locale differs from preferred, don't auto-redirect
  // (user explicitly chose this locale)
  return false;
}

/**
 * Get locale cookie options
 */
export function getLocaleCookieOptions() {
  return {
    name: 'NEXT_LOCALE',
    maxAge: 365 * 24 * 60 * 60, // 1 year
    path: '/',
    sameSite: 'lax' as const,
  };
}

/**
 * Get available locales
 */
export function getAvailableLocales(): readonly Locale[] {
  return AVAILABLE_LOCALES;
}

/**
 * Get default locale
 */
export function getDefaultLocale(): Locale {
  return DEFAULT_LOCALE;
}
