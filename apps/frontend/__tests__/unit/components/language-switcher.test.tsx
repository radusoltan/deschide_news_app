/**
 * LanguageSwitcher unit tests
 *
 * Covers the disabled-state behavior driven by `publishedLocales` (T60.6 / ADR-028 refinement):
 * - locales not present in `publishedLocales` render as aria-disabled spans
 * - the disabled span is NOT clickable (no <a href>)
 * - prop wins over PublishedLocalesContext when both provide a value
 * - when both are absent, all locales remain enabled
 */

import { fireEvent, render, screen } from '@testing-library/react';
import { IntlProvider } from 'react-intl';
import LanguageSwitcher from '@/app/components/LanguageSwitcher';
import {
  PublishedLocalesProvider,
  usePublishedLocales,
} from '@/lib/contexts/PublishedLocalesContext';
import { useEffect } from 'react';

jest.mock('next/navigation', () => ({
  usePathname: () => '/ro/politica/some-article',
}));

function ContextSeed({ locales }: { locales?: string[] }) {
  const { setPublishedLocales } = usePublishedLocales();
  useEffect(() => {
    setPublishedLocales(locales);
  }, [locales, setPublishedLocales]);
  return null;
}

function renderSwitcher(
  ui: React.ReactNode,
  intlLocale: 'ro' | 'en' | 'ru' = 'ro',
) {
  return render(
    <IntlProvider locale={intlLocale} messages={{}}>
      <PublishedLocalesProvider>{ui}</PublishedLocalesProvider>
    </IntlProvider>,
  );
}

describe('LanguageSwitcher (publishedLocales disabled state)', () => {
  it('renders all locales as enabled links when no publishedLocales is provided', () => {
    renderSwitcher(<LanguageSwitcher />);

    expect(screen.getByTestId('locale-switch-ro')).toBeInTheDocument();
    expect(screen.getByTestId('locale-switch-en')).toBeInTheDocument();
    expect(screen.getByTestId('locale-switch-ru')).toBeInTheDocument();
    expect(screen.queryByTestId('locale-switch-en-disabled')).not.toBeInTheDocument();
    expect(screen.queryByTestId('locale-switch-ru-disabled')).not.toBeInTheDocument();
  });

  it('renders RU as aria-disabled when publishedLocales prop excludes it', () => {
    renderSwitcher(<LanguageSwitcher publishedLocales={['ro', 'en']} />);

    const ru = screen.getByTestId('locale-switch-ru-disabled');
    expect(ru).toBeInTheDocument();
    expect(ru).toHaveAttribute('aria-disabled', 'true');
    expect(ru.tagName).toBe('SPAN'); // not <a>
  });

  it('disabled locale span has no href and clicking it does not navigate', () => {
    renderSwitcher(<LanguageSwitcher publishedLocales={['ro']} />);

    const en = screen.getByTestId('locale-switch-en-disabled');
    expect(en).not.toHaveAttribute('href');

    // Clicking is a no-op (just confirm no error is thrown and aria stays disabled)
    fireEvent.click(en);
    expect(en).toHaveAttribute('aria-disabled', 'true');
  });

  it('reads publishedLocales from context when no prop is given', () => {
    renderSwitcher(
      <>
        <ContextSeed locales={['ro']} />
        <LanguageSwitcher />
      </>,
    );

    expect(screen.getByTestId('locale-switch-en-disabled')).toBeInTheDocument();
    expect(screen.getByTestId('locale-switch-ru-disabled')).toBeInTheDocument();
    expect(screen.getByTestId('locale-switch-ro')).toBeInTheDocument();
  });

  it('prop value wins over context value', () => {
    renderSwitcher(
      <>
        <ContextSeed locales={['ro']} />
        <LanguageSwitcher publishedLocales={['ro', 'en', 'ru']} />
      </>,
    );

    expect(screen.queryByTestId('locale-switch-en-disabled')).not.toBeInTheDocument();
    expect(screen.queryByTestId('locale-switch-ru-disabled')).not.toBeInTheDocument();
  });
});
