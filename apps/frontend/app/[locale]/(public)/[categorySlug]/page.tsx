/**
 * Category Page — TailNews-aligned design
 * Matches homepage layout: sidebar LEFT (1/3) + main content RIGHT (2/3)
 * Uses design system tokens exclusively — no legacy brand-* classes.
 * Route: /{locale}/{categorySlug}
 */

import { notFound } from 'next/navigation';
import type { Metadata } from 'next';
import Link from 'next/link';
import Image from 'next/image';
import { fetchCategories } from '@/lib/api/categories';
import { fetchArticlesByCategory } from '@/lib/api/articles';
import { getTrendingArticles } from '@/lib/api/statistics';
import { isReservedSlug } from '@/lib/constants/reserved-slugs';
import { getFallbackContent, hasPendingTranslation, isSupportedLocale, LocaleFallbackNotice } from '@/lib/i18n/locale-fallback';
import { generateCategoryMetadata } from '@/lib/seo/meta-tags';
import { buildImageUrl, getThumbnailByProfile, getFeaturedImage } from '@/lib/api/important-articles';
import { buildArticleUrl, buildCategoryLocaleAlternates } from '@/lib/utils/url-builder';
import { getSectionColor, getCategorySlugFromArticle } from '@/components/cards/utils';
import LocaleContextSetter from '@/app/components/LocaleContextSetter';
import type { Locale } from '@/lib/types';
import type { Article, Category } from '@/lib/types/article';
import type { TrendingArticle } from '@/lib/api/statistics';

export const dynamic = 'force-dynamic';
export const revalidate = 120;

/* ================================================================== */
/*  Localized labels                                                   */
/* ================================================================== */

const labels = {
  ro: { mostRead: 'Cele mai citite', inTrend: 'În trend', ad: 'Publicitate', noArticles: 'Niciun articol găsit', noArticlesDesc: 'Nu există articole în această categorie momentan.', prev: 'Înapoi', next: 'Următoarea' },
  en: { mostRead: 'Most Read', inTrend: 'Trending', ad: 'Advertisement', noArticles: 'No articles found', noArticlesDesc: 'There are no articles in this category yet.', prev: 'Previous', next: 'Next' },
  ru: { mostRead: 'Самые читаемые', inTrend: 'В тренде', ad: 'Реклама', noArticles: 'Статьи не найдены', noArticlesDesc: 'В этой категории пока нет статей.', prev: 'Назад', next: 'Далее' },
} as const;

/* Static placeholder articles for "In Trend" — same as homepage, will be replaced with API data later */
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
/*  Metadata                                                           */
/* ================================================================== */

export async function generateMetadata({ params }: CategoryPageProps): Promise<Metadata> {
  const { locale, categorySlug } = await params;
  const validLocale = isSupportedLocale(locale) ? locale : 'ro';
  if (isReservedSlug(categorySlug)) return { title: 'Page Not Found' };
  try {
    const { content: category, effectiveLocale } = await getFallbackContent(validLocale, async (candidateLocale) => {
      const categoriesResponse = await fetchCategories(candidateLocale);
      return (categoriesResponse.member || []).find((cat: Category) => cat.slug === categorySlug) || null;
    }, { isTranslationPending: hasPendingTranslation });
    if (!category) return { title: 'Category Not Found' };
    return generateCategoryMetadata(category.title, category.slug, effectiveLocale, category.description);
  } catch {
    return { title: 'Category | Deschide News' };
  }
}

/* ================================================================== */
/*  Page                                                               */
/* ================================================================== */

interface CategoryPageProps {
  params: Promise<{ locale: string; categorySlug: string }>;
  searchParams: Promise<{ page?: string }>;
}

function getCatTitle(category: Category | string): string {
  return typeof category === 'object' && category?.title ? category.title : '';
}

