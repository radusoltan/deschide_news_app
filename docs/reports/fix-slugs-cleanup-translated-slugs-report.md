# Fix: Tag Slug Data Cleanup + Article/Category Hreflang Translated Slugs

**Data:** 2026-04-01
**Branch:** develop

---

## Raport Final

| # | Actiune | Status | Detalii |
|---|---------|--------|---------|
| **FAZA 1: ANALIZA** | | | |
| 1.1 | Audit tag slugs RO vs EN | DONE | 15 din 20 tag-uri (1-20) aveau slug EN in base table |
| 1.2 | Audit tag names RO vs EN | DONE | Names corecte RO in base table (doar slug-urile erau EN) |
| 1.3 | Analiza Article entity/provider | DONE | Article NU avea translatedSlugs. ArticleProvider eager loads category, authors, tags, images |
| 1.4 | Analiza Category entity | DONE | Existe entitate separata cu Gedmo Translatable, slug tradus EN/RU |
| 1.5 | Analiza buildAlternateUrls | DONE | Deja avea param `translations` optional! Apelat din: metadata-generator.ts (via generateArticleMetadata) |
| 1.6 | Audit article slugs | DONE | Article slugs RO corecte in base table. Unele au traduceri EN/RU in ext_translations |
| 1.7 | Pattern TagProvider referinta | DONE | populateTranslatedSlugs: batch query base table + ext_translations, set pe entity |
| **FAZA 2: TAG SLUG CLEANUP** | | | |
| 2.1 | Mapping corectie generat | DONE | 15 slug-uri de corectat, 14 duplicate de sters |
| 2.2 | Corectie aplicata | DONE | Comanda Symfony `app:fix:tag-slugs` — tranzactie cu rollback safety |
| 2.3 | Verificare post-cleanup | DONE | Toate 37 tag-uri cu slug-uri RO corecte. API returneaza translatedSlugs corect |
| 2.4 | Name-uri — necesita corectie? | NU | Name-urile din base table sunt RO corecte (Politica, Economie, etc.) |
| **FAZA 3: TRANSLATED SLUGS ARTICLE/CATEGORY** | | | |
| 3.1 | Article.translatedSlugs | DONE | Proprietate non-persistata, Groups(['article:read']) |
| 3.2 | ArticleProvider enrichment | DONE | Batch query, 0 N+1. Single item + collection |
| 3.3 | Category.translatedSlugs | DONE | Proprietate non-persistata, Groups(['category:read', 'article:read']) |
| 3.4 | Category slug in Article response | DONE | Populat in ArticleProvider si SlugController |
| 3.5 | Frontend types actualizate | DONE | translatedSlugs pe Article si Category |
| **FAZA 4: FIX buildAlternateUrls** | | | |
| 4.1 | buildAlternateUrls refactorizat | DONE | Functia deja suporta `translations` param — nu a necesitat modificare |
| 4.2 | generateArticleMetadata actualizat | DONE | Construieste translations din article.translatedSlugs + category.translatedSlugs |
| 4.3 | Alte pagini actualizate | N/A | Tag detail deja folosea translatedSlugs. Tags listing nu necesita hreflang pe slug |
| 4.4 | Tag detail page — intact | DONE | Functioneaza cu noile slug-uri RO corecte |
| **FAZA 5: VALIDARE** | | | |
| 5.1 | Tag slugs RO corecte | DONE | /ro/tags/politica, /ro/tags/economie, etc. |
| 5.2 | Article hreflang corect | DONE | translatedSlugs cu ro/en/ru unde exista traduceri |
| 5.3 | API translatedSlugs Article | DONE | Single + collection + by-slug endpoint |
| 5.4 | Teste existente | DONE | Backend: 74 entity tests + 24 tag tests pass. Frontend: 750/750 pass |
| 5.5 | Edge cases (fara traducere) | DONE | Articles fara EN/RU: translatedSlugs contine doar `ro`. Frontend fallback functional |

---

## Tag Slugs Corectate

**15 slug-uri fixate (EN → RO):**

| ID | Name | Before | After |
|---|---|---|---|
| 1 | Politica | politics | politica |
| 2 | Economie | economy | economie |
| 4 | Cultura | culture | cultura |
| 5 | Tehnologie | technology | tehnologie |
| 6 | Sanatate | health | sanatate |
| 7 | Educatie | education | educatie |
| 8 | Mediu | environment | mediu |
| 9 | Justitie | justice | justitie |
| 10 | Infrastructura | infrastructure | infrastructura |
| 12 | UE | eu | ue |
| 14 | Rusia | russia | rusia |
| 17 | Alegeri | elections | alegeri |
| 18 | Coruptie | corruption | coruptie |
| 19 | Agricultura | agriculture | agricultura |
| 20 | Energie | energy | energie |

