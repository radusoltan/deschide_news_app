# Archive Feature - Agent Execution Plan

**Document Version:** 1.0
**Created:** 2025-11-30
**Status:** Ready for Execution
**Total Estimated Time:** 28-35 hours

---

## Executive Summary

This document provides a detailed execution plan for completing the archive functionality in the Deschide News application. The plan is structured into 5 phases with specific tasks assigned to specialized agents.

### Current Implementation Status

| Component | Status | Notes |
|-----------|--------|-------|
| Backend Entity (Article) | 100% | archivedAt, archiveReason fields, archive()/unarchive() methods |
| Backend Enum (ArchiveReason) | 100% | 7 archive reasons defined |
| Backend Migration | 100% | Executed, indexes created |
| ArchivedArticleProvider | 100% | Handles /api/archived_articles |
| ArticleProvider | 100% | Excludes archived articles |
| ImportantArticlesListProvider | 100% | Excludes archived articles |
| ArchiveOldArticlesCommand | 100% | Functional with dry-run, batch processing |
| Elasticsearch Mapping | 100% | Updated for archive fields |
| **ArticleArchiveService** | 0% | **Missing** |
| **ArchiveController** | 0% | **Missing** - public API endpoints |
| **ArticleArchiveController** | 0% | **Missing** - admin endpoints |
| **Cron Job Configuration** | 0% | **Missing** |
| **Frontend Archive Page** | 0% | **Missing** |
| **Frontend Components** | 0% | **Missing** |
| **Admin Archive Management** | 0% | **Missing** |
| **SEO Implementation** | 0% | **Missing** |
| **Tests** | 0% | **Missing** |

---

## Phase 1: Backend API Completion

### BE-01: Create ArticleArchiveService

**Agent Type:** general-purpose
**Estimated Time:** 2-3 hours
**Dependencies:** None
**Priority:** Critical

**Description:**
Create a service class that encapsulates all archive business logic including single article archiving, bulk archiving, unarchiving, and statistics retrieval.

**Files to Create:**
- `/var/www/deschide_news_app/apps/backend/src/Service/ArticleArchiveService.php`

**Acceptance Criteria:**
- [ ] Service class with proper dependency injection
- [ ] `archiveArticle(Article $article, ArchiveReason $reason): void` method
- [ ] `unarchiveArticle(Article $article): void` method
- [ ] `archiveOldArticles(int $yearsOld, int $batchSize): int` method
- [ ] `getArchiveStats(): array` method returning totals, by year, by category
- [ ] `getAvailableYears(): array` method for year filter
- [ ] Proper logging for all operations
- [ ] Transaction handling for bulk operations

**Agent Prompt:**
```
Create the ArticleArchiveService class at /var/www/deschide_news_app/apps/backend/src/Service/ArticleArchiveService.php

Context:
- This is a Symfony 7.3 application with PHP 8.4
- The Article entity already has archive(), unarchive(), isArchived() methods
- The ArchiveReason enum exists at src/Enum/ArchiveReason.php
- The ArticleStatus enum has ARCHIVED case
- Follow the pattern from existing services in src/Service/

Requirements:
1. Inject EntityManagerInterface, ArticleRepository, LoggerInterface
2. Implement archiveArticle() - archive single article with reason
3. Implement unarchiveArticle() - restore article to PUBLISHED
4. Implement archiveOldArticles() - bulk archive with batch processing
5. Implement getArchiveStats() - return statistics array
6. Implement getAvailableYears() - return years with article counts
7. Use transactions for bulk operations
8. Log all archive/unarchive operations

Existing patterns to follow:
- See src/State/ArchivedArticleProvider.php for query patterns
- See src/Command/ArchiveOldArticlesCommand.php for batch processing logic
```

---

### BE-02: Create ArchiveController (Public API)

**Agent Type:** general-purpose
**Estimated Time:** 2 hours
**Dependencies:** BE-01
**Priority:** Critical

**Description:**
Create a controller for public archive API endpoints that allows browsing archived articles with filtering and pagination.

**Files to Create:**
- `/var/www/deschide_news_app/apps/backend/src/Controller/Api/ArchiveController.php`

**Acceptance Criteria:**
- [ ] GET `/api/archive/years` - returns available years with counts
- [ ] GET `/api/archive/stats` - returns archive statistics
- [ ] GET `/api/archive/categories` - returns categories with archived article counts
- [ ] Proper cache headers (1 day client, 1 week CDN)
- [ ] Correct serialization groups
- [ ] CORS-compatible responses

