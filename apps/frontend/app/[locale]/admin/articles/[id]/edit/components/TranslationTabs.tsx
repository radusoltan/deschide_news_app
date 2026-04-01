'use client';

import Link from 'next/link';

interface TranslationTabsProps {
  articleId: number;
  activeLocale: string;
  availableLocales: { code: string; label: string }[];
}

const LOCALE_CONFIG = [
  { code: 'ro', label: 'RO', fullName: 'Română' },
  { code: 'en', label: 'EN', fullName: 'English' },
  { code: 'ru', label: 'RU', fullName: 'Русский' },
];

export default function TranslationTabs({ articleId, activeLocale }: TranslationTabsProps) {
  return (
    <div className="mb-4">
      <label className="block text-sm font-medium text-primary dark:text-primary-dark mb-2">
        Edit Translation
      </label>
      <div className="flex border-b border-gray-200 dark:border-gray-700">
        {LOCALE_CONFIG.map(({ code, label, fullName }) => {
          const isActive = activeLocale === code;
          const isDefault = code === 'ro';

          return (
            <Link
              key={code}
              href={`/${code}/admin/articles/${articleId}/edit`}
              className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
                isActive
                  ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                  : 'border-transparent text-secondary hover:text-primary hover:border-gray-300 dark:text-gray-400 dark:hover:text-primary-dark'
              }`}
            >
              {label}
              <span className="ml-1 text-xs text-gray-400 dark:text-secondary">
                ({fullName})
              </span>
              {isDefault && (
                <span className="ml-1 text-xs text-green-600 dark:text-green-400">
                  default
                </span>
              )}
            </Link>
          );
        })}
      </div>
      {activeLocale !== 'ro' && (
        <p className="mt-2 text-sm text-amber-600 dark:text-amber-400">
          Editing <strong>{activeLocale.toUpperCase()}</strong> translation. Non-translatable fields (status, category, authors) are shared across all languages.
        </p>
      )}
    </div>
  );
}
