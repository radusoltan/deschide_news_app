# Playwright Manual Testing: Articles CRUD

## Scenario: Test Article Management in Admin Panel

Use Playwright MCP tools to perform exploratory testing of article CRUD operations.

### Prerequisites
- Must be logged in to admin panel
- If not logged in, run `/pw-test-login` first

### Test Environment
- **Articles List**: http://localhost:3005/ro/admin/articles
- **New Article**: http://localhost:3005/ro/admin/articles/new
- **Edit Article**: http://localhost:3005/ro/admin/articles/{id}/edit

### Test Steps

#### 1. Navigate to Articles List
```
Use mcp__playwright__browser_navigate to go to http://localhost:3005/ro/admin/articles
```

#### 2. Verify Articles List Page
After navigation, use `mcp__playwright__browser_snapshot` to verify:
- [ ] Page title/heading "Articles" or "Articole"
- [ ] Table with article columns (Title, Category, Author, Status, Date)
- [ ] Pagination controls
- [ ] "New Article" / "Create" button
- [ ] Search/filter functionality
- [ ] Action buttons (Edit, Delete) per row

#### 3. Test Scenarios

**Scenario A: View Articles List**
1. Verify table loads with articles
2. Check pagination works (if multiple pages)
3. Verify column sorting (if available)
4. Test search/filter (if available)

**Scenario B: Create New Article**
1. Click "New Article" button
2. Verify article form loads with fields:
   - Title (required)
   - Content/Body (rich text editor)
   - Category dropdown
   - Author selection
   - Status (draft/published)
   - Featured image
   - Publish date
3. Fill in test data:
   - Title: "Test Article - Playwright"
   - Content: "This is a test article created during Playwright testing."
   - Category: Select first available
   - Status: Draft
4. Click Save/Create button
5. Verify success message
6. Verify redirect to articles list or article detail

**Scenario C: Edit Existing Article**
1. From articles list, click Edit on an article
2. Verify form loads with existing data
3. Modify title: append " - EDITED"
4. Click Save/Update
5. Verify success message
6. Verify changes persisted

**Scenario D: Delete Article (if safe to test)**
1. From articles list, click Delete on test article
2. Verify confirmation dialog appears
3. Confirm deletion
4. Verify article removed from list
5. Verify success message

#### 4. Multilanguage Testing
1. Switch locale to English: http://localhost:3005/en/admin/articles
2. Verify UI labels change
3. Test article with translations (if supported)

#### 5. Article Locking Test
1. Open article for editing
2. Verify lock indicator appears
3. Check if lock prevents concurrent edits

#### 6. Document Results
Create a markdown report with:
- Screenshots of each CRUD operation
- Any validation errors encountered
- Form field behavior
- Error handling observations
- Performance notes

### Playwright MCP Tools to Use
- `mcp__playwright__browser_navigate` - Navigate between pages
- `mcp__playwright__browser_snapshot` - Capture page state
- `mcp__playwright__browser_type` - Fill form fields
- `mcp__playwright__browser_click` - Click buttons
- `mcp__playwright__browser_select_option` - Select dropdowns
- `mcp__playwright__browser_take_screenshot` - Visual evidence
- `mcp__playwright__browser_fill_form` - Fill multiple fields at once

---

**START TESTING NOW**: Navigate to the articles admin page and begin testing.
