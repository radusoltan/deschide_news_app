/**
 * Translation API Service
 *
 * Utilities for fetching translated slugs for articles and categories
 * across different locales to enable proper language switching
 */

import type { Locale } from '../types';
import { lookupArticle, lookupCategory } from './slug-lookup';

const AVAILABLE_LOCALES: Locale[] = ['ro', 'en', 'ru'];

export interface TranslatedSlugs {
  ro: string | null;
  en: string | null;
  ru: string | null;
}

export interface ArticleTranslations {
  articleSlugs: TranslatedSlugs;
  categorySlugs: TranslatedSlugs;
  articleId: number;
  categoryId: number;
}

export interface CategoryTranslations {
  categorySlugs: TranslatedSlugs;
  categoryId: number;
}

/**
 * Fetch article translations across all locales
 *
 * This function uses the backend `/api/articles/{id}/translations` endpoint
 * to get the article slug and category slug in all available locales.
 *
 * @param currentArticleSlug - Current article slug
 * @param currentCategorySlug - Current category slug
 * @param currentLocale - Current locale
 * @returns Article translations or null if not found
 */
export async function fetchArticleTranslations(
  currentArticleSlug: string,
  currentCategorySlug: string,
  currentLocale: Locale
): Promise<ArticleTranslations | null> {
  try {
    // First, fetch the article in the current locale to get its ID
    const currentArticle = await lookupArticle(currentArticleSlug, currentLocale);

    if (!currentArticle || !currentArticle.id) {
      console.error('Article not found in current locale:', currentArticleSlug, currentLocale);
      return null;
    }

    const articleId = currentArticle.id;
    const categoryId = currentArticle.category?.id;

    // Fetch translations from the backend endpoint
    const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';
    const response = await fetch(`${API_BASE_URL}/api/articles/${articleId}/translations`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
      next: {
        revalidate: 300, // Cache for 5 minutes
      },
    });

    if (!response.ok) {
      console.error('Failed to fetch article translations:', response.status, response.statusText);
      return null;
    }

    const data = await response.json();

    if (!data.success || !data.translations) {
      console.error('Invalid translation response format:', data);
      return null;
    }

    // Map the backend response to our format
    const articleSlugs: TranslatedSlugs = {
      ro: data.translations.ro?.article_slug || currentArticleSlug,
      en: data.translations.en?.article_slug || currentArticleSlug,
      ru: data.translations.ru?.article_slug || currentArticleSlug,
    };

    const categorySlugs: TranslatedSlugs = {
      ro: data.translations.ro?.category_slug || currentCategorySlug,
      en: data.translations.en?.category_slug || currentCategorySlug,
      ru: data.translations.ru?.category_slug || currentCategorySlug,
    };

    return {
      articleSlugs,
      categorySlugs,
      articleId,
      categoryId,
    };
  } catch (error) {
    console.error('Error fetching article translations:', error);
    return null;
  }
}

/**
 * Fetch category translations across all locales
 *
 * This function uses the backend `/api/categories/{id}/translations` endpoint
 * to get the category slug in all available locales.
 *
 * @param currentCategorySlug - Current category slug
 * @param currentLocale - Current locale
 * @returns Category translations or null if not found
 */
export async function fetchCategoryTranslations(
  currentCategorySlug: string,
  currentLocale: Locale
): Promise<CategoryTranslations | null> {
  try {
    // First, fetch the category in the current locale to get its ID
    const currentCategory = await lookupCategory(currentCategorySlug, currentLocale);

    if (!currentCategory || !currentCategory.id) {
      console.error('Category not found in current locale:', currentCategorySlug, currentLocale);
      return null;
    }

    const categoryId = currentCategory.id;

    // Fetch translations from the backend endpoint
    const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';
    const response = await fetch(`${API_BASE_URL}/api/categories/${categoryId}/translations`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
      next: {
        revalidate: 300, // Cache for 5 minutes
      },
    });

    if (!response.ok) {
      console.error('Failed to fetch category translations:', response.status, response.statusText);
      return null;
    }

    const data = await response.json();

    if (!data.success || !data.translations) {
      console.error('Invalid translation response format:', data);
      return null;
    }

    // Map the backend response to our format
    const categorySlugs: TranslatedSlugs = {
      ro: data.translations.ro?.category_slug || currentCategorySlug,
      en: data.translations.en?.category_slug || currentCategorySlug,
      ru: data.translations.ru?.category_slug || currentCategorySlug,
    };

    return {
      categorySlugs,
      categoryId,
    };
  } catch (error) {
    console.error('Error fetching category translations:', error);
    return null;
  }
}

/**
 * Build localized URL for article page
 *
 * @param locale - Target locale
 * @param categorySlug - Category slug in target locale
 * @param articleSlug - Article slug in target locale
 * @returns Localized URL
 */
export function buildArticleUrl(
  locale: Locale,
  categorySlug: string,
  articleSlug: string
): string {
  if (locale === 'ro') {
    return `/${categorySlug}/${articleSlug}`;
  }
  return `/${locale}/${categorySlug}/${articleSlug}`;
}

/**
 * Build localized URL for category page
 *
 * @param locale - Target locale
 * @param categorySlug - Category slug in target locale
 * @returns Localized URL
 */
export function buildCategoryUrl(locale: Locale, categorySlug: string): string {
  if (locale === 'ro') {
    return `/${categorySlug}`;
  }
  return `/${locale}/${categorySlug}`;
}

/**
 * Build localized URL for author page
 *
 * @param locale - Target locale
 * @param authorSlug - Author slug (not translated)
 * @returns Localized URL
 */
export function buildAuthorUrl(locale: Locale, authorSlug: string): string {
  if (locale === 'ro') {
    return `/author/${authorSlug}`;
  }
  return `/${locale}/author/${authorSlug}`;
}

/**
 * Build localized URL for static pages
 *
 * @param locale - Target locale
 * @param pagePath - Page path (e.g., 'all', 'search', 'trending', 'about', 'contact')
 * @returns Localized URL
 */
export function buildStaticPageUrl(locale: Locale, pagePath: string): string {
  if (locale === 'ro') {
    return `/${pagePath}`;
  }
  return `/${locale}/${pagePath}`;
}
