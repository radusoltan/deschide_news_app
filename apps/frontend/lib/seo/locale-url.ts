import i18nConfig from '@/i18nConfig';

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

/* ============================================================================
 * Relative-path locale URL builders (navigation)
 *
 * These helpers are the single source of truth for the "target locale → URL
 * path" mapping used by in-app navigation (Link hrefs, language switcher).
 * `lib/utils/url-builder.ts` delegates prefix handling to `applyLocalePrefix`.
 * ========================================================================== */

const KNOWN_LOCALES: readonly string[] = i18nConfig.locales;
const DEFAULT_LOCALE = i18nConfig.defaultLocale;
const PREFIX_DEFAULT = i18nConfig.prefixDefault;

/**
 * Apply locale prefix to a relative path according to i18nConfig.
 *
 * - For the default locale with `prefixDefault: false`, no prefix is added.
 * - Otherwise the path is prefixed with `/<locale>/`.
 * - The returned path always starts with `/` and has no trailing slash
 *   (except for the root `/`).
 */
export function applyLocalePrefix(locale: Locale, path: string): string {
  const cleanPath = path.replace(/^\/+/, '');
  const shouldPrefix = locale !== DEFAULT_LOCALE || PREFIX_DEFAULT;
  const prefix = shouldPrefix ? `${locale}/` : '';
  const result = `/${prefix}${cleanPath}`;

  if (result.length > 1 && result.endsWith('/')) {
    return result.slice(0, -1);
  }
  return result;
}

export interface ArticleLocaleInput {
  translatedSlugs?: Partial<Record<Locale, string>>;
  category?:
    | {
        slug?: string;
        translatedSlugs?: Partial<Record<Locale, string>>;
      }
    | string
    | null;
}

/**
 * Build the relative URL of an article in `targetLocale`, using per-locale
 * translated slugs when available.
 *
 * Returns `null` when no translated slug exists for `targetLocale` — the
 * caller must treat that locale as unavailable (disabled button).
 */
export function buildLocaleUrlForArticle(
  targetLocale: Locale,
  article: ArticleLocaleInput,
  currentLocale: Locale
): string | null {
  const articleSlug = article.translatedSlugs?.[targetLocale];
  if (!articleSlug) {
    return null;
  }

  const categoryObj = typeof article.category === 'object' ? article.category : null;
  const translatedCategorySlugs = categoryObj?.translatedSlugs;
  const categorySlug =
    translatedCategorySlugs?.[targetLocale] ??
    translatedCategorySlugs?.[currentLocale] ??
    translatedCategorySlugs?.[DEFAULT_LOCALE] ??
    (typeof article.category === 'string' ? article.category : categoryObj?.slug);

  if (!categorySlug) {
    return null;
  }

  return applyLocalePrefix(targetLocale, `${categorySlug}/${articleSlug}`);
}

export interface CategoryLocaleInput {
  slug?: string;
  translatedSlugs?: Partial<Record<Locale, string>>;
}

/**
 * Build the relative URL of a category in `targetLocale`. Falls back to the
 * slug in `currentLocale`, then the default-locale slug, then `category.slug`.
 * Never returns null — categories remain visible across locales by design.
 */
export function buildLocaleUrlForCategory(
  targetLocale: Locale,
  category: CategoryLocaleInput,
  currentLocale: Locale
): string {
  const slug =
    category.translatedSlugs?.[targetLocale] ??
    category.translatedSlugs?.[currentLocale] ??
    category.translatedSlugs?.[DEFAULT_LOCALE] ??
    category.slug ??
    '';

  return applyLocalePrefix(targetLocale, slug);
}

/**
 * Build the relative URL of the current path under `targetLocale`, stripping
 * any existing locale segment. Used for generic pages without translatable
 * slugs (homepage, about, search results, …).
 */
export function buildLocaleUrlGeneric(
  targetLocale: Locale,
  currentPath: string,
  _currentLocale: Locale
): string {
  const normalizedPath = currentPath.startsWith('/') ? currentPath : `/${currentPath}`;
  const segments = normalizedPath.split('/').filter(Boolean);

  if (segments.length > 0 && KNOWN_LOCALES.includes(segments[0])) {
    segments.shift();
  }

  const pathWithoutLocale = segments.join('/');
  return applyLocalePrefix(targetLocale, pathWithoutLocale);
}
