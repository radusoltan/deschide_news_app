/**
 * CategorySection — TailNews-inspired category layouts
 *
 * 4 layout variants that alternate on homepage for visual rhythm:
 *   A  "grid-3col"      — 3-column vertical card grid (6 articles)
 *   B  "compact-list"   — compact thumbnail+title rows in 3-col grid (6 articles)
 *   C  "grid-4col"      — 4-column vertical card grid (8 articles)
 *   D  "featured-grid"  — 1 featured + 3 stacked + 4 small (up to 8 articles)
 */

import { Suspense } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { fetchArticlesByCategory } from '@/lib/api/articles';
import {
  getFeaturedImage,
  getThumbnailByProfile,
  buildImageUrl,
} from '@/lib/api/important-articles';
import { buildArticleUrl, buildCategoryUrl } from '@/lib/utils/url-builder';
import {
  getSectionColor,
  getCategorySlugFromArticle,
  getCategoryTitle,
  getFirstSentence,
  formatRelativeTime,
} from '@/components/cards/utils';
import { stripHtml } from '@/lib/utils/strip-html';
import type { Article, Category } from '@/lib/types/article';
import type { Locale } from '@/lib/types';

/* ================================================================== */
/*  Types                                                              */
/* ================================================================== */

export type CategorySectionLayout =
  | 'grid-3col'
  | 'compact-list'
  | 'grid-4col'
  | 'featured-grid';

export interface CategorySectionProps {
  category: Category;
  locale: string;
  layout: CategorySectionLayout;
}

/* ================================================================== */
/*  Localized labels                                                   */
/* ================================================================== */

const labels = {
  ro: { viewAll: 'Vezi toate' },
  en: { viewAll: 'View all' },
  ru: { viewAll: 'Смотреть все' },
} as const;

/* ================================================================== */
/*  Shared sub-components                                              */
/* ================================================================== */

/** Section header: title + line + "View all →" */
function SectionHeader({
  title,
  href,
  sectionColor,
  viewAllLabel,
}: {
  title: string;
  href: string;
  sectionColor: string;
  viewAllLabel: string;
}) {
  return (
    <div className="flex items-center gap-4 mb-6">
      <h2
        className="font-sans font-bold text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] whitespace-nowrap border-b-2 pb-1"
        style={{ fontSize: 'var(--font-size-2xl)', borderBottomColor: sectionColor }}
      >
        {title}
      </h2>
      <div className="flex-1 h-px bg-[var(--color-border)] dark:bg-[var(--color-border-dark)]" />
      <Link
        href={href}
        className="text-[var(--color-accent)] hover:text-[var(--color-accent-hover)] font-medium transition-colors font-sans whitespace-nowrap"
        style={{ fontSize: 'var(--font-size-sm)' }}
      >
        {viewAllLabel} →
      </Link>
    </div>
  );
}

/** Vertical card: image on top, title + excerpt + badge below */
function VerticalCard({
  article,
  locale,
  small = false,
  showExcerpt = true,
}: {
  article: Article;
  locale: string;
  small?: boolean;
  showExcerpt?: boolean;
}) {
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage
    ? getThumbnailByProfile(featuredImage, small ? 'card_small' : 'card_medium')
    : null;
  const imageToUse = thumbnail || featuredImage;
  const articleUrl = buildArticleUrl(article, locale as Locale);
  const categorySlug = getCategorySlugFromArticle(article.category);
  const categoryTitle = getCategoryTitle(article.category);
  const sectionColor = getSectionColor(categorySlug);
  const excerpt = article.lead
    ? stripHtml(article.lead)
    : (article.content ? getFirstSentence(article.content) : '');

  return (
    <article className="group">
      <Link href={articleUrl} className="block">
        {/* Image */}
        <div className="relative aspect-[3/2] overflow-hidden rounded-lg mb-3 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)]">
          {imageToUse ? (
            <Image
              src={buildImageUrl(imageToUse.path)}
              alt={featuredImage?.alt || article.title}
              fill
              sizes={small
                ? '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 25vw'
                : '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw'}
              className="object-cover transition-transform duration-300 group-hover:scale-105"
              loading="lazy"
            />
          ) : (
            <div className="absolute inset-0 bg-gradient-to-br from-[var(--color-skeleton)] to-[var(--color-border)] dark:from-[var(--color-skeleton-dark)] dark:to-[var(--color-border-dark)]" />
          )}
        </div>

        {/* Title */}
        <h3
          className={`font-sans font-bold leading-tight line-clamp-2 text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] group-hover:text-[var(--color-accent)] transition-colors duration-200 ${small ? 'text-sm' : 'text-base lg:text-lg'
            }`}
        >
          {article.title}
        </h3>

        {/* Excerpt */}
        {showExcerpt && excerpt && (
          <p className="mt-1 text-sm text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] line-clamp-2 font-serif">
            {excerpt}
          </p>
        )}
      </Link>

      {/* Category + Date */}
      <div className="flex items-center gap-2 mt-2">
        {categoryTitle && (
          <span
            className="text-xs font-semibold tracking-wider uppercase font-sans"
            style={{ color: sectionColor }}
          >
            {categoryTitle}
          </span>
        )}
        {article.publishedAt && (
          <time
            className="text-xs font-sans text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)]"
            dateTime={article.publishedAt}
          >
            {formatRelativeTime(article.publishedAt, locale)}
          </time>
        )}
      </div>
    </article>
  );
}

