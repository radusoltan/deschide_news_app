import { Suspense } from 'react';
import Link from 'next/link';
import { Spinner } from 'flowbite-react';
import CategoriesTable from './CategoriesTable';

interface CategoriesPageProps {
  params: Promise<{
    locale: string;
  }>;
}

export default async function CategoriesPage({ params }: CategoriesPageProps) {
  const { locale } = await params;

  return (
    <div className="p-4">
      {/* Page Header */}
      <div className="mb-4 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">
            Categories
          </h1>
          <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Manage article categories
          </p>
        </div>
        <div>
          <Link
            href={`/${locale}/admin/categories/new`}
            className="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800 inline-block"
          >
            Create Category
          </Link>
        </div>
      </div>

      {/* Categories Table */}
      <div className="bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg overflow-hidden">
        <Suspense
          fallback={
            <div className="flex justify-center items-center py-12">
              <Spinner size="xl" />
            </div>
          }
        >
          <CategoriesTable locale={locale} />
        </Suspense>
      </div>
    </div>
  );
}