**Agent Prompt:**
```
Create the ArchiveController at /var/www/deschide_news_app/apps/backend/src/Controller/Api/ArchiveController.php

Context:
- This is a Symfony 7.3 API Platform application
- The /api/archived_articles endpoint already exists via ArchivedArticleProvider
- This controller adds supplementary endpoints for archive navigation

Requirements:
1. Route prefix: /api/archive
2. GET /years - return years array: [{year: 2020, count: 12500}, ...]
3. GET /stats - return {total, byYear[], byCategory[]}
4. GET /categories - return categories that have archived articles
5. Inject ArticleArchiveService for data
6. Add cache headers: max-age=86400, s-maxage=604800
7. Use JSON responses with proper content-type

Existing patterns to follow:
- See src/Controller/Api/SlugController.php for controller structure
- Use JsonResponse for all responses
- Apply #[Route] attributes
```

---

### BE-03: Create ArticleArchiveController (Admin API)

**Agent Type:** general-purpose
**Estimated Time:** 2 hours
**Dependencies:** BE-01
**Priority:** Critical

**Description:**
Create an admin controller for managing article archives including archiving, unarchiving, and bulk operations.

**Files to Create:**
- `/var/www/deschide_news_app/apps/backend/src/Controller/Admin/ArticleArchiveController.php`

**Acceptance Criteria:**
- [ ] POST `/api/admin/articles/{id}/archive` - archive single article
- [ ] POST `/api/admin/articles/{id}/unarchive` - restore article
- [ ] POST `/api/admin/articles/archive-bulk` - bulk archive old articles
- [ ] GET `/api/admin/archive/stats` - detailed admin statistics
- [ ] Proper security: `#[IsGranted('ROLE_ADMIN')]` or `#[IsGranted('ROLE_EDITOR')]`
- [ ] Request validation
- [ ] Proper error responses

**Agent Prompt:**
```
Create the ArticleArchiveController at /var/www/deschide_news_app/apps/backend/src/Controller/Admin/ArticleArchiveController.php

Context:
- This is a Symfony 7.3 application with JWT authentication
- Admin routes require ROLE_ADMIN or ROLE_EDITOR
- Uses ArticleArchiveService for business logic

Requirements:
1. Route prefix: /api/admin
2. POST /articles/{id}/archive - body: {reason: "old_content"}
3. POST /articles/{id}/unarchive - no body required
4. POST /articles/archive-bulk - body: {years_old: 4, batch_size: 100}
5. GET /archive/stats - detailed statistics for admin dashboard
6. Apply #[IsGranted('ROLE_ADMIN')] to all routes
7. Validate ArchiveReason enum from request
8. Return proper HTTP status codes (200, 400, 401, 403, 404)

Existing patterns:
- See how security is applied in other admin controllers
- Use ArticleRepository to find articles by ID
- Log admin actions for audit trail
```

---

### BE-04: Configure Cron Job for Automated Archiving

**Agent Type:** general-purpose
**Estimated Time:** 30 minutes
**Dependencies:** BE-01
**Priority:** Medium

**Description:**
Create cron job configuration for automated monthly archiving of old articles.

**Files to Create:**
- `/var/www/deschide_news_app/scripts/cron/archive-old-articles.sh`
- `/var/www/deschide_news_app/docs/CRON_SETUP.md`

**Acceptance Criteria:**
- [ ] Shell script that runs the archive command
- [ ] Script includes logging to /var/log/deschide/archive.log
- [ ] Documentation for cron setup
- [ ] Error handling and notification (optional)

**Agent Prompt:**
```
Create cron job configuration for automated article archiving.

Context:
- Command exists: app:archive-old-articles --years=4
- Backend location: /var/www/deschide_news_app/apps/backend
- Should run monthly on the 1st at 02:00 AM

Create:
1. Shell script at /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh
   - Change to backend directory
   - Run: symfony console app:archive-old-articles --years=4 --no-interaction
   - Log output to /var/log/deschide/archive.log with timestamp
   - Handle errors gracefully

2. Documentation at /var/www/deschide_news_app/docs/CRON_SETUP.md
   - Crontab entry: 0 2 1 * * /path/to/script.sh
   - Log rotation setup
   - Verification steps
```

---

## Phase 2: Frontend Public Pages

### FE-01: Create Archive Page Route

**Agent Type:** general-purpose
**Estimated Time:** 3 hours
**Dependencies:** BE-02
**Priority:** Critical

**Description:**
Create the main archive browsing page with server-side rendering and proper SEO configuration.

**Files to Create:**
- `/var/www/deschide_news_app/apps/frontend/app/[locale]/arhiva/page.tsx`
- `/var/www/deschide_news_app/apps/frontend/app/[locale]/arhiva/layout.tsx`

**Acceptance Criteria:**
- [ ] Server component with async data fetching
- [ ] noindex, follow robots meta tag
- [ ] Proper metadata generation
- [ ] Info banner explaining archive section
- [ ] Responsive layout
- [ ] Loading states

