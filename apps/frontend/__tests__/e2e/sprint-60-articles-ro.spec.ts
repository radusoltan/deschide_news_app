/**
 * Sprint 60 — RO article slug routing (24-probe matrix, T60.8.2)
 *
 * Empirically validates the frontend article slug-resolution pipeline against
 * the corpus that existed at branch creation time (2026-05-08, develop @ 4065e9e).
 *
 * Probe layout (24 probes total):
 *   A — 8 canonical RO slugs (real published articles)
 *   B — 6 legacy/redirected URLs (entries from url_redirects table)
 *   C — 4 diacritics URL-encoding edge cases (UTF-8 raw vs percent-encoded)
 *   D — 4 missing-slug 404 boundary cases
 *   E — 2 edge cases (very long slug, empty trailing slug)
 *
 * Rationale for fixture-as-corpus (not Playwright fixture infra):
 *   The current frontend test setup has no E2E fixture loader for articles
 *   (verified Phase 1 discovery). Reusing real published articles keeps the
 *   probe surface deterministic AS LONG AS the eight referenced articles stay
 *   published. If app:dev:reset purges them, this spec must be updated to
 *   match the new corpus — flake guarded by an explicit "article exists" probe
 *   in Set A so a regression surfaces with a clear cause.
 *
 * Sentry hardening (T60.8.2) sanity:
 *   The probes do not directly assert Sentry capture (slug-lookup.ts runs
 *   server-side in RSC; Sentry calls hit the SDK before reaching the browser
 *   console). They DO exercise the catch-block surface — if the hardening
 *   regresses, future probe failures will lack the structured breadcrumb,
 *   which is itself the regression signal we want to surface in ops.
 */

import { test, expect, type APIRequestContext } from '@playwright/test';

// ---------------------------------------------------------------------------
// Fixture corpus — captured 2026-05-08 from develop @ 4065e9e against local DB
// ---------------------------------------------------------------------------

const CANONICAL_ARTICLES: ReadonlyArray<{
  id: number;
  category: string;
  slug: string;
  publishedLocales: ReadonlyArray<string>;
}> = [
  { id: 1, category: 'politica', slug: 'moldova-deschide-cluster-1-negocieri-ue-draft', publishedLocales: ['ro', 'ru'] },
  { id: 4, category: 'economie', slug: 'energocom-obligata-sa-cumpere-energie-de-pe-pietele-organizate-ministerul-energiei', publishedLocales: ['ro', 'ru', 'en'] },
  { id: 5, category: 'politica', slug: 'tiraspolul-cere-chisinaului-sa-renunte-la-noile-taxe-pentru-intreprinderile-de-pe-malul-stang', publishedLocales: ['ro', 'ru', 'en'] },
  { id: 6, category: 'externe', slug: 'regele-charles-in-congresul-sua-apel-la-unitate-si-sprijin-de-neclintit-pentru-ucraina', publishedLocales: ['ro', 'ru'] },
  { id: 8, category: 'societate', slug: 'dosarul-focurilor-de-arma-de-la-ip-centru-un-politist-trimis-in-judecata', publishedLocales: ['ro', 'ru'] },
  { id: 10, category: 'economie', slug: 'alexandru-munteanu-infrastructura-si-investitiile-sunt-piloni-ai-securitatii-regionale', publishedLocales: ['ro', 'ru'] },
  { id: 11, category: 'externe', slug: 'sumi-si-odesa-tinte-ale-atacurilor-rusesti-un-mort-raniti-si-infrastructura-civila-distrusa', publishedLocales: ['ro', 'en'] },
  { id: 13, category: 'societate', slug: 'semafoare-care-trec-pe-rosu-la-depasirea-vitezei-instalate-la-brasov', publishedLocales: ['ro'] },
];

interface LegacyRedirect {
  oldCategory: string;
  newCategory: string;
  slug: string;
  entityId: number;
}

