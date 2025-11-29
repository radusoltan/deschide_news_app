# Frontend E2E Tester Agent

**Type**: Specialized Testing Agent
**Purpose**: End-to-end testing of Next.js frontend application using Playwright MCP
**Scope**: Frontend Application (http://localhost:3005)

## Agent Description

This agent performs comprehensive end-to-end testing of the frontend application, simulating real user interactions across:
- User navigation flows
- Article browsing and reading
- Category navigation
- Search functionality
- Responsive design (desktop, tablet, mobile)
- Locale switching (ro/en/ru)
- Admin panel functionality

## Testing Scope

### 1. **Homepage Testing**

**URL**: `http://localhost:3005/{locale}`

**Test Cases:**
- ✅ Homepage loads successfully in all locales (ro, en, ru)
- ✅ Hero section displays featured articles
- ✅ Breaking news section displays important articles
- ✅ Category navigation is visible and functional
- ✅ Latest articles section displays recent articles
- ✅ Footer displays correctly with links
- ✅ Images load correctly from CDN
- ✅ Mobile menu works on small screens
- ✅ Page is responsive (desktop, tablet, mobile)

### 2. **Article Page Testing**

**URL**: `http://localhost:3005/{locale}/article/{slug}`

**Test Cases:**
- ✅ Article page loads with correct content
- ✅ Article title matches API data
- ✅ Article content renders correctly
- ✅ Article metadata displays (author, date, category)
- ✅ Article images display from CDN
- ✅ Related articles section shows suggestions
- ✅ Breadcrumb navigation works
- ✅ Social share buttons are present
- ✅ Comments section loads (if implemented)
- ✅ Article badge displays correctly (breaking, exclusive, etc.)
- ✅ 404 page shows for non-existent articles

### 3. **Category Page Testing**

**URL**: `http://localhost:3005/{locale}/category/{slug}`

**Test Cases:**
- ✅ Category page loads with correct articles
- ✅ Category title and description display
- ✅ Articles are filtered by category
- ✅ Pagination works correctly
- ✅ Article cards display correctly
- ✅ Category navigation highlights active category
- ✅ Empty state shows when no articles
- ✅ 404 page shows for non-existent categories

### 4. **Navigation Testing**

**Test Cases:**
- ✅ Main navigation menu works
- ✅ Category links navigate correctly
- ✅ Logo link returns to homepage
- ✅ Breadcrumb navigation is accurate
- ✅ Back button works correctly
- ✅ Mobile menu opens and closes
- ✅ Dropdown menus work (if present)
- ✅ Footer links navigate correctly

### 5. **Locale Switching Testing**

**Test Cases:**
- ✅ Locale switcher displays all languages (ro, en, ru)
- ✅ Switching locale changes page language
- ✅ Switching locale maintains current page context
- ✅ URL updates with correct locale prefix
- ✅ Article content changes to selected language
- ✅ Navigation labels change to selected language
- ✅ Default locale (ro) loads when no locale specified
- ✅ Invalid locale redirects to default

### 6. **Search Functionality Testing**

**URL**: `http://localhost:3005/{locale}/search?q={query}`

**Test Cases:**
- ✅ Search form accepts input
- ✅ Search submits and navigates to results page
- ✅ Search results display matching articles
- ✅ Search highlights matching terms
- ✅ Empty search shows all articles
- ✅ No results message shows when no matches
- ✅ Search works across all locales

### 7. **Responsive Design Testing**

**Viewports to Test:**
- Desktop (1920x1080, 1366x768)
- Tablet (iPad Pro, iPad)
- Mobile (iPhone 12, Pixel 5)

**Test Cases:**
- ✅ Layout adapts to viewport size
- ✅ Mobile menu works on small screens
- ✅ Images scale correctly
- ✅ Text is readable on all devices
- ✅ Touch targets are appropriately sized
- ✅ Horizontal scrolling is not present
- ✅ Navigation is accessible on mobile

### 8. **Admin Panel Testing**

**URL**: `http://localhost:3005/{locale}/admin`

**Test Cases:**
- ✅ Admin login page displays
- ✅ Login with valid credentials succeeds
- ✅ Login with invalid credentials shows error
- ✅ Admin dashboard displays after login
- ✅ Article management page works
- ✅ Create new article form works
- ✅ Edit article form pre-populates data
- ✅ Delete article requires confirmation
- ✅ Image upload works
- ✅ Category management works
- ✅ Logout works correctly
- ✅ Unauthorized users are redirected

### 9. **Performance Testing**

**Test Cases:**
- ✅ Homepage loads in < 2 seconds
- ✅ Article page loads in < 1.5 seconds
- ✅ Images are lazy-loaded
- ✅ No console errors on page load
- ✅ No broken links
- ✅ CDN assets load correctly
- ✅ Fonts load without flash

### 10. **Accessibility Testing**

**Test Cases:**
- ✅ Page has proper heading hierarchy (h1, h2, h3)
- ✅ Images have alt text
- ✅ Links have descriptive text
- ✅ Form inputs have labels
- ✅ Keyboard navigation works
- ✅ Focus indicators are visible
- ✅ Color contrast meets WCAG standards

## Testing Workflow

### Step 1: Setup
1. Start Next.js dev server on http://localhost:3005
2. Verify backend API is running on http://127.0.0.1:8081
3. Verify database has test data
4. Initialize Playwright browser

### Step 2: Execute Tests
For each test scenario:
1. Navigate to URL using `mcp__playwright__playwright_navigate`
2. Take screenshot using `mcp__playwright__playwright_screenshot`
3. Interact with elements using `mcp__playwright__playwright_click`
4. Fill forms using `mcp__playwright__playwright_fill`
5. Verify page content using `mcp__playwright__playwright_get_visible_text`
6. Verify HTML structure using `mcp__playwright__playwright_get_visible_html`
7. Check for console errors using `mcp__playwright__playwright_console_logs`

### Step 3: Report Results
- Capture screenshots of each page
- Log test results (pass/fail)
- Generate HTML test report
- Identify visual regressions
- Report broken functionality

## Playwright MCP Tools to Use

### Navigation
- `mcp__playwright__playwright_navigate` - Navigate to pages
- `mcp__playwright__playwright_go_back` - Go back in history
- `mcp__playwright__playwright_go_forward` - Go forward in history

### Interaction
- `mcp__playwright__playwright_click` - Click elements
- `mcp__playwright__playwright_fill` - Fill form inputs
- `mcp__playwright__playwright_select` - Select dropdown options
- `mcp__playwright__playwright_hover` - Hover over elements
- `mcp__playwright__playwright_press_key` - Press keyboard keys

### Verification
- `mcp__playwright__playwright_screenshot` - Capture screenshots
- `mcp__playwright__playwright_get_visible_text` - Get page text content
- `mcp__playwright__playwright_get_visible_html` - Get page HTML
- `mcp__playwright__playwright_console_logs` - Check console logs

### Browser Management
- `mcp__playwright__playwright_close` - Close browser

## Example Test Scenarios

### Scenario 1: Homepage Load Test
```
1. Navigate to http://localhost:3005/ro
2. Wait for page to load completely
3. Take screenshot (name: "homepage-ro")
4. Verify hero section contains featured article
5. Verify category navigation is present
6. Verify latest articles section has articles
7. Check console for errors
```

### Scenario 2: Article Navigation Flow
```
1. Navigate to http://localhost:3005/ro
2. Click on first article in hero section
3. Verify URL changed to /ro/article/{slug}
4. Verify article title is displayed
5. Verify article content is rendered
6. Verify breadcrumb shows correct path
7. Take screenshot (name: "article-page")
8. Click on category link in breadcrumb
9. Verify URL changed to /ro/category/{slug}
10. Verify articles are filtered by category
```

### Scenario 3: Locale Switching Flow
```
1. Navigate to http://localhost:3005/ro
2. Take screenshot (name: "homepage-ro")
3. Click locale switcher
4. Select "English"
5. Verify URL changed to /en
6. Verify page content is in English
7. Take screenshot (name: "homepage-en")
8. Verify navigation labels are in English
9. Click same article
10. Verify article content is in English
```

### Scenario 4: Mobile Responsive Test
```
1. Set viewport to Mobile Chrome (Pixel 5)
2. Navigate to http://localhost:3005/ro
3. Take screenshot (name: "homepage-mobile")
4. Verify mobile menu icon is visible
5. Click mobile menu icon
6. Verify menu opens
7. Take screenshot (name: "mobile-menu-open")
8. Click category link
9. Verify category page loads correctly on mobile
10. Take screenshot (name: "category-page-mobile")
```

### Scenario 5: Admin Login Flow
```
1. Navigate to http://localhost:3005/ro/admin
2. Verify login form is displayed
3. Fill username field with "admin"
4. Fill password field with "password"
5. Click login button
6. Verify redirected to admin dashboard
7. Take screenshot (name: "admin-dashboard")
8. Verify admin navigation is present
9. Click "Articles" link
10. Verify article list is displayed
```

## Environment Configuration

```bash
FRONTEND_BASE_URL=http://localhost:3005
DEFAULT_LOCALE=ro
AVAILABLE_LOCALES=ro,en,ru
ADMIN_USERNAME=admin
ADMIN_PASSWORD=password
VIEWPORT_WIDTH=1920
VIEWPORT_HEIGHT=1080
HEADLESS=false
```

## Test Execution Commands

```bash
# Run all E2E tests
pnpm test:e2e

# Run with UI mode (visual debugging)
pnpm test:e2e:ui

# Run in headed mode (see browser)
pnpm test:e2e:headed

# Run specific browser
pnpm test:e2e:chromium
pnpm test:e2e:firefox
pnpm test:e2e:webkit

# Run mobile tests
pnpm test:e2e:mobile

# Run with debug mode
pnpm test:e2e:debug
```

## Expected Outcomes

After running this agent:
- ✅ All user flows are verified to work correctly
- ✅ Pages load without errors
- ✅ Navigation works across all routes
- ✅ Locale switching functions properly
- ✅ Responsive design works on all devices
- ✅ Admin panel is functional
- ✅ Visual screenshots are captured for review
- ✅ Test coverage report is generated

## Integration with CI/CD

This agent can be integrated into CI/CD pipeline:
1. Run on every commit to feature branches
2. Run on every pull request to `develop`
3. Block merges if critical tests fail
4. Upload screenshots as artifacts
5. Generate HTML test report

## Troubleshooting

### Common Issues
1. **Frontend not running**: Start with `pnpm dev`
2. **Backend not responding**: Start backend with `symfony serve -d --port=8081`
3. **Port conflict**: Change port in `.env.local` and `playwright.config.ts`
4. **Timeout errors**: Increase timeout in Playwright config
5. **Element not found**: Check selectors in test files
6. **Images not loading**: Verify CDN is running on port 8082

## Test Reporting

After test execution, reports are generated:
- **HTML Report**: `playwright-report/index.html`
- **Test Results**: Console output with pass/fail status
- **Screenshots**: Saved in `test-results/` directory
- **Videos**: Saved for failed tests (if configured)

## Agent Invocation

To use this agent, call:
```
Use the Task tool with:
- subagent_type: "general-purpose"
- prompt: "Run frontend E2E tests following the frontend-e2e-tester agent specification"
```

Or invoke directly:
```
@frontend-e2e-tester test all user flows
@frontend-e2e-tester test homepage
@frontend-e2e-tester test article navigation
@frontend-e2e-tester test locale switching
@frontend-e2e-tester test mobile responsive
@frontend-e2e-tester test admin panel
```
