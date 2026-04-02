/**
 * SEO Metadata Generator
 * Utilities for generating optimized metadata for articles
 */

import type { Metadata } from 'next';
import type { Article } from '@/lib/types/article';
import type { Locale } from '@/lib/types';

const SITE_NAME = process.env.NEXT_PUBLIC_APP_NAME || 'Deschide News';
const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL ?? '';

/**
 * Truncate text to specified length with ellipsis
 */
function truncate(text: string, maxLength: number): string {
  if (!text) return '';
  if (text.length <= maxLength) return text;
  return text.substring(0, maxLength - 3) + '...';
}

/**
 * Strip HTML tags from text
 */
function stripHtml(html: string): string {
  return html.replace(/<[^>]*>/g, '');
}

/**
 * Generate optimized title tag
 * Best practices: 50-60 characters, front-load keywords
 */
export function generateTitle(article: Article, locale: Locale): string {
  const baseTitle = article.metaTitle || truncate(article.title, 55);
  return `${baseTitle} | ${SITE_NAME}`;
}

/**
 * Generate optimized meta description
 * Best practices: 150-160 characters, include keywords, call-to-action
 */
export function generateDescription(article: Article): string {
  if (article.metaDescription) {
    return truncate(article.metaDescription, 160);
  }

  if (article.lead) {
    return truncate(article.lead, 155);
  }

  if (article.content) {
    const plainText = stripHtml(article.content);
    return truncate(plainText, 155);
  }

  return truncate(article.title, 155);
}

/**
 * Generate keywords from article data
 */
export function generateKeywords(article: Article): string[] {
  const keywords: string[] = [];

  // Add category
  if (article.category) {
    const categoryTitle = typeof article.category === 'object'
      ? article.category.title
      : article.category;
    if (categoryTitle) {
      keywords.push(categoryTitle);
    }
  }

  // Add author names
  if (article.authors && article.authors.length > 0) {
    article.authors.forEach((author: any) => {
      if (author.fullName) {
        keywords.push(author.fullName);
      }
    });
  }

  // Add tags if available (extract tag names from Tag objects or use string directly)
  if ('tags' in article && article.tags && Array.isArray(article.tags)) {
    article.tags.forEach((tag: any) => {
      if (typeof tag === 'string') {
        keywords.push(tag);
      } else if (tag && typeof tag === 'object' && tag.name) {
        keywords.push(tag.name);
      }
    });
  }

  return keywords;
}

/**
 * Get category slug safely
 */
export function getCategorySlug(category: any): string {
  if (!category) return 'uncategorized';
  if (typeof category === 'object' && category?.slug) {
    return category.slug;
  }
  if (typeof category === 'string') {
    return category;
  }
  return 'uncategorized';
}

/**
 * Build canonical URL for article
 */
export function buildCanonicalUrl(
  article: Article,
  locale: Locale
): string {
  const categorySlug = getCategorySlug(article.category);
  const localePrefix = locale === 'ro' ? '' : `${locale}/`;

  return `${SITE_URL}/${localePrefix}${categorySlug}/${article.slug}`;
}

/**
 * Build alternate language URLs
 */
export function buildAlternateUrls(
  article: Article,
  translations?: {
    ro?: { slug: string; category: { slug: string } };
    en?: { slug: string; category: { slug: string } };
    ru?: { slug: string; category: { slug: string } };
  }
): Record<string, string> {
  const categorySlug = getCategorySlug(article.category);

  // If translations are provided, use them
  if (translations) {
    return {
      ro: translations.ro
        ? `${SITE_URL}/${translations.ro.category.slug}/${translations.ro.slug}`
        : `${SITE_URL}/${categorySlug}/${article.slug}`,
      en: translations.en
        ? `${SITE_URL}/en/${translations.en.category.slug}/${translations.en.slug}`
        : `${SITE_URL}/en/${categorySlug}/${article.slug}`,
      ru: translations.ru
        ? `${SITE_URL}/ru/${translations.ru.category.slug}/${translations.ru.slug}`
        : `${SITE_URL}/ru/${categorySlug}/${article.slug}`,
      'x-default': translations.ro
        ? `${SITE_URL}/${translations.ro.category.slug}/${translations.ro.slug}`
        : `${SITE_URL}/${categorySlug}/${article.slug}`,
    };
  }

  // Fallback: use same slug for all locales
  return {
    ro: `${SITE_URL}/${categorySlug}/${article.slug}`,
    en: `${SITE_URL}/en/${categorySlug}/${article.slug}`,
    ru: `${SITE_URL}/ru/${categorySlug}/${article.slug}`,
    'x-default': `${SITE_URL}/${categorySlug}/${article.slug}`,
  };
}

