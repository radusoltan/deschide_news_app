/**
 * ImportantList Skeleton Component
 * Loading placeholder for the important articles grid
 */

import React from 'react';

export function ImportantListSkeleton() {
  return (
    <div className="container-deschide animate-pulse">
      {/* Desktop: Bento Grid Layout */}
      <div className="hidden lg:grid grid-cols-12 gap-4">
        {/* Main featured article - spans 8 columns */}
        <div className="col-span-8 row-span-2">
          <div className="relative aspect-[16/9] bg-gray-200 rounded-xl overflow-hidden">
            <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
            <div className="absolute bottom-0 left-0 right-0 p-6 space-y-3">
              <div className="h-5 w-24 bg-gray-300/50 rounded" />
              <div className="h-8 bg-gray-300/50 rounded w-full" />
              <div className="h-8 bg-gray-300/50 rounded w-3/4" />
              <div className="h-5 bg-gray-300/30 rounded w-2/3" />
            </div>
          </div>
        </div>

        {/* Secondary articles - right sidebar */}
        <div className="col-span-4 space-y-4">
          {[1, 2].map((i) => (
            <div key={i} className="relative aspect-[16/10] bg-gray-200 rounded-lg overflow-hidden">
              <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
              <div className="absolute bottom-0 left-0 right-0 p-4 space-y-2">
                <div className="h-4 w-16 bg-gray-300/50 rounded" />
                <div className="h-5 bg-gray-300/50 rounded w-full" />
                <div className="h-5 bg-gray-300/50 rounded w-2/3" />
              </div>
            </div>
          ))}
        </div>

        {/* Bottom row - 4 smaller articles */}
        {[1, 2, 3, 4].map((i) => (
          <div key={`bottom-${i}`} className="col-span-3">
            <div className="space-y-2">
              <div className="aspect-[16/10] bg-gray-200 rounded-lg" />
              <div className="h-4 w-16 bg-gray-200 rounded" />
              <div className="h-5 bg-gray-200 rounded w-full" />
              <div className="h-5 bg-gray-200 rounded w-3/4" />
            </div>
          </div>
        ))}
      </div>

      {/* Tablet: 2 columns */}
      <div className="hidden md:grid lg:hidden grid-cols-2 gap-4">
        {/* Featured */}
        <div className="col-span-2">
          <div className="aspect-[21/9] bg-gray-200 rounded-xl mb-4" />
          <div className="space-y-2">
            <div className="h-5 w-20 bg-gray-200 rounded" />
            <div className="h-7 bg-gray-200 rounded w-full" />
            <div className="h-7 bg-gray-200 rounded w-3/4" />
          </div>
        </div>
        {/* Secondary */}
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="space-y-2">
            <div className="aspect-[16/10] bg-gray-200 rounded-lg" />
            <div className="h-5 bg-gray-200 rounded w-full" />
            <div className="h-5 bg-gray-200 rounded w-2/3" />
          </div>
        ))}
      </div>

      {/* Mobile: Single column */}
      <div className="md:hidden space-y-4">
        {/* Featured */}
        <div className="space-y-3">
          <div className="aspect-[16/9] bg-gray-200 rounded-xl" />
          <div className="h-5 w-20 bg-gray-200 rounded" />
          <div className="h-6 bg-gray-200 rounded w-full" />
          <div className="h-6 bg-gray-200 rounded w-4/5" />
          <div className="h-4 bg-gray-100 rounded w-full" />
        </div>
        {/* Secondary - horizontal cards */}
        {[1, 2, 3].map((i) => (
          <div key={i} className="flex gap-3">
            <div className="w-28 h-20 bg-gray-200 rounded-lg shrink-0" />
            <div className="flex-1 space-y-2">
              <div className="h-4 w-16 bg-gray-200 rounded" />
              <div className="h-5 bg-gray-200 rounded w-full" />
              <div className="h-5 bg-gray-200 rounded w-2/3" />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

export default ImportantListSkeleton;
