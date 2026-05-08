/**
 * Unit tests for LocaleContextProvider initialData prop and the
 * shallow-equal short-circuit in setLocaleContext / resetLocaleContext.
 *
 * Added for T60.15 / ADR-029 — server-side locale context priming.
 *
 * The provider now accepts `initialData` so the (public) layout can prime
 * LocaleContext on the SSR pass; without this, the LanguageSwitcher sees
 * INITIAL_DATA on first render and emits non-canonical hreflang URLs in
 * raw HTML. The shallow-equal guard prevents a no-op state update when
 * the page-level <LocaleContextSetter> useEffect fires with payload
 * identical to the SSR-primed initialData.
 */

import React, { act, useEffect } from 'react';
import { render, screen } from '@testing-library/react';
import { IntlProvider } from 'react-intl';
import LanguageSwitcher from '@/app/components/LanguageSwitcher';
import {
  LocaleContextProvider,
  useLocaleContext,
  type LocaleContextData,
} from '@/lib/contexts/LocaleContext';

jest.mock('next/navigation', () => ({
  usePathname: () => '/en/politics/article-en',
  useRouter: () => ({
    push: jest.fn(), replace: jest.fn(), prefetch: jest.fn(), back: jest.fn(),
  }),
  useSearchParams: () => new URLSearchParams(),
  useParams: () => ({}),
}));

const ARTICLE_INITIAL: LocaleContextData = {
  publishedLocales: ['ro', 'en', 'ru'],
  translatedSlugs: { ro: 'articol-ro', en: 'article-en', ru: 'statya-ru' },
  categoryTranslatedSlugs: { ro: 'politica', en: 'politics', ru: 'politika' },
  context: 'article',
};

describe('LocaleContextProvider — initialData prop', () => {
  it('emits SSR-canonical hrefs on first render when initialData is provided', () => {
    render(
      <IntlProvider locale="en" messages={{}} defaultLocale="ro">
        <LocaleContextProvider initialData={ARTICLE_INITIAL}>
          <LanguageSwitcher />
        </LocaleContextProvider>
      </IntlProvider>
    );

    // The fix-of-record: SSR pass sees article-context slugs immediately,
    // not naive prefix-swap of the pathname.
    expect(screen.getByTestId('locale-switch-ro')).toHaveAttribute(
      'href',
      '/politica/articol-ro' // ro is default locale, no prefix
    );
    expect(screen.getByTestId('locale-switch-ru')).toHaveAttribute(
      'href',
      '/ru/politika/statya-ru'
    );
  });

  it('falls back to INITIAL_DATA when no initialData is provided', () => {
    render(
      <IntlProvider locale="en" messages={{}} defaultLocale="ro">
        <LocaleContextProvider>
          <LanguageSwitcher />
        </LocaleContextProvider>
      </IntlProvider>
    );

    // No context → generic prefix-swap of pathname.
    expect(screen.getByTestId('locale-switch-ro')).toHaveAttribute(
      'href',
      '/politics/article-en' // strips /en, ro is default
    );
  });
});

describe('LocaleContextProvider — shallow-equal short-circuit', () => {
  it('skips state update when setLocaleContext is called with identical payload', () => {
    let capturedSetLocaleContext: ((value: Partial<LocaleContextData>) => void) | null = null;
    let renderCount = 0;

    function Probe() {
      const ctx = useLocaleContext();
      useEffect(() => {
        renderCount += 1;
        capturedSetLocaleContext = ctx.setLocaleContext;
      });
      return <span>{ctx.context}</span>;
    }

    render(
      <LocaleContextProvider initialData={ARTICLE_INITIAL}>
        <Probe />
      </LocaleContextProvider>
    );

    const baseline = renderCount;

    // Calling setLocaleContext with shallow-equal payload should be a no-op.
    act(() => {
      capturedSetLocaleContext?.({
        context: 'article',
        publishedLocales: ['ro', 'en', 'ru'],
        translatedSlugs: { ro: 'articol-ro', en: 'article-en', ru: 'statya-ru' },
        categoryTranslatedSlugs: { ro: 'politica', en: 'politics', ru: 'politika' },
      });
    });

    expect(renderCount).toBe(baseline);
  });

  it('does re-render when setLocaleContext is called with a different payload', () => {
    let capturedSetLocaleContext: ((value: Partial<LocaleContextData>) => void) | null = null;
    let renderCount = 0;

    function Probe() {
      const ctx = useLocaleContext();
      useEffect(() => {
        renderCount += 1;
        capturedSetLocaleContext = ctx.setLocaleContext;
      });
      return <span>{ctx.context}</span>;
    }

    render(
      <LocaleContextProvider initialData={ARTICLE_INITIAL}>
        <Probe />
      </LocaleContextProvider>
    );

    const baseline = renderCount;

    act(() => {
      capturedSetLocaleContext?.({
        context: 'category',
        translatedSlugs: { ro: 'altceva' },
      });
    });

    expect(renderCount).toBeGreaterThan(baseline);
  });

  it('resetLocaleContext is a no-op when current state already equals INITIAL_DATA', () => {
    let captured: { reset: () => void } | null = null;
    let renderCount = 0;

    function Probe() {
      const ctx = useLocaleContext();
      useEffect(() => {
        renderCount += 1;
        captured = { reset: ctx.resetLocaleContext };
      });
      return null;
    }

    // No initialData → starts as INITIAL_DATA
    render(
      <LocaleContextProvider>
        <Probe />
      </LocaleContextProvider>
    );

    const baseline = renderCount;
    act(() => {
      captured?.reset();
    });
    expect(renderCount).toBe(baseline);
  });
});
