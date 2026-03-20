'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Checkbox, Badge } from 'flowbite-react';
import { type Category } from '@/lib/dal';
import DeleteCategoryModal from './components/DeleteCategoryModal';

interface CategoriesTableProps {
  categories: any[];
  totalItems: number;
  locale: string;
}

export default function CategoriesTable({ categories, totalItems, locale }: CategoriesTableProps) {
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState<{ id: number; title: string } | null>(null);

  if (!categories || categories.length === 0) {
    return (
      <div className="p-8 text-center text-gray-500 dark:text-gray-400">
        <p className="text-lg mb-2">No categories found</p>
        <p className="text-sm">Create your first category to get started.</p>
      </div>
    );
  }

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

  const handleDeleteClick = (category: { id: number; title: string }) => {
    setSelectedCategory(category);
    setDeleteModalOpen(true);
  };

  const handleCloseDeleteModal = () => {
    setDeleteModalOpen(false);
    setSelectedCategory(null);
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
                Parent
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
                  {category.parent && (
                    <span className="text-gray-400 dark:text-gray-500 mr-1">&mdash;</span>
                  )}
                  {category.title}
                </td>
                <td className="px-6 py-4 text-gray-500 dark:text-gray-400">
                  {category.slug}
                </td>
                <td className="px-6 py-4 text-gray-500 dark:text-gray-400">
                  {category.parent
                    ? (typeof category.parent === 'object' && category.parent !== null
                        ? category.parent.title || '-'
                        : category.parent)
                    : <span className="text-gray-300 dark:text-gray-600">-</span>}
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
                  <div className="flex items-center justify-end gap-3">
                    <Link
                      href={`/${locale}/admin/categories/${category.id}/edit`}
                      className="font-medium text-blue-600 hover:underline dark:text-blue-500"
                    >
                      Edit
                    </Link>
                    <button
                      type="button"
                      onClick={() => handleDeleteClick({ id: category.id, title: category.title })}
                      className="font-medium text-red-600 hover:underline dark:text-red-500"
                    >
                      Delete
                    </button>
                  </div>
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

      {/* Delete Modal */}
      {selectedCategory && (
        <DeleteCategoryModal
          isOpen={deleteModalOpen}
          onClose={handleCloseDeleteModal}
          category={selectedCategory}
          locale={locale}
        />
      )}
    </div>
  );
}
