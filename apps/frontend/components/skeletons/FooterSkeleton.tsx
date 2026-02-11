/**
 * Footer Skeleton Component
 * Loading placeholder for site footer
 */

import React from 'react';

export function FooterSkeleton() {
  return (
    <footer className="bg-slate-900 text-white animate-pulse">
      <div className="container-deschide py-12">
        {/* Main footer grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-8">
          {/* Brand column */}
          <div className="space-y-4">
            <div className="h-8 w-32 bg-slate-700 rounded" />
            <div className="h-4 bg-slate-700 rounded w-full" />
            <div className="h-4 bg-slate-700 rounded w-4/5" />
            <div className="h-4 bg-slate-700 rounded w-3/5" />
            {/* Social links */}
            <div className="flex gap-3 pt-2">
              {[1, 2, 3, 4].map((i) => (
                <div key={i} className="h-9 w-9 bg-slate-700 rounded-full" />
              ))}
            </div>
          </div>

          {/* Categories column */}
          <div className="space-y-4">
            <div className="h-5 w-24 bg-slate-700 rounded" />
            <div className="space-y-2">
              {[1, 2, 3, 4, 5, 6].map((i) => (
                <div key={i} className="h-4 bg-slate-800 rounded w-3/4" />
              ))}
            </div>
          </div>

          {/* Links column */}
          <div className="space-y-4">
            <div className="h-5 w-20 bg-slate-700 rounded" />
            <div className="space-y-2">
              {[1, 2, 3, 4, 5].map((i) => (
                <div key={i} className="h-4 bg-slate-800 rounded w-2/3" />
              ))}
            </div>
          </div>

          {/* Newsletter column */}
          <div className="space-y-4">
            <div className="h-5 w-28 bg-slate-700 rounded" />
            <div className="h-4 bg-slate-800 rounded w-full" />
            <div className="h-4 bg-slate-800 rounded w-4/5" />
            <div className="flex gap-2 mt-4">
              <div className="flex-1 h-10 bg-slate-800 rounded-lg" />
              <div className="h-10 w-24 bg-slate-700 rounded-lg" />
            </div>
          </div>
        </div>

        {/* Divider */}
        <div className="h-px bg-slate-700 my-8" />

        {/* Bottom bar */}
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
          <div className="h-4 w-48 bg-slate-800 rounded" />
          <div className="flex gap-4">
            {[1, 2, 3].map((i) => (
              <div key={i} className="h-4 w-20 bg-slate-800 rounded" />
            ))}
          </div>
        </div>
      </div>
    </footer>
  );
}

export default FooterSkeleton;
