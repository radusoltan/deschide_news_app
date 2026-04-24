/**
 * Archive Sitemap
 *
 * Sitemap for archived articles (older than 1 year)
 * Separate from main sitemap to keep main sitemap focused on fresh content
 */

import { MetadataRoute } from 'next';
import { fetchArchivedArticlesForSitemap } from '@/lib/api/sitemap-data';
import { buildArticleUrl, parseDate, generateLanguageAlternates } from '@/lib/seo/sitemap-utils';
import { SITEMAP_CONFIG, type Locale } from '@/lib/seo/sitemap-config';

export default async function archiveSitemap(): Promise<MetadataRoute.Sitemap> {
  const { locales } = SITEMAP_CONFIG;
  const sitemapEntries: MetadataRoute.Sitemap = [];

  try {
    // Fetch articles older than 1 year with archived status
    const archivedArticles = await fetchArchivedArticlesForSitemap();

    for (const article of archivedArticles) {
      const availableLocales = article.publishedLocales.length > 0
        ? article.publishedLocales.filter((l) => locales.includes(l))
        : locales;

      const alternatePaths: Partial<Record<Locale, string>> = {};
      for (const locale of availableLocales) {
        const t = article.translations[locale];
        if (t?.slug) {
          alternatePaths[locale] = `${t.categorySlug}/${t.slug}`;
        }
      }

      for (const locale of availableLocales) {
        const translation = article.translations[locale];
        if (!translation?.slug) {
          continue;
        }

        sitemapEntries.push({
          url: buildArticleUrl(locale, translation.categorySlug, translation.slug),
          lastModified: parseDate(article.archivedAt || article.updatedAt),
          changeFrequency: 'yearly', // Archived content rarely changes
          priority: 0.3, // Lower priority for archived content
          alternates: generateLanguageAlternates(alternatePaths),
        });
      }
    }

    console.log(`Generated archive sitemap with ${sitemapEntries.length} entries`);
  } catch (error) {
    console.error('Error generating archive sitemap:', error);
  }

  return sitemapEntries;
}

// Cache for 1 day (archived content doesn't change frequently)
export const revalidate = 86400;
