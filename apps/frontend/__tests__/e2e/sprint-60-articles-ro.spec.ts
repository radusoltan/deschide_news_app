/**
 * Sprint 60 — RO article slug routing (24-probe matrix, T60.8.2)
 *
 * Empirically validates the frontend article slug-resolution pipeline against
 * the corpus that existed at branch creation time (2026-05-08, develop @ 4065e9e).
 *
 * Probe layout (24 logical probes, expanded to 28 test bodies after Option β):
 *   A      — 8 canonical RO slugs (real published articles)
 *   B-FE   — 6 legacy/redirected URLs hitting the frontend article page
 *   B-API  — 6 backend redirect-lookup endpoint contract probes (chromium-only)
 *   C      — 4 diacritics URL-encoding edge cases (UTF-8 raw vs percent-encoded)
 *   D      — 4 missing-slug 404 boundary cases
 *   E      — 2 edge cases (very long slug, empty trailing slug)
 *
 * Hybrid assertion strategy (T60.8.2 Option α-revised, 2026-05-08):
 *   Next.js dev mode renders `notFound()` UI with HTTP 200 (server-component
 *   soft-404), while production builds emit a proper HTTP 404. The probes
 *   for B-FE / D / E1 therefore accept BOTH outcomes via:
 *
 *     status === 404 OR (status === 200 AND body has SOFT_404_TITLE_MARKER)
 *
 *   In production CI the 404 path matches and the body marker is irrelevant.
 *   In local dev, the body marker provides the secondary signal so probes do
 *   not false-fail on dev-mode soft-404 behavior.
 *
 *   Why title-marker, not data-testid: data-testid="not-found" was added to
 *   not-found.tsx in 4c11dcf, but Next.js 16 + Turbopack 'use client' SSR
 *   pipeline materializes that attribute only post-hydration (verified
 *   empirically — attribute appears in compiled client chunk but not in SSR
 *   HTML). Probes use Playwright APIRequestContext (HTTP-only, no JS) so they
 *   need an SSR-stable marker. The <title>Article Not Found</title> emitted
 *   by page.tsx:166-170 generateMetadata() early-return is hardcoded EN,
 *   present in plain SSR HTML <head>, and stable across all soft-404
 *   scenarios (D, E1, B-FE) — universal locale-independent marker.
 *
 *   Regression alarm: a top-of-spec invariant test asserts that
 *   /ro/politica/<missing> SSR HTML still contains SOFT_404_TITLE_MARKER.
 *   If page.tsx generateMetadata is refactored to localize the not-found
 *   title, this invariant fails first, with a clear message pointing here.
 *
 * Legacy redirect mechanism (Option β rationale):
 *   The backend ships a UrlRedirect entity, UrlRedirectRepository, and
 *   /api/redirects/lookup endpoint with 33 active rows seeded by category-
 *   change events. The mechanism is NOT yet wired into the frontend
 *   slug-resolution path (proxy.ts does not consult it; SlugLookupController
 *   does not short-circuit on legacy slugs). Consequently the FE-side B
 *   probes can only assert "must NOT serve canonical content at old path"
 *   via the hybrid 404-or-soft-404 contract — they cannot assert a 301 with
 *   a Location header pointing to the new category. Once wiring lands
 *   (backlog: T60.X-WIRE-LEGACY-REDIRECTS) those probes can be flipped to
 *   assert the 301 contract directly. The B-API probe set independently
 *   exercises the backend lookup endpoint so the contract is regression-
 *   guarded regardless of FE wiring state.
 *
 * Browser environment caveat (WSL2 specific):
 *   webkit and Mobile Safari may fail locally on WSL2 due to missing system
 *   libraries (libwebpdemux2, libharfbuzz-icu0, libenchant-2, libhyphen,
 *   libwayland-server, libmanette-0.2, libx264, libflite_*). CI runs
 *   `pnpm exec playwright install --with-deps` and passes consistently. To
 *   enable locally: `sudo npx playwright install-deps webkit`.
 *
 * Sentry hardening (T60.8.2) sanity:
 *   The probes do not directly assert Sentry capture (slug-lookup.ts runs
 *   server-side in RSC; Sentry calls hit the SDK before reaching the browser
 *   console). They DO exercise the catch-block surface — if the hardening
 *   regresses, future probe failures will lack the structured breadcrumb,
 *   which is itself the regression signal we want to surface in ops.
 */

