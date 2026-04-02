/**
 * LatestNewsSection — TailNews "Latest news" layout
 *
 * Matches https://demo.tailwindtemplate.net/tailnews/ :
 *
 * ┌──────────────────────┬──────────────────────────────────────────────┐
 * │  SIDEBAR (1/3)       │  ## Ultimele Știri (red accent │)            │
 * │  ┌────────────────┐  │  ┌─────────────────────────────────────────┐│
 * │  │Most Popular    │  │  │  FEATURED (overlay on image)            ││
 * │  │ bg-gray header │  │  │  Title + Excerpt + Category             ││
 * │  │ 1  Title       │  │  └─────────────────────────────────────────┘│
 * │  │ 2  Title       │  │  ┌──────────┬──────────┬──────────┐        │
 * │  │ 3  Title       │  │  │ img      │ img      │ img      │        │
 * │  │ 4  Title       │  │  │ Title    │ Title    │ Title    │        │
 * │  │ 5  Title       │  │  │ Excerpt  │ Excerpt  │ Excerpt  │        │
 * │  └────────────────┘  │  │ │Category│ │Category│ │Category│        │
 * │  ┌────────────────┐  │  ├──────────┼──────────┼──────────┤        │
 * │  │ In Trend       │  │  │ img      │ img      │ img      │        │
 * │  │ ...10 items    │  │  │ Title    │ Title    │ Title    │        │
 * │  └────────────────┘  │  │ Excerpt  │ Excerpt  │ Excerpt  │        │
 * │  ┌────────────────┐  │  │ │Category│ │Category│ │Category│        │
 * │  │ Ad 300x250     │  │  └──────────┴──────────┴──────────┘        │
 * │  └────────────────┘  │                                            │
 * └──────────────────────┴────────────────────────────────────────────┘
 */

import { Suspense } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import {
  getFeaturedImage,
  getThumbnailByProfile,
  buildImageUrl,
} from '@/lib/api/important-articles';
import { buildArticleUrl, buildLocalizedUrl } from '@/lib/utils/url-builder';
import {
  getSectionColor,
  getCategorySlugFromArticle,
  getCategoryTitle,
  formatRelativeTime,
} from '@/components/cards/utils';
import type { Article } from '@/lib/types/article';
import type { Locale } from '@/lib/types';

/* ================================================================== */
/*  Localized labels                                                   */
/* ================================================================== */

const labels = {
  ro: { latest: 'Ultimele știri', inTrend: 'În trend', ad: 'Publicitate', viewAll: 'Vezi toate' },
  en: { latest: 'Latest news', inTrend: 'Trending', ad: 'Advertisement', viewAll: 'View all' },
  ru: { latest: 'Последние новости', inTrend: 'В тренде', ad: 'Реклама', viewAll: 'Смотреть все' },
} as const;

import type { TrendingArticle } from '@/lib/api/statistics';

/* ================================================================== */
/*  Sub-components                                                     */
/* ================================================================== */

/** Featured article card — full-width image overlay with gradient (TailNews style) */
function FeaturedArticleCard({ article, locale }: { article: Article; locale: string }) {
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage ? getThumbnailByProfile(featuredImage, 'hero_small') : null;
  const imageToUse = thumbnail || featuredImage;
  const articleUrl = buildArticleUrl(article, locale as Locale);
  const categorySlug = getCategorySlugFromArticle(article.category);
  const categoryTitle = getCategoryTitle(article.category);
  const sectionColor = getSectionColor(categorySlug);

  return (
    <article className="group relative overflow-hidden rounded-lg">
      <Link href={articleUrl} className="block">
        <div className="relative aspect-[16/9] md:aspect-[2/1] overflow-hidden rounded-lg bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)]">
          {imageToUse ? (
            <Image
              src={buildImageUrl(imageToUse.path)}
              alt={featuredImage?.alt || article.title}
              fill
              className="object-cover transition-transform duration-700 group-hover:scale-105"
              sizes="(max-width: 1024px) 100vw, 66vw"
              priority
            />
          ) : (
            <div className="absolute inset-0 bg-gradient-to-br from-[var(--color-skeleton)] to-[var(--color-border)] dark:from-[var(--color-skeleton-dark)] dark:to-[var(--color-border-dark)]" />
          )}
          {/* Gradient overlay */}
          <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/40 to-transparent" />
        </div>

        {/* Text overlay */}
        <div className="absolute bottom-0 left-0 right-0 px-5 pt-8 pb-5">
          <h2
            className="font-sans font-bold text-white leading-tight mb-3 text-on-photo-strong"
            style={{ fontSize: 'var(--font-size-2xl)' }}
          >
            {article.title}
          </h2>
          {article.lead && (
            <p className="text-gray-100 hidden sm:inline-block font-serif line-clamp-2" style={{ fontSize: 'var(--font-size-sm)' }}>
              {article.lead}
            </p>
          )}
          <div className="flex items-center gap-2 mt-2">
            {categoryTitle && (
              <span
                className="text-xs font-semibold tracking-wider uppercase font-sans text-gray-100"
              >
                {categoryTitle}
              </span>
            )}
            {article.publishedAt && (
              <>
                {categoryTitle && <span className="text-primary-dark text-xs">·</span>}
                <time className="text-xs font-sans text-primary-dark" dateTime={article.publishedAt}>
                  {formatRelativeTime(article.publishedAt, locale)}
                </time>
              </>
            )}
          </div>
        </div>
      </Link>
    </article>
  );
}