/** Compact row: thumbnail (80×80) + title + category */
function CompactRow({
  article,
  locale,
}: {
  article: Article;
  locale: string;
}) {
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage
    ? getThumbnailByProfile(featuredImage, 'card_small')
    : null;
  const imageToUse = thumbnail || featuredImage;
  const articleUrl = buildArticleUrl(article, locale as Locale);
  const categorySlug = getCategorySlugFromArticle(article.category);
  const categoryTitle = getCategoryTitle(article.category);
  const sectionColor = getSectionColor(categorySlug);

  return (
    <article className="group">
      <Link href={articleUrl} className="flex items-start gap-3">
        {/* Thumbnail */}
        <div className="relative flex-shrink-0 w-20 h-20 overflow-hidden rounded-lg bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)]">
          {imageToUse ? (
            <Image
              src={buildImageUrl(imageToUse.path)}
              alt={featuredImage?.alt || article.title}
              fill
              sizes="80px"
              className="object-cover transition-transform duration-300 group-hover:scale-110"
              loading="lazy"
            />
          ) : (
            <div className="absolute inset-0 bg-gradient-to-br from-[var(--color-skeleton)] to-[var(--color-border)] dark:from-[var(--color-skeleton-dark)] dark:to-[var(--color-border-dark)]" />
          )}
        </div>

        {/* Text */}
        <div className="flex-1 min-w-0">
          <h3 className="font-sans font-bold text-sm leading-tight line-clamp-2 text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] group-hover:text-[var(--color-accent)] transition-colors duration-200">
            {article.title}
          </h3>
          <div className="flex items-center gap-2 mt-1.5">
            {categoryTitle && (
              <span
                className="text-xs font-semibold tracking-wider uppercase font-sans"
                style={{ color: sectionColor }}
              >
                {categoryTitle}
              </span>
            )}
            {article.publishedAt && (
              <time
                className="text-xs font-sans text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)]"
                dateTime={article.publishedAt}
              >
                {formatRelativeTime(article.publishedAt, locale)}
              </time>
            )}
          </div>
        </div>
      </Link>
    </article>
  );
}

/** Featured card: large image + big title + excerpt (for layout D) */
function FeaturedCard({
  article,
  locale,
}: {
  article: Article;
  locale: string;
}) {
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage
    ? getThumbnailByProfile(featuredImage, 'hero_small')
    : null;
  const imageToUse = thumbnail || featuredImage;
  const articleUrl = buildArticleUrl(article, locale as Locale);
  const categorySlug = getCategorySlugFromArticle(article.category);
  const categoryTitle = getCategoryTitle(article.category);
  const sectionColor = getSectionColor(categorySlug);
  const excerpt = article.lead
    ? stripHtml(article.lead)
    : (article.content ? getFirstSentence(article.content, 200) : '');

  return (
    <article className="group">
      <Link href={articleUrl} className="block">
        {/* Image */}
        <div className="relative aspect-[3/2] overflow-hidden rounded-lg mb-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)]">
          {imageToUse ? (
            <Image
              src={buildImageUrl(imageToUse.path)}
              alt={featuredImage?.alt || article.title}
              fill
              sizes="(max-width: 768px) 100vw, 50vw"
              className="object-cover transition-transform duration-500 group-hover:scale-105"
              loading="lazy"
            />
          ) : (
            <div className="absolute inset-0 bg-gradient-to-br from-[var(--color-skeleton)] to-[var(--color-border)] dark:from-[var(--color-skeleton-dark)] dark:to-[var(--color-border-dark)]" />
          )}
        </div>

        {/* Title */}
        <h3 className="font-sans font-bold text-xl lg:text-2xl leading-tight text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] group-hover:text-[var(--color-accent)] transition-colors duration-200 line-clamp-3">
          {article.title}
        </h3>

        {/* Excerpt */}
        {excerpt && (
          <p className="mt-2 text-sm text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] line-clamp-3 font-serif">
            {excerpt}
          </p>
        )}
      </Link>

      <div className="flex items-center gap-2 mt-2">
        {categoryTitle && (
          <span
            className="text-xs font-semibold tracking-wider uppercase font-sans"
            style={{ color: sectionColor }}
          >
            {categoryTitle}
          </span>
        )}
        {article.publishedAt && (
          <time
            className="text-xs font-sans text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)]"
            dateTime={article.publishedAt}
          >
            {formatRelativeTime(article.publishedAt, locale)}
          </time>
        )}
      </div>
    </article>
  );
}