import { test, expect, type APIRequestContext } from '@playwright/test';

// TODO: extract to BACKEND_URL env var when CI integration lands (T60.X-CI-PLAYWRIGHT-BACKEND-URL)
const BACKEND_URL = 'http://127.0.0.1:8081';

// Soft-404 SSR marker. See header docblock (Hybrid assertion strategy) for
// why this is title-based rather than data-testid-based. Single hardcoded EN
// literal emitted by page.tsx:166-170 generateMetadata() on missing-article
// paths, stable across all locales and probe scenarios in plain SSR HTML.
const SOFT_404_TITLE_MARKER = '<title>Article Not Found</title>';

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

interface FetchResult {
  status: number;
  location: string | null;
  hasNotFoundMarker: boolean;
}

async function fetchUrl(
  request: APIRequestContext,
  pathname: string,
): Promise<FetchResult> {
  const response = await request.get(pathname, {
    maxRedirects: 0,
    failOnStatusCode: false,
  });
  const status = response.status();
  const location = response.headers()['location'] ?? null;
  let hasNotFoundMarker = false;
  if (status === 200) {
    try {
      const body = await response.text();
      hasNotFoundMarker = body.includes(SOFT_404_TITLE_MARKER);
    } catch {
      hasNotFoundMarker = false;
    }
  }
  return { status, location, hasNotFoundMarker };
}

/**
 * Hybrid soft-404 / hard-404 assertion.
 * Accepts: 404 (production), or 200+marker (dev), or 3xx redirect (future-proof).
 * Rejects: 200 without marker (canonical content served — leak), or 5xx (crash).
 */
function expectSoftOrHard404(
  result: FetchResult,
  pathname: string,
  probeId: string,
): void {
  const { status, hasNotFoundMarker } = result;
  const isHard404 = status === 404;
  const isSoftDevMode = status === 200 && hasNotFoundMarker;
  const isAcceptableRedirect = [301, 302, 307, 308, 410].includes(status);
  const ok = isHard404 || isSoftDevMode || isAcceptableRedirect;

  expect(
    ok,
    `${probeId}: ${pathname} must be 404 (prod) OR 200+title-marker (dev soft-404) OR 3xx/410, got status=${status} hasTitleMarker=${hasNotFoundMarker}`,
  ).toBe(true);
}

// ---------------------------------------------------------------------------
// Spec body
// ---------------------------------------------------------------------------

