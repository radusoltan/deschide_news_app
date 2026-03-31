import { Suspense } from 'react';
import Link from 'next/link';
import { Spinner } from 'flowbite-react';
import CategoriesTable from './CategoriesTable';
import { CategoriesPagination } from './components/CategoriesPagination';
import FrontPageOrder from './components/FrontPageOrder';
import { getCategories } from '@/lib/dal';

interface CategoriesPageProps {
  params: Promise<{
    locale: string;
  }>;
  searchParams: Promise<{
    page?: string;
  }>;
}

export default async function CategoriesPage({ params, searchParams }: CategoriesPageProps) {
  const { locale } = await params;
  const { page: pageParam } = await searchParams;

  const currentPage = pageParam ? parseInt(pageParam, 10) : 1;
  const itemsPerPage = 50; // 50 categories per page (matches API default)

  // Fetch categories
  let categoriesData: any[] = [];
  let totalItems = 0;
  let error: string | null = null;

  try {
    const data = await getCategories({ locale, page: currentPage, itemsPerPage });
    categoriesData = data.member || [];
    totalItems = data.totalItems || 0;
  } catch (err) {
    console.error('Failed to fetch categories:', err);
    error = err instanceof Error ? err.message : 'Failed to load categories';
    categoriesData = [];
  }

  const totalPages = Math.ceil(totalItems / itemsPerPage);

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

      {/* Error Message */}
      {error && (
        <div className="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <p className="text-sm text-red-600 dark:text-red-400">
            <strong>Error:</strong> {error}
          </p>
        </div>
      )}

      {/* Categories Table */}
      <div className="bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg overflow-hidden">
        <CategoriesTable categories={categoriesData} totalItems={totalItems} locale={locale} />
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="mt-8 flex justify-center">
          <CategoriesPagination
            currentPage={currentPage}
            totalPages={totalPages}
            locale={locale}
          />
        </div>
      )}

      {/* Front Page Order Section */}
      {(() => {
        const frontPageCategories = categoriesData
          .filter((cat: any) => cat.onFrontPage)
          .map((cat: any) => ({
            id: cat.id,
            title: cat.title,
            slug: cat.slug,
            frontPagePosition: cat.frontPagePosition ?? 0,
          }));

        if (frontPageCategories.length === 0) return null;

        return (
          <div className="mt-8 bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg overflow-hidden">
            <FrontPageOrder categories={frontPageCategories} locale={locale} />
          </div>
        );
      })()}
    </div>
  );
}
