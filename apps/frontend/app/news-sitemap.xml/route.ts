/**
 * News Sitemap (Google News Format)
 *
 * Generates news-sitemap.xml following Google News sitemap protocol
 * Only includes articles published within the last 48 hours
 *
 * Google News Sitemap Requirements:
 * - Only articles from last 2 days
 * - Uses <news:news> tag
 * - Includes publication name and language
 * - Includes publication date
 */

import { NextRequest, NextResponse } from 'next/server';
import { fetchRecentArticlesForNewsSitemap } from '@/lib/api/sitemap-data';
import { buildArticleUrl, generateLanguageAlternates, isRecentArticle } from '@/lib/seo/sitemap-utils';
import { SITEMAP_CONFIG, Locale } from '@/lib/seo/sitemap-config';

export async function GET(request: NextRequest) {
  try {
    const articles = await fetchRecentArticlesForNewsSitemap();

    // Filter to only recent articles (double-check server-side)
    const recentArticles = articles.filter((article) =>
      isRecentArticle(article.publishedAt)
    );

    const { news } = SITEMAP_CONFIG;

    // Generate XML
    const xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
${recentArticles
  .map((article) => {
    const availableLocales: readonly Locale[] = article.publishedLocales.length > 0
      ? article.publishedLocales.filter((l: Locale) => SITEMAP_CONFIG.locales.includes(l))
      : SITEMAP_CONFIG.locales;

    const alternatePaths: Partial<Record<Locale, string>> = {};
    for (const locale of availableLocales) {
      const t = article.translations?.[locale];
      if (t?.slug) {
        alternatePaths[locale] = `${t.categorySlug}/${t.slug}`;
      }
    }

    return availableLocales
      .map((locale) => {
        const translation = article.translations?.[locale];
        if (!translation?.slug) return '';

        const title = translation.title || translation.slug;
        const url = buildArticleUrl(locale, translation.categorySlug, translation.slug);
        const publishedAt = new Date(article.publishedAt).toISOString();
        const language = news.languageMap[locale];
        const alternates = generateLanguageAlternates(alternatePaths);
        const alternateLinks = Object.entries(alternates.languages)
          .map(
            ([lang, href]) =>
              `    <xhtml:link rel="alternate" hreflang="${escapeXml(lang)}" href="${escapeXml(href)}" />`
          )
          .join('\n');

        // Extract keywords from category (basic implementation)
        const keywords = translation.categorySlug.replace(/-/g, ', ');

        return `  <url>
    <loc>${escapeXml(url)}</loc>
${alternateLinks}
    <news:news>
      <news:publication>
        <news:name>${escapeXml(news.publicationName)}</news:name>
        <news:language>${language}</news:language>
      </news:publication>
      <news:publication_date>${publishedAt}</news:publication_date>
      <news:title>${escapeXml(title)}</news:title>
      <news:keywords>${escapeXml(keywords)}</news:keywords>
    </news:news>
  </url>`;
      })
      .filter((entry) => entry !== '')
      .join('\n');
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
    console.error('Error generating news sitemap:', error);

    // Return empty but valid sitemap on error
    const emptyXml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">
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
