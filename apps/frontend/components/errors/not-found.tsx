/**
 * Not Found Component
 * 404 error display for missing content
 */

'use client';

import Link from 'next/link';
import type { Locale } from '@/lib/types';

interface NotFoundProps {
  locale: Locale;
  resourceType?: 'article' | 'category' | 'page';
}

export default function NotFound({ locale, resourceType = 'page' }: NotFoundProps) {
  const translations = {
    ro: {
      title: {
        article: 'Articol negăsit',
        category: 'Categorie negăsită',
        page: 'Pagină negăsită',
      },
      description: {
        article: 'Ne pare rău, dar articolul pe care îl căutați nu există sau a fost mutat.',
        category: 'Ne pare rău, dar categoria pe care o căutați nu există.',
        page: 'Ne pare rău, dar pagina pe care o căutați nu există.',
      },
      suggestions: 'Sugestii:',
      suggestion1: 'Verificați URL-ul pentru erori de scriere',
      suggestion2: 'Căutați articolul folosind bara de căutare',
      suggestion3: 'Mergeți la prima pagină',
      home: 'Înapoi la prima pagină',
      search: 'Căutare',
    },
    en: {
      title: {
        article: 'Article Not Found',
        category: 'Category Not Found',
        page: 'Page Not Found',
      },
      description: {
        article: 'Sorry, but the article you are looking for does not exist or has been moved.',
        category: 'Sorry, but the category you are looking for does not exist.',
        page: 'Sorry, but the page you are looking for does not exist.',
      },
      suggestions: 'Suggestions:',
      suggestion1: 'Check the URL for typos',
      suggestion2: 'Search for the article using the search bar',
      suggestion3: 'Go to the home page',
      home: 'Back to Home',
      search: 'Search',
    },
    ru: {
      title: {
        article: 'Статья не найдена',
        category: 'Категория не найдена',
        page: 'Страница не найдена',
      },
      description: {
        article: 'К сожалению, статья, которую вы ищете, не существует или была перемещена.',
        category: 'К сожалению, категория, которую вы ищете, не существует.',
        page: 'К сожалению, страница, которую вы ищете, не существует.',
      },
      suggestions: 'Предложения:',
      suggestion1: 'Проверьте URL на опечатки',
      suggestion2: 'Найдите статью с помощью строки поиска',
      suggestion3: 'Перейдите на главную страницу',
      home: 'Вернуться на главную',
      search: 'Поиск',
    },
  };

  const t = translations[locale];

  return (
    <div className="min-h-[60vh] flex items-center justify-center px-4">
      <div className="max-w-md w-full text-center">
        {/* 404 Icon */}
        <div className="mb-6 flex justify-center">
          <div className="text-9xl font-bold text-gray-200 select-none">
            404
          </div>
        </div>

        {/* Error Message */}
        <h1 className="text-2xl font-bold text-gray-900 mb-3">
          {t.title[resourceType]}
        </h1>
        <p className="text-gray-600 mb-6">
          {t.description[resourceType]}
        </p>

        {/* Suggestions */}
        <div className="mb-6 p-4 bg-gray-50 rounded-lg text-left">
          <p className="text-sm font-semibold text-gray-700 mb-3">{t.suggestions}</p>
          <ul className="space-y-2 text-sm text-gray-600">
            <li className="flex items-start gap-2">
              <span className="text-red-600 mt-0.5">•</span>
              <span>{t.suggestion1}</span>
            </li>
            <li className="flex items-start gap-2">
              <span className="text-red-600 mt-0.5">•</span>
              <span>{t.suggestion2}</span>
            </li>
            <li className="flex items-start gap-2">
              <span className="text-red-600 mt-0.5">•</span>
              <span>{t.suggestion3}</span>
            </li>
          </ul>
        </div>

        {/* Action Buttons */}
        <div className="flex flex-col sm:flex-row gap-3 justify-center">
          <Link
            href={`/${locale}`}
            className="px-6 py-3 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors"
          >
            {t.home}
          </Link>
          {/* TODO: Implement search functionality */}
          {/* <Link
            href={`/${locale}/search`}
            className="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-colors"
          >
            {t.search}
          </Link> */}
        </div>
      </div>
    </div>
  );
}
