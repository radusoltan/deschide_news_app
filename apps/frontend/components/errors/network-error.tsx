/**
 * Network Error Component
 * Error display for network/API failures
 */

'use client';

import Link from 'next/link';
import type { Locale } from '@/lib/types';

interface NetworkErrorProps {
  locale: Locale;
  onRetry?: () => void;
}

export default function NetworkError({ locale, onRetry }: NetworkErrorProps) {
  const translations = {
    ro: {
      title: 'Problemă de conexiune',
      description: 'Nu am putut conecta la server. Verificați conexiunea la internet și încercați din nou.',
      retry: 'Încearcă din nou',
      home: 'Înapoi la prima pagină',
      tips: 'Sfaturi:',
      tip1: 'Verificați conexiunea la internet',
      tip2: 'Reîmprospătați pagina',
      tip3: 'Încercați mai târziu',
    },
    en: {
      title: 'Connection Problem',
      description: 'We could not connect to the server. Please check your internet connection and try again.',
      retry: 'Try Again',
      home: 'Back to Home',
      tips: 'Tips:',
      tip1: 'Check your internet connection',
      tip2: 'Refresh the page',
      tip3: 'Try again later',
    },
    ru: {
      title: 'Проблема с подключением',
      description: 'Не удалось подключиться к серверу. Проверьте подключение к Интернету и попробуйте снова.',
      retry: 'Попробовать снова',
      home: 'Вернуться на главную',
      tips: 'Советы:',
      tip1: 'Проверьте подключение к Интернету',
      tip2: 'Обновите страницу',
      tip3: 'Попробуйте позже',
    },
  };

  const t = translations[locale];

  return (
    <div className="min-h-[60vh] flex items-center justify-center px-4">
      <div className="max-w-md w-full text-center">
        {/* Network Icon */}
        <div className="mb-6 flex justify-center">
          <div className="w-20 h-20 bg-orange-100 rounded-full flex items-center justify-center">
            <svg
              className="w-10 h-10 text-orange-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 2.829a4.978 4.978 0 01-1.414-2.83m-1.414 5.658a9 9 0 01-2.167-9.238m7.824 2.167a1 1 0 111.414 1.414m-1.414-1.414L3 3m8.293 8.293l1.414 1.414"
              />
            </svg>
          </div>
        </div>

        {/* Error Message */}
        <h1 className="text-2xl font-bold text-gray-900 mb-3">
          {t.title}
        </h1>
        <p className="text-gray-600 mb-6">
          {t.description}
        </p>

        {/* Tips */}
        <div className="mb-6 p-4 bg-gray-50 rounded-lg text-left">
          <p className="text-sm font-semibold text-gray-700 mb-3">{t.tips}</p>
          <ul className="space-y-2 text-sm text-gray-600">
            <li className="flex items-start gap-2">
              <span className="text-orange-600 mt-0.5">•</span>
              <span>{t.tip1}</span>
            </li>
            <li className="flex items-start gap-2">
              <span className="text-orange-600 mt-0.5">•</span>
              <span>{t.tip2}</span>
            </li>
            <li className="flex items-start gap-2">
              <span className="text-orange-600 mt-0.5">•</span>
              <span>{t.tip3}</span>
            </li>
          </ul>
        </div>

        {/* Action Buttons */}
        <div className="flex flex-col sm:flex-row gap-3 justify-center">
          {onRetry && (
            <button
              onClick={onRetry}
              className="px-6 py-3 bg-orange-600 text-white rounded-lg font-medium hover:bg-orange-700 transition-colors"
            >
              {t.retry}
            </button>
          )}
          <Link
            href={`/${locale}`}
            className="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-colors"
          >
            {t.home}
          </Link>
        </div>
      </div>
    </div>
  );
}
