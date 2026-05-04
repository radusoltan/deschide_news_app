---
adr: 33
title: CORS wildcard intentional on /api/embed
status: Accepted
date_proposed: 2026-05-03
date_decided: 2026-05-03
supersedes: "Audit 2026-05-03 finding 3.1 (P0 mis-classification)"
related: "Sprint 60 Phase C, EMBED_CAPABILITY_GUIDE.md"
author: "Radu (decided) + workflow-orchestrator (proposed)"
---

# ADR-033 — CORS wildcard intentional on `/api/embed`

## Status

Accepted (decided 2026-05-03).

## Context

The endpoint family `/api/embed/*` is a product-design public surface for third-party publishers (WordPress shortcode, iframe embed, JavaScript SDK), as documented in `apps/frontend/docs/features/EMBED_CAPABILITY_GUIDE.md`. The capability guide explicitly states:

- "✅ CORS-enabled for all domains" (line 19)
- A WordPress shortcode integration is provided (lines 504-547)
- The use case is "publishers we don't know a priori"

An allowlist approach would break the ecosystem because the consumer set is open-ended.

The 2026-05-03 comprehensive audit (`50_Audit/comprehensive-audit-2026-05-03.md` finding 3.1) classified the wildcard `Access-Control-Allow-Origin: *` on this endpoint as a **P0 vulnerability**. Post-audit discovery (Sprint 60 Phase C 3.0) found the audit was misframed — it did not consult the embed capability guide and applied a generic "wildcard CORS = bad" heuristic to a deliberately public endpoint.

## Decision

**Keep `Access-Control-Allow-Origin: *` on `/api/embed/*`.**

The decision is bounded by the following invariants, which together neutralize the audit's stated risk:

1. **GET-only.** All `/api/embed/*` routes are HTTP GET (with OPTIONS for preflight). No state-mutating verbs are exposed under this path prefix.
2. **No credentials.** `Access-Control-Allow-Credentials` is NOT set (NelmioCorsBundle default `false`, no override). The CORS spec forbids the combination `ACAO: *` + `ACAC: true` and browsers reject it. Cookies are stripped by the browser on cross-origin requests when credentials are not allowed.
3. **No sensitive fields.** Response payloads contain LiveText title, posts, slug, public author name, public sport-match scoreboard. No auth tokens, no PII, no admin-only fields.

Under these invariants, the wildcard adds zero attack surface beyond what is already obtainable via:
- The iframe embed (renders the same data visually)
- Server-side scrape of the unauthenticated endpoint
Both routes are available regardless of CORS configuration.

## Consequences

### Allowed
- Any third-party page may fetch `/api/embed/*` JSON via XHR/fetch from its own origin.
- The WordPress shortcode and JS SDK published via `embed.js` continue to work without per-publisher allowlist changes.

### Forbidden — enforced by ADR-033
- **No new write endpoints (POST/PUT/PATCH/DELETE) under `/api/embed/*`.** A future write route would inherit the CORS wildcard, which would expose state-mutating operations to any origin. Such a route MUST be relocated to an authenticated path (e.g., `/api/livetext-admin/...`) with the project's standard `%env(CORS_ALLOW_ORIGIN)%` allowlist.
- **No exposure of authenticated or user-specific fields.** Responses must remain identical for any caller. If user-scoped data ever needs to be embeddable, it must move to a JWT-signed embed flow (Sprint 61+ candidate).
- **No credentialed CORS.** `Access-Control-Allow-Credentials: true` must never be set on `/api/embed/*`.

These constraints are enforced by:
1. An inline comment in `apps/backend/config/packages/nelmio_cors.yaml` referencing this ADR.
2. A regression test (`apps/backend/tests/Functional/Cors/EmbedCorsTest.php`) that fails CI if any `/api/embed/*` route is ever registered with a non-GET-or-OPTIONS method, or if `ACAC: true` ever appears on the response.

### Implementation notes (Sprint 60 Phase C empirical findings)

Two non-obvious NelmioCorsBundle behaviours surfaced during implementation and are codified in the yaml comments:

1. **Path order matters — first-match-wins.** `Nelmio\CorsBundle\Options\ConfigProvider::getOptions` iterates `paths` in YAML order and returns the first regex match. Before Sprint 60 Phase C, the config listed `^/api` before `^/api/embed`, which meant the wildcard rule was dead code: every `/api/embed/*` request matched the more general `^/api` block first, picked up the project's standard `CORS_ALLOW_ORIGIN` regex, and never received the wildcard treatment. The capability guide's "✅ CORS-enabled for all domains" claim was actually false in production. Sprint 60 Phase C reordered the yaml so `^/api/embed` comes first.
2. **`allow_origin: ['*']` does not produce a literal `*` in the response.** `Nelmio\CorsBundle\EventListener\CorsListener::onKernelResponse` (line 160) and `getPreflightResponse` (line 233) **always echo the request `Origin`** into the response. To get the literal wildcard mandated by this ADR — required for cache-friendly responses without per-origin `Vary: Origin` fragmentation — `forced_allow_origin_value: '*'` must be set on the path. The yaml keeps both `allow_origin: ['*']` (for documentation parity with this ADR's text) and `forced_allow_origin_value: '*'` (the setting that actually drives the response header).

## Reverse-decision triggers

This ADR must be revisited (and likely superseded) if any of the following becomes true:

- **(a) Embed needs auth.** A use case appears where embed responses must be scoped to an authenticated user.
- **(b) Embed exposes user-specific data.** Personalization, watch-history, account-bound content are added to embed responses.
- **(c) Embed mutates state.** Reactions, comments, votes, or any write operation is desired under the embed surface.

If any of (a)/(b)/(c) ships, the wildcard MUST be replaced with a signed-JWT-on-embed-URL pattern: the embed URL carries a JWT identifying the publisher; CORS dynamically derives `Access-Control-Allow-Origin` from the JWT issuer claim, falling back to deny if the JWT is invalid.

## Audit reframing

This ADR closes a documentation loop on the 2026-05-03 audit. **Lesson encoded in the audit's Post-audit revisions section:** P0 security findings on public-by-design endpoints require product-intent verification (read the capability guide, controller comments, related ADRs) **before** classification. A `['*']` allow_origin is a code smell only when the endpoint family does not have an explicit "public for any consumer" design intent.

## References

- `apps/backend/config/packages/nelmio_cors.yaml` (line 21-26 — `^/api/embed` block)
- `apps/backend/src/Controller/EmbedController.php` (4 GET routes only)
- `apps/frontend/docs/features/EMBED_CAPABILITY_GUIDE.md` (full product design)
- `apps/backend/tests/Functional/Cors/EmbedCorsTest.php` (regression guard)
- `50_Audit/comprehensive-audit-2026-05-03.md` (finding 3.1 + Post-audit revisions)
- CORS spec: https://fetch.spec.whatwg.org/#http-cors-protocol
