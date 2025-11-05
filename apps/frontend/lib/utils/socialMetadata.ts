/**
 * Social Media Metadata Utilities
 *
 * Utilities for generating Open Graph and Twitter Cards metadata
 * for optimal social media sharing
 */

import type { LiveText } from '../types/livetext';
import type { Article } from '../types/article';

interface SocialMetadata {
  title: string;
  description: string;
  image: string;
  url: string;
  type: 'website' | 'article';
  siteName: string;
  locale: string;
  publishedTime?: string;
  modifiedTime?: string;
  author?: string;
  section?: string;
  tags?: string[];
}

const SITE_NAME = 'Deschide News';
const DEFAULT_IMAGE = '/images/og-default.jpg';
const BASE_URL = process.env.NEXT_PUBLIC_BASE_URL || 'http://localhost:3005';

/**
 * Generate Open Graph metadata for LiveText
 */
export function generateLiveTextMetadata(liveText: LiveText, locale: string = 'ro'): SocialMetadata {
  const title = `${liveText.title} - ${SITE_NAME}`;
  const description = liveText.description || `Live coverage: ${liveText.title}`;
  const url = `${BASE_URL}/${locale}/live/${liveText.slug}`;

  // Use default image (LiveTextPost doesn't have images property)
  const image = DEFAULT_IMAGE;

  return {
    title: liveText.title,
    description,
    image: image.startsWith('http') ? image : `${BASE_URL}${image}`,
    url,
    type: 'article',
    siteName: SITE_NAME,
    locale: mapLocale(locale),
    publishedTime: liveText.startTime ? new Date(liveText.startTime).toISOString() : undefined,
    modifiedTime: liveText.updatedAt ? new Date(liveText.updatedAt).toISOString() : undefined,
    author: liveText.author?.username,
    section: liveText.category?.title,
  };
}

/**
 * Generate Open Graph metadata for Article
 */
export function generateArticleMetadata(article: Article, locale: string = 'ro'): SocialMetadata {
  const title = `${article.title} - ${SITE_NAME}`;
  const description = article.lead || article.content?.substring(0, 200) || '';
  const url = `${BASE_URL}/${locale}/article/${article.slug}`;

  // Get featured image or default
  let image = DEFAULT_IMAGE;
  if (article.articleImages && article.articleImages.length > 0) {
    const featuredImage = article.articleImages.find(ai => ai.isFeatured);
    const firstImage = article.articleImages[0];
    const selectedImage = featuredImage || firstImage;

    if (selectedImage && selectedImage.image) {
      const cdnUrl = process.env.NEXT_PUBLIC_CDN_URL || 'http://127.0.0.1:8082';
      // Check if image is an object with path property, otherwise it's a string URL
      if (typeof selectedImage.image === 'object' && 'path' in selectedImage.image) {
        image = `${cdnUrl}/uploads/${selectedImage.image.path}`;
      } else if (typeof selectedImage.image === 'string') {
        image = selectedImage.image;
      }
    }
  }

  return {
    title: article.title,
    description,
    image,
    url,
    type: 'article',
    siteName: SITE_NAME,
    locale: mapLocale(locale),
    publishedTime: article.publishedAt ? new Date(article.publishedAt).toISOString() : undefined,
    modifiedTime: article.updatedAt ? new Date(article.updatedAt).toISOString() : undefined,
    author: undefined, // authors are IRIs, not expanded objects
    section: typeof article.category === 'object' ? article.category.title : undefined,
    tags: undefined, // tags are not defined in Article type
  };
}

/**
 * Map locale to Open Graph locale format
 */
function mapLocale(locale: string): string {
  const localeMap: Record<string, string> = {
    'ro': 'ro_RO',
    'en': 'en_US',
    'ru': 'ru_RU',
  };
  return localeMap[locale] || 'en_US';
}

/**
 * Generate Open Graph meta tags (for Next.js metadata)
 */
export function generateOpenGraphTags(metadata: SocialMetadata) {
  return {
    title: metadata.title,
    description: metadata.description,
    url: metadata.url,
    siteName: metadata.siteName,
    locale: metadata.locale,
    type: metadata.type,
    images: [
      {
        url: metadata.image,
        width: 1200,
        height: 630,
        alt: metadata.title,
      },
    ],
    ...(metadata.publishedTime && { publishedTime: metadata.publishedTime }),
    ...(metadata.modifiedTime && { modifiedTime: metadata.modifiedTime }),
    ...(metadata.author && { authors: [metadata.author] }),
    ...(metadata.section && { section: metadata.section }),
    ...(metadata.tags && { tags: metadata.tags }),
  };
}

/**
 * Generate Twitter Card meta tags (for Next.js metadata)
 */
export function generateTwitterCardTags(metadata: SocialMetadata) {
  return {
    card: 'summary_large_image',
    title: metadata.title,
    description: metadata.description,
    images: [metadata.image],
    creator: '@deschide_news', // Replace with actual Twitter handle
    site: '@deschide_news',
  };
}

/**
 * Get sharing URL for social platforms
 */
export function getSharingUrl(platform: 'facebook' | 'twitter' | 'linkedin' | 'whatsapp' | 'telegram', url: string, title?: string, description?: string): string {
  const encodedUrl = encodeURIComponent(url);
  const encodedTitle = encodeURIComponent(title || '');
  const encodedDescription = encodeURIComponent(description || '');

  switch (platform) {
    case 'facebook':
      return `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`;

    case 'twitter':
      const twitterText = title ? `${encodedTitle} ${encodedUrl}` : encodedUrl;
      return `https://twitter.com/intent/tweet?text=${twitterText}`;

    case 'linkedin':
      return `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`;

    case 'whatsapp':
      const whatsappText = title ? `${title} ${url}` : url;
      return `https://wa.me/?text=${encodeURIComponent(whatsappText)}`;

    case 'telegram':
      return `https://t.me/share/url?url=${encodedUrl}&text=${encodedTitle}`;

    default:
      return url;
  }
}

/**
 * Copy URL to clipboard
 */
export async function copyToClipboard(url: string): Promise<boolean> {
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(url);
      return true;
    } else {
      // Fallback for older browsers
      const textArea = document.createElement('textarea');
      textArea.value = url;
      textArea.style.position = 'fixed';
      textArea.style.left = '-999999px';
      document.body.appendChild(textArea);
      textArea.focus();
      textArea.select();

      try {
        document.execCommand('copy');
        textArea.remove();
        return true;
      } catch (error) {
        console.error('Failed to copy:', error);
        textArea.remove();
        return false;
      }
    }
  } catch (error) {
    console.error('Failed to copy to clipboard:', error);
    return false;
  }
}
