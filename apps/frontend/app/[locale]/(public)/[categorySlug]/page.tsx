/**
 * Category Page - Premium Editorial Design
 * Displays articles within a specific category
 * Route: /{locale}/{categorySlug}
 */

import { notFound } from 'next/navigation';
import type { Metadata } from 'next';
import Link from 'next/link';
import Image from 'next/image';
import { fetchCategories } from '@/lib/api/categories';
import { fetchArticlesByCategory } from '@/lib/api/articles';
import { isReservedSlug } from '@/lib/constants/reserved-slugs';
import { generateCategoryMetadata } from '@/lib/seo/meta-tags';
import { buildImageUrl, getThumbnailByProfile, getFeaturedImage } from '@/lib/api/important-articles';
import { buildArticleUrl } from '@/lib/utils/url-builder';
import MostPopular from '@/components/MostPopular';
import ArticleCard from '@/components/ArticleCard';
import type { Locale } from '@/lib/types';
import type { Article, Category } from '@/lib/types/article';

export const dynamic = 'force-dynamic';
export const revalidate = 120;

// Generate dynamic SEO metadata
export async function generateMetadata({
  params,
}: CategoryPageProps): Promise<Metadata> {
  const { locale, categorySlug } = await params;
  const validLocale = (['ro', 'en', 'ru'].includes(locale) ? locale : 'ro') as Locale;

  if (isReservedSlug(categorySlug)) {
    return { title: 'Page Not Found' };
  }

  try {
    const categoriesResponse = await fetchCategories(validLocale);
    const categories = categoriesResponse.member || [];
    const category = categories.find((cat: Category) => cat.slug === categorySlug);

    if (!category) {
      return { title: 'Category Not Found' };
    }

    return generateCategoryMetadata(category.title, category.slug, validLocale, category.description);
  } catch {
    return { title: 'Category | Deschide News' };
  }
}

interface CategoryPageProps {
  params: Promise<{ locale: string; categorySlug: string }>;
  searchParams: Promise<{ page?: string }>;
}

function getCategoryTitle(category: Category | string): string {
  return typeof category === 'object' && category?.title ? category.title : 'Categorie';
}

function getExcerpt(article: Article, maxLength: number = 120): string {
  if (article.lead) {
    return article.lead.length > maxLength ? article.lead.substring(0, maxLength) + '...' : article.lead;
  }
  if (article.content) {
    const text = article.content.replace(/<[^>]*>/g, '');
    return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
  }
  return '';
}

export default async function CategoryPage({ params, searchParams }: CategoryPageProps) {
  const { locale, categorySlug } = await params;
  const { page: pageParam } = await searchParams;

  if (isReservedSlug(categorySlug)) {
    notFound();
  }

  // Fetch category
  let category: Category | null = null;
  try {
    const categoriesResponse = await fetchCategories(locale);
    const categories = categoriesResponse.member || [];
    category = categories.find((cat: Category) => cat.slug === categorySlug) || null;
  } catch {
    // Category fetch failed
  }

  if (!category) {
    notFound();
  }

  const currentPage = pageParam ? parseInt(pageParam, 10) : 1;
  const itemsPerPage = 10;

  // Fetch articles
  let articles: Article[] = [];
  let totalItems = 0;
  try {
    const response = await fetchArticlesByCategory(category.id, locale, itemsPerPage);
    articles = response.member || [];
    totalItems = response.totalItems || 0;
  } catch {
    articles = [];
  }

  const heroArticle = articles[0];
  const gridArticles = articles.slice(1);
  const totalPages = Math.ceil(totalItems / itemsPerPage);

  return (
    <div className="min-h-screen bg-white">
      {/* Main Content */}
      <main className="xl:container mx-auto px-3 sm:px-4 xl:px-2 py-6">
        <div className="flex flex-row flex-wrap">
          {/* Articles Section - 2/3 width */}
          <div className="flex-shrink max-w-full w-full lg:w-2/3 overflow-hidden">
            {articles.length > 0 ? (
              <div className="space-y-6">
                {/* Section Header - matching homepage pattern */}
                <div className="w-full">
                  <h1 className="text-brand-oxford-900 text-2xl font-heading uppercase">
                    <span className="inline-block h-5 border-l-3 border-brand-tomato-500 mr-2"></span>
                    {category.title}
                  </h1>
                </div>

                {/* Hero Article */}
                {heroArticle && (
                  <HeroCard article={heroArticle} locale={locale as Locale} />
                )}

                {/* Articles Grid - Simple 3-column layout */}
                {gridArticles.length > 0 && (
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-x-4 gap-y-6">
                    {gridArticles.map((article) => (
                      <ArticleCard
                        key={article.id}
                        article={article}
                        locale={locale as Locale}
                        thumbnailProfile="article_card"
                      />
                    ))}
                  </div>
                )}

                {/* Pagination */}
                {totalPages > 1 && (
                  <Pagination
                    currentPage={currentPage}
                    totalPages={totalPages}
                    locale={locale}
                    categorySlug={category.slug}
                  />
                )}
              </div>
            ) : (
              <EmptyState />
            )}
          </div>

          {/* Sidebar - 1/3 width */}
          <aside className="flex-shrink max-w-full w-full lg:w-1/3 lg:pl-8 lg:pt-14 lg:pb-8 order-first lg:order-last">
            <div className="sticky top-24 space-y-6">
              <MostPopular locale={locale as Locale} categoryId={category.id} limit={5} />
              <AdPlaceholder />
            </div>
          </aside>
        </div>
      </main>
    </div>
  );
}