**Agent Prompt:**
```
Create the archive page at /var/www/deschide_news_app/apps/frontend/app/[locale]/arhiva/page.tsx

Context:
- Next.js 16 with App Router
- Locales: ro, en, ru
- API endpoint: GET http://127.0.0.1:8081/api/archived_articles
- Stats endpoint: GET http://127.0.0.1:8081/api/archive/stats

Requirements:
1. Server component that fetches archive stats
2. generateMetadata() function with:
   - robots: { index: false, follow: true }
   - Title: "Arhiva | Deschide News" (localized)
   - Description explaining the archive section
3. Layout with:
   - Info banner (blue) explaining archive content (2016-2020)
   - Link to homepage for recent content
4. Integrate ArchiveBrowser component (created in FE-02)
5. Handle Accept-Language header for API calls

Existing patterns:
- See app/[locale]/layout.tsx for metadata generation
- Use ServerIntlProvider for translations
- Follow styling patterns from existing pages
```

---

### FE-02: Create ArchiveBrowser Component

**Agent Type:** general-purpose
**Estimated Time:** 3-4 hours
**Dependencies:** FE-01
**Priority:** Critical

**Description:**
Create the main archive browsing component with filters, pagination, and article grid.

**Files to Create:**
- `/var/www/deschide_news_app/apps/frontend/components/archive/ArchiveBrowser.tsx`
- `/var/www/deschide_news_app/apps/frontend/components/archive/index.ts`

**Acceptance Criteria:**
- [ ] Client component with state management
- [ ] Sidebar with YearFilter and CategoryFilter
- [ ] Article grid with archive-styled cards
- [ ] Pagination component
- [ ] Loading skeleton states
- [ ] Empty state handling
- [ ] URL query parameter sync for filters
- [ ] Mobile responsive (filters collapse)

**Agent Prompt:**
```
Create the ArchiveBrowser component at /var/www/deschide_news_app/apps/frontend/components/archive/ArchiveBrowser.tsx

Context:
- Client component ('use client')
- Fetches from /api/archived_articles with query params
- Fetches years from /api/archive/years
- Uses Next.js URL search params for filter state

Requirements:
1. Layout: sidebar (filters) + main (articles grid)
2. State: selectedYear, selectedCategory, page, articles, loading
3. Fetch functions:
   - fetchArchivedArticles(page, year?, category?)
   - fetchAvailableYears()
4. URL sync: useSearchParams for filter persistence
5. Responsive: filters in sheet/drawer on mobile
6. Pagination: simple prev/next with page indicator
7. Loading: skeleton cards during fetch

Components to integrate:
- YearFilter (created in FE-03)
- CategoryFilter (created in FE-04)
- ArticleCard with isArchived prop

Styling:
- Use Tailwind CSS
- Follow existing component patterns
- Archive theme: blue accents
```

---

### FE-03: Create YearFilter Component

**Agent Type:** general-purpose
**Estimated Time:** 1 hour
**Dependencies:** None
**Priority:** High

**Description:**
Create a year filter component for the archive sidebar.

**Files to Create:**
- `/var/www/deschide_news_app/apps/frontend/components/archive/YearFilter.tsx`

**Acceptance Criteria:**
- [ ] Displays list of years with article counts
- [ ] "All years" option at top
- [ ] Visual indication of selected year
- [ ] onClick handler to parent component
- [ ] Loading state while fetching years

**Agent Prompt:**
```
Create the YearFilter component at /var/www/deschide_news_app/apps/frontend/components/archive/YearFilter.tsx

Props interface:
- years: Array<{year: number, count: number}>
- selectedYear: number | null
- onYearChange: (year: number | null) => void
- isLoading?: boolean

Requirements:
1. List with "All years" option (null selection)
2. Each year shows: "2019 (15,800)"
3. Selected year has blue background
4. Hover state with subtle highlight
5. Loading skeleton when isLoading=true
6. Sorted by year descending

Styling:
- Tailwind CSS
- Blue color scheme for archive
- Rounded corners, proper spacing
```

---

### FE-04: Create CategoryFilter Component

**Agent Type:** general-purpose
**Estimated Time:** 1 hour
**Dependencies:** None
**Priority:** High

**Description:**
Create a category filter component for the archive sidebar.

**Files to Create:**
- `/var/www/deschide_news_app/apps/frontend/components/archive/CategoryFilter.tsx`

**Acceptance Criteria:**
- [ ] Fetches categories with archived article counts
- [ ] "All categories" option at top
- [ ] Visual indication of selected category
- [ ] onClick handler to parent component
- [ ] Excludes categories with 0 archived articles

**Agent Prompt:**
```
Create the CategoryFilter component at /var/www/deschide_news_app/apps/frontend/components/archive/CategoryFilter.tsx

Props interface:
- selectedCategory: number | null
- onCategoryChange: (categoryId: number | null) => void

Requirements:
1. Fetch categories from /api/archive/categories on mount
2. List with "All categories" option (null selection)
3. Each category shows: "Politica (18,500)"
4. Selected category has blue background
5. Only show categories with archived articles
6. Loading skeleton during fetch

Styling:
- Tailwind CSS
- Match YearFilter styling
- Blue color scheme
```

---

### FE-05: Update ArticleCard for Archive Badge