/* ================================================================== */
/*  Layout renderers                                                   */
/* ================================================================== */

/** Layout A — 3-column vertical card grid */
function LayoutGrid3col({ articles, locale }: { articles: Article[]; locale: string }) {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      {articles.slice(0, 6).map((article) => (
        <VerticalCard key={article.id} article={article} locale={locale} />
      ))}
    </div>
  );
}

/** Layout B — compact thumbnail+title rows */
function LayoutCompactList({ articles, locale }: { articles: Article[]; locale: string }) {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-0">
      {articles.slice(0, 6).map((article) => (
        <div
          key={article.id}
          className="py-3 border-[var(--color-border)] dark:border-[var(--color-border-dark)] last:border-b-0"
        >
          <CompactRow article={article} locale={locale} />
        </div>
      ))}
    </div>
  );
}

/** Layout C — 4-column vertical card grid */
function LayoutGrid4col({ articles, locale }: { articles: Article[]; locale: string }) {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      {articles.slice(0, 8).map((article) => (
        <VerticalCard key={article.id} article={article} locale={locale} small />
      ))}
    </div>
  );
}

/** Layout D — 1 featured + 4 compact stacked + 3 small cards */
function LayoutFeaturedGrid({ articles, locale }: { articles: Article[]; locale: string }) {
  if (articles.length === 0) return null;

  const featured = articles[0];
  const stacked = articles.slice(1, 5);
  const bottom = articles.slice(5, 8);

  return (
    <div>
      {/* Top: featured left + stacked right */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <FeaturedCard article={featured} locale={locale} />

        {stacked.length > 0 && (
          <div className="flex flex-col gap-0">
            {stacked.map((article) => (
              <div
                key={article.id}
                className="first:pt-0 py-5 border-[var(--color-border)] dark:border-[var(--color-border-dark)] last:border-b-0"
              >
                <CompactRow article={article} locale={locale} />
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Bottom: 3 small cards */}
      {bottom.length > 0 && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          {bottom.map((article) => (
            <VerticalCard key={article.id} article={article} locale={locale} small />
          ))}
        </div>
      )}
    </div>
  );
}

/* ================================================================== */
/*  Main component (server-side with Suspense)                         */
/* ================================================================== */

async function CategorySectionContent({
  category,
  locale,
  layout,
}: CategorySectionProps) {
  const maxArticles = layout === 'grid-4col' || layout === 'featured-grid' ? 8 : 6;

  let articles: Article[] = [];
  try {
    const response = await fetchArticlesByCategory(category.id, locale, maxArticles);
    articles = response.member || [];
  } catch (error) {
    console.error(`Failed to fetch articles for category ${category.id}:`, error);
  }

  if (articles.length === 0) return null;

  const categorySlug = category.slug || 'default';
  const sectionColor = getSectionColor(categorySlug);
  const categoryTitle = category.title || 'Uncategorized';
  const categoryUrl = buildCategoryUrl(category, locale as Locale);
  const localeLabels = labels[locale as keyof typeof labels] || labels.ro;

  return (
    <section>
      <SectionHeader
        title={categoryTitle}
        href={categoryUrl}
        sectionColor={sectionColor}
        viewAllLabel={localeLabels.viewAll}
      />

      {layout === 'grid-3col' && <LayoutGrid3col articles={articles} locale={locale} />}
      {layout === 'compact-list' && <LayoutCompactList articles={articles} locale={locale} />}
      {layout === 'grid-4col' && <LayoutGrid4col articles={articles} locale={locale} />}
      {layout === 'featured-grid' && <LayoutFeaturedGrid articles={articles} locale={locale} />}
    </section>
  );
}

/* ================================================================== */
/*  Skeleton                                                           */
/* ================================================================== */

function CategorySectionSkeleton() {
  return (
    <div>
      {/* Header skeleton */}
      <div className="flex items-center gap-4 mb-6">
        <div className="w-32 h-8 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded animate-pulse" />
        <div className="flex-1 h-px bg-[var(--color-border)] dark:bg-[var(--color-border-dark)]" />
        <div className="w-20 h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded animate-pulse" />
      </div>
      {/* Cards skeleton */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        {Array.from({ length: 3 }).map((_, i) => (
          <div key={i} className="animate-pulse">
            <div className="aspect-[3/2] bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded-lg mb-3" />
            <div className="h-5 w-full bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded mb-2" />
            <div className="h-5 w-3/4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded mb-2" />
            <div className="h-4 w-full bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded" />
          </div>
        ))}
      </div>
    </div>
  );
}

/* ================================================================== */
/*  Export                                                              */
/* ================================================================== */

export default function CategorySection({
  category,
  locale,
  layout,
}: CategorySectionProps) {
  return (
    <Suspense fallback={<CategorySectionSkeleton />}>
      <CategorySectionContent category={category} locale={locale} layout={layout} />
    </Suspense>
  );
}
