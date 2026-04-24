import { Suspense } from 'react';
import Link from 'next/link';
import { Spinner } from 'flowbite-react';
import AuthorsTable from './AuthorsTable';
import { AuthorsPagination } from './components/AuthorsPagination';
import { getAuthors, type Author } from '@/lib/dal';

interface AuthorsPageProps {
  params: Promise<{
    locale: string;
  }>;
  searchParams: Promise<{
    page?: string;
  }>;
}

export default async function AuthorsPage({ params, searchParams }: AuthorsPageProps) {
  const { locale } = await params;
  const { page: pageParam } = await searchParams;

  const currentPage = pageParam ? parseInt(pageParam, 10) : 1;
  const itemsPerPage = 30; // 30 authors per page

  // Fetch authors
  let authorsData: Author[] = [];
  let totalItems = 0;
  let error: string | null = null;

  try {
    const data = await getAuthors({ locale, page: currentPage, itemsPerPage });
    authorsData = data.member || [];
    totalItems = data.totalItems || 0;
  } catch (err) {
    console.error('Failed to fetch authors:', err);
    error = err instanceof Error ? err.message : 'Failed to load authors';
    authorsData = [];
  }

  const totalPages = Math.ceil(totalItems / itemsPerPage);

  return (
    <div className="p-4">
      {/* Page Header */}
      <div className="mb-4 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-primary dark:text-primary-dark">
            Authors
          </h1>
          <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Manage article authors
          </p>
        </div>
        <div>
          <Link
            href={`/${locale}/admin/authors/new`}
            className="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800 inline-block"
          >
            Create Author
          </Link>
        </div>
      </div>

      {/* Error Message */}
      {error && (
        <div className="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <p className="text-sm text-red-600 dark:text-red-400">
            <strong>Error:</strong> {error}
          </p>
        </div>
      )}

      {/* Authors Table */}
      <div className="bg-surface dark:bg-surface-dark relative shadow-md sm:rounded-lg overflow-hidden">
        <AuthorsTable authors={authorsData} totalItems={totalItems} locale={locale} />
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="mt-8 flex justify-center">
          <AuthorsPagination
            currentPage={currentPage}
            totalPages={totalPages}
            locale={locale}
          />
        </div>
      )}
    </div>
  );
}
