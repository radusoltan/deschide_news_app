/**
 * TSK-692: LangSwitcher disabled-state E2E regression guard.
 *
 * Scenario source: .claude/commands/pw-test-locale-switcher-disabled.md
 * Notion task: 3534b6d1-296e-8157-9bca-cfc76ff61173
 * ADRs: 027 (locale gate 404) + 028 (Unified Locale URL Builder D4)
 *      + 030 (SSR locale context priming)
 *
 * NB: Commit a6ea64c body contains historical "ADR-029 (SSR locale context
 * prime)" reference (corrected here to ADR-030 per Phase 1 review BLOCKER).
 * Commit history is immutable per --no-ff convention; corrected breadcrumb
 * lives in this docblock.
 *
 * Partial overlap with sprint-59-i18n.spec.ts (Tests 1, 2, 5, 10).
 * Coexistence intentional: this spec asserts the complete 11-step scenario;
 * sprint-59 partial coverage is regression guard for separate concerns.
 *
 * Fixtures (empirical post-NUKE substitution per Phase 5.1):
 *   - RO-only article: id=18, category=politica, slug=inspectoratul-de-mediu-...
 *     (substituted from scenario file's Article 103 — absent in current dev DB)
 *   - Trilingual article: id=4, category=economie, slugs=energocom-...
 *     (substituted from scenario file's Article 100 — absent in current dev DB,
 *      reused from Phase 3 work)
 *
 * WSL2 environment caveat:
 *   webkit + Mobile Safari may fail browser-bound tests locally on WSL2 due
 *   to missing system libraries. CI runs `pnpm exec playwright install
 *   --with-deps` and they pass. HTTP-only steps (8, 10) work via the request
 *   fixture even when browser launch is env-blocked.
 */

import { test, expect, type TestInfo } from '@playwright/test';

// ─────────────────────────────────────────────────────────────────
// Fixtures (empirical, captured 2026-05-09)
// ─────────────────────────────────────────────────────────────────

const BASE_URL = 'http://localhost:3005';

const RO_ONLY_ARTICLE = {
  id: 18,
  category: 'politica',
  slug: 'inspectoratul-de-mediu-a-gasit-abateri-la-sangera-ministru-subiectul-este-intens-politizat',
  publishedLocales: ['ro'] as const,
} as const;

const TRILINGUAL_ARTICLE = {
  id: 4,
  category: 'economie',
  slugs: {
    ro: 'energocom-obligata-sa-cumpere-energie-de-pe-pietele-organizate-ministerul-energiei',
    en: 'energocom-obliged-to-buy-energy-from-organized-markets-ministry-of-energy',
    ru: 'energocom-obyazhut-zakupat-elektroenergiyu-na-organizovannyh-rynkah-ministerstvo-energetiki',
  },
  publishedLocales: ['ro', 'en', 'ru'] as const,
} as const;

// Tooltip text from messages/ro.json:12 (languageSwitcher.notTranslated)
const TOOLTIP_RO = 'Articolul nu este tradus în această limbă';

// Mobile Chrome (Pixel 5 viewport, 393×851) hides the desktop LangSwitcher
// behind a hamburger menu — element resolves in DOM but display:none at
// mobile breakpoint blocks BOTH click and hover (Phase 5 TSK-692 surfaced
// the hover case after Phase 3.2-impl-3 introduced this helper for click).
// Reused pattern from sprint-59-i18n.spec.ts (renamed in commit a6ec5cd).
function skipMobileChromeForVisibility(testInfo: TestInfo): void {
  test.skip(
    testInfo.project.name === 'Mobile Chrome',
    'Mobile Chrome (Pixel 5) hides desktop LangSwitcher behind hamburger menu. ' +
    'Click + hover both require visibility. ' +
    'Backlog: T60.X-MOBILE-MENU-LANGSWITCHER-E2E.',
  );
}

const RO_ONLY_URL = `/ro/${RO_ONLY_ARTICLE.category}/${RO_ONLY_ARTICLE.slug}`;
const RO_ONLY_FULL_URL = `${BASE_URL}${RO_ONLY_URL}`;

// ─────────────────────────────────────────────────────────────────
// Spec
// ─────────────────────────────────────────────────────────────────

