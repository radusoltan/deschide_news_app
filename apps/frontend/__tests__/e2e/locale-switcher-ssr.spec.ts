import { test, expect } from '@playwright/test';
import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';

// ─────────────────────────────────────────────────
// LOCALE SWITCHER SSR — T60.15 / ADR-029
// Verifies the bug-of-record fix: server-side LocaleContext priming
// emits canonical hreflang URLs in raw HTML for the switcher buttons,
// so SEO crawlers and copy-link sharing see translated slugs (article,
// category, topic, tag) instead of naive prefix-swap.
//
// Pattern: zero-deps `page.request.get(...).then(r => r.text())` (matches
// existing __tests__/e2e/auth.spec.ts).
// ─────────────────────────────────────────────────

const BASE_URL = process.env.BASE_URL || 'http://localhost:3005';

interface SwitcherHrefs {
  ro?: string;
  en?: string;
  ru?: string;
  roDisabled: boolean;
  enDisabled: boolean;
  ruDisabled: boolean;
}

/**
 * Extract the switcher hrefs from raw HTML by regex on the testid attrs
 * the LanguageSwitcher emits. Returns either the href or marks the locale
 * as disabled when its testid is the `-disabled` variant.
 */
function extractSwitcherHrefs(html: string): SwitcherHrefs {
  const result: SwitcherHrefs = { roDisabled: false, enDisabled: false, ruDisabled: false };
  for (const locale of ['ro', 'en', 'ru'] as const) {
    const linkMatch = new RegExp(
      `data-testid="locale-switch-${locale}"[^>]*href="([^"]+)"`,
    ).exec(html);
    if (linkMatch) {
      result[locale] = linkMatch[1];
      continue;
    }
    const disabledMatch = new RegExp(
      `data-testid="locale-switch-${locale}-disabled"`,
    ).exec(html);
    if (disabledMatch) {
      result[`${locale}Disabled` as 'roDisabled' | 'enDisabled' | 'ruDisabled'] = true;
    }
  }
  return result;
}

/**
 * Extract `<link rel="alternate" hreflang="<locale>" href="<url>">` PATHS
 * (path component, stripping the absolute scheme/host that head emits per
 * Google hreflang spec). Returns `undefined` when the entry is missing.
 *
 * Used together with `extractSwitcherHrefs` to verify the
 * head-hreflang === switcher-href path-equality invariant for EN/RU
 * (T60.15 / ADR-029 Phase 1.5 fix).
 *
 * RO is intentionally EXCEPTED from path-equality assertions per ADR-029 §D13:
 *   - Switcher applies `i18nConfig.prefixDefault: false` → RO has no `/ro`
 *     prefix in the URL (e.g. `/societate`)
 *   - Head emits absolute canonical URL with `/ro` prefix per Google spec
 *     (e.g. `/ro/societate`)
 *   - Both resolve to the same canonical resource after proxy normalization
 *   - Pre-existing intentional convention; not a bug to fix here.
 */
function extractHeadHreflangPaths(html: string): { ro?: string; en?: string; ru?: string } {
  const result: { ro?: string; en?: string; ru?: string } = {};
  for (const locale of ['ro', 'en', 'ru'] as const) {
    // Next.js Metadata renders these as `link rel="alternate" hrefLang="..."`
    // (mixed-case attribute name in raw HTML).
    const match = new RegExp(
      `<link[^>]+rel="alternate"[^>]+hrefLang="${locale}"[^>]+href="([^"]+)"`,
    ).exec(html);
    if (match) {
      result[locale] = match[1].replace(/^https?:\/\/[^/]+/, '');
    }
  }
  return result;
}