/** Grid article card — vertical card matching CategorySection's VerticalCard style */
function GridArticleCard({ article, locale }: { article: Article; locale: string }) {
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage ? getThumbnailByProfile(featuredImage, 'card_medium') : null;
  const imageToUse = thumbnail || featuredImage;
  const articleUrl = buildArticleUrl(article, locale as Locale);
  const categorySlug = getCategorySlugFromArticle(article.category);
  const categoryTitle = getCategoryTitle(article.category);
  const sectionColor = getSectionColor(categorySlug);

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
              className="object-cover transition-transform duration-300 group-hover:scale-105"
              sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 22vw"
            />
          ) : (
            <div className="absolute inset-0 bg-gradient-to-br from-[var(--color-skeleton)] to-[var(--color-border)] dark:from-[var(--color-skeleton-dark)] dark:to-[var(--color-border-dark)]" />
          )}
        </div>

        {/* Title */}
        <h3 className="font-sans font-bold leading-tight line-clamp-2 text-base lg:text-lg text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] group-hover:text-[var(--color-accent)] transition-colors duration-200">
          {article.title}
        </h3>

        {/* Excerpt */}
        {article.lead && (
          <p className="mt-1 text-sm text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] line-clamp-2 font-serif">
            {article.lead}
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

/** "In Trend" list with colored category dot + title — real data from API */
function InTrendWidget({ articles, locale }: { articles: TrendingArticle[]; locale: string }) {
  const l = labels[locale as keyof typeof labels] || labels.ro;

  if (!articles || articles.length === 0) return null;

  return (
    <div>
      <h2
        className="font-sans font-bold text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] mb-4 pb-2 border-b-2 border-[var(--color-accent)]"
        style={{ fontSize: 'var(--font-size-lg)' }}
      >
        {l.inTrend}
      </h2>

      <ul className="space-y-0">
        {articles.slice(0, 10).map((article) => {
          const catSlug = article.category?.slug || 'news';
          const sectionColor = getSectionColor(catSlug);
          const articleUrl = `/${locale}/${catSlug}/${article.slug}`;

          return (
            <li
              key={article.id}
              className="group py-3 border-b border-[var(--color-border)] dark:border-[var(--color-border-dark)] last:border-b-0"
            >
              <Link href={articleUrl}>
                <span
                  className="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider font-sans mb-1"
                  style={{ color: sectionColor }}
                >
                  <span
                    className="inline-block w-1.5 h-1.5 rounded-full flex-shrink-0"
                    style={{ backgroundColor: sectionColor }}
                  />
                  {article.category?.name}
                </span>
                <p
                  className="font-sans font-medium leading-snug text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] group-hover:text-[var(--color-accent)] transition-colors cursor-pointer line-clamp-2"
                  style={{ fontSize: 'var(--font-size-sm)' }}
                >
                  {article.title}
                </p>
              </Link>
            </li>
          );
        })}
      </ul>
    </div>
  );
}

/** Ad placeholder block */
function AdPlaceholder({ locale }: { locale: string }) {
  const l = labels[locale as keyof typeof labels] || labels.ro;

  return (
    <div className="text-center">
      <span className="text-xs text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)] uppercase tracking-wider font-sans block mb-2">
        {l.ad}
      </span>
      <div className="mx-auto w-[300px] h-[250px] bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] flex items-center justify-center">
        <span className="text-xs text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)] font-sans">
          300 x 250
        </span>
      </div>
    </div>
  );
}

/* ================================================================== */
/*  Main Section                                                       */
/* ================================================================== */

interface LatestNewsSectionProps {
  articles: Article[];
  popularArticles: TrendingArticle[];
  locale: string;
}

function LatestNewsSectionContent({ articles, popularArticles, locale }: LatestNewsSectionProps) {
  if (articles.length === 0) return null;

  const l = labels[locale as keyof typeof labels] || labels.ro;

  const featuredArticle = articles[0];
  const gridArticles = articles.slice(1, 7); // 6 cards in 2 rows of 3

  return (
    <section className="mt-8 md:mt-12">
      {/* Layout: sidebar LEFT (1/3) + main content RIGHT (2/3) */}
      <div className="flex flex-col-reverse lg:flex-row gap-8">

        {/* ── Sidebar (LEFT on desktop, BELOW on mobile) ── */}
        <aside className="w-full lg:w-1/3 lg:pr-8">
          <div className="sticky top-24 space-y-8">
            <InTrendWidget articles={popularArticles} locale={locale} />
            <AdPlaceholder locale={locale} />
          </div>
        </aside>

        {/* ── Main content (RIGHT on desktop) ── */}
        <div className="w-full lg:w-2/3 overflow-hidden">
          {/* Section header — matches CategorySection SectionHeader */}
          <div className="flex items-center gap-4 mb-6">
            <h2
              className="font-sans font-bold text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] whitespace-nowrap border-b-2 pb-1"
              style={{ fontSize: 'var(--font-size-2xl)', borderBottomColor: 'var(--color-breaking)' }}
            >
              {l.latest}
            </h2>
            <div className="flex-1 h-px bg-[var(--color-border)] dark:bg-[var(--color-border-dark)]" />
            <Link
              href={buildLocalizedUrl('/all', locale as Locale)}
              className="text-[var(--color-accent)] hover:text-[var(--color-accent-hover)] font-medium transition-colors font-sans whitespace-nowrap"
              style={{ fontSize: 'var(--font-size-sm)' }}
            >
              {l.viewAll} →
            </Link>
          </div>

          {/* Featured article — full width overlay */}
          <div className="pb-5">
            <FeaturedArticleCard article={featuredArticle} locale={locale} />
          </div>

          {/* Grid: 3 columns, 2 rows */}
          {gridArticles.length > 0 && (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 pt-3">
              {gridArticles.map((article) => (
                <GridArticleCard key={article.id} article={article} locale={locale} />
              ))}
            </div>
          )}
        </div>
      </div>
    </section>
  );
}

/* ================================================================== */
/*  Skeleton                                                           */
/* ================================================================== */

function LatestNewsSectionSkeleton() {
  return (
    <section className="mt-8 md:mt-12">
      <div className="flex flex-col-reverse lg:flex-row gap-8">
        {/* Sidebar skeleton */}
        <aside className="w-full lg:w-1/3 lg:pr-8 lg:pt-1 space-y-8">
          <div>
            <div className="w-28 h-6 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded animate-pulse mb-4" />
            {Array.from({ length: 10 }).map((_, i) => (
              <div key={i} className="py-3 animate-pulse space-y-1.5">
                <div className="h-3 w-16 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded" />
                <div className="h-4 w-full bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded" />
              </div>
            ))}
          </div>
        </aside>

        {/* Main content skeleton */}
        <div className="w-full lg:w-2/3">
          <div className="flex items-center gap-4 mb-6">
            <div className="w-40 h-8 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded animate-pulse" />
            <div className="flex-1 h-px bg-[var(--color-border)] dark:bg-[var(--color-border-dark)]" />
            <div className="w-20 h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded animate-pulse" />
          </div>
          <div className="aspect-[2/1] bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] animate-pulse rounded-lg mb-5" />
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            {Array.from({ length: 6 }).map((_, i) => (
              <div key={i} className="animate-pulse">
                <div className="aspect-[3/2] bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded-lg mb-3" />
                <div className="h-5 w-full bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded mb-2" />
                <div className="h-5 w-3/4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded mb-2" />
                <div className="h-4 w-full bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded" />
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}

/* ================================================================== */
/*  Export                                                              */
/* ================================================================== */

export default function LatestNewsSection(props: LatestNewsSectionProps) {
  return (
    <Suspense fallback={<LatestNewsSectionSkeleton />}>
      <LatestNewsSectionContent {...props} />
    </Suspense>
  );
}
