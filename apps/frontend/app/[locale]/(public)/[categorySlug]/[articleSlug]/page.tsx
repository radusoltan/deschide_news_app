/**
 * Article Page
 * Route: /{locale}/{category-slug}/{article-slug}
 * Example: /en/economy/article-title
 *
 * Features:
 * - Slug lookup with redirect detection
 * - Category slug validation
 * - Dynamic metadata generation
 * - SEO optimization
 */

import { notFound } from 'next/navigation';
import type { Metadata } from 'next';
import * as Sentry from '@sentry/nextjs';
import { buildImageUrl, getFeaturedImage, getThumbnailByProfile } from '@/lib/api/important-articles';
import { fetchRelatedArticles } from '@/lib/api/articles';
import { lookupArticle } from '@/lib/api/slug-lookup';
import type { Locale } from '@/lib/types';
import type { Article } from '@/lib/types/article';
import {
  ArticleLayout,
  ArticleHeader,
  ArticleBody,
  ArticleImage,
  ArticleMeta,
  ArticleSidebar,
} from '@/components/article';
import ArticleDisclaimer from '@/components/article/ArticleDisclaimer';
import DRRMBanner from '@/components/banners/DRRMBanner';
import { generateArticleMetadata, generateArticleStructuredData } from '@/lib/seo';
import { getFallbackContent, hasPendingTranslation, LocaleFallbackNotice } from '@/lib/i18n/locale-fallback';
import StructuredData from '@/components/seo/StructuredData';
import Breadcrumb, { buildArticleBreadcrumbs } from '@/components/navigation/Breadcrumb';
import LocaleContextSetter from '@/app/components/LocaleContextSetter';

// Valid locales for the application
const VALID_LOCALES = ['ro', 'en', 'ru'] as const;

/**
 * Check if a locale string is valid
 */
function isValidLocale(locale: string): locale is Locale {
  return VALID_LOCALES.includes(locale as Locale);
}

export const dynamic = 'force-dynamic';
export const revalidate = 120;

interface ArticlePageProps {
  params: Promise<{
    locale: string;
    categorySlug: string;
    articleSlug: string;
  }>;
  searchParams: Promise<{ [key: string]: string | string[] | undefined }>;
}

/**
 * Validate if the request is for a legitimate article route
 */
function isValidArticleRequest(categorySlug: string, articleSlug: string): boolean {
  // Reject system paths that shouldn't be matched by article routes
  const systemPaths = ['.well-known', '_next', 'api', 'static'];
  if (systemPaths.some(path => categorySlug.startsWith(path) || articleSlug.startsWith(path))) {
    return false;
  }

  // Reject paths with file extensions
  if (articleSlug.includes('.') && /\.(json|xml|txt|js|css|jpg|jpeg|png|gif|svg|ico|woff|woff2|ttf|eot)$/i.test(articleSlug)) {
    return false;
  }

  return true;
}

/**
 * Fetch article by slug with validation
 * Uses the new /api/articles/by-slug/{slug} endpoint from Sprint 1
 */
async function fetchArticleBySlug(
  categorySlug: string,
  articleSlug: string,
  locale: Locale
) {
  try {
    // Validate request before making API call
    if (!isValidArticleRequest(categorySlug, articleSlug)) {
      console.error(`Invalid article request: ${categorySlug}/${articleSlug}`);
      return null;
    }

    // Use the new slug lookup endpoint
    const article = await lookupArticle(articleSlug, locale);

    if (!article) {
      console.error(`No article found with slug: ${articleSlug}`);
      return null;
    }

    // Verify that the category slug in URL matches article's actual category
    // This prevents wrong URLs like /wrong-category/article-slug
    const articleCategorySlug = typeof article.category === 'object' ? article.category?.slug : undefined;
    if (articleCategorySlug !== categorySlug) {
      console.error(`Category mismatch: expected ${categorySlug}, got ${articleCategorySlug}`);
      return null;
    }

    return article;
  } catch (error) {
    // T60.8.2 hardening: this catch used to silently swallow exceptions
    // (parse errors, type-coercion crashes, await-chain failures), which
    // produced unexplained 404s in production with no breadcrumb beyond
    // a `console.error` that nobody saw. Sentry now records the failure
    // with full context. The `return null` is preserved so the consumer
    // still triggers `notFound()` — but ops can now ask "why".
    Sentry.captureException(error, {
      tags: {
        component: 'article-page',
        operation: 'fetchArticleBySlug',
      },
      extra: {
        categorySlug,
        articleSlug,
        locale,
      },
    });
    console.error('[article-page:fetchArticleBySlug] failed', {
      categorySlug,
      articleSlug,
      locale,
      error,
    });
    return null;
  }
}

/**
 * Fetch article with locale fallback.
 * Requested locale enforces full categorySlug match (URL integrity).
 * Fallback (RO) bypasses categorySlug match since the article's own category
 * is canonical when we render RO content at the requested-locale URL.
 */
async function fetchArticleWithFallback(
  categorySlug: string,
  articleSlug: string,
  requestedLocale: Locale
) {
  return getFallbackContent<Article>(requestedLocale, async (candidateLocale) => {
    if (candidateLocale === requestedLocale) {
      return await fetchArticleBySlug(categorySlug, articleSlug, candidateLocale);
    }
    // Fallback path: skip categorySlug match, rely on article's own category
    if (!isValidArticleRequest(categorySlug, articleSlug)) {
      return null;
    }
    return await lookupArticle(articleSlug, candidateLocale);
  }, { isTranslationPending: hasPendingTranslation });
}


