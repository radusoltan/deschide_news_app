# Sprint 8: Menu Builder — Raport Final

**Data**: 2026-03-30
**Branch**: `feature/menu-builder`
**Base branch**: `fix/design-audit-remediation`

## Rezumat

- Task-uri completate: **4/4**
- Commits: 4 (3 feature + 1 docs)
- Teste API: **9/9 PASS**
- Frontend build: **PASS**
- PHPUnit: Pre-existing failures only, zero new regressions

## Commits

| # | Hash | Mesaj |
|---|------|-------|
| 1 | `ca231f2` | feat(menu): add MenuItem entity with API Platform CRUD and Gedmo translations |
| 2 | `ccc88af` | feat(menu): add seed command to populate menu items from existing categories |
| 3 | `1ab1d3a` | feat(menu): add admin Menu Builder page with reorder and CRUD |
| 4 | (this commit) | docs: add Menu Builder Sprint 8 report |

## TASK 1 — Entitate MenuItem + API

**Status**: DONE

### Fișiere create
- `apps/backend/src/Entity/MenuItem.php` — Entitate cu Gedmo Translatable, validare, API Platform
- `apps/backend/src/Enum/MenuType.php` — Enum: `main`, `footer`
- `apps/backend/src/Enum/MenuItemType.php` — Enum: `category`, `external_link`
- `apps/backend/src/State/MenuItemProcessor.php` — Processor cu traduceri (pattern CategoryProcessor)
- `apps/backend/src/State/MenuItemProvider.php` — Provider cu Accept-Language locale support
- `apps/backend/migrations/Version20260330075511.php` — Migrare DB cu indexuri
- `apps/backend/config/packages/security.yaml` — Public GET + Admin write access

### Câmpuri MenuItem
id, menu (enum), type (enum), label (Gedmo Translatable), url (nullable), category (ManyToOne), position, isActive, openInNewTab, cssClass, createdAt (Timestampable), updatedAt (Timestampable)

### Validări
- `external_link` fara `url` → 422
- `category` fara `category` relation → 422
- `label` NotBlank

### API Endpoints
- `GET /api/menu-items` — Public, cu filtre `menu`, `isActive`, `order[position]`
- `GET /api/menu-items/{id}` — Public
- `POST /api/menu-items` — Admin/Editor
- `PATCH /api/menu-items/{id}` — Admin/Editor, cu Accept-Language pentru traduceri
- `DELETE /api/menu-items/{id}` — Admin/Editor

## TASK 2 — Seed MenuItem

**Status**: DONE

### Command
`php bin/console app:seed-menu-items [-v] [--dry-run]`

### Rezultate seed
| Meniu | Items create | Categorii |
|-------|-------------|-----------|
| Main | 10 | Sport, Politica, Economie, Societate, Cultura, Externe, Stiinta, Tehnologie, Opinii, Investigatii |
| Footer | 6 | Sport, Politica, Economie, Societate, Cultura, Externe |
| **Total** | **16** | |

### Traduceri create
- 32 traduceri (16 items x 2 locale-uri: en, ru)
- Label-uri copiate din traducerile categoriilor existente

### Idempotenta
Verificat: rularea de 2 ori nu creaza duplicate.

## TASK 3 — Frontend Admin UI

**Status**: DONE

### Fișiere create
- `apps/frontend/lib/types/menu.ts` — TypeScript interfaces (MenuItem, CreateMenuItemData, etc.)
- `apps/frontend/lib/api/menu-items.ts` — API service (CRUD functions)
- `apps/frontend/app/[locale]/admin/menu-builder/page.tsx` — Server page component
- `apps/frontend/app/[locale]/admin/menu-builder/MenuBuilderManager.tsx` — Client component cu CRUD complet

### Fișiere modificate
- `apps/frontend/lib/types/index.ts` — Export menu types
- `apps/frontend/app/[locale]/admin/components/Sidebar.tsx` — Link "Menu Builder" adaugat
- `apps/frontend/lib/dal.ts` — Fix: adaugat `inMenu`/`inFooterMenu` pe Category interface

### Funcționalitați
- Tab-uri Main Menu / Footer Menu
- Tabel cu items sortati by position
- Butoane reordonare (▲/▼) cu swap position
- Toggle active/inactive (PATCH isActive)
- Add Category — modal cu dropdown categorii neadaugate
- Add External Link — modal cu Label, URL, Open in New Tab
- Edit — modal cu tab-uri traducere RO/EN/RU
- Delete — modal confirmare
- Badges: albastru (category), violet (external_link)

### Build
Frontend build: **PASS** (compiled successfully)

## TASK 4 — Teste End-to-End

### Teste API

| Test | Descriere | Rezultat |
|------|-----------|----------|
| T1 | GET menu items (main) | PASS — 10 items |
| T2 | GET menu items (footer) | PASS — 6 items |
| T3 | POST category item | PASS — HTTP 201 |
| T4 | POST external link | PASS — HTTP 201 |
| T5 | Validare: external_link fara url | PASS — HTTP 422 |
| T6 | Validare: category fara category | PASS — HTTP 422 |
| T7 | Traducere label (PATCH en + GET en) | PASS — label=Google EN |
| T8 | Toggle isActive | PASS — isActive=false |
| T9 | DELETE | PASS — HTTP 204 |

### PHPUnit
- **Total**: 3861 tests
- **Errors**: 41 (pre-existing: AuthorType, NotificationType, DatabaseQueryPerformance)
- **Failures**: 21 (pre-existing)
- **Menu Builder related**: 0 errors, 0 failures
- **Concluzie**: Zero regresii din schimbarile Sprint 8

### Frontend Build
- `pnpm build`: **PASS**
- Zero erori TypeScript

## Probleme intampinate

1. **Serialization `isActive`**: Symfony serializer maps `isActive()` getter to `active`. Fix: adaugat `getIsActive()` getter + `#[SerializedName('isActive')]`
2. **Gedmo findAll() boolean hydration**: `findAll()` pe repository Gedmo returna `false` pentru `inMenu`/`inFooterMenu`. Fix: DQL direct query in seed command.
3. **Category IDs non-secventiale**: Categoriile incep de la ID 4 (nu 1). Testele API adaptate.
4. **Branch design preservation**: Initial, branch-ul a fost creat din `main` (design vechi). Corectat: branch creat din `fix/design-audit-remediation`.
