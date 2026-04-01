import Link from 'next/link';
import UsersTable from './components/UsersTable';
import { getUsers } from '@/lib/dal';

interface UsersPageProps {
  params: Promise<{
    locale: string;
  }>;
  searchParams: Promise<{
    page?: string;
  }>;
}

export default async function UsersPage({ params, searchParams }: UsersPageProps) {
  const { locale } = await params;
  const { page: pageParam } = await searchParams;

  const currentPage = pageParam ? parseInt(pageParam, 10) : 1;
  const itemsPerPage = 20;

  let usersData: any[] = [];
  let totalItems = 0;
  let error: string | null = null;

  try {
    const data = await getUsers({ page: currentPage, itemsPerPage });
    usersData = data.member || [];
    totalItems = data.totalItems || 0;
  } catch (err) {
    console.error('Failed to fetch users:', err);
    error = err instanceof Error ? err.message : 'Failed to load users';
    usersData = [];
  }

  const totalPages = Math.ceil(totalItems / itemsPerPage);

  return (
    <div className="p-4">
      {/* Page Header */}
      <div className="mb-4 flex items-center justify-between">
        <div>
          <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 mb-2">
            <Link href={`/${locale}/admin`} className="hover:text-blue-600">
              Dashboard
            </Link>
            <span>/</span>
            <span>Users</span>
          </div>
          <h1 className="text-2xl font-semibold text-primary dark:text-primary-dark">
            Users
          </h1>
          <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Manage user accounts and permissions
          </p>
        </div>
        <div>
          <Link
            href={`/${locale}/admin/users/new`}
            className="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800 inline-block"
          >
            New User
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

      {/* Users Table */}
      <div className="bg-surface dark:bg-surface-dark relative shadow-md sm:rounded-lg overflow-hidden">
        <UsersTable users={usersData} totalItems={totalItems} locale={locale} />
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="mt-4 flex justify-center gap-2">
          {currentPage > 1 && (
            <Link
              href={`/${locale}/admin/users?page=${currentPage - 1}`}
              className="px-4 py-2 text-sm font-medium text-primary bg-surface border border-gray-300 rounded-lg hover:bg-surface-sunken dark:bg-surface-dark dark:text-primary-dark dark:border-gray-600 dark:hover:bg-gray-700"
            >
              Previous
            </Link>
          )}
          <span className="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">
            Page {currentPage} of {totalPages}
          </span>
          {currentPage < totalPages && (
            <Link
              href={`/${locale}/admin/users?page=${currentPage + 1}`}
              className="px-4 py-2 text-sm font-medium text-primary bg-surface border border-gray-300 rounded-lg hover:bg-surface-sunken dark:bg-surface-dark dark:text-primary-dark dark:border-gray-600 dark:hover:bg-gray-700"
            >
              Next
            </Link>
          )}
        </div>
      )}
    </div>
  );
}
