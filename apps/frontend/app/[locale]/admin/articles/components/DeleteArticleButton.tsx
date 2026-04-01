'use client';

import { useState, useTransition } from 'react';
import { HiTrash, HiX, HiExclamation } from 'react-icons/hi';
import { deleteArticleAction } from '@/app/actions/articles';
import { useRouter } from 'next/navigation';

interface DeleteArticleButtonProps {
  articleId: number;
  articleTitle: string;
  locale: string;
}

export function DeleteArticleButton({
  articleId,
  articleTitle,
  locale,
}: DeleteArticleButtonProps) {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isPending, startTransition] = useTransition();
  const [error, setError] = useState<string | null>(null);
  const router = useRouter();

  const handleDelete = async () => {
    setError(null);

    startTransition(async () => {
      const result = await deleteArticleAction(articleId, locale);

      if (result.success) {
        setIsModalOpen(false);
        router.refresh();
      } else if (result.errors?._form) {
        setError(result.errors._form[0]);
      }
    });
  };

  return (
    <>
      <button
        onClick={() => setIsModalOpen(true)}
        className="inline-flex items-center text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 font-medium"
        disabled={isPending}
      >
        <HiTrash className="w-4 h-4 mr-1" />
        Delete
      </button>

      {isModalOpen && (
        <div className="fixed inset-0 z-50 overflow-y-auto">
          {/* Backdrop */}
          <div
            className="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
            onClick={() => !isPending && setIsModalOpen(false)}
          />

          {/* Modal */}
          <div className="flex min-h-full items-center justify-center p-4">
            <div className="relative bg-surface dark:bg-surface-dark rounded-lg shadow-xl max-w-md w-full">
              {/* Header */}
              <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                <div className="flex items-center gap-3">
                  <div className="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center">
                    <HiExclamation className="w-6 h-6 text-red-600 dark:text-red-400" />
                  </div>
                  <h3 className="text-lg font-semibold text-primary dark:text-primary-dark">
                    Delete Article
                  </h3>
                </div>
                <button
                  onClick={() => setIsModalOpen(false)}
                  disabled={isPending}
                  className="text-gray-400 hover:text-gray-600 dark:hover:text-primary-dark"
                >
                  <HiX className="w-6 h-6" />
                </button>
              </div>

              {/* Body */}
              <div className="p-6">
                <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
                  Are you sure you want to delete this article? This action cannot be undone.
                </p>
                <div className="bg-surface-sunken dark:bg-gray-700 rounded-lg p-4 mb-4">
                  <p className="text-sm font-medium text-primary dark:text-primary-dark">
                    {articleTitle}
                  </p>
                  <p className="text-xs text-secondary dark:text-gray-400 mt-1">
                    ID: {articleId}
                  </p>
                </div>

                {error && (
                  <div className="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                    <p className="text-sm text-red-600 dark:text-red-400">{error}</p>
                  </div>
                )}
              </div>

              {/* Footer */}
              <div className="flex items-center justify-end gap-3 p-4 border-t border-gray-200 dark:border-gray-700">
                <button
                  onClick={() => setIsModalOpen(false)}
                  disabled={isPending}
                  className="px-4 py-2 text-sm font-medium text-primary dark:text-primary-dark bg-surface dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-surface-sunken dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Cancel
                </button>
                <button
                  onClick={handleDelete}
                  disabled={isPending}
                  className="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                >
                  {isPending ? (
                    <>
                      <svg
                        className="animate-spin h-4 w-4 text-white"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                      >
                        <circle
                          className="opacity-25"
                          cx="12"
                          cy="12"
                          r="10"
                          stroke="currentColor"
                          strokeWidth="4"
                        ></circle>
                        <path
                          className="opacity-75"
                          fill="currentColor"
                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                      </svg>
                      Deleting...
                    </>
                  ) : (
                    <>
                      <HiTrash className="w-4 h-4" />
                      Delete Article
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
