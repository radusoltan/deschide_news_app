# Menu Category Reorganization Plan

This plan addresses the user requirement to reorganize the main navigation menu. Categories flagged with `in_menu` will appear directly in the navigation bar. All other active categories will be grouped under a new dropdown menu item labelled "Stiri" (News).

## User Review Required

> [!IMPORTANT]
> **API Change**: This introduces new fields `inMenu` and `inFooterMenu` to the `Category` resource.
> **Frontend Change**: The navigation bar behavior significantly changes. `CategoryNavWrapper` will now fetch *all* active categories instead of just those marked `onFrontPage`.

## Proposed Changes

### Backend (`apps/backend`)

#### [MODIFY] [Category.php](file:///var/www/deschide_news_app/apps/backend/src/Entity/Category.php)
- Add `inMenu` boolean property (default `false`).
- Add `inFooterMenu` boolean property (default `false`).
- Add getters and setters.
- Add `#[Groups(['category:read', 'category:write'])]` to expose them.
- Add `#[ORM\Column(options: ['default' => false])]`.

#### [NEW] Migration
- Generate a new Doctrine migration to update the `categories` table.

### Frontend (`apps/frontend`)

#### [MODIFY] [CategoryNavWrapper.tsx](file:///var/www/deschide_news_app/apps/frontend/components/navigation/CategoryNavWrapper.tsx)
- Change data fetching from `fetchFrontPageCategories` to `fetchCategories` to retrieve all active categories, regardless of `onFrontPage` status.

#### [MODIFY] [CategoryNav.tsx](file:///var/www/deschide_news_app/apps/frontend/components/navigation/CategoryNav.tsx)
- Update props to accept all categories.
- Implement logic to separate categories into:
    - `menuItems`: Categories with `inMenu === true`.
    - `dropdownItems`: Categories with `inMenu === false` (to be placed in "Stiri").
- Update Desktop view:
    - Render `menuItems` as before.
    - Add a new "Stiri" item that opens a dropdown containing `dropdownItems`.
- Update Mobile view:
    - Consider showing "Stiri" as a collapsible section or list all items. *Decision*: Follow desktop logic (dropdown) or just list them all?
    - *Refinement*: For mobile, often it's better to just list them, but to match the request, we will keep the "Stiri" grouping or flatten if "Stiri" is just a container.
    - *Approach*: On mobile, we can show "Stiri" as a parent item that toggles its children, keeping the hierarchy consistent with desktop.

#### [MODIFY] [Header.tsx](file:///var/www/deschide_news_app/apps/frontend/app/[locale]/(public)/components/Header.tsx)
- Remove the "Archiva" (Archive) link from both Desktop and Mobile navigation menus.

#### [MODIFY] [types/article.ts](file:///var/www/deschide_news_app/apps/frontend/lib/types/article.ts)
- Update `Category` interface to include `inMenu: boolean` and `inFooterMenu: boolean`.

## Verification Plan

### Automated Tests
- **Backend**: Run `Functionality/CategoryTest.php` (if exists) or create a simple test to verify `inMenu` field persistence.
    - Command: `vendor/bin/phpunit tests/Functionality/CategoriesTest.php` (will verify existence)
- **Frontend**: Run existing tests if any.
    - Command: `pnpm test`

### Manual Verification
1.  **Backend Setup**:
    - Run migration: `symfony console doctrine:migrations:migrate`
    - Update some categories via database or API to set `inMenu = true`.
2.  **Frontend Check**:
    - Open Homepage.
    - Verify categories with `inMenu = true` are visible in the main bar.
    - Verify "Stiri" item exists.
    - Hover/Click "Stiri" and verify other active categories are listed.
    - Verify links work.
    - Check Mobile view for usability.