**Agent Type:** general-purpose
**Estimated Time:** 1 hour
**Dependencies:** None
**Priority:** Medium

**Description:**
Update the existing ArticleCard component to display an archive badge when article is archived.

**Files to Modify:**
- `/var/www/deschide_news_app/apps/frontend/components/articles/ArticleCard.tsx` (if exists)
- OR create `/var/www/deschide_news_app/apps/frontend/components/archive/ArchiveArticleCard.tsx`

**Acceptance Criteria:**
- [ ] isArchived prop added to ArticleCard
- [ ] Blue "Archived" badge displayed when isArchived=true
- [ ] Slightly muted styling for archived cards
- [ ] Archive icon in badge

**Agent Prompt:**
```
Update or create ArticleCard component with archive badge support.

Check if exists: /var/www/deschide_news_app/apps/frontend/components/articles/ArticleCard.tsx

If exists, modify to add:
- isArchived?: boolean prop
- Conditional badge rendering
- Muted styling (opacity-90, blue border)

If not exists, create ArchiveArticleCard at:
/var/www/deschide_news_app/apps/frontend/components/archive/ArchiveArticleCard.tsx

Badge requirements:
- Position: top-right corner
- Style: blue background, white text
- Text: "Arhivat" with archive icon
- Uses Tailwind: bg-blue-100 text-blue-800 rounded-full px-3 py-1
```

---

### FE-06: Add Archive Link to Navigation

**Agent Type:** general-purpose
**Estimated Time:** 30 minutes
**Dependencies:** FE-01
**Priority:** Medium

**Description:**
Add archive link to the site footer and optionally to the main navigation.

**Files to Modify:**
- Find and modify Footer component
- Optionally modify Header/Navigation component

**Acceptance Criteria:**
- [ ] Archive link in footer under "Resources" section
- [ ] Link text: "Arhiva (2016-2020)" with archive icon
- [ ] Link properly localized for all 3 languages
- [ ] Correct href: /{locale}/arhiva

**Agent Prompt:**
```
Add archive navigation links to the application.

First, find the footer component:
- Check /var/www/deschide_news_app/apps/frontend/components/layout/
- Check /var/www/deschide_news_app/apps/frontend/app/components/

Requirements:
1. Add link in footer: "Arhiva (2016-2020)"
2. Icon: archive/folder-archive icon
3. Href: /{locale}/arhiva (use locale from context)
4. Localized text for ro/en/ru

If footer doesn't exist, note this for later implementation.
Also check for main navigation/header to optionally add there.
```

---

## Phase 3: Frontend Admin Pages

### FE-07: Create Admin Archive Management Page

**Agent Type:** general-purpose
**Estimated Time:** 3 hours
**Dependencies:** BE-03, FE-02
**Priority:** High

**Description:**
Create the admin page for managing article archives.

**Files to Create:**
- `/var/www/deschide_news_app/apps/frontend/app/[locale]/admin/archive/page.tsx`
- `/var/www/deschide_news_app/apps/frontend/app/[locale]/admin/archive/layout.tsx`

**Acceptance Criteria:**
- [ ] Protected route (requires authentication)
- [ ] Statistics dashboard section
- [ ] Bulk archive form section
- [ ] Archived articles table with actions
- [ ] Unarchive button per article
- [ ] Confirmation dialogs for destructive actions

**Agent Prompt:**
```
Create admin archive management page at /var/www/deschide_news_app/apps/frontend/app/[locale]/admin/archive/page.tsx

Context:
- Admin section of Next.js 16 app
- Requires JWT authentication
- Uses admin API endpoints from BE-03

Requirements:
1. Page layout with 3 sections:
   - ArchiveStats component (statistics cards)
   - BulkArchiveForm component (archive old articles)
   - ArchivedArticlesList component (table)
2. Protected route - check auth status
3. Handle loading and error states

Integrate components:
- ArchiveStats (FE-08)
- BulkArchiveForm (FE-09)
- ArchivedArticlesList (FE-10)
```

---

### FE-08: Create ArchiveStats Component

**Agent Type:** general-purpose
**Estimated Time:** 1.5 hours
**Dependencies:** BE-03
**Priority:** High

**Description:**
Create statistics dashboard for admin archive page.

**Files to Create:**
- `/var/www/deschide_news_app/apps/frontend/components/admin/archive/ArchiveStats.tsx`

**Acceptance Criteria:**
- [ ] Displays total archived count
- [ ] Displays total published count
- [ ] Shows percentage archived
- [ ] Top 5 years by archive count
- [ ] Top 5 categories by archive count
- [ ] Auto-refresh capability

