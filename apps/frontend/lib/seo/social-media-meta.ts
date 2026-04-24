/**
 * Social Media Meta Tags
 *
 * Comprehensive Open Graph and Twitter Card generation
 * for optimal social media sharing
 */

import type { Article } from '@/lib/types/article';
import { buildLocalizedUrl } from './locale-url';

const SITE_NAME = process.env.NEXT_PUBLIC_APP_NAME || 'Deschide News';
const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL ?? '';
const CDN_URL = process.env.NEXT_PUBLIC_CDN_URL ?? '';
const TWITTER_HANDLE = '@deschidenews'; // Update with actual handle

/**
 * Open Graph Types
 */
export type OGType = 'website' | 'article' | 'profile';

/**
 * Twitter Card Types
 */
export type TwitterCardType = 'summary' | 'summary_large_image' | 'app' | 'player';

/**
 * Open Graph Image Configuration
 */
export interface OGImage {
  url: string;
  secureUrl?: string;
  type?: string;
  width?: number;
  height?: number;
  alt?: string;
}

/**
 * Open Graph Article Meta Tags
 */
export interface OGArticleMeta {
  publishedTime?: string;
  modifiedTime?: string;
  expirationTime?: string;
  author?: string[];
  section?: string;
  tag?: string[];
}

/**
 * Generate Open Graph image from article
 */