const LEGACY_REDIRECTS: ReadonlyArray<LegacyRedirect> = [
  { oldCategory: 'economie',  newCategory: 'politica',  slug: 'tiraspolul-cere-chisinaului-sa-renunte-la-noile-taxe-pentru-intreprinderile-de-pe-malul-stang', entityId: 5 },
  { oldCategory: 'politica',  newCategory: 'societate', slug: 'semafoare-care-trec-pe-rosu-la-depasirea-vitezei-instalate-la-brasov', entityId: 13 },
  { oldCategory: 'economie',  newCategory: 'externe',   slug: 'sumi-si-odesa-tinte-ale-atacurilor-rusesti-un-mort-raniti-si-infrastructura-civila-distrusa', entityId: 11 },
  { oldCategory: 'societate', newCategory: 'politica',  slug: 'eliberarea-angajatilor-sis-din-captivitate-in-rusia-a-presupus-decizii-complicate-vicepremier', entityId: 0 },
  { oldCategory: 'economie',  newCategory: 'societate', slug: 'alexandru-machidon-numit-oficial-procuror-general', entityId: 0 },
  { oldCategory: 'societate', newCategory: 'politica',  slug: 'vicepremierul-chiveri-despre-solicitarea-tiraspolului-modificarile-fiscale-sunt-pentru-oameni', entityId: 0 },
];

// Probes for percent-encoding parser coherence. Both forms must yield the
// same final HTTP status — ASCII normalization or 404, but never 500.
const DIACRITIC_EDGE_CASES: ReadonlyArray<{
  label: string;
  utf8: string;
  percent: string;
}> = [
  {
    label: 'ș (single Romanian comma-below)',
    utf8: 'știri-importante-test-fictional',
    percent: '%C8%99tiri-importante-test-fictional',
  },
  {
    label: 'ț + ă (mixed comma-below + breve)',
    utf8: 'țară-test-fictional',
    percent: '%C8%9Bar%C4%83-test-fictional',
  },
];

const SYSTEM_PATH_NEGATIVES: ReadonlyArray<string> = [
  'this-slug-definitely-does-not-exist-12345',
  'another-non-existent-slug-67890',
  '.well-known',
  'sitemap.xml',
];

const VERY_LONG_SLUG = 'x'.repeat(280) + '-end';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

async function fetchHead(
  request: APIRequestContext,
  pathname: string,
): Promise<{ status: number; location: string | null }> {
  const response = await request.get(pathname, {
    maxRedirects: 0,
    failOnStatusCode: false,
  });
  return {
    status: response.status(),
    location: response.headers()['location'] ?? null,
  };
}

// ---------------------------------------------------------------------------
// Spec body
// ---------------------------------------------------------------------------