/**
 * Get author names as array
 */
export function getAuthorNames(article: Article): string[] {
  if (!article.authors || article.authors.length === 0) {
    return [];
  }

  return article.authors
    .map((author: any) => author.fullName || author.name)
    .filter(Boolean);
}

/**
 * Get category title safely
 */
export function getCategoryTitle(category: any): string {
  if (!category) return 'News';
  if (typeof category === 'object' && category?.title) {
    return category.title;
  }
  if (typeof category === 'string') {
    return category;
  }
  return 'News';
}

/**
 * Generate publication date string
 */
export function getPublicationDate(article: Article): string {
  return article.publishedAt || article.createdAt || new Date().toISOString();
}

/**
 * Generate modification date string
 */
export function getModificationDate(article: Article): string {
  return article.updatedAt || article.publishedAt || article.createdAt || new Date().toISOString();
}

/**
 * Generate complete metadata object for Next.js
 */
export function generateArticleMetadata(
  article: Article,
  locale: Locale,
  imageUrl?: string
): Metadata {
  const title = generateTitle(article, locale);
  const description = generateDescription(article);
  const keywords = generateKeywords(article);
  const canonicalUrl = buildCanonicalUrl(article, locale);

  // Build translations for hreflang alternate URLs using translatedSlugs
  const articleSlugs = article.translatedSlugs;
  const categorySlugs = typeof article.category === 'object' ? article.category?.translatedSlugs : undefined;
  const translations = (articleSlugs || categorySlugs)
    ? {
        ro: {
          slug: articleSlugs?.ro || article.slug,
          category: { slug: categorySlugs?.ro || getCategorySlug(article.category) },
        },
        en: {
          slug: articleSlugs?.en || article.slug,
          category: { slug: categorySlugs?.en || getCategorySlug(article.category) },
        },
        ru: {
          slug: articleSlugs?.ru || article.slug,
          category: { slug: categorySlugs?.ru || getCategorySlug(article.category) },
        },
      }
    : undefined;
  const alternateUrls = buildAlternateUrls(article, translations);
  const authorNames = getAuthorNames(article);
  const categoryTitle = getCategoryTitle(article.category);
  const publishedTime = getPublicationDate(article);
  const modifiedTime = getModificationDate(article);

  return {
    title,
    description,
    keywords: keywords.join(', '),
    authors: authorNames.map((name) => ({ name })),
    category: categoryTitle,

    // Open Graph
    openGraph: {
      type: 'article',
      title: article.title,
      description,
      url: canonicalUrl,
      siteName: SITE_NAME,
      locale: locale === 'ro' ? 'ro_RO' : locale === 'en' ? 'en_US' : 'ru_RU',
      images: imageUrl ? [
        {
          url: imageUrl,
          width: 1200,
          height: 630,
          alt: article.title,
        },
      ] : [],
      publishedTime,
      modifiedTime,
      authors: authorNames,
      section: categoryTitle,
    },

    // Twitter Card
    twitter: {
      card: 'summary_large_image',
      title: article.title,
      description,
      images: imageUrl ? [imageUrl] : [],
      // TODO: Fix creator - article.authors is string[] not Author[]
      creator: undefined,
    },

    // Alternate locales
    alternates: {
      canonical: canonicalUrl,
      languages: alternateUrls,
    },

    /**
     * Robots configuration for article SEO:
     * - Published articles: fully indexed (index: true, follow: true)
     * - Archived articles: not indexed but links followed (index: false, follow: true)
     *   This preserves link equity while removing outdated content from search results
     * - Draft/other: completely hidden (index: false, follow: false)
     */
    robots: {
      index: article.status === 'published',
      follow: article.status === 'published' || article.status === 'archived',
      noarchive: article.status === 'archived',
      googleBot: {
        index: article.status === 'published',
        follow: article.status === 'published' || article.status === 'archived',
        noarchive: article.status === 'archived',
        'max-video-preview': -1,
        'max-image-preview': 'large',
        'max-snippet': -1,
      },
    },

    // Additional metadata
    other: {
      'article:published_time': publishedTime,
      'article:modified_time': modifiedTime,
      'article:author': authorNames.join(', '),
      'article:section': categoryTitle,
    },
  };
}
