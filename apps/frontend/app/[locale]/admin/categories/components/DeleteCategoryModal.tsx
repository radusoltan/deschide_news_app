'use client';

import { useState } from 'react';
import { FiX, FiAlertTriangle } from 'react-icons/fi';
import { deleteCategoryAction } from '@/app/actions/categories';
import { useRouter } from 'next/navigation';

interface DeleteCategoryModalProps {
  isOpen: boolean;
  onClose: () => void;
  category: {
    id: number;
    title: string;
  };
  locale: string;
}

export default function DeleteCategoryModal({
  isOpen,
  onClose,
  category,
  locale,
}: DeleteCategoryModalProps) {
  const router = useRouter();
  const [isDeleting, setIsDeleting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleDelete = async () => {
    setError(null);
    setIsDeleting(true);

    try {
      const result = await deleteCategoryAction(category.id, locale);

      if (result.success) {
        // Close modal and refresh the page
        onClose();
        router.refresh();
      } else {
        setError(result.error || 'Failed to delete category');
        setIsDeleting(false);
      }
    } catch (err) {
      console.error('Failed to delete category:', err);
      setError(err instanceof Error ? err.message : 'Failed to delete category');
      setIsDeleting(false);
    }
  };

  const handleClose = () => {
    if (!isDeleting) {
      setError(null);
      onClose();
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50">
      <div className="bg-surface dark:bg-surface-dark rounded-lg shadow-xl w-full max-w-md">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
          <div className="flex items-center gap-3">
            <div className="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/20 flex items-center justify-center">
              <FiAlertTriangle className="w-5 h-5 text-red-600 dark:text-red-500" />
            </div>
            <h2 className="text-xl font-semibold text-primary dark:text-primary-dark">
              Delete Category
            </h2>
          </div>
          <button
            onClick={handleClose}
            disabled={isDeleting}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-primary-dark transition-colors disabled:opacity-50"
          >
            <FiX className="w-6 h-6" />
          </button>
        </div>

        {/* Content */}
        <div className="p-6 space-y-4">
          {/* Error Message */}
          {error && (
            <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
              <p className="text-sm text-red-600 dark:text-red-400">{error}</p>
            </div>
          )}

          {/* Warning Message */}
          <div className="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
            <p className="text-sm text-yellow-800 dark:text-yellow-300">
              <strong>Warning:</strong> This action cannot be undone. If this category has
              associated articles, you may not be able to delete it.
            </p>
          </div>

          {/* Confirmation Text */}
          <p className="text-primary dark:text-primary-dark">
            Are you sure you want to delete the category{' '}
            <span className="font-semibold">&quot;{category.title}&quot;</span>?
          </p>
        </div>

        {/* Actions */}
        <div className="flex items-center justify-end gap-3 p-6 border-t border-gray-200 dark:border-gray-700">
          <button
            type="button"
            onClick={handleClose}
            disabled={isDeleting}
            className="px-6 py-2 text-sm font-medium text-primary dark:text-primary-dark bg-surface dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-surface-sunken dark:hover:bg-gray-600 focus:outline-none focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Cancel
          </button>
          <button
            type="button"
            onClick={handleDelete}
            disabled={isDeleting}
            className="px-6 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-300 dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isDeleting ? 'Deleting...' : 'Delete Category'}
          </button>
        </div>
      </div>
    </div>
  );
}