**Agent Prompt:**
```
Create ArchiveStats component at /var/www/deschide_news_app/apps/frontend/components/admin/archive/ArchiveStats.tsx

Data source: GET /api/admin/archive/stats
Response: {
  totalArchived: number,
  totalPublished: number,
  byYear: [{year, count}],
  byCategory: [{category, count}]
}

Requirements:
1. Stats cards grid (2x2 or responsive)
   - Total Archived (large number, blue)
   - Total Published (large number, green)
   - Percentage (calculated, pie chart optional)
   - Last archive date
2. Charts/lists:
   - Top 5 years list with bars
   - Top 5 categories list with bars
3. Loading skeleton
4. onRefresh prop for parent to trigger update
```

---

### FE-09: Create BulkArchiveForm Component

**Agent Type:** general-purpose
**Estimated Time:** 1.5 hours
**Dependencies:** BE-03
**Priority:** High

**Description:**
Create form for bulk archiving old articles.

**Files to Create:**
- `/var/www/deschide_news_app/apps/frontend/components/admin/archive/BulkArchiveForm.tsx`

**Acceptance Criteria:**
- [ ] Input for years threshold (default: 4)
- [ ] Preview text showing affected date range
- [ ] Confirmation dialog before execution
- [ ] Progress indicator during operation
- [ ] Success/error notifications
- [ ] Triggers refresh of stats after completion

**Agent Prompt:**
```
Create BulkArchiveForm component at /var/www/deschide_news_app/apps/frontend/components/admin/archive/BulkArchiveForm.tsx

Props:
- onComplete: () => void (called after successful archive)
- getAuthToken: () => string (function to get JWT)

API: POST /api/admin/articles/archive-bulk
Body: { years_old: number, batch_size: number }
Response: { message: string, total_archived: number }

Requirements:
1. Form fields:
   - Years threshold: number input (min: 1, max: 10, default: 4)
   - Preview: "Will archive articles published before {date}"
2. Submit button: "Archive Old Articles"
3. Confirmation modal before submit
4. Loading state during API call
5. Success toast with count
6. Error handling with message display
7. Call onComplete() after success
```

---

### FE-10: Create ArchivedArticlesList Component

**Agent Type:** general-purpose
**Estimated Time:** 2 hours
**Dependencies:** BE-02, BE-03
**Priority:** High

**Description:**
Create paginated table of archived articles with unarchive action.

**Files to Create:**
- `/var/www/deschide_news_app/apps/frontend/components/admin/archive/ArchivedArticlesList.tsx`

**Acceptance Criteria:**
- [ ] Table with columns: ID, Title, Category, Published, Archived, Reason, Actions
- [ ] Pagination (20 per page)
- [ ] Unarchive button per row
- [ ] Confirmation dialog for unarchive
- [ ] Success notification after action
- [ ] Link to view article

**Agent Prompt:**
```
Create ArchivedArticlesList component at /var/www/deschide_news_app/apps/frontend/components/admin/archive/ArchivedArticlesList.tsx

Data source: GET /api/archived_articles?page={}&itemsPerPage=20
Action: POST /api/admin/articles/{id}/unarchive

Props:
- getAuthToken: () => string
- onArticleUnarchived: () => void

Requirements:
1. Table columns:
   - ID (number)
   - Title (truncated, link to article)
   - Category (name)
   - Published (date formatted)
   - Archived (date formatted)
   - Reason (translated label)
   - Actions (Unarchive button, View link)
2. Pagination component below table
3. Unarchive action:
   - Confirmation: "Restore this article to published?"
   - API call with auth header
   - Remove row from table on success
   - Toast notification
4. Loading skeleton for table
5. Empty state if no articles
```

---

## Phase 4: SEO Implementation

### SEO-01: Implement noindex for Archived Articles

**Agent Type:** seo-specialist
**Estimated Time:** 2 hours
**Dependencies:** None
**Priority:** High

**Description:**
Update metadata generation to apply noindex for archived articles while maintaining follow.

**Files to Modify:**
- `/var/www/deschide_news_app/apps/frontend/lib/seo/metadata-generator.ts`
- Article detail page (find location)

**Acceptance Criteria:**
- [ ] Archived articles have `robots: { index: false, follow: true }`
- [ ] Published articles maintain `robots: { index: true, follow: true }`
- [ ] Googlebot-specific directives included
- [ ] X-Robots-Tag header consideration

**Agent Prompt:**
```
Update SEO metadata for archived article handling.

Context:
- Metadata generator at /var/www/deschide_news_app/apps/frontend/lib/seo/metadata-generator.ts
- Article has status field: 'published' | 'archived' | 'draft' | etc.
- Current logic in generateArticleMetadata() line 260-270

Requirements:
1. Update robots config in generateArticleMetadata():
   - If status === 'archived': index: false, follow: true
   - If status === 'published': index: true, follow: true
   - Add 'noarchive' for archived articles
2. Update googleBot config similarly
3. Add comment explaining SEO strategy
4. Ensure archived pages maintain link equity with follow: true

Test cases:
- Published article should have index: true
- Archived article should have index: false, follow: true
```

---

### SEO-02: Create Archive Sitemap

**Agent Type:** seo-specialist
**Estimated Time:** 2 hours
**Dependencies:** BE-02
**Priority:** Medium

