# ADR-027 — Scope the ArticleProvider per-locale publishing gate to public reads

- **Status**: Accepted (v1.4.1 — hotfix)
- **Date**: 2026-04-24
- **Decision owner**: Backend
- **Supersedes**: —
- **Related findings**: REPORT.md §4 (`/tmp/cc-img-investigation/REPORT.md`), finding #1 (blocker)
- **Hotfix branch**: `hotfix/image-attach-locale-gate`

---

## Context

`App\State\ArticleProvider::provide()` is registered as the API Platform state
provider for the `Article` resource. API Platform calls it from at least two
different code paths:

1. **Public single-item read.** A request like `GET /api/articles/52` with
   `Accept-Language: en` is handled by `ApiPlatform\Symfony\EventListener\ReadListener`,
   which calls
   `$provider->provide($operation, $uriVariables, ['request' => ..., 'uri_variables' => ..., 'resource_class' => ...])`.
2. **IRI denormalization during writes.** When another resource carries an
   Article IRI in its payload — e.g. `POST /api/article_images` with
   `{"article": "/api/articles/52", "image": "/api/images/7"}` —
   `ApiPlatform\Serializer\AbstractItemNormalizer::denormalize()` converts the
   string IRI to an entity by calling
   `$iriConverter->getResourceFromIri($data, $context + ['fetch_data' => true])`.
   The IriConverter then invokes the exact same Article provider with the Get
   operation on Article and the augmented `$context`.

Before the hotfix, the provider applied a per-locale publishing gate to every
single-item result:

```php
if ($result instanceof Article) {
    if (!$result->isPublishedInLocale($locale)) {
        return null;
    }
    ...
}
```

The gate is the correct behaviour for path (1): an article published only in
`ro` should 404 for a reader whose `Accept-Language` resolves to `en`.

The gate is wrong for path (2). Returning `null` there causes `IriConverter`
to throw `ItemNotFoundException`, which the serializer re-raises as
`Symfony\Component\Serializer\Exception\UnexpectedValueException: "Item not
found for /api/articles/52"` — and the write ends as a 400/500. Every admin
workflow that attaches media, tags, or related entities to an article
not-yet-published in the editor's browser locale hit this, which in practice
meant **every** editorial workflow run in any non-RO browser profile,
including CI-driven Playwright sessions whose default locale is `en-US`.

### Accept-Language propagation (open question)

The mechanism by which the browser's `Accept-Language` reaches the backend
through Next.js server-side fetches is not fully isolated. The repo's
`lib/api/article-images.ts:attachImageToArticle` does not set the header
explicitly, and `proxy.ts` excludes `/api/**` from its matcher and does not
forward headers. `@sentry/nextjs` auto-instrumentation is the most plausible
candidate (it wraps undici fetch), but tcpdump / console.log verification was
skipped in favour of HTTP-level reproduction with curl, which deterministically
reproduces the bug (REPORT.md §6 reproduction matrix). The fix is correct
independent of how propagation happens. This is flagged in Open Questions.

## Decision

Apply the per-locale publishing gate **only** when the provider is serving a
public single-item read. The discriminator is the presence of
`$context['fetch_data']`: set by `AbstractItemNormalizer` whenever IriConverter
is invoked during denormalization, absent from the context built by
`ReadListener` for direct HTTP reads.

```php
use ApiPlatform\Metadata\Get;

if ($result instanceof Article) {
    $isPublicRead = $operation instanceof Get
        && $operation->getClass() === Article::class
        && !isset($context['fetch_data']);

    if ($isPublicRead && !$result->isPublishedInLocale($locale)) {
        return null;
    }
    $this->populateTranslatedSlugs([$result]);
}
```

### Options considered

| # | Option | Verdict |
|---|---|---|
| 1 | Discriminate on `$context['fetch_data']` (chosen). | **Hotfix**. One-line change inside the existing provider; minimum diff, minimum blast radius. |
| 2 | Split the admin surface: new `/api/admin/articles/{id}` resource with its own provider that lacks the gate. Admin UI switches to the new path. | **Long-term (S+1 or later).** Cleaner separation of public vs admin concerns, but a larger diff that rewires the admin UI and adds a second API surface. Not appropriate for a hotfix on a cut tag. |
| 3 | Register a second ProviderInterface specifically for IriConverter resolution (via service config). | Rejected — API Platform does not expose a stable seam for a dedicated IRI provider; would rely on undocumented internals. |

Option 1 is a conscious compromise: it keeps one method serving two masters
and therefore depends on the context-flag contract with AbstractItemNormalizer
remaining stable. The compromise is documented here so a future provider author
can see the invariant.

## Consequences

### Positive

- Admin image-attach works in every browser locale. Unblocks editorial flow
  prerequisite for the v1.4.1 hotfix and FRA1 deploy.
- Public `GET /api/articles/{id}` still 404s for the correct locale.
- Four regression tests (1 unit file + 1 functional file) pin the contract.

### Negative / Risks

- **Future providers for Article or any related entity MUST NOT reimplement the
  gate in `provide()` without an explicit operation-type + `fetch_data` check.**
  Violating this invariant silently reintroduces the bug.
- Upgrading API Platform could in principle change how `fetch_data` flows
  through the denormalization chain. The contract has been stable since
  API Platform 3.0 and is exercised by the functional test; a major upgrade
  must re-run `ArticleProviderLocaleTest` as part of the regression sweep.

### Test coverage

- `apps/backend/tests/Unit/State/ArticleProviderTest.php` — four unit tests
  exercising the gate-scope matrix (public read published / public read not
  published / IRI lookup with non-matching locale / IRI lookup on draft article).
- `apps/backend/tests/Functional/Api/ArticleProviderLocaleTest.php` — four HTTP
  scenarios mirroring the hotfix matrix.

## Open questions

1. **Accept-Language propagation mechanism** (REPORT.md §7.5). The exact code
   path that forwards the browser's `Accept-Language` from a Next.js server
   action to the Symfony backend is unconfirmed. Fix is correct independent of
   this, but diagnosing the propagation makes the long-term admin split
   (Option 2) more informed. Candidate: `@sentry/nextjs` auto-instrumented
   fetch. To be investigated in S+1 if Option 2 is prioritised.

2. **Admin surface split (Option 2).** Long-term posture should probably move
   admin reads to a dedicated resource with its own provider so the gate is
   definitionally absent on admin paths. Revisit when the editorial flow next
   receives substantial work.

3. **Other providers susceptible to the same bug.** An audit of every
   `*Provider.php` that applies any locale/visibility gate is warranted. Likely
   candidates: `CategoryProvider`, `AuthorProvider`, any provider that scopes
   by `isPublished*`. Deferred to S+1.
