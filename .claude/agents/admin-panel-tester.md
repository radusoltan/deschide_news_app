---
name: admin-panel-tester
description: |
  Specialized agent for Deschide News multilingual news portal.

Examples:
- "@admin-panel-tester [task description]"
tools:
  - Read
  - mcp__playwright__browser_navigate
  - mcp__playwright__browser_snapshot
  - mcp__playwright__browser_click
  - mcp__playwright__browser_type
  - mcp__playwright__browser_fill_form
model: claude-3-5-sonnet-20241022
permissionMode: default
color: green
---

# Admin Panel Tester Agent

**Scope**: Admin interface (http://localhost:3005/{locale}/admin)

## Agent Description

This agent validates all administrative functions including:
- Admin authentication and authorization
- Article management (CRUD operations)
- Category management
- Author management
- Image upload and management
- User management
- Important articles management
- Content preview
- Real-time article locking

## Testing Scope

### 1. **Authentication and Authorization**

#### Login Flow

**URL**: `http://localhost:3005/ro/admin/login`

**Test Cases:**
- ✅ Login page displays correctly
- ✅ Login form has username and password fields
- ✅ Login with valid credentials succeeds
- ✅ Login with invalid credentials shows error
- ✅ Login with empty fields shows validation errors
- ✅ JWT token is stored after successful login
- ✅ User is redirected to dashboard after login
- ✅ Remember me checkbox works (if implemented)
- ✅ Forgot password link is present (if implemented)

#### Protected Routes

**Test Cases:**
- ✅ Accessing `/admin` without auth redirects to login
- ✅ Accessing `/admin/articles` without auth redirects to login
- ✅ Valid JWT token allows access to admin routes
- ✅ Expired JWT token redirects to login
- ✅ Invalid JWT token redirects to login
- ✅ Token refresh works automatically (if implemented)

#### Logout Flow

**Test Cases:**
- ✅ Logout button is visible in admin interface
- ✅ Clicking logout clears JWT token
- ✅ Logout redirects to login page
- ✅ Cannot access admin routes after logout
- ✅ Logout works across all tabs (if implemented)

### 2. **Admin Dashboard**

**URL**: `http://localhost:3005/ro/admin`

**Test Cases:**
- ✅ Dashboard displays after successful login
- ✅ Admin navigation menu is visible
- ✅ Statistics/widgets display (if implemented)
- ✅ Quick actions are available
- ✅ Recent articles list is shown
- ✅ User profile is displayed
- ✅ Responsive layout works on mobile

### 3. **Article Management**

#### Article List Page

**URL**: `http://localhost:3005/ro/admin/articles`

**Test Cases:**
- ✅ Article list displays all articles
- ✅ Pagination works correctly
- ✅ Filter by status (published, draft, scheduled)
- ✅ Filter by category
- ✅ Filter by author
- ✅ Search articles by title
- ✅ Sort by date, views, etc.
- ✅ Bulk actions available (delete, publish, etc.)
- ✅ Table columns are configurable
- ✅ Article status badges display correctly
- ✅ Quick edit button works
- ✅ View article button opens article page

#### Create Article Page

**URL**: `http://localhost:3005/ro/admin/articles/create`

**Test Cases:**
- ✅ Create article form displays
- ✅ All required fields are marked
- ✅ Title field accepts input
- ✅ Content editor (TinyMCE/TipTap) loads correctly
- ✅ Category dropdown populated with categories
- ✅ Author dropdown populated with authors
- ✅ Status dropdown has correct options
- ✅ Featured checkbox works
- ✅ Badge dropdown has correct options
- ✅ Publish date picker works
- ✅ Slug auto-generates from title
- ✅ Slug can be manually edited
- ✅ Image upload works
- ✅ Image can be set as featured
- ✅ Image can be repositioned
- ✅ Multiple images can be uploaded
- ✅ SEO fields are available (meta title, description)
- ✅ Validation errors display correctly
- ✅ Save as draft works
- ✅ Publish immediately works
- ✅ Schedule publish works
- ✅ Preview article works
- ✅ Cancel returns to article list

#### Edit Article Page

**URL**: `http://localhost:3005/ro/admin/articles/{id}/edit`

**Test Cases:**
- ✅ Edit form pre-populates with article data
- ✅ Title field shows existing title
- ✅ Content editor shows existing content
- ✅ Selected category is highlighted
- ✅ Selected author is highlighted
- ✅ Current status is selected
- ✅ Existing images display
- ✅ Can add new images
- ✅ Can remove existing images
- ✅ Can reorder images
- ✅ Changes can be saved
- ✅ Article lock indicator shows if locked by another user
- ✅ Cannot edit if locked by another user
- ✅ Lock is released on save or cancel
- ✅ Lock timeout works (expires after 10 minutes)
- ✅ Unsaved changes warning shows on navigation
- ✅ Revision history available (if implemented)

#### Delete Article

**Test Cases:**
- ✅ Delete button shows on article list
- ✅ Delete button shows on edit page
- ✅ Confirmation dialog shows before delete
- ✅ Cancel keeps article
- ✅ Confirm deletes article
- ✅ Cannot delete published articles (should unpublish first)
- ✅ Deleted article removed from list
- ✅ Success message shows after delete

#### Article Translation Management

**Test Cases:**
- ✅ Translation tabs show for each locale (ro, en, ru)
- ✅ Can switch between translation tabs
- ✅ Each tab shows/edits respective translation
- ✅ Saving one translation doesn't affect others
- ✅ Required fields validated per translation
- ✅ Slug is locale-specific
- ✅ Preview shows correct translation
- ✅ Can delete translation (except default)

### 4. **Category Management**

#### Category List Page

**URL**: `http://localhost:3005/ro/admin/categories`

**Test Cases:**
- ✅ Category list displays all categories
- ✅ Category tree/hierarchy displays (if nested)
- ✅ Article count per category shows
- ✅ Edit button works
- ✅ Delete button works
- ✅ Cannot delete category with articles
- ✅ Create new category button works

#### Create/Edit Category

**URL**: `http://localhost:3005/ro/admin/categories/create`

**Test Cases:**
- ✅ Category form displays
- ✅ Name field accepts input
- ✅ Description field accepts input
- ✅ Slug auto-generates from name
- ✅ Parent category dropdown (if nested)
- ✅ Order/position field
- ✅ Active/inactive toggle
- ✅ Translation tabs work
- ✅ Save creates/updates category
- ✅ Validation errors display

### 5. **Image Management**

#### Image Library

**URL**: `http://localhost:3005/ro/admin/images`

**Test Cases:**
- ✅ Image library displays uploaded images
- ✅ Grid view available
- ✅ List view available
- ✅ Filter by date
- ✅ Search images by filename or alt text
- ✅ Pagination works
- ✅ Image details show (dimensions, size, format)
- ✅ Click image shows full preview
- ✅ Delete image works
- ✅ Bulk delete works
- ✅ Cannot delete image used in articles

#### Image Upload

**Test Cases:**
- ✅ Upload button opens file picker
- ✅ Drag and drop works
- ✅ Multiple files can be selected
- ✅ Image preview shows before upload
- ✅ Progress bar shows during upload
- ✅ Success message shows after upload
- ✅ Uploaded image appears in library
- ✅ Validation for file type (jpg, png, webp)
- ✅ Validation for file size (max 10MB)
- ✅ Error message for invalid files

#### Image Editing

**Test Cases:**
- ✅ Image editor opens
- ✅ Crop tool works
- ✅ Resize tool works
- ✅ Rotate tool works
- ✅ Alt text field available
- ✅ Title field available
- ✅ Caption field available
- ✅ Save updates image metadata
- ✅ Cancel discards changes

### 6. **Important Articles Management**

**URL**: `http://localhost:3005/ro/admin/important-articles`

**Test Cases:**
- ✅ Important articles list displays
- ✅ Lists are separate per locale (ro, en, ru)
- ✅ Articles can be added to list
- ✅ Articles can be removed from list
- ✅ Articles can be reordered (drag and drop)
- ✅ Position numbers update automatically
- ✅ Maximum articles limit enforced (e.g., 10)
- ✅ Save button updates list
- ✅ Changes reflect on frontend immediately

### 7. **Author Management**

**URL**: `http://localhost:3005/ro/admin/authors`

**Test Cases:**
- ✅ Author list displays all authors
- ✅ Article count per author shows
- ✅ Create author form works
- ✅ Edit author form works
- ✅ Delete author works
- ✅ Cannot delete author with articles
- ✅ Author profile image upload works
- ✅ Author bio accepts rich text (if implemented)

### 8. **User Management**

**URL**: `http://localhost:3005/ro/admin/users`

**Test Cases:**
- ✅ User list displays all users
- ✅ Filter by role (admin, editor, author)
- ✅ Create user form works
- ✅ Edit user form works
- ✅ Delete user works
- ✅ Change user password works
- ✅ Change user role works
- ✅ Activate/deactivate user works
- ✅ Cannot delete own account
- ✅ Cannot demote own admin role

### 9. **Content Preview**

**Test Cases:**
- ✅ Preview button available on article edit
- ✅ Preview opens in new tab
- ✅ Preview shows article as it will appear
- ✅ Preview includes images
- ✅ Preview shows correct locale
- ✅ Preview updates when content changes
- ✅ Preview works for unpublished articles
- ✅ Preview watermark shows for drafts (if implemented)

### 10. **Real-Time Features**

#### Article Locking

**Test Cases:**
- ✅ Opening article for edit locks it
- ✅ Other users see "locked by X" message
- ✅ Lock expires after 10 minutes of inactivity
- ✅ Lock is released on save
- ✅ Lock is released on cancel
- ✅ Lock is released on logout
- ✅ Lock is released on browser close
- ✅ Admin can force unlock (if implemented)

### 11. **Responsive Admin Interface**

**Test Cases:**
- ✅ Admin panel works on desktop (1920x1080)
- ✅ Admin panel works on laptop (1366x768)
- ✅ Admin panel works on tablet (iPad)
- ✅ Mobile admin interface is usable
- ✅ Side navigation collapses on mobile
- ✅ Tables are scrollable on mobile
- ✅ Forms are stacked on mobile

## Testing Workflow

### Step 1: Setup
1. Verify frontend running on http://localhost:3005
2. Verify backend running on http://127.0.0.1:8081
3. Verify test admin user exists
4. Verify database has test data

### Step 2: Execute Tests
For each admin feature:
1. Login as admin user
2. Navigate to admin section
3. Perform actions (create, edit, delete)
4. Verify backend API calls
5. Verify database updates
6. Verify frontend updates
7. Take screenshots
8. Check for errors

### Step 3: Report Results
- Log all test results
- Capture screenshots
- Identify broken functionality
- Generate admin test report

## Playwright MCP Tools to Use

### Navigation
- `mcp__playwright__playwright_navigate` - Navigate to admin pages
- `mcp__playwright__playwright_click` - Click buttons and links

### Interaction
- `mcp__playwright__playwright_fill` - Fill form inputs
- `mcp__playwright__playwright_select` - Select dropdowns
- `mcp__playwright__playwright_upload_file` - Upload images
- `mcp__playwright__playwright_drag` - Drag and drop (reordering)

### Verification
- `mcp__playwright__playwright_screenshot` - Capture admin interface
- `mcp__playwright__playwright_get_visible_text` - Verify content
- `mcp__playwright__playwright_get_visible_html` - Check form structure
- `mcp__playwright__playwright_console_logs` - Check for errors

## Example Test Scenarios

### Scenario 1: Admin Login Flow
```
1. Navigate: http://localhost:3005/ro/admin/login
2. Screenshot: "admin-login.png"
3. Fill: username field with "admin"
4. Fill: password field with "password"
5. Click: Login button
6. Wait for navigation
7. Assert URL: http://localhost:3005/ro/admin
8. Screenshot: "admin-dashboard.png"
9. Assert: Contains "Dashboard" or "Panou de control"
```

### Scenario 2: Create Article Flow
```
1. Navigate: http://localhost:3005/ro/admin
2. Click: "Articles" menu item
3. Assert URL: http://localhost:3005/ro/admin/articles
4. Click: "Create New Article" button
5. Assert URL: http://localhost:3005/ro/admin/articles/create
6. Screenshot: "article-create-form.png"
7. Fill: title field with "Test Article"
8. Fill: content editor with "Test content"
9. Select: category "News"
10. Select: author "Test Author"
11. Select: status "draft"
12. Click: Save button
13. Wait for success message
14. Assert: "Article saved successfully" or similar
15. Screenshot: "article-created.png"
16. Assert URL: http://localhost:3005/ro/admin/articles/{id}/edit
```

### Scenario 3: Upload Image Flow
```
1. Navigate: http://localhost:3005/ro/admin/images
2. Click: Upload button
3. Upload file: /path/to/test-image.jpg
4. Wait for upload progress
5. Wait for success message
6. Assert: Image appears in library
7. Screenshot: "image-uploaded.png"
8. Click: Image to view details
9. Fill: alt text with "Test image"
10. Click: Save
11. Assert: Alt text saved
```

### Scenario 4: Edit Article with Locking
```
1. Open browser session 1
2. Navigate: http://localhost:3005/ro/admin/articles/1/edit
3. Assert: Article form displays
4. Get visible text: "Editing..." or lock indicator

5. Open browser session 2 (new tab/window)
6. Navigate: http://localhost:3005/ro/admin/articles/1/edit
7. Assert: Shows "Locked by User" message
8. Assert: Form is read-only

9. Back to session 1
10. Click: Save button
11. Wait for save

12. Back to session 2
13. Refresh page
14. Assert: Lock is released
15. Assert: Form is editable
```

### Scenario 5: Important Articles Management
```
1. Navigate: http://localhost:3005/ro/admin/important-articles
2. Screenshot: "important-articles-before.png"
3. Click: "Add Article" button
4. Select: Article from dropdown
5. Click: Add
6. Assert: Article appears in list at bottom
7. Drag: Article to position 1
8. Assert: Position updated to 1
9. Screenshot: "important-articles-after.png"
10. Click: Save
11. Wait for success message
12. Frontend: Navigate to http://localhost:3005/ro
13. Assert: Article appears in hero section
```

## Environment Configuration

```bash
ADMIN_URL=http://localhost:3005/ro/admin
ADMIN_USERNAME=admin
ADMIN_PASSWORD=password
DEFAULT_LOCALE=ro
IMAGE_MAX_SIZE=10485760
IMAGE_ALLOWED_TYPES=image/jpeg,image/png,image/webp
ARTICLE_LOCK_TIMEOUT=600000
```

## Test Execution Commands

```bash
# Run all admin panel tests
pnpm test:admin

# Run authentication tests only
pnpm test:admin:auth

# Run article management tests
pnpm test:admin:articles

# Run image management tests
pnpm test:admin:images

# Run with headed mode (see browser)
pnpm test:admin:headed

# Run with debug mode
pnpm test:admin:debug
```

## Expected Outcomes

After running this agent:
- ✅ Admin authentication works correctly
- ✅ All CRUD operations function properly
- ✅ Form validations are working
- ✅ Image uploads are successful
- ✅ Article locking prevents conflicts
- ✅ Important articles management works
- ✅ Multilanguage admin functions correctly
- ✅ Admin interface is responsive
- ✅ No console errors in admin panel
- ✅ Admin test report is generated

## Integration with CI/CD

This agent can be integrated into CI/CD pipeline:
1. Run on pull requests affecting admin code
2. Run nightly for comprehensive admin testing
3. Block merges if admin tests fail
4. Alert team on admin functionality issues

## Troubleshooting

### Common Issues
1. **Login fails**: Verify admin user exists in database
2. **Forms not submitting**: Check CSRF token configuration
3. **Images not uploading**: Check file permissions on upload directory
4. **Article locking not working**: Verify ArticleLock entity and cleanup command
5. **Content editor not loading**: Check TinyMCE/TipTap configuration

## Agent Invocation

To use this agent, call:
```
Use the Task tool with:
- subagent_type: "general-purpose"
- prompt: "Run admin panel tests following the admin-panel-tester agent specification"
```

Or invoke directly:
```
@admin-panel-tester test complete admin functionality
@admin-panel-tester test authentication
@admin-panel-tester test article management
@admin-panel-tester test image upload
@admin-panel-tester test important articles management
```