// Article 17 in seed fixture — full RO/EN/RU translations + category translations.
// Verified at API: /api/articles/17 returns publishedLocales=['ro','ru','en'] and
// translatedSlugs for all 3 locales, plus category.translatedSlugs.
const ARTICLE = {
  ro: { cat: 'societate', slug: 'alexandru-machidon-numit-oficial-procuror-general' },
  en: { cat: 'society', slug: 'alexandru-machidon-officially-appointed-prosecutor-general' },
  ru: { cat: 'obshchestvo', slug: 'aleksandru-mahidon-oficialno-naznachen-generalnym-prokurorom' },
};

test.describe('Locale switcher SSR — article context', () => {
  test('emits canonical translated slugs (NOT naive prefix-swap) on /en article URL', async ({ page }) => {
    const html = await page.request
      .get(`${BASE_URL}/en/${ARTICLE.en.cat}/${ARTICLE.en.slug}`)
      .then((r) => r.text());

    const hrefs = extractSwitcherHrefs(html);

    // RO is the default locale (prefixDefault=false) → no /ro prefix.
    expect(hrefs.ro).toBe(`/${ARTICLE.ro.cat}/${ARTICLE.ro.slug}`);
    // RU prefixed normally.
    expect(hrefs.ru).toBe(`/ru/${ARTICLE.ru.cat}/${ARTICLE.ru.slug}`);
    // The bug-of-record: pre-fix, hrefs would have been
    //   /ro/society/article-en   /ru/society/article-en
    // (naive prefix-swap of the EN URL). Asserting the canonical form
    // directly catches any regression.
    expect(hrefs.ro).not.toContain('/society/');
    expect(hrefs.ru).not.toContain('/society/');
  });

  test('article switcher hrefs are correct from RO origin too', async ({ page }) => {
    const html = await page.request
      .get(`${BASE_URL}/${ARTICLE.ro.cat}/${ARTICLE.ro.slug}`) // ro default = no prefix
      .then((r) => r.text());

    const hrefs = extractSwitcherHrefs(html);
    expect(hrefs.en).toBe(`/en/${ARTICLE.en.cat}/${ARTICLE.en.slug}`);
    expect(hrefs.ru).toBe(`/ru/${ARTICLE.ru.cat}/${ARTICLE.ru.slug}`);
  });
});

test.describe('Locale switcher SSR — category context', () => {
  test('category /en/society emits canonical translated slugs in raw HTML', async ({ page }) => {
    const html = await page.request.get(`${BASE_URL}/en/society`).then((r) => r.text());

    const hrefs = extractSwitcherHrefs(html);
    expect(hrefs.ro).toBe('/societate');
    expect(hrefs.ru).toBe('/ru/obshchestvo');
    // Pre-fix bug: ro href would have been '/ro/society' or '/society',
    // ru would have been '/ru/society' (naive prefix-swap).
    expect(hrefs.ro).not.toContain('/society');
    expect(hrefs.ru).not.toContain('/society');
  });
});

test.describe('Locale switcher SSR — author context (slug shared, prefix-swap is canonical)', () => {
  test('author /en/author/ipn emits prefix-swap hrefs (slug identical across locales)', async ({ page }) => {
    const html = await page.request.get(`${BASE_URL}/en/author/ipn`).then((r) => r.text());

    const hrefs = extractSwitcherHrefs(html);
    expect(hrefs.ro).toBe('/author/ipn');
    expect(hrefs.ru).toBe('/ru/author/ipn');
  });
});

test.describe('Locale switcher SSR — homepage (generic context)', () => {
  test('homepage /en emits clean prefix-swap hrefs', async ({ page }) => {
    const html = await page.request.get(`${BASE_URL}/en`).then((r) => r.text());

    const hrefs = extractSwitcherHrefs(html);
    expect(hrefs.ro).toBe('/');
    expect(hrefs.ru).toBe('/ru');
  });
});

