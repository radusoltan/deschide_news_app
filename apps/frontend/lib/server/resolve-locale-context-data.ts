/**
 * Server-side resolver that primes `LocaleContext` for the requested page
 * before any React rendering happens. Without this, the shared
 * `LanguageSwitcher` falls through to `buildLocaleUrlGeneric` (naive
 * locale-prefix swap) on the SSR pass and emits non-canonical hrefs in raw
 * HTML — broken for SEO crawlers and copy-link sharing (see ADR-029).
 *
 * Called from `(public)/layout.tsx` with the proxy-injected `x-pathname`
 * header. Result is passed as `initialData` to `LocaleContextProvider`,
 * so the very first render produces correct hreflang URLs.
 *
 * Fail-soft: any fetch error returns the generic `INITIAL_DATA` shape.
 * The page's own `<LocaleContextSetter>` will overwrite once data arrives
 * client-side.
 */

import type { LocaleContextData } from '@/lib/contexts/LocaleContext';
import type { Locale } from '@/lib/types';
import type { Article, Category } from '@/lib/types/article';
import type { Tag } from '@/lib/types/tag';
import { lookupArticle } from '@/lib/api/slug-lookup';
import { fetchCategories } from '@/lib/api/categories';
import { fetchTags } from '@/lib/api/tags';
import { fetchTopicBySlug } from '@/lib/api/topics';
import { isReservedSlug } from '@/lib/constants/reserved-slugs';

const LOCALES: readonly Locale[] = ['ro', 'en', 'ru'] as const;

/**
 * Static page slugs not present in `RESERVED_SLUGS` (which is auto-generated
 * from the backend's category-name validator). Listing them here lets the
 * resolver skip a category lookup for known-static one-segment paths like
 * `/{locale}/gdpr` — the slug is shared across locales, generic prefix-swap
 * is canonical, no fetch needed.
 */
const STATIC_PAGE_SLUGS: ReadonlySet<string> = new Set([
  'gdpr',
  'license',
  'team',
  'advertise',
  'media-kit',
  'emisiuni',
]);

const GENERIC: LocaleContextData = {
  publishedLocales: undefined,
  translatedSlugs: undefined,
  categoryTranslatedSlugs: undefined,
  context: 'generic',
};

/** Strip leading locale segment if present and split on '/'. */
function splitPathname(pathname: string, currentLocale: Locale): string[] {
  const trimmed = pathname.replace(/^\/+/, '').replace(/\/+$/, '');
  if (!trimmed) return [];
  const segments = trimmed.split('/');
  if (segments[0] === currentLocale || (LOCALES as readonly string[]).includes(segments[0])) {
    segments.shift();
  }
  return segments;
}

async function resolveTopic(slug: string, locale: Locale): Promise<LocaleContextData> {
  const topic = await fetchTopicBySlug(slug, locale);
  if (!topic?.translatedSlugs) {
    return { ...GENERIC, context: 'topic' };
  }
  return {
    publishedLocales: undefined,
    translatedSlugs: topic.translatedSlugs,
    categoryTranslatedSlugs: undefined,
    context: 'topic',
  };
}

async function resolveTag(slug: string, locale: Locale): Promise<LocaleContextData> {
  try {
    const response = await fetchTags(locale, { slug, itemsPerPage: 1 });
    const tag: Tag | undefined = response['hydra:member']?.[0];
    if (!tag?.translatedSlugs) {
      return { ...GENERIC, context: 'tag' };
    }
    return {
      publishedLocales: undefined,
      translatedSlugs: tag.translatedSlugs,
      categoryTranslatedSlugs: undefined,
      context: 'tag',
    };
  } catch {
    return { ...GENERIC, context: 'tag' };
  }
}

async function resolveArticle(
  categorySlug: string,
  articleSlug: string,
  locale: Locale,
): Promise<LocaleContextData | null> {
  const article: Article | null = await lookupArticle(articleSlug, locale);
  if (!article) return null;

  const articleCategory = typeof article.category === 'object' ? article.category : null;
  if (articleCategory?.slug !== categorySlug) {
    return null;
  }

  return {
    publishedLocales: article.publishedLocales,
    translatedSlugs: article.translatedSlugs,
    categoryTranslatedSlugs: articleCategory?.translatedSlugs,
    context: 'article',
  };
}

async function resolveCategory(slug: string, locale: Locale): Promise<LocaleContextData | null> {
  try {
    const response = await fetchCategories(locale);
    const category: Category | undefined = (response.member || []).find(
      (cat: Category) => cat.slug === slug,
    );
    if (!category?.translatedSlugs) return null;
    return {
      publishedLocales: undefined,
      translatedSlugs: category.translatedSlugs,
      categoryTranslatedSlugs: undefined,
      context: 'category',
    };
  } catch {
    return null;
  }
}

/**
 * Branch order is most-specific-first to handle the greedy
 * `/[locale]/<categorySlug>/<articleSlug>` pattern correctly.
 */
export async function resolveLocaleContextData(
  pathname: string,
  currentLocale: Locale,
): Promise<LocaleContextData> {
  try {
    const segments = splitPathname(pathname, currentLocale);

    // Homepage
    if (segments.length === 0) {
      return GENERIC;
    }

    const first = segments[0];
    const second = segments[1];

    // Generic-context routes (slug shared across locales — naive prefix-swap is correct)
    if (first === 'search' || first === 'archive' || first === 'all' || first === 'trending') {
      return GENERIC;
    }

    if (first === 'topics' && second) {
      return resolveTopic(second, currentLocale);
    }

    if (first === 'author' && second) {
      // Author slugs are non-translatable (shared across all locales).
      return GENERIC;
    }

    if (first === 'tags' && second) {
      return resolveTag(second, currentLocale);
    }

    // Reserved system slugs — never an article/category route.
    if (isReservedSlug(first)) {
      return GENERIC;
    }

    // Known-static one-segment pages (slug shared across locales).
    if (segments.length === 1 && STATIC_PAGE_SLUGS.has(first)) {
      return GENERIC;
    }

    // Article: /[locale]/<categorySlug>/<articleSlug>
    if (segments.length >= 2 && second && !isReservedSlug(second)) {
      const articleResult = await resolveArticle(first, second, currentLocale);
      if (articleResult) return articleResult;
      // fall through if article not found / category mismatch — try category
    }

    // Category: /[locale]/<categorySlug>
    if (segments.length === 1) {
      const categoryResult = await resolveCategory(first, currentLocale);
      if (categoryResult) return categoryResult;
    }

    return GENERIC;
  } catch {
    return GENERIC;
  }
}
