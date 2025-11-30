/**
 * Sitemap Data Fetching
 *
 * API functions to fetch data for sitemap generation
 */

import { Locale } from '../seo/sitemap-config';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

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
 * Article data for sitemap
 */
export interface SitemapArticle {
  id: number;
  slug: string;
  publishedAt: string;
  updatedAt: string;
  isFeatured: boolean;
  category: {
    slug: string;
  };
  translations: {
    ro: ArticleTranslation;
    en: ArticleTranslation;
    ru: ArticleTranslation;
  };
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
        cache: 'no-store', // Ensure we get fresh data
      }
    );

    if (!response.ok) {
      console.error('Failed to fetch articles for sitemap:', response.status);
      return [];
    }

    const data = await response.json();
    const articles = data['hydra:member'] || [];

    // For each article, we need to fetch translations
    // In production, you might want to optimize this with a dedicated endpoint
    return articles.map((article: any) => ({
      id: article.id,
      slug: article.slug,
      publishedAt: article.publishedAt,
      updatedAt: article.updatedAt,
      isFeatured: article.isFeatured || false,
      category: {
        slug: article.category?.slug || '',
      },
      translations: {
        ro: {
          locale: 'ro' as Locale,
          slug: article.slug,
          categorySlug: article.category?.slug || '',
          title: article.title,
        },
        en: {
          locale: 'en' as Locale,
          slug: article.slug, // TODO: Fetch actual translation
          categorySlug: article.category?.slug || '',
          title: article.title,
        },
        ru: {
          locale: 'ru' as Locale,
          slug: article.slug, // TODO: Fetch actual translation
          categorySlug: article.category?.slug || '',
          title: article.title,
        },
      },
      articleImages: article.articleImages || [],
    }));
  } catch (error) {
    console.error('Error fetching articles for sitemap:', error);
    return [];
  }
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
        cache: 'no-store',
      }
    );

    if (!response.ok) {
      console.error(`Failed to fetch articles for locale ${locale}:`, response.status);
      return [];
    }

    const data = await response.json();
    return data['hydra:member'] || [];
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
        cache: 'no-store',
      }
    );

    if (!response.ok) {
      console.error('Failed to fetch recent articles for news sitemap:', response.status);
      return [];
    }

    const data = await response.json();
    return data['hydra:member'] || [];
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
      cache: 'no-store',
    });

    if (!response.ok) {
      console.error('Failed to fetch categories for sitemap:', response.status);
      return [];
    }

    const data = await response.json();
    return data['hydra:member'] || [];
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
      cache: 'no-store',
    });

    if (!response.ok) {
      console.error('Failed to fetch authors for sitemap:', response.status);
      return [];
    }

    const data = await response.json();
    return data['hydra:member'] || [];
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
        cache: 'no-store',
      }
    );

    if (!response.ok) return 0;

    const data = await response.json();
    return data['hydra:totalItems'] || 0;
  } catch (error) {
    console.error('Error fetching article count:', error);
    return 0;
  }
}

/**
 * Archived article data for sitemap
 */
export interface ArchivedArticle {
  id: number;
  slug: string;
  updatedAt: string;
  archivedAt?: string;
  category: {
    slug: string;
  };
  translations: {
    ro: ArticleTranslation;
    en: ArticleTranslation;
    ru: ArticleTranslation;
  };
}

/**
 * Fetch all archived articles for sitemap with pagination handling
 */
export async function fetchAllArchivedArticlesForSitemap(): Promise<ArchivedArticle[]> {
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
          cache: 'no-store',
        }
      );

      if (!response.ok) {
        console.error(`Failed to fetch archived articles page ${currentPage}:`, response.status);
        break;
      }

      const data = await response.json();
      const articles = data['hydra:member'] || [];

      // Map to our interface
      const mappedArticles = articles.map((article: any) => ({
        id: article.id,
        slug: article.slug,
        updatedAt: article.updatedAt,
        archivedAt: article.archivedAt || article.updatedAt,
        category: {
          slug: article.category?.slug || '',
        },
        translations: {
          ro: {
            locale: 'ro' as Locale,
            slug: article.slug,
            categorySlug: article.category?.slug || '',
            title: article.title,
          },
          en: {
            locale: 'en' as Locale,
            slug: article.slug, // TODO: Fetch actual translation
            categorySlug: article.category?.slug || '',
            title: article.title,
          },
          ru: {
            locale: 'ru' as Locale,
            slug: article.slug, // TODO: Fetch actual translation
            categorySlug: article.category?.slug || '',
            title: article.title,
          },
        },
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