/**
 * Generate dynamic metadata for SEO
 * Uses centralized SEO utilities for consistent metadata generation
 */
export async function generateMetadata({
  params,
}: ArticlePageProps): Promise<Metadata> {
  const { locale, categorySlug, articleSlug } = await params;

  // Validate locale first to prevent Intl API errors
  if (!isValidLocale(locale)) {
    return {
      title: 'Invalid Locale',
      description: 'The requested locale is not supported.',
    };
  }

  try {
    // Fetch article with language fallback — enables correct metadata even
    // when the requested locale has no translation (RO content served).
    const { content: article, effectiveLocale } = await fetchArticleWithFallback(
      categorySlug,
      articleSlug,
      locale
    );

    if (!article) {
      return {
        title: 'Article Not Found',
        description: 'The requested article could not be found.',
      };
    }

    // Get featured image URL for Open Graph
    const featuredImage = getFeaturedImage(article.articleImages || []);
    const thumbnail = featuredImage
      ? getThumbnailByProfile(featuredImage, 'article_main')
      : null;
    const imageToUse = thumbnail || featuredImage;
    const imageUrl = imageToUse ? buildImageUrl(imageToUse.path) : undefined;

    // Generate comprehensive metadata using SEO utilities
    return generateArticleMetadata(article, effectiveLocale, imageUrl);
  } catch (error) {
    // T60.8.2 hardening: a metadata-generation crash used to leave only a
    // generic console.error. Now the failure surfaces in Sentry with the
    // route params so SEO regressions are observable.
    Sentry.captureException(error, {
      tags: {
        component: 'article-page',
        operation: 'generateMetadata',
      },
      extra: {
        locale,
        categorySlug,
        articleSlug,
      },
    });
    console.error('[article-page:generateMetadata] failed', {
      locale,
      categorySlug,
      articleSlug,
      error,
    });
    return {
      title: 'Article | Deschide News',
      description: 'Read the latest news and articles.',
    };
  }
}

export default async function ArticlePage({ params }: ArticlePageProps) {
  const { locale, categorySlug, articleSlug } = await params;

  // Validate locale to prevent Intl API errors
  if (!isValidLocale(locale)) {
    notFound();
  }

  // Fetch article with in-place locale fallback (renders RO content at
  // requested-locale URL with a notice when translation is missing).
  const articleFallback = await fetchArticleWithFallback(
    categorySlug,
    articleSlug,
    locale
  );

  const article = articleFallback.content;
  if (!article) {
    notFound();
  }

  const isLangFallback = articleFallback.isFallback;
  const effectiveLocale = articleFallback.effectiveLocale;

  // Get featured image
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage
    ? getThumbnailByProfile(featuredImage, 'article_main')
    : null;
  const imageToUse = thumbnail || featuredImage;

  // Get image URL for structured data
  const imageUrl = imageToUse ? buildImageUrl(imageToUse.path) : undefined;

  // Generate structured data (JSON-LD) for SEO
  const structuredData = generateArticleStructuredData(
    article,
    locale,
    imageUrl
  );

  // Fetch related articles by category
  const relatedArticles = await fetchRelatedArticles(
    article.id,
    typeof article.category === 'object' ? article.category.id : 0,
    locale,
    6
  );

  // Build breadcrumbs
  const breadcrumbItems = buildArticleBreadcrumbs(article, locale);

  const articleCategory =
    typeof article.category === 'object' ? article.category : null;

  return (
    <>
      {/* Structured Data (JSON-LD) */}
      <StructuredData data={structuredData} />

      {/* Feeds per-article metadata into the shared Header's LanguageSwitcher */}
      <LocaleContextSetter
        context="article"
        publishedLocales={article.publishedLocales}
        translatedSlugs={article.translatedSlugs}
        categoryTranslatedSlugs={articleCategory?.translatedSlugs}
      />

      <ArticleLayout
        sidebar={
          <ArticleSidebar
            relatedArticles={relatedArticles}
            // popularArticles={popularArticles}
            locale={locale}
          />
        }
      >
        {/* Breadcrumb Navigation */}
        <Breadcrumb items={breadcrumbItems} locale={locale} className="mb-6" />

        {/* Language fallback banner */}
        {isLangFallback && (
          <LocaleFallbackNotice
            requestedLocale={locale}
            effectiveLocale={effectiveLocale}
            translationPending={articleFallback.translationPending}
          />
        )}

        {/* Article Header */}
        <ArticleHeader article={article} locale={locale} />

        {/* Featured Image */}
        {imageToUse && (
          <ArticleImage
            image={{
              path: imageToUse.path ?? '',
              width: imageToUse.width ?? 0,
              height: imageToUse.height ?? 0,
              alt: featuredImage?.alt ?? '',
            }}
            priority
            enableLightbox
          />
        )}

        {/* Article Body */}
        {article.content && (
          <ArticleBody
            content={article.content}
            enableTableOfContents={article.content.length > 3000}
          />
        )}

        {/* Copyright disclaimer */}
        <ArticleDisclaimer locale={locale} />

        {/* Article Meta (author bio, social share) */}
        <ArticleMeta article={article} locale={locale} />

        {/* Partnership Banner */}
        <div className="mt-8">
          <DRRMBanner />
        </div>
      </ArticleLayout>
    </>
  );
}
