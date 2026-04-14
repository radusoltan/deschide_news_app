'use client';

import { useState, useMemo, useEffect, useRef, useCallback, useTransition } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { HiPencil, HiSearch, HiX, HiLockClosed, HiTrash, HiExclamation, HiCheckCircle, HiClock } from 'react-icons/hi';
import Link from 'next/link';
import { DeleteArticleButton } from './components/DeleteArticleButton';
import { batchDeleteArticlesAction, batchUpdateStatusAction } from '@/app/actions/articles';

interface Article {
  id: number;
  title: string;
  slug: string;
  status: string;
  category?: { id: number; title: string; slug: string } | string;
  authors?: { id: number; fullName: string; slug: string }[] | string[];
  publishedAt?: string;
  createdAt?: string;
  viewCount?: number;
  aiGenerated?: boolean;
  aiConfidenceScore?: number | null;
  aiSourceCount?: number | null;
  sourceClusterId?: number | null;
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
  new: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-primary-dark',
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

function AiBadge({ confidence }: { confidence?: number | null }) {
  if (confidence == null) return null;
  const isHigh = confidence >= 0.85;
  const color = isHigh
    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
    : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300';

  return (
    <span className={`ml-1.5 px-1.5 py-0.5 inline-flex items-center text-xs font-medium rounded ${color}`} title={`AI confidence: ${(confidence * 100).toFixed(0)}%`}>
      AI
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

  // Batch selection state
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [showBatchDeleteModal, setShowBatchDeleteModal] = useState(false);
  const [isBatchDeleting, startBatchTransition] = useTransition();
  const [isBatchUpdating, startBatchUpdateTransition] = useTransition();
  const [batchError, setBatchError] = useState<string | null>(null);
  const [batchResult, setBatchResult] = useState<string | null>(null);

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

  // Selection helpers
  const toggleSelect = (id: number) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const toggleSelectAll = () => {
    if (selectedIds.size === displayArticles.length) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(displayArticles.map((a) => a.id)));
    }
  };

  const isAllSelected = displayArticles.length > 0 && selectedIds.size === displayArticles.length;
  const isSomeSelected = selectedIds.size > 0 && selectedIds.size < displayArticles.length;

  // Clear selection when articles change (filter, search, pagination)
  useEffect(() => {
    setSelectedIds(new Set());
  }, [articles, searchResults, statusFilter, categoryFilter]);

  const handleBatchDelete = () => {
    setBatchError(null);
    setBatchResult(null);

    startBatchTransition(async () => {
      const result = await batchDeleteArticlesAction(Array.from(selectedIds), locale);

      if (result.success) {
        setBatchResult(result.message || null);
        setSelectedIds(new Set());
        setShowBatchDeleteModal(false);
        router.refresh();
      } else {
        setBatchError(result.message || 'Batch delete failed');
        // Remove successfully deleted from selection
        if (result.deletedCount > 0) {
          const failedSet = new Set(result.failedIds);
          setSelectedIds(failedSet);
          router.refresh();
        }
      }
    });
  };

  const handleBatchStatusChange = (newStatus: string) => {
    setBatchError(null);
    setBatchResult(null);

    startBatchUpdateTransition(async () => {
      const result = await batchUpdateStatusAction(Array.from(selectedIds), newStatus, locale);

      if (result.success) {
        setBatchResult(result.message || null);
        setSelectedIds(new Set());
        router.refresh();
      } else {
        setBatchError(result.message || 'Status update failed');
        if (result.updatedCount > 0) {
          const failedSet = new Set(result.failedIds);
          setSelectedIds(failedSet);
          router.refresh();
        }
      }
    });
  };

