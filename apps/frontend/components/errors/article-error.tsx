/**
 * Article Error Component
 * Error display for article loading failures
 */

'use client';

import Link from 'next/link';
import type { Locale } from '@/lib/types';

interface ArticleErrorProps {
  error?: Error | string;
  locale: Locale;
  onRetry?: () => void;
}

export default function ArticleError({ error, locale, onRetry }: ArticleErrorProps) {
  const errorMessage = error instanceof Error ? error.message : error || 'Failed to load article';

  const translations = {
    ro: {
      title: 'Eroare la încărcarea articolului',
      description: 'Ne cerem scuze, dar nu am putut încărca articolul solicitat.',
      retry: 'Încearcă din nou',
      home: 'Înapoi la prima pagină',
      error: 'Detalii eroare',
    },
    en: {
      title: 'Error Loading Article',
      description: 'We apologize, but we could not load the requested article.',
      retry: 'Try Again',
      home: 'Back to Home',
      error: 'Error Details',
    },
    ru: {
      title: 'Ошибка загрузки статьи',
      description: 'Извините, мы не смогли загрузить запрошенную статью.',
      retry: 'Попробовать снова',
      home: 'Вернуться на главную',
      error: 'Детали ошибки',
    },
  };

  const t = translations[locale];

  return (
    <div className="min-h-[60vh] flex items-center justify-center px-4">
      <div className="max-w-md w-full text-center">
        {/* Error Icon */}
        <div className="mb-6 flex justify-center">
          <div className="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center">
            <svg
              className="w-10 h-10 text-red-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
              />
            </svg>
          </div>
        </div>

        {/* Error Message */}
        <h1 className="text-2xl font-bold text-primary mb-3">
          {t.title}
        </h1>
        <p className="text-gray-600 mb-6">
          {t.description}
        </p>

        {/* Error Details (Development) */}
        {process.env.NODE_ENV === 'development' && errorMessage && (
          <div className="mb-6 p-4 bg-gray-100 rounded-lg text-left">
            <p className="text-sm font-semibold text-primary mb-2">{t.error}:</p>
            <p className="text-sm text-gray-600 font-mono break-all">{errorMessage}</p>
          </div>
        )}

        {/* Action Buttons */}
        <div className="flex flex-col sm:flex-row gap-3 justify-center">
          {onRetry && (
            <button
              onClick={onRetry}
              className="px-6 py-3 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors"
            >
              {t.retry}
            </button>
          )}
          <Link
            href={`/${locale}`}
            className="px-6 py-3 bg-gray-200 text-primary rounded-lg font-medium hover:bg-gray-300 transition-colors"
          >
            {t.home}
          </Link>
        </div>
      </div>
    </div>
  );
}
