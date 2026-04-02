/**
 * LiveText Skeleton Component
 * Loading placeholder for live broadcast/text section
 */

import React from 'react';

export function LiveTextSkeleton() {
  return (
    <div className="container-deschide animate-pulse">
      {/* Section header with live indicator */}
      <div className="flex items-center gap-3 mb-6">
        <div className="relative">
          <div className="h-3 w-3 bg-red-400 rounded-full" />
          <div className="absolute inset-0 h-3 w-3 bg-red-400 rounded-full animate-ping" />
        </div>
        <div className="h-7 w-32 bg-gray-200 rounded" />
      </div>

      {/* Desktop: Featured live + sidebar */}
      <div className="hidden lg:grid grid-cols-12 gap-6">
        {/* Main live content */}
        <div className="col-span-8">
          <div className="bg-surface rounded-xl border p-6 space-y-4">
            {/* Live header */}
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="h-8 w-8 bg-red-200 rounded-full" />
                <div className="h-6 w-48 bg-gray-200 rounded" />
              </div>
              <div className="h-6 w-24 bg-gray-100 rounded" />
            </div>

            {/* Match/Event info */}
            <div className="py-4 border-y">
              <div className="flex items-center justify-center gap-8">
                <div className="text-center space-y-2">
                  <div className="h-12 w-12 bg-gray-200 rounded-full mx-auto" />
                  <div className="h-5 w-24 bg-gray-200 rounded" />
                </div>
                <div className="h-8 w-16 bg-gray-200 rounded" />
                <div className="text-center space-y-2">
                  <div className="h-12 w-12 bg-gray-200 rounded-full mx-auto" />
                  <div className="h-5 w-24 bg-gray-200 rounded" />
                </div>
              </div>
            </div>

            {/* Live updates */}
            <div className="space-y-4">
              {[1, 2, 3].map((i) => (
                <div key={i} className="flex gap-4">
                  <div className="h-5 w-12 bg-gray-100 rounded shrink-0" />
                  <div className="flex-1 space-y-2">
                    <div className="h-5 bg-gray-200 rounded w-full" />
                    <div className="h-5 bg-gray-200 rounded w-3/4" />
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Sidebar - other lives */}
        <div className="col-span-4 space-y-4">
          <div className="h-6 w-32 bg-gray-200 rounded" />
          {[1, 2].map((i) => (
            <div key={i} className="bg-surface rounded-lg border p-4 space-y-3">
              <div className="flex items-center gap-2">
                <div className="h-3 w-3 bg-red-400 rounded-full" />
                <div className="h-5 w-32 bg-gray-200 rounded" />
              </div>
              <div className="h-5 bg-gray-200 rounded w-full" />
              <div className="h-4 bg-gray-100 rounded w-1/2" />
            </div>
          ))}
        </div>
      </div>

      {/* Mobile: Single card */}
      <div className="lg:hidden">
        <div className="bg-surface rounded-xl border p-4 space-y-4">
          {/* Live badge */}
          <div className="flex items-center gap-2">
            <div className="h-6 w-6 bg-red-200 rounded-full" />
            <div className="h-5 w-20 bg-red-200 rounded" />
          </div>

          {/* Title */}
          <div className="h-6 bg-gray-200 rounded w-full" />
          <div className="h-6 bg-gray-200 rounded w-3/4" />

          {/* Score/Status */}
          <div className="py-3 border-y flex items-center justify-center">
            <div className="h-8 w-32 bg-gray-200 rounded" />
          </div>

          {/* Latest update */}
          <div className="space-y-2">
            <div className="h-4 w-16 bg-gray-100 rounded" />
            <div className="h-5 bg-gray-200 rounded w-full" />
          </div>

          {/* CTA */}
          <div className="h-10 bg-gray-200 rounded-lg w-full" />
        </div>
      </div>
    </div>
  );
}

export default LiveTextSkeleton;
