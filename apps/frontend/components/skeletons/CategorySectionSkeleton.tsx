/**
 * CategorySection Skeleton Component
 * Loading placeholder for category article sections
 */

import React from 'react';

interface CategorySectionSkeletonProps {
  showHeader?: boolean;
  articleCount?: number;
}

export function CategorySectionSkeleton({
  showHeader = true,
  articleCount = 6,
}: CategorySectionSkeletonProps) {
  return (
    <div className="container-deschide animate-pulse">
      {/* Section header */}
      {showHeader && (
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-3">
            <div className="h-8 w-32 bg-gray-200 rounded" />
            <div className="h-6 w-6 bg-gray-200 rounded" />
          </div>
          <div className="h-5 w-20 bg-gray-100 rounded hidden sm:block" />
        </div>
      )}

      {/* Desktop: Featured + Grid */}
      <div className="hidden lg:grid grid-cols-12 gap-6">
        {/* Featured article */}
        <div className="col-span-6">
          <div className="aspect-[16/10] bg-gray-200 rounded-xl mb-4" />
          <div className="space-y-3">
            <div className="h-6 bg-gray-200 rounded w-full" />
            <div className="h-6 bg-gray-200 rounded w-4/5" />
            <div className="h-5 bg-gray-100 rounded w-full" />
            <div className="h-5 bg-gray-100 rounded w-2/3" />
            <div className="flex gap-4 pt-2">
              <div className="h-4 bg-gray-100 rounded w-20" />
              <div className="h-4 bg-gray-100 rounded w-16" />
            </div>
          </div>
        </div>

        {/* Grid of smaller articles */}
        <div className="col-span-6 grid grid-cols-2 gap-4">
          {Array.from({ length: Math.min(articleCount - 1, 4) }).map((_, i) => (
            <div key={i} className="space-y-2">
              <div className="aspect-[16/10] bg-gray-200 rounded-lg" />
              <div className="h-5 bg-gray-200 rounded w-full" />
              <div className="h-5 bg-gray-200 rounded w-3/4" />
              <div className="h-3 bg-gray-100 rounded w-20" />
            </div>
          ))}
        </div>
      </div>

      {/* Tablet: 3 columns */}
      <div className="hidden md:grid lg:hidden grid-cols-3 gap-4">
        {Array.from({ length: Math.min(articleCount, 6) }).map((_, i) => (
          <div key={i} className="space-y-2">
            <div className="aspect-[16/10] bg-gray-200 rounded-lg" />
            <div className="h-5 bg-gray-200 rounded w-full" />
            <div className="h-5 bg-gray-200 rounded w-2/3" />
          </div>
        ))}
      </div>

      {/* Mobile: Horizontal scroll or stack */}
      <div className="md:hidden">
        {/* Featured */}
        <div className="space-y-3 mb-4">
          <div className="aspect-[16/9] bg-gray-200 rounded-lg" />
          <div className="h-5 bg-gray-200 rounded w-full" />
          <div className="h-5 bg-gray-200 rounded w-3/4" />
        </div>

        {/* Horizontal scroll */}
        <div className="flex gap-4 overflow-hidden">
          {[1, 2, 3].map((i) => (
            <div key={i} className="w-48 shrink-0 space-y-2">
              <div className="aspect-[16/10] bg-gray-200 rounded-lg" />
              <div className="h-4 bg-gray-200 rounded w-full" />
              <div className="h-4 bg-gray-200 rounded w-2/3" />
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

export default CategorySectionSkeleton;
