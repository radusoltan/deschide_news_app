/**
 * Article Not Found Page
 * Displays when article or category slug is not found
 */

'use client';

import Link from 'next/link';

export default function ArticleNotFound() {
  return (
    <main id="content">
      <div className="bg-surface-sunken py-12">
        <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
          <div className="flex flex-col items-center justify-center min-h-[500px]">
            <div className="max-w-md w-full text-center">
              {/* 404 Icon */}
              <div className="mb-6">
                <svg
                  className="mx-auto h-24 w-24 text-gray-400"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  aria-hidden="true"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
              </div>

              {/* 404 Title */}
              <h1 className="text-6xl font-bold text-primary mb-4">404</h1>

              <h2 className="text-2xl font-semibold text-gray-800 mb-4">
                Article Not Found
              </h2>

              <p className="text-lg text-gray-600 mb-8">
                Sorry, we couldn&apos;t find the article you&apos;re looking for. It may have been moved or deleted.
              </p>

              {/* Possible Reasons */}
              <div className="text-left mb-8 p-4 bg-gray-100 rounded">
                <p className="text-sm font-semibold text-primary mb-2">
                  This could be because:
                </p>
                <ul className="text-sm text-gray-600 space-y-1 list-disc list-inside">
                  <li>The article URL has changed</li>
                  <li>The article has been removed</li>
                  <li>The category doesn&apos;t match the article</li>
                  <li>There&apos;s a typo in the URL</li>
                </ul>
              </div>

              {/* Action Buttons */}
              <div className="flex flex-col sm:flex-row gap-4 justify-center">
                <Link
                  href="/"
                  className="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-brand-tomato-500 hover:bg-brand-tomato-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-tomato-500 transition-colors"
                >
                  <svg
                    className="w-5 h-5 mr-2"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                    />
                  </svg>
                  Go to Homepage
                </Link>

                <button
                  onClick={() => window.history.back()}
                  className="inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-primary bg-surface hover:bg-surface-sunken focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-tomato-500 transition-colors"
                >
                  <svg
                    className="w-5 h-5 mr-2"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M10 19l-7-7m0 0l7-7m-7 7h18"
                    />
                  </svg>
                  Go Back
                </button>
              </div>

              {/* Search Suggestion */}
              <div className="mt-8 pt-6 border-t border-gray-200">
                <p className="text-sm text-secondary mb-3">
                  Try searching for what you&apos;re looking for:
                </p>
                <div className="flex">
                  <input
                    type="text"
                    placeholder="Search articles..."
                    className="flex-1 px-4 py-2 border border-gray-300 rounded-l-md focus:outline-none focus:ring-2 focus:ring-brand-tomato-500 focus:border-transparent"
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') {
                        const value = (e.target as HTMLInputElement).value;
                        if (value) {
                          window.location.href = `/search?q=${encodeURIComponent(value)}`;
                        }
                      }
                    }}
                  />
                  <button
                    onClick={() => {
                      const input = document.querySelector('input[type="text"]') as HTMLInputElement;
                      if (input && input.value) {
                        window.location.href = `/search?q=${encodeURIComponent(input.value)}`;
                      }
                    }}
                    className="px-6 py-2 bg-brand-tomato-500 text-white rounded-r-md hover:bg-brand-tomato-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-tomato-500 transition-colors"
                  >
                    <svg
                      className="w-5 h-5"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                      />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  );
}
