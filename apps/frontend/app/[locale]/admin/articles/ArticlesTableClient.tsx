'use client';

import { useState, useMemo, useEffect, useRef, useCallback } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { HiPencil, HiSearch, HiX, HiLockClosed } from 'react-icons/hi';
import Link from 'next/link';
import { DeleteArticleButton } from './components/DeleteArticleButton';

interface Article {
  id: number;
  title: string;
  slug: string;
  status: string;
  category?: any;
  authors?: any[];
  publishedAt?: string;
  createdAt?: string;
  viewCount?: number;
}

interface Category {
  id: number;
  title: string;
  slug: string;
}

interface ArticlesTableClientProps {
  articles: Article[];
  locale: string;
  categories: Category[];
  totalItems?: number;
}

const statusStyles = {
  new: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
  submitted: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
  published: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
};

function StatusBadge({ status }: { status: string }) {
  const style = statusStyles[status as keyof typeof statusStyles] || statusStyles.new;

  return (
    <span className={`px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${style}`}>
      {status}
    </span>
  );
}

interface ArticleLock {
  articleId: number;
  lockedBy: {
    id: number;
    firstName: string;
    lastName: string;
    email: string;
  };
  lockedAt: string;
  expiresAt: string;
}

export function ArticlesTableClient({ articles, locale, categories, totalItems }: ArticlesTableClientProps) {
  const router = useRouter();
  const currentSearchParams = useSearchParams();
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>(currentSearchParams.get('status') || 'all');
  const [categoryFilter, setCategoryFilter] = useState<string>(currentSearchParams.get('category') || 'all');
  const [activeLocks, setActiveLocks] = useState<Map<number, ArticleLock>>(new Map());

  // Navigate with server-side filters
  const applyServerFilter = useCallback((key: string, value: string) => {
    const params = new URLSearchParams(currentSearchParams.toString());
    if (value === 'all' || value === '') {
      params.delete(key);
    } else {
      params.set(key, value);
    }
    params.delete('page'); // reset to page 1 on filter change
    router.push(`?${params.toString()}`);
  }, [router, currentSearchParams]);

  // Server-side search state
  const [searchResults, setSearchResults] = useState<Article[] | null>(null);
  const [isSearching, setIsSearching] = useState(false);
  const [searchTotalItems, setSearchTotalItems] = useState(0);
  const debounceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Fetch active locks
  useEffect(() => {
    const fetchLocks = async () => {
      try {
        const response = await fetch('/api/articles/locks/active');
        if (response.ok) {
          const locks: ArticleLock[] = await response.json();
          const locksMap = new Map<number, ArticleLock>();
          locks.forEach((lock) => {
            locksMap.set(lock.articleId, lock);
          });
          setActiveLocks(locksMap);
        }
      } catch (error) {
        console.error('Failed to fetch active locks:', error);
      }
    };

    fetchLocks();
    const interval = setInterval(fetchLocks, 30000);
    return () => clearInterval(interval);
  }, []);

  // Server-side search with debounce
  const searchArticles = useCallback(async (query: string) => {
    if (!query.trim()) {
      setSearchResults(null);
      setSearchTotalItems(0);
      setIsSearching(false);
      return;
    }

    setIsSearching(true);
    try {
      const params = new URLSearchParams({
        title: query,
        locale,
        itemsPerPage: '50',
      });
      const response = await fetch(`/api/articles/search?${params.toString()}`);
      if (response.ok) {
        const data = await response.json();
        const members = data['hydra:member'] || data.member || [];
        setSearchResults(members);
        setSearchTotalItems(data['hydra:totalItems'] || data.totalItems || members.length);
      } else {
        console.error('Search failed:', response.status);
        setSearchResults(null);
      }
    } catch (error) {
      console.error('Search error:', error);
      setSearchResults(null);
    } finally {
      setIsSearching(false);
    }
  }, [locale]);

  // Debounced search effect
  useEffect(() => {
    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
    }

    if (!searchQuery.trim()) {
      setSearchResults(null);
      setSearchTotalItems(0);
      return;
    }

    debounceTimerRef.current = setTimeout(() => {
      searchArticles(searchQuery);
    }, 300);

    return () => {
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
      }
    };
  }, [searchQuery, searchArticles]);

  // Use search results when available, otherwise filter props articles locally
  const displayArticles = useMemo(() => {
    const sourceArticles = searchResults !== null ? searchResults : articles;

    return sourceArticles.filter((article) => {
      // Status filter (always client-side)
      const matchesStatus = statusFilter === 'all' || article.status === statusFilter;

      // Category filter (always client-side)
      const articleCategoryId = typeof article.category === 'object' && article.category !== null
        ? article.category.id
        : null;
      const matchesCategory = categoryFilter === 'all' ||
        (categoryFilter === 'none' ? !articleCategoryId : articleCategoryId?.toString() === categoryFilter);

      return matchesStatus && matchesCategory;
    });
  }, [articles, searchResults, statusFilter, categoryFilter]);

  const hasActiveFilters = searchQuery !== '' || statusFilter !== 'all' || categoryFilter !== 'all';
  const isUsingServerSearch = searchResults !== null;

  const clearFilters = () => {
    setSearchQuery('');
    setStatusFilter('all');
    setCategoryFilter('all');
    setSearchResults(null);
    setSearchTotalItems(0);
    router.push('?');
  };

  const formatDate = (dateString?: string) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString(locale, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  };

  return (
    <>
      {/* Search and Filters */}
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 p-4">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          {/* Search Input */}
          <div className="relative md:col-span-2">
            <label
              htmlFor="search"
              className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
            >
              Search by title
            </label>
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                {isSearching ? (
                  <svg className="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                  </svg>
                ) : (
                  <HiSearch className="h-5 w-5 text-gray-400" />
                )}
              </div>
              <input
                type="text"
                id="search"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search articles..."
                className="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
              {searchQuery && (
                <button
                  onClick={() => {
                    setSearchQuery('');
                    setSearchResults(null);
                    setSearchTotalItems(0);
                  }}
                  className="absolute inset-y-0 right-0 pr-3 flex items-center"
                >
                  <HiX className="h-5 w-5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" />
                </button>
              )}
            </div>
          </div>

          {/* Status Filter */}
          <div>
            <label
              htmlFor="status-filter"
              className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
            >
              Filter by status
            </label>
            <select
              id="status-filter"
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                applyServerFilter('status', e.target.value);
              }}
              className="block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="all">All Status</option>
              <option value="new">New</option>
              <option value="submitted">Submitted</option>
              <option value="published">Published</option>
            </select>
          </div>

          {/* Category Filter */}
          <div>
            <label
              htmlFor="category-filter"
              className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
            >
              Filter by category
            </label>
            <select
              id="category-filter"
              value={categoryFilter}
              onChange={(e) => {
                setCategoryFilter(e.target.value);
                applyServerFilter('category', e.target.value);
              }}
              className="block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="all">All Categories</option>
              <option value="none">No Category</option>
              {categories.map((category) => (
                <option key={category.id} value={category.id.toString()}>
                  {category.title}
                </option>
              ))}
            </select>
          </div>
        </div>

        {/* Filter Info */}
        {hasActiveFilters && (
          <div className="mt-3 flex items-center">
            <button
              onClick={clearFilters}
              className="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 font-medium"
            >
              Clear all filters
            </button>
            <span className="ml-2 text-sm text-gray-500 dark:text-gray-400">
              {isUsingServerSearch ? (
                <>({displayArticles.length} results from server search)</>
              ) : (
                <>({displayArticles.length} of {articles.length} articles)</>
              )}
            </span>
          </div>
        )}
      </div>

      {/* Articles Table */}
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left text-gray-500 dark:text-gray-400">
            <thead className="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
              <tr>
                <th scope="col" className="px-6 py-3">
                  Title
                </th>
                <th scope="col" className="px-6 py-3">
                  Status
                </th>
                <th scope="col" className="px-6 py-3">
                  Category
                </th>
                <th scope="col" className="px-6 py-3">
                  Author
                </th>
                <th scope="col" className="px-6 py-3">
                  Published
                </th>
                <th scope="col" className="px-6 py-3">
                  Views
                </th>
                <th scope="col" className="px-6 py-3">
                  <span className="sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {displayArticles.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-6 py-12 text-center">
                    <div className="text-gray-500 dark:text-gray-400">
                      <p className="text-lg mb-2">No articles found</p>
                      <p className="text-sm">
                        {hasActiveFilters
                          ? 'Try adjusting your search or filters'
                          : 'Create your first article to get started'}
                      </p>
                    </div>
                  </td>
                </tr>
              ) : (
                displayArticles.map((article) => {
                  const lock = activeLocks.get(article.id);
                  const isLocked = !!lock;

                  return (
                    <tr
                      key={article.id}
                      className="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600"
                    >
                      <td className="px-6 py-4 font-medium text-gray-900 dark:text-white">
                        <div className="flex items-start gap-2">
                          <div className="flex-1">
                            <div>{article.title}</div>
                            {isLocked && (
                              <div className="flex items-center gap-1.5 mt-1 text-xs text-yellow-700 dark:text-yellow-400">
                                <HiLockClosed className="w-3.5 h-3.5 flex-shrink-0" />
                                <span>
                                  Editing: {lock.lockedBy.firstName} {lock.lockedBy.lastName}
                                </span>
                              </div>
                            )}
                          </div>
                        </div>
                      </td>
                    <td className="px-6 py-4">
                      <StatusBadge status={article.status} />
                    </td>
                    <td className="px-6 py-4">
                      {typeof article.category === 'object' && article.category !== null
                        ? article.category.title || '-'
                        : article.category || '-'}
                    </td>
                    <td className="px-6 py-4">
                      {Array.isArray(article.authors) && article.authors.length > 0
                        ? article.authors.map((author: any) =>
                            typeof author === 'object' && author !== null
                              ? author.fullName || `${author.firstName || ''} ${author.lastName || ''}`.trim()
                              : author
                          ).join(', ')
                        : '-'}
                    </td>
                    <td className="px-6 py-4">{formatDate(article.publishedAt)}</td>
                    <td className="px-6 py-4">{article.viewCount || 0}</td>
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <Link
                          href={`/${locale}/admin/articles/${article.id}/edit`}
                          className="inline-flex items-center text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 font-medium"
                        >
                          <HiPencil className="w-4 h-4 mr-1" />
                          Edit
                        </Link>
                        <DeleteArticleButton
                          articleId={article.id}
                          articleTitle={article.title}
                          locale={locale}
                        />
                      </div>
                    </td>
                  </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {/* Results Summary */}
        {displayArticles.length > 0 && (
          <div className="px-6 py-4 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
            <p className="text-sm text-gray-600 dark:text-gray-400">
              {isUsingServerSearch ? (
                <>
                  Found <span className="font-medium text-gray-900 dark:text-white">{displayArticles.length}</span> articles
                  {searchTotalItems > displayArticles.length && (
                    <> (showing first {displayArticles.length} of {searchTotalItems})</>
                  )}
                </>
              ) : (
                <>
                  Showing <span className="font-medium text-gray-900 dark:text-white">{displayArticles.length}</span> of{' '}
                  <span className="font-medium text-gray-900 dark:text-white">{totalItems ?? articles.length}</span> articles
                </>
              )}
            </p>
          </div>
        )}
      </div>
    </>
  );
}
