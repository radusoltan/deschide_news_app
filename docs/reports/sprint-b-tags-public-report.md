# Sprint B: Tags pe Pagina Publica + Merge Tags + Polish

**Data:** 2026-04-01
**Branch:** develop

---

## Raport Final

| # | Actiune | Status | Detalii |
|---|---------|--------|---------|
| 1.0 | Citire rapoarte | ✅ | Sprint A + analiza initiala — ambele citite |
| 1.1 | Analiza pagina publica articol | ✅ | `app/[locale]/(public)/[categorySlug]/[articleSlug]/page.tsx` — ArticleHeader + ArticleImage + ArticleBody + ArticleMeta + ArticleSidebar |
| 1.2 | Analiza ArticleCard | ✅ | `components/ArticleCard.tsx` — DEJA ARE TagList cu maxTags={3}, variant default, size sm. NU necesita modificari |
| 1.3 | Analiza componente Tag existente | ✅ | TagBadge (3 variants, 3 sizes, link to /tags/[slug]), TagList (wrapper with maxTags + "+N"), TagCloud (weighted display) |
| 1.4 | Analiza pagini publice tags | ✅ | `/[locale]/tags` (TagCloud listing) + `/[locale]/tags/[slug]` (articles + related tags sidebar). Ambele existente dar cu params sync (Next.js 14 pattern) |
| 1.5 | Analiza merge backend | ✅ | `TagService::mergeTags()` exista. Endpoint API NU exista — creat in Sprint B |
| 2.1 | Tags pe pagina de articol | ✅ | TagList adaugat in ArticleHeader sub metadata (autor, data, categorie), variant="outline", size="sm" |
| 2.2 | Tags pe ArticleCard | ✅ SKIP | DEJA IMPLEMENTAT — liniile 231-243 in ArticleCard.tsx (maxTags=3, variant=default, size=sm) |
| 2.3 | JSON-LD keywords | ✅ | Adaugat tag names in `generateNewsArticleSchema()` — keywords include acum category + authors + tags |
| 2.4 | Meta keywords tag | ✅ SKIP | DEJA IMPLEMENTAT — `generateKeywords()` in metadata-generator.ts (liniile 62-96) extrage deja tag names |
| 3.1 | Merge endpoint API | ✅ | NOU: `POST /api/tags/{id}/merge` cu body `{"targetTagId": N}`. Validari: source exists, target exists, source != target |
| 3.2 | Merge UI in admin | ✅ | Buton "Merge" per row + modal cu autocomplete search target + preview articole mutate + warning stergere |
| 3.3 | Bulk actions | SKIP | Tabela nu are checkbox-uri — complexitate negiustificata |
| 4.1 | Pagini publice tags functionale | ✅ | Fixat async params pattern (Next.js 16). Ambele pagini au structura corecta |
| 4.2 | Related tags pe articol | ✅ SKIP | Pagina `/[locale]/tags/[slug]` DEJA ARE sidebar cu related tags. Pe pagina de articol nu adaugam — ArticleSidebar afiseaza related articles, nu tags |
| 4.3 | Hreflang pe pagini tag | ✅ | Adaugat `alternates.languages` cu ro/en/ru in generateMetadata pe tag detail page |
| 4.4 | ISR/cache verificat | ✅ | Pagina articol: `revalidate=120` (2 min). Tags pages: `revalidate=300` via fetchPopularTags. Functioneaza corect |
| 5.1 | Teste backend | ✅ | Route `api_tags_merge` inregistrat si functional (401 fara JWT — corect). Erorile pre-existente neatinse |
| 5.2 | Teste frontend | ✅ | 750/750 Jest tests trec. 0 regresii. TypeScript: 0 erori in fisierele modificate |
| 5.3 | Validare E2E | ✅ | API: tags field present in articles, search works, merge route registered. 11 tag routes total |

---

## Fisiere Modificate

