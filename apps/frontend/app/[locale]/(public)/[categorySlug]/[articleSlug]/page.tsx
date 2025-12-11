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
import { buildImageUrl, getFeaturedImage, getThumbnailByProfile } from '@/lib/api/important-articles';
import { fetchRelatedArticles } from '@/lib/api/articles';
import { lookupArticle } from '@/lib/api/slug-lookup';
import type { Locale } from '@/lib/types';
import {
  ArticleLayout,
  ArticleHeader,
  ArticleBody,
  ArticleImage,
  ArticleMeta,
  ArticleSidebar,
} from '@/components/article';
import { generateArticleMetadata, generateArticleStructuredData } from '@/lib/seo';
import StructuredData from '@/components/seo/StructuredData';
import Breadcrumb, { buildArticleBreadcrumbs } from '@/components/navigation/Breadcrumb';

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
    const articleCategorySlug = article.category?.slug;
    if (articleCategorySlug !== categorySlug) {
      console.error(`Category mismatch: expected ${categorySlug}, got ${articleCategorySlug}`);
      return null;
    }

    return article;
  } catch (error) {
    console.error('Error fetching article:', error);
    return null;
  }
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
    // Fetch article data
    const article = await fetchArticleBySlug(
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
    return generateArticleMetadata(article, locale, imageUrl);
  } catch (error) {
    console.error('Error generating metadata:', error);
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

  // Fetch article from API with category validation
  const article = await fetchArticleBySlug(
    categorySlug, // category slug
    articleSlug, // article slug
    locale
  );

  if (!article) {
    notFound();
  }

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
    article.category.id,
    locale,
    6
  );

  // Build breadcrumbs
  const breadcrumbItems = buildArticleBreadcrumbs(article, locale);

  return (
    <>
      {/* Structured Data (JSON-LD) */}
      <StructuredData data={structuredData} />

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

        {/* Article Header */}
        <ArticleHeader article={article} locale={locale} />

        {/* Featured Image */}
        {imageToUse && (
          <ArticleImage
            image={{
              path: imageToUse.path,
              width: imageToUse.width,
              height: imageToUse.height,
              alt: featuredImage?.alt,
              title: featuredImage?.title,
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

        {/* Article Meta (author bio, social share) */}
        <ArticleMeta article={article} locale={locale} />
      </ArticleLayout>
    </>
  );
}
