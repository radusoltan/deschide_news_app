/**
 * Main Sitemap
 *
 * Generates sitemap.xml with all published content across all locales
 * Includes: homepage, articles, categories, authors, and static pages
 */

import { MetadataRoute } from 'next';
import {
  fetchAllArticlesForSitemap,
  fetchAllCategoriesForSitemap,
  fetchAllAuthorsForSitemap,
} from '@/lib/api/sitemap-data';
import {
  buildLocalizedUrl,
  buildArticleUrl,
  buildCategoryUrl,
  buildAuthorUrl,
  buildArchiveUrl,
  generateLanguageAlternates,
  parseDate,
} from '@/lib/seo/sitemap-utils';
import { SITEMAP_CONFIG, Locale } from '@/lib/seo/sitemap-config';

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const { locales, changeFrequency, priority } = SITEMAP_CONFIG;

  const sitemapEntries: MetadataRoute.Sitemap = [];

  // ===== HOMEPAGE =====
  for (const locale of locales) {
    sitemapEntries.push({
      url: buildLocalizedUrl(locale, ''),
      lastModified: new Date(),
      changeFrequency: changeFrequency.homepage,
      priority: priority.homepage,
      alternates: generateLanguageAlternates({
        ro: '',
        en: '',
        ru: '',
      }),
    });
  }

  // ===== STATIC PAGES =====
  const staticPages = [
    { path: 'all', priority: priority.static },
    { path: 'trending', priority: priority.static },
    { path: 'archive', priority: priority.static },
    { path: 'about', priority: priority.static },
    { path: 'contact', priority: priority.static },
  ];

  for (const page of staticPages) {
    for (const locale of locales) {
      sitemapEntries.push({
        url: buildLocalizedUrl(locale, page.path),
        lastModified: new Date(),
        changeFrequency: changeFrequency.static,
        priority: page.priority,
        alternates: generateLanguageAlternates({
          ro: page.path,
          en: page.path,
          ru: page.path,
        }),
      });
    }
  }

  // ===== CATEGORIES =====
  try {
    const categories = await fetchAllCategoriesForSitemap();

    for (const category of categories) {
      // For each locale, create a sitemap entry
      for (const locale of locales) {
        const categorySlug = category.translations?.[locale]?.slug || category.slug;

        sitemapEntries.push({
          url: buildCategoryUrl(locale, categorySlug),
          lastModified: new Date(),
          changeFrequency: changeFrequency.category,
          priority: priority.category,
          alternates: generateLanguageAlternates({
            ro: category.translations?.ro?.slug || category.slug,
            en: category.translations?.en?.slug || category.slug,
            ru: category.translations?.ru?.slug || category.slug,
          }),
        });
      }
    }
  } catch (error) {
    console.error('Error adding categories to sitemap:', error);
  }

  // ===== AUTHORS =====
  try {
    const authors = await fetchAllAuthorsForSitemap();

    for (const author of authors) {
      // Authors are not translated (same slug across locales)
      for (const locale of locales) {
        sitemapEntries.push({
          url: buildAuthorUrl(locale, author.slug),
          lastModified: new Date(),
          changeFrequency: changeFrequency.author,
          priority: priority.author,
          alternates: generateLanguageAlternates({
            ro: `author/${author.slug}`,
            en: `author/${author.slug}`,
            ru: `author/${author.slug}`,
          }),
        });
      }
    }
  } catch (error) {
    console.error('Error adding authors to sitemap:', error);
  }

  // ===== ARTICLES =====
  try {
    const articles = await fetchAllArticlesForSitemap();

    for (const article of articles) {
      // For each locale, create a sitemap entry with translations
      for (const locale of locales) {
        const translation = article.translations?.[locale];
        const categorySlug = translation?.categorySlug || article.category.slug;
        const articleSlug = translation?.slug || article.slug;

        // Determine priority based on featured status
        const articlePriority = article.isFeatured
          ? priority.featuredArticle
          : priority.article;

        sitemapEntries.push({
          url: buildArticleUrl(locale, categorySlug, articleSlug),
          lastModified: parseDate(article.updatedAt),
          changeFrequency: changeFrequency.article,
          priority: articlePriority,
          alternates: generateLanguageAlternates({
            ro: `${article.translations.ro.categorySlug}/${article.translations.ro.slug}`,
            en: `${article.translations.en.categorySlug}/${article.translations.en.slug}`,
            ru: `${article.translations.ru.categorySlug}/${article.translations.ru.slug}`,
          }),
        });
      }
    }
  } catch (error) {
    console.error('Error adding articles to sitemap:', error);
  }

  // ===== ARCHIVE PAGES =====
  // Add current year and previous year archive pages
  const currentYear = new Date().getFullYear();
  const years = [currentYear, currentYear - 1];

  for (const year of years) {
    for (const locale of locales) {
      // Year archive
      sitemapEntries.push({
        url: buildArchiveUrl(locale, year),
        lastModified: new Date(),
        changeFrequency: changeFrequency.archive,
        priority: priority.archive,
        alternates: generateLanguageAlternates({
          ro: `archive/${year}`,
          en: `archive/${year}`,
          ru: `archive/${year}`,
        }),
      });

      // Month archives for current year
      if (year === currentYear) {
        const currentMonth = new Date().getMonth() + 1;
        for (let month = 1; month <= currentMonth; month++) {
          sitemapEntries.push({
            url: buildArchiveUrl(locale, year, month),
            lastModified: new Date(),
            changeFrequency: changeFrequency.archive,
            priority: priority.archive,
            alternates: generateLanguageAlternates({
              ro: `archive/${year}/${month}`,
              en: `archive/${year}/${month}`,
              ru: `archive/${year}/${month}`,
            }),
          });
        }
      }
    }
  }

  console.log(`Generated sitemap with ${sitemapEntries.length} entries`);

  return sitemapEntries;
}

// Cache for 1 hour (sitemaps don't need to be updated frequently)
export const revalidate = 3600;
