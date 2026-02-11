/**
 * SpecialArticles Skeleton Component
 * Loading placeholder for breaking/alert/flash news ticker
 */

import React from 'react';

export function SpecialArticlesSkeleton() {
  return (
    <div className="container-deschide animate-pulse">
      <div className="flex flex-col sm:flex-row gap-3">
        {/* Badge */}
        <div className="flex items-center gap-2 shrink-0">
          <div className="h-6 w-6 bg-red-200 rounded" />
          <div className="h-6 w-24 bg-red-200 rounded" />
        </div>

        {/* Articles ticker */}
        <div className="flex-1 overflow-hidden">
          <div className="flex gap-4">
            {[1, 2, 3].map((i) => (
              <div
                key={i}
                className="flex items-center gap-3 px-4 py-2 bg-gray-100 rounded-lg shrink-0"
              >
                <div className="h-5 w-5 bg-gray-200 rounded-full" />
                <div className="h-5 w-48 bg-gray-200 rounded" />
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

export default SpecialArticlesSkeleton;
