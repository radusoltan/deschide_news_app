/**
 * Meta Tags Generation Utilities
 *
 * Comprehensive meta tags for SEO, social media, and browser features
 */

import type { Metadata } from 'next';

const SITE_NAME = process.env.NEXT_PUBLIC_APP_NAME || 'Deschide News';
const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'http://localhost:3005';
const DEFAULT_OG_IMAGE = `${SITE_URL}/images/og-default.jpg`;
const DEFAULT_OG_IMAGE_ALT = 'Deschide News - Portal de știri';

export interface MetaTagsConfig {
  title: string;
  description: string;
  keywords?: string[];
  author?: string;
  locale: 'ro' | 'en' | 'ru';
  canonicalUrl?: string;
  alternateUrls?: {
    ro?: string;
    en?: string;
    ru?: string;
  };
  imageUrl?: string;
  imageAlt?: string;
  publishedTime?: string;
  modifiedTime?: string;
  section?: string; // Article section/category
  noindex?: boolean;
  nofollow?: boolean;
}

/**
 * Generate complete metadata object for any page
 */
export function generatePageMetadata(config: MetaTagsConfig): Metadata {
  const {
    title,
    description,
    keywords = [],
    author,
    locale,
    canonicalUrl,
    alternateUrls = {},
    imageUrl,
    imageAlt,
    publishedTime,
    modifiedTime,
    section,
    noindex = false,
    nofollow = false,
  } = config;

  // Full title with site name
  const fullTitle = title.includes(SITE_NAME) ? title : `${title} | ${SITE_NAME}`;

  // OG locale mapping
  const ogLocaleMap = {
    ro: 'ro_RO',
    en: 'en_US',
    ru: 'ru_RU',
  };

  // Language alternates
  const languages: Record<string, string> = {};
  if (alternateUrls.ro) languages.ro = alternateUrls.ro;
  if (alternateUrls.en) languages.en = alternateUrls.en;
  if (alternateUrls.ru) languages.ru = alternateUrls.ru;

  // Set x-default to Romanian (default locale)
  if (alternateUrls.ro) {
    languages['x-default'] = alternateUrls.ro;
  }

  const metadata: Metadata = {
    title: fullTitle,
    description,
    keywords: keywords.length > 0 ? keywords.join(', ') : undefined,
    authors: author ? [{ name: author }] : undefined,

    // Open Graph - always include an image (use default if none provided)
    openGraph: {
      type: publishedTime ? 'article' : 'website',
      title,
      description,
      url: canonicalUrl || SITE_URL,
      siteName: SITE_NAME,
      locale: ogLocaleMap[locale],
      images: [
        {
          url: imageUrl || DEFAULT_OG_IMAGE,
          width: 1200,
          height: 630,
          alt: imageAlt || (imageUrl ? title : DEFAULT_OG_IMAGE_ALT),
        },
      ],
      ...(publishedTime && {
        publishedTime,
        modifiedTime: modifiedTime || publishedTime,
      }),
      ...(section && { section }),
    },

    // Twitter Card - always include an image (use default if none provided)
    twitter: {
      card: 'summary_large_image',
      title,
      description,
      images: [imageUrl || DEFAULT_OG_IMAGE],
      site: '@deschidenews', // Replace with actual Twitter handle
    },

    // Alternate locales (hreflang)
    alternates: {
      canonical: canonicalUrl,
      languages: Object.keys(languages).length > 0 ? languages : undefined,
    },

    // Robots
    robots: {
      index: !noindex,
      follow: !nofollow,
      googleBot: {
        index: !noindex,
        follow: !nofollow,
        'max-video-preview': -1,
        'max-image-preview': 'large',
        'max-snippet': -1,
      },
    },

    // Additional meta tags
    other: {
      'content-language': locale,
      ...(publishedTime && { 'article:published_time': publishedTime }),
      ...(modifiedTime && { 'article:modified_time': modifiedTime }),
      ...(author && { 'article:author': author }),
      ...(section && { 'article:section': section }),
    },
  };

  return metadata;
}

/**
 * Generate metadata for homepage
 */
