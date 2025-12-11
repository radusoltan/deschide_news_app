/**
 * Archive Sitemap
 *
 * Generates sitemap-archive.xml for all archived articles
 * Includes articles from the archive with lower priority and change frequency
 *
 * Archive Sitemap Specifications:
 * - Includes all archived articles across all locales
 * - Lower priority (0.3) than active content
 * - Yearly change frequency (archives rarely change)
 * - Uses article's updatedAt or archivedAt as lastmod
 * - Handles pagination to fetch all archived articles
 */

import { NextRequest, NextResponse } from 'next/server';
import { fetchArchivedArticlesForSitemap } from '@/lib/api/sitemap-data';
import { buildArticleUrl, generateLanguageAlternates } from '@/lib/seo/sitemap-utils';
import { SITEMAP_CONFIG, Locale } from '@/lib/seo/sitemap-config';

/**
 * Archive-specific configuration
 */
const ARCHIVE_CONFIG = {
  priority: 0.3, // Lower than main content (0.7-0.8)
  changeFrequency: 'yearly' as const, // Archives rarely change
  cacheMaxAge: 86400, // 24 hours
  cacheSMaxAge: 604800, // 1 week for CDN
};

export async function GET(request: NextRequest) {
  try {
    // Fetch all archived articles with pagination handling
    const archivedArticles = await fetchArchivedArticlesForSitemap();

    if (archivedArticles.length === 0) {
      console.log('No archived articles found for sitemap');
    } else {
      console.log(`Generating archive sitemap with ${archivedArticles.length} articles`);
    }

    // Generate XML sitemap
    const xml = generateArchiveSitemapXml(archivedArticles);

    return new NextResponse(xml, {
      status: 200,
      headers: {
        'Content-Type': 'application/xml; charset=utf-8',
        'Cache-Control': `public, max-age=${ARCHIVE_CONFIG.cacheMaxAge}, s-maxage=${ARCHIVE_CONFIG.cacheSMaxAge}`,
      },
    });
  } catch (error) {
    console.error('Error generating archive sitemap:', error);

    // Return empty but valid sitemap on error
    const emptyXml = generateEmptySitemap();

    return new NextResponse(emptyXml, {
      status: 200,
      headers: {
        'Content-Type': 'application/xml; charset=utf-8',
        'Cache-Control': 'public, max-age=300', // Cache for 5 minutes on error
      },
    });
  }
}

/**
 * Generate the complete archive sitemap XML
 */
function generateArchiveSitemapXml(articles: any[]): string {
  const urlEntries = articles
    .map((article) => generateArticleUrlEntries(article))
    .join('\n');

  return `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
${urlEntries}
</urlset>`;
}

/**
 * Generate URL entries for a single article across all locales
 */
function generateArticleUrlEntries(article: any): string {
  return SITEMAP_CONFIG.locales
    .map((locale) => {
      const translation = article.translations?.[locale];
      const categorySlug = translation?.categorySlug || article.category.slug;
      const articleSlug = translation?.slug || article.slug;

      // Build the primary URL for this locale
      const url = buildArticleUrl(locale, categorySlug, articleSlug);

      // Use archivedAt if available, otherwise updatedAt
      const lastModDate = article.archivedAt || article.updatedAt;
      const lastmod = formatDateForSitemap(lastModDate);

      // Generate alternate language links (hreflang)
      const alternates = generateLanguageAlternates({
        ro: `${article.translations.ro.categorySlug}/${article.translations.ro.slug}`,
        en: `${article.translations.en.categorySlug}/${article.translations.en.slug}`,
        ru: `${article.translations.ru.categorySlug}/${article.translations.ru.slug}`,
      });

      // Generate xhtml:link tags for each language alternate
      const alternateLinks = Object.entries(alternates.languages)
        .map(
          ([lang, href]) =>
            `    <xhtml:link rel="alternate" hreflang="${escapeXml(lang)}" href="${escapeXml(href)}" />`
        )
        .join('\n');

      return `  <url>
    <loc>${escapeXml(url)}</loc>
    <lastmod>${lastmod}</lastmod>
    <changefreq>${ARCHIVE_CONFIG.changeFrequency}</changefreq>
    <priority>${ARCHIVE_CONFIG.priority}</priority>
${alternateLinks}
  </url>`;
    })
    .join('\n');
}

/**
 * Generate empty sitemap (used for error cases)
 */
function generateEmptySitemap(): string {
  return `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
</urlset>`;
}

/**
 * Format date for sitemap (ISO 8601 format)
 */
function formatDateForSitemap(dateString: string | Date | null | undefined): string {
  if (!dateString) {
    return new Date().toISOString();
  }

  try {
    const date = typeof dateString === 'string' ? new Date(dateString) : dateString;
    return date.toISOString();
  } catch {
    return new Date().toISOString();
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

/**
 * Revalidate every 24 hours (archives don't change frequently)
 */
export const revalidate = 86400;