test.describe('Sprint 60 — RO article slug routing (24-probe matrix, T60.8.2)', () => {
  test.describe.configure({ mode: 'parallel' });

  // -------------------------------------------------------------------------
  // Probe Set A — canonical RO slugs (8 probes)
  // -------------------------------------------------------------------------
  test.describe('Probe Set A — canonical RO slugs', () => {
    for (const [index, article] of CANONICAL_ARTICLES.entries()) {
      const probeId = `A${index + 1}`;
      test(`${probeId}: /ro/${article.category}/${article.slug.slice(0, 40)}… renders article ${article.id}`, async ({
        page,
      }) => {
        const url = `/ro/${article.category}/${article.slug}`;
        const response = await page.goto(url, { waitUntil: 'domcontentloaded' });
        expect(response, `${probeId}: navigation must produce a response`).not.toBeNull();
        expect(response!.status(), `${probeId}: expected 200 OK at ${url}`).toBe(200);

        // Article body should render <h1> with non-empty text within timeout.
        await expect(page.locator('h1').first()).toBeVisible({ timeout: 10_000 });
        const headingText = (await page.locator('h1').first().textContent())?.trim() ?? '';
        expect(headingText.length, `${probeId}: <h1> must have non-empty text`).toBeGreaterThan(0);
      });
    }
  });

  // -------------------------------------------------------------------------
  // Probe Set B — legacy/redirected URLs (6 probes)
  // The empirical contract: legacy URL MUST NOT render successfully at the old
  // location. It is acceptable for the response to be 301 (redirect to canonical)
  // OR 404 (legacy lookup not wired into proxy/route layer). The probe captures
  // which one fires per row, so a regression that starts serving 200 at the old
  // category surfaces immediately.
  // -------------------------------------------------------------------------
  test.describe('Probe Set B — legacy redirect URLs (must NOT serve 200 at old path)', () => {
    for (const [index, row] of LEGACY_REDIRECTS.entries()) {
      const probeId = `B${index + 1}`;
      test(`${probeId}: /ro/${row.oldCategory}/${row.slug.slice(0, 40)}… is non-200 (301 to /ro/${row.newCategory}/… or 404)`, async ({
        request,
      }) => {
        const oldPath = `/ro/${row.oldCategory}/${row.slug}`;
        const { status, location } = await fetchHead(request, oldPath);

        expect(
          [301, 302, 307, 308, 404, 410].includes(status),
          `${probeId}: status must be redirect or not-found at ${oldPath}, got ${status}`,
        ).toBe(true);
        expect(status, `${probeId}: legacy URL must NOT serve 200 at old path`).not.toBe(200);

        if ([301, 302, 307, 308].includes(status)) {
          expect(location, `${probeId}: redirect must include Location header`).not.toBeNull();
          expect(location, `${probeId}: redirect target must reference new category /ro/${row.newCategory}/`).toContain(
            `/ro/${row.newCategory}/`,
          );
        }
      });
    }
  });

  // -------------------------------------------------------------------------
  // Probe Set C — diacritics URL-encoding edge cases (4 probes)
  // Every probe asserts that BOTH encodings (raw UTF-8 and percent-encoded)
  // produce the SAME HTTP status. If either form crashes the server (5xx) or
  // serves a divergent status, a regression has been introduced into the
  // URL parsing layer.
  // -------------------------------------------------------------------------
  test.describe('Probe Set C — diacritics URL-encoding parser coherence', () => {
    for (const [index, ex] of DIACRITIC_EDGE_CASES.entries()) {
      const baseProbe = `C${index * 2 + 1}/C${index * 2 + 2}`;
      test(`${baseProbe} (${ex.label}): UTF-8 raw and percent-encoded forms produce identical status, never 5xx`, async ({
        request,
      }) => {
        const utf8Path = `/ro/politica/${ex.utf8}`;
        const percentPath = `/ro/politica/${ex.percent}`;

        const utf8Result = await fetchHead(request, utf8Path);
        const percentResult = await fetchHead(request, percentPath);

        expect(utf8Result.status, `${baseProbe}: UTF-8 form must not be 5xx (got ${utf8Result.status})`).toBeLessThan(500);
        expect(percentResult.status, `${baseProbe}: percent-encoded form must not be 5xx (got ${percentResult.status})`).toBeLessThan(500);
        expect(
          utf8Result.status,
          `${baseProbe}: encoding parity — UTF-8 status (${utf8Result.status}) must equal percent-encoded status (${percentResult.status})`,
        ).toBe(percentResult.status);
      });
    }
  });

  // -------------------------------------------------------------------------
  // Probe Set D — 404 boundary precision (4 probes)
  // -------------------------------------------------------------------------
  test.describe('Probe Set D — missing-slug 404 boundary precision', () => {
    for (const [index, slug] of SYSTEM_PATH_NEGATIVES.entries()) {
      const probeId = `D${index + 1}`;
      test(`${probeId}: /ro/politica/${slug.slice(0, 30)} returns precise 404 (not 500, not soft-200)`, async ({
        request,
      }) => {
        const path = `/ro/politica/${slug}`;
        const { status } = await fetchHead(request, path);

        expect(status, `${probeId}: status must be 404 at ${path}, got ${status}`).toBe(404);
      });
    }
  });

  // -------------------------------------------------------------------------
  // Probe Set E — edge cases (2 probes)
  // -------------------------------------------------------------------------
  test.describe('Probe Set E — edge cases', () => {
    test('E1: very long slug (280+ chars) returns 4xx, not 5xx', async ({ request }) => {
      const path = `/ro/politica/${VERY_LONG_SLUG}`;
      const { status } = await fetchHead(request, path);

      expect(status, `E1: very long slug must not 5xx (got ${status})`).toBeLessThan(500);
      expect(status, `E1: very long slug must be 4xx (404 or 414, got ${status})`).toBeGreaterThanOrEqual(400);
    });

    test('E2: empty trailing slug /ro/politica/ resolves to category page (200) without crashing', async ({
      request,
    }) => {
      const { status } = await fetchHead(request, '/ro/politica/');
      // Acceptable shapes: 200 (category page renders) or 308 (trailing-slash normalize).
      // Crash signals (5xx) or unexpected 4xx flag a regression in the route matcher.
      expect(status, `E2: empty trailing slug must not 5xx (got ${status})`).toBeLessThan(500);
      expect(
        [200, 301, 308].includes(status),
        `E2: status must be 200 / 301 / 308 at /ro/politica/, got ${status}`,
      ).toBe(true);
    });
  });
});
