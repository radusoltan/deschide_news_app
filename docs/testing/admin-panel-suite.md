# Deschide News Admin Panel - Comprehensive Test Suite

**Document Version**: 1.0
**Last Updated**: 2025-11-28
**Application**: Deschide News - Multilingual News Platform
**Testing Tool**: Playwright MCP (Browser Automation)

---

## Table of Contents

1. [Overview](#overview)
2. [Test Environment Setup](#test-environment-setup)
3. [Phase 1: Authentication & Access Control](#phase-1-authentication--access-control)
4. [Phase 2: Articles CRUD Operations](#phase-2-articles-crud-operations)
5. [Phase 3: Categories Management](#phase-3-categories-management)
6. [Phase 4: Authors Management](#phase-4-authors-management)
7. [Phase 5: Media/Images Management](#phase-5-mediaimages-management)
8. [Phase 6: Important Articles (Featured)](#phase-6-important-articles-featured)
9. [Phase 7: Multilingual Content Testing](#phase-7-multilingual-content-testing)
10. [Phase 8: Edge Cases & Error Handling](#phase-8-edge-cases--error-handling)
11. [Phase 9: Performance & UX](#phase-9-performance--ux)
12. [Phase 10: Integration & End-to-End Flows](#phase-10-integration--end-to-end-flows)
13. [Test Data Requirements](#test-data-requirements)
14. [Appendix: Playwright MCP Commands](#appendix-playwright-mcp-commands)

---

## Overview

### Application Architecture

| Component | Technology | URL |
|-----------|------------|-----|
| Frontend | Next.js 16 (React 19.2 / TypeScript) | http://localhost:3005 |
| Backend API | Symfony 7.3 (PHP 8.4) with API Platform | http://127.0.0.1:8081 |
| Admin Panel | Next.js App Router | http://localhost:3005/[locale]/admin |
| CDN (Images) | Static server | http://127.0.0.1:8082 |

### Supported Locales

- **Romanian (ro)** - Default locale
- **English (en)**
- **Russian (ru)**

### Admin Panel Routes

| Route | Description |
|-------|-------------|
| `/[locale]/admin` | Admin dashboard |
| `/[locale]/admin/articles` | Articles list |
| `/[locale]/admin/articles/new` | Create article |
| `/[locale]/admin/articles/[id]/edit` | Edit article |
| `/[locale]/admin/categories` | Categories list |
| `/[locale]/admin/categories/new` | Create category |
| `/[locale]/admin/categories/[id]/edit` | Edit category |
| `/[locale]/admin/authors` | Authors list |
| `/[locale]/admin/authors/new` | Create author |
| `/[locale]/admin/authors/[id]/edit` | Edit author |
| `/[locale]/admin/images` | Media library |
| `/[locale]/admin/important-articles` | Featured articles management |

---

## Test Environment Setup

### Prerequisites

1. **Backend API Running**
   ```bash
   cd /var/www/deschide_news_app/apps/backend
   symfony serve -d --port=8081
   ```

2. **Frontend Running**
   ```bash
   cd /var/www/deschide_news_app/apps/frontend
   pnpm dev
   ```

3. **Database Seeded**
   - Minimum test data required (see Test Data Requirements section)
   - At least one admin user account

4. **Test User Credentials**
   - Admin user: `admin@deschide.md` / `TestPassword123!`
   - Editor user: `editor@deschide.md` / `TestPassword123!`
   - Viewer user: `viewer@deschide.md` / `TestPassword123!`

### Playwright MCP Configuration

```javascript
// playwright.config.ts
export default {
  baseURL: 'http://localhost:3005',
  use: {
    locale: 'ro',
    timezoneId: 'Europe/Chisinau',
    viewport: { width: 1920, height: 1080 },
  },
  projects: [
    { name: 'chromium', use: { browserName: 'chromium' } },
    { name: 'firefox', use: { browserName: 'firefox' } },
    { name: 'webkit', use: { browserName: 'webkit' } },
  ],
};
```

---

## Phase 1: Authentication & Access Control

### Objective
Verify that authentication flows work correctly and unauthorized access is properly blocked.

### Prerequisites
- Valid admin user exists in database
- JWT authentication configured in backend
- Session cookies properly set up

### Test Scenarios

#### AUTH-001: Successful Admin Login
| Field | Value |
|-------|-------|
| **ID** | AUTH-001 |
| **Name** | Successful Admin Login |
| **Priority** | Critical |
| **Description** | Verify that an admin user can successfully log in to the admin panel |

**Steps:**
1. Navigate to `http://localhost:3005/ro/admin`
2. System redirects to login page (if not authenticated)
3. Enter valid admin email: `admin@deschide.md`
4. Enter valid password: `TestPassword123!`
5. Click "Login" button
6. Wait for redirect

**Expected Results:**
- User is redirected to admin dashboard
- Session cookie is set
- User name/avatar displayed in header
- No error messages shown

---

#### AUTH-002: Login with Invalid Credentials
| Field | Value |
|-------|-------|
| **ID** | AUTH-002 |
| **Name** | Login with Invalid Credentials |
| **Priority** | Critical |
| **Description** | Verify that login fails with incorrect password |

**Steps:**
1. Navigate to login page
2. Enter valid email: `admin@deschide.md`
3. Enter invalid password: `WrongPassword123`
4. Click "Login" button

**Expected Results:**
- Error message displayed: "Invalid credentials" or similar
- User remains on login page
- No session cookie set
- Password field cleared

---

#### AUTH-003: Login with Non-existent User
| Field | Value |
|-------|-------|
| **ID** | AUTH-003 |
| **Name** | Login with Non-existent User |
| **Priority** | High |
| **Description** | Verify that login fails for non-existent email |

**Steps:**
1. Navigate to login page
2. Enter non-existent email: `nonexistent@deschide.md`
3. Enter any password
4. Click "Login" button

**Expected Results:**
- Error message displayed (generic, not revealing user existence)
- User remains on login page

---

#### AUTH-004: Successful Logout
| Field | Value |
|-------|-------|
| **ID** | AUTH-004 |
| **Name** | Successful Logout |
| **Priority** | Critical |
| **Description** | Verify that user can successfully log out |

**Steps:**
1. Login as admin user
2. Navigate to admin dashboard
3. Click user menu/avatar
4. Click "Logout" button

**Expected Results:**
- Session cookie deleted
- User redirected to login page or home page
- Attempting to access admin routes redirects to login

---

#### AUTH-005: Protected Route Access Without Authentication
| Field | Value |
|-------|-------|
| **ID** | AUTH-005 |
| **Name** | Protected Route Access Without Auth |
| **Priority** | Critical |
| **Description** | Verify unauthenticated users cannot access admin routes |

**Steps:**
1. Clear all cookies/session
2. Navigate directly to `http://localhost:3005/ro/admin/articles`

**Expected Results:**
- User redirected to login page
- Original URL preserved for redirect after login
- No admin content visible

---

#### AUTH-006: Session Expiration Handling
| Field | Value |
|-------|-------|
| **ID** | AUTH-006 |
| **Name** | Session Expiration Handling |
| **Priority** | High |
| **Description** | Verify that expired sessions are handled gracefully |

**Steps:**
1. Login as admin user
2. Manually expire/delete session cookie
3. Attempt to perform an action (e.g., load articles list)

**Expected Results:**
- User redirected to login page
- Appropriate message shown (session expired)
- No error page displayed

---

#### AUTH-007: JWT Token Refresh
| Field | Value |
|-------|-------|
| **ID** | AUTH-007 |
| **Name** | JWT Token Refresh |
| **Priority** | High |
| **Description** | Verify that access token is refreshed before expiration |

**Steps:**
1. Login as admin user
2. Wait for access token to near expiration (or simulate)
3. Perform an API action

**Expected Results:**
- Token refreshed automatically using refresh token
- Action completes successfully
- No logout or error

---

#### AUTH-008: Role-Based Access Control
| Field | Value |
|-------|-------|
| **ID** | AUTH-008 |
| **Name** | Role-Based Access Control |
| **Priority** | High |
| **Description** | Verify that different roles have appropriate access levels |

**Steps:**
1. Login as viewer role user
2. Navigate to articles management
3. Attempt to create/edit/delete article

**Expected Results:**
- Read operations allowed
- Write operations blocked with appropriate message
- UI elements hidden or disabled for unauthorized actions

---

#### AUTH-009: Login Form Validation
| Field | Value |
|-------|-------|
| **ID** | AUTH-009 |
| **Name** | Login Form Validation |
| **Priority** | Medium |
| **Description** | Verify client-side validation on login form |

**Steps:**
1. Navigate to login page
2. Leave email field empty, click Login
3. Enter invalid email format, click Login
4. Leave password field empty, click Login

**Expected Results:**
- Appropriate validation messages displayed
- Form not submitted until valid
- Email format validated

---

#### AUTH-010: Remember Me Functionality
| Field | Value |
|-------|-------|
| **ID** | AUTH-010 |
| **Name** | Remember Me Functionality |
| **Priority** | Low |
| **Description** | Verify "Remember Me" extends session duration |

**Steps:**
1. Navigate to login page
2. Enter valid credentials
3. Check "Remember Me" checkbox
4. Click Login
5. Close browser, reopen after some time
6. Navigate to admin

**Expected Results:**
- Session persists for extended period (30 days)
- User remains authenticated

---

### Phase 1 Success Criteria
- [ ] All Critical priority tests pass
- [ ] All High priority tests pass
- [ ] 80%+ of Medium/Low priority tests pass
- [ ] No security vulnerabilities detected
- [ ] Session management works correctly across all browsers

---

## Phase 2: Articles CRUD Operations

### Objective
Verify complete Create, Read, Update, Delete functionality for articles.

### Prerequisites
- Authenticated as admin user
- At least 5 existing articles in database
- At least 3 categories exist
- At least 2 authors exist

### Test Scenarios

#### ART-001: View Articles List
| Field | Value |
|-------|-------|
| **ID** | ART-001 |
| **Name** | View Articles List |
| **Priority** | Critical |
| **Description** | Verify articles list displays correctly with all columns |

**Steps:**
1. Login as admin
2. Navigate to `/ro/admin/articles`
3. Observe the articles table

**Expected Results:**
- Table displays with columns: Title, Status, Category, Author, Published, Views, Actions
- Articles loaded from API
- Pagination controls visible (if more than page size)
- Search and filter controls visible
- "Create Article" button visible

---

#### ART-002: Search Articles by Title
| Field | Value |
|-------|-------|
| **ID** | ART-002 |
| **Name** | Search Articles by Title |
| **Priority** | High |
| **Description** | Verify search functionality filters articles by title |

**Steps:**
1. Navigate to articles list
2. Enter search term in search box (e.g., "breaking")
3. Observe results

**Expected Results:**
- Table filters to show only matching articles
- Results count updated
- "Clear filters" option appears
- Case-insensitive matching

---

#### ART-003: Filter Articles by Status
| Field | Value |
|-------|-------|
| **ID** | ART-003 |
| **Name** | Filter Articles by Status |
| **Priority** | High |
| **Description** | Verify status filter works correctly |

**Steps:**
1. Navigate to articles list
2. Select "Published" from status dropdown
3. Observe results
4. Change to "New" status
5. Clear filter

**Expected Results:**
- Only articles with selected status shown
- Status badge matches filter
- Results count accurate
- Filter clearing works

---

#### ART-004: Filter Articles by Category
| Field | Value |
|-------|-------|
| **ID** | ART-004 |
| **Name** | Filter Articles by Category |
| **Priority** | High |
| **Description** | Verify category filter works correctly |

**Steps:**
1. Navigate to articles list
2. Select a category from dropdown
3. Observe results
4. Select "No Category" option

**Expected Results:**
- Only articles in selected category shown
- "No Category" filter shows uncategorized articles
- Can combine with other filters

---

#### ART-005: Create New Article - Minimum Required Fields
| Field | Value |
|-------|-------|
| **ID** | ART-005 |
| **Name** | Create Article - Minimum Fields |
| **Priority** | Critical |
| **Description** | Verify article creation with only required fields |

**Steps:**
1. Navigate to articles list
2. Click "Create Article" button
3. Enter title: "Test Article Title"
4. Enter slug: "test-article-title"
5. Enter content: "This is the article content."
6. Select at least one author
7. Click "Create Article" button

**Expected Results:**
- Article created successfully
- Redirect to articles list
- New article appears in list
- Success message displayed
- Status defaults to "new"

---

#### ART-006: Create New Article - All Fields
| Field | Value |
|-------|-------|
| **ID** | ART-006 |
| **Name** | Create Article - All Fields |
| **Priority** | Critical |
| **Description** | Verify article creation with all fields populated |

**Steps:**
1. Navigate to create article form
2. Fill all fields:
   - Title: "Complete Test Article"
   - Slug: "complete-test-article"
   - Lead: "This is the article lead paragraph."
   - Content: "Full article content with formatting."
   - Excerpt: "Short excerpt for listings."
   - Category: Select a category
   - Authors: Select multiple authors (up to 5)
   - Status: "published"
   - Publish At: Future date/time
3. Click "Create Article"

**Expected Results:**
- Article created with all fields
- All data saved correctly
- Redirect to articles list
- Article searchable/filterable

---

#### ART-007: Create Article - Slug Auto-generation
| Field | Value |
|-------|-------|
| **ID** | ART-007 |
| **Name** | Create Article - Slug Generation |
| **Priority** | High |
| **Description** | Verify slug is auto-generated from title |

**Steps:**
1. Navigate to create article form
2. Enter title: "Test Article with Special Characters & Spaces!"
3. Click "Generate slug" button (or observe auto-generation)

**Expected Results:**
- Slug generated: "test-article-with-special-characters-spaces"
- Special characters removed
- Spaces converted to hyphens
- Lowercase

---

#### ART-008: Create Article - Validation Errors
| Field | Value |
|-------|-------|
| **ID** | ART-008 |
| **Name** | Create Article - Validation Errors |
| **Priority** | Critical |
| **Description** | Verify form validation prevents invalid submission |

**Steps:**
1. Navigate to create article form
2. Leave title empty, try to submit
3. Leave slug empty, try to submit
4. Leave content empty, try to submit
5. Don't select any author, try to submit

**Expected Results:**
- Validation errors displayed for each required field
- Form not submitted
- Error messages clear and helpful:
  - "Title is required"
  - "Slug is required"
  - "Content is required"
  - "At least one author is required"

---

#### ART-009: Create Article - Duplicate Slug Prevention
| Field | Value |
|-------|-------|
| **ID** | ART-009 |
| **Name** | Create Article - Duplicate Slug |
| **Priority** | High |
| **Description** | Verify duplicate slugs are rejected |

**Steps:**
1. Create article with slug "unique-test-slug"
2. Try to create another article with same slug

**Expected Results:**
- Error message: "This slug is already in use"
- Article not created
- User prompted to change slug

---

#### ART-010: Create Article - Maximum Authors Validation
| Field | Value |
|-------|-------|
| **ID** | ART-010 |
| **Name** | Create Article - Max Authors |
| **Priority** | Medium |
| **Description** | Verify maximum 5 authors limit is enforced |

**Steps:**
1. Navigate to create article form
2. Try to add more than 5 authors

**Expected Results:**
- Error message: "Maximum 5 authors allowed"
- Sixth author cannot be added
- Form prevents invalid state

---

#### ART-011: Edit Article - Load Existing Data
| Field | Value |
|-------|-------|
| **ID** | ART-011 |
| **Name** | Edit Article - Load Data |
| **Priority** | Critical |
| **Description** | Verify edit form loads existing article data |

**Steps:**
1. Navigate to articles list
2. Click "Edit" on an existing article
3. Observe form fields

**Expected Results:**
- All existing data loaded into form fields
- Title, slug, lead, content populated
- Category selected
- Authors selected
- Status set correctly
- All fields editable

---

#### ART-012: Edit Article - Update Title
| Field | Value |
|-------|-------|
| **ID** | ART-012 |
| **Name** | Edit Article - Update Title |
| **Priority** | Critical |
| **Description** | Verify article title can be updated |

**Steps:**
1. Navigate to edit form for existing article
2. Change title to "Updated Article Title"
3. Click "Update Article" button

**Expected Results:**
- Article updated successfully
- New title saved
- Redirect to articles list
- Updated title visible in list

---

#### ART-013: Edit Article - Change Status
| Field | Value |
|-------|-------|
| **ID** | ART-013 |
| **Name** | Edit Article - Change Status |
| **Priority** | High |
| **Description** | Verify article status can be changed |

**Steps:**
1. Find article with status "new"
2. Edit article
3. Change status to "published"
4. Save

**Expected Results:**
- Status updated
- publishedAt timestamp set automatically
- Status badge updates in list

---

#### ART-014: Edit Article - Change Category
| Field | Value |
|-------|-------|
| **ID** | ART-014 |
| **Name** | Edit Article - Change Category |
| **Priority** | High |
| **Description** | Verify article category can be changed |

**Steps:**
1. Edit existing article
2. Change category selection
3. Save

**Expected Results:**
- Category updated
- Article now appears under new category filter
- Old category no longer shows article

---

#### ART-015: Delete Article - Confirmation Modal
| Field | Value |
|-------|-------|
| **ID** | ART-015 |
| **Name** | Delete Article - Confirmation |
| **Priority** | Critical |
| **Description** | Verify delete confirmation modal appears |

**Steps:**
1. Navigate to articles list
2. Click "Delete" button on an article

**Expected Results:**
- Modal appears with warning
- Article title displayed for confirmation
- Cancel and Delete buttons present
- Article ID shown

---

#### ART-016: Delete Article - Cancel Deletion
| Field | Value |
|-------|-------|
| **ID** | ART-016 |
| **Name** | Delete Article - Cancel |
| **Priority** | High |
| **Description** | Verify canceling deletion preserves article |

**Steps:**
1. Click delete on an article
2. Click "Cancel" in modal

**Expected Results:**
- Modal closes
- Article still exists
- No changes made

---

#### ART-017: Delete Article - Confirm Deletion
| Field | Value |
|-------|-------|
| **ID** | ART-017 |
| **Name** | Delete Article - Confirm |
| **Priority** | Critical |
| **Description** | Verify confirmed deletion removes article |

**Steps:**
1. Note article title and ID
2. Click delete on article
3. Click "Delete Article" in modal

**Expected Results:**
- Article removed from database
- Article no longer in list
- Success message shown
- Cannot find article by ID

---

#### ART-018: Article Lock Indicator
| Field | Value |
|-------|-------|
| **ID** | ART-018 |
| **Name** | Article Lock Indicator |
| **Priority** | Medium |
| **Description** | Verify locked articles show lock indicator |

**Steps:**
1. Have another user edit an article (creating lock)
2. View articles list

**Expected Results:**
- Lock icon visible on locked article
- Tooltip shows who is editing
- Lock info updates periodically (30 seconds)

---

#### ART-019: Articles Pagination
| Field | Value |
|-------|-------|
| **ID** | ART-019 |
| **Name** | Articles Pagination |
| **Priority** | High |
| **Description** | Verify pagination works correctly |

**Steps:**
1. Ensure more than 30 articles exist
2. Navigate to articles list
3. Check pagination controls
4. Navigate to page 2

**Expected Results:**
- Pagination shows total count
- Page navigation works
- Different articles on each page
- Current page highlighted

---

#### ART-020: Articles Table Sorting
| Field | Value |
|-------|-------|
| **ID** | ART-020 |
| **Name** | Articles Table Sorting |
| **Priority** | Medium |
| **Description** | Verify table columns can be sorted |

**Steps:**
1. Navigate to articles list
2. Click column headers to sort

**Expected Results:**
- Articles sorted by clicked column
- Sort direction indicator visible
- Can toggle ascending/descending

---

### Phase 2 Success Criteria
- [ ] All Critical priority tests pass
- [ ] All High priority tests pass
- [ ] 80%+ of Medium/Low priority tests pass
- [ ] CRUD operations complete without errors
- [ ] Data integrity maintained across operations

---

## Phase 3: Categories Management

### Objective
Verify complete CRUD functionality for categories.

### Prerequisites
- Authenticated as admin user
- At least 3 existing categories
- Some categories linked to articles

### Test Scenarios

#### CAT-001: View Categories List
| Field | Value |
|-------|-------|
| **ID** | CAT-001 |
| **Name** | View Categories List |
| **Priority** | Critical |
| **Description** | Verify categories list displays correctly |

**Steps:**
1. Login as admin
2. Navigate to `/ro/admin/categories`

**Expected Results:**
- Table shows: Title, Slug, Status, On Front Page, Created, Actions
- All categories loaded
- Checkboxes for bulk selection (if implemented)
- Create button visible

---

#### CAT-002: Create Category - Required Fields
| Field | Value |
|-------|-------|
| **ID** | CAT-002 |
| **Name** | Create Category - Required Fields |
| **Priority** | Critical |
| **Description** | Verify category creation with required fields |

**Steps:**
1. Click "Create Category"
2. Enter title: "Test Category"
3. Enter or generate slug
4. Select status: "active"
5. Click "Create Category"

**Expected Results:**
- Category created successfully
- Redirect to categories list
- New category visible
- Default: onFrontPage = false

---

#### CAT-003: Create Category - Slug Generation
| Field | Value |
|-------|-------|
| **ID** | CAT-003 |
| **Name** | Create Category - Slug Generation |
| **Priority** | High |
| **Description** | Verify slug auto-generation from title |

**Steps:**
1. Navigate to create category form
2. Enter title with Romanian characters: "Politica si Afaceri"
3. Click "Generate from title" button

**Expected Results:**
- Slug generated: "politica-si-afaceri"
- Romanian diacritics handled correctly
- Special characters removed

---

#### CAT-004: Create Category - Validation
| Field | Value |
|-------|-------|
| **ID** | CAT-004 |
| **Name** | Create Category - Validation |
| **Priority** | Critical |
| **Description** | Verify form validation |

**Steps:**
1. Try to submit with empty title
2. Try to submit with empty slug

**Expected Results:**
- Error: "Title is required"
- Error: "Slug is required"
- Form not submitted

---

#### CAT-005: Create Category - Duplicate Slug
| Field | Value |
|-------|-------|
| **ID** | CAT-005 |
| **Name** | Create Category - Duplicate Slug |
| **Priority** | High |
| **Description** | Verify duplicate slug prevention |

**Steps:**
1. Create category with slug "unique-cat-slug"
2. Try to create another with same slug

**Expected Results:**
- Error message about duplicate slug
- Second category not created

---

#### CAT-006: Create Category - On Front Page Toggle
| Field | Value |
|-------|-------|
| **ID** | CAT-006 |
| **Name** | Create Category - Front Page |
| **Priority** | Medium |
| **Description** | Verify front page toggle works |

**Steps:**
1. Create category with "Display on front page" checked
2. Verify in list

**Expected Results:**
- Category shows "Yes" in On Front Page column
- Badge indicator visible

---

#### CAT-007: Edit Category - Load Data
| Field | Value |
|-------|-------|
| **ID** | CAT-007 |
| **Name** | Edit Category - Load Data |
| **Priority** | Critical |
| **Description** | Verify edit form loads existing data |

**Steps:**
1. Click "Edit" on existing category
2. Observe form fields

**Expected Results:**
- All fields populated correctly
- Title, slug, status, onFrontPage loaded
- Form ready for editing

---

#### CAT-008: Edit Category - Update Title
| Field | Value |
|-------|-------|
| **ID** | CAT-008 |
| **Name** | Edit Category - Update Title |
| **Priority** | Critical |
| **Description** | Verify title update |

**Steps:**
1. Edit existing category
2. Change title
3. Save

**Expected Results:**
- Title updated
- Slug preserved (unless manually changed)
- Redirect to list

---

#### CAT-009: Edit Category - Change Status
| Field | Value |
|-------|-------|
| **ID** | CAT-009 |
| **Name** | Edit Category - Change Status |
| **Priority** | High |
| **Description** | Verify status change |

**Steps:**
1. Edit category with status "active"
2. Change to "inactive"
3. Save

**Expected Results:**
- Status updated
- Badge changes in list
- Inactive categories may be hidden from public

---

#### CAT-010: Delete Category - Confirmation
| Field | Value |
|-------|-------|
| **ID** | CAT-010 |
| **Name** | Delete Category - Confirmation |
| **Priority** | Critical |
| **Description** | Verify delete confirmation modal |

**Steps:**
1. Click "Delete" on a category

**Expected Results:**
- Modal appears with warning
- Category title shown
- Cancel and Delete buttons present

---

#### CAT-011: Delete Category - With Articles
| Field | Value |
|-------|-------|
| **ID** | CAT-011 |
| **Name** | Delete Category - With Articles |
| **Priority** | High |
| **Description** | Verify behavior when deleting category with articles |

**Steps:**
1. Create category and assign articles to it
2. Try to delete the category

**Expected Results:**
- Either: Category deleted, articles become uncategorized
- Or: Error message preventing deletion
- (Depends on business rules)

---

#### CAT-012: Delete Category - Empty Category
| Field | Value |
|-------|-------|
| **ID** | CAT-012 |
| **Name** | Delete Category - Empty |
| **Priority** | High |
| **Description** | Verify deleting category without articles |

**Steps:**
1. Create new category (no articles)
2. Delete the category

**Expected Results:**
- Category deleted successfully
- No longer in list
- No orphaned data

---

### Phase 3 Success Criteria
- [ ] All Critical priority tests pass
- [ ] All High priority tests pass
- [ ] Categories can be fully managed
- [ ] Slug uniqueness enforced
- [ ] Status changes work correctly

---

## Phase 4: Authors Management

### Objective
Verify complete CRUD functionality for authors.

### Prerequisites
- Authenticated as admin user
- At least 3 existing authors
- Some authors linked to articles

### Test Scenarios

#### AUT-001: View Authors List
| Field | Value |
|-------|-------|
| **ID** | AUT-001 |
| **Name** | View Authors List |
| **Priority** | Critical |
| **Description** | Verify authors list displays correctly |

**Steps:**
1. Navigate to `/ro/admin/authors`

**Expected Results:**
- Table shows: Name, Email, Status, Active, Articles Count, Actions
- All authors loaded
- Create button visible

---

#### AUT-002: Create Author - Required Fields
| Field | Value |
|-------|-------|
| **ID** | AUT-002 |
| **Name** | Create Author - Required Fields |
| **Priority** | Critical |
| **Description** | Verify author creation with required fields |

**Steps:**
1. Click "Create Author"
2. Enter first name: "John"
3. Enter last name: "Doe"
4. Enter email: "john.doe@example.com"
5. Save

**Expected Results:**
- Author created
- Slug auto-generated: "john-doe"
- Status defaults to "active"
- isActive defaults to true

---

#### AUT-003: Create Author - All Fields
| Field | Value |
|-------|-------|
| **ID** | AUT-003 |
| **Name** | Create Author - All Fields |
| **Priority** | High |
| **Description** | Verify author creation with all fields |

**Steps:**
1. Create author with:
   - First/Last name
   - Email
   - Bio (translatable)
   - Twitter handle
   - Facebook URL
   - LinkedIn URL
   - Website URL
2. Save

**Expected Results:**
- All fields saved correctly
- URLs validated
- Social links stored

---

#### AUT-004: Create Author - Email Validation
| Field | Value |
|-------|-------|
| **ID** | AUT-004 |
| **Name** | Create Author - Email Validation |
| **Priority** | High |
| **Description** | Verify email validation |

**Steps:**
1. Try to create author with invalid email: "not-an-email"
2. Try with duplicate email

**Expected Results:**
- Invalid email rejected
- Duplicate email rejected
- Clear error messages

---

#### AUT-005: Create Author - URL Validation
| Field | Value |
|-------|-------|
| **ID** | AUT-005 |
| **Name** | Create Author - URL Validation |
| **Priority** | Medium |
| **Description** | Verify social media URL validation |

**Steps:**
1. Enter invalid URL for Facebook: "not-a-url"
2. Enter valid URL: "https://facebook.com/author"

**Expected Results:**
- Invalid URLs rejected
- Valid URLs accepted
- Proper validation messages

---

#### AUT-006: Edit Author - Load Data
| Field | Value |
|-------|-------|
| **ID** | AUT-006 |
| **Name** | Edit Author - Load Data |
| **Priority** | Critical |
| **Description** | Verify edit form loads author data |

**Steps:**
1. Click "Edit" on existing author

**Expected Results:**
- All fields populated
- Bio loaded (in current locale)
- Social links visible

---

#### AUT-007: Edit Author - Update Name
| Field | Value |
|-------|-------|
| **ID** | AUT-007 |
| **Name** | Edit Author - Update Name |
| **Priority** | High |
| **Description** | Verify name update and slug regeneration |

**Steps:**
1. Edit author
2. Change first name
3. Save

**Expected Results:**
- Name updated
- Slug may be regenerated (updatable: true)
- FullName computed property updated

---

#### AUT-008: Edit Author - Toggle Active
| Field | Value |
|-------|-------|
| **ID** | AUT-008 |
| **Name** | Edit Author - Toggle Active |
| **Priority** | High |
| **Description** | Verify active status toggle |

**Steps:**
1. Edit active author
2. Uncheck "Active" toggle
3. Save

**Expected Results:**
- isActive set to false
- Status indicator updated
- Author may be hidden from selections

---

#### AUT-009: Delete Author - Without Articles
| Field | Value |
|-------|-------|
| **ID** | AUT-009 |
| **Name** | Delete Author - Without Articles |
| **Priority** | High |
| **Description** | Verify deleting author without articles |

**Steps:**
1. Create new author (no articles)
2. Delete author

**Expected Results:**
- Author deleted
- No longer in list

---

#### AUT-010: Delete Author - With Articles
| Field | Value |
|-------|-------|
| **ID** | AUT-010 |
| **Name** | Delete Author - With Articles |
| **Priority** | High |
| **Description** | Verify behavior when deleting author with articles |

**Steps:**
1. Find author with articles
2. Try to delete

**Expected Results:**
- Either: Author deleted, articles updated
- Or: Error preventing deletion
- (Depends on business rules)

---

### Phase 4 Success Criteria
- [ ] All Critical priority tests pass
- [ ] All High priority tests pass
- [ ] Authors can be fully managed
- [ ] Email uniqueness enforced
- [ ] Social links validated

---

## Phase 5: Media/Images Management

### Objective
Verify image upload, display, and management functionality.

### Prerequisites
- Authenticated as admin user
- CDN server running on port 8082
- Sample images for testing

### Test Scenarios

#### IMG-001: View Images List
| Field | Value |
|-------|-------|
| **ID** | IMG-001 |
| **Name** | View Images List |
| **Priority** | Critical |
| **Description** | Verify image library displays correctly |

**Steps:**
1. Navigate to `/ro/admin/images`

**Expected Results:**
- Grid or list of images shown
- Thumbnails visible
- Metadata visible (filename, size, date)
- Upload button present

---

#### IMG-002: Upload Single Image
| Field | Value |
|-------|-------|
| **ID** | IMG-002 |
| **Name** | Upload Single Image |
| **Priority** | Critical |
| **Description** | Verify single image upload |

**Steps:**
1. Click "Upload" button
2. Select image file (JPG, 1MB)
3. Wait for upload

**Expected Results:**
- Upload progress shown
- Image appears in library
- Thumbnail generated
- Metadata stored (width, height, mimeType)

---

#### IMG-003: Upload Multiple Images
| Field | Value |
|-------|-------|
| **ID** | IMG-003 |
| **Name** | Upload Multiple Images |
| **Priority** | High |
| **Description** | Verify multiple image upload |

**Steps:**
1. Click upload
2. Select 5 images
3. Wait for all uploads

**Expected Results:**
- All images uploaded
- Progress for each
- All appear in library

---

#### IMG-004: Upload - File Type Validation
| Field | Value |
|-------|-------|
| **ID** | IMG-004 |
| **Name** | Upload - File Type Validation |
| **Priority** | High |
| **Description** | Verify only allowed file types accepted |

**Steps:**
1. Try to upload .txt file
2. Try to upload .exe file
3. Upload valid .png file

**Expected Results:**
- Invalid files rejected with message
- Only images (jpg, png, gif, webp) accepted

---

#### IMG-005: Upload - File Size Limit
| Field | Value |
|-------|-------|
| **ID** | IMG-005 |
| **Name** | Upload - File Size Limit |
| **Priority** | High |
| **Description** | Verify file size limits |

**Steps:**
1. Try to upload image > 10MB

**Expected Results:**
- Upload rejected
- Error message about size limit

---

#### IMG-006: View Image Details
| Field | Value |
|-------|-------|
| **ID** | IMG-006 |
| **Name** | View Image Details |
| **Priority** | Medium |
| **Description** | Verify image detail view |

**Steps:**
1. Click on image in library

**Expected Results:**
- Full size image displayed
- Metadata shown:
  - Original filename
  - File size
  - Dimensions
  - Upload date
  - Alt text field

---

#### IMG-007: Edit Image Metadata
| Field | Value |
|-------|-------|
| **ID** | IMG-007 |
| **Name** | Edit Image Metadata |
| **Priority** | Medium |
| **Description** | Verify image metadata editing |

**Steps:**
1. Open image details
2. Update alt text: "New alt text description"
3. Save

**Expected Results:**
- Alt text updated
- Changes persisted
- Used when image embedded in articles

---

#### IMG-008: Delete Image
| Field | Value |
|-------|-------|
| **ID** | IMG-008 |
| **Name** | Delete Image |
| **Priority** | High |
| **Description** | Verify image deletion |

**Steps:**
1. Select image not used in articles
2. Click delete
3. Confirm

**Expected Results:**
- Image removed from library
- File deleted from storage
- Thumbnails deleted

---

#### IMG-009: Delete Image - In Use
| Field | Value |
|-------|-------|
| **ID** | IMG-009 |
| **Name** | Delete Image - In Use |
| **Priority** | High |
| **Description** | Verify behavior when deleting used image |

**Steps:**
1. Find image attached to article
2. Try to delete

**Expected Results:**
- Warning about usage
- Either: Force delete option
- Or: Deletion prevented

---

#### IMG-010: Thumbnail Profiles Verification
| Field | Value |
|-------|-------|
| **ID** | IMG-010 |
| **Name** | Thumbnail Profiles |
| **Priority** | Medium |
| **Description** | Verify all thumbnail profiles generated |

**Steps:**
1. Upload new image
2. Wait for thumbnail generation
3. Check thumbnail availability

**Expected Results:**
- 10 thumbnail profiles generated:
  - hero_big (1920x1080)
  - hero_small (800x600)
  - article_main (1600x900)
  - article_inline (1200x675)
  - card_large (800x600)
  - card_medium (600x400)
  - card_small (400x300)
  - list_item (300x200)
  - mobile_hero (800x600)
  - gallery (1920x600)
- All in WebP format

---

### Phase 5 Success Criteria
- [ ] All Critical priority tests pass
- [ ] All High priority tests pass
- [ ] Images upload correctly
- [ ] Thumbnails generated
- [ ] CDN serves images correctly

---

## Phase 6: Important Articles (Featured)

### Objective
Verify featured articles management functionality.

### Prerequisites
- Authenticated as admin user
- At least 10 published articles exist

### Test Scenarios

#### FEAT-001: View Featured Articles List
| Field | Value |
|-------|-------|
| **ID** | FEAT-001 |
| **Name** | View Featured Articles |
| **Priority** | Critical |
| **Description** | Verify featured articles list displays |

**Steps:**
1. Navigate to `/ro/admin/important-articles`

**Expected Results:**
- Current featured articles shown
- Position/order visible
- Add/Remove controls present
- Reorder controls available

---

#### FEAT-002: Add Article to Featured
| Field | Value |
|-------|-------|
| **ID** | FEAT-002 |
| **Name** | Add to Featured |
| **Priority** | Critical |
| **Description** | Verify adding article to featured list |

**Steps:**
1. Navigate to featured articles
2. Click "Add Article"
3. Select article from list
4. Confirm

**Expected Results:**
- Article added to featured list
- Appears at end of list
- Position assigned

---

#### FEAT-003: Remove Article from Featured
| Field | Value |
|-------|-------|
| **ID** | FEAT-003 |
| **Name** | Remove from Featured |
| **Priority** | Critical |
| **Description** | Verify removing article from featured |

**Steps:**
1. Click "Remove" on featured article

**Expected Results:**
- Article removed from featured list
- Other positions updated
- Article still exists in system

---

#### FEAT-004: Reorder Featured Articles
| Field | Value |
|-------|-------|
| **ID** | FEAT-004 |
| **Name** | Reorder Featured |
| **Priority** | High |
| **Description** | Verify drag-drop reordering |

**Steps:**
1. Drag article from position 3 to position 1
2. Save order

**Expected Results:**
- Order updated
- Positions recalculated
- Front page reflects new order

---

#### FEAT-005: Featured Articles Limit
| Field | Value |
|-------|-------|
| **ID** | FEAT-005 |
| **Name** | Featured Limit |
| **Priority** | Medium |
| **Description** | Verify maximum featured articles limit |

**Steps:**
1. Try to add articles beyond limit

**Expected Results:**
- Either: Oldest removed automatically
- Or: Error message about limit
- (Depends on business rules)

---

#### FEAT-006: Featured - Unpublished Articles
| Field | Value |
|-------|-------|
| **ID** | FEAT-006 |
| **Name** | Featured - Unpublished |
| **Priority** | High |
| **Description** | Verify behavior with unpublished articles |

**Steps:**
1. Add published article to featured
2. Change article status to draft

**Expected Results:**
- Article hidden from public featured
- Warning in admin about unpublished featured

---

### Phase 6 Success Criteria
- [ ] All Critical priority tests pass
- [ ] Featured list fully manageable
- [ ] Reordering works correctly
- [ ] Changes reflected on front page

---

## Phase 7: Multilingual Content Testing

### Objective
Verify multilingual functionality across all content types.

### Prerequisites
- Authenticated as admin user
- All three locales enabled (ro, en, ru)
- Translatable content exists

### Test Scenarios

#### ML-001: Switch Admin Interface Language
| Field | Value |
|-------|-------|
| **ID** | ML-001 |
| **Name** | Switch Interface Language |
| **Priority** | Critical |
| **Description** | Verify admin interface language switching |

**Steps:**
1. Navigate to admin in Romanian: `/ro/admin`
2. Switch to English: `/en/admin`
3. Switch to Russian: `/ru/admin`

**Expected Results:**
- UI labels change with locale
- URLs update correctly
- Content remains accessible

---

#### ML-002: Create Article - Romanian
| Field | Value |
|-------|-------|
| **ID** | ML-002 |
| **Name** | Create Article - Romanian |
| **Priority** | Critical |
| **Description** | Verify article creation in Romanian |

**Steps:**
1. Navigate to `/ro/admin/articles/new`
2. Create article with Romanian content:
   - Title: "Titlul articolului in romana"
   - Content with diacritics

**Expected Results:**
- Article saved with Romanian translations
- Diacritics preserved: a, i, s, t
- Slug generated correctly

---

#### ML-003: Create Article - English Translation
| Field | Value |
|-------|-------|
| **ID** | ML-003 |
| **Name** | Create Article - English |
| **Priority** | Critical |
| **Description** | Verify English translation creation |

**Steps:**
1. Switch to English locale
2. Edit article created in Romanian
3. Enter English content

**Expected Results:**
- English translation saved
- Romanian content preserved
- Both versions accessible

---

#### ML-004: Create Article - Russian Translation
| Field | Value |
|-------|-------|
| **ID** | ML-004 |
| **Name** | Create Article - Russian |
| **Priority** | High |
| **Description** | Verify Russian translation with Cyrillic |

**Steps:**
1. Switch to Russian locale
2. Edit article
3. Enter Cyrillic content

**Expected Results:**
- Cyrillic characters saved correctly
- Encoding preserved
- Content displays properly

---

#### ML-005: View Article in Different Locales
| Field | Value |
|-------|-------|
| **ID** | ML-005 |
| **Name** | View Translations |
| **Priority** | Critical |
| **Description** | Verify viewing article in different locales |

**Steps:**
1. View article list in Romanian
2. Switch to English
3. Switch to Russian

**Expected Results:**
- Translated titles shown per locale
- Fallback to default if translation missing
- Correct content per locale

---

#### ML-006: Category Translation
| Field | Value |
|-------|-------|
| **ID** | ML-006 |
| **Name** | Category Translation |
| **Priority** | High |
| **Description** | Verify category translations |

**Steps:**
1. Create category in Romanian
2. Switch to English, edit category
3. Enter English title

**Expected Results:**
- Category has translations
- Correct title per locale
- Articles display correct category name

---

#### ML-007: Author Bio Translation
| Field | Value |
|-------|-------|
| **ID** | ML-007 |
| **Name** | Author Bio Translation |
| **Priority** | Medium |
| **Description** | Verify author bio translations |

**Steps:**
1. Edit author in Romanian
2. Enter Romanian bio
3. Switch to English, enter English bio

**Expected Results:**
- Bio translations saved
- Displayed per locale
- Names not translated (same across locales)

---

#### ML-008: API Language Header
| Field | Value |
|-------|-------|
| **ID** | ML-008 |
| **Name** | API Language Header |
| **Priority** | High |
| **Description** | Verify API respects Accept-Language header |

**Steps:**
1. Call API with `Accept-Language: ro`
2. Call same endpoint with `Accept-Language: en`

**Expected Results:**
- Different translations returned
- Correct content per header
- Fallback for missing translations

---

#### ML-009: Missing Translation Fallback
| Field | Value |
|-------|-------|
| **ID** | ML-009 |
| **Name** | Missing Translation Fallback |
| **Priority** | High |
| **Description** | Verify fallback for missing translations |

**Steps:**
1. Create article only in Romanian
2. View in English locale

**Expected Results:**
- Falls back to Romanian content
- No errors or blank content
- Indicator that translation missing (optional)

---

#### ML-010: Slug Transliteration - Romanian
| Field | Value |
|-------|-------|
| **ID** | ML-010 |
| **Name** | Slug Transliteration |
| **Priority** | High |
| **Description** | Verify Romanian diacritics in slug generation |

**Steps:**
1. Create article with title: "Afaceri si Economie"
2. Generate slug

**Expected Results:**
- Slug: "afaceri-si-economie"
- Diacritics converted correctly
- URL-safe characters only

---

### Phase 7 Success Criteria
- [ ] All Critical priority tests pass
- [ ] All locales functional
- [ ] Translations saved correctly
- [ ] Fallback works properly
- [ ] Cyrillic characters handled

---

## Phase 8: Edge Cases & Error Handling

### Objective
Verify system behavior in edge cases and error scenarios.

### Prerequisites
- Authenticated as admin user
- Various test data states

### Test Scenarios

#### EDGE-001: Very Long Title
| Field | Value |
|-------|-------|
| **ID** | EDGE-001 |
| **Name** | Very Long Title |
| **Priority** | Medium |
| **Description** | Verify handling of maximum length title |

**Steps:**
1. Create article with 255 character title
2. Try to exceed 255 characters

**Expected Results:**
- 255 characters accepted
- Beyond 255 rejected or truncated
- No database errors

---

#### EDGE-002: Special Characters in Content
| Field | Value |
|-------|-------|
| **ID** | EDGE-002 |
| **Name** | Special Characters |
| **Priority** | Medium |
| **Description** | Verify special characters handled |

**Steps:**
1. Create article with content containing:
   - HTML tags
   - Script tags
   - Unicode characters
   - Emojis

**Expected Results:**
- XSS prevented (script tags sanitized)
- Valid HTML preserved in WYSIWYG
- Unicode and emojis displayed correctly

---

#### EDGE-003: Empty Content Handling
| Field | Value |
|-------|-------|
| **ID** | EDGE-003 |
| **Name** | Empty Content |
| **Priority** | Medium |
| **Description** | Verify empty optional fields |

**Steps:**
1. Create article with only required fields
2. Leave lead, excerpt empty

**Expected Results:**
- Article created
- Empty fields saved as null
- No display errors

---

#### EDGE-004: Network Error During Save
| Field | Value |
|-------|-------|
| **ID** | EDGE-004 |
| **Name** | Network Error |
| **Priority** | High |
| **Description** | Verify handling of network failures |

**Steps:**
1. Start creating article
2. Simulate network disconnect
3. Try to save

**Expected Results:**
- Error message displayed
- Form data preserved
- Retry option available
- No partial save

---

#### EDGE-005: API Server Error (500)
| Field | Value |
|-------|-------|
| **ID** | EDGE-005 |
| **Name** | API Server Error |
| **Priority** | High |
| **Description** | Verify handling of server errors |

**Steps:**
1. Trigger server error condition
2. Observe frontend behavior

**Expected Results:**
- User-friendly error message
- No technical details exposed
- Ability to retry

---

#### EDGE-006: Concurrent Editing Conflict
| Field | Value |
|-------|-------|
| **ID** | EDGE-006 |
| **Name** | Concurrent Editing |
| **Priority** | High |
| **Description** | Verify concurrent edit handling |

**Steps:**
1. Open article in two browser tabs
2. Edit in tab 1, save
3. Edit in tab 2, save

**Expected Results:**
- Either: Lock prevents second edit
- Or: Conflict resolution shown
- No silent data loss

---

#### EDGE-007: Browser Back Button
| Field | Value |
|-------|-------|
| **ID** | EDGE-007 |
| **Name** | Browser Back Button |
| **Priority** | Medium |
| **Description** | Verify back button handling |

**Steps:**
1. Start creating article
2. Enter data in form
3. Click browser back button

**Expected Results:**
- Warning about unsaved changes
- Option to stay or leave
- Data preserved if staying

---

#### EDGE-008: Session Timeout During Edit
| Field | Value |
|-------|-------|
| **ID** | EDGE-008 |
| **Name** | Session Timeout |
| **Priority** | High |
| **Description** | Verify session timeout handling |

**Steps:**
1. Start editing article
2. Wait for session to expire
3. Try to save

**Expected Results:**
- Redirect to login
- Data preserved in draft (if implemented)
- Return to edit after re-login

---

#### EDGE-009: Large Image Upload
| Field | Value |
|-------|-------|
| **ID** | EDGE-009 |
| **Name** | Large Image Upload |
| **Priority** | Medium |
| **Description** | Verify handling of large images |

**Steps:**
1. Upload 10MB image
2. Observe behavior

**Expected Results:**
- Either: Accepted with processing
- Or: Clear size limit error
- No timeout or crash

---

#### EDGE-010: Invalid URL Parameters
| Field | Value |
|-------|-------|
| **ID** | EDGE-010 |
| **Name** | Invalid URL Parameters |
| **Priority** | Medium |
| **Description** | Verify handling of invalid URLs |

**Steps:**
1. Navigate to `/ro/admin/articles/invalid-id/edit`
2. Navigate to `/ro/admin/articles/-1/edit`

**Expected Results:**
- 404 or error page shown
- No server crash
- User can navigate back

---

### Phase 8 Success Criteria
- [ ] All High priority tests pass
- [ ] No system crashes
- [ ] Graceful error handling
- [ ] User data protected

---

## Phase 9: Performance & UX

### Objective
Verify performance and user experience quality.

### Prerequisites
- Authenticated as admin user
- Large dataset (100+ articles)

### Test Scenarios

#### PERF-001: Articles List Load Time
| Field | Value |
|-------|-------|
| **ID** | PERF-001 |
| **Name** | Articles List Load Time |
| **Priority** | High |
| **Description** | Verify acceptable load time |

**Steps:**
1. Navigate to articles list with 100+ articles
2. Measure load time

**Expected Results:**
- Initial render < 3 seconds
- Data populated < 5 seconds
- No loading spinner stuck

---

#### PERF-002: Search Response Time
| Field | Value |
|-------|-------|
| **ID** | PERF-002 |
| **Name** | Search Response Time |
| **Priority** | High |
| **Description** | Verify search is responsive |

**Steps:**
1. Type in search box
2. Observe filtering speed

**Expected Results:**
- Filtering < 500ms
- No input lag
- Real-time results (debounced)

---

#### PERF-003: Form Submission Speed
| Field | Value |
|-------|-------|
| **ID** | PERF-003 |
| **Name** | Form Submission Speed |
| **Priority** | High |
| **Description** | Verify form submission speed |

**Steps:**
1. Fill article form
2. Click save
3. Measure time to completion

**Expected Results:**
- Submission < 3 seconds
- Loading indicator shown
- Redirect happens promptly

---

#### PERF-004: Image Upload Progress
| Field | Value |
|-------|-------|
| **ID** | PERF-004 |
| **Name** | Image Upload Progress |
| **Priority** | Medium |
| **Description** | Verify upload feedback |

**Steps:**
1. Upload large image
2. Observe progress indicator

**Expected Results:**
- Progress bar/percentage shown
- Accurate progress
- Cancel option available

---

#### PERF-005: Dark Mode Support
| Field | Value |
|-------|-------|
| **ID** | PERF-005 |
| **Name** | Dark Mode Support |
| **Priority** | Low |
| **Description** | Verify dark mode functionality |

**Steps:**
1. Toggle dark mode (if available)
2. Navigate through admin

**Expected Results:**
- All elements properly styled
- No contrast issues
- Consistent theming

---

#### PERF-006: Mobile Responsiveness
| Field | Value |
|-------|-------|
| **ID** | PERF-006 |
| **Name** | Mobile Responsiveness |
| **Priority** | Medium |
| **Description** | Verify mobile layout |

**Steps:**
1. Access admin on mobile viewport (375px width)
2. Navigate through sections

**Expected Results:**
- Navigation accessible (hamburger menu)
- Forms usable
- Tables scroll horizontally
- No overflow issues

---

#### PERF-007: Tablet Responsiveness
| Field | Value |
|-------|-------|
| **ID** | PERF-007 |
| **Name** | Tablet Responsiveness |
| **Priority** | Medium |
| **Description** | Verify tablet layout |

**Steps:**
1. Access admin on tablet viewport (768px)
2. Navigate through sections

**Expected Results:**
- Layout adapts appropriately
- Sidebar may collapse
- All functionality accessible

---

#### PERF-008: Keyboard Navigation
| Field | Value |
|-------|-------|
| **ID** | PERF-008 |
| **Name** | Keyboard Navigation |
| **Priority** | Medium |
| **Description** | Verify keyboard accessibility |

**Steps:**
1. Navigate using Tab key
2. Activate elements with Enter/Space
3. Use Escape to close modals

**Expected Results:**
- All interactive elements focusable
- Focus visible and logical order
- Modals trap focus appropriately

---

#### PERF-009: Loading States
| Field | Value |
|-------|-------|
| **ID** | PERF-009 |
| **Name** | Loading States |
| **Priority** | Medium |
| **Description** | Verify loading indicators |

**Steps:**
1. Observe page loads
2. Check form submissions
3. Check data fetching

**Expected Results:**
- Spinners/skeletons shown while loading
- Buttons disabled during submission
- No layout shifts

---

#### PERF-010: Error State Recovery
| Field | Value |
|-------|-------|
| **ID** | PERF-010 |
| **Name** | Error State Recovery |
| **Priority** | High |
| **Description** | Verify recovery from errors |

**Steps:**
1. Trigger an error
2. Dismiss error
3. Try operation again

**Expected Results:**
- Error can be dismissed
- Form state preserved
- Retry works correctly

---

### Phase 9 Success Criteria
- [ ] All High priority tests pass
- [ ] Load times acceptable
- [ ] Mobile experience functional
- [ ] Keyboard navigation works

---

## Phase 10: Integration & End-to-End Flows

### Objective
Verify complete user workflows from start to finish.

### Prerequisites
- Clean test environment
- All services running

### Test Scenarios

#### E2E-001: Complete Article Creation Flow
| Field | Value |
|-------|-------|
| **ID** | E2E-001 |
| **Name** | Complete Article Creation |
| **Priority** | Critical |
| **Description** | Full article creation workflow |

**Steps:**
1. Login as admin
2. Create new category: "Test Category"
3. Create new author: "Test Author"
4. Create new article:
   - Title: "Complete Test Article"
   - Content: Full content
   - Category: "Test Category"
   - Author: "Test Author"
   - Status: Published
5. Add to featured articles
6. Verify on front page

**Expected Results:**
- All entities created
- Relationships correct
- Article visible on front page
- Featured section updated

---

#### E2E-002: Article Translation Workflow
| Field | Value |
|-------|-------|
| **ID** | E2E-002 |
| **Name** | Article Translation Workflow |
| **Priority** | Critical |
| **Description** | Complete translation workflow |

**Steps:**
1. Create article in Romanian
2. Switch to English, add translation
3. Switch to Russian, add translation
4. Verify all three versions via API

**Expected Results:**
- Three language versions exist
- Each has unique content
- API returns correct version per locale

---

#### E2E-003: Article with Images Workflow
| Field | Value |
|-------|-------|
| **ID** | E2E-003 |
| **Name** | Article with Images |
| **Priority** | High |
| **Description** | Article with featured image |

**Steps:**
1. Upload image to media library
2. Create new article
3. Attach image as featured
4. Publish article
5. View on front page

**Expected Results:**
- Image uploaded and thumbnails generated
- Image attached to article
- Correct thumbnail displayed on front page
- Full image in article detail

---

#### E2E-004: Category with Articles Lifecycle
| Field | Value |
|-------|-------|
| **ID** | E2E-004 |
| **Name** | Category Lifecycle |
| **Priority** | High |
| **Description** | Category from creation to deletion |

**Steps:**
1. Create category
2. Create 3 articles in category
3. View category filter
4. Change category to inactive
5. Observe article visibility
6. Reactivate category

**Expected Results:**
- Category created
- Articles properly categorized
- Filter shows correct articles
- Inactive category behavior correct

---

#### E2E-005: Author with Multiple Articles
| Field | Value |
|-------|-------|
| **ID** | E2E-005 |
| **Name** | Author with Articles |
| **Priority** | High |
| **Description** | Author page and article count |

**Steps:**
1. Create author
2. Create 5 articles by this author
3. View author detail
4. Check article count

**Expected Results:**
- Author created
- Articles linked correctly
- Article count accurate
- Author bio accessible

---

#### E2E-006: Bulk Operations Workflow
| Field | Value |
|-------|-------|
| **ID** | E2E-006 |
| **Name** | Bulk Operations |
| **Priority** | Medium |
| **Description** | Bulk article operations |

**Steps:**
1. Select multiple articles (if supported)
2. Apply bulk status change
3. Apply bulk delete

**Expected Results:**
- Multiple selection works
- Bulk operations apply to all
- Confirmation for destructive actions

---

#### E2E-007: Search and Filter Combined
| Field | Value |
|-------|-------|
| **ID** | E2E-007 |
| **Name** | Combined Search and Filter |
| **Priority** | High |
| **Description** | Use search and filters together |

**Steps:**
1. Navigate to articles
2. Enter search term
3. Select category filter
4. Select status filter
5. Clear all filters

**Expected Results:**
- Filters combine correctly (AND logic)
- Results accurate
- Clear filters resets all

---

#### E2E-008: Error Recovery Flow
| Field | Value |
|-------|-------|
| **ID** | E2E-008 |
| **Name** | Error Recovery |
| **Priority** | High |
| **Description** | Recover from failed operation |

**Steps:**
1. Start creating article
2. Simulate network failure during save
3. Restore network
4. Retry save

**Expected Results:**
- Error displayed
- Form data preserved
- Retry succeeds
- Article created

---

#### E2E-009: Multi-tab Workflow
| Field | Value |
|-------|-------|
| **ID** | E2E-009 |
| **Name** | Multi-tab Workflow |
| **Priority** | Medium |
| **Description** | Working in multiple tabs |

**Steps:**
1. Open admin in two tabs
2. Create article in tab 1
3. Refresh list in tab 2
4. Edit same article in both

**Expected Results:**
- New article visible after refresh
- Lock prevents concurrent edit
- Data consistency maintained

---

#### E2E-010: Full Day Admin Workflow
| Field | Value |
|-------|-------|
| **ID** | E2E-010 |
| **Name** | Full Day Workflow |
| **Priority** | Low |
| **Description** | Simulate full day of admin work |

**Steps:**
1. Login
2. Create 10 articles
3. Edit 5 articles
4. Create 2 categories
5. Create 2 authors
6. Upload 5 images
7. Manage featured
8. Switch locales multiple times
9. Logout

**Expected Results:**
- All operations complete
- No memory leaks
- Performance stable
- Session maintained

---

### Phase 10 Success Criteria
- [ ] All Critical priority tests pass
- [ ] Complete workflows functional
- [ ] Data integrity maintained
- [ ] No regression issues

---

## Test Data Requirements

### Minimum Test Data

| Entity | Count | Notes |
|--------|-------|-------|
| Users | 3 | Admin, Editor, Viewer roles |
| Categories | 5 | Mix of active/inactive |
| Authors | 5 | Mix of active/inactive |
| Articles | 50 | Various statuses |
| Images | 10 | Different sizes |
| Featured Articles | 5 | For homepage |

### Test User Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@deschide.md | TestPassword123! |
| Editor | editor@deschide.md | TestPassword123! |
| Viewer | viewer@deschide.md | TestPassword123! |

### Database Seed Commands

```bash
# Backend - Load fixtures
cd /var/www/deschide_news_app/apps/backend
symfony console doctrine:fixtures:load --append

# Or use sample import
symfony console app:sample-import
```

---

## Appendix: Playwright MCP Commands

### Navigation Commands

```typescript
// Navigate to page
await mcp.browser.navigate('http://localhost:3005/ro/admin');

// Wait for element
await mcp.browser.waitForSelector('[data-testid="articles-table"]');

// Take screenshot
await mcp.browser.screenshot({ path: 'test-result.png' });
```

### Interaction Commands

```typescript
// Click element
await mcp.browser.click('button[type="submit"]');

// Fill input
await mcp.browser.fill('input[name="title"]', 'Test Article');

// Select dropdown
await mcp.browser.selectOption('select[name="status"]', 'published');

// Check checkbox
await mcp.browser.check('input[name="onFrontPage"]');
```

### Assertion Commands

```typescript
// Check element visible
await mcp.browser.isVisible('[data-testid="success-message"]');

// Get text content
const text = await mcp.browser.textContent('h1');

// Check URL
const url = await mcp.browser.url();
expect(url).toContain('/admin/articles');
```

### Authentication Helper

```typescript
async function loginAsAdmin() {
  await mcp.browser.navigate('http://localhost:3005/ro/login');
  await mcp.browser.fill('input[name="email"]', 'admin@deschide.md');
  await mcp.browser.fill('input[name="password"]', 'TestPassword123!');
  await mcp.browser.click('button[type="submit"]');
  await mcp.browser.waitForNavigation();
}
```

---

## Test Execution Summary Template

```markdown
## Test Execution Report

**Date**: YYYY-MM-DD
**Environment**: Development/Staging/Production
**Tester**: Name
**Browser**: Chrome/Firefox/Safari

### Results Summary

| Phase | Total | Passed | Failed | Blocked | Skipped |
|-------|-------|--------|--------|---------|---------|
| Phase 1 | 10 | | | | |
| Phase 2 | 20 | | | | |
| Phase 3 | 12 | | | | |
| Phase 4 | 10 | | | | |
| Phase 5 | 10 | | | | |
| Phase 6 | 6 | | | | |
| Phase 7 | 10 | | | | |
| Phase 8 | 10 | | | | |
| Phase 9 | 10 | | | | |
| Phase 10 | 10 | | | | |
| **Total** | 108 | | | | |

### Critical Issues Found

1. Issue description
   - Steps to reproduce
   - Expected vs Actual
   - Severity

### Notes

Additional observations and recommendations.
```

---

**Document Maintained By**: QA Team
**Review Cycle**: Monthly
**Last Review**: 2025-11-28
