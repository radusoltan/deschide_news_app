# Raport Remediere CAT Feature Gaps — Claude Code — 20 Martie 2026

**Data**: 20 Martie 2026
**Remediat de**: Claude Code (Opus 4.6)
**Referinta**: Raport Gemini QA - `docs/reports/CAT_CRUD_TEST_REPORT_2026_03_20.md`

---

## Sumar Fix-uri

| Issue | Severitate | Status | Fisiere Modificate |
|-------|-----------|--------|-------------------|
| CAT-C02: Parent-child dropdown | HIGH | FIXED | 7 fisiere (3 backend + 4 frontend) |
| CAT-U02: Translation tabs | MEDIUM | FIXED | 2 fisiere (frontend) |

---

## CAT-C02: Dropdown Parent-Child pe formular categorii

### Cauza
Entitatea Category NU avea campul `parent`. Nu exista nici in entity, nici in DB.
Necesitar backend changes (contrar instructiunilor initiale care presupuneau ca exista).

### Fix aplicat

**Backend:**
1. `Category.php` — Adaugat `parent` (ManyToOne self-referencing) + `children` (OneToMany inverse)
2. Migration `Version20260320111919.php` — ALTER TABLE categories ADD parent_id
3. `CategoryProcessor.php` — Handle parent field pe create si update (get managed entity)

**Frontend:**
4. `CategoryForm.tsx` — Adaugat dropdown "Parent Category" cu optiune "No parent (top-level)"
5. `categories.ts` (server actions) — Extract parent from FormData, send as IRI
6. `dal.ts` — Adaugat `parent` in types si function signatures
7. `CategoriesTable.tsx` — Adaugat coloana "Parent" + indicator vizual (mdash prefix)
8. `new/page.tsx` — Fetch categories for parent dropdown
9. `[id]/edit/page.tsx` — Fetch categories + extract parentId from API response

### Self-reference prevention
- Pe edit page, categoria curenta este exclusa din dropdown (`filter(c => c.id !== category?.id)`)
- Backend valideaza ca parentId != entityId inainte de setare

### Validare
```
POST /api/categories {parent: "/api/categories/1"} → parent_id=1 in DB ✅
GET /api/categories/11 → parent object cu id=1, title="Politica" ✅
PUT /api/categories/11 {parent: null} → parent_id=NULL ✅
```

---

## CAT-U02: Tab-uri Traducere pe editare categorii

### Cauza
Pagina de editare categorii nu avea tab-uri RO/EN/RU. Backend-ul functiona corect
(confirmat de Gemini: "Persistence OK: Traducerile se salveaza corect in ext_translations").

### Fix aplicat
1. Creat `TranslationTabs.tsx` generalizat (bazat pe varianta Articles dar cu `basePath` param)
2. Integrat pe `[id]/edit/page.tsx` — tab-uri RO/EN/RU deasupra formularului
3. Mesaj informativ la editare non-default locale

### Validare
```sql
SELECT locale, field, content FROM ext_translations
WHERE object_class LIKE '%Category%' AND foreign_key = '1';
-- en | title | Politics
-- ru | title | Политика

GET /api/categories/1 Accept-Language: en → "Politics" ✅
GET /api/categories/1 Accept-Language: ru → "Политика" ✅
GET /api/categories/1 Accept-Language: ro → "Politica" ✅
```

---

## Re-test Rezultate

| Test | Status | Observatii |
|------|--------|-----------|
| CAT-C02 | PASS | Dropdown parent vizibil pe create + edit. Self-reference prevention activ. |
| CAT-U02 | PASS | Tab-uri RO/EN/RU vizibile. Traduceri persist in ext_translations. API per locale OK. |

## Verificare Regresii

| Test | Status | Observatii |
|------|--------|-----------|
| CAT-R01 | PASS | Lista se incarca normal, coloana Parent vizibila |
| CAT-C01 | PASS | Creare categorie fara parent functioneaza |
| CAT-C03 | PASS | Validare title/slug required functioneaza |
| CAT-U01 | PASS | Editare simpla (title change) functioneaza |
| CAT-D01 | PASS | Stergere cu confirmare functioneaza |

## Backend Tests
- Unit tests: 367/367 PASS (19 Category tests PASS)
- Frontend build: SUCCESS (0 erori)

---

*Raport generat de Claude Code (Opus 4.6).*
