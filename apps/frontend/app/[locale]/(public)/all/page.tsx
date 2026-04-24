/**
 * All Articles Page — design-aligned with CategoryPage
 * Layout: sidebar LEFT (1/3) + main RIGHT (2/3)
 * Uses design system tokens exclusively.
 * Route: /{locale}/all
 */

import type { Metadata } from 'next';
import Link from 'next/link';
import Image from 'next/image';
import { fetchCategories } from '@/lib/api/categories';
import { buildImageUrl, getThumbnailByProfile, getFeaturedImage } from '@/lib/api/important-articles';
import { buildArticleUrl } from '@/lib/utils/url-builder';
import { getSectionColor, getCategorySlugFromArticle } from '@/components/cards/utils';
import type { Locale } from '@/lib/types';
import type { Article, Category } from '@/lib/types/article';

export const dynamic = 'force-dynamic';
export const revalidate = 300;

/* ================================================================== */
/*  Localized labels                                                   */
/* ================================================================== */

const labels = {
  ro: { title: 'Toate articolele', subtitle: 'articole publicate', inTrend: 'In trend', ad: 'Publicitate', noArticles: 'Niciun articol gasit', noArticlesDesc: 'Nu exista articole publicate momentan.', prev: 'Inapoi', next: 'Urmatoarea', all: 'Toate' },
  en: { title: 'All articles', subtitle: 'published articles', inTrend: 'Trending', ad: 'Advertisement', noArticles: 'No articles found', noArticlesDesc: 'There are no published articles yet.', prev: 'Previous', next: 'Next', all: 'All' },
  ru: { title: 'Vse stati', subtitle: 'opublikovannyh statej', inTrend: 'V trende', ad: 'Reklama', noArticles: 'Statji ne najdeny', noArticlesDesc: 'Opublikovannyh statej poka net.', prev: 'Nazad', next: 'Dalee', all: 'Vse' },
} as const;

/* Static placeholder articles for "In Trend" */
const TRENDING_PLACEHOLDER: Record<string, Array<{ id: number; title: string; category: string; categoryColor: string }>> = {
  ro: [
    { id: 1001, title: 'Alegerile prezidentiale 2025: ultimele sondaje', category: 'Politica', categoryColor: 'var(--color-section-politics)' },
    { id: 1002, title: 'Cursul valutar: leul moldovenesc se stabilizeaza', category: 'Economie', categoryColor: 'var(--color-section-economy)' },
    { id: 1003, title: 'Festival international de film la Chisinau', category: 'Cultura', categoryColor: 'var(--color-section-culture)' },
    { id: 1004, title: 'Reforma sistemului educational: ce se schimba', category: 'Societate', categoryColor: 'var(--color-section-society)' },
    { id: 1005, title: 'Integrarea europeana: noi pasi spre aderare', category: 'Externe', categoryColor: 'var(--color-section-world)' },
  ],
  en: [
    { id: 1001, title: 'Presidential elections 2025: latest polls', category: 'Politics', categoryColor: 'var(--color-section-politics)' },
    { id: 1002, title: 'Exchange rate: Moldovan leu stabilizes', category: 'Economy', categoryColor: 'var(--color-section-economy)' },
    { id: 1003, title: 'International film festival in Chisinau', category: 'Culture', categoryColor: 'var(--color-section-culture)' },
    { id: 1004, title: 'Education system reform: what changes', category: 'Society', categoryColor: 'var(--color-section-society)' },
    { id: 1005, title: 'European integration: new steps toward accession', category: 'World', categoryColor: 'var(--color-section-world)' },
  ],
  ru: [
    { id: 1001, title: 'Prezidentskie vybory 2025: poslednie oprosy', category: 'Politika', categoryColor: 'var(--color-section-politics)' },
    { id: 1002, title: 'Kurs valjut: moldavskij lej stabilizirujetsja', category: 'Ekonomika', categoryColor: 'var(--color-section-economy)' },
    { id: 1003, title: 'Mezhdunarodnyj kinofestival v Kishineve', category: 'Kultura', categoryColor: 'var(--color-section-culture)' },
    { id: 1004, title: 'Reforma sistemy obrazovanija: chto menjaetsja', category: 'Obschestvo', categoryColor: 'var(--color-section-society)' },
    { id: 1005, title: 'Evropejskaja integracija: novye shagi', category: 'Mir', categoryColor: 'var(--color-section-world)' },
  ],
};

