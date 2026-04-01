'use client';

/**
 * Public Routes Error Page
 * Catches errors in public routes and provides recovery options
 *
 * @see NEXTJS_ALIGNMENT_PLAN.md - Phase 4 Error Handling
 */

import { useEffect } from 'react';
import Link from 'next/link';
import * as Sentry from '@sentry/nextjs';

export default function PublicError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    // Log error to console in development
    console.error('[Public Route Error]', error);

    // Send to Sentry error tracking (no-op if DSN is not configured)
    Sentry.captureException(error);
  }, [error]);

  return (
    <div className="min-h-[60vh] flex items-center justify-center px-4 py-12">
      <div className="max-w-md w-full text-center">
        {/* Error Icon */}
        <div className="mb-6">
          <svg
            className="mx-auto h-14 w-14 text-amber-500"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1.5}
              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
        </div>

        {/* Title */}
        <h1 className="text-2xl font-bold text-primary mb-3">
          Ups! A apărut o problemă
        </h1>

        {/* Description */}
        <p className="text-gray-600 mb-6">
          Nu am putut încărca această pagină. Vă rugăm să încercați din nou sau
          să reveniți mai târziu.
        </p>

        {/* Error digest */}
        {error.digest && (
          <p className="text-xs text-gray-400 mb-6 font-mono bg-gray-100 py-2 px-4 rounded-lg inline-block">
            {error.digest}
          </p>
        )}

        {/* Actions */}
        <div className="flex flex-col sm:flex-row gap-3 justify-center">
          <button
            onClick={reset}
            className="px-5 py-2.5 bg-brand-tomato text-white font-medium rounded-lg hover:bg-brand-tomato-600 transition-colors"
          >
            Încearcă din nou
          </button>
          <Link
            href="/"
            className="px-5 py-2.5 bg-gray-100 text-gray-800 font-medium rounded-lg hover:bg-gray-200 transition-colors"
          >
            Acasă
          </Link>
        </div>

        {/* Alternative navigation */}
        <div className="mt-8 pt-6 border-t">
          <p className="text-sm text-secondary mb-3">Sau navigați către:</p>
          <div className="flex flex-wrap justify-center gap-2">
            <Link
              href="/archive"
              className="text-sm text-brand-oxford hover:underline"
            >
              Arhivă
            </Link>
            <span className="text-primary-dark">•</span>
            <Link
              href="/search"
              className="text-sm text-brand-oxford hover:underline"
            >
              Căutare
            </Link>
            <span className="text-primary-dark">•</span>
            <Link
              href="/contact"
              className="text-sm text-brand-oxford hover:underline"
            >
              Contact
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
