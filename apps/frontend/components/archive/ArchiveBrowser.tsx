'use client';

/**
 * ArchiveBrowser Component
 * Main archive browsing interface with filters, pagination, and article grid
 * Features a vintage newspaper aesthetic with sepia/amber tones
 */

import { useState, useEffect, useCallback, Suspense } from 'react';
import { useSearchParams, useRouter, usePathname } from 'next/navigation';
import YearFilter from './YearFilter';
import CategoryFilter from './CategoryFilter';
import ArchiveArticleCard from './ArchiveArticleCard';
import type { Locale } from '@/lib/types';

interface YearData {
  year: number;
  count: number;
}

interface ArchiveStats {
  total: number;
  byYear: YearData[];
  byCategory: { id: number; name: string; count: number }[];
}

interface ArchiveBrowserProps {
  locale: Locale;
  initialStats?: ArchiveStats;
}

const translations = {
  ro: {
    title: 'Arhiva de Știri',
    subtitle: 'Explorează articolele din trecut',
    filters: 'Filtre',
    years: 'Anii',
    categories: 'Categorii',
    results: 'rezultate',
    noResults: 'Nu s-au găsit articole arhivate',
    noResultsDesc: 'Încercați să modificați filtrele sau să reveniți mai târziu.',
    loading: 'Se încarcă...',
    prev: 'Anterior',
    next: 'Următor',
    page: 'Pagina',
    of: 'din',
    showFilters: 'Filtre',
    hideFilters: 'Ascunde',
    clearFilters: 'Șterge filtrele',
  },
  en: {
    title: 'News Archive',
    subtitle: 'Explore articles from the past',
    filters: 'Filters',
    years: 'Years',
    categories: 'Categories',
    results: 'results',
    noResults: 'No archived articles found',
    noResultsDesc: 'Try adjusting your filters or check back later.',
    loading: 'Loading...',
    prev: 'Previous',
    next: 'Next',
    page: 'Page',
    of: 'of',
    showFilters: 'Filters',
    hideFilters: 'Hide',
    clearFilters: 'Clear filters',
  },
  ru: {
    title: 'Архив новостей',
    subtitle: 'Исследуйте статьи из прошлого',
    filters: 'Фильтры',
    years: 'Годы',
    categories: 'Категории',
    results: 'результатов',
    noResults: 'Архивные статьи не найдены',
    noResultsDesc: 'Попробуйте изменить фильтры или вернитесь позже.',
    loading: 'Загрузка...',
    prev: 'Назад',
    next: 'Вперед',
    page: 'Страница',
    of: 'из',
    showFilters: 'Фильтры',
    hideFilters: 'Скрыть',
    clearFilters: 'Очистить фильтры',
  },
};

