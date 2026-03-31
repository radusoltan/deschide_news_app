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
  ro: { latest: 'Ultimele știri', mostRead: 'Cele mai citite', inTrend: 'În trend', ad: 'Publicitate', viewAll: 'Vezi toate' },
  en: { latest: 'Latest news', mostRead: 'Most Read', inTrend: 'Trending', ad: 'Advertisement', viewAll: 'View all' },
  ru: { latest: 'Последние новости', mostRead: 'Самые читаемые', inTrend: 'В тренде', ad: 'Реклама', viewAll: 'Смотреть все' },
} as const;

/* Static placeholder articles for "In Trend" — will be replaced with API data later */
const TRENDING_PLACEHOLDER: Record<string, Array<{ id: number; title: string; category: string; categoryColor: string }>> = {
  ro: [
    { id: 1001, title: 'Alegerile prezidențiale 2025: ultimele sondaje', category: 'Politică', categoryColor: 'var(--color-section-politics)' },
    { id: 1002, title: 'Cursul valutar: leul moldovenesc se stabilizează', category: 'Economie', categoryColor: 'var(--color-section-economy)' },
    { id: 1003, title: 'Festival internațional de film la Chișinău', category: 'Cultură', categoryColor: 'var(--color-section-culture)' },
    { id: 1004, title: 'Reforma sistemului educațional: ce se schimbă', category: 'Societate', categoryColor: 'var(--color-section-society)' },
    { id: 1005, title: 'Integrarea europeană: noi pași spre aderare', category: 'Externe', categoryColor: 'var(--color-section-world)' },
    { id: 1006, title: 'Campionatul național de fotbal: rezultatele etapei', category: 'Sport', categoryColor: 'var(--color-section-sport)' },
    { id: 1007, title: 'Tehnologiile verzi: Moldova accelerează tranziția', category: 'Tehnologie', categoryColor: 'var(--color-section-tech)' },
    { id: 1008, title: 'Diaspora moldovenească: noi politici de repatriere', category: 'Societate', categoryColor: 'var(--color-section-society)' },
    { id: 1009, title: 'Parlamentul aprobă bugetul pentru anul viitor', category: 'Politică', categoryColor: 'var(--color-section-politics)' },
    { id: 1010, title: 'Exporturile agricole cresc cu 15% în trimestrul III', category: 'Economie', categoryColor: 'var(--color-section-economy)' },
  ],
  en: [
    { id: 1001, title: 'Presidential elections 2025: latest polls', category: 'Politics', categoryColor: 'var(--color-section-politics)' },
    { id: 1002, title: 'Exchange rate: Moldovan leu stabilizes', category: 'Economy', categoryColor: 'var(--color-section-economy)' },
    { id: 1003, title: 'International film festival in Chișinău', category: 'Culture', categoryColor: 'var(--color-section-culture)' },
    { id: 1004, title: 'Education system reform: what changes', category: 'Society', categoryColor: 'var(--color-section-society)' },
    { id: 1005, title: 'European integration: new steps toward accession', category: 'World', categoryColor: 'var(--color-section-world)' },
    { id: 1006, title: 'National football championship: round results', category: 'Sport', categoryColor: 'var(--color-section-sport)' },
    { id: 1007, title: 'Green technologies: Moldova accelerates transition', category: 'Tech', categoryColor: 'var(--color-section-tech)' },
    { id: 1008, title: 'Moldovan diaspora: new repatriation policies', category: 'Society', categoryColor: 'var(--color-section-society)' },
    { id: 1009, title: 'Parliament approves next year\'s budget', category: 'Politics', categoryColor: 'var(--color-section-politics)' },
    { id: 1010, title: 'Agricultural exports grow 15% in Q3', category: 'Economy', categoryColor: 'var(--color-section-economy)' },
  ],
  ru: [
    { id: 1001, title: 'Президентские выборы 2025: последние опросы', category: 'Политика', categoryColor: 'var(--color-section-politics)' },
    { id: 1002, title: 'Курс валют: молдавский лей стабилизируется', category: 'Экономика', categoryColor: 'var(--color-section-economy)' },
    { id: 1003, title: 'Международный кинофестиваль в Кишинёве', category: 'Культура', categoryColor: 'var(--color-section-culture)' },
    { id: 1004, title: 'Реформа системы образования: что меняется', category: 'Общество', categoryColor: 'var(--color-section-society)' },
    { id: 1005, title: 'Европейская интеграция: новые шаги', category: 'Мир', categoryColor: 'var(--color-section-world)' },
    { id: 1006, title: 'Чемпионат по футболу: результаты тура', category: 'Спорт', categoryColor: 'var(--color-section-sport)' },
    { id: 1007, title: 'Зелёные технологии: Молдова ускоряет переход', category: 'Технологии', categoryColor: 'var(--color-section-tech)' },
    { id: 1008, title: 'Молдавская диаспора: новая политика репатриации', category: 'Общество', categoryColor: 'var(--color-section-society)' },
    { id: 1009, title: 'Парламент утвердил бюджет на следующий год', category: 'Политика', categoryColor: 'var(--color-section-politics)' },
    { id: 1010, title: 'Экспорт сельхозпродукции вырос на 15% в III квартале', category: 'Экономика', categoryColor: 'var(--color-section-economy)' },
  ],
};

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
                {categoryTitle && <span className="text-gray-300 text-xs">·</span>}
                <time className="text-xs font-sans text-gray-300" dateTime={article.publishedAt}>
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

