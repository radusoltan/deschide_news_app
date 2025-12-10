'use client';

import { useRouter, usePathname } from 'next/navigation';
import { useIntl } from 'react-intl';
import { useState, useRef, useEffect } from 'react';
import { parseUrl, buildUrl, isTranslatableUrl } from '@/lib/utils/url-parser';
import { fetchArticleTranslations, fetchCategoryTranslations } from '@/lib/api/translations';
import type { Locale } from '@/lib/types';

const LOCALES = [
  { code: 'ro', flag: '🇷🇴' },
  { code: 'en', flag: '🇬🇧' },
  { code: 'ru', flag: '🇷🇺' },
];

export default function LanguageSwitcher() {
  const router = useRouter();
  const pathname = usePathname();
  const intl = useIntl();
  const locale = intl.locale as Locale;
  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);


  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleLanguageChange = async (newLocale: string) => {
    if (newLocale === locale) {
      setIsOpen(false);
      return;
    }

    setIsLoading(true);
    setIsOpen(false);

    // Set locale cookie to prevent middleware auto-detection
    // This cookie will be read by the middleware to determine the user's preferred locale
    document.cookie = `NEXT_LOCALE=${newLocale}; path=/; max-age=31536000; SameSite=Lax`;

    try {
      // Parse the current URL
      const parsed = parseUrl(pathname);

      // Check if this is a translatable URL (article or category)
      if (isTranslatableUrl(parsed)) {
        // Article page - fetch translations
        if (parsed.pageType === 'article' && parsed.categorySlug && parsed.articleSlug) {
          const translations = await fetchArticleTranslations(
            parsed.articleSlug,
            parsed.categorySlug,
            locale
          );

          if (translations) {
            // Use translated slugs
            const newCategorySlug =
              translations.categorySlugs[newLocale as Locale] || parsed.categorySlug;
            const newArticleSlug =
              translations.articleSlugs[newLocale as Locale] || parsed.articleSlug;

            const newUrl = buildUrl({
              locale: newLocale as Locale,
              pageType: 'article',
              categorySlug: newCategorySlug,
              articleSlug: newArticleSlug,
            });

            // For Romanian, add /ro prefix explicitly to avoid middleware auto-detection
            // The middleware will then redirect from /ro to / automatically
            const finalUrl = newLocale === 'ro' ? `/ro${newUrl}` : newUrl;

            window.location.replace(finalUrl);
            return;
          }
        }

        // Category page - fetch translations
        if (parsed.pageType === 'category' && parsed.categorySlug) {
          const translations = await fetchCategoryTranslations(parsed.categorySlug, locale);

          if (translations) {
            const newCategorySlug =
              translations.categorySlugs[newLocale as Locale] || parsed.categorySlug;

            const newUrl = buildUrl({
              locale: newLocale as Locale,
              pageType: 'category',
              categorySlug: newCategorySlug,
            });

            // For Romanian, add /ro prefix explicitly to avoid middleware auto-detection
            const finalUrl = newLocale === 'ro' ? `/ro${newUrl}` : newUrl;

            window.location.replace(finalUrl);
            return;
          }
        }
      }

      // For non-translatable URLs (home, static pages, author, archive),
      // just change the locale prefix
      const newUrl = buildUrl({
        ...parsed,
        locale: newLocale as Locale,
      });

      // For Romanian, add /ro prefix explicitly to avoid middleware auto-detection
      // The middleware will then redirect from /ro to / automatically
      const finalUrl = newLocale === 'ro' ? `/ro${newUrl}` : newUrl;

      window.location.replace(finalUrl);
    } catch (error) {
      console.error('Error changing language:', error);

      // Fallback: simple locale replacement
      const segments = pathname.split('/').filter(Boolean);

      // Remove current locale prefix if exists
      if (locale !== 'ro' && segments.length > 0 && ['en', 'ru'].includes(segments[0])) {
        segments.shift(); // Remove locale prefix
      }

      // Add new locale prefix if not Romanian
      if (newLocale !== 'ro') {
        segments.unshift(newLocale);
      }

      // Build new URL
      let newPath = '/' + segments.join('/');

      // For Romanian, add /ro prefix explicitly to avoid middleware auto-detection
      if (newLocale === 'ro') {
        newPath = `/ro${newPath}`;
      }

      window.location.replace(newPath);
    } finally {
      setIsLoading(false);
    }
  };

  const currentLocale = LOCALES.find((l) => l.code === locale) || LOCALES[0];

  return (
    <div className="relative" ref={dropdownRef}>
      <button
        onClick={() => setIsOpen(!isOpen)}
        disabled={isLoading}
        className="flex items-center gap-2 px-4 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        aria-label={intl.formatMessage({ id: 'common.language' })}
      >
        <span className="text-xl">{currentLocale.flag}</span>
        <span className="font-medium text-gray-700 dark:text-gray-200">
          {intl.formatMessage({ id: `languages.${locale}` })}
        </span>
        {isLoading ? (
          <svg
            className="w-4 h-4 text-gray-500 animate-spin"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle
              className="opacity-25"
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              strokeWidth="4"
            ></circle>
            <path
              className="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            ></path>
          </svg>
        ) : (
          <svg
            className={`w-4 h-4 text-gray-500 transition-transform ${isOpen ? 'rotate-180' : ''}`}
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
          </svg>
        )}
      </button>

      {isOpen && (
        <div className="absolute top-full mt-2 right-0 w-48 bg-white border border-gray-300 rounded-lg shadow-lg overflow-hidden z-50">
          {LOCALES.map((localeOption) => (
            <button
              key={localeOption.code}
              onClick={() => handleLanguageChange(localeOption.code)}
              className={`w-full flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors ${locale === localeOption.code
                ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400'
                : 'text-gray-700 dark:text-gray-200'
                }`}
            >
              <span className="text-xl">{localeOption.flag}</span>
              <span className="font-medium">
                {intl.formatMessage({ id: `languages.${localeOption.code}` })}
              </span>
              {locale === localeOption.code && (
                <svg
                  className="w-5 h-5 ml-auto text-blue-600 dark:text-blue-400"
                  fill="currentColor"
                  viewBox="0 0 20 20"
                >
                  <path
                    fillRule="evenodd"
                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                    clipRule="evenodd"
                  />
                </svg>
              )}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