**Description:**
Create a separate sitemap for archived articles with appropriate priority and changefreq.

**Files to Create:**
- `/var/www/deschide_news_app/apps/frontend/app/sitemap-archive.xml/route.ts`

**Files to Modify:**
- `/var/www/deschide_news_app/apps/frontend/public/robots.txt`
- Main sitemap index (if exists)

**Acceptance Criteria:**
- [ ] Generates XML sitemap for archived articles
- [ ] Priority: 0.3 (lower than main content)
- [ ] Changefreq: yearly (archives rarely change)
- [ ] Includes all locales
- [ ] robots.txt updated with sitemap reference
- [ ] Cache headers for sitemap response

**Agent Prompt:**
```
Create archive sitemap at /var/www/deschide_news_app/apps/frontend/app/sitemap-archive.xml/route.ts

Context:
- Next.js App Router
- API: GET /api/archived_articles (paginated)
- 3 locales: ro, en, ru
- Each article URL: /{locale}/{category-slug}/{article-slug}

Requirements:
1. Route handler that generates XML sitemap
2. Fetch all archived articles (handle pagination)
3. For each article, generate URLs for all locales
4. Sitemap attributes:
   - priority: 0.3
   - changefreq: yearly
   - lastmod: article.updatedAt
5. Response headers:
   - Content-Type: application/xml
   - Cache-Control: public, max-age=86400

Also update:
1. /apps/frontend/public/robots.txt
   - Add: Sitemap: https://deschide.md/sitemap-archive.xml
2. Check for sitemap index and add reference
```

---

### SEO-03: Update Structured Data for Archived Articles

**Agent Type:** seo-specialist
**Estimated Time:** 1 hour
**Dependencies:** SEO-01
**Priority:** Low

**Description:**
Ensure structured data reflects archived status appropriately.

**Files to Modify:**
- `/var/www/deschide_news_app/apps/frontend/components/seo/StructuredData.tsx`
- `/var/www/deschide_news_app/apps/frontend/lib/seo/structured-data.ts`

**Acceptance Criteria:**
- [ ] Add archive date to NewsArticle schema if archived
- [ ] Consider adding 'archived' indicator
- [ ] Maintain all required NewsArticle properties

**Agent Prompt:**
```
Update structured data for archived articles.

Files to check:
- /var/www/deschide_news_app/apps/frontend/components/seo/StructuredData.tsx
- /var/www/deschide_news_app/apps/frontend/lib/seo/structured-data.ts

Requirements:
1. For archived articles, consider adding:
   - "expires" field (optional, Schema.org)
   - Additional context about archive status
2. Ensure dateModified reflects archive date if available
3. Keep all required NewsArticle properties
4. No changes needed for non-archived articles

Note: This is lower priority - core structured data should work as-is.
Check if any adjustments improve search appearance.
```

---

## Phase 5: Testing

### TEST-01: Backend Unit Tests

**Agent Type:** general-purpose
**Estimated Time:** 2 hours
**Dependencies:** BE-01, BE-02, BE-03
**Priority:** High

**Description:**
Create unit tests for ArticleArchiveService.

**Files to Create:**
- `/var/www/deschide_news_app/apps/backend/tests/Unit/Service/ArticleArchiveServiceTest.php`

**Acceptance Criteria:**
- [ ] Test archiveArticle() sets correct status and metadata
- [ ] Test unarchiveArticle() restores to PUBLISHED
- [ ] Test archiveOldArticles() returns correct count
- [ ] Test getArchiveStats() returns expected structure
- [ ] Test edge cases (already archived, etc.)

**Agent Prompt:**
```
Create unit tests at /var/www/deschide_news_app/apps/backend/tests/Unit/Service/ArticleArchiveServiceTest.php

Context:
- PHPUnit tests
- Test ArticleArchiveService in isolation
- Mock EntityManager and Repository

Test cases:
1. testArchiveArticleSetsCorrectStatus
   - Given: PUBLISHED article
   - When: archiveArticle(article, ArchiveReason::OLD_CONTENT)
   - Then: status=ARCHIVED, archivedAt!=null, archiveReason=OLD_CONTENT

2. testArchiveArticleAlreadyArchivedLogsWarning
   - Given: ARCHIVED article
   - When: archiveArticle()
   - Then: No exception, warning logged

3. testUnarchiveArticleRestoresStatus
   - Given: ARCHIVED article
   - When: unarchiveArticle()
   - Then: status=PUBLISHED, archivedAt=null, archiveReason=null

4. testArchiveOldArticlesReturnsCount
   - Given: 10 articles older than threshold
   - When: archiveOldArticles(4, 100)
   - Then: Returns 10

5. testGetArchiveStatsStructure
   - When: getArchiveStats()
   - Then: Returns array with total, byYear, byCategory keys
```

---

### TEST-02: Backend Integration Tests