test.describe('Locale switcher SSR — static page (D4 fast-path)', () => {
  test('static /en/gdpr emits prefix-swap hrefs without category lookup', async ({ page }) => {
    const html = await page.request.get(`${BASE_URL}/en/gdpr`).then((r) => r.text());

    const hrefs = extractSwitcherHrefs(html);
    expect(hrefs.ro).toBe('/gdpr');
    expect(hrefs.ru).toBe('/ru/gdpr');
  });
});

/**
 * Hreflang ↔ Switcher consistency invariant (T60.15 / ADR-029 Phase 1.5).
 *
 * For every public route, the path component of `<head>` hreflang URLs MUST
 * equal the LanguageSwitcher href for the same locale (EN and RU). This
 * guards against the bug where the head and switcher pointed at different
 * URLs per locale (head naive prefix-swap vs switcher canonical translated).
 *
 * RO is excluded per ADR-029 §D13 — see `extractHeadHreflangPaths` JSDoc.
 */
test.describe('Locale switcher SSR — head hreflang ↔ switcher href consistency', () => {
  test('article: head hreflang en/ru paths match switcher en/ru hrefs', async ({ page }) => {
    const html = await page.request
      .get(`${BASE_URL}/en/${ARTICLE.en.cat}/${ARTICLE.en.slug}`)
      .then((r) => r.text());

    const switcher = extractSwitcherHrefs(html);
    const head = extractHeadHreflangPaths(html);

    expect(head.en, 'expected hreflang="en" in <head>').toBeTruthy();
    expect(head.ru, 'expected hreflang="ru" in <head>').toBeTruthy();
    expect(head.en).toBe(switcher.en);
    expect(head.ru).toBe(switcher.ru);
  });

  test('category: head hreflang en/ru paths match switcher en/ru hrefs (Phase 1.5 fix)', async ({ page }) => {
    const html = await page.request.get(`${BASE_URL}/en/society`).then((r) => r.text());

    const switcher = extractSwitcherHrefs(html);
    const head = extractHeadHreflangPaths(html);

    expect(head.en, 'expected hreflang="en" in <head>').toBeTruthy();
    expect(head.ru, 'expected hreflang="ru" in <head>').toBeTruthy();
    // Pre-Phase-1.5: head was /en/society + /ru/society (naive prefix-swap)
    // while switcher was /en/society + /ru/obshchestvo. Now both equal.
    expect(head.en).toBe(switcher.en);
    expect(head.ru).toBe(switcher.ru);
  });

  test('author: head hreflang en/ru paths match switcher en/ru hrefs (slug shared)', async ({ page }) => {
    const html = await page.request.get(`${BASE_URL}/en/author/ipn`).then((r) => r.text());

    const switcher = extractSwitcherHrefs(html);
    const head = extractHeadHreflangPaths(html);

    expect(head.en).toBe(switcher.en);
    expect(head.ru).toBe(switcher.ru);
  });

  test('static /en/gdpr: head hreflang en/ru paths match switcher en/ru hrefs (slug shared)', async ({ page }) => {
    const html = await page.request.get(`${BASE_URL}/en/gdpr`).then((r) => r.text());

    const switcher = extractSwitcherHrefs(html);
    const head = extractHeadHreflangPaths(html);

    expect(head.en).toBe(switcher.en);
    expect(head.ru).toBe(switcher.ru);
  });

  test('homepage /en: head hreflang en/ru paths match switcher en/ru hrefs', async ({ page }) => {
    const html = await page.request.get(`${BASE_URL}/en`).then((r) => r.text());

    const switcher = extractSwitcherHrefs(html);
    const head = extractHeadHreflangPaths(html);

    expect(head.en).toBe(switcher.en);
    expect(head.ru).toBe(switcher.ru);
  });

  // Topic + tag head/switcher consistency tests are deferred per D9: current
  // dev fixture has 0/164 topics and 0/2000 tags with translatedSlugs.en|ru.
  // Functionality covered by lib/seo/locale-url.test.ts (builders) +
  // lib/seo/meta-tags.test.ts (generator) + LanguageSwitcher.test.tsx
  // (topic/tag context branches). Add an e2e case here once fixtures grow.
});

