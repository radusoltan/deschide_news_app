import { notFound } from 'next/navigation';
import { fetchArticlesByCategory } from '@/lib/api/articles';
import { fetchCategories } from '@/lib/api/categories';
import CategoryHeroArticle from '@/components/CategoryHeroArticle';
import ArticleCard from '@/components/ArticleCard';
import MostPopular from '@/components/MostPopular';
import { buildLocalizedUrl } from '@/lib/utils/url-builder';
import type { Locale } from '@/lib/types';

export const dynamic = 'force-dynamic';
export const revalidate = 120; // Revalidate every 2 minutes

interface CategoryPageProps {
  params: Promise<{
    locale: string;
    slug: string;
  }>;
  searchParams: Promise<{
    page?: string;
  }>;
}

export default async function CategoryPage({ params, searchParams }: CategoryPageProps) {
  const { locale: rawLocale, slug } = await params;
  const locale = rawLocale as Locale;
  const { page: pageParam } = await searchParams;

  const currentPage = pageParam ? parseInt(pageParam, 10) : 1;
  const itemsPerPage = 7; // 1 hero + 6 grid articles

  // Fetch all categories to find the one matching the slug
  let category: any = null;
  try {
    const categoriesResponse = await fetchCategories(locale);
    const categories = categoriesResponse.member || [];
    category = categories.find((cat: any) => cat.slug === slug);
  } catch (error) {
    console.error('Failed to fetch categories:', error);
  }

  if (!category) {
    notFound();
  }

  // Fetch articles for this category
  let articles: any[] = [];
  let totalItems = 0;
  try {
    const response = await fetchArticlesByCategory(category.id, locale, itemsPerPage);
    articles = response.member || [];
    totalItems = response.totalItems || 0;
  } catch (error) {
    console.error('Failed to fetch articles:', error);
    articles = [];
  }

  const heroArticle = articles[0];
  const gridArticles = articles.slice(1, 7);
  const totalPages = Math.ceil(totalItems / itemsPerPage);

  return (
    <>
      {/* Category Section - Premium Editorial Design */}
      <div className="bg-white py-8">
        <div className="xl:container mx-auto px-4 sm:px-6 xl:px-8">
          <div className="flex flex-row flex-wrap">
            {/* Left - Main Content */}
            <div className="flex-shrink max-w-full w-full lg:w-2/3 overflow-hidden lg:pr-8">
              {/* Premium Category Header with Oxford Blue Accent Bar */}
              <div className="w-full mb-8">
                <div className="relative">
                  {/* Tomato Accent Stripe */}
                  <div className="absolute left-0 top-0 bottom-0 w-1 bg-brand-tomato"></div>

                  {/* Category Title - League Spartan Bold, UPPERCASE */}
                  <h1 className="pl-6 text-4xl md:text-5xl font-heading text-brand-oxford-900 tracking-tight">
                    {category.title}
                  </h1>

                  {/* Subtle bottom border */}
                  <div className="mt-4 h-px bg-gradient-to-r from-brand-oxford-900/20 via-brand-oxford-900/10 to-transparent"></div>
                </div>
              </div>

              <div className="flex flex-row flex-wrap -mx-3">
                {/* Hero Article */}
                {heroArticle && (
                  <CategoryHeroArticle article={heroArticle} locale={locale} />
                )}

                {/* Grid Articles - Premium Card Layout */}
                <div className="w-full px-3">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    {gridArticles.map((article, index) => (
                      <div
                        key={article.id}
                        className="animate-fade-in-up"
                        style={{ animationDelay: `${index * 0.1}s` }}
                      >
                        <ArticleCard
                          article={article}
                          locale={locale}
                          thumbnailProfile="article_card"
                        />
                      </div>
                    ))}
                  </div>
                </div>

                {/* No Articles Message */}
                {articles.length === 0 && (
                  <div className="flex-shrink max-w-full w-full px-3 py-16 text-center">
                    <div className="max-w-md mx-auto">
                      <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-brand-oxford-50 flex items-center justify-center">
                        <svg className="w-8 h-8 text-brand-oxford-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                        </svg>
                      </div>
                      <p className="text-brand-oxford-900/70 text-lg font-body">
                        No articles found in this category.
                      </p>
                    </div>
                  </div>
                )}
              </div>

              {/* Premium Pagination with Oxford Blue Buttons */}
              {totalPages > 1 && (
                <div className="mt-12 px-3">
                  <div className="flex items-center justify-between border-t border-brand-oxford-900/10 pt-6">
                    <div className="flex-1">
                      <p className="text-brand-oxford-900/60 font-body text-sm">
                        Page <span className="font-semibold text-brand-oxford-900">{currentPage}</span> of <span className="font-semibold text-brand-oxford-900">{totalPages}</span>
                      </p>
                    </div>

                    <div className="flex gap-3">
                      {currentPage > 1 && (
                        <a
                          href={`${buildLocalizedUrl(`/category/${slug}`, locale as Locale)}?page=${currentPage - 1}`}
                          className="group inline-flex items-center gap-2 px-6 py-3 bg-brand-oxford-900 text-white font-medium rounded-lg hover:bg-brand-oxford-800 transition-all duration-300 shadow-md hover:shadow-lg hover:-translate-y-0.5"
                        >
                          <svg className="w-4 h-4 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                          </svg>
                          Previous
                        </a>
                      )}
                      {currentPage < totalPages && (
                        <a
                          href={`${buildLocalizedUrl(`/category/${slug}`, locale as Locale)}?page=${currentPage + 1}`}
                          className="group inline-flex items-center gap-2 px-6 py-3 bg-brand-oxford-900 text-white font-medium rounded-lg hover:bg-brand-oxford-800 transition-all duration-300 shadow-md hover:shadow-lg hover:-translate-y-0.5"
                        >
                          Next
                          <svg className="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                          </svg>
                        </a>
                      )}
                    </div>
                  </div>
                </div>
              )}
            </div>

            {/* Right Sidebar - Most Popular */}
            <div className="flex-shrink max-w-full w-full lg:w-1/3 lg:pt-0 order-first lg:order-last mt-8 lg:mt-0">
              <div className="lg:sticky lg:top-4">
                <MostPopular locale={locale} categoryId={category.id} limit={5} />

                {/* Advertisement Placeholder - Premium Style */}
                <div className="mt-8">
                  <div className="text-center">
                    <span className="text-xs font-medium text-brand-oxford-900/40 uppercase tracking-wider">Advertisement</span>
                    <div className="mt-3 bg-gradient-to-br from-brand-oxford-50 to-brand-oxford-100/50 rounded-lg h-64 flex items-center justify-center border border-brand-oxford-900/10">
                      <span className="text-brand-oxford-900/30 font-medium">Ad Space 250x250</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
