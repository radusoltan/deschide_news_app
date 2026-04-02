'use client';

/**
 * Global Error Page
 * Catches errors in the root layout and provides a fallback UI
 *
 * @see NEXTJS_ALIGNMENT_PLAN.md - Phase 4 Error Handling
 */

import { useEffect } from 'react';
import Link from 'next/link';
import * as Sentry from '@sentry/nextjs';

export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    // Log error to error reporting service
    console.error('[Global Error]', error);

    // Send to Sentry error tracking (no-op if DSN is not configured)
    Sentry.captureException(error);
  }, [error]);

  return (
    <html lang="ro">
      <body className="min-h-screen bg-surface-sunken">
        <div className="min-h-screen flex items-center justify-center px-4">
          <div className="max-w-md w-full text-center">
            {/* Error Icon */}
            <div className="mb-6">
              <svg
                className="mx-auto h-16 w-16 text-red-500"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={1.5}
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
              </svg>
            </div>

            {/* Title */}
            <h1 className="text-3xl font-bold text-primary mb-4">
              Ceva nu a funcționat
            </h1>

            {/* Description */}
            <p className="text-gray-600 mb-8">
              Ne cerem scuze pentru neplăceri. A apărut o eroare neașteptată.
              Vă rugăm să încercați din nou.
            </p>

            {/* Error digest (for debugging) */}
            {error.digest && (
              <p className="text-sm text-gray-400 mb-6 font-mono">
                Cod eroare: {error.digest}
              </p>
            )}

            {/* Actions */}
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <button
                onClick={reset}
                className="px-6 py-3 bg-brand-tomato text-white font-semibold rounded-lg hover:bg-brand-tomato-600 transition-colors"
              >
                Încearcă din nou
              </button>
              <Link
                href="/"
                className="px-6 py-3 bg-gray-200 text-gray-800 font-semibold rounded-lg hover:bg-gray-300 transition-colors"
              >
                Pagina principală
              </Link>
            </div>
          </div>
        </div>
      </body>
    </html>
  );
}