function ArchiveBrowserContent({ locale, initialStats }: ArchiveBrowserProps) {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const t = translations[locale as keyof typeof translations] || translations.ro;

  // State
  const [articles, setArticles] = useState<any[]>([]);
  const [years, setYears] = useState<YearData[]>(initialStats?.byYear || []);
  const [totalItems, setTotalItems] = useState(0);
  const [isLoading, setIsLoading] = useState(true);
  const [isYearsLoading, setIsYearsLoading] = useState(!initialStats);
  const [showMobileFilters, setShowMobileFilters] = useState(false);

  // Get filter values from URL
  const selectedYear = searchParams.get('year') ? parseInt(searchParams.get('year')!) : null;
  const selectedCategory = searchParams.get('category') ? parseInt(searchParams.get('category')!) : null;
  const currentPage = parseInt(searchParams.get('page') || '1');

  const itemsPerPage = 12;

  // Update URL with filters
  const updateFilters = useCallback((updates: Record<string, string | null>) => {
    const params = new URLSearchParams(searchParams.toString());

    Object.entries(updates).forEach(([key, value]) => {
      if (value === null) {
        params.delete(key);
      } else {
        params.set(key, value);
      }
    });

    // Reset to page 1 when filters change (except when changing page)
    if (!('page' in updates)) {
      params.delete('page');
    }

    router.push(`${pathname}?${params.toString()}`, { scroll: false });
  }, [searchParams, pathname, router]);

  // Fetch years data
  useEffect(() => {
    if (initialStats) return;

    const fetchYears = async () => {
      try {
        const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? '';
        const response = await fetch(`${apiUrl}/api/archive/years?locale=${locale}`, {
          headers: {
            'Accept': 'application/json',
            'Accept-Language': locale,
          },
        });

        if (response.ok) {
          const data = await response.json();
          setYears(data);
        }
      } catch (error) {
        console.error('Error fetching years:', error);
      } finally {
        setIsYearsLoading(false);
      }
    };

    fetchYears();
  }, [locale, initialStats]);

  // Fetch articles
  useEffect(() => {
    const fetchArticles = async () => {
      setIsLoading(true);

      try {
        const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? '';
        const params = new URLSearchParams({
          page: currentPage.toString(),
          itemsPerPage: itemsPerPage.toString(),
          'order[publishedAt]': 'DESC',
        });

        // Add year filter if selected
        if (selectedYear) {
          const startDate = `${selectedYear}-01-01T00:00:00`;
          const endDate = `${selectedYear}-12-31T23:59:59`;
          params.append('publishedAt[after]', startDate);
          params.append('publishedAt[before]', endDate);
        }

        // Add category filter if selected
        if (selectedCategory) {
          params.append('category', selectedCategory.toString());
        }

        const response = await fetch(`${apiUrl}/api/archived_articles?${params.toString()}`, {
          headers: {
            'Accept': 'application/ld+json',
            'Accept-Language': locale,
          },
        });

        if (response.ok) {
          const data = await response.json();
          setArticles(data['hydra:member'] || data.member || []);
          setTotalItems(data['hydra:totalItems'] || data.totalItems || 0);
        }
      } catch (error) {
        console.error('Error fetching articles:', error);
        setArticles([]);
        setTotalItems(0);
      } finally {
        setIsLoading(false);
      }
    };

    fetchArticles();
  }, [locale, currentPage, selectedYear, selectedCategory]);

  const totalPages = Math.ceil(totalItems / itemsPerPage);
  const hasFilters = selectedYear !== null || selectedCategory !== null;

  return (
    <div className="min-h-screen">
      {/* Decorative Header */}
      <div className="relative bg-gradient-to-br from-amber-900 via-amber-800 to-orange-900 text-white overflow-hidden">
        {/* Noise texture */}
        <div
          className="absolute inset-0 opacity-10"
          style={{
            backgroundImage: `url("data:image/svg+xml,%3Csvg viewBox='0 0 400 400' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E")`,
          }}
        />

        {/* Decorative lines */}
        <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-amber-400/50 to-transparent" />
        <div className="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-amber-300/30 to-transparent" />

        <div className="relative container mx-auto px-4 py-12 md:py-16">
          <div className="max-w-3xl mx-auto text-center">
            {/* Archive Icon */}
            <div className="inline-flex items-center justify-center w-16 h-16 mb-6 rounded-full bg-amber-800/50 border border-amber-600/50">
              <svg className="w-8 h-8 text-amber-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
              </svg>
            </div>

            <h1 className="text-4xl md:text-5xl font-bold mb-4 font-serif tracking-tight">
              {t.title}
            </h1>
            <p className="text-lg text-amber-200/80 font-light">
              {t.subtitle}
            </p>

            {/* Stats */}
            {totalItems > 0 && (
              <div className="mt-8 inline-flex items-center gap-3 px-6 py-3 rounded-full bg-amber-800/40 border border-amber-600/30">
                <span className="text-2xl font-bold text-amber-100">{totalItems.toLocaleString()}</span>
                <span className="text-amber-300/80">{t.results}</span>
              </div>
            )}
          </div>
        </div>

        {/* Bottom decorative border */}
        <div className="absolute bottom-0 left-0 right-0">
          <svg className="w-full h-4 text-amber-50" preserveAspectRatio="none" viewBox="0 0 1200 20">
            <path d="M0 20 Q300 0 600 10 Q900 20 1200 5 L1200 20 Z" fill="currentColor" />
          </svg>
        </div>
      </div>

      {/* Main Content */}
      <div className="bg-gradient-to-b from-amber-50 to-orange-50/30 min-h-[60vh]">
        <div className="container mx-auto px-4 py-8">
          <div className="flex flex-col lg:flex-row gap-8">
            {/* Sidebar - Filters */}
            <aside className={`
              lg:w-72 flex-shrink-0
              ${showMobileFilters ? 'block' : 'hidden lg:block'}
            `}>
              <div className="sticky top-4 space-y-6">
                {/* Mobile close button */}
                <div className="lg:hidden flex justify-between items-center mb-4">
                  <h2 className="text-lg font-bold text-amber-900">{t.filters}</h2>
                  <button
                    onClick={() => setShowMobileFilters(false)}
                    className="p-2 rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200 transition-colors"
                  >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>

                {/* Clear Filters */}
                {hasFilters && (
                  <button
                    onClick={() => updateFilters({ year: null, category: null })}
                    className="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-red-50 text-red-700 hover:bg-red-100 transition-colors text-sm font-medium"
                  >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    {t.clearFilters}
                  </button>
                )}

                {/* Year Filter Section */}
                <div className="bg-surface/70 backdrop-blur rounded-xl p-5 shadow-sm border border-amber-200/50">
                  <h3 className="text-sm font-bold uppercase tracking-widest text-amber-700 mb-4 flex items-center gap-2">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    {t.years}
                  </h3>
                  <YearFilter
                    years={years}
                    selectedYear={selectedYear}
                    onYearChange={(year) => updateFilters({ year: year?.toString() || null })}
                    isLoading={isYearsLoading}
                    locale={locale}
                  />
                </div>

                {/* Category Filter Section */}
                <div className="bg-surface/70 backdrop-blur rounded-xl p-5 shadow-sm border border-amber-200/50">
                  <h3 className="text-sm font-bold uppercase tracking-widest text-amber-700 mb-4 flex items-center gap-2">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    {t.categories}
                  </h3>
                  <CategoryFilter
                    selectedCategory={selectedCategory}
                    onCategoryChange={(cat) => updateFilters({ category: cat?.toString() || null })}
                    locale={locale}
                  />
                </div>
              </div>
            </aside>

            {/* Main Content Area */}
            <main className="flex-1 min-w-0">
              {/* Mobile Filter Toggle */}
              <div className="lg:hidden mb-6">
                <button
                  onClick={() => setShowMobileFilters(!showMobileFilters)}
                  className="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-amber-100 text-amber-800 hover:bg-amber-200 transition-colors font-medium"
                >
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                  </svg>
                  {showMobileFilters ? t.hideFilters : t.showFilters}
                  {hasFilters && (
                    <span className="ml-2 px-2 py-0.5 text-xs bg-amber-600 text-white rounded-full">
                      {(selectedYear ? 1 : 0) + (selectedCategory ? 1 : 0)}
                    </span>
                  )}
                </button>
              </div>

              {/* Active Filters Display */}
              {hasFilters && (
                <div className="flex flex-wrap gap-2 mb-6">
                  {selectedYear && (
                    <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-200/70 text-amber-800 text-sm font-medium">
                      {selectedYear}
                      <button
                        onClick={() => updateFilters({ year: null })}
                        className="hover:text-amber-600"
                      >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    </span>
                  )}
                </div>
              )}

              {/* Loading State */}
              {isLoading && (
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                  {[...Array(6)].map((_, i) => (
                    <div
                      key={i}
                      className="animate-pulse bg-amber-100/50 rounded-xl h-80"
                      style={{ animationDelay: `${i * 100}ms` }}
                    />
                  ))}
                </div>
              )}

              {/* Articles Grid */}
              {!isLoading && articles.length > 0 && (
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                  {articles.map((article, index) => (
                    <div
                      key={article.id}
                      className="animate-fade-in-up"
                      style={{ animationDelay: `${index * 50}ms` }}
                    >
                      <ArchiveArticleCard
                        article={article}
                        locale={locale}
                        showLead
                      />
                    </div>
                  ))}
                </div>
              )}

              {/* Empty State */}
              {!isLoading && articles.length === 0 && (
                <div className="text-center py-20">
                  <div className="inline-flex items-center justify-center w-20 h-20 mb-6 rounded-full bg-amber-100">
                    <svg className="w-10 h-10 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                  </div>
                  <h3 className="text-xl font-bold text-amber-900 mb-2">{t.noResults}</h3>
                  <p className="text-amber-700/70 max-w-md mx-auto">{t.noResultsDesc}</p>
                  {hasFilters && (
                    <button
                      onClick={() => updateFilters({ year: null, category: null })}
                      className="mt-6 px-6 py-2.5 rounded-full bg-amber-600 text-white hover:bg-amber-700 transition-colors font-medium"
                    >
                      {t.clearFilters}
                    </button>
                  )}
                </div>
              )}

              {/* Pagination */}
              {!isLoading && totalPages > 1 && (
                <nav className="mt-12 flex items-center justify-center gap-2" aria-label="Pagination">
                  {/* Previous */}
                  <button
                    onClick={() => updateFilters({ page: (currentPage - 1).toString() })}
                    disabled={currentPage <= 1}
                    className={`
                      flex items-center gap-1.5 px-4 py-2.5 rounded-lg font-medium transition-all
                      ${currentPage <= 1
                        ? 'bg-amber-100/50 text-amber-400 cursor-not-allowed'
                        : 'bg-amber-100 text-amber-800 hover:bg-amber-200 hover:shadow-md'
                      }
                    `}
                  >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                    </svg>
                    {t.prev}
                  </button>

                  {/* Page Info */}
                  <div className="px-6 py-2.5 rounded-lg bg-amber-600 text-white font-medium">
                    {t.page} {currentPage} {t.of} {totalPages}
                  </div>

                  {/* Next */}
                  <button
                    onClick={() => updateFilters({ page: (currentPage + 1).toString() })}
                    disabled={currentPage >= totalPages}
                    className={`
                      flex items-center gap-1.5 px-4 py-2.5 rounded-lg font-medium transition-all
                      ${currentPage >= totalPages
                        ? 'bg-amber-100/50 text-amber-400 cursor-not-allowed'
                        : 'bg-amber-100 text-amber-800 hover:bg-amber-200 hover:shadow-md'
                      }
                    `}
                  >
                    {t.next}
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                    </svg>
                  </button>
                </nav>
              )}
            </main>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function ArchiveBrowser(props: ArchiveBrowserProps) {
  return (
    <Suspense fallback={
      <div className="min-h-screen bg-amber-50 flex items-center justify-center">
        <div className="animate-pulse text-amber-600">Loading...</div>
      </div>
    }>
      <ArchiveBrowserContent {...props} />
    </Suspense>
  );
}
