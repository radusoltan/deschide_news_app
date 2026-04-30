'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useIntl } from 'react-intl';
import type { Locale } from '@/lib/types';
import {
  applyLocalePrefix,
  buildLocaleUrlForArticle,
  buildLocaleUrlForCategory,
  buildLocaleUrlGeneric,
} from '@/lib/seo/locale-url';
import {
  useLocaleContext,
  type LocaleSwitcherContext,
} from '@/lib/contexts/LocaleContext';

const LOCALE_OPTIONS: Array<{ code: Locale; label: string; lang: string }> = [
  { code: 'ro', label: 'Română', lang: 'ro' },
  { code: 'ru', label: 'Русский', lang: 'ru' },
  { code: 'en', label: 'English', lang: 'en' },
];

const UNAVAILABLE_TOOLTIP_FALLBACK: Record<Locale, string> = {
  ro: 'Articolul nu este tradus în această limbă',
  en: 'Article is not translated in this language',
  ru: 'Статья не переведена на этот язык',
};

const LOCALE_COOKIE_NAME = 'NEXT_LOCALE';
const LOCALE_COOKIE_MAX_AGE = 365 * 24 * 60 * 60;

// Aligns with proxy.ts:withLocaleCookie so a click on the switcher updates
// the cookie before navigation, preventing proxy from resolving an unprefixed
// default-locale URL back to the previous locale.
function setLocaleCookie(locale: Locale): void {
  if (typeof document === 'undefined') {
    return;
  }
  document.cookie = `${LOCALE_COOKIE_NAME}=${locale}; path=/; max-age=${LOCALE_COOKIE_MAX_AGE}; SameSite=Lax`;
}

export interface LanguageSwitcherProps {
  /** When set, locales not in this list are shown as disabled */
  publishedLocales?: string[];
  /** Article/category translated slugs for cross-locale redirect */
  translatedSlugs?: Partial<Record<Locale, string>>;
  /** Category translated slugs (only relevant when context === 'article') */
  categoryTranslatedSlugs?: Partial<Record<Locale, string>>;
  /** What kind of page the switcher is rendered on. Default 'generic'. */
  context?: LocaleSwitcherContext;
}

export default function LanguageSwitcher(props: LanguageSwitcherProps = {}) {
  const pathname = usePathname() || '/';
  const intl = useIntl();
  const currentLocale = (intl.locale as Locale) || 'ro';
  const ctx = useLocaleContext();

  // Props take priority; fall back to React context populated by pages.
  const publishedLocales = props.publishedLocales ?? ctx.publishedLocales;
  const translatedSlugs = props.translatedSlugs ?? ctx.translatedSlugs;
  const categoryTranslatedSlugs =
    props.categoryTranslatedSlugs ?? ctx.categoryTranslatedSlugs;
  const context: LocaleSwitcherContext = props.context ?? ctx.context ?? 'generic';

  const computeHref = (target: Locale): string | null => {
    if (context === 'article') {
      return buildLocaleUrlForArticle(
        target,
        {
          translatedSlugs,
          category: categoryTranslatedSlugs
            ? { translatedSlugs: categoryTranslatedSlugs }
            : undefined,
        },
        currentLocale
      );
    }
    if (context === 'category') {
      return buildLocaleUrlForCategory(
        target,
        { translatedSlugs },
        currentLocale
      );
    }
    return buildLocaleUrlGeneric(target, pathname, currentLocale);
  };

  return (
    <nav
      aria-label="Switch language"
      className="flex items-center gap-0.5 font-sans text-sm text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)]"
    >
      {LOCALE_OPTIONS.map((option, index) => {
        const isCurrent = option.code === currentLocale;

        const publishedBlock =
          publishedLocales !== undefined && !publishedLocales.includes(option.code);
        const href = computeHref(option.code);
        const missingHref = href === null;
        const isDisabled = publishedBlock || missingHref;

        const tooltip = intl.formatMessage({
          id: 'languageSwitcher.notTranslated',
          defaultMessage: UNAVAILABLE_TOOLTIP_FALLBACK[currentLocale] ?? UNAVAILABLE_TOOLTIP_FALLBACK.en,
        });

        return (
          <span key={option.code} className="flex items-center">
            {isDisabled ? (
              <span
                role="link"
                aria-disabled="true"
                title={tooltip}
                lang={option.lang}
                data-testid={`locale-switch-${option.code}-disabled`}
                className="cursor-not-allowed rounded-sm px-1.5 py-1 opacity-40"
              >
                {option.label}
              </span>
            ) : (
              <Link
                href={href ?? applyLocalePrefix(option.code, '/')}
                onClick={() => setLocaleCookie(option.code)}
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
