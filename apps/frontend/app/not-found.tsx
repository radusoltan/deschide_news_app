/**
 * Global Not Found Page
 * Displayed when a route is not found
 *
 * @see NEXTJS_ALIGNMENT_PLAN.md - Phase 4 Error Handling
 */

import Link from 'next/link';

export default function NotFound() {
  return (
    // data-testid="not-found" reserved for E2E testing.
    // Current Next.js 16 + Turbopack 'use client' SSR pipeline materializes this
    // attribute only post-hydration; E2E specs (sprint-60-articles-ro.spec.ts) use
    // the SSR-stable <title>Article Not Found</title> marker instead. When/if this
    // limitation is fixed upstream, the spec can switch back to data-testid.
    <div data-testid="not-found" className="min-h-screen flex items-center justify-center bg-surface-sunken px-4">
      <div className="max-w-lg w-full text-center">
        {/* 404 Illustration */}
        <div className="mb-8">
          <h1 className="text-9xl font-bold text-gray-200">404</h1>
        </div>

        {/* Title */}
        <h2 className="text-3xl font-bold text-primary mb-4">
          Pagina nu a fost găsită
        </h2>

        {/* Description */}
        <p className="text-gray-600 mb-8 text-lg">
          Ne pare rău, pagina pe care o căutați nu există sau a fost mutată.
        </p>

        {/* Suggestions */}
        <div className="text-left bg-surface rounded-xl p-6 mb-8 shadow-sm border">
          <h3 className="font-semibold text-primary mb-3">
            Iată ce puteți face:
          </h3>
          <ul className="space-y-2 text-gray-600">
            <li className="flex items-start gap-2">
              <span className="text-brand-tomato">•</span>
              Verificați dacă adresa URL este corectă
            </li>
            <li className="flex items-start gap-2">
              <span className="text-brand-tomato">•</span>
              Folosiți bara de căutare pentru a găsi conținutul dorit
            </li>
            <li className="flex items-start gap-2">
              <span className="text-brand-tomato">•</span>
              Reveniți la pagina principală
            </li>
          </ul>
        </div>

        {/* Actions */}
        <div className="flex flex-col sm:flex-row gap-4 justify-center">
          <Link
            href="/"
            className="px-6 py-3 bg-brand-tomato text-white font-semibold rounded-lg hover:bg-brand-tomato-600 transition-colors"
          >
            Pagina principală
          </Link>
          <Link
            href="/search"
            className="px-6 py-3 bg-gray-200 text-gray-800 font-semibold rounded-lg hover:bg-gray-300 transition-colors"
          >
            Caută articole
          </Link>
        </div>

        {/* Popular categories */}
        <div className="mt-12 pt-8 border-t">
          <h3 className="text-sm font-medium text-secondary mb-4">
            Categorii populare
          </h3>
          <div className="flex flex-wrap justify-center gap-2">
            {['Politică', 'Economie', 'Societate', 'Sport', 'Cultură'].map(
              (category) => (
                <Link
                  key={category}
                  href={`/category/${category.toLowerCase()}`}
                  className="px-4 py-2 bg-gray-100 text-primary rounded-full text-sm hover:bg-gray-200 transition-colors"
                >
                  {category}
                </Link>
              )
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
