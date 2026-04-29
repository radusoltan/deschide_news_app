/**
 * Unit tests for app/components/LanguageSwitcher.tsx
 *
 * Covers T59.1 / ADR-028 cross-locale switcher behavior:
 *  F1 — Visible RO/EN/RU buttons; active state on current locale
 *  F2 — Disabled (aria-disabled) state for locales not in publishedLocales
 *       and tooltip via languageSwitcher.notTranslated
 *  Slug application across article / category / generic contexts
 *  Props vs LocaleContext precedence (props win)
 *
 * The component lives in `app/components/LanguageSwitcher.tsx` and pulls
 * its current locale from `useIntl().locale`. We wrap renders in
 * IntlProvider + LocaleContextProvider to exercise both code paths.
 */

import React from 'react';
import { render, screen } from '@testing-library/react';
import { IntlProvider } from 'react-intl';
import LanguageSwitcher, {
  type LanguageSwitcherProps,
} from '@/app/components/LanguageSwitcher';
import {
  LocaleContextProvider,
  useLocaleContext,
  type LocaleContextData,
} from '@/lib/contexts/LocaleContext';
import type { Locale } from '@/lib/types';

// Override the default jest.setup.js usePathname mock per-test as needed.
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

interface RenderOptions {
  locale?: Locale;
  pathname?: string;
  contextData?: Partial<LocaleContextData>;
  messages?: Record<string, string>;
}

/**
 * Helper: render <LanguageSwitcher> with IntlProvider + optional context
 * pre-populated via a tiny seeder component.
 */
