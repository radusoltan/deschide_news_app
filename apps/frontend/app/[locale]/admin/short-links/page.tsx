import { Suspense } from 'react';
import Link from 'next/link';
import { Spinner } from 'flowbite-react';
import ShortLinksTable from './components/ShortLinksTable';
import { getShortLinks } from '@/lib/api/short-links';
import { getAccessToken } from '@/lib/dal';

interface ShortLinksPageProps {
  params: Promise<{
    locale: string;
  }>;
  searchParams: Promise<{
    page?: string;
    code?: string;
    title?: string;
  }>;
}

export default async function ShortLinksPage({
  params,
  searchParams,
}: ShortLinksPageProps) {
  const { locale } = await params;
  const { page: pageParam, code, title } = await searchParams;

  const currentPage = pageParam ? parseInt(pageParam, 10) : 1;
  const itemsPerPage = 30;

  // Get access token
  const token = await getAccessToken();

  // Fetch short links
  let shortLinksData: { id: number; code: string; targetUrl: string; clickCount: number; isActive: boolean; createdAt: string; expiresAt?: string }[] = [];
  let totalItems = 0;
  let error: string | null = null;

  try {
    if (!token) {
      throw new Error('Nu sunteți autentificat');
    }

    const data = await getShortLinks({
      page: currentPage,
      itemsPerPage,
      code,
      title,
      token,
    });

    shortLinksData = data['hydra:member'] || [];
    totalItems = data['hydra:totalItems'] || 0;
  } catch (err) {
    console.error('Failed to fetch short links:', err);
    error =
      err instanceof Error ? err.message : 'Eroare la încărcarea linkurilor scurte';
    shortLinksData = [];
  }

  const totalPages = Math.ceil(totalItems / itemsPerPage);

  return (
    <div className="p-4">
      {/* Page Header */}
      <div className="mb-4 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-primary dark:text-primary-dark">
            Linkuri Scurte
          </h1>
          <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Gestionați linkurile scurte și urmăriți statisticile
          </p>
        </div>
        <div>
          <Link
            href={`/${locale}/admin/short-links/new`}
            className="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800 inline-block"
          >
            Creează Link Scurt
          </Link>
        </div>
      </div>

      {/* Statistics Summary */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div className="bg-surface dark:bg-surface-dark rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                Total Linkuri
              </p>
              <p className="text-2xl font-bold text-primary dark:text-primary-dark mt-1">
                {totalItems}
              </p>
            </div>
            <div className="p-3 bg-blue-100 dark:bg-blue-900 rounded-full">
              <svg
                className="w-6 h-6 text-blue-600 dark:text-blue-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                />
              </svg>
            </div>
          </div>
        </div>

        <div className="bg-surface dark:bg-surface-dark rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                Total Clicuri
              </p>
              <p className="text-2xl font-bold text-primary dark:text-primary-dark mt-1">
                {shortLinksData
                  .reduce((sum, link) => sum + link.clickCount, 0)
                  .toLocaleString('ro-RO')}
              </p>
            </div>
            <div className="p-3 bg-green-100 dark:bg-green-900 rounded-full">
              <svg
                className="w-6 h-6 text-green-600 dark:text-green-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"
                />
              </svg>
            </div>
          </div>
        </div>

        <div className="bg-surface dark:bg-surface-dark rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                Media pe Link
              </p>
              <p className="text-2xl font-bold text-primary dark:text-primary-dark mt-1">
                {totalItems > 0
                  ? Math.round(
                      shortLinksData.reduce(
                        (sum, link) => sum + link.clickCount,
                        0
                      ) / totalItems
                    ).toLocaleString('ro-RO')
                  : 0}
              </p>
            </div>
            <div className="p-3 bg-purple-100 dark:bg-purple-900 rounded-full">
              <svg
                className="w-6 h-6 text-purple-600 dark:text-purple-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                />
              </svg>
            </div>
          </div>
        </div>
      </div>

      {/* Error Message */}
      {error && (
        <div className="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <p className="text-sm text-red-600 dark:text-red-400">
            <strong>Eroare:</strong> {error}
          </p>
        </div>
      )}

      {/* Short Links Table */}
      <div className="bg-surface dark:bg-surface-dark relative shadow-md sm:rounded-lg overflow-hidden">
        <ShortLinksTable
          shortLinks={shortLinksData}
          totalItems={totalItems}
          locale={locale}
          accessToken={token}
        />
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="mt-8 flex justify-center">
          <nav className="flex items-center gap-2">
            {currentPage > 1 && (
              <Link
                href={`/${locale}/admin/short-links?page=${currentPage - 1}`}
                className="px-3 py-2 text-sm font-medium text-primary bg-surface border border-gray-300 rounded-lg hover:bg-gray-100 dark:bg-surface-dark dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700"
              >
                Anterior
              </Link>
            )}

            <span className="px-4 py-2 text-sm text-primary dark:text-gray-400">
              Pagina {currentPage} din {totalPages}
            </span>

            {currentPage < totalPages && (
              <Link
                href={`/${locale}/admin/short-links?page=${currentPage + 1}`}
                className="px-3 py-2 text-sm font-medium text-primary bg-surface border border-gray-300 rounded-lg hover:bg-gray-100 dark:bg-surface-dark dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700"
              >
                Următor
              </Link>
            )}
          </nav>
        </div>
      )}
    </div>
  );
}