**Agent Type:** general-purpose
**Estimated Time:** 2 hours
**Dependencies:** BE-01, BE-02, BE-03
**Priority:** High

**Description:**
Create integration tests for archive API endpoints.

**Files to Create:**
- `/var/www/deschide_news_app/apps/backend/tests/Functional/Controller/ArchiveControllerTest.php`
- `/var/www/deschide_news_app/apps/backend/tests/Functional/Controller/ArticleArchiveControllerTest.php`

**Acceptance Criteria:**
- [ ] Test GET /api/archive/years returns correct format
- [ ] Test GET /api/archive/stats returns statistics
- [ ] Test POST /api/admin/articles/{id}/archive requires auth
- [ ] Test POST /api/admin/articles/{id}/archive with valid auth
- [ ] Test POST /api/admin/articles/{id}/unarchive

**Agent Prompt:**
```
Create integration tests for archive controllers.

File 1: /var/www/deschide_news_app/apps/backend/tests/Functional/Controller/ArchiveControllerTest.php

Test cases:
1. testGetArchiveYearsReturnsArray
2. testGetArchiveStatsReturnsJson
3. testGetArchiveCategoriesFiltersCorrectly

File 2: /var/www/deschide_news_app/apps/backend/tests/Functional/Controller/ArticleArchiveControllerTest.php

Test cases:
1. testArchiveArticleRequiresAuthentication (401)
2. testArchiveArticleRequiresAdminRole (403)
3. testArchiveArticleSuccess (200)
4. testArchiveArticleNotFound (404)
5. testUnarchiveArticleSuccess (200)
6. testBulkArchiveSuccess (200)

Use WebTestCase from Symfony, create test fixtures.
```

---

### TEST-03: Frontend E2E Tests

**Agent Type:** general-purpose
**Estimated Time:** 2 hours
**Dependencies:** FE-01 through FE-06
**Priority:** Medium

**Description:**
Create Playwright E2E tests for archive functionality.

**Files to Create:**
- `/var/www/deschide_news_app/apps/frontend/tests/e2e/archive.spec.ts`
- `/var/www/deschide_news_app/apps/frontend/tests/e2e/admin-archive.spec.ts`

**Acceptance Criteria:**
- [ ] Test archive page loads correctly
- [ ] Test year filter changes results
- [ ] Test category filter changes results
- [ ] Test pagination works
- [ ] Test admin archive page (with mock auth)
- [ ] Test unarchive action

**Agent Prompt:**
```
Create Playwright E2E tests for archive functionality.

File 1: /var/www/deschide_news_app/apps/frontend/tests/e2e/archive.spec.ts

Test cases:
1. 'archive page displays correctly'
   - Navigate to /ro/arhiva
   - Check for info banner
   - Check for article list

2. 'year filter works'
   - Select year 2019
   - Verify URL updates
   - Verify results change

3. 'category filter works'
   - Select a category
   - Verify results filtered

4. 'pagination works'
   - Click next page
   - Verify URL updates
   - Verify different articles shown

5. 'archive has noindex meta tag'
   - Check page source for noindex

File 2: /var/www/deschide_news_app/apps/frontend/tests/e2e/admin-archive.spec.ts

Test cases:
1. 'admin archive page requires auth'
2. 'admin can view archive stats'
3. 'admin can unarchive article'
```

---

## Execution Order

### Parallel Execution Groups

**Group 1 (can run in parallel):**
- BE-01: ArticleArchiveService
- FE-03: YearFilter Component
- FE-04: CategoryFilter Component
- FE-05: ArticleCard Archive Badge

**Group 2 (depends on Group 1):**
- BE-02: ArchiveController (depends on BE-01)
- BE-03: ArticleArchiveController (depends on BE-01)

**Group 3 (depends on Group 2):**
- FE-01: Archive Page Route (depends on BE-02)
- FE-02: ArchiveBrowser Component (depends on BE-02, FE-03, FE-04)

**Group 4 (depends on Group 3):**
- FE-06: Navigation Links (depends on FE-01)
- FE-07: Admin Archive Page (depends on BE-03)
- FE-08: ArchiveStats (depends on BE-03)
- FE-09: BulkArchiveForm (depends on BE-03)
- FE-10: ArchivedArticlesList (depends on BE-02, BE-03)

**Group 5 (depends on Group 4):**
- SEO-01: noindex Implementation
- SEO-02: Archive Sitemap
- SEO-03: Structured Data

**Group 6 (final - depends on all above):**
- BE-04: Cron Configuration
- TEST-01: Backend Unit Tests
- TEST-02: Backend Integration Tests
- TEST-03: Frontend E2E Tests

### Critical Path

```
BE-01 -> BE-02 -> FE-01 -> FE-02 -> FE-06 (core public archive)
    \-> BE-03 -> FE-07 -> FE-08/09/10 (admin functionality)
```

---

## Risk Assessment

### High Risk Items