export default async function CategoryPage({ params, searchParams }: CategoryPageProps) {
  const { locale, categorySlug } = await params;
  const { page: pageParam } = await searchParams;
  const requestedLocale = isSupportedLocale(locale) ? locale : 'ro';

  if (isReservedSlug(categorySlug)) notFound();

  // Fetch category
  const categoryFallback = await getFallbackContent(requestedLocale, async (candidateLocale) => {
    const categoriesResponse = await fetchCategories(candidateLocale);
    return (categoriesResponse.member || []).find((cat: Category) => cat.slug === categorySlug) || null;
  }, { isTranslationPending: hasPendingTranslation });

  let category = categoryFallback.content;
  if (!category) notFound();
  let effectiveLocale = categoryFallback.effectiveLocale;
  let isLangFallback = categoryFallback.isFallback;

  const currentPage = pageParam ? parseInt(pageParam, 10) : 1;
  const itemsPerPage = 10;

  let articles: Article[] = [];
  let totalItems = 0;
  let trendingArticles: TrendingArticle[] = [];

  try {
    const articlesResult = await fetchArticlesByCategory(category.id, effectiveLocale, itemsPerPage);
    articles = articlesResult.member || [];
    totalItems = articlesResult.totalItems || 0;
  } catch { /* fallback below */ }

  if (!isLangFallback && requestedLocale !== 'ro' && totalItems === 0) {
    try {
      const roArticlesResult = await fetchArticlesByCategory(category.id, 'ro', itemsPerPage);
      const roTotalItems = roArticlesResult.totalItems || 0;

      if (roTotalItems > 0) {
        articles = roArticlesResult.member || [];
        totalItems = roTotalItems;
        effectiveLocale = 'ro';
        isLangFallback = true;

        const roCategoriesResponse = await fetchCategories('ro');
        category = (roCategoriesResponse.member || []).find((cat: Category) => cat.slug === categorySlug) || category;
      }
    } catch { /* keep requested-locale empty state */ }
  }

  try {
    const trendingResult = await getTrendingArticles(5, effectiveLocale);
    trendingArticles = trendingResult || [];
  } catch { /* */ }

  const heroArticle = articles[0];
  const gridArticles = articles.slice(1);
  const totalPages = Math.ceil(totalItems / itemsPerPage);
  const l = labels[effectiveLocale];
  const sectionColor = getSectionColor(category.slug);

  return (
    <div className="min-h-screen bg-[var(--color-surface)] dark:bg-[var(--color-surface-dark)]">
      {/* Push per-locale category alternate URLs into context so the global LanguageSwitcher
          emits locale-correct hrefs that consume category.translatedSlugs (T60.6 Cluster B). */}
      <LocaleContextSetter localeAlternates={buildCategoryLocaleAlternates(category)} />

      <main className="max-w-[1440px] mx-auto px-4 lg:px-6 py-6">
        {isLangFallback && (
          <LocaleFallbackNotice
            requestedLocale={requestedLocale}
            effectiveLocale={effectiveLocale}
            translationPending={categoryFallback.translationPending}
          />
        )}

        {/* Layout: sidebar LEFT (1/3) + main RIGHT (2/3) — matching homepage */}
        <div className="flex flex-col-reverse lg:flex-row gap-8">

          {/* ── Sidebar (LEFT on desktop, BELOW on mobile) ── */}
          <aside className="w-full lg:w-1/3 lg:pr-8 lg:pt-14">
            <div className="sticky top-24 space-y-8">
              {trendingArticles.length > 0 && (
                <MostPopularWidget articles={trendingArticles} locale={effectiveLocale} label={l.mostRead} />
              )}
              <InTrendWidget locale={effectiveLocale} label={l.inTrend} />
              <AdPlaceholder label={l.ad} />
            </div>
          </aside>

          {/* ── Main content (RIGHT on desktop) ── */}
          <div className="w-full lg:w-2/3 overflow-hidden">
            {articles.length > 0 ? (
              <div className="space-y-6">
                {/* Section header — matches homepage CategorySection SectionHeader */}
                <div className="flex items-center gap-4">
                  <h1
                    className="font-sans font-bold text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] whitespace-nowrap border-b-2 pb-1"
                    style={{ fontSize: 'var(--font-size-2xl)', borderBottomColor: sectionColor }}
                  >
                    {category.title}
                  </h1>
                  <div className="flex-1 h-px bg-[var(--color-border)] dark:bg-[var(--color-border-dark)]" />
                </div>

                {/* Hero article — full-width overlay */}
                {heroArticle && (
                  <HeroCard article={heroArticle} locale={effectiveLocale} />
                )}

                {/* Articles grid — 3 columns */}
                {gridArticles.length > 0 && (
                  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 pt-3">
                    {gridArticles.map((article) => (
                      <GridArticleCard key={article.id} article={article} locale={effectiveLocale} />
                    ))}
                  </div>
                )}

                {/* Pagination */}
                {totalPages > 1 && (
                  <Pagination
                    currentPage={currentPage}
                    totalPages={totalPages}
                    locale={effectiveLocale}
                    categorySlug={category.slug}
                    labels={{ prev: l.prev, next: l.next }}
                  />
                )}
              </div>
            ) : (
              <EmptyState label={l.noArticles} description={l.noArticlesDesc} />
            )}
          </div>
        </div>
      </main>
    </div>
  );
}

