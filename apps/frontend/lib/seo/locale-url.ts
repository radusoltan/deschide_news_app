export const SUPPORTED_LOCALES = ['ro', 'en', 'ru'] as const;

export type Locale = (typeof SUPPORTED_LOCALES)[number];
export type HreflangLocale = 'ro-MD' | Locale | 'x-default';

function normalizeBaseUrl(baseUrl: string): string {
  return baseUrl.replace(/\/+$/, '');
}

function normalizePath(path: string): string {
  return path.replace(/^\/+/, '').replace(/\/+$/, '');
}

export function buildLocalizedUrl(
  baseUrl: string,
  locale: Locale,
  path: string
): string {
  const normalizedBaseUrl = normalizeBaseUrl(baseUrl);
  const cleanPath = normalizePath(path);
  const localizedPath = cleanPath ? `${locale}/${cleanPath}` : locale;

  return `${normalizedBaseUrl}/${localizedPath}`.replace(/\/+$/, '');
}

export function buildCanonicalUrl(
  baseUrl: string,
  locale: Locale,
  path: string
): string {
  return buildLocalizedUrl(baseUrl, locale, path);
}

export function buildHreflangAlternates(
  baseUrl: string,
  path: string
): Record<HreflangLocale, string> {
  return {
    'ro-MD': buildLocalizedUrl(baseUrl, 'ro', path),
    ro: buildLocalizedUrl(baseUrl, 'ro', path),
    en: buildLocalizedUrl(baseUrl, 'en', path),
    ru: buildLocalizedUrl(baseUrl, 'ru', path),
    'x-default': buildLocalizedUrl(baseUrl, 'ro', path),
  };
}