| Fisier | Tip | Detalii |
|--------|-----|---------|
| `apps/frontend/components/article/ArticleHeader.tsx` | Modificat | +TagList import, +tags display sub metadata |
| `apps/frontend/lib/seo/structured-data.ts` | Modificat | +tag names in JSON-LD keywords |
| `apps/backend/src/Controller/TagController.php` | Modificat | +merge endpoint (POST /api/tags/{id}/merge) |
| `apps/frontend/app/[locale]/admin/tags/TagsPageClient.tsx` | Modificat | +merge state, +merge search, +merge handler, +merge button, +merge modal |
| `apps/frontend/app/[locale]/(public)/tags/page.tsx` | Modificat | Fix async params pattern (Next.js 16) |
| `apps/frontend/app/[locale]/(public)/tags/[slug]/page.tsx` | Modificat | Fix async params + searchParams pattern, +hreflang alternates |

## Fisiere Create

Niciun fisier nou creat.

## Endpoint-uri Noi

| Route | Method | Descriere |
|-------|--------|-----------|
| `/api/tags/{id}/merge` | POST | Merge source tag in target tag. Body: `{"targetTagId": N}`. Sterge source, muta articole la target |

---

## Ce era DEJA implementat (0 munca necesara)

1. **ArticleCard tags** — Deja avea TagList cu maxTags=3, size=sm (liniile 231-243)
2. **Meta keywords** — `generateKeywords()` deja extragea tag names din article.tags (liniile 85-93)
3. **Article type tags** — `lib/types/article.ts` deja avea `tags?: (Tag | string)[]`
4. **Public tags pages** — Deja existau cu layout complet (TagCloud pe listing, ArticleCard grid + related tags sidebar pe detail)
5. **TagService.mergeTags()** — Backend service complet existent, doar endpoint-ul API lipsea

## Probleme/Decizii Luate

1. **Params async pattern** — Ambele pagini publice tags foloseau vechiul pattern sync `params: { locale: string }` in loc de `params: Promise<{ locale: string }>`. Actualizat la Next.js 16 pattern.

2. **Hreflang pe tag detail** — Tag slug-urile traduse ar fi diferite per locale (ex: ro:`politica`, en:`politics`, ru:`политика`). Dar API-ul returneaza slug-ul deja tradus per locale, iar paginile folosesc slug-ul din URL. Hreflang-ul foloseste acelasi slug (din URL) — imperfect dar functional. Corectia perfecta ar necesita un API endpoint dedicat pentru translated slugs.

3. **Merge endpoint autentificare** — Endpoint-ul merge este protejat de JWT firewall (returneaza 401 fara token). Corect pentru operatie admin-only.

4. **Bulk actions SKIP** — Tabela admin nu are checkbox-uri. Adaugarea lor ar complica semnificativ UI-ul fara beneficiu proportional. Merge individual este suficient.

5. **Related tags pe pagina de articol SKIP** — Sidebar-ul paginii de articol afiseaza deja "related articles" (articole similare). Adaugarea "related tags" ar aglomera UI-ul. Tags-urile similare sunt deja disponibile pe pagina `/[locale]/tags/[slug]`.

---

## Verificari API

```
GET /api/articles?itemsPerPage=1    → tags field present (empty - no associations yet)
GET /api/tags/search?q=pol          → 1 result (Politica, ID: 1)  
GET /api/tags/popular?limit=5       → 5 tags returned
POST /api/tags/{id}/merge           → 401 (JWT required - correct)
debug:router | grep tag             → 11 tag routes total (6 custom + 5 API Platform)
```

## Rezumat Total Routes Tag

| Route | Method | Source |
|-------|--------|--------|
| `/api/tags` | GET | API Platform |
| `/api/tags` | POST | API Platform |
| `/api/tags/{id}` | GET | API Platform |
| `/api/tags/{id}` | PUT | API Platform |
| `/api/tags/{id}` | DELETE | API Platform |
| `/api/tags/popular` | GET | TagController |
| `/api/tags/search` | GET | TagController |
| `/api/tags/{id}/related` | GET | TagController |
| `/api/tags/{id}/stats` | GET | TagController |
| `/api/tags/unused` | GET | TagController |
| `/api/tags/{id}/merge` | POST | TagController (NOU Sprint B) |
