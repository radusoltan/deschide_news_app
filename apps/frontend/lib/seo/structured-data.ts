/**
 * Structured Data Generator (JSON-LD)
 * Generates schema.org structured data for SEO
 */

import type { Article } from '@/lib/types/article';
import type { Locale } from '@/lib/types';
import { getCategorySlug, getCategoryTitle, getAuthorNames } from './metadata-generator';

const SITE_NAME = process.env.NEXT_PUBLIC_APP_NAME || 'Deschide News';
const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL ?? '';
const ORGANIZATION_LOGO = `${SITE_URL}/logo.png`;

/**
 * Organization Schema
 * Represents the publisher/organization
 */
export interface OrganizationSchema {
  '@type': 'Organization';
  name: string;
  url: string;
  logo: {
    '@type': 'ImageObject';
    url: string;
  };
}

export function generateOrganizationSchema(): OrganizationSchema {
  return {
    '@type': 'Organization',
    name: SITE_NAME,
    url: SITE_URL,
    logo: {
      '@type': 'ImageObject',
      url: ORGANIZATION_LOGO,
    },
  };
}

/**
 * Person Schema
 * Represents article author
 */
export interface PersonSchema {
  '@type': 'Person';
  name: string;
  url?: string;
}

export function generatePersonSchema(author: any, locale: Locale): PersonSchema {
  const authorSlug = author.slug || '';
  const localePrefix = locale === 'ro' ? '' : `${locale}/`;

  return {
    '@type': 'Person',
    name: author.fullName || author.name || 'Unknown Author',
    url: authorSlug ? `${SITE_URL}/${localePrefix}author/${authorSlug}` : undefined,
  };
}

/**
 * NewsArticle Schema
 * Main article structured data
 *
 * Archive-specific metadata strategy:
 * - For archived articles, we add the 'expires' property to indicate when the content
 *   was archived, signaling to search engines that the content may be outdated
 * - We update 'dateModified' to reflect the archive date, as archival is a significant
 *   modification to the article's status
 * - This helps search engines understand the freshness and relevance of the content
 */
export interface NewsArticleSchema {
  '@context': 'https://schema.org';
  '@type': 'NewsArticle';
  headline: string;
  description?: string;
  image?: string[];
  datePublished: string;
  dateModified: string;
  author: PersonSchema | PersonSchema[];
  publisher: OrganizationSchema;
  mainEntityOfPage: {
    '@type': 'WebPage';
    '@id': string;
  };
  articleSection?: string;
  keywords?: string;
  wordCount?: number;
  inLanguage: string;
  expires?: string; // Date when content was archived (for archived articles)
}

export function generateNewsArticleSchema(
  article: Article,
  locale: Locale,
  imageUrl?: string,
  additionalImages?: string[]
): NewsArticleSchema {
  const categorySlug = getCategorySlug(article.category);
  const localePrefix = locale === 'ro' ? '' : `${locale}/`;
  const articleUrl = `${SITE_URL}/${localePrefix}${categorySlug}/${article.slug}`;

  // Check if article is archived
  const isArchived = article.status === 'archived' || !!article.archivedAt;

  // Generate author schemas
  const authors = article.authors || [];
  const authorSchemas = authors.map((author) => generatePersonSchema(author, locale));

  // Calculate word count from content
  let wordCount: number | undefined;
  if (article.content) {
    const plainText = article.content.replace(/<[^>]*>/g, '');
    wordCount = plainText.split(/\s+/).length;
  }

  // Get keywords (category + authors + tags)
  const tagNames = (article.tags || [])
    .map((tag: any) => (typeof tag === 'object' && tag?.name ? tag.name : null))
    .filter(Boolean);
  const keywords = [
    getCategoryTitle(article.category),
    ...getAuthorNames(article),
    ...tagNames,
  ].filter(Boolean).join(', ');

  // Determine dateModified based on archive status
  // For archived articles, use archivedAt as the last modification date
  const dateModified = isArchived && article.archivedAt
    ? article.archivedAt
    : (article.updatedAt || article.publishedAt || article.createdAt);

  // Build image array with multiple aspect ratios for rich results
  // Google recommends including images in 16:9, 4:3, and 1:1 aspect ratios
  const images: string[] = [];
  if (imageUrl) {
    images.push(imageUrl);
  }
  if (additionalImages && additionalImages.length > 0) {
    images.push(...additionalImages);
  }

  const schema: NewsArticleSchema = {
    '@context': 'https://schema.org',
    '@type': 'NewsArticle',
    headline: article.title,
    description: article.lead || undefined,
    image: images.length > 0 ? images : undefined,
    datePublished: article.publishedAt || article.createdAt,
    dateModified,
    author: authorSchemas.length === 1 ? authorSchemas[0] : authorSchemas,
    publisher: generateOrganizationSchema(),
    mainEntityOfPage: {
      '@type': 'WebPage',
      '@id': articleUrl,
    },
    articleSection: getCategoryTitle(article.category),
    keywords,
    wordCount,
    inLanguage: locale === 'ro' ? 'ro-RO' : locale === 'en' ? 'en-US' : 'ru-RU',
  };

  // Add archive-specific metadata for archived articles
  if (isArchived && article.archivedAt) {
    schema.expires = article.archivedAt;
  }

  return schema;
}

