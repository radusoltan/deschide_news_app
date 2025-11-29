# Playwright Manual Testing: Categories CRUD

## Scenario: Test Category Management in Admin Panel

Use Playwright MCP tools to perform exploratory testing of category CRUD operations.

### Prerequisites
- Must be logged in to admin panel
- If not logged in, run `/pw-test-login` first

### Test Environment
- **Categories List**: http://localhost:3005/ro/admin/categories
- **New Category**: http://localhost:3005/ro/admin/categories/new
- **Edit Category**: http://localhost:3005/ro/admin/categories/{id}/edit

### Test Steps

#### 1. Navigate to Categories List
```
Use mcp__playwright__browser_navigate to go to http://localhost:3005/ro/admin/categories
```

#### 2. Verify Categories List Page
After navigation, use `mcp__playwright__browser_snapshot` to verify:
- [ ] Page title/heading "Categories" or "Categorii"
- [ ] Table/List with category columns (Name, Slug, Description, Articles Count)
- [ ] Pagination controls (if many categories)
- [ ] "New Category" / "Create" button
- [ ] Action buttons (Edit, Delete) per row

#### 3. Test Scenarios

**Scenario A: View Categories List**
1. Verify list loads with existing categories
2. Check if categories show article count
3. Verify slug is displayed correctly
4. Test pagination (if applicable)

**Scenario B: Create New Category**
1. Click "New Category" button
2. Verify form loads with fields:
   - Name (required) - translatable
   - Slug (auto-generated or manual)
   - Description (optional) - translatable
   - Parent category (if hierarchical)
   - Order/Position
3. Fill in test data:
   - Name (RO): "Categorie Test Playwright"
   - Name (EN): "Test Category Playwright"
   - Name (RU): "Тестовая категория Playwright"
   - Slug: "test-playwright"
   - Description: "Category for testing purposes"
4. Click Save/Create button
5. Verify success message
6. Verify new category appears in list

**Scenario C: Edit Existing Category**
1. From categories list, click Edit on a category
2. Verify form loads with existing data
3. Verify translations load correctly (RO, EN, RU tabs if available)
4. Modify name: append " - EDITED"
5. Click Save/Update
6. Verify success message
7. Verify changes persisted in list

**Scenario D: Delete Category**
1. From categories list, click Delete on test category
2. Verify confirmation dialog:
   - Warning about articles in this category
   - Option to reassign articles (if applicable)
3. Confirm deletion
4. Verify category removed from list
5. Verify success message

#### 4. Multilanguage Testing
1. Switch locale in URL: /en/admin/categories
2. Verify UI labels change to English
3. Edit a category and switch between translation tabs
4. Verify each language saves independently

#### 5. Validation Testing
1. Try creating category with:
   - Empty name → Validation error
   - Duplicate slug → Validation error
   - Very long name (>255 chars) → Validation or truncation
   - Special characters in slug → Sanitization check

#### 6. Document Results
Create a markdown report with:
- Screenshots of list and form views
- Translation handling behavior
- Validation messages
- Any issues found

### Playwright MCP Tools to Use
- `mcp__playwright__browser_navigate` - Navigate between pages
- `mcp__playwright__browser_snapshot` - Capture page state
- `mcp__playwright__browser_type` - Fill form fields
- `mcp__playwright__browser_click` - Click buttons/tabs
- `mcp__playwright__browser_take_screenshot` - Visual evidence

---

**START TESTING NOW**: Navigate to the categories admin page and begin testing.
