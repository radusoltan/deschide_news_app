/**
 * Navigation Skeleton Component
 * Loading placeholder for header navigation
 */

import React from 'react';

export function NavigationSkeleton() {
  return (
    <nav className="bg-surface border-b animate-pulse">
      <div className="container-deschide">
        <div className="flex items-center justify-between h-16">
          {/* Logo */}
          <div className="h-8 w-32 bg-gray-200 rounded" />

          {/* Desktop navigation */}
          <div className="hidden lg:flex items-center gap-6">
            {[1, 2, 3, 4, 5, 6].map((i) => (
              <div key={i} className="h-5 w-16 bg-gray-200 rounded" />
            ))}
          </div>

          {/* Right side: Search, Locale, User */}
          <div className="flex items-center gap-4">
            {/* Search */}
            <div className="hidden sm:block">
              <div className="h-9 w-40 bg-gray-100 rounded-lg" />
            </div>

            {/* Locale selector */}
            <div className="h-9 w-16 bg-gray-100 rounded-lg" />

            {/* User/Login */}
            <div className="h-9 w-9 bg-gray-200 rounded-full" />

            {/* Mobile menu button */}
            <div className="lg:hidden h-9 w-9 bg-gray-100 rounded" />
          </div>
        </div>
      </div>
    </nav>
  );
}

export default NavigationSkeleton;