/**
 * BreadcrumbList Schema
 * Navigation breadcrumbs for SEO
 */
export interface BreadcrumbSchema {
  '@context': 'https://schema.org';
  '@type': 'BreadcrumbList';
  itemListElement: Array<{
    '@type': 'ListItem';
    position: number;
    name: string;
    item?: string;
  }>;
}

export function generateBreadcrumbSchema(
  article: Article,
  locale: Locale
): BreadcrumbSchema {
  const categorySlug = getCategorySlug(article.category);
  const categoryTitle = getCategoryTitle(article.category);
  const localePrefix = locale === 'ro' ? '' : `${locale}/`;

  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: [
      {
        '@type': 'ListItem',
        position: 1,
        name: 'Home',
        item: `${SITE_URL}/${localePrefix}`,
      },
      {
        '@type': 'ListItem',
        position: 2,
        name: categoryTitle,
        item: `${SITE_URL}/${localePrefix}${categorySlug}`,
      },
      {
        '@type': 'ListItem',
        position: 3,
        name: article.title,
        // Last item should not have 'item' property according to Google guidelines
      },
    ],
  };
}

/**
 * WebPage Schema
 * Represents the web page itself
 */
export interface WebPageSchema {
  '@context': 'https://schema.org';
  '@type': 'WebPage';
  '@id': string;
  url: string;
  name: string;
  description?: string;
  publisher: OrganizationSchema;
  inLanguage: string;
  datePublished: string;
  dateModified: string;
}

export function generateWebPageSchema(
  article: Article,
  locale: Locale
): WebPageSchema {
  const categorySlug = getCategorySlug(article.category);
  const localePrefix = locale === 'ro' ? '' : `${locale}/`;
  const articleUrl = `${SITE_URL}/${localePrefix}${categorySlug}/${article.slug}`;

  // Check if article is archived and use archivedAt as dateModified
  const isArchived = article.status === 'archived' || !!article.archivedAt;
  const dateModified = isArchived && article.archivedAt
    ? article.archivedAt
    : (article.updatedAt || article.publishedAt || article.createdAt);

  return {
    '@context': 'https://schema.org',
    '@type': 'WebPage',
    '@id': articleUrl,
    url: articleUrl,
    name: article.title,
    description: article.lead || undefined,
    publisher: generateOrganizationSchema(),
    inLanguage: locale === 'ro' ? 'ro-RO' : locale === 'en' ? 'en-US' : 'ru-RU',
    datePublished: article.publishedAt || article.createdAt,
    dateModified,
  };
}

/**
 * Generate all structured data for an article
 * Returns array of JSON-LD schemas
 *
 * @param article - The article object
 * @param locale - The current locale
 * @param imageUrl - Primary featured image URL (16:9 aspect ratio recommended)
 * @param additionalImages - Array of additional image URLs (4:3, 1:1 aspect ratios)
 */
export function generateArticleStructuredData(
  article: Article,
  locale: Locale,
  imageUrl?: string,
  additionalImages?: string[]
): Array<NewsArticleSchema | BreadcrumbSchema | WebPageSchema> {
  return [
    generateNewsArticleSchema(article, locale, imageUrl, additionalImages),
    generateBreadcrumbSchema(article, locale),
    generateWebPageSchema(article, locale),
  ];
}

/**
 * Render structured data as script tag content
 * Use this in a <script type="application/ld+json"> tag
 */
export function renderStructuredData(schemas: any[]): string {
  return JSON.stringify(schemas, null, 2);
}
