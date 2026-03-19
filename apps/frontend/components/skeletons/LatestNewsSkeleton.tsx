/**
 * LatestNews Skeleton Component
 * Loading placeholder for the latest news section
 */

import React from 'react';

export function LatestNewsSkeleton() {
  return (
    <div className="container-deschide animate-pulse">
      {/* Section header */}
      <div className="flex items-center justify-between mb-6">
        <div className="h-8 w-40 bg-gray-200 rounded" />
        <div className="h-5 w-24 bg-gray-100 rounded hidden sm:block" />
      </div>

      {/* Desktop: Grid layout */}
      <div className="hidden lg:grid grid-cols-4 gap-6">
        {/* Main article - larger */}
        <div className="col-span-2 row-span-2">
          <div className="aspect-[4/3] bg-gray-200 rounded-xl mb-4" />
          <div className="space-y-3">
            <div className="h-5 w-20 bg-gray-200 rounded" />
            <div className="h-7 bg-gray-200 rounded w-full" />
            <div className="h-7 bg-gray-200 rounded w-4/5" />
            <div className="h-5 bg-gray-100 rounded w-full" />
            <div className="h-5 bg-gray-100 rounded w-2/3" />
            <div className="flex gap-4 pt-2">
              <div className="h-4 bg-gray-100 rounded w-20" />
              <div className="h-4 bg-gray-100 rounded w-16" />
            </div>
          </div>
        </div>

        {/* Secondary articles - right side */}
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="space-y-2">
            <div className="aspect-[16/10] bg-gray-200 rounded-lg" />
            <div className="h-4 w-16 bg-gray-200 rounded" />
            <div className="h-5 bg-gray-200 rounded w-full" />
            <div className="h-5 bg-gray-200 rounded w-3/4" />
            <div className="h-3 bg-gray-100 rounded w-20" />
          </div>
        ))}
      </div>

      {/* Tablet: 2 columns */}
      <div className="hidden md:grid lg:hidden grid-cols-2 gap-6">
        {[1, 2, 3, 4, 5, 6].map((i) => (
          <div key={i} className="space-y-2">
            <div className="aspect-[16/10] bg-gray-200 rounded-lg" />
            <div className="h-4 w-16 bg-gray-200 rounded" />
            <div className="h-5 bg-gray-200 rounded w-full" />
            <div className="h-5 bg-gray-200 rounded w-3/4" />
          </div>
        ))}
      </div>

      {/* Mobile: Single column with horizontal cards */}
      <div className="md:hidden space-y-4">
        {/* First article - featured style */}
        <div className="space-y-3 pb-4 border-b">
          <div className="aspect-[16/9] bg-gray-200 rounded-lg" />
          <div className="h-4 w-16 bg-gray-200 rounded" />
          <div className="h-6 bg-gray-200 rounded w-full" />
          <div className="h-6 bg-gray-200 rounded w-3/4" />
        </div>

        {/* Rest - horizontal layout */}
        {[1, 2, 3, 4, 5].map((i) => (
          <div key={i} className="flex gap-3 py-3 border-b last:border-0">
            <div className="w-24 h-18 bg-gray-200 rounded-lg shrink-0" />
            <div className="flex-1 space-y-2">
              <div className="h-4 w-14 bg-gray-200 rounded" />
              <div className="h-4 bg-gray-200 rounded w-full" />
              <div className="h-4 bg-gray-200 rounded w-2/3" />
            </div>
          </div>
        ))}
      </div>

      {/* Load more button placeholder */}
      <div className="flex justify-center mt-8">
        <div className="h-10 w-32 bg-gray-200 rounded-lg" />
      </div>
    </div>
  );
}

export default LatestNewsSkeleton;