function renderSwitcher(
  props: LanguageSwitcherProps = {},
  options: RenderOptions = {}
) {
  const {
    locale = 'ro',
    pathname = '/',
    contextData,
    messages = {},
  } = options;

  mockUsePathname.mockReturnValue(pathname);

  function ContextSeeder({ children }: { children: React.ReactNode }) {
    const { setLocaleContext } = useLocaleContext();
    React.useEffect(() => {
      if (contextData) {
        setLocaleContext(contextData);
      }
      // Seeded once per render — safe to skip deps.
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);
    return <>{children}</>;
  }

  return render(
    <IntlProvider locale={locale} messages={messages} defaultLocale="ro">
      <LocaleContextProvider>
        <ContextSeeder>
          <LanguageSwitcher {...props} />
        </ContextSeeder>
      </LocaleContextProvider>
    </IntlProvider>
  );
}

describe('LanguageSwitcher — F1: rendering & active state', () => {
  it('renders 3 locale entries (RO, EN, RU)', () => {
    renderSwitcher();

    expect(screen.getByText('Română')).toBeInTheDocument();
    expect(screen.getByText('English')).toBeInTheDocument();
    expect(screen.getByText('Русский')).toBeInTheDocument();
  });

  it('marks the current locale with aria-current="true"', () => {
    renderSwitcher({}, { locale: 'en' });

    const current = screen.getByText('English').closest('a');
    expect(current).toHaveAttribute('aria-current', 'true');

    const other = screen.getByText('Română').closest('a');
    expect(other).not.toHaveAttribute('aria-current');
  });

  it('exposes data-testid for active and disabled variants', () => {
    renderSwitcher({ publishedLocales: ['ro'] }, { locale: 'ro' });

    // Active locale renders as link
    expect(screen.getByTestId('locale-switch-ro')).toBeInTheDocument();
    // Unpublished locales render disabled
    expect(screen.getByTestId('locale-switch-en-disabled')).toBeInTheDocument();
    expect(screen.getByTestId('locale-switch-ru-disabled')).toBeInTheDocument();
  });
});

describe('LanguageSwitcher — F2: published locales gating', () => {
  it('renders all 3 as links when publishedLocales contains all locales', () => {
    renderSwitcher(
      { publishedLocales: ['ro', 'en', 'ru'] },
      { locale: 'ro', pathname: '/' }
    );

    expect(screen.getByTestId('locale-switch-ro')).toBeInTheDocument();
    expect(screen.getByTestId('locale-switch-en')).toBeInTheDocument();
    expect(screen.getByTestId('locale-switch-ru')).toBeInTheDocument();
    expect(screen.queryByTestId('locale-switch-en-disabled')).not.toBeInTheDocument();
    expect(screen.queryByTestId('locale-switch-ru-disabled')).not.toBeInTheDocument();
  });

  it('renders unpublished locales as aria-disabled span (role=link)', () => {
    renderSwitcher(
      { publishedLocales: ['ro'] },
      { locale: 'ro', pathname: '/' }
    );

    const disabledEn = screen.getByTestId('locale-switch-en-disabled');
    expect(disabledEn).toHaveAttribute('aria-disabled', 'true');
    expect(disabledEn).toHaveAttribute('role', 'link');
    expect(disabledEn.tagName).toBe('SPAN');
  });

  it('uses the languageSwitcher.notTranslated tooltip when provided', () => {
    renderSwitcher(
      { publishedLocales: ['ro'] },
      {
        locale: 'ro',
        messages: {
          'languageSwitcher.notTranslated': 'Articolul nu este tradus',
        },
      }
    );

    const disabled = screen.getByTestId('locale-switch-en-disabled');
    expect(disabled).toHaveAttribute('title', 'Articolul nu este tradus');
  });

  it('falls back to a per-locale default tooltip when no message provided', () => {
    // react-intl logs MISSING_TRANSLATION when no message matches; that is the
    // *intended* path — defaultMessage is used. Silence it for cleanliness.
    const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
    try {
      renderSwitcher(
        { publishedLocales: ['ro'] },
        { locale: 'ro' } // no messages → defaultMessage applies
      );

      const disabled = screen.getByTestId('locale-switch-en-disabled');
      // Romanian fallback string is fixed in implementation.
      expect(disabled).toHaveAttribute(
        'title',
        'Articolul nu este tradus în această limbă'
      );
    } finally {
      errorSpy.mockRestore();
    }
  });
});

describe('LanguageSwitcher — translatedSlugs application', () => {
  it('uses translated article slug + translated category slug for href (article context)', () => {
    renderSwitcher(
      {
        context: 'article',
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
      },
      { locale: 'ro' }
    );

    const enLink = screen.getByTestId('locale-switch-en');
    expect(enLink).toHaveAttribute('href', '/en/politics/article-en');

    const ruLink = screen.getByTestId('locale-switch-ru');
    expect(ruLink).toHaveAttribute('href', '/ru/politika/statya-ru');
  });

  it('uses translated category slug for href (category context)', () => {
    renderSwitcher(
      {
        context: 'category',
        publishedLocales: ['ro', 'en', 'ru'],
        translatedSlugs: {
          ro: 'politica',
          en: 'politics',
          ru: 'politika',
        },
      },
      { locale: 'ro' }
    );

    expect(screen.getByTestId('locale-switch-en')).toHaveAttribute(
      'href',
      '/en/politics'
    );
    expect(screen.getByTestId('locale-switch-ru')).toHaveAttribute(
      'href',
      '/ru/politika'
    );
  });

  it('preserves path with locale swap (generic context, default)', () => {
    renderSwitcher({}, { locale: 'ro', pathname: '/about' });

    expect(screen.getByTestId('locale-switch-en')).toHaveAttribute(
      'href',
      '/en/about'
    );
    expect(screen.getByTestId('locale-switch-ru')).toHaveAttribute(
      'href',
      '/ru/about'
    );
    // RO is current and rendered as link too (generic always succeeds).
    expect(screen.getByTestId('locale-switch-ro')).toHaveAttribute('href', '/about');
  });

  it('disables a locale when its translated slug is missing in article context', () => {
    renderSwitcher(
      {
        context: 'article',
        // publishedLocales lists all 3, but translatedSlugs lacks RU
        publishedLocales: ['ro', 'en', 'ru'],
        translatedSlugs: { ro: 'articol-ro', en: 'article-en' },
        categoryTranslatedSlugs: {
          ro: 'politica',
          en: 'politics',
          ru: 'politika',
        },
      },
      { locale: 'ro' }
    );

    // RU has no translated article slug → builder returns null → disabled
    expect(screen.getByTestId('locale-switch-ru-disabled')).toBeInTheDocument();
    expect(screen.queryByTestId('locale-switch-ru')).not.toBeInTheDocument();
    // EN is fine
    expect(screen.getByTestId('locale-switch-en')).toHaveAttribute(
      'href',
      '/en/politics/article-en'
    );
  });
});

describe('LanguageSwitcher — context vs props precedence', () => {
  it('reads publishedLocales + translatedSlugs from LocaleContext when no props are passed', () => {
    renderSwitcher(
      {},
      {
        locale: 'ro',
        contextData: {
          context: 'article',
          publishedLocales: ['ro', 'en'],
          translatedSlugs: { ro: 'articol', en: 'article' },
          categoryTranslatedSlugs: { ro: 'politica', en: 'politics' },
        },
      }
    );

    // RU not in publishedLocales → disabled via context
    expect(screen.getByTestId('locale-switch-ru-disabled')).toBeInTheDocument();
    // EN link uses context-provided slugs
    expect(screen.getByTestId('locale-switch-en')).toHaveAttribute(
      'href',
      '/en/politics/article'
    );
  });

  it('props override context when both are present', () => {
    renderSwitcher(
      {
        // Props say only RO is published
        publishedLocales: ['ro'],
      },
      {
        locale: 'ro',
        contextData: {
          // Context says all three locales are published
          context: 'generic',
          publishedLocales: ['ro', 'en', 'ru'],
        },
      }
    );

    // Props win → EN and RU disabled
    expect(screen.getByTestId('locale-switch-en-disabled')).toBeInTheDocument();
    expect(screen.getByTestId('locale-switch-ru-disabled')).toBeInTheDocument();
  });
});
