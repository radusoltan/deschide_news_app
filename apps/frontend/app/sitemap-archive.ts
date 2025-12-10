/**
 * Archive Sitemap
 *
 * Sitemap for archived articles (older than 1 year)
 * Separate from main sitemap to keep main sitemap focused on fresh content
 */

import { MetadataRoute } from 'next';
import { fetchArchivedArticlesForSitemap } from '@/lib/api/sitemap-data';
import { buildArticleUrl, parseDate, generateLanguageAlternates } from '@/lib/seo/sitemap-utils';
import { SITEMAP_CONFIG } from '@/lib/seo/sitemap-config';

export default async function archiveSitemap(): Promise<MetadataRoute.Sitemap> {
  const { locales, changeFrequency, priority } = SITEMAP_CONFIG;
  const sitemapEntries: MetadataRoute.Sitemap = [];

  try {
    // Fetch articles older than 1 year with archived status
    const archivedArticles = await fetchArchivedArticlesForSitemap();

    for (const article of archivedArticles) {
      // For each locale, create sitemap entry
      for (const locale of locales) {
        const translation = article.translations?.[locale];
        const categorySlug = translation?.categorySlug || article.category.slug;
        const articleSlug = translation?.slug || article.slug;

        sitemapEntries.push({
          url: buildArticleUrl(locale, categorySlug, articleSlug),
          lastModified: parseDate(article.archivedAt || article.updatedAt),
          changeFrequency: 'yearly', // Archived content rarely changes
          priority: 0.3, // Lower priority for archived content
          alternates: generateLanguageAlternates({
            ro: `${article.translations.ro.categorySlug}/${article.translations.ro.slug}`,
            en: `${article.translations.en.categorySlug}/${article.translations.en.slug}`,
            ru: `${article.translations.ru.categorySlug}/${article.translations.ru.slug}`,
          }),
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
