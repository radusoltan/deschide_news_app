/**
 * VideoSlider Skeleton Component
 * Loading placeholder for the video shows slider
 */

import React from 'react';

export function VideoSliderSkeleton() {
  return (
    <div className="bg-slate-900 py-12 animate-pulse">
      <div className="container-deschide">
        {/* Section header */}
        <div className="flex items-center justify-between mb-8">
          <div className="flex items-center gap-3">
            <div className="h-8 w-8 bg-slate-700 rounded" />
            <div className="h-7 w-48 bg-slate-700 rounded" />
          </div>
          <div className="flex gap-2 hidden sm:flex">
            <div className="h-10 w-10 bg-slate-700 rounded-full" />
            <div className="h-10 w-10 bg-slate-700 rounded-full" />
          </div>
        </div>

        {/* Video show tabs */}
        <div className="flex gap-3 mb-6 overflow-hidden">
          {[1, 2, 3, 4].map((i) => (
            <div
              key={i}
              className={`h-10 rounded-full shrink-0 ${
                i === 1 ? 'w-32 bg-slate-600' : 'w-28 bg-slate-800'
              }`}
            />
          ))}
        </div>

        {/* Desktop: Video grid */}
        <div className="hidden lg:grid grid-cols-4 gap-6">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="space-y-3">
              <div className="relative aspect-video bg-slate-700 rounded-xl overflow-hidden">
                {/* Play button */}
                <div className="absolute inset-0 flex items-center justify-center">
                  <div className="h-14 w-14 bg-slate-600 rounded-full" />
                </div>
                {/* Duration badge */}
                <div className="absolute bottom-2 right-2 h-5 w-12 bg-slate-800 rounded" />
              </div>
              <div className="h-5 bg-slate-700 rounded w-full" />
              <div className="h-5 bg-slate-700 rounded w-3/4" />
              <div className="h-4 bg-slate-800 rounded w-1/2" />
            </div>
          ))}
        </div>

        {/* Tablet: 2 columns */}
        <div className="hidden md:grid lg:hidden grid-cols-2 gap-4">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="space-y-2">
              <div className="relative aspect-video bg-slate-700 rounded-lg">
                <div className="absolute inset-0 flex items-center justify-center">
                  <div className="h-12 w-12 bg-slate-600 rounded-full" />
                </div>
              </div>
              <div className="h-5 bg-slate-700 rounded w-full" />
              <div className="h-5 bg-slate-700 rounded w-2/3" />
            </div>
          ))}
        </div>

        {/* Mobile: Horizontal scroll */}
        <div className="md:hidden flex gap-4 overflow-hidden pb-4">
          {[1, 2, 3].map((i) => (
            <div key={i} className="w-72 shrink-0 space-y-2">
              <div className="relative aspect-video bg-slate-700 rounded-lg">
                <div className="absolute inset-0 flex items-center justify-center">
                  <div className="h-12 w-12 bg-slate-600 rounded-full" />
                </div>
              </div>
              <div className="h-5 bg-slate-700 rounded w-full" />
              <div className="h-5 bg-slate-700 rounded w-3/4" />
            </div>
          ))}
        </div>

        {/* View all button */}
        <div className="flex justify-center mt-8">
          <div className="h-10 w-40 bg-slate-700 rounded-lg" />
        </div>
      </div>
    </div>
  );
}

export default VideoSliderSkeleton;