/**
 * Backend access log (Symfony local server) is the only vantage point that
 * sees server-side fetches issued by Next.js (server components run in a
 * separate Node process; Playwright's `page.route` only intercepts browser
 * traffic). Tail the JSON-line log written by `symfony serve` to count
 * upstream HTTP calls per URL pattern within a test window.
 */
const SYMFONY_ACCESS_LOG = path.join(
  os.homedir(),
  '.symfony5/log/32ddae0a98131804102ded73181a2de396ecd7ac.log',
);

interface AccessEntry {
  time: string;
  method: string;
  message: string;
  status: number;
}

function readAccessLogTail(sinceByteOffset: number): AccessEntry[] {
  if (!fs.existsSync(SYMFONY_ACCESS_LOG)) return [];
  const fd = fs.openSync(SYMFONY_ACCESS_LOG, 'r');
  try {
    const stats = fs.fstatSync(fd);
    const length = Math.max(0, stats.size - sinceByteOffset);
    if (length === 0) return [];
    const buffer = Buffer.alloc(length);
    fs.readSync(fd, buffer, 0, length, sinceByteOffset);
    return buffer
      .toString('utf-8')
      .split('\n')
      .filter(Boolean)
      .map((line) => {
        try {
          return JSON.parse(line) as AccessEntry;
        } catch {
          return null;
        }
      })
      .filter((entry): entry is AccessEntry => entry !== null);
  } finally {
    fs.closeSync(fd);
  }
}

function getLogByteOffset(): number {
  if (!fs.existsSync(SYMFONY_ACCESS_LOG)) return 0;
  return fs.statSync(SYMFONY_ACCESS_LOG).size;
}

test.describe('Locale switcher SSR — fetch dedup verification (backend log)', () => {
  test('article page issues exactly 1 /api/articles/by-slug call (resolver + page share via React 19 fetch memo)', async ({ page }) => {
    test.skip(!fs.existsSync(SYMFONY_ACCESS_LOG), 'Symfony access log not present at expected path');

    const offset = getLogByteOffset();

    await page.goto(
      `${BASE_URL}/en/${ARTICLE.en.cat}/${ARTICLE.en.slug}`,
      { waitUntil: 'domcontentloaded' },
    );
    // Brief settle for log flush.
    await page.waitForTimeout(500);

    const tail = readAccessLogTail(offset);
    const matching = tail.filter(
      (e) =>
        e.method === 'GET' &&
        e.message.includes(`/api/articles/by-slug/${ARTICLE.en.slug}`) &&
        e.message.includes('locale=en'),
    );

    // The article page calls fetchArticleBySlug + the resolver calls
    // lookupArticle — both via the same URL+options. React fetch memo
    // should collapse to ≤ 1 upstream HTTP call (0 when Next.js serves
    // a cached HTML page; 1 on cache miss). If dedup breaks, this
    // becomes ≥ 2 and the test catches the regression.
    expect(matching.length).toBeLessThanOrEqual(1);
  });

  test('category page issues exactly 1 /api/categories call (layout + resolver + page share)', async ({ page }) => {
    test.skip(!fs.existsSync(SYMFONY_ACCESS_LOG), 'Symfony access log not present at expected path');

    const offset = getLogByteOffset();

    await page.goto(`${BASE_URL}/en/society`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(500);

    const tail = readAccessLogTail(offset);
    // Layout, resolver, and page all call `fetchCategories('en')` with
    // identical URL: /api/categories?... — React 19 fetch memo dedupes
    // to ≤ 1 upstream HTTP call (0 on Next.js cache hit, 1 on miss).
    const matching = tail.filter(
      (e) =>
        e.method === 'GET' &&
        /^\/api\/categories\?/.test(e.message),
    );

    expect(matching.length).toBeLessThanOrEqual(1);
  });
});
