# Sprint 10: Dropdown Menus + Drag & Drop Reorder

**Date**: 2026-03-30
**Branch**: `feature/dynamic-menus`
**Status**: COMPLETE

## Summary

Added dropdown menu support to the MenuItem system. Admin can create dropdown containers with nested sub-items, reorder via drag-and-drop, and the public frontend renders dropdowns dynamically from API data. Hardcoded "Stiri"/"News" dropdown and "Toate"/"All" link removed.

## Task Results

| Task | Description | Status | Commit |
|------|-------------|--------|--------|
| TASK 1 | Backend parent-child + dropdown type | Done | `2d7886b` |
| TASK 2 | Seed dropdown "Stiri" with sub-items | Done | `81b2ab4` |
| TASK 3 | Admin Menu Builder with DnD + nesting | Done | `d90a641` |
| TASK 4 | Frontend public dropdown rendering | Done | `3007dc4` |
| TASK 5 | Tests + Report | Done | this commit |

## Changes by Task

### TASK 1: Backend Parent-Child + Dropdown Type

**Files modified:**
- `apps/backend/src/Enum/MenuItemType.php` - Added `DROPDOWN = 'dropdown'`
- `apps/backend/src/Entity/MenuItem.php` - Added `parent` (ManyToOne self), `children` (OneToMany), constructor, validation (max 1-level nesting, dropdown cannot be sub-item, dropdown must not have url/category), ExistsFilter on parent
- `apps/backend/src/State/MenuItemProvider.php` - Eager-load children + children.category
- `apps/backend/src/State/MenuItemProcessor.php` - Handle parent field in PATCH + POST
- `apps/backend/migrations/Version20260330092431.php` - parent_id column + FK

**API filters added:**
- `?type=dropdown` - filter by type
- `?parent=/api/menu-items/5` - filter by parent
- `?exists[parent]=false` - top-level items only

### TASK 2: Seed Dropdown

**Files created:**
- `apps/backend/src/Command/SeedMenuDropdownCommand.php` - Idempotent console command

**Result:**
- Dropdown "Stiri" (ID: 25) created at position 0
- Translations: EN="News", RU="Novosti"
- 4 categories moved as sub-items: Politica, Societate, Economie, Externe
- Remaining 5 top-level items repositioned

### TASK 3: Admin DnD Menu Builder

**Files modified:**
- `apps/frontend/lib/types/menu.ts` - Added dropdown type, parent/children fields
- `apps/frontend/lib/api/public-menu.ts` - Added dropdown handling in getMenuItemHref
- `apps/frontend/app/[locale]/admin/menu-builder/page.tsx` - Updated description
- `apps/frontend/app/[locale]/admin/menu-builder/MenuBuilderManager.tsx` - Full rewrite

**Files created:**
- `apps/frontend/app/[locale]/admin/menu-builder/SortableMenuItem.tsx` - DnD item component
- `apps/frontend/app/[locale]/admin/menu-builder/DragOverlayItem.tsx` - Drag overlay

**Packages:**
- `@dnd-kit/core`, `@dnd-kit/sortable`, `@dnd-kit/utilities` (already installed)

**Features:**
- Main menu: @dnd-kit sortable with tree visualization
- Dropdown expand/collapse with indented children
- Drag to reorder or nest under dropdowns
- Batch PATCH after drag-end
- "Add Dropdown" button with translation support
- Footer menu: unchanged (flat with arrow buttons)

### TASK 4: Public Frontend Dropdowns

**Files modified:**
- `apps/frontend/lib/api/public-menu.ts` - Added `buildMenuTree()` function
- `apps/frontend/app/[locale]/(public)/components/Header.tsx` - Dynamic dropdown rendering

**Removed:**
- Hardcoded "Stiri"/"News" dropdown button
- Hardcoded "Toate"/"All" link (desktop + mobile)
- `dropdownCategories` computed list
- `isStiriDropdownOpen` / `isMobileStiriOpen` state

**Added:**
- Desktop: hover + click dropdown with sub-items from API
- Mobile: expandable sections for dropdown items
- `buildMenuTree()`: builds parent-child tree from flat API response
- Graceful fallback when API is empty

## Build & Tests

- **Frontend build**: PASS (zero TypeScript errors)
- **Backend PHPUnit**: 3,861 tests, pre-existing failures (41 errors, 21 failures) - none related to MenuItem changes
- **Schema validation**: OK (database in sync)

## API Verification

```
GET /api/menu-items?menu=main (Accept-Language: ro)
  0. [dropdown] Stiri [4 children]
     - Politica
     - Societate
     - Economie
     - Externe
  1. [category] Cultura
  2. [category] Stiinta
  3. [category] Tehnologie
  4. [category] Opinii
  5. [category] Sport

GET /api/menu-items?menu=main (Accept-Language: en)
  0. [dropdown] News [4 children]
     - Politics, Society, Economy, International
  1-5: Culture, Science, Technology, Opinions, Sports
```

## Architecture Decisions

1. **Max 1-level nesting**: Dropdowns can contain items, but items cannot contain sub-items. Enforced via entity validation.
2. **Tree built client-side**: `buildMenuTree()` in public-menu service constructs parent-child tree from flat API response.
3. **Footer unaffected**: Footer menu remains flat, no DnD, no nesting.
4. **Fallback**: If MenuItem API returns empty, header falls back to category-based navigation.
5. **Orphan removal**: Deleting a dropdown cascades to children (`orphanRemoval: true`).
