/**
 * TrendingArticles Skeleton Component
 * Loading placeholder for the trending articles section
 */

import React from 'react';

export function TrendingArticlesSkeleton() {
  return (
    <div className="container-deschide animate-pulse">
      {/* Section header */}
      <div className="flex items-center gap-3 mb-6">
        <div className="h-6 w-6 bg-gray-200 rounded" />
        <div className="h-7 w-36 bg-gray-200 rounded" />
      </div>

      {/* Desktop: Horizontal list with numbers */}
      <div className="hidden lg:grid grid-cols-5 gap-6">
        {[1, 2, 3, 4, 5].map((i) => (
          <div key={i} className="relative">
            {/* Rank number */}
            <div className="absolute -top-4 -left-2 h-12 w-10 bg-gray-100 rounded text-center" />
            <div className="ml-6 space-y-2">
              <div className="aspect-[16/10] bg-gray-200 rounded-lg" />
              <div className="h-4 w-14 bg-gray-200 rounded" />
              <div className="h-5 bg-gray-200 rounded w-full" />
              <div className="h-5 bg-gray-200 rounded w-3/4" />
              <div className="h-3 bg-gray-100 rounded w-20" />
            </div>
          </div>
        ))}
      </div>

      {/* Tablet: 3 columns */}
      <div className="hidden md:grid lg:hidden grid-cols-3 gap-4">
        {[1, 2, 3].map((i) => (
          <div key={i} className="flex gap-3">
            <div className="h-12 w-8 bg-gray-100 rounded flex items-center justify-center shrink-0" />
            <div className="flex-1 space-y-2">
              <div className="aspect-[16/10] bg-gray-200 rounded-lg" />
              <div className="h-5 bg-gray-200 rounded w-full" />
              <div className="h-5 bg-gray-200 rounded w-2/3" />
            </div>
          </div>
        ))}
      </div>

      {/* Mobile: Numbered list */}
      <div className="md:hidden space-y-4">
        {[1, 2, 3, 4, 5].map((i) => (
          <div
            key={i}
            className="flex gap-4 items-start py-3 border-b last:border-0"
          >
            {/* Rank number */}
            <div className="h-10 w-8 bg-gray-100 rounded flex items-center justify-center shrink-0" />
            {/* Article info */}
            <div className="flex-1 space-y-2">
              <div className="h-4 w-16 bg-gray-200 rounded" />
              <div className="h-5 bg-gray-200 rounded w-full" />
              <div className="h-5 bg-gray-200 rounded w-4/5" />
            </div>
            {/* Thumbnail */}
            <div className="w-20 h-14 bg-gray-200 rounded-lg shrink-0" />
          </div>
        ))}
      </div>
    </div>
  );
}

export default TrendingArticlesSkeleton;