/* ================================================================== */
/*  Hero Card — overlay on image (matches LatestNewsSection)           */
/* ================================================================== */

function HeroCard({ article, locale }: { article: Article; locale: Locale }) {
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage ? getThumbnailByProfile(featuredImage, 'hero_small') : null;
  const imageToUse = thumbnail || featuredImage;
  const articleUrl = buildArticleUrl(article, locale);
  const categoryTitle = getCatTitle(article.category);

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
          <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/40 to-transparent" />
        </div>

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
          {categoryTitle && (
            <span className="inline-block mt-2 text-xs font-semibold tracking-wider uppercase font-sans text-gray-100">
              {categoryTitle}
            </span>
          )}
        </div>
      </Link>
    </article>
  );
}

/* ================================================================== */
/*  Grid Article Card (matches LatestNewsSection GridArticleCard)       */
/* ================================================================== */

function GridArticleCard({ article, locale }: { article: Article; locale: Locale }) {
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage ? getThumbnailByProfile(featuredImage, 'card_medium') : null;
  const imageToUse = thumbnail || featuredImage;
  const articleUrl = buildArticleUrl(article, locale);
  const categorySlug = getCategorySlugFromArticle(article.category);
  const categoryTitle = getCatTitle(article.category);
  const catSectionColor = getSectionColor(categorySlug);

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

      {/* Category badge */}
      {categoryTitle && (
        <span
          className="inline-block mt-2 text-xs font-semibold tracking-wider uppercase font-sans"
          style={{ color: catSectionColor }}
        >
          {categoryTitle}
        </span>
      )}
    </article>
  );
}

/* ================================================================== */
/*  Most Popular — gray header + numbered list (matches homepage)      */
/* ================================================================== */