/* ================================================================== */
/*  Metadata                                                           */
/* ================================================================== */

interface AllArticlesPageProps {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{ page?: string; category?: string }>;
}

export async function generateMetadata({ params }: AllArticlesPageProps): Promise<Metadata> {
  const { locale } = await params;
  const l = labels[locale as keyof typeof labels] || labels.ro;
  return {
    title: `${l.title} | Deschide News`,
    description: l.subtitle,
    alternates: {
      canonical: `/${locale}/all`,
      languages: { 'ro-MD': '/ro/all', ro: '/ro/all', en: '/en/all', ru: '/ru/all', 'x-default': '/ro/all' },
    },
  };
}

/* ================================================================== */
/*  Page                                                               */
/* ================================================================== */

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

function getCatTitle(category: Category | string): string {
  return typeof category === 'object' && category?.title ? category.title : '';
}

export default async function AllArticlesPage({ params, searchParams }: AllArticlesPageProps) {
  const { locale } = await params;
  const { page: pageParam, category: categoryFilter } = await searchParams;

  const currentPage = pageParam ? parseInt(pageParam, 10) : 1;
  const itemsPerPage = 10;
  const l = labels[locale as keyof typeof labels] || labels.ro;

  // Fetch articles, categories, and trending in parallel
  let articles: Article[] = [];
  let totalItems = 0;
  let categories: Category[] = [];
  const queryParams = new URLSearchParams({
    page: currentPage.toString(),
    itemsPerPage: itemsPerPage.toString(),
    'order[publishedAt]': 'DESC',
  });
  if (categoryFilter) {
    queryParams.set('categoryId', categoryFilter);
  }

  const [articlesResult, categoriesResult] = await Promise.allSettled([
    fetch(`${API_BASE_URL}/api/articles?${queryParams.toString()}`, {
      headers: { 'Content-Type': 'application/json', 'Accept-Language': locale },
      next: { revalidate: 300 },
    }).then(r => r.ok ? r.json() : null),
    fetchCategories(locale),
  ]);

  if (articlesResult.status === 'fulfilled' && articlesResult.value) {
    articles = articlesResult.value.member || [];
    totalItems = articlesResult.value.totalItems || 0;
  }
  if (categoriesResult.status === 'fulfilled') {
    categories = categoriesResult.value.member || [];
  }

  const totalPages = Math.ceil(totalItems / itemsPerPage);

  return (
    <div className="min-h-screen bg-[var(--color-surface)] dark:bg-[var(--color-surface-dark)]">
      <main className="max-w-[1440px] mx-auto px-4 lg:px-6 py-6">
        {/* Layout: sidebar LEFT (1/3) + main RIGHT (2/3) */}
        <div className="flex flex-col-reverse lg:flex-row gap-8">

          {/* Sidebar (LEFT on desktop, BELOW on mobile) */}
          <aside className="w-full lg:w-1/3 lg:pr-8 lg:pt-14">
            <div className="sticky top-24 space-y-8">
              <InTrendWidget locale={locale} label={l.inTrend} />
              <AdPlaceholder label={l.ad} />
            </div>
          </aside>

          {/* Main content (RIGHT on desktop) */}
          <div className="w-full lg:w-2/3 overflow-hidden">
            {/* Section header */}
            <div className="flex items-center gap-4 mb-6">
              <h1
                className="font-sans font-bold text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] whitespace-nowrap border-b-2 pb-1"
                style={{ fontSize: 'var(--font-size-2xl)', borderBottomColor: 'var(--color-accent)' }}
              >
                {l.title}
              </h1>
              <div className="flex-1 h-px bg-[var(--color-border)] dark:bg-[var(--color-border-dark)]" />
            </div>

            {/* Category filter pills */}
            {categories.length > 0 && (
              <div className="flex flex-wrap gap-2 mb-6">
                <Link
                  href={`/${locale}/all`}
                  className={`px-3 py-1.5 rounded-full text-xs font-semibold tracking-wider uppercase font-sans transition-colors ${
                    !categoryFilter
                      ? 'bg-[var(--color-text-primary)] dark:bg-[var(--color-text-primary-dark)] text-[var(--color-surface)] dark:text-[var(--color-surface-dark)]'
                      : 'bg-[var(--color-surface-sunken)] dark:bg-[var(--color-surface-sunken-dark)] text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)]'
                  }`}
                >
                  {l.all}
                </Link>
                {categories.map((cat) => {
                  const catColor = getSectionColor(cat.slug);
                  const isActive = categoryFilter === cat.id.toString();
                  return (
                    <Link
                      key={cat.id}
                      href={`/${locale}/all?category=${cat.id}`}
                      className={`px-3 py-1.5 rounded-full text-xs font-semibold tracking-wider uppercase font-sans transition-colors ${
                        isActive
                          ? 'text-white'
                          : 'bg-[var(--color-surface-sunken)] dark:bg-[var(--color-surface-sunken-dark)] text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)]'
                      }`}
                      style={isActive ? { backgroundColor: catColor } : undefined}
                    >
                      {cat.title}
                    </Link>
                  );
                })}
              </div>
            )}

            {articles.length > 0 ? (
              <div className="space-y-6">
                {/* Articles grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                  {articles.map((article) => (
                    <GridArticleCard key={article.id} article={article} locale={locale as Locale} />
                  ))}
                </div>

                {/* Pagination */}
                {totalPages > 1 && (
                  <Pagination
                    currentPage={currentPage}
                    totalPages={totalPages}
                    locale={locale}
                    categoryFilter={categoryFilter}
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
/*  Grid Article Card — matches category page                          */
/* ================================================================== */

function GridArticleCard({ article, locale }: { article: Article; locale: Locale }) {
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage ? getThumbnailByProfile(featuredImage, 'card_medium') : null;
  const imageToUse = thumbnail || featuredImage;
  const articleUrl = buildArticleUrl(article, locale);
  const categorySlug = getCategorySlugFromArticle(article.category);
  const categoryTitle = getCatTitle(article.category);
  const sectionColor = getSectionColor(categorySlug);

  return (
    <article className="group">
      <Link href={articleUrl} className="block">
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

        <h3 className="font-sans font-bold leading-tight line-clamp-2 text-base lg:text-lg text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] group-hover:text-[var(--color-accent)] transition-colors duration-200">
          {article.title}
        </h3>

        {article.lead && (
          <p className="mt-1 text-sm text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] line-clamp-2 font-serif">
            {article.lead}
          </p>
        )}
      </Link>

      {categoryTitle && (
        <span
          className="inline-block mt-2 text-xs font-semibold tracking-wider uppercase font-sans"
          style={{ color: sectionColor }}
        >
          {categoryTitle}
        </span>
      )}
    </article>
  );
}

/* ================================================================== */
/*  Pagination                                                         */
/* ================================================================== */

function Pagination({ currentPage, totalPages, locale, categoryFilter, labels: pLabels }: {
  currentPage: number;
  totalPages: number;
  locale: string;
  categoryFilter?: string;
  labels: { prev: string; next: string };
}) {
  const pages = generatePageNumbers(currentPage, totalPages);
  const buildHref = (page: number) => {
    const params = new URLSearchParams();
    params.set('page', page.toString());
    if (categoryFilter) params.set('category', categoryFilter);
    return `/${locale}/all?${params.toString()}`;
  };

  return (
    <nav className="flex items-center justify-center gap-2 pt-8" aria-label="Pagination">
      {currentPage > 1 && (
        <Link
          href={buildHref(currentPage - 1)}
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
              href={buildHref(pageNum as number)}
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
          href={buildHref(currentPage + 1)}
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
/*  In Trend Widget                                                    */
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