/** Most Popular — gray header bar + numbered list with large faded digits (TailNews style) */
function MostPopularWidget({
  articles,
  locale,
}: {
  articles: Array<{ id: number; title: string | null; slug: string | null; category: { slug: string } | null }>;
  locale: string;
}) {
  const l = labels[locale as keyof typeof labels] || labels.ro;

  return (
    <div className="bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)]">
      {/* Gray header bar */}
      <div className="p-4 bg-[var(--color-surface-sunken)] dark:bg-[var(--color-surface-sunken-dark)]">
        <h2 className="font-sans font-bold text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)]" style={{ fontSize: 'var(--font-size-lg)' }}>
          {l.mostRead}
        </h2>
      </div>

      <ul>
        {articles.slice(0, 5).map((article, index) => {
          const catSlug = article.category?.slug || 'news';
          const artSlug = article.slug || '';
          const articleUrl = `/${locale === 'ro' ? '' : locale + '/'}${catSlug}/${artSlug}`;

          return (
            <li
              key={article.id}
              className="border-b border-[var(--color-border)] dark:border-[var(--color-border-dark)] last:border-b-0 hover:bg-[var(--color-surface-sunken)] dark:hover:bg-[var(--color-surface-sunken-dark)] transition-colors"
            >
              <Link
                href={articleUrl}
                className="flex items-center gap-3 px-4 py-3 font-sans font-bold text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] hover:text-[var(--color-accent)] transition-colors"
                style={{ fontSize: 'var(--font-size-base)' }}
              >
                <span className="flex-shrink-0 text-3xl font-bold leading-none font-sans text-[var(--color-border)] dark:text-[var(--color-border-dark)] select-none min-w-[1.5rem]">
                  {index + 1}
                </span>
                <span className="line-clamp-2">{article.title}</span>
              </Link>
            </li>
          );
        })}
      </ul>
    </div>
  );
}

/** "In Trend" list with colored category dot + title */
function InTrendWidget({ locale }: { locale: string }) {
  const l = labels[locale as keyof typeof labels] || labels.ro;
  const items = TRENDING_PLACEHOLDER[locale] || TRENDING_PLACEHOLDER.ro;

  return (
    <div>
      <h2
        className="font-sans font-bold text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] mb-4 pb-2 border-b-2 border-[var(--color-accent)]"
        style={{ fontSize: 'var(--font-size-lg)' }}
      >
        {l.inTrend}
      </h2>

      <ul className="space-y-0">
        {items.map((item) => (
          <li
            key={item.id}
            className="group py-3 border-b border-[var(--color-border)] dark:border-[var(--color-border-dark)] last:border-b-0"
          >
            <span
              className="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider font-sans mb-1"
              style={{ color: item.categoryColor }}
            >
              <span
                className="inline-block w-1.5 h-1.5 rounded-full flex-shrink-0"
                style={{ backgroundColor: item.categoryColor }}
              />
              {item.category}
            </span>
            <p
              className="font-sans font-medium leading-snug text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] group-hover:text-[var(--color-accent)] transition-colors cursor-pointer line-clamp-2"
              style={{ fontSize: 'var(--font-size-sm)' }}
            >
              {item.title}
            </p>
          </li>
        ))}
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
  popularArticles: Array<{ id: number; title: string | null; slug: string | null; category: { slug: string } | null }>;
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
        <aside className="w-full lg:w-1/3 lg:pr-8 lg:pt-14">
          <div className="sticky top-24 space-y-8">
            {popularArticles.length > 0 && (
              <MostPopularWidget articles={popularArticles} locale={locale} />
            )}
            <InTrendWidget locale={locale} />
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
              href={buildLocalizedUrl('/', locale as Locale)}
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
        <aside className="w-full lg:w-1/3 lg:pr-8 lg:pt-14 space-y-8">
          <div>
            <div className="p-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded-t animate-pulse">
              <div className="w-32 h-5 bg-[var(--color-surface)] dark:bg-[var(--color-surface-dark)] rounded" />
            </div>
            {Array.from({ length: 5 }).map((_, i) => (
              <div key={i} className="flex items-center gap-3 px-4 py-3 animate-pulse">
                <div className="w-6 h-8 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded" />
                <div className="flex-1 h-4 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded" />
              </div>
            ))}
          </div>
          <div>
            <div className="w-28 h-6 bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] rounded animate-pulse mb-4" />
            {Array.from({ length: 5 }).map((_, i) => (
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
