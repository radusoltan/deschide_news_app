/**
 * ArticleCard Skeleton Component
 * Loading placeholder for article cards
 */

import React from 'react';

interface ArticleCardSkeletonProps {
  variant?: 'default' | 'compact' | 'featured' | 'horizontal';
  showImage?: boolean;
  showCategory?: boolean;
  showMeta?: boolean;
}

export function ArticleCardSkeleton({
  variant = 'default',
  showImage = true,
  showCategory = true,
  showMeta = true,
}: ArticleCardSkeletonProps) {
  if (variant === 'horizontal') {
    return (
      <div className="flex gap-4 animate-pulse">
        {showImage && (
          <div className="w-32 h-24 bg-gray-200 rounded-lg shrink-0" />
        )}
        <div className="flex-1 space-y-2">
          {showCategory && (
            <div className="h-4 w-16 bg-gray-200 rounded" />
          )}
          <div className="h-5 bg-gray-200 rounded w-full" />
          <div className="h-5 bg-gray-200 rounded w-3/4" />
          {showMeta && (
            <div className="h-3 bg-gray-100 rounded w-24" />
          )}
        </div>
      </div>
    );
  }

  if (variant === 'compact') {
    return (
      <div className="animate-pulse space-y-2">
        {showImage && (
          <div className="aspect-[16/10] bg-gray-200 rounded-lg" />
        )}
        <div className="space-y-2">
          <div className="h-4 bg-gray-200 rounded w-full" />
          <div className="h-4 bg-gray-200 rounded w-2/3" />
        </div>
      </div>
    );
  }

  if (variant === 'featured') {
    return (
      <div className="animate-pulse">
        <div className="aspect-[16/9] bg-gray-200 rounded-xl mb-4" />
        <div className="space-y-3">
          {showCategory && (
            <div className="h-5 w-20 bg-gray-200 rounded" />
          )}
          <div className="h-7 bg-gray-200 rounded w-full" />
          <div className="h-7 bg-gray-200 rounded w-4/5" />
          <div className="h-5 bg-gray-100 rounded w-full" />
          <div className="h-5 bg-gray-100 rounded w-3/4" />
          {showMeta && (
            <div className="flex gap-4 pt-2">
              <div className="h-4 bg-gray-100 rounded w-24" />
              <div className="h-4 bg-gray-100 rounded w-20" />
            </div>
          )}
        </div>
      </div>
    );
  }

  // Default variant
  return (
    <div className="animate-pulse">
      {showImage && (
        <div className="aspect-[16/10] bg-gray-200 rounded-lg mb-3" />
      )}
      <div className="space-y-2">
        {showCategory && (
          <div className="h-4 w-16 bg-gray-200 rounded" />
        )}
        <div className="h-5 bg-gray-200 rounded w-full" />
        <div className="h-5 bg-gray-200 rounded w-4/5" />
        {showMeta && (
          <div className="h-3 bg-gray-100 rounded w-28 mt-2" />
        )}
      </div>
    </div>
  );
}

interface ArticleCardGridSkeletonProps {
  count?: number;
  columns?: 1 | 2 | 3 | 4;
  variant?: 'default' | 'compact' | 'horizontal';
}

export function ArticleCardGridSkeleton({
  count = 6,
  columns = 3,
  variant = 'default',
}: ArticleCardGridSkeletonProps) {
  const gridCols = {
    1: 'grid-cols-1',
    2: 'grid-cols-1 sm:grid-cols-2',
    3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
    4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
  };

  if (variant === 'horizontal') {
    return (
      <div className="space-y-4">
        {Array.from({ length: count }).map((_, i) => (
          <ArticleCardSkeleton key={i} variant="horizontal" />
        ))}
      </div>
    );
  }

  return (
    <div className={`grid ${gridCols[columns]} gap-6`}>
      {Array.from({ length: count }).map((_, i) => (
        <ArticleCardSkeleton key={i} variant={variant} />
      ))}
    </div>
  );
}

export default ArticleCardSkeleton;