test.describe('Sprint 60 — Soft-404 marker invariant (T60.8.2)', () => {
  test.beforeEach(async ({}, testInfo) => {
    test.skip(
      testInfo.project.name !== 'chromium',
      'Marker invariant is browser-agnostic; chromium-only to avoid project multiplication',
    );
  });

  test('SSR <title> emits hardcoded EN literal on missing-article paths', async ({ request }) => {
    const response = await request.get(`/ro/politica/marker-invariant-${Date.now()}`);
    const body = await response.text();
    expect(
      body,
      [
        `Soft-404 marker invariant FAILED: SSR HTML does not contain ${SOFT_404_TITLE_MARKER}.`,
        `If page.tsx:166-170 generateMetadata() was refactored to localize the`,
        `not-found title, update SOFT_404_TITLE_MARKER + the docblock in this spec.`,
      ].join(' '),
    ).toContain(SOFT_404_TITLE_MARKER);
  });
});

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
  // Probe Set B-FE — legacy/redirected URLs hitting the frontend (6 probes)
  // Hybrid assertion: legacy URL must produce 404 (prod) or 200+not-found-marker
  // (dev soft-404). Drop the 301-with-Location assertion — wiring not in place
  // currently (see header docblock). Once T60.X-WIRE-LEGACY-REDIRECTS lands,
  // flip these assertions to expect 301 + Location → /ro/<newCategory>/<slug>.
  // -------------------------------------------------------------------------
  test.describe('Probe Set B-FE — legacy URLs must not serve canonical content (hybrid)', () => {
    for (const [index, row] of LEGACY_REDIRECTS.entries()) {
      const probeId = `B${index + 1}`;
      test(`${probeId}: /ro/${row.oldCategory}/${row.slug.slice(0, 40)}… does not serve canonical content at old path`, async ({
        request,
      }) => {
        const oldPath = `/ro/${row.oldCategory}/${row.slug}`;
        const result = await fetchUrl(request, oldPath);
        expectSoftOrHard404(result, oldPath, probeId);
      });
    }
  });

  // -------------------------------------------------------------------------
  // Probe Set B-API — backend redirect-lookup endpoint contract (6 probes)
  // Independent of frontend wiring. Asserts that the backend mechanism is
  // populated and returns the correct old→new mapping for each row in
  // LEGACY_REDIRECTS. Browser-agnostic; chromium-only to avoid 6× project
  // multiplication for what is effectively an HTTP API contract test.
  // -------------------------------------------------------------------------
  test.describe('Probe Set B-API — backend redirect-lookup contract', () => {
    test.beforeEach(async ({}, testInfo) => {
      test.skip(
        testInfo.project.name !== 'chromium',
        'B-API probes are browser-agnostic; chromium-only to avoid project multiplication',
      );
    });

    for (const [index, row] of LEGACY_REDIRECTS.entries()) {
      const probeId = `B-API${index + 1}`;
      test(`${probeId}: /api/redirects/lookup returns 301 mapping /ro/${row.oldCategory}/… → /ro/${row.newCategory}/…`, async ({
        request,
      }) => {
        const oldPath = `/${row.oldCategory}/${row.slug}`;
        const expectedNewPath = `/${row.newCategory}/${row.slug}`;
        const lookupUrl = `${BACKEND_URL}/api/redirects/lookup?url=${encodeURIComponent(oldPath)}`;

        const response = await request.get(lookupUrl, { failOnStatusCode: false });
        expect(response.status(), `${probeId}: lookup endpoint must return 200 OK at ${lookupUrl}`).toBe(200);

        const json = await response.json();
        expect(json.success, `${probeId}: lookup response must be success=true`).toBe(true);
        expect(json.redirect, `${probeId}: lookup response must include redirect object`).toBeDefined();
        expect(json.redirect.old_url, `${probeId}: redirect.old_url must echo input`).toBe(oldPath);
        expect(json.redirect.new_url, `${probeId}: redirect.new_url must point to ${expectedNewPath}`).toBe(expectedNewPath);
        expect(json.redirect.status_code, `${probeId}: redirect.status_code must be 301`).toBe(301);
        expect(json.redirect.locale, `${probeId}: redirect.locale must be ro`).toBe('ro');
        expect(json.redirect.type, `${probeId}: redirect.type must be article`).toBe('article');
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

        const utf8Result = await fetchUrl(request, utf8Path);
        const percentResult = await fetchUrl(request, percentPath);

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
  // Hybrid assertion: must be 404 (prod) or 200+not-found-marker (dev).
  // -------------------------------------------------------------------------
  test.describe('Probe Set D — missing-slug 404 boundary precision', () => {
    for (const [index, slug] of SYSTEM_PATH_NEGATIVES.entries()) {
      const probeId = `D${index + 1}`;
      test(`${probeId}: /ro/politica/${slug.slice(0, 30)} returns 404 (prod) or 200+marker (dev), never 5xx or canonical 200`, async ({
        request,
      }) => {
        const path = `/ro/politica/${slug}`;
        const result = await fetchUrl(request, path);
        expectSoftOrHard404(result, path, probeId);
      });
    }
  });

  // -------------------------------------------------------------------------
  // Probe Set E — edge cases (2 probes)
  // -------------------------------------------------------------------------
  test.describe('Probe Set E — edge cases', () => {
    test('E1: very long slug (280+ chars) returns 4xx (prod) or 200+marker (dev), never 5xx', async ({
      request,
    }) => {
      const path = `/ro/politica/${VERY_LONG_SLUG}`;
      const result = await fetchUrl(request, path);
      expect(result.status, `E1: very long slug must not 5xx (got ${result.status})`).toBeLessThan(500);
      expectSoftOrHard404(result, path, 'E1');
    });

    test('E2: empty trailing slug /ro/politica/ resolves to category page (200) without crashing', async ({
      request,
    }) => {
      const result = await fetchUrl(request, '/ro/politica/');
      // Acceptable shapes: 200 (category page renders) or 308 (trailing-slash normalize).
      // Crash signals (5xx) or unexpected 4xx flag a regression in the route matcher.
      expect(result.status, `E2: empty trailing slug must not 5xx (got ${result.status})`).toBeLessThan(500);
      expect(
        [200, 301, 308].includes(result.status),
        `E2: status must be 200 / 301 / 308 at /ro/politica/, got ${result.status}`,
      ).toBe(true);
    });
  });
});
