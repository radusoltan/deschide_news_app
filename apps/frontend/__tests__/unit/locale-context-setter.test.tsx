/**
 * Integration-style tests for LocaleContextSetter ↔ LocaleContext ↔
 * LanguageSwitcher wiring (T59.1 / ADR-028, follow-up F4).
 *
 * The article and category server pages emit a `<LocaleContextSetter>`
 * client component that pushes per-page metadata (publishedLocales,
 * translatedSlugs, categoryTranslatedSlugs, context type) into the
 * `LocaleContext` so the shared Header switcher can render correct
 * cross-locale URLs and disabled states.
 *
 * Rather than rendering the full server pages (heavy DB / fetch
 * dependencies), we exercise the same wiring contract pages use:
 *
 *   1. Mount <LocaleContextProvider>
 *   2. Render <LocaleContextSetter> with the article-page payload
 *   3. Assert <LanguageSwitcher> downstream consumes that context
 *      (link hrefs, disabled state)
 *
 * Placed under __tests__/unit/ instead of __tests__/integration/ because
 * jest.config.mjs ignores the integration directory (reserved for
 * Playwright). Same precedent as __tests__/unit/app/sitemap.test.ts.
 */

import React from 'react';
import { render, screen } from '@testing-library/react';
import { IntlProvider } from 'react-intl';
import LocaleContextSetter from '@/app/components/LocaleContextSetter';
import LanguageSwitcher from '@/app/components/LanguageSwitcher';
import { LocaleContextProvider } from '@/lib/contexts/LocaleContext';
import type { Locale } from '@/lib/types';

const mockUsePathname = jest.fn<string, []>(() => '/');
jest.mock('next/navigation', () => ({
  usePathname: () => mockUsePathname(),
  useRouter: () => ({
    push: jest.fn(),
    replace: jest.fn(),
    prefetch: jest.fn(),
    back: jest.fn(),
  }),
  useSearchParams: () => new URLSearchParams(),
  useParams: () => ({}),
}));

interface ArticlePageMock {
  publishedLocales: string[];
  translatedSlugs: Partial<Record<Locale, string>>;
  categoryTranslatedSlugs: Partial<Record<Locale, string>>;
}

interface CategoryPageMock {
  translatedSlugs: Partial<Record<Locale, string>>;
}

/**
 * Mirrors what `app/[locale]/(public)/[categorySlug]/[articleSlug]/page.tsx`
 * renders alongside the article body — feeds the article payload into the
 * shared switcher.
 */
function ArticlePageHarness({
  payload,
  locale,
}: {
  payload: ArticlePageMock;
  locale: Locale;
}) {
  return (
    <IntlProvider locale={locale} messages={{}} defaultLocale="ro">
      <LocaleContextProvider>
        <LocaleContextSetter
          context="article"
          publishedLocales={payload.publishedLocales}
          translatedSlugs={payload.translatedSlugs}
          categoryTranslatedSlugs={payload.categoryTranslatedSlugs}
        />
        <LanguageSwitcher />
      </LocaleContextProvider>
    </IntlProvider>
  );
}

/**
 * Mirrors what `app/[locale]/(public)/[categorySlug]/page.tsx` renders.
 */
function CategoryPageHarness({
  payload,
  locale,
}: {
  payload: CategoryPageMock;
  locale: Locale;
}) {
  return (
    <IntlProvider locale={locale} messages={{}} defaultLocale="ro">
      <LocaleContextProvider>
        <LocaleContextSetter
          context="category"
          translatedSlugs={payload.translatedSlugs}
        />
        <LanguageSwitcher />
      </LocaleContextProvider>
    </IntlProvider>
  );
}

