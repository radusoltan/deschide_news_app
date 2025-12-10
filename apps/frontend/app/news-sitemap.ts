/**
 * Google News Sitemap
 *
 * Special sitemap for Google News containing articles from the last 48 hours
 * Requirements:
 * - Only articles published within last 48 hours
 * - Include publication date, title, keywords
 * - Include multiple image references (16:9, 4:3, 1:1 aspect ratios)
 * - Update in real-time when articles are published
 */

import { MetadataRoute } from 'next';
import { fetchRecentArticlesForNewsSitemap } from '@/lib/api/sitemap-data';
import { buildArticleUrl, parseDate } from '@/lib/seo/sitemap-utils';
import { SITEMAP_CONFIG, Locale } from '@/lib/seo/sitemap-config';

export default async function newsSitemap(): Promise<MetadataRoute.Sitemap> {
  const CDN_URL = process.env.NEXT_PUBLIC_CDN_URL || 'http://localhost:8082';

  const entries: MetadataRoute.Sitemap = [];

  try {
    // Fetch articles from last 48 hours for all locales
    const recentArticles = await fetchRecentArticlesForNewsSitemap();

    for (const article of recentArticles) {
      // Generate entry for each locale
      for (const locale of SITEMAP_CONFIG.locales) {
        const translation = article.translations?.[locale];
        if (!translation) continue;

        const categorySlug = translation.categorySlug || article.category.slug;
        const articleSlug = translation.slug || article.slug;

        // Build image URLs from articleImages
        const imageUrls = article.articleImages?.map(
          (ai: any) => `${CDN_URL}/uploads/${ai.image.path}`
        ) || [];

        entries.push({
          url: buildArticleUrl(locale, categorySlug, articleSlug),
          lastModified: parseDate(article.updatedAt || article.publishedAt),
          changeFrequency: 'hourly', // News articles change frequently
          priority: 1.0, // Highest priority for fresh news
          images: imageUrls.length > 0 ? imageUrls : undefined,
        });
      }
    }

    console.log(`Generated news sitemap with ${entries.length} entries (last 48h)`);
  } catch (error) {
    console.error('Error generating news sitemap:', error);
  }

  return entries;
}

// Revalidate every 5 minutes (news sitemap must be fresh)
export const revalidate = 300;