  const isBatchBusy = isBatchDeleting || isBatchUpdating;

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
      <div className="bg-surface dark:bg-surface-dark rounded-lg shadow mb-4 p-4">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          {/* Search Input */}
          <div className="relative md:col-span-2">
            <label
              htmlFor="search"
              className="block text-sm font-medium text-primary dark:text-primary-dark mb-1"
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
                className="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
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
                  <HiX className="h-5 w-5 text-gray-400 hover:text-gray-600 dark:hover:text-primary-dark" />
                </button>
              )}
            </div>
          </div>

          {/* Status Filter */}
          <div>
            <label
              htmlFor="status-filter"
              className="block text-sm font-medium text-primary dark:text-primary-dark mb-1"
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
              className="block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
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
              className="block text-sm font-medium text-primary dark:text-primary-dark mb-1"
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
              className="block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
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
            <span className="ml-2 text-sm text-secondary dark:text-gray-400">
              {isUsingServerSearch ? (
                <>({displayArticles.length} results from server search)</>
              ) : (
                <>({displayArticles.length} of {articles.length} articles)</>
              )}
            </span>
          </div>
        )}
      </div>

      {/* Batch Action Toolbar */}
      {selectedIds.size > 0 && (
        <div className="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg shadow mb-4 p-3 flex items-center justify-between flex-wrap gap-2">
          <div className="flex items-center gap-3">
            <span className="text-sm font-medium text-blue-800 dark:text-blue-200">
              {selectedIds.size} article{selectedIds.size > 1 ? 's' : ''} selected
            </span>
            <button
              onClick={() => setSelectedIds(new Set())}
              className="text-sm text-blue-600 dark:text-blue-400 hover:underline"
              disabled={isBatchBusy}
            >
              Clear selection
            </button>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={() => handleBatchStatusChange('submitted')}
              disabled={isBatchBusy}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-yellow-800 bg-yellow-100 hover:bg-yellow-200 dark:text-yellow-200 dark:bg-yellow-900/50 dark:hover:bg-yellow-900/70 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <HiClock className="w-4 h-4" />
              Mark Submitted
            </button>
            <button
              onClick={() => handleBatchStatusChange('published')}
              disabled={isBatchBusy}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-green-800 bg-green-100 hover:bg-green-200 dark:text-green-200 dark:bg-green-900/50 dark:hover:bg-green-900/70 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <HiCheckCircle className="w-4 h-4" />
              Mark Published
            </button>
            <div className="w-px h-6 bg-blue-200 dark:bg-blue-700" />
            <button
              onClick={() => { setBatchError(null); setBatchResult(null); setShowBatchDeleteModal(true); }}
              disabled={isBatchBusy}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <HiTrash className="w-4 h-4" />
              Delete
            </button>
          </div>
        </div>
      )}

      {/* Batch Result Toast */}
      {batchResult && (
        <div className="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg mb-4 p-3 flex items-center justify-between">
          <span className="text-sm text-green-800 dark:text-green-200">{batchResult}</span>
          <button onClick={() => setBatchResult(null)} className="text-green-600 dark:text-green-400 hover:text-green-800">
            <HiX className="w-4 h-4" />
          </button>
        </div>
      )}

      {/* Articles Table */}
      <div className="bg-surface dark:bg-surface-dark rounded-lg shadow overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left text-secondary dark:text-gray-400">
            <thead className="text-xs text-primary uppercase bg-surface-sunken dark:bg-gray-700 dark:text-gray-400">
              <tr>
                <th scope="col" className="w-10 px-4 py-3">
                  <input
                    type="checkbox"
                    checked={isAllSelected}
                    ref={(el) => { if (el) el.indeterminate = isSomeSelected; }}
                    onChange={toggleSelectAll}
                    className="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 dark:bg-gray-700 dark:border-gray-600"
                    disabled={displayArticles.length === 0}
                  />
                </th>
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
                  <td colSpan={8} className="px-6 py-12 text-center">
                    <div className="text-secondary dark:text-gray-400">
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
                      className={`border-b dark:border-gray-700 hover:bg-surface-sunken dark:hover:bg-gray-600 ${
                        selectedIds.has(article.id)
                          ? 'bg-blue-50 dark:bg-blue-900/20'
                          : 'bg-surface dark:bg-surface-dark'
                      }`}
                    >
                      <td className="w-10 px-4 py-4">
                        <input
                          type="checkbox"
                          checked={selectedIds.has(article.id)}
                          onChange={() => toggleSelect(article.id)}
                          className="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 dark:bg-gray-700 dark:border-gray-600"
                        />
                      </td>
                      <td className="px-6 py-4 font-medium text-primary dark:text-primary-dark">
                        <div className="flex items-start gap-2">
                          <div className="flex-1">
                            <div className="flex items-center">
                              <span>{article.title}</span>
                              {article.aiGenerated && (
                                <AiBadge confidence={article.aiConfidenceScore} />
                              )}
                            </div>
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
                        ? article.authors.map((author) =>
                            typeof author === 'object' && author !== null
                              ? author.fullName
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
          <div className="px-6 py-4 bg-surface-sunken dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
            <p className="text-sm text-gray-600 dark:text-gray-400">
              {isUsingServerSearch ? (
                <>
                  Found <span className="font-medium text-primary dark:text-primary-dark">{displayArticles.length}</span> articles
                  {searchTotalItems > displayArticles.length && (
                    <> (showing first {displayArticles.length} of {searchTotalItems})</>
                  )}
                </>
              ) : (
                <>
                  Showing <span className="font-medium text-primary dark:text-primary-dark">{displayArticles.length}</span> of{' '}
                  <span className="font-medium text-primary dark:text-primary-dark">{totalItems ?? articles.length}</span> articles
                </>
              )}
            </p>
          </div>
        )}
      </div>

      {/* Batch Delete Confirmation Modal */}
      {showBatchDeleteModal && (
        <div className="fixed inset-0 z-50 overflow-y-auto">
          <div
            className="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
            onClick={() => !isBatchDeleting && setShowBatchDeleteModal(false)}
          />
          <div className="flex min-h-full items-center justify-center p-4">
            <div className="relative bg-surface dark:bg-surface-dark rounded-lg shadow-xl max-w-md w-full">
              {/* Header */}
              <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                <div className="flex items-center gap-3">
                  <div className="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center">
                    <HiExclamation className="w-6 h-6 text-red-600 dark:text-red-400" />
                  </div>
                  <h3 className="text-lg font-semibold text-primary dark:text-primary-dark">
                    Delete {selectedIds.size} article{selectedIds.size > 1 ? 's' : ''}
                  </h3>
                </div>
                <button
                  onClick={() => setShowBatchDeleteModal(false)}
                  disabled={isBatchDeleting}
                  className="text-gray-400 hover:text-gray-600 dark:hover:text-primary-dark"
                >
                  <HiX className="w-6 h-6" />
                </button>
              </div>

              {/* Body */}
              <div className="p-6">
                <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
                  Are you sure you want to delete <strong>{selectedIds.size}</strong> selected article{selectedIds.size > 1 ? 's' : ''}? This action cannot be undone.
                </p>

                {/* List selected articles */}
                <div className="bg-surface-sunken dark:bg-gray-700 rounded-lg p-3 mb-4 max-h-48 overflow-y-auto">
                  <ul className="space-y-1">
                    {displayArticles
                      .filter((a) => selectedIds.has(a.id))
                      .map((a) => (
                        <li key={a.id} className="text-sm text-primary dark:text-primary-dark flex items-center gap-2">
                          <span className="text-xs text-secondary dark:text-gray-400 font-mono">#{a.id}</span>
                          <span className="truncate">{a.title}</span>
                        </li>
                      ))}
                  </ul>
                </div>

                {batchError && (
                  <div className="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                    <p className="text-sm text-red-600 dark:text-red-400">{batchError}</p>
                  </div>
                )}
              </div>

              {/* Footer */}
              <div className="flex items-center justify-end gap-3 p-4 border-t border-gray-200 dark:border-gray-700">
                <button
                  onClick={() => setShowBatchDeleteModal(false)}
                  disabled={isBatchDeleting}
                  className="px-4 py-2 text-sm font-medium text-primary dark:text-primary-dark bg-surface dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-surface-sunken dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Cancel
                </button>
                <button
                  onClick={handleBatchDelete}
                  disabled={isBatchDeleting}
                  className="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                >
                  {isBatchDeleting ? (
                    <>
                      <svg className="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                      </svg>
                      Deleting...
                    </>
                  ) : (
                    <>
                      <HiTrash className="w-4 h-4" />
                      Delete {selectedIds.size} article{selectedIds.size > 1 ? 's' : ''}
                    </>
                  )}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
