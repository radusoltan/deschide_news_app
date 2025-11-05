/**
 * Article Layout Component
 * Responsive layout for article pages with header, body, sidebar, and footer
 */

import React from 'react';

interface ArticleLayoutProps {
  children: React.ReactNode;
  sidebar?: React.ReactNode;
  className?: string;
}

export default function ArticleLayout({
  children,
  sidebar,
  className = '',
}: ArticleLayoutProps) {
  return (
    <div className={`bg-gray-50 py-6 ${className}`}>
      <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
        <div className="flex flex-row flex-wrap">
          {/* Main Content - 2/3 width on desktop */}
          <div className="flex-shrink max-w-full w-full lg:w-2/3 overflow-hidden">
            {children}
          </div>

          {/* Sidebar - 1/3 width on desktop */}
          {sidebar && (
            <div className="flex-shrink max-w-full w-full lg:w-1/3 lg:pl-8 lg:pt-14 lg:pb-8 order-first lg:order-last">
              {sidebar}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