export function generateHomepageMetadata(locale: 'ro' | 'en' | 'ru'): Metadata {
  const titles = {
    ro: 'Deschide News - Știri și Informații',
    en: 'Deschide News - News and Information',
    ru: 'Deschide News - Новости и Информация',
  };

  const descriptions = {
    ro: 'Portal de știri și informații în limba română, engleză și rusă. Ultimele știri din Moldova și din lume.',
    en: 'News and information portal in Romanian, English and Russian. Latest news from Moldova and worldwide.',
    ru: 'Новостной и информационный портал на румынском, английском и русском языках. Последние новости из Молдовы и мира.',
  };

  const keywords = {
    ro: ['știri', 'Moldova', 'actualitate', 'politică', 'economie', 'societate'],
    en: ['news', 'Moldova', 'current events', 'politics', 'economy', 'society'],
    ru: ['новости', 'Молдова', 'текущие события', 'политика', 'экономика', 'общество'],
  };

  const localePrefix = locale === 'ro' ? '' : `${locale}/`;

  return generatePageMetadata({
    title: titles[locale],
    description: descriptions[locale],
    keywords: keywords[locale],
    locale,
    canonicalUrl: `${SITE_URL}/${localePrefix}`,
    alternateUrls: {
      ro: `${SITE_URL}/`,
      en: `${SITE_URL}/en/`,
      ru: `${SITE_URL}/ru/`,
    },
  });
}

/**
 * Generate metadata for category pages
 */
export function generateCategoryMetadata(
  categoryTitle: string,
  categorySlug: string,
  locale: 'ro' | 'en' | 'ru',
  description?: string
): Metadata {
  const localePrefix = locale === 'ro' ? '' : `${locale}/`;

  const defaultDescriptions = {
    ro: `Citește ultimele articole din categoria ${categoryTitle}. Știri și informații actualizate.`,
    en: `Read the latest articles from ${categoryTitle}. Updated news and information.`,
    ru: `Читайте последние статьи из категории ${categoryTitle}. Обновленные новости и информация.`,
  };

  return generatePageMetadata({
    title: categoryTitle,
    description: description || defaultDescriptions[locale],
    locale,
    canonicalUrl: `${SITE_URL}/${localePrefix}${categorySlug}`,
    alternateUrls: {
      ro: `${SITE_URL}/${categorySlug}`,
      en: `${SITE_URL}/en/${categorySlug}`,
      ru: `${SITE_URL}/ru/${categorySlug}`,
    },
  });
}

/**
 * Generate metadata for author pages
 */
export function generateAuthorMetadata(
  authorName: string,
  authorSlug: string,
  locale: 'ro' | 'en' | 'ru',
  bio?: string
): Metadata {
  const localePrefix = locale === 'ro' ? '' : `${locale}/`;

  const defaultDescriptions = {
    ro: `Articole scrise de ${authorName}. Citește toate articolele autorului.`,
    en: `Articles written by ${authorName}. Read all author's articles.`,
    ru: `Статьи написанные ${authorName}. Читайте все статьи автора.`,
  };

  return generatePageMetadata({
    title: authorName,
    description: bio || defaultDescriptions[locale],
    author: authorName,
    locale,
    canonicalUrl: `${SITE_URL}/${localePrefix}author/${authorSlug}`,
    alternateUrls: {
      ro: `${SITE_URL}/author/${authorSlug}`,
      en: `${SITE_URL}/en/author/${authorSlug}`,
      ru: `${SITE_URL}/ru/author/${authorSlug}`,
    },
  });
}

/**
 * Generate metadata for static pages (About, Contact, etc.)
 */
export function generateStaticPageMetadata(
  title: string,
  description: string,
  slug: string,
  locale: 'ro' | 'en' | 'ru'
): Metadata {
  const localePrefix = locale === 'ro' ? '' : `${locale}/`;

  return generatePageMetadata({
    title,
    description,
    locale,
    canonicalUrl: `${SITE_URL}/${localePrefix}${slug}`,
    alternateUrls: {
      ro: `${SITE_URL}/${slug}`,
      en: `${SITE_URL}/en/${slug}`,
      ru: `${SITE_URL}/ru/${slug}`,
    },
  });
}

/**
 * Generate viewport metadata
 */
export function generateViewport() {
  return {
    width: 'device-width',
    initialScale: 1,
    maximumScale: 5,
    userScalable: true,
    themeColor: [
      { media: '(prefers-color-scheme: light)', color: '#ffffff' },
      { media: '(prefers-color-scheme: dark)', color: '#0a0a0a' },
    ],
  };
}

/**
 * Generate verification meta tags for search engines
 */
export interface VerificationTags {
  google?: string;
  yandex?: string;
  bing?: string;
}

export function generateVerificationTags(tags: VerificationTags): Record<string, string> {
  const verification: Record<string, string> = {};

  if (tags.google) {
    verification['google-site-verification'] = tags.google;
  }

  if (tags.yandex) {
    verification['yandex-verification'] = tags.yandex;
  }

  if (tags.bing) {
    verification['msvalidate.01'] = tags.bing;
  }

  return verification;
}