**14 duplicate tags sterse:** IDs 21, 23, 28, 29, 30, 31, 33, 34, 36, 38, 40, 41, 42, 44
**84 ext_translations rows sterse** (pentru duplicate)
**Total tags ramase:** 37 (de la 51)

---

## Fisiere Modificate

| Fisier | Detalii |
|--------|---------|
| `apps/backend/src/Entity/Article.php` | +`translatedSlugs` property (non-persisted, `article:read` group), +getter/setter |
| `apps/backend/src/Entity/Category.php` | +`translatedSlugs` property (non-persisted, `category:read` + `article:read` groups), +getter/setter |
| `apps/backend/src/State/ArticleProvider.php` | +`populateTranslatedSlugs()` method (article + category slugs batch query), enrich single + collection |
| `apps/backend/src/Controller/Api/SlugController.php` | +`populateArticleTranslatedSlugs()` method, called before serialization in by-slug endpoint |
| `apps/backend/src/Controller/TagController.php` | Unchanged (already had translatedSlugs support from previous sprint) |
| `apps/backend/src/State/TagProvider.php` | Unchanged (translatedSlugs now returns correct RO slugs after data fix) |
| `apps/frontend/lib/types/article.ts` | +`translatedSlugs` on Article and Category interfaces |
| `apps/frontend/lib/seo/metadata-generator.ts` | `generateArticleMetadata()` now builds translations from translatedSlugs and passes to `buildAlternateUrls()` |

## Fisiere Create

| Fisier | Detalii |
|--------|---------|
| `apps/backend/src/Command/FixTagSlugsCommand.php` | Comanda temporara `app:fix:tag-slugs` — poate fi stearsa dupa deploy |

---

## Exemplu API Response (Article cu translatedSlugs)

```json
{
  "@id": "/api/articles/3395",
  "title": "Vitalie Vovc: Saptamana in care razboiul ne-a luat apele",
  "slug": "vitalie-vovc-saptamana-in-care-razboiul-ne-a-luat-apele",
  "translatedSlugs": {
    "ro": "vitalie-vovc-saptamana-in-care-razboiul-ne-a-luat-apele",
    "en": "vitalie-vovc-the-week-the-war-took-our-waters",
    "ru": "vitalii-vovk-nedelya-v-kotoruyu-voina-otnyala-u-nas-vodu"
  },
  "category": {
    "title": "Editoriale",
    "slug": "editoriale",
    "translatedSlugs": {
      "ro": "editoriale"
    }
  }
}
```

## Exemplu Hreflang Output (generat de generateArticleMetadata)

```html
<!-- Article cu traduceri complete -->
<link rel="alternate" hreflang="ro" href="https://deschide.md/editoriale/vitalie-vovc-saptamana-in-care-razboiul-ne-a-luat-apele" />
<link rel="alternate" hreflang="en" href="https://deschide.md/en/editoriale/vitalie-vovc-the-week-the-war-took-our-waters" />
<link rel="alternate" hreflang="ru" href="https://deschide.md/ru/editoriale/vitalii-vovk-nedelya-v-kotoruyu-voina-otnyala-u-nas-vodu" />

<!-- Tag cu slug-uri corecte per locale -->
<link rel="alternate" hreflang="ro" href="https://deschide.md/ro/tags/politica" />
<link rel="alternate" hreflang="en" href="https://deschide.md/en/tags/politics" />
<link rel="alternate" hreflang="ru" href="https://deschide.md/ru/tags/politika" />
```

---

## Performanta

- Collection API (20 articole): **207ms** — fara impact semnificativ de la batch query-urile ext_translations
- Query-uri adaugate per request: **2 extra** (base table + ext_translations) pentru articole + **2 extra** pentru categorii
- Toate query-urile sunt batch (IN clause), nu N+1

## Breaking Changes

- **Tag URL-uri RO vechi** nu mai functioneaza: `/ro/tags/politics` → acum e `/ro/tags/politica`
  - Impact: minim (site-ul nu a fost lansat public, 0 trafic extern)
  - Daca necesar, se pot adauga redirect-uri 301 in Nginx sau UrlRedirect entity

## Comanda Temporara

`app:fix:tag-slugs` — poate fi stearsa dupa deploy (o singura rulare, idempotenta prin precondition check pe article_tag count)
