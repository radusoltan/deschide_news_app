# Fix Hreflang Tag Detail Page: Translated Slugs

**Data:** 2026-04-01
**Branch:** develop

---

## Raport Final

| # | Actiune | Status | Detalii |
|---|---------|--------|---------|
| 1.1 | Analiza tag detail page | ✅ | `generateMetadata` folosea `slug` din URL params identic pentru toate locale-urile |
| 1.2 | Analiza API response | ✅ | API returneaza slug tradus per locale via Gedmo HINT_TRANSLATABLE_LOCALE. RO=base table, EN/RU=ext_translations |
| 1.3 | Verificare ext_translations | ✅ | 50 din 51 tag-uri au slug-uri EN + RU. 0 tag-uri au RO in ext_translations (RO e in base table). Column: `foreign_key` (nu `object_id`) |
| 1.4 | Verificare pattern articole | ✅ | Articolele au ACEEASI problema — `buildAlternateUrls()` falls back to same slug for all locales. Fix separat necesar |
| 1.5 | Pattern existent translated slugs | ✅ | Nu exista niciun pattern. Aceasta e prima implementare |
| 2 | Strategie aleasa | A | Backend returneaza `translatedSlugs` in raspunsul API. Un singur request, 0 apeluri suplimentare din frontend |
| 3.1 | Backend translatedSlugs | ✅ | Tag entity + TagProvider: batch query ext_translations + base table |
| 3.2 | Frontend hreflang fix | ✅ | Tag detail page: `tag.translatedSlugs?.ro/en/ru` cu fallback la slug-ul curent |
| 3.3 | Tip Tag actualizat | ✅ | `translatedSlugs?: { ro?: string; en?: string; ru?: string }` |
| 3.4 | Aceeasi problema pe articole? | DA | Articles folosesc `buildAlternateUrls()` fara translations. Fix SEPARAT necesar (necesita translatedSlugs pe Article + Category) |
| 4.1 | API translatedSlugs verificat | ✅ | Single item: ✅, Collection: ✅, Custom endpoints: omis (nu e necesar) |
| 4.2 | Teste existente | ✅ | Frontend: 750/750 Jest pass, TS: 0 erori noi. Backend: cache cleared, routes intact |
| 4.3 | Edge cases (fara traducere) | ✅ | 1 tag fara EN/RU slug → frontend fallback la slug-ul curent (`|| slug`) |

---

## Strategie Implementata: A — Backend `translatedSlugs`

### Cum functioneaza

1. **Tag entity** (`Tag.php`): Proprietate non-persistata `translatedSlugs` (fara `@ORM\Column`), cu `#[Groups(['tag:read'])]`
2. **TagProvider** (`TagProvider.php`): Dupa incarcarea tag-urilor (single sau collection), ruleaza 2 query-uri SQL:
   - `SELECT id, slug FROM tags WHERE id IN (...)` — slug-urile din base table (= locale default RO)
   - `SELECT foreign_key, locale, content FROM ext_translations WHERE ... AND field = 'slug'` — slug-urile traduse EN/RU
3. **TagController** (`TagController.php`): `serializeTag()` include `translatedSlugs` cand este populat
4. **Frontend**: Tag type actualizat, tag detail page foloseste `tag.translatedSlugs?.{locale}` cu fallback

### Exemplu API Response

```json
{
  "@id": "/api/tags/1",
  "@type": "Tag",
  "id": 1,
  "name": "Politica",
  "slug": "politics",
  "translatedSlugs": {
    "ro": "politics",
    "en": "politics",
    "ru": "politika"
  }
}
```

### Hreflang generat (corect)

```html
<link rel="alternate" hreflang="ro" href="https://deschide.md/ro/tags/politics" />
<link rel="alternate" hreflang="en" href="https://deschide.md/en/tags/politics" />
<link rel="alternate" hreflang="ru" href="https://deschide.md/ru/tags/politika" />
```

---

## Fisiere Modificate

| Fisier | Detalii |
|--------|---------|
| `apps/backend/src/Entity/Tag.php` | +`translatedSlugs` property (non-persisted, `tag:read` group), +getter/setter |
| `apps/backend/src/State/TagProvider.php` | +`populateTranslatedSlugs()` method, enrich single item + collection results |
| `apps/backend/src/Controller/TagController.php` | `serializeTag()` includes `translatedSlugs` when present |
| `apps/frontend/lib/types/tag.ts` | +`translatedSlugs?: { ro?: string; en?: string; ru?: string }` |
| `apps/frontend/app/[locale]/(public)/tags/[slug]/page.tsx` | `alternates.languages` uses `tag.translatedSlugs` with fallback |

## Fisiere Create

Niciun fisier nou.

---

## Observatie: Problema pe Articole

Paginile de articol au ACEEASI problema cu hreflang — `buildAlternateUrls()` din `lib/seo/metadata-generator.ts` foloseste acelasi slug pentru toate locale-urile cand nu primeste parametrul `translations`. Fix-ul necesita:
1. Adaugare `translatedSlugs` pe Article entity (similar cu Tag)
2. Populare in ArticleProvider din ext_translations (slug article + slug category per locale)
3. Transmitere translations la `buildAlternateUrls()` din pagina de articol

Aceasta e o sarcina separata — mai complexa decat tag-urile deoarece implica si translated category slugs.

---

## Observatie: Slug-uri RO in base table

Base table `tags.slug` contine valori care arata ca slug-uri englezesti (`politics`, `economy`, `sport`). Aceasta e probabil o problema de import — tag-urile au fost create cu slug-uri EN ca locale default. RO nu are intrari separate in ext_translations deoarece valorile din base table SUNT considerate locale-ul default.

Aceasta e o problema de date, nu de cod. Slug-urile sunt consistente cu ce returneaza API-ul.
