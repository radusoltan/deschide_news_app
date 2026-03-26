import { getArticles, getCategories } from '@/lib/dal';
import { apiRequest } from '@/lib/api/client';
import { getAccessToken } from '@/lib/dal';
import { ArticlesTableClient } from './ArticlesTableClient';
import { ArticlesPageClient } from './components/ArticlesPageClient';
import { ArticlesPagination } from './components/ArticlesPagination';

interface ArticlesPageProps {
  params: Promise<{
    locale: string;
  }>;
  searchParams: Promise<{
    page?: string;
  }>;
}

export default async function ArticlesPage({ params, searchParams }: ArticlesPageProps) {
  const { locale } = await params;
  const { page: pageParam } = await searchParams;

  const currentPage = pageParam ? parseInt(pageParam, 10) : 1;
  const itemsPerPage = 20; // 20 articles per page

  // Fetch articles from API
  let articlesData: any[] = [];
  let totalItems = 0;
  let error: string | null = null;

  try {
    const data = await getArticles({ locale, page: currentPage, itemsPerPage });
    articlesData = data.member;
    totalItems = data.totalItems || 0;
  } catch (err) {
    console.error('Failed to fetch articles:', err);
    error = err instanceof Error ? err.message : 'Failed to load articles';
    articlesData = [];
  }

  // Fetch categories for modal
  let categories: any[] = [];
  try {
    const categoriesData = await getCategories({ locale, page: 1, itemsPerPage: 100 });
    categories = categoriesData.member || [];
  } catch (err) {
    console.error('Failed to fetch categories:', err);
    categories = [];
  }

  // Fetch article counts from stats API for accurate totals
  let articleCounts: any = null;
  try {
    const token = await getAccessToken();
    if (token) {
      articleCounts = await apiRequest<any>('/api/admin/stats/article-counts', {
        token,
        next: { revalidate: 60 },
      });
    }
  } catch (err) {
    console.error('Failed to fetch article counts:', err);
  }

  const publishedArticles = articleCounts?.published ?? articlesData.filter((a) => a.status === 'published').length;
  const newArticles = articleCounts?.new ?? articlesData.filter((a) => a.status === 'new').length;
  const totalViews = articlesData.reduce((sum, a) => sum + (a.viewCount || 0), 0);

  const totalPages = Math.ceil(totalItems / itemsPerPage);

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
            Articles
          </h1>
          <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Manage your articles
          </p>
        </div>
        <ArticlesPageClient locale={locale} categories={categories} />
      </div>

      {/* Error Message */}
      {error && (
        <div className="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <p className="text-sm text-red-600 dark:text-red-400">
            <strong>Error:</strong> {error}
          </p>
        </div>
      )}

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                Total Articles
              </p>
              <p className="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                {totalItems}
              </p>
            </div>
            <div className="p-3 bg-blue-100 dark:bg-blue-900 rounded-full">
              <svg
                className="w-6 h-6 text-blue-600 dark:text-blue-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
              </svg>
            </div>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                Published
              </p>
              <p className="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                {publishedArticles}
              </p>
            </div>
            <div className="p-3 bg-green-100 dark:bg-green-900 rounded-full">
              <svg
                className="w-6 h-6 text-green-600 dark:text-green-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            </div>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                New
              </p>
              <p className="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                {newArticles}
              </p>
            </div>
            <div className="p-3 bg-yellow-100 dark:bg-yellow-900 rounded-full">
              <svg
                className="w-6 h-6 text-yellow-600 dark:text-yellow-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                />
              </svg>
            </div>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                Total Views
              </p>
              <p className="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                {totalViews.toLocaleString()}
              </p>
            </div>
            <div className="p-3 bg-purple-100 dark:bg-purple-900 rounded-full">
              <svg
                className="w-6 h-6 text-purple-600 dark:text-purple-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                />
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                />
              </svg>
            </div>
          </div>
        </div>
      </div>

      {/* Articles Table */}
      <ArticlesTableClient articles={articlesData} locale={locale} categories={categories} totalItems={totalItems} />

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="mt-8 flex justify-center">
          <ArticlesPagination
            currentPage={currentPage}
            totalPages={totalPages}
            locale={locale}
          />
        </div>
      )}
    </div>
  );
}