describe('LocaleContextSetter — article page integration', () => {
  beforeEach(() => {
    mockUsePathname.mockReturnValue('/politica/articol-ro');
  });

  it('feeds full 3-locale article payload into context, switcher emits all 3 links', () => {
    render(
      <ArticlePageHarness
        locale="ro"
        payload={{
          publishedLocales: ['ro', 'en', 'ru'],
          translatedSlugs: {
            ro: 'articol-ro',
            en: 'article-en',
            ru: 'statya-ru',
          },
          categoryTranslatedSlugs: {
            ro: 'politica',
            en: 'politics',
            ru: 'politika',
          },
        }}
      />
    );

    expect(screen.getByTestId('locale-switch-en')).toHaveAttribute(
      'href',
      '/en/politics/article-en'
    );
    expect(screen.getByTestId('locale-switch-ru')).toHaveAttribute(
      'href',
      '/ru/politika/statya-ru'
    );
  });

  it('article context uses translatedSlugs for href construction (not pathname)', () => {
    // Pathname is intentionally generic — must be ignored by article context.
    mockUsePathname.mockReturnValue('/some/unrelated/path');

    render(
      <ArticlePageHarness
        locale="ro"
        payload={{
          publishedLocales: ['ro', 'en', 'ru'],
          translatedSlugs: { ro: 'art-ro', en: 'art-en', ru: 'art-ru' },
          categoryTranslatedSlugs: {
            ro: 'societate',
            en: 'society',
            ru: 'obshchestvo',
          },
        }}
      />
    );

    expect(screen.getByTestId('locale-switch-en')).toHaveAttribute(
      'href',
      '/en/society/art-en'
    );
  });

  it('honors publishedLocales — locales not in the list render disabled', () => {
    render(
      <ArticlePageHarness
        locale="ro"
        payload={{
          publishedLocales: ['ro', 'en'], // RU not yet translated
          translatedSlugs: { ro: 'articol', en: 'article' },
          categoryTranslatedSlugs: { ro: 'politica', en: 'politics' },
        }}
      />
    );

    expect(screen.getByTestId('locale-switch-en')).toHaveAttribute(
      'href',
      '/en/politics/article'
    );
    expect(screen.getByTestId('locale-switch-ru-disabled')).toBeInTheDocument();
    expect(screen.queryByTestId('locale-switch-ru')).not.toBeInTheDocument();
  });

  it('handles missing translated slug for one locale gracefully (disabled, not crash)', () => {
    render(
      <ArticlePageHarness
        locale="ro"
        payload={{
          // publishedLocales says all 3 published…
          publishedLocales: ['ro', 'en', 'ru'],
          // …but translatedSlugs only has RO+EN — RU article slug is missing.
          translatedSlugs: { ro: 'articol-ro', en: 'article-en' },
          categoryTranslatedSlugs: {
            ro: 'politica',
            en: 'politics',
            ru: 'politika',
          },
        }}
      />
    );

    // builder returns null for RU → disabled span, no link
    expect(screen.getByTestId('locale-switch-ru-disabled')).toBeInTheDocument();
    expect(screen.queryByTestId('locale-switch-ru')).not.toBeInTheDocument();
    // RO + EN remain functional
    expect(screen.getByTestId('locale-switch-en')).toHaveAttribute(
      'href',
      '/en/politics/article-en'
    );
  });
});

describe('LocaleContextSetter — category page integration', () => {
  beforeEach(() => {
    mockUsePathname.mockReturnValue('/politica');
  });

  it('feeds category translatedSlugs into context, switcher emits per-locale category URLs', () => {
    render(
      <CategoryPageHarness
        locale="ro"
        payload={{
          translatedSlugs: {
            ro: 'politica',
            en: 'politics',
            ru: 'politika',
          },
        }}
      />
    );

    expect(screen.getByTestId('locale-switch-en')).toHaveAttribute(
      'href',
      '/en/politics'
    );
    expect(screen.getByTestId('locale-switch-ru')).toHaveAttribute(
      'href',
      '/ru/politika'
    );
    // Current locale RO renders prefix-less per i18nConfig.prefixDefault=false
    expect(screen.getByTestId('locale-switch-ro')).toHaveAttribute(
      'href',
      '/politica'
    );
  });

  it('category context emits no publishedLocales gating by default — categories visible cross-locale', () => {
    render(
      <CategoryPageHarness
        locale="ro"
        payload={{
          translatedSlugs: { ro: 'economie', en: 'economy' }, // RU absent
        }}
      />
    );

    // No publishedLocales prop in category page → falls back to currentLocale
    // slug for missing RU. Builder for category never returns null (visible).
    expect(screen.getByTestId('locale-switch-ru')).toHaveAttribute(
      'href',
      '/ru/economie'
    );
    expect(screen.queryByTestId('locale-switch-ru-disabled')).not.toBeInTheDocument();
  });
});
