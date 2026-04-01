'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import ArticleForm from '../../../components/ArticleForm';
import ArticleLockBanner from '@/components/admin/articles/ArticleLockBanner';
import { useArticleLock } from '@/lib/hooks/useArticleLock';
import { FiLock, FiAlertCircle } from 'react-icons/fi';

interface ArticleEditWrapperProps {
  locale: string;
  article: any;
  categories: any[];
  authors: any[];
}

export default function ArticleEditWrapper({
  locale,
  article,
  categories,
  authors,
}: ArticleEditWrapperProps) {
  const router = useRouter();
  const [showLockError, setShowLockError] = useState(false);

  const {
    lockInfo,
    isLoading,
    error,
    isLockedByOther,
    isLockedByCurrentUser,
  } = useArticleLock({
    articleId: article.id,
    autoAcquire: true,
    heartbeatInterval: 60000, // 1 minute
    onLockFailed: (err) => {
      console.error('Failed to acquire lock:', err);
      setShowLockError(true);
    },
    onLockLost: () => {
      alert('Your editing lock has expired. Please refresh the page to continue editing.');
    },
  });

  // Show loading state while acquiring lock
  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
          <p className="text-gray-600 dark:text-gray-400">Checking edit availability...</p>
        </div>
      </div>
    );
  }

  // Show error if lock acquisition failed
  if (showLockError && error) {
    return (
      <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-6">
        <div className="flex items-start">
          <FiAlertCircle className="h-6 w-6 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
          <div className="ml-3 flex-1">
            <h3 className="text-lg font-medium text-red-800 dark:text-red-200">
              Cannot Edit Article
            </h3>
            <p className="mt-2 text-sm text-red-700 dark:text-red-300">
              {error.message}
            </p>
            <div className="mt-4 flex gap-3">
              <button
                onClick={() => router.push(`/${locale}/admin/articles`)}
                className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
              >
                Back to Articles List
              </button>
              <button
                onClick={() => router.refresh()}
                className="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
              >
                Try Again
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // Show banner if locked by another user
  if (isLockedByOther && lockInfo?.lockedBy) {
    return (
      <div>
        <ArticleLockBanner
          lockedBy={lockInfo.lockedBy}
          lockedAt={lockInfo.lockedAt!}
          expiresAt={lockInfo.expiresAt!}
        />
        <div className="bg-gray-100 dark:bg-surface-dark rounded-lg p-8 text-center">
          <FiLock className="h-16 w-16 text-gray-400 mx-auto mb-4" />
          <h3 className="text-lg font-medium text-primary dark:text-primary-dark mb-2">
            Article Locked for Editing
          </h3>
          <p className="text-gray-600 dark:text-gray-400 mb-6">
            This article is currently being edited by another user. The form is read-only until the lock is released.
          </p>
          <button
            onClick={() => router.push(`/${locale}/admin/articles`)}
            className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            Back to Articles List
          </button>
        </div>
      </div>
    );
  }

  // Show form with active lock indicator
  return (
    <div>
      {isLockedByCurrentUser && (
        <div className="mb-4 bg-green-50 dark:bg-green-900/20 border-l-4 border-green-400 dark:border-green-500 p-4 rounded-r-lg">
          <div className="flex items-center">
            <FiLock className="h-5 w-5 text-green-400 dark:text-green-500 mr-2" />
            <p className="text-sm text-green-800 dark:text-green-200">
              You have exclusive editing access to this article. Lock will auto-refresh every minute.
            </p>
          </div>
        </div>
      )}

      <ArticleForm
        locale={locale}
        article={article}
        categories={categories}
        authors={authors}
      />
    </div>
  );
}