/* ========== Components ========== */

function HeroCard({ article, locale }: { article: Article; locale: Locale }) {
  const featuredImage = getFeaturedImage(article.articleImages || []);
  const thumbnail = featuredImage ? getThumbnailByProfile(featuredImage, 'article_hero') : null;
  const imageToUse = thumbnail || featuredImage;
  const articleUrl = buildArticleUrl(article, locale);
  const excerpt = getExcerpt(article, 200);

  return (
    <article className="group">
      <Link href={articleUrl} className="block relative rounded overflow-hidden shadow-lg hover:shadow-xl transition-shadow duration-300">
        {/* Image Container */}
        <div className="relative aspect-[2/1] md:aspect-[21/9] bg-brand-oxford-100">
          {imageToUse ? (
            <Image
              className="object-cover transition-transform duration-500 group-hover:scale-105"
              src={buildImageUrl(imageToUse.path)}
              alt={featuredImage?.alt || article.title}
              fill
              sizes="(max-width: 768px) 100vw, 900px"
              priority
            />
          ) : (
            <div className="absolute inset-0 bg-gradient-to-br from-brand-oxford-200 to-brand-oxford-100 flex items-center justify-center">
              <svg className="w-16 h-16 text-brand-oxford-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
          )}

          {/* Gradient Overlay */}
          <div className="absolute inset-0 bg-gradient-to-t from-brand-oxford-900/90 via-brand-oxford-900/40 to-transparent" />

          {/* Content */}
          <div className="absolute bottom-0 left-0 right-0 p-6 md:p-8">
            <span className="inline-block px-3 py-1 bg-brand-tomato text-white text-xs font-bold uppercase tracking-wider rounded-full mb-3">
              {getCategoryTitle(article.category)}
            </span>
            <h2 className="text-2xl md:text-3xl lg:text-4xl font-heading font-bold text-white leading-tight mb-2 group-hover:text-brand-mindaro-400 transition-colors">
              {article.title}
            </h2>
            <p className="hidden md:block text-white/70 text-base max-w-2xl line-clamp-2">
              {excerpt}
            </p>
            <div className="flex items-center gap-3 mt-3 text-white/50 text-sm">
              {article.publishedAt && (
                <time dateTime={article.publishedAt}>
                  {new Date(article.publishedAt).toLocaleDateString(locale, { day: 'numeric', month: 'long', year: 'numeric' })}
                </time>
              )}
              {article.viewCount && (
                <>
                  <span className="w-1 h-1 rounded-full bg-white/30" />
                  <span>{article.viewCount.toLocaleString()} vizualizări</span>
                </>
              )}
            </div>
          </div>
        </div>
      </Link>
    </article>
  );
}

function Pagination({ currentPage, totalPages, locale, categorySlug }: {
  currentPage: number;
  totalPages: number;
  locale: string;
  categorySlug: string;
}) {
  const pages = generatePageNumbers(currentPage, totalPages);

  return (
    <nav className="flex items-center justify-center gap-2 pt-8" aria-label="Paginare">
      {currentPage > 1 && (
        <Link
          href={`/${locale}/${categorySlug}?page=${currentPage - 1}`}
          className="group flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-brand-oxford-900 rounded-lg hover:bg-brand-oxford-900 hover:text-white hover:border-brand-oxford-900 transition-all duration-200 shadow-sm"
        >
          <svg className="w-4 h-4 transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
          </svg>
          <span className="text-sm font-medium">Înapoi</span>
        </Link>
      )}

      <div className="flex items-center gap-1">
        {pages.map((pageNum, idx) => (
          pageNum === '...' ? (
            <span key={`ellipsis-${idx}`} className="px-2 text-gray-400">...</span>
          ) : (
            <Link
              key={pageNum}
              href={`/${locale}/${categorySlug}?page=${pageNum}`}
              className={`w-10 h-10 flex items-center justify-center rounded-lg text-sm font-medium transition-all duration-200 ${
                currentPage === pageNum
                  ? 'bg-brand-oxford-900 text-white shadow-md'
                  : 'bg-white border border-gray-200 text-brand-oxford-900 hover:border-brand-tomato hover:text-brand-tomato'
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
          className="group flex items-center gap-2 px-4 py-2.5 bg-brand-oxford-900 text-white rounded-lg hover:bg-brand-oxford-800 transition-all duration-200 shadow-md"
        >
          <span className="text-sm font-medium">Următoarea</span>
          <svg className="w-4 h-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
          </svg>
        </Link>
      )}
    </nav>
  );
}

function EmptyState() {
  return (
    <div className="text-center py-16 bg-white rounded-2xl border border-gray-100 shadow-sm">
      <div className="w-16 h-16 mx-auto mb-5 rounded-full bg-gray-100 flex items-center justify-center">
        <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
        </svg>
      </div>
      <h3 className="text-lg font-bold text-brand-oxford-900 mb-2">Niciun articol găsit</h3>
      <p className="text-gray-500 text-sm">Nu există articole în această categorie momentan.</p>
    </div>
  );
}

function AdPlaceholder() {
  return (
    <div className="bg-white rounded-xl p-5 border border-gray-100 shadow-sm">
      <p className="text-xs text-gray-400 uppercase tracking-wider mb-3 text-center">Publicitate</p>
      <div className="aspect-square bg-gray-50 rounded-lg flex items-center justify-center border border-dashed border-gray-200">
        <span className="text-sm text-gray-400">300×300</span>
      </div>
    </div>
  );
}

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
