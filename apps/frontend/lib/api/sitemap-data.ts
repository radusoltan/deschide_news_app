/**
 * Sitemap Data Fetching
 *
 * API functions to fetch data for sitemap generation
 */

import { Locale } from '../seo/sitemap-config';
import { CACHE_TAGS } from '../data/cache-config';

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

/**
 * Article translation data for sitemap
 */
export interface ArticleTranslation {
  locale: Locale;
  slug: string;
  categorySlug: string;
  title: string;
}

/**
 * Article data for sitemap.
 *
 * `translations` is sparse: a locale key is present only if the article
 * has a real translated slug for that locale. Consumers must treat a
 * missing key as "do not emit a hreflang alternate for this locale".
 *
 * `publishedLocales` mirrors the backend flag — if a locale is not in
 * this list, the article has no public URL in that locale and must not
 * appear in any sitemap entry (primary URL or alternate).
 */
export interface SitemapArticle {
  id: number;
  slug: string;
  publishedAt: string;
  updatedAt: string;
  isFeatured: boolean;
  publishedLocales: Locale[];
  category: {
    slug: string;
  };
  translations: Partial<Record<Locale, ArticleTranslation>>;
  // For image sitemap
  articleImages?: Array<{
    image: {
      path: string;
      alt?: string;
      caption?: string;
      title?: string;
    };
  }>;
}

/**
 * Category data for sitemap
 */
export interface SitemapCategory {
  id: number;
  slug: string;
  translations: {
    ro: { slug: string; title: string };
    en: { slug: string; title: string };
    ru: { slug: string; title: string };
  };
}

/**
 * Author data for sitemap
 */
export interface SitemapAuthor {
  id: number;
  slug: string;
  name: string;
}

/**
 * Fetch all published articles with translations for sitemap
 */
export async function fetchAllArticlesForSitemap(): Promise<SitemapArticle[]> {
  try {
    const response = await fetch(
      `${API_URL}/api/articles?status=published&itemsPerPage=5000`,
      {
        headers: {
          'Accept': 'application/ld+json',
          'Accept-Language': 'ro', // Start with default locale
        },
        next: {
          revalidate: 3600, // ISR: revalidate every hour
          tags: [CACHE_TAGS.articles, CACHE_TAGS.locale('ro')],
        },
      }
    );

    if (!response.ok) {
      console.error('Failed to fetch articles for sitemap:', response.status);
      return [];
    }

    const data = await response.json();
    const articles = data['member'] ?? data['hydra:member'] ?? [];

    return articles.map(mapArticleToSitemap);
  } catch (error) {
    console.error('Error fetching articles for sitemap:', error);
    return [];
  }
}

/**
 * Raw article shape returned by `/api/articles` (list endpoint).
 */
interface RawArticle {
  id: number;
  title?: string;
  slug: string;
  publishedAt?: string;
  updatedAt?: string;
  archivedAt?: string;
  isFeatured?: boolean;
  publishedLocales?: string[];
  translatedSlugs?: Partial<Record<Locale, string>>;
  category?: {
    slug?: string;
    translatedSlugs?: Partial<Record<Locale, string>>;
  };
  articleImages?: Array<{ image: { path: string; alt?: string; caption?: string; title?: string } }>;
}

const SUPPORTED_LOCALES: Locale[] = ['ro', 'en', 'ru'];

function isSupportedLocale(value: string): value is Locale {
  return (SUPPORTED_LOCALES as string[]).includes(value);
}

/**
 * Build the sparse `translations` map: one entry per locale that has a
 * real translated slug. Consumers will iterate `publishedLocales` and
 * only emit hreflang alternates for locales present in this map.
 */
function buildTranslationsMap(article: RawArticle): Partial<Record<Locale, ArticleTranslation>> {
  const translations: Partial<Record<Locale, ArticleTranslation>> = {};
  const categorySlugFallback = article.category?.slug ?? '';
  const categoryTranslatedSlugs = article.category?.translatedSlugs ?? {};
  const articleTranslatedSlugs = article.translatedSlugs ?? {};

  for (const locale of SUPPORTED_LOCALES) {
    const slug = articleTranslatedSlugs[locale];
    if (!slug) {
      continue;
    }
    translations[locale] = {
      locale,
      slug,
      categorySlug: categoryTranslatedSlugs[locale] ?? categorySlugFallback,
      title: article.title ?? '',
    };
  }

  return translations;
}

function normalizePublishedLocales(raw: RawArticle): Locale[] {
  const list = Array.isArray(raw.publishedLocales) ? raw.publishedLocales : [];
  return list.filter(isSupportedLocale);
}

function mapArticleToSitemap(article: RawArticle): SitemapArticle {
  return {
    id: article.id,
    slug: article.slug,
    publishedAt: article.publishedAt ?? '',
    updatedAt: article.updatedAt ?? '',
    isFeatured: article.isFeatured ?? false,
    publishedLocales: normalizePublishedLocales(article),
    category: {
      slug: article.category?.slug ?? '',
    },
    translations: buildTranslationsMap(article),
    articleImages: article.articleImages ?? [],
  };
}

/**
 * Fetch articles for specific locale
 */
export async function fetchArticlesByLocale(locale: Locale): Promise<SitemapArticle[]> {
  try {
    const response = await fetch(
      `${API_URL}/api/articles?status=published&itemsPerPage=5000`,
      {
        headers: {
          'Accept': 'application/ld+json',
          'Accept-Language': locale,
        },
        next: {
          revalidate: 3600, // ISR: revalidate every hour
          tags: [CACHE_TAGS.articles, CACHE_TAGS.locale(locale)],
        },
      }
    );

    if (!response.ok) {
      console.error(`Failed to fetch articles for locale ${locale}:`, response.status);
      return [];
    }

    const data = await response.json();
    return data['member'] ?? data['hydra:member'] ?? [];
  } catch (error) {
    console.error(`Error fetching articles for locale ${locale}:`, error);
    return [];
  }
}