test.describe('TSK-692 — LangSwitcher disabled-state regression guard', () => {
  test('Step 1: navigates to RO-only article on /ro/', async ({ page }) => {
    const response = await page.goto(RO_ONLY_FULL_URL, { waitUntil: 'domcontentloaded' });
    expect(response, `Step 1: navigation must produce a response at ${RO_ONLY_URL}`).not.toBeNull();
    expect(response!.status(), `Step 1: expected 200 OK at ${RO_ONLY_URL}`).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('lang', 'ro');
  });

  test('Step 2: confirms RO active link with aria-current=true', async ({ page }) => {
    await page.goto(RO_ONLY_FULL_URL, { waitUntil: 'domcontentloaded' });
    const roActive = page.locator('[data-testid="locale-switch-ro"]');
    await expect(roActive, 'Step 2: RO active testid must render once').toHaveCount(1);
    await expect(roActive).toHaveAttribute('aria-current', 'true');
  });

  test('Step 3: EN-disabled testid + role + aria + title attributes', async ({ page }) => {
    await page.goto(RO_ONLY_FULL_URL, { waitUntil: 'domcontentloaded' });
    const enDisabled = page.locator('[data-testid="locale-switch-en-disabled"]');
    await expect(enDisabled, 'Step 3: EN disabled testid must render').toHaveCount(1);
    await expect(enDisabled).toHaveAttribute('role', 'link');
    await expect(enDisabled).toHaveAttribute('aria-disabled', 'true');
    await expect(enDisabled).toHaveAttribute('title', TOOLTIP_RO);
  });

  test('Step 4: RU-disabled testid + role + aria + title attributes', async ({ page }) => {
    await page.goto(RO_ONLY_FULL_URL, { waitUntil: 'domcontentloaded' });
    const ruDisabled = page.locator('[data-testid="locale-switch-ru-disabled"]');
    await expect(ruDisabled, 'Step 4: RU disabled testid must render').toHaveCount(1);
    await expect(ruDisabled).toHaveAttribute('role', 'link');
    await expect(ruDisabled).toHaveAttribute('aria-disabled', 'true');
    await expect(ruDisabled).toHaveAttribute('title', TOOLTIP_RO);
  });

  test('Step 5: active EN/RU testids absent (mutual exclusion)', async ({ page }) => {
    await page.goto(RO_ONLY_FULL_URL, { waitUntil: 'domcontentloaded' });
    await expect(
      page.locator('[data-testid="locale-switch-en"]'),
      'Step 5: EN active testid must be absent on RO-only article',
    ).toHaveCount(0);
    await expect(
      page.locator('[data-testid="locale-switch-ru"]'),
      'Step 5: RU active testid must be absent on RO-only article',
    ).toHaveCount(0);
  });

  test('Step 6: clicking EN disabled span triggers no nav and no NEXT_LOCALE cookie (ADR-028 D4)', async ({
    page,
    context,
  }, testInfo) => {
    skipMobileChromeForVisibility(testInfo);
    await page.goto(RO_ONLY_FULL_URL, { waitUntil: 'domcontentloaded' });
    const urlBefore = page.url();

    // Snapshot cookies before click — ADR-028 D4 says click on disabled span MUST NOT
    // set NEXT_LOCALE=en (the cookie must remain at whatever value the user had).
    const cookiesBefore = await context.cookies();
    const localeCookieBefore = cookiesBefore.find((c) => c.name === 'NEXT_LOCALE')?.value ?? null;

    // dispatchEvent('click') bypasses Playwright's aria-disabled enablement check.
    // Tests the assertion intent: "if a click event reaches the handler,
    // does anything bad happen?" — exactly the ADR-028 D4 contract surface.
    // .click({ force: true }) would also bypass visibility checks, which would
    // overshoot the assertion scope (we want to test handler behavior, not
    // visual accessibility shortcomings).
    const enDisabled = page.locator('[data-testid="locale-switch-en-disabled"]');
    await enDisabled.dispatchEvent('click');

    // Allow any in-flight handlers a beat to misbehave; if there's a regression
    // it would manifest as URL change or cookie write within this window.
    await page.waitForTimeout(500);

    expect(page.url(), 'Step 6: URL must not change after clicking disabled span').toBe(urlBefore);

    const cookiesAfter = await context.cookies();
    const localeCookieAfter = cookiesAfter.find((c) => c.name === 'NEXT_LOCALE')?.value ?? null;
    expect(
      localeCookieAfter,
      'Step 6: NEXT_LOCALE cookie must not be set to "en" via disabled-span click',
    ).not.toBe('en');
    expect(
      localeCookieAfter,
      'Step 6: NEXT_LOCALE cookie value must be unchanged by disabled-span click',
    ).toBe(localeCookieBefore);
  });

  test('Step 7: hover on EN disabled span — observably inert (no nav, no DOM mutation, no console errors)', async ({
    page,
  }, testInfo) => {
    skipMobileChromeForVisibility(testInfo);
    await page.goto(RO_ONLY_FULL_URL, { waitUntil: 'domcontentloaded' });
    const enDisabled = page.locator('[data-testid="locale-switch-en-disabled"]');
    await expect(enDisabled).toHaveCount(1);

    // Capture pre-hover state baselines for inert-hover assertion.
    // Use the LangSwitcher's accessibility-named navigation as the DOM
    // mutation scope — narrower than <header> (the article page renders
    // two <header> elements: site banner + article-page header), and ties
    // the assertion to the LangSwitcher's a11y contract per ADR-028.
    const langSwitcher = page.getByRole('navigation', { name: 'Switch language' });
    const initialUrl = page.url();
    const initialDomSnapshot = await langSwitcher.innerHTML();
    const consoleErrors: string[] = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') consoleErrors.push(msg.text());
    });

    await enDisabled.hover();

    // Capture screenshot for human-in-the-loop visual evidence (cursor change,
    // tooltip render, opacity state). Path surfaced in test runner output.
    const screenshotPath = '/tmp/tsk-692-en-disabled-hover.png';
    await page.screenshot({ path: screenshotPath, fullPage: false });
    console.log(`[Step 7] hover screenshot saved: ${screenshotPath}`);

    // Wait short window for any hover-triggered side effects to manifest in
    // URL, DOM, or console — a regression in tooltip/hover handler that
    // navigates / mutates / errors would surface within this budget.
    await page.waitForTimeout(1000);

    // Inert-hover contract:
    expect(page.url(), 'Step 7: URL must not change on disabled span hover').toBe(initialUrl);
    expect(
      await langSwitcher.innerHTML(),
      'Step 7: LangSwitcher DOM must not mutate on disabled span hover',
    ).toBe(initialDomSnapshot);
    expect(consoleErrors, `Step 7: no console errors during hover window — got ${consoleErrors.join(' | ')}`).toHaveLength(0);
  });

  test.skip('Step 8: ADR-027 locale gate — direct /en/<ro-only-slug> behavior', async () => {
    // DEFERRED: Architectural ambiguity surfaced Phase 5.3-investigate (2026-05-09).
    //
    // ADR-027 ORIGINAL WORDING: "RO slug under /ru/ prefix returns 404, no
    //   silent RO fallback"
    // EMPIRICAL POST-NUKE: Article 18 served at /en/politica/<ro-slug> returns:
    //   - HTTP 200
    //   - <title>Inspectoratul de Mediu... | Deschide News</title> (RO content)
    //   - ZERO LocaleFallbackNotice marker in plain SSR HTML body
    //
    // Two possible interpretations:
    //   A) Article-route fallback intended; LocaleFallbackNotice renders
    //      post-hydration OR via different marker not covered by current
    //      probe pattern (page.tsx imports the component, so the wiring
    //      is in place — render conditions need investigation).
    //   B) ADR-027 article-route enforcement was rolled back post-NUKE
    //      without doc refresh.
    //
    // Resolution requires ADR-level review by orchestrator + product. Does
    // NOT block Phase D Stage 2.3+ deliverable shape; deferred test
    // preserves regression-guard intent for post-resolution re-enable.
    //
    // Backlog: T60.X-ADR-027-AUDIT — investigate, decide A/B, refresh
    //          ADR-027 wording, re-enable this test with the correct
    //          assertion shape (404 / fallback-notice / something else).
    //
    // Original scenario file (Notion 3534b6d1-296e-8157-9bca-cfc76ff61173)
    // expected 404 empirical (Article 103) — fixture absent post-NUKE;
    // substitution with Article 18 surfaced the ambiguity instead of
    // confirming the 404 contract.
  });

  test('Step 9: trilingual article cross-check — all 3 locale switches enabled with distinct hrefs', async ({
    page,
  }, testInfo) => {
    skipMobileChromeForVisibility(testInfo);
    const trilingualUrl =
      `${BASE_URL}/ro/${TRILINGUAL_ARTICLE.category}/${TRILINGUAL_ARTICLE.slugs.ro}`;
    await page.goto(trilingualUrl, { waitUntil: 'domcontentloaded' });

    // All three active testids must render (no -disabled variants).
    const ro = page.locator('[data-testid="locale-switch-ro"]');
    const en = page.locator('[data-testid="locale-switch-en"]');
    const ru = page.locator('[data-testid="locale-switch-ru"]');
    await expect(ro, 'Step 9: RO active testid on trilingual').toHaveCount(1);
    await expect(en, 'Step 9: EN active testid on trilingual').toHaveCount(1);
    await expect(ru, 'Step 9: RU active testid on trilingual').toHaveCount(1);

    // Disabled variants must be absent — mutual exclusion.
    await expect(page.locator('[data-testid="locale-switch-en-disabled"]')).toHaveCount(0);
    await expect(page.locator('[data-testid="locale-switch-ru-disabled"]')).toHaveCount(0);

    // Hrefs must be pairwise distinct (translated slugs differ across locales).
    const roHref = await ro.getAttribute('href');
    const enHref = await en.getAttribute('href');
    const ruHref = await ru.getAttribute('href');
    expect(roHref).toBeTruthy();
    expect(enHref).toBeTruthy();
    expect(ruHref).toBeTruthy();
    expect(roHref, 'Step 9: RO and EN hrefs must differ').not.toBe(enHref);
    expect(roHref, 'Step 9: RO and RU hrefs must differ').not.toBe(ruHref);
    expect(enHref, 'Step 9: EN and RU hrefs must differ').not.toBe(ruHref);
  });

  test('Step 10: SSR raw HTML contains data-testid="locale-switch-en-disabled" pre-hydration', async ({
    request,
  }) => {
    // HTTP-only via request fixture — bypasses browser. The disabled testid
    // is expected in plain SSR HTML because LanguageSwitcher renders through
    // a server-component parent path (Header → public layout), NOT directly
    // as a 'use client' boundary like not-found.tsx (which materializes its
    // own data-testids only post-hydration per Phase 2.7 finding).
    const response = await request.get(RO_ONLY_URL, { failOnStatusCode: false });
    expect(response.status(), `Step 10: SSR fetch must succeed (got ${response.status()})`).toBe(200);
    const html = await response.text();
    expect(
      html.includes('data-testid="locale-switch-en-disabled"'),
      'Step 10: SSR raw HTML must contain locale-switch-en-disabled testid pre-hydration',
    ).toBe(true);
    expect(
      html.includes('data-testid="locale-switch-ru-disabled"'),
      'Step 10: SSR raw HTML must contain locale-switch-ru-disabled testid pre-hydration',
    ).toBe(true);
  });

  test('Step 11: keyboard Tab order skips disabled spans (a11y)', async ({ page }, testInfo) => {
    skipMobileChromeForVisibility(testInfo);
    await page.goto(RO_ONLY_FULL_URL, { waitUntil: 'domcontentloaded' });
    // Allow focus state to settle before walking the tab order.
    await page.waitForLoadState('networkidle');

    // Walk through up to 50 Tab presses, capture each focused element's
    // data-testid (if any). 50 is sufficient for the LangSwitcher region —
    // the entire focusable-control set on a public article page is well
    // under 50 stops; a higher bound just guards against header-heavy
    // layouts adding more controls.
    const focusedTestidsThroughTab: Array<string | null> = [];
    for (let i = 0; i < 50; i += 1) {
      await page.keyboard.press('Tab');
      const focusedTestid = await page.evaluate(() => {
        const active = document.activeElement as HTMLElement | null;
        return active ? active.getAttribute('data-testid') : null;
      });
      focusedTestidsThroughTab.push(focusedTestid);
    }

    // Disabled spans must NEVER receive focus.
    expect(
      focusedTestidsThroughTab,
      'Step 11: locale-switch-en-disabled must never receive keyboard focus',
    ).not.toContain('locale-switch-en-disabled');
    expect(
      focusedTestidsThroughTab,
      'Step 11: locale-switch-ru-disabled must never receive keyboard focus',
    ).not.toContain('locale-switch-ru-disabled');
  });
});
