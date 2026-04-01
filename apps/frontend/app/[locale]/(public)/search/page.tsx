'use client';

import { useEffect, useState, Suspense } from 'react';
import { useSearchParams } from 'next/navigation';
import Link from 'next/link';

// Force dynamic rendering for search page
export const dynamic = 'force-dynamic';

interface SearchPageProps {
  params: Promise<{
    locale: string;
  }>;
}

interface SearchResult {
  id: number;
  title: string;
  slug: string;
  excerpt: string;
  category: {
    slug: string;
    title: string;
  };
  publishedAt: string;
  articleImages?: Array<{
    image: {
      path: string;
      alt?: string;
    };
    isFeatured: boolean;
  }>;
}

/**
 * Search Content Component
 * Contains the actual search logic with useSearchParams
 */
function SearchContent({ params }: SearchPageProps) {
  const [locale, setLocale] = useState('ro');
  const searchParams = useSearchParams();
  const query = searchParams?.get('q') || '';
  const [searchQuery, setSearchQuery] = useState(query);
  const [results, setResults] = useState<SearchResult[]>([]);
  const [loading, setLoading] = useState(false);
  const [totalResults, setTotalResults] = useState(0);
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 12;

  useEffect(() => {
    params.then(({ locale: l }) => setLocale(l));
  }, [params]);

  useEffect(() => {
    if (query) {
      setSearchQuery(query);
      performSearch(query, 1);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [query]);

  const performSearch = async (q: string, page: number) => {
    if (!q || q.trim().length < 2) {
      setResults([]);
      setTotalResults(0);
      return;
    }

    setLoading(true);

    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? '';

      const queryParams = new URLSearchParams({
        q: q.trim(),
        page: page.toString(),
        itemsPerPage: itemsPerPage.toString(),
        locale: locale,
      });

      // Use Elasticsearch search endpoint
      const response = await fetch(
        `${apiUrl}/search?${queryParams.toString()}`,
        {
          headers: {
            'Accept': 'application/json',
            'Accept-Language': locale,
          },
        }
      );

      if (response.ok) {
        const data = await response.json();
        setResults(data.results || []);
        setTotalResults(data.total || 0);
        setCurrentPage(page);
      } else {
        setResults([]);
        setTotalResults(0);
      }
    } catch (error) {
      console.error('Search error:', error);
      setResults([]);
      setTotalResults(0);
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchQuery.trim()) {
      window.history.pushState({}, '', `/${locale}/search?q=${encodeURIComponent(searchQuery)}`);
      performSearch(searchQuery, 1);
    }
  };

  const totalPages = Math.ceil(totalResults / itemsPerPage);

  const texts = {
    ro: {
      title: 'Căutare',
      placeholder: 'Caută articole...',
      searchButton: 'Caută',
      resultsFor: 'Rezultate pentru',
      noResults: 'Nu am găsit rezultate pentru',
      suggestions: 'Încercați să folosiți cuvinte cheie diferite sau să simplificați căutarea.',
      loading: 'Se caută...',
      found: 'Găsite',
      results: 'rezultate',
    },
    en: {
      title: 'Search',
      placeholder: 'Search articles...',
      searchButton: 'Search',
      resultsFor: 'Results for',
      noResults: 'No results found for',
      suggestions: 'Try using different keywords or simplifying your search.',
      loading: 'Searching...',
      found: 'Found',
      results: 'results',
    },
    ru: {
      title: 'Поиск',
      placeholder: 'Поиск статей...',
      searchButton: 'Искать',
      resultsFor: 'Результаты для',
      noResults: 'Результатов не найдено для',
      suggestions: 'Попробуйте использовать другие ключевые слова или упростить поиск.',
      loading: 'Поиск...',
      found: 'Найдено',
      results: 'результатов',
    },
  };

  const t = texts[locale as keyof typeof texts] || texts.ro;

  return (
    <div className="container mx-auto px-4 py-8">
      {/* Page Header */}
      <div className="mb-8">
        <h1 className="text-4xl font-bold text-primary dark:text-primary-dark mb-6">
          {t.title}
        </h1>

        {/* Search Form */}
        <form onSubmit={handleSearch} className="max-w-3xl">
          <div className="flex gap-2">
            <input
              type="search"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder={t.placeholder}
              className="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-tomato-500 focus:border-transparent dark:bg-surface-dark dark:border-gray-700 dark:text-primary-dark"
              autoFocus
            />
            <button
              type="submit"
              disabled={loading || !searchQuery.trim()}
              className="px-6 py-3 bg-brand-tomato-500 text-white rounded-lg hover:bg-brand-tomato-600 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors font-medium"
            >
              {loading ? t.loading : t.searchButton}
            </button>
          </div>
        </form>
      </div>

      {/* Search Results */}
      {query && (
        <>
          {/* Results Header */}
          <div className="mb-6">
            {totalResults > 0 ? (
              <p className="text-gray-600 dark:text-gray-400">
                {t.found} <strong>{totalResults}</strong> {t.results} {t.resultsFor}{' '}
                <strong>&quot;{query}&quot;</strong>
              </p>
            ) : loading ? (
              <p className="text-gray-600 dark:text-gray-400">{t.loading}</p>
            ) : (
              <div>
                <p className="text-gray-600 dark:text-gray-400 mb-2">
                  {t.noResults} <strong>&quot;{query}&quot;</strong>
                </p>
                <p className="text-secondary dark:text-secondary text-sm">
                  {t.suggestions}
                </p>
              </div>
            )}
          </div>

          {/* Results Grid */}
          {results.length > 0 && (
            <>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                {results.map((article) => {
                  const featuredImage = article.articleImages?.find((img) => img.isFeatured);
                  const imageUrl = featuredImage
                    ? `${process.env.NEXT_PUBLIC_CDN_URL}/uploads/${featuredImage.image.path}`
                    : null;

                  return (
                    <Link
                      key={article.id}
                      href={`/${locale}/${article.category.slug}/${article.slug}`}
                      className="block bg-surface dark:bg-surface-dark rounded-lg shadow hover:shadow-lg transition-shadow overflow-hidden"
                    >
                      {imageUrl && featuredImage && (
                        <div className="aspect-video bg-gray-200 dark:bg-gray-700">
                          {/* eslint-disable-next-line @next/next/no-img-element */}
                          <img
                            src={imageUrl}
                            alt={featuredImage.image.alt || article.title}
                            className="w-full h-full object-cover"
                          />
                        </div>
                      )}
                      <div className="p-4">
                        <div className="text-xs text-brand-tomato-500 font-semibold mb-2">
                          {article.category.title}
                        </div>
                        <h2 className="text-lg font-bold text-primary dark:text-primary-dark mb-2 line-clamp-2">
                          {article.title}
                        </h2>
                        {article.excerpt && (
                          <p className="text-gray-600 dark:text-gray-400 text-sm line-clamp-3">
                            {article.excerpt}
                          </p>
                        )}
                        <div className="mt-3 text-xs text-secondary dark:text-secondary">
                          {new Date(article.publishedAt).toLocaleDateString(locale)}
                        </div>
                      </div>
                    </Link>
                  );
                })}
              </div>

              {/* Pagination */}
              {totalPages > 1 && (
                <div className="flex justify-center items-center gap-2">
                  {currentPage > 1 && (
                    <button
                      onClick={() => performSearch(query, currentPage - 1)}
                      className="px-4 py-2 bg-gray-200 text-primary rounded hover:bg-gray-300 dark:bg-gray-700 dark:text-primary-dark"
                    >
                      ←
                    </button>
                  )}

                  <div className="flex gap-2">
                    {Array.from({ length: Math.min(totalPages, 5) }, (_, i) => {
                      let pageNum;
                      if (totalPages <= 5) {
                        pageNum = i + 1;
                      } else if (currentPage <= 3) {
                        pageNum = i + 1;
                      } else if (currentPage >= totalPages - 2) {
                        pageNum = totalPages - 4 + i;
                      } else {
                        pageNum = currentPage - 2 + i;
                      }

                      return (
                        <button
                          key={pageNum}
                          onClick={() => performSearch(query, pageNum)}
                          className={`px-4 py-2 rounded ${
                            currentPage === pageNum
                              ? 'bg-brand-tomato-500 text-white'
                              : 'bg-gray-200 text-primary hover:bg-gray-300 dark:bg-gray-700 dark:text-primary-dark'
                          }`}
                        >
                          {pageNum}
                        </button>
                      );
                    })}
                  </div>

                  {currentPage < totalPages && (
                    <button
                      onClick={() => performSearch(query, currentPage + 1)}
                      className="px-4 py-2 bg-gray-200 text-primary rounded hover:bg-gray-300 dark:bg-gray-700 dark:text-primary-dark"
                    >
                      →
                    </button>
                  )}
                </div>
              )}
            </>
          )}
        </>
      )}
    </div>
  );
}

/**
 * Search Page
 * Wraps SearchContent in Suspense boundary for useSearchParams
 */
export default function SearchPage({ params }: SearchPageProps) {
  return (
    <Suspense fallback={
      <div className="container mx-auto px-4 py-8">
        <div className="text-center">Loading search...</div>
      </div>
    }>
      <SearchContent params={params} />
    </Suspense>
  );
}
