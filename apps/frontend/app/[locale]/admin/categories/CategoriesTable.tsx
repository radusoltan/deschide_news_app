import Link from 'next/link';
import { Checkbox, Badge } from 'flowbite-react';
import { getCategories, type Category } from '@/lib/dal';

interface CategoriesTableProps {
  locale: string;
}

export default async function CategoriesTable({ locale }: CategoriesTableProps) {
  let data;
  let error;

  try {
    data = await getCategories({ locale, page: 1, itemsPerPage: 30 });
  } catch (err) {
    error = err instanceof Error ? err.message : 'Failed to fetch categories';
  }

  if (error) {
    return (
      <div className="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400">
        <span className="font-medium">Error:</span> {error}
      </div>
    );
  }

  if (!data || data.member.length === 0) {
    return (
      <div className="p-8 text-center text-gray-500 dark:text-gray-400">
        <p className="text-lg mb-2">No categories found</p>
        <p className="text-sm">Create your first category to get started.</p>
      </div>
    );
  }

  const categories = data.member;
  const totalItems = data.totalItems;

  const getStatusBadge = (status?: string) => {
    switch (status) {
      case 'active':
        return <Badge color="success">Active</Badge>;
      case 'inactive':
        return <Badge color="gray">Inactive</Badge>;
      default:
        return <Badge color="gray">{status || 'Unknown'}</Badge>;
    }
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
    <div className="overflow-x-auto">
      <div className="relative overflow-x-auto shadow-md sm:rounded-lg">
        <table className="w-full text-sm text-left text-gray-500 dark:text-gray-400">
          <thead className="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
              <th scope="col" className="p-4">
                <Checkbox />
              </th>
              <th scope="col" className="px-6 py-3">
                Title
              </th>
              <th scope="col" className="px-6 py-3">
                Slug
              </th>
              <th scope="col" className="px-6 py-3">
                Status
              </th>
              <th scope="col" className="px-6 py-3">
                On Front Page
              </th>
              <th scope="col" className="px-6 py-3">
                Created
              </th>
              <th scope="col" className="px-6 py-3">
                <span className="sr-only">Actions</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {categories.map((category) => (
              <tr
                key={category.id}
                className="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600"
              >
                <td className="w-4 p-4">
                  <Checkbox />
                </td>
                <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                  {category.title}
                </td>
                <td className="px-6 py-4 text-gray-500 dark:text-gray-400">
                  {category.slug}
                </td>
                <td className="px-6 py-4">{getStatusBadge(category.status)}</td>
                <td className="px-6 py-4">
                  {category.onFrontPage ? (
                    <Badge color="info">Yes</Badge>
                  ) : (
                    <span className="text-gray-400">No</span>
                  )}
                </td>
                <td className="px-6 py-4">{formatDate(category.createdAt)}</td>
                <td className="px-6 py-4 text-right">
                  <Link
                    href={`/${locale}/admin/categories/${category.id}/edit`}
                    className="font-medium text-blue-600 hover:underline dark:text-blue-500"
                  >
                    Edit
                  </Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Total count */}
      <div className="mt-4 text-sm text-gray-600 dark:text-gray-400">
        Showing {categories.length} of {totalItems} categories
      </div>
    </div>
  );
}