export function getOGImageFromArticle(
  article: Article,
  preferredSize: 'large' | 'medium' = 'large'
): OGImage | null {
  // Get featured image
  const featuredImage = article.articleImages?.find((ai) => ai.isFeatured)?.image;

  if (!featuredImage || typeof featuredImage === 'string') {
    return null;
  }

  // Type guard: check if image has path property (from API response)
  if (!featuredImage.path) {
    return null;
  }

  // Use original image path
  const imageUrl = `${CDN_URL}/uploads/${featuredImage.path}`;

  return {
    url: imageUrl,
    secureUrl: imageUrl.replace(/^http:\/\//, 'https://'),
    type: featuredImage.mimeType || 'image/jpeg',
    width: preferredSize === 'large' ? 1200 : 800,
    height: preferredSize === 'large' ? 630 : 600,
    alt: featuredImage.alt || article.title,
  };
}

/**
 * Generate fallback OG image (site logo or default image)
 */
export function getFallbackOGImage(): OGImage {
  return {
    url: `${SITE_URL}/og-default.png`,
    secureUrl: `${SITE_URL}/og-default.png`.replace(/^http:\/\//, 'https://'),
    type: 'image/png',
    width: 1200,
    height: 630,
    alt: SITE_NAME,
  };
}

/**
 * Generate Open Graph meta tags for article
 */
export interface OpenGraphMeta {
  type: OGType;
  title: string;
  description: string;
  url: string;
  siteName: string;
  locale: string;
  images: OGImage[];
  article?: OGArticleMeta;
}

export function generateArticleOGMeta(
  article: Article,
  locale: 'ro' | 'en' | 'ru',
  canonicalUrl: string
): OpenGraphMeta {
  const ogImage = getOGImageFromArticle(article) || getFallbackOGImage();

  // Extract author names (TODO: Fix - authors is string[] not Author[])
  const authorNames: string[] = [];

  // Get category title
  const categoryTitle = typeof article.category === 'object'
    ? article.category.title
    : article.category || 'News';

  // Extract tags if available (TODO: Fix - tags property doesn't exist on Article type)
  const tags: string[] = [];

  const localeMap = {
    ro: 'ro_RO',
    en: 'en_US',
    ru: 'ru_RU',
  };

  return {
    type: 'article',
    title: article.title,
    description: article.lead || article.title,
    url: canonicalUrl,
    siteName: SITE_NAME,
    locale: localeMap[locale],
    images: [ogImage],
    article: {
      publishedTime: article.publishedAt || undefined,
      modifiedTime: article.updatedAt || article.publishedAt || undefined,
      author: authorNames,
      section: categoryTitle,
      tag: tags,
    },
  };
}

/**
 * Generate Twitter Card meta tags
 */
export interface TwitterCardMeta {
  card: TwitterCardType;
  site?: string;
  creator?: string;
  title: string;
  description: string;
  image?: string;
  imageAlt?: string;
}

export function generateArticleTwitterCard(
  article: Article,
  locale: 'ro' | 'en' | 'ru'
): TwitterCardMeta {
  const ogImage = getOGImageFromArticle(article) || getFallbackOGImage();

  // Get author Twitter handle if available (TODO: Fix - authors is string[])
  const creatorHandle = undefined;

  return {
    card: 'summary_large_image',
    site: TWITTER_HANDLE,
    creator: creatorHandle,
    title: article.title,
    description: article.lead || article.title,
    image: ogImage.url,
    imageAlt: ogImage.alt,
  };
}

/**
 * Generate Facebook-specific meta tags
 */
export interface FacebookMeta {
  appId?: string;
  pages?: string;
  admins?: string;
}

export function generateFacebookMeta(): FacebookMeta {
  return {
    // Add Facebook App ID if you have one
    // appId: 'YOUR_FACEBOOK_APP_ID',
    // pages: 'YOUR_FACEBOOK_PAGE_ID',
  };
}

/**
 * Truncate text for social media descriptions
 * Facebook: 300 chars
 * Twitter: 200 chars
 */
export function truncateForSocial(text: string, platform: 'facebook' | 'twitter' = 'facebook'): string {
  const maxLength = platform === 'twitter' ? 200 : 300;

  if (!text || text.length <= maxLength) {
    return text;
  }

  return text.substring(0, maxLength - 3) + '...';
}

/**
 * Generate social media meta tags object for Next.js metadata
 */
export function generateSocialMediaMeta(
  article: Article,
  locale: 'ro' | 'en' | 'ru',
  canonicalUrl: string
) {
  const ogMeta = generateArticleOGMeta(article, locale, canonicalUrl);
  const twitterMeta = generateArticleTwitterCard(article, locale);
  const ogImage = ogMeta.images[0];

  return {
    openGraph: {
      type: ogMeta.type,
      title: ogMeta.title,
      description: truncateForSocial(ogMeta.description, 'facebook'),
      url: ogMeta.url,
      siteName: ogMeta.siteName,
      locale: ogMeta.locale,
      images: [
        {
          url: ogImage.url,
          secureUrl: ogImage.secureUrl,
          width: ogImage.width,
          height: ogImage.height,
          alt: ogImage.alt,
          type: ogImage.type,
        },
      ],
      ...(ogMeta.article && {
        publishedTime: ogMeta.article.publishedTime,
        modifiedTime: ogMeta.article.modifiedTime,
        authors: ogMeta.article.author,
        section: ogMeta.article.section,
        tags: ogMeta.article.tag,
      }),
    },
    twitter: {
      card: twitterMeta.card,
      site: twitterMeta.site,
      creator: twitterMeta.creator,
      title: twitterMeta.title,
      description: truncateForSocial(twitterMeta.description, 'twitter'),
      images: twitterMeta.image ? [twitterMeta.image] : [],
    },
  };
}

/**
 * Generate social media meta for homepage
 */
export function generateHomepageSocialMeta(locale: 'ro' | 'en' | 'ru') {
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

  const localeMap = {
    ro: 'ro_RO',
    en: 'en_US',
    ru: 'ru_RU',
  };

  const fallbackImage = getFallbackOGImage();

  return {
    openGraph: {
      type: 'website' as const,
      title: titles[locale],
      description: descriptions[locale],
      url: buildLocalizedUrl(SITE_URL, locale, ''),
      siteName: SITE_NAME,
      locale: localeMap[locale],
      images: [
        {
          url: fallbackImage.url,
          secureUrl: fallbackImage.secureUrl,
          width: fallbackImage.width,
          height: fallbackImage.height,
          alt: fallbackImage.alt,
          type: fallbackImage.type,
        },
      ],
    },
    twitter: {
      card: 'summary_large_image' as const,
      site: TWITTER_HANDLE,
      title: titles[locale],
      description: descriptions[locale],
      images: [fallbackImage.url],
    },
  };
}

/**
 * Generate social media meta for category pages
 */
export function generateCategorySocialMeta(
  categoryTitle: string,
  categorySlug: string,
  locale: 'ro' | 'en' | 'ru',
  description?: string
) {
  const defaultDescriptions = {
    ro: `Citește ultimele articole din categoria ${categoryTitle}. Știri și informații actualizate.`,
    en: `Read the latest articles from ${categoryTitle}. Updated news and information.`,
    ru: `Читайте последние статьи из категории ${categoryTitle}. Обновленные новости и информация.`,
  };

  const localeMap = {
    ro: 'ro_RO',
    en: 'en_US',
    ru: 'ru_RU',
  };

  const categoryUrl = buildLocalizedUrl(SITE_URL, locale, categorySlug);
  const fallbackImage = getFallbackOGImage();

  return {
    openGraph: {
      type: 'website' as const,
      title: `${categoryTitle} | ${SITE_NAME}`,
      description: description || defaultDescriptions[locale],
      url: categoryUrl,
      siteName: SITE_NAME,
      locale: localeMap[locale],
      images: [
        {
          url: fallbackImage.url,
          secureUrl: fallbackImage.secureUrl,
          width: fallbackImage.width,
          height: fallbackImage.height,
          alt: categoryTitle,
          type: fallbackImage.type,
        },
      ],
    },
    twitter: {
      card: 'summary_large_image' as const,
      site: TWITTER_HANDLE,
      title: `${categoryTitle} | ${SITE_NAME}`,
      description: description || defaultDescriptions[locale],
      images: [fallbackImage.url],
    },
  };
}
