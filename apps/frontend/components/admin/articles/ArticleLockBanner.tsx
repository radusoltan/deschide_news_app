'use client';

import { FiLock, FiAlertTriangle } from 'react-icons/fi';

interface ArticleLockBannerProps {
  lockedBy: {
    firstName: string;
    lastName: string;
    email: string;
  };
  lockedAt: string;
  expiresAt: string;
}

export default function ArticleLockBanner({ lockedBy, lockedAt, expiresAt }: ArticleLockBannerProps) {
  const lockedDate = new Date(lockedAt);
  const expiresDate = new Date(expiresAt);
  const now = new Date();
  const minutesRemaining = Math.floor((expiresDate.getTime() - now.getTime()) / 60000);

  return (
    <div className="mb-6 bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-400 dark:border-yellow-500 p-4 rounded-r-lg">
      <div className="flex items-start">
        <div className="flex-shrink-0">
          <FiAlertTriangle className="h-6 w-6 text-yellow-400 dark:text-yellow-500" />
        </div>
        <div className="ml-3 flex-1">
          <h3 className="text-sm font-medium text-yellow-800 dark:text-yellow-200 flex items-center gap-2">
            <FiLock className="h-4 w-4" />
            Article is Being Edited
          </h3>
          <div className="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
            <p>
              <strong>{lockedBy.firstName} {lockedBy.lastName}</strong> ({lockedBy.email}) is currently editing this article.
            </p>
            <p className="mt-1 text-xs">
              Lock acquired at {lockedDate.toLocaleTimeString()} • Expires in ~{minutesRemaining} minutes
            </p>
          </div>
          <div className="mt-3 text-xs text-yellow-600 dark:text-yellow-400">
            You cannot edit this article until the lock is released or expires.
          </div>
        </div>
      </div>
    </div>
  );
}