/**
 * Fetch recent articles for news sitemap (last 48 hours)
 */
export async function fetchRecentArticlesForNewsSitemap(): Promise<SitemapArticle[]> {
  try {
    const twoDaysAgo = new Date();
    twoDaysAgo.setHours(twoDaysAgo.getHours() - 48);

    const response = await fetch(
      `${API_URL}/api/articles?status=published&publishedAt[after]=${twoDaysAgo.toISOString()}&itemsPerPage=1000`,
      {
        headers: {
          'Accept': 'application/ld+json',
          'Accept-Language': 'ro',
        },
        next: {
          revalidate: 3600, // ISR: revalidate every hour
          tags: [CACHE_TAGS.articles, CACHE_TAGS.locale('ro')],
        },
      }
    );

    if (!response.ok) {
      console.error('Failed to fetch recent articles for news sitemap:', response.status);
      return [];
    }

    const data = await response.json();
    return data['member'] ?? data['hydra:member'] ?? [];
  } catch (error) {
    console.error('Error fetching recent articles for news sitemap:', error);
    return [];
  }
}

/**
 * Fetch all categories for sitemap
 */
export async function fetchAllCategoriesForSitemap(): Promise<SitemapCategory[]> {
  try {
    const response = await fetch(`${API_URL}/api/categories?itemsPerPage=100`, {
      headers: {
        'Accept': 'application/ld+json',
        'Accept-Language': 'ro',
      },
      next: {
        revalidate: 3600, // ISR: revalidate every hour
        tags: [CACHE_TAGS.categories, CACHE_TAGS.locale('ro')],
      },
    });

    if (!response.ok) {
      console.error('Failed to fetch categories for sitemap:', response.status);
      return [];
    }

    const data = await response.json();
    return data['member'] ?? data['hydra:member'] ?? [];
  } catch (error) {
    console.error('Error fetching categories for sitemap:', error);
    return [];
  }
}

/**
 * Fetch all authors for sitemap
 */
export async function fetchAllAuthorsForSitemap(): Promise<SitemapAuthor[]> {
  try {
    const response = await fetch(`${API_URL}/api/authors?itemsPerPage=100`, {
      headers: {
        'Accept': 'application/ld+json',
      },
      next: {
        revalidate: 3600, // ISR: revalidate every hour
        tags: [CACHE_TAGS.authors],
      },
    });

    if (!response.ok) {
      console.error('Failed to fetch authors for sitemap:', response.status);
      return [];
    }

    const data = await response.json();
    return data['member'] ?? data['hydra:member'] ?? [];
  } catch (error) {
    console.error('Error fetching authors for sitemap:', error);
    return [];
  }
}

/**
 * Get count of published articles
 */
export async function getArticleCount(): Promise<number> {
  try {
    const response = await fetch(
      `${API_URL}/api/articles?status=published&itemsPerPage=1`,
      {
        headers: {
          'Accept': 'application/ld+json',
        },
        next: {
          revalidate: 3600, // ISR: revalidate every hour
          tags: [CACHE_TAGS.articles],
        },
      }
    );

    if (!response.ok) return 0;

    const data = await response.json();
    return data['totalItems'] ?? data['hydra:totalItems'] ?? 0;
  } catch (error) {
    console.error('Error fetching article count:', error);
    return 0;
  }
}

/**
 * Archived article data for sitemap
 */
export interface ArchivedArticle extends SitemapArticle {
  archivedAt?: string;
}

/**
 * Fetch all archived articles for sitemap with pagination handling
 */
export async function fetchArchivedArticlesForSitemap(): Promise<ArchivedArticle[]> {
  const allArticles: ArchivedArticle[] = [];
  let currentPage = 1;
  let hasNextPage = true;

  try {
    while (hasNextPage) {
      const response = await fetch(
        `${API_URL}/api/archived_articles?page=${currentPage}&itemsPerPage=100`,
        {
          headers: {
            'Accept': 'application/ld+json',
            'Accept-Language': 'ro', // Start with default locale
          },
          next: {
            revalidate: 3600, // ISR: revalidate every hour
            tags: [CACHE_TAGS.articles, CACHE_TAGS.locale('ro')],
          },
        }
      );

      if (!response.ok) {
        console.error(`Failed to fetch archived articles page ${currentPage}:`, response.status);
        break;
      }

      const data = await response.json();
      const articles = data['member'] ?? data['hydra:member'] ?? [];

      const mappedArticles: ArchivedArticle[] = articles.map((article: RawArticle) => ({
        ...mapArticleToSitemap(article),
        archivedAt: article.archivedAt || article.updatedAt,
      }));

      allArticles.push(...mappedArticles);

      // Check if there's a next page
      const view = data['hydra:view'];
      hasNextPage = !!view?.['hydra:next'];
      currentPage++;

      // Safety limit to prevent infinite loops (adjust based on expected volume)
      if (currentPage > 1000) {
        console.warn('Reached maximum page limit (1000) when fetching archived articles');
        break;
      }
    }

    console.log(`Fetched ${allArticles.length} archived articles across ${currentPage - 1} pages`);
    return allArticles;
  } catch (error) {
    console.error('Error fetching archived articles for sitemap:', error);
    return allArticles; // Return what we've collected so far
  }
}