| Risk | Impact | Mitigation |
|------|--------|------------|
| API rate limiting on archive fetches | Slow page loads | Implement caching headers, use incremental static regeneration |
| Large dataset pagination performance | Timeout errors | Use cursor-based pagination, add DB indexes |
| JWT token expiry during bulk operations | Operation fails midway | Implement progress checkpoints, resume capability |

### Medium Risk Items

| Risk | Impact | Mitigation |
|------|--------|------------|
| Missing frontend patterns | Inconsistent UI | Review existing components before creating new ones |
| Locale handling edge cases | Missing translations | Test all 3 locales thoroughly |
| Cache invalidation | Stale data shown | Clear caches after archive operations |

### Low Risk Items

| Risk | Impact | Mitigation |
|------|--------|------------|
| SEO impact of noindex | Reduced indexed pages | This is intentional - archives should not dilute rankings |
| Cron job failures | Old articles not archived | Add monitoring/alerting |

---

## Success Metrics

### Functional Metrics
- [ ] All API endpoints return correct data
- [ ] Archive page loads in under 2 seconds
- [ ] Filter operations complete in under 500ms
- [ ] Admin operations complete successfully with confirmation
- [ ] All tests pass

### SEO Metrics
- [ ] Archived articles have noindex meta tag
- [ ] Archive sitemap generates valid XML
- [ ] robots.txt includes archive sitemap
- [ ] Structured data validates in Google Rich Results Test

### Performance Metrics
- [ ] Database queries for archive list: <100ms
- [ ] API response times: <300ms
- [ ] Frontend LCP: <2.5s
- [ ] Cache hit rate: >90% for archive pages

---

## Git Workflow

### Branch Strategy

```bash
# Main feature branch
git checkout develop
git flow feature start archive-completion

# Sub-branches for parallel work (optional)
git checkout -b archive-completion-backend
git checkout -b archive-completion-frontend
git checkout -b archive-completion-seo
```

### Commit Convention

```
feat(backend): add ArticleArchiveService with stats methods
feat(frontend): create archive page with year/category filters
fix(seo): update robots meta for archived articles
test(backend): add unit tests for ArticleArchiveService
docs: update CLAUDE.md with archive endpoints
```

### Pull Request Template

```markdown
## Archive Feature Completion

### Changes
- [x] BE-01: ArticleArchiveService
- [x] BE-02: ArchiveController
- [x] FE-01: Archive page
- ...

### Testing
- [ ] Unit tests pass
- [ ] Integration tests pass
- [ ] Manual testing completed

### Screenshots
[Add screenshots of archive page, admin page]
```

---

## Appendix A: File Structure After Completion

```
apps/backend/
  src/
    Controller/
      Admin/
        ArticleArchiveController.php  # NEW
      Api/
        ArchiveController.php         # NEW
    Service/
      ArticleArchiveService.php       # NEW
  tests/
    Unit/
      Service/
        ArticleArchiveServiceTest.php # NEW
    Functional/
      Controller/
        ArchiveControllerTest.php     # NEW
        ArticleArchiveControllerTest.php # NEW

apps/frontend/
  app/
    [locale]/
      arhiva/
        page.tsx                      # NEW
        layout.tsx                    # NEW
      admin/
        archive/
          page.tsx                    # NEW
          layout.tsx                  # NEW
    sitemap-archive.xml/
      route.ts                        # NEW
  components/
    archive/
      index.ts                        # NEW
      ArchiveBrowser.tsx              # NEW
      YearFilter.tsx                  # NEW
      CategoryFilter.tsx              # NEW
      ArchiveArticleCard.tsx          # NEW (or modify existing ArticleCard)
    admin/
      archive/
        ArchiveStats.tsx              # NEW
        BulkArchiveForm.tsx           # NEW
        ArchivedArticlesList.tsx      # NEW
  tests/
    e2e/
      archive.spec.ts                 # NEW
      admin-archive.spec.ts           # NEW

scripts/
  cron/
    archive-old-articles.sh           # NEW

docs/
  CRON_SETUP.md                       # NEW
  planning/
    ARCHIVE_EXECUTION_PLAN.md         # THIS FILE
```

---

## Appendix B: API Endpoints Summary

### Public Endpoints (No Auth)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/archived_articles | List archived articles (paginated) |
| GET | /api/archived_articles/{id} | Get single archived article |
| GET | /api/archive/years | Get years with article counts |
| GET | /api/archive/stats | Get archive statistics |
| GET | /api/archive/categories | Get categories with counts |

### Admin Endpoints (Requires ROLE_ADMIN)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/admin/articles/{id}/archive | Archive single article |
| POST | /api/admin/articles/{id}/unarchive | Unarchive article |
| POST | /api/admin/articles/archive-bulk | Bulk archive old articles |
| GET | /api/admin/archive/stats | Detailed admin statistics |

---

**Document Prepared By:** Claude Code Agent
**Last Updated:** 2025-11-30
**Next Review:** After Phase 1 completion
