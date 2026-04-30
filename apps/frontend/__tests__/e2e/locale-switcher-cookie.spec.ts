/**
 * E2E — LanguageSwitcher NEXT_LOCALE cookie sync (bugfix/locale-switcher-ro-cookie)
 *
 * Regression test for the loop where clicking RO from a non-RO page produced an
 * unprefixed href (per applyLocalePrefix + prefixDefault:false), proxy.ts then
 * resolved the locale from the still-stale NEXT_LOCALE cookie, and redirected
 * back to the previous locale.
 *
 * The fix: LanguageSwitcher writes NEXT_LOCALE=<target> synchronously on click,
 * before Next.js navigation. We assert here that all 6 cross-locale transitions
 * land on the requested locale and don't re-redirect to the source locale.
 *
 * Fixture (article 5, three-locale published; verified live against dev DB on
 * 2026-04-30 — sprint-59 fixture article 100 was stale and replaced):
 *
 *   Category 1 ("Politică")           Article slug per locale
 *   ─────────────────────────────────  ────────────────────────────────────────
 *   ro: /politica                      tiraspolul-cere-chisinaului-sa-renunte-...
 *   en: /politics                      tiraspol-calls-on-chisinau-to-drop-new-...
 *   ru: /politika                      tiraspol-prizyvaet-kishinev-otkazatsya-...
 *
 * Both the category and article slugs are translated in the dev DB
 * (`categories.slug` + `ext_translations` for object_class App\\Entity\\Category
 * id=1 and object_class App\\Entity\\Article foreign_key=5, fields slug+title).
 * Tests therefore use the canonical per-locale URL and assert that clicks land
 * on the canonical URL of the target locale — not just a locale-prefix swap of
 * the source URL.
 */

import { test, expect, type Page } from '@playwright/test';

type Locale = 'ro' | 'en' | 'ru';

const ARTICLE_3_LOCALE = {
  category: {
    ro: 'politica',
    en: 'politics',
    ru: 'politika',
  },
  slugs: {
    ro: 'tiraspolul-cere-chisinaului-sa-renunte-la-noile-taxe-pentru-intreprinderile-de-pe-malul-stang',
    en: 'tiraspol-calls-on-chisinau-to-drop-new-taxes-for-enterprises-on-the-left-bank',
    ru: 'tiraspol-prizyvaet-kishinev-otkazatsya-ot-novyh-nalogov-dlya-predpriyatiy-levoberezhya',
  },
} as const satisfies {
  category: Record<Locale, string>;
  slugs: Record<Locale, string>;
};

function articlePath(locale: Locale): string {
  // Always pre-prefix the start URL with the locale so we land on the canonical
  // page directly (proxy would otherwise prefix unprefixed paths to /ro/).
  return `/${locale}/${ARTICLE_3_LOCALE.category[locale]}/${ARTICLE_3_LOCALE.slugs[locale]}`;
}

function expectedDestinationRegex(locale: Locale): RegExp {
  // After click + proxy redirect, the URL must contain the target-locale
  // prefix AND the target-locale category slug AND the target-locale article
  // slug. Anchoring on all three guards against:
  //   (a) the cookie loop (wrong locale prefix),
  //   (b) the switcher falling back to generic prefix-swap (wrong category
  //       and/or article slug).
  // Note: proxy always prefixes /ro/ even when prefixDefault:false, so the
  // browser-bar URL for RO contains /ro/ as well.
  return new RegExp(
    `/${locale}/${ARTICLE_3_LOCALE.category[locale]}/${ARTICLE_3_LOCALE.slugs[locale]}(?:[/?#]|$)`
  );
}

async function switchLocaleAndAssert(
  page: Page,
  fromLocale: Locale,
  toLocale: Locale
): Promise<void> {
  const startPath = articlePath(fromLocale);
  await page.goto(startPath);
  // networkidle waits long enough for LocaleContextSetter (a useEffect-driven
  // client component) to populate publishedLocales + translatedSlugs +
  // categoryTranslatedSlugs into LocaleContext, so the switcher hrefs reflect
  // the canonical translated URLs rather than the SSR generic-prefix fallback.
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(new RegExp(`/${fromLocale}/${ARTICLE_3_LOCALE.category[fromLocale]}/`));

  // The switcher may render in multiple slots (header + mobile drawer); the
  // first visible one is the canonical click target.
  const switcher = page.getByTestId(`locale-switch-${toLocale}`).first();
  await expect(switcher).toBeVisible();

  // Assert pre-click that the rendered href already points at the canonical
  // target-locale URL (translated category + translated article slug). This
  // catches a hydration regression separately from the cookie regression.
  const targetHref = await switcher.getAttribute('href');
  expect(targetHref, `switcher href for ${toLocale} on ${startPath}`).toMatch(
    new RegExp(
      `(?:^|/)${ARTICLE_3_LOCALE.category[toLocale]}/${ARTICLE_3_LOCALE.slugs[toLocale]}(?:[/?#]|$)`
    )
  );

  await switcher.click();
  await page.waitForLoadState('networkidle');

  // Final URL must be on the canonical target-locale URL.
  await expect(page).toHaveURL(expectedDestinationRegex(toLocale));
  // And must NOT be on the source locale (this is the regression guard:
  // proxy was looping back to the source locale before the cookie fix).
  if (fromLocale !== toLocale) {
    await expect(page).not.toHaveURL(new RegExp(`/${fromLocale}/`));
  }
  // The article itself must still render (no error page, no infinite redirect
  // returning a non-200).
  await expect(page.locator('main h1').first()).toBeVisible();

  // NEXT_LOCALE cookie must reflect the new locale.
  const cookies = await page.context().cookies();
  const localeCookie = cookies.find((c) => c.name === 'NEXT_LOCALE');
  expect(localeCookie?.value).toBe(toLocale);
}

test.describe('LanguageSwitcher — cross-locale cookie sync (no proxy loop)', () => {
  // The 6 cross-locale transitions called out in the bugfix prompt.
  test('EN → RO does not loop back to EN', async ({ page }) => {
    await switchLocaleAndAssert(page, 'en', 'ro');
  });

  test('EN → RU lands on canonical /ru/politika/<slug-ru>', async ({ page }) => {
    await switchLocaleAndAssert(page, 'en', 'ru');
  });

  test('RO → EN lands on canonical /en/politics/<slug-en>', async ({ page }) => {
    await switchLocaleAndAssert(page, 'ro', 'en');
  });

  test('RO → RU lands on canonical /ru/politika/<slug-ru>', async ({ page }) => {
    await switchLocaleAndAssert(page, 'ro', 'ru');
  });

  test('RU → RO does not loop back to RU and lands on canonical RO URL', async ({ page }) => {
    await switchLocaleAndAssert(page, 'ru', 'ro');
  });

  test('RU → EN lands on canonical /en/politics/<slug-en>', async ({ page }) => {
    await switchLocaleAndAssert(page, 'ru', 'en');
  });
});
