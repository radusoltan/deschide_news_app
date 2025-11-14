/**
 * Image Sitemap
 *
 * Generates image-sitemap.xml with all article images
 * Helps Google index article images for Image Search
 *
 * Image Sitemap Format:
 * - Parent URL (article page)
 * - Image location
 * - Image caption, title, license info
 */

import { NextRequest, NextResponse } from 'next/server';
import { fetchAllArticlesForSitemap } from '@/lib/api/sitemap-data';
import { buildArticleUrl, getCdnImageUrl } from '@/lib/seo/sitemap-utils';
import { SITEMAP_CONFIG } from '@/lib/seo/sitemap-config';

export async function GET(request: NextRequest) {
  try {
    const articles = await fetchAllArticlesForSitemap();

    const { images: imageConfig } = SITEMAP_CONFIG;

    // Generate XML
    const xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
${articles
  .filter((article) => article.articleImages && article.articleImages.length > 0)
  .map((article) => {
    // Use default locale (ro) for image sitemap to avoid duplicates
    const locale = SITEMAP_CONFIG.defaultLocale;
    const translation = article.translations?.[locale];
    const categorySlug = translation?.categorySlug || article.category.slug;
    const articleSlug = translation?.slug || article.slug;
    const articleTitle = translation?.title || article.slug;

    const url = buildArticleUrl(locale, categorySlug, articleSlug);

    // Get images (featured + inline, up to max)
    const images = article.articleImages || [];
    const limitedImages = imageConfig.maxImagesPerArticle
      ? images.slice(0, imageConfig.maxImagesPerArticle)
      : images;

    return `  <url>
    <loc>${escapeXml(url)}</loc>
${limitedImages
  .map((articleImage) => {
    const image = articleImage.image;
    const imageUrl = getCdnImageUrl(image.path);
    const imageTitle = image.title || image.alt || articleTitle;
    const imageCaption = image.caption || image.alt || articleTitle;

    return `    <image:image>
      <image:loc>${escapeXml(imageUrl)}</image:loc>
      <image:title>${escapeXml(imageTitle)}</image:title>
      <image:caption>${escapeXml(imageCaption)}</image:caption>
    </image:image>`;
  })
  .join('\n')}
  </url>`;
  })
  .join('\n')}
</urlset>`;

    return new NextResponse(xml, {
      status: 200,
      headers: {
        'Content-Type': 'application/xml',
        'Cache-Control': 'public, max-age=3600, s-maxage=3600', // Cache for 1 hour
      },
    });
  } catch (error) {
    console.error('Error generating image sitemap:', error);

    // Return empty but valid sitemap on error
    const emptyXml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
</urlset>`;

    return new NextResponse(emptyXml, {
      status: 200,
      headers: {
        'Content-Type': 'application/xml',
        'Cache-Control': 'public, max-age=300', // Cache for 5 minutes on error
      },
    });
  }
}

/**
 * Escape XML special characters
 */
function escapeXml(unsafe: string): string {
  return unsafe
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;');
}

// Revalidate every hour
export const revalidate = 3600;