function MostPopularWidget({
  articles,
  locale,
  label,
}: {
  articles: Array<{ id: number; title: string | null; slug: string | null; category: { slug: string } | null }>;
  locale: string;
  label: string;
}) {
  return (
    <div className="bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)]">
      <div className="p-4 bg-[var(--color-surface-sunken)] dark:bg-[var(--color-surface-sunken-dark)]">
        <h2 className="font-sans font-bold text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)]" style={{ fontSize: 'var(--font-size-lg)' }}>
          {label}
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

/* ================================================================== */
/*  Pagination                                                         */
/* ================================================================== */

function Pagination({ currentPage, totalPages, locale, categorySlug, labels: pLabels }: {
  currentPage: number;
  totalPages: number;
  locale: string;
  categorySlug: string;
  labels: { prev: string; next: string };
}) {
  const pages = generatePageNumbers(currentPage, totalPages);

  return (
    <nav className="flex items-center justify-center gap-2 pt-8" aria-label="Pagination">
      {currentPage > 1 && (
        <Link
          href={`/${locale}/${categorySlug}?page=${currentPage - 1}`}
          className="group flex items-center gap-2 px-4 py-2.5 bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)] border border-[var(--color-border)] dark:border-[var(--color-border-dark)] text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] rounded-lg hover:bg-[var(--color-surface-sunken)] dark:hover:bg-[var(--color-surface-sunken-dark)] transition-all duration-200"
        >
          <svg className="w-4 h-4 transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
          </svg>
          <span className="text-sm font-medium font-sans">{pLabels.prev}</span>
        </Link>
      )}

      <div className="flex items-center gap-1">
        {pages.map((pageNum, idx) => (
          pageNum === '...' ? (
            <span key={`ellipsis-${idx}`} className="px-2 text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)] font-sans">...</span>
          ) : (
            <Link
              key={pageNum}
              href={`/${locale}/${categorySlug}?page=${pageNum}`}
              className={`w-10 h-10 flex items-center justify-center rounded-lg text-sm font-medium font-sans transition-all duration-200 ${
                currentPage === pageNum
                  ? 'bg-[var(--color-text-primary)] dark:bg-[var(--color-text-primary-dark)] text-[var(--color-surface)] dark:text-[var(--color-surface-dark)]'
                  : 'bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)] border border-[var(--color-border)] dark:border-[var(--color-border-dark)] text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] hover:text-[var(--color-accent)] hover:border-[var(--color-accent)]'
              }`}
            >
              {pageNum}
            </Link>
          )
        ))}
      </div>

      {currentPage < totalPages && (
        <Link
          href={`/${locale}/${categorySlug}?page=${currentPage + 1}`}
          className="group flex items-center gap-2 px-4 py-2.5 bg-[var(--color-text-primary)] dark:bg-[var(--color-text-primary-dark)] text-[var(--color-surface)] dark:text-[var(--color-surface-dark)] rounded-lg hover:opacity-90 transition-all duration-200"
        >
          <span className="text-sm font-medium font-sans">{pLabels.next}</span>
          <svg className="w-4 h-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
          </svg>
        </Link>
      )}
    </nav>
  );
}

/* ================================================================== */
/*  Empty State                                                        */
/* ================================================================== */

function EmptyState({ label, description }: { label: string; description: string }) {
  return (
    <div className="text-center py-16 bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)] rounded-[var(--radius-card)] border border-[var(--color-border)] dark:border-[var(--color-border-dark)]">
      <div className="w-16 h-16 mx-auto mb-5 rounded-full bg-[var(--color-skeleton)] dark:bg-[var(--color-skeleton-dark)] flex items-center justify-center">
        <svg className="w-8 h-8 text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
        </svg>
      </div>
      <h3 className="font-sans font-bold text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] mb-2" style={{ fontSize: 'var(--font-size-lg)' }}>{label}</h3>
      <p className="text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)] font-sans" style={{ fontSize: 'var(--font-size-sm)' }}>{description}</p>
    </div>
  );
}

/* ================================================================== */
/*  In Trend Widget (static data — matches homepage)                   */
/* ================================================================== */

function InTrendWidget({ locale, label }: { locale: string; label: string }) {
  const items = TRENDING_PLACEHOLDER[locale] || TRENDING_PLACEHOLDER.ro;

  return (
    <div>
      <h2
        className="font-sans font-bold text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] mb-4 pb-2 border-b-2 border-[var(--color-accent)]"
        style={{ fontSize: 'var(--font-size-lg)' }}
      >
        {label}
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

/* ================================================================== */
/*  Ad Placeholder                                                     */
/* ================================================================== */

function AdPlaceholder({ label }: { label: string }) {
  return (
    <div className="text-center">
      <span className="text-xs text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)] uppercase tracking-wider font-sans block mb-2">
        {label}
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
/*  Helpers                                                            */
/* ================================================================== */

function generatePageNumbers(current: number, total: number): (number | string)[] {
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
  const pages: (number | string)[] = [1];
  if (current > 3) pages.push('...');
  for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
    if (!pages.includes(i)) pages.push(i);
  }
  if (current < total - 2) pages.push('...');
  if (!pages.includes(total)) pages.push(total);
  return pages;
}
