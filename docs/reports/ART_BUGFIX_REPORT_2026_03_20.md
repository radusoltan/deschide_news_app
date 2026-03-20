# Raport Remediere ART CRUD Bugs — Claude Code — 20 Martie 2026

**Data**: 20 Martie 2026
**Remediat de**: Claude Code (Opus 4.6)
**Referință**: Raport Gemini QA - `docs/reports/ART_CRUD_TEST_REPORT_2026_03_20.md`

---

## Sumar Fix-uri

| Bug ID | Severitate | Status | Cauza Root | Fișiere Modificate |
|--------|-----------|--------|------------|-------------------|
| BUG-ART-001 | CRITICAL | FIXED | Frontend: lipsă tab-uri traduceri pe pagina edit | 3 fișiere |
| BUG-ART-002 | HIGH | FIXED | Frontend: search client-side only + Backend: lipsă title filter în ArticleProvider | 4 fișiere |
| BUG-ART-003 | MEDIUM | FIXED | Frontend: TinyMCE race condition pe onEditorChange/setup | 1 fișier |

---

## BUG-ART-001: Traducerile nu se persistă în ext_translations

### Investigare
- Backend-ul (ArticleProcessor.php) gestionează CORECT traducerile:
  - Citește locale din `Accept-Language` header
  - Setează `setTranslatableLocale()` pe entitate
  - Folosește `$translationRepo->translate()` explicit pentru locale non-default
  - Salvează corect în `ext_translations`
- **Test API direct**: PUT cu `Accept-Language: en` → traducerile SE salvează corect în DB
- **Cauza reală**: Frontend-ul NU avea tab-uri de limbă pe pagina de editare articol. Utilizatorii nu aveau cum să comute între RO/EN/RU pentru a edita traduceri.

### Fix aplicat
1. **Nou component**: `TranslationTabs.tsx` — tab-uri RO/EN/RU pe pagina edit articol
   - Tab-urile navighează între `/{locale}/admin/articles/{id}/edit`
   - Tab-ul activ este evidențiat vizual
   - Mesaj informativ când se editează o traducere non-default
2. **Integrat în** `[id]/edit/page.tsx` — tab-urile apar deasupra formularului

### Fișiere modificate
- `apps/frontend/app/[locale]/admin/articles/[id]/edit/components/TranslationTabs.tsx` (NOU)
- `apps/frontend/app/[locale]/admin/articles/[id]/edit/page.tsx` (MODIFICAT)

### Validare
```sql
-- ext_translations conține traduceri EN și RU pentru articolul 81
SELECT locale, field, LEFT(content, 50) FROM ext_translations
WHERE object_class LIKE '%Article%' AND foreign_key = '81';

-- Rezultat: 8 rânduri (en: title, slug, lead, content; ru: title, slug, lead, content)
```

```bash
# API returnează traduceri corecte per locale
GET /api/articles/81 Accept-Language: en → "English Test by Claude Code"
GET /api/articles/81 Accept-Language: ru → "Тестовая статья Claude Code"
GET /api/articles/81 Accept-Language: ro → "Gemini Test Article 001 — Full Fields" (original)
```

---

## BUG-ART-002: Căutarea e doar client-side

### Investigare
- `ArticlesTableClient.tsx` folosea `useMemo` pentru filtrare client-side pe array-ul `articles` primit ca props
- Array-ul conținea doar articolele de pe pagina curentă (20 din 80+)
- Backend: Article entity avea `#[ApiFilter(SearchFilter::class, properties: ['title' => 'partial'])]` dar custom `ArticleProvider` nu implementa filtrul `title`
- Rezultat: `GET /api/articles?title=Gemini` returna 0 rezultate (filtrul era ignorat)

### Fix aplicat
1. **Backend** (`ArticleProvider.php`): Adăugat suport `?title=` în query builder:
   ```php
   if ($title = $request->query->get('title')) {
       $queryBuilder->andWhere('LOWER(a.title) LIKE LOWER(:titleSearch)')
           ->setParameter('titleSearch', '%' . $title . '%');
   }
   ```
2. **Frontend API Route** (`/api/articles/search/route.ts`): Proxy autentificat pentru search
3. **Frontend Component** (`ArticlesTableClient.tsx`):
   - Debounced server-side search (300ms)
   - Loading spinner în search input
   - Rezultate server vs. client diferențiate vizual
   - Clear search revine la lista paginată normală

### Fișiere modificate
- `apps/backend/src/State/ArticleProvider.php` (MODIFICAT — adăugat title filter)
- `apps/frontend/app/api/articles/search/route.ts` (NOU — search proxy)
- `apps/frontend/app/[locale]/admin/articles/ArticlesTableClient.tsx` (RESCRIERE — server-side search)

### Validare
```bash
# Backend search funcționează
GET /api/articles?title=Gemini → totalItems: 1 (articolul 81)
GET /api/articles?title=Test+Art → totalItems: 2

# Frontend: search input trimite request API cu debounce
# Network tab: GET /api/articles/search?title=Gemini&locale=ro&itemsPerPage=50
```

---

## BUG-ART-003: TinyMCE race condition

### Investigare
- Eroare: `TypeError: Cannot read properties of undefined (reading 'parse')` în `tinymce.min.js`
- Cauza: `onEditorChange` se declanșa înainte ca editorul TinyMCE să fie complet inițializat
- Self-hosted TinyMCE (`/tinymce/tinymce.min.js`) are timing variabil la încărcare

### Fix aplicat
1. Adăugat `isReadyRef` (useRef) care devine `true` doar după `onInit` + `setup.init`
2. Guard pe `handleEditorChange`: verifică `isReadyRef.current` înainte de `editor.getContent()`
3. Try-catch pe `getContent()` pentru a prinde orice eroare reziduală
4. Callback-uri memoizate cu `useCallback` pentru a preveni re-render-uri inutile
5. Dublu guard: atât în `onInit` callback cât și în `setup.init` event

### Fișier modificat
- `apps/frontend/components/editor/TinyEditor.tsx` (RESCRIERE — guards și memoizare)

### Validare
- Navigare rapidă (5x consecutiv) pe `/admin/articles/create` → zero TypeError în consolă
- Editor-ul se încarcă corect și acceptă input fără erori

---

## Re-test Rezultate

| Test | Status | Observații |
|------|--------|-----------|
| ART-U03 (Traduceri EN/RU) | PASS | Tab-uri funcționale, traduceri persistă în ext_translations, API returnează per locale |
| ART-R02 (Căutare server-side) | PASS | Search trimite GET API, găsește articole din toată baza de date, debounce 300ms |
| ART-C02 (TinyMCE init) | PASS | Guard pe isReady, zero TypeError la navigare rapidă |

---

## Impact

### Backend
- PHPUnit: **532 tests, 2532 assertions — ALL PASSING** (0 regressions)
- Title search filter adăugat în ArticleProvider (backward compatible)
- Cache invalidation neschimbată

### Frontend
- Build: **SUCCES** (0 erori compilare)
- Noi route-uri: `/api/articles/search` (search proxy)
- Componente noi: `TranslationTabs` (edit page)
- TinyEditor: guards adăugate (backward compatible)

---

*Raport generat de Claude Code (Opus 4.6) pe baza testării Gemini CLI QA Agent.*
