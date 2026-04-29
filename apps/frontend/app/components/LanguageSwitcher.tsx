'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useIntl } from 'react-intl';
import type { Locale } from '@/lib/types';
import { usePublishedLocales } from '@/lib/contexts/PublishedLocalesContext';

const LOCALE_OPTIONS: Array<{ code: Locale; label: string; lang: string }> = [
  { code: 'ro', label: 'Română', lang: 'ro' },
  { code: 'ru', label: 'Русский', lang: 'ru' },
  { code: 'en', label: 'English', lang: 'en' },
];

const UNAVAILABLE_TOOLTIP: Record<string, string> = {
  ro: 'Traducerea nu este disponibilă',
  en: 'Translation not available',
  ru: 'Перевод недоступен',
};

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

interface LanguageSwitcherProps {
  /** When set, locales not in this list are shown as disabled */
  publishedLocales?: string[];
}

export default function LanguageSwitcher({ publishedLocales: publishedLocalesProp }: LanguageSwitcherProps = {}) {
  const pathname = usePathname() || '/';
  const intl = useIntl();
  const currentLocale = (intl.locale as Locale) || 'ro';
  const { publishedLocales: publishedLocalesCtx } = usePublishedLocales();

  // Prop wins; otherwise use context (populated by per-page LocaleContextSetter);
  // when both are absent every locale is enabled (homepage, category, generic pages).
  const publishedLocales = publishedLocalesProp ?? publishedLocalesCtx;

  return (
    <nav
      aria-label="Switch language"
      className="flex items-center gap-0.5 font-sans text-sm text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)]"
    >
      {LOCALE_OPTIONS.map((option, index) => {
        const isCurrent = option.code === currentLocale;
        const isAvailable = !publishedLocales || publishedLocales.includes(option.code);

        return (
          <span key={option.code} className="flex items-center">
            {isAvailable ? (
              <Link
                href={buildLocaleHref(pathname, option.code)}
                aria-current={isCurrent ? 'true' : undefined}
                aria-label={`Switch to ${option.label}`}
                lang={option.lang}
                data-testid={`locale-switch-${option.code}`}
                className={[
                  'rounded-sm px-1.5 py-1 transition-colors',
                  isCurrent
                    ? 'font-semibold text-[var(--color-text-primary)] underline underline-offset-4 dark:text-[var(--color-text-primary-dark)]'
                    : 'hover:text-[var(--color-accent)]',
                ].join(' ')}
              >
                {option.label}
              </Link>
            ) : (
              <span
                role="link"
                aria-disabled="true"
                title={UNAVAILABLE_TOOLTIP[currentLocale] || UNAVAILABLE_TOOLTIP.en}
                lang={option.lang}
                data-testid={`locale-switch-${option.code}-disabled`}
                className="cursor-not-allowed rounded-sm px-1.5 py-1 opacity-40"
              >
                {option.label}
              </span>
            )}
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
