'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useIntl } from 'react-intl';
import type { Locale } from '@/lib/types';

const LOCALE_OPTIONS: Array<{ code: Locale; label: string; lang: string }> = [
  { code: 'ro', label: 'Română', lang: 'ro' },
  { code: 'ru', label: 'Русский', lang: 'ru' },
  { code: 'en', label: 'English', lang: 'en' },
];

function buildLocaleHref(pathname: string, locale: Locale): string {
  const normalizedPath = pathname.startsWith('/') ? pathname : `/${pathname}`;
  const segments = normalizedPath.split('/').filter(Boolean);
  const currentLocale = segments[0];

  if (currentLocale && ['ro', 'en', 'ru'].includes(currentLocale)) {
    segments[0] = locale;
  } else {
    segments.unshift(locale);
  }

  return `/${segments.join('/')}`;
}

export default function LanguageSwitcher() {
  const pathname = usePathname() || '/';
  const intl = useIntl();
  const currentLocale = (intl.locale as Locale) || 'ro';

  return (
    <nav
      aria-label="Switch language"
      className="flex items-center gap-0.5 font-sans text-sm text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)]"
    >
      {LOCALE_OPTIONS.map((option, index) => {
        const isCurrent = option.code === currentLocale;

        return (
          <span key={option.code} className="flex items-center">
            <Link
              href={buildLocaleHref(pathname, option.code)}
              aria-current={isCurrent ? 'true' : undefined}
              aria-label={`Switch to ${option.label}`}
              lang={option.lang}
              className={[
                'rounded-sm px-1.5 py-1 transition-colors',
                isCurrent
                  ? 'font-semibold text-[var(--color-text-primary)] underline underline-offset-4 dark:text-[var(--color-text-primary-dark)]'
                  : 'hover:text-[var(--color-accent)]',
              ].join(' ')}
            >
              {option.label}
            </Link>
            {index < LOCALE_OPTIONS.length - 1 && (
              <span
                aria-hidden="true"
                className="px-1 text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)]"
              >
                |
              </span>
            )}
          </span>
        );
      })}
    </nav>
  );
}
