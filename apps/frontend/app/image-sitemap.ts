/**
 * Image Sitemap
 *
 * Dedicated sitemap for images to improve image SEO
 * Includes all images from published articles with proper metadata
 */

import { MetadataRoute } from 'next';
import { fetchAllArticlesForSitemap } from '@/lib/api/sitemap-data';
import { buildArticleUrl, parseDate } from '@/lib/seo/sitemap-utils';
import { SITEMAP_CONFIG } from '@/lib/seo/sitemap-config';
import type { ArticleImage } from '@/lib/types/image';

export default async function imageSitemap(): Promise<MetadataRoute.Sitemap> {
  const CDN_URL = process.env.NEXT_PUBLIC_CDN_URL ?? '';
  const { locales } = SITEMAP_CONFIG;

  const sitemapEntries: MetadataRoute.Sitemap = [];

  try {
    const articles = await fetchAllArticlesForSitemap();

    for (const article of articles) {
      // Only include articles with images
      if (!article.articleImages || article.articleImages.length === 0) {
        continue;
      }

      // For each locale, create an entry with all images
      for (const locale of locales) {
        const translation = article.translations?.[locale];
        const categorySlug = translation?.categorySlug || article.category.slug;
        const articleSlug = translation?.slug || article.slug;

        // Next.js 16 expects images as string[] (URLs only)
        const images = article.articleImages.map((articleImage: ArticleImage) =>
          `${CDN_URL}/uploads/${articleImage.image.path}`
        );

        sitemapEntries.push({
          url: buildArticleUrl(locale, categorySlug, articleSlug),
          lastModified: parseDate(article.updatedAt),
          images,
        });
      }
    }

    console.log(`Generated image sitemap with ${sitemapEntries.length} entries`);
  } catch (error) {
    console.error('Error generating image sitemap:', error);
  }

  return sitemapEntries;
}

// Cache for 1 hour (images don't change frequently)
export const revalidate = 3600;
