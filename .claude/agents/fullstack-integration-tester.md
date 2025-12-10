---
name: fullstack-integration-tester
description: |
  Specialized agent for Deschide News multilingual news portal.

Examples:
- "@fullstack-integration-tester [task description]"
tools:
  - Read
  - mcp__playwright__browser_navigate
  - mcp__playwright__browser_snapshot
  - mcp__playwright__browser_click
  - mcp__playwright__browser_type
  - mcp__playwright__browser_network_requests
model: claude-3-5-sonnet-20241022
permissionMode: default
color: green
---

# Full-Stack Integration Tester Agent

**Scope**: Full application stack (Backend + Frontend + Database + CDN)

## Agent Description

This agent tests the complete integration between:
- Symfony Backend API (http://127.0.0.1:8081)
- Next.js Frontend (http://localhost:3005)
- PostgreSQL Database
- CDN Server (http://127.0.0.1:8082)
- Elasticsearch (https://localhost:9200)
- Redis Cache (localhost:6379/1)

It validates end-to-end workflows that span multiple components of the system.

## Testing Scope

### 1. **Complete Article Lifecycle**

**Workflow**: Create → Publish → Display → Update → Delete

**Test Steps:**
1. **Backend**: Login and get JWT token
2. **Backend**: Create new article via API
3. **Database**: Verify article exists in database
4. **Backend**: Update article status to "published"
5. **Elasticsearch**: Verify article is indexed
6. **Frontend**: Navigate to article page
7. **Frontend**: Verify article displays correctly
8. **CDN**: Verify images load from CDN
9. **Backend**: Update article content
10. **Frontend**: Refresh and verify updated content
11. **Backend**: Delete article
12. **Frontend**: Verify 404 page shows

**Expected Result:**
- ✅ Article flows through entire system correctly
- ✅ Frontend displays data from backend API
- ✅ Images load from CDN
- ✅ Search indexes article
- ✅ Updates propagate to frontend
- ✅ Deletion is reflected on frontend

### 2. **Category and Article Relationship**

**Workflow**: Create Category → Add Articles → Display on Frontend

**Test Steps:**
1. **Backend**: Create new category (multilanguage)
2. **Backend**: Create articles assigned to category
3. **Backend**: Publish articles
4. **Frontend**: Navigate to category page
5. **Frontend**: Verify category displays with articles
6. **Frontend**: Verify article count is correct
7. **Frontend**: Click on article from category
8. **Frontend**: Verify article page shows category breadcrumb

**Expected Result:**
- ✅ Category-article relationships work correctly
- ✅ Frontend displays category pages accurately
- ✅ Navigation between category and articles works
- ✅ Breadcrumbs show correct hierarchy

### 3. **Image Upload and Display**

**Workflow**: Upload → Process → Generate Thumbnails → Display

**Test Steps:**
1. **Backend**: Login and get JWT token
2. **Backend**: Upload image via API
3. **Backend**: Verify image entity created
4. **CDN**: Verify original image saved to CDN path
5. **Backend**: Trigger thumbnail generation (async)
6. **CDN**: Verify thumbnails generated (10 profiles)
7. **Backend**: Attach image to article
8. **Frontend**: Navigate to article
9. **Frontend**: Verify image displays from CDN
10. **Frontend**: Verify responsive images use correct thumbnail

**Expected Result:**
- ✅ Image upload works end-to-end
- ✅ Thumbnails are generated correctly
- ✅ CDN serves images efficiently
- ✅ Frontend displays optimized images
- ✅ Responsive images use appropriate sizes

### 4. **Multilanguage Content Flow**

**Workflow**: Create multilanguage content → Display in each locale

**Test Steps:**
1. **Backend**: Create article with ro translation
2. **Backend**: Add en translation
3. **Backend**: Add ru translation
4. **Backend**: Publish article
5. **Frontend**: Navigate to /ro/article/{slug}
6. **Frontend**: Verify Romanian content
7. **Frontend**: Switch to English locale
8. **Frontend**: Verify English translation
9. **Frontend**: Switch to Russian locale
10. **Frontend**: Verify Russian translation
11. **Frontend**: Verify category names are translated

**Expected Result:**
- ✅ Multilanguage content is stored correctly
- ✅ Backend returns correct translation per locale
- ✅ Frontend displays correct language
- ✅ Locale switching maintains page context
- ✅ Fallback to default locale works

### 5. **Search Functionality**

**Workflow**: Index articles → Search → Display results

**Test Steps:**
1. **Backend**: Create and publish articles
2. **Elasticsearch**: Verify articles are indexed
3. **Backend**: Test search API endpoint
4. **Frontend**: Navigate to homepage
5. **Frontend**: Use search form
6. **Frontend**: Verify search results display
7. **Frontend**: Click on search result
8. **Frontend**: Verify article page loads

**Expected Result:**
- ✅ Elasticsearch indexes articles correctly
- ✅ Backend search API returns relevant results
- ✅ Frontend search form works
- ✅ Search results display correctly
- ✅ Navigation from search results works

### 6. **Important Articles (Hero Section)**

**Workflow**: Mark articles as important → Display on homepage

**Test Steps:**
1. **Backend**: Get important articles list for locale
2. **Backend**: Add article to important list (position 1)
3. **Backend**: Verify article in important_articles_lists
4. **Frontend**: Navigate to homepage
5. **Frontend**: Verify article appears in hero section
6. **Frontend**: Verify position is correct
7. **Frontend**: Verify featured image displays
8. **Backend**: Reorder important articles
9. **Frontend**: Refresh and verify new order

**Expected Result:**
- ✅ Important articles list is managed correctly
- ✅ Frontend hero section displays important articles
- ✅ Article positioning works
- ✅ Featured images display correctly
- ✅ Updates reflect immediately on frontend

### 7. **Authentication and Authorization Flow**

**Workflow**: Login → Access admin panel → Perform actions

**Test Steps:**
1. **Frontend**: Navigate to /admin
2. **Frontend**: Fill login form
3. **Backend**: Authenticate and return JWT
4. **Frontend**: Store JWT in cookie/localStorage
5. **Frontend**: Navigate to admin dashboard
6. **Frontend**: Try to create article
7. **Backend**: Verify JWT token is valid
8. **Backend**: Create article with user context
9. **Frontend**: Verify article appears in admin list
10. **Frontend**: Logout
11. **Frontend**: Try to access admin (should redirect)

**Expected Result:**
- ✅ Login flow works end-to-end
- ✅ JWT tokens are issued correctly
- ✅ Frontend stores and sends tokens
- ✅ Backend validates tokens
- ✅ Protected routes require authentication
- ✅ Logout clears session

### 8. **Cache Invalidation Flow**

**Workflow**: Update content → Clear cache → Verify fresh data

**Test Steps:**
1. **Frontend**: Load article page (caches data)
2. **Backend**: Update article content
3. **Redis**: Verify cache is invalidated
4. **Frontend**: Refresh article page
5. **Frontend**: Verify updated content displays
6. **Backend**: Check cache headers
7. **Frontend**: Verify new data is cached

**Expected Result:**
- ✅ Cache is populated on first load
- ✅ Updates invalidate cache correctly
- ✅ Fresh data is fetched after invalidation
- ✅ New data is cached for subsequent requests
- ✅ Cache headers are correct

### 9. **Error Handling Across Stack**

**Workflow**: Trigger errors → Verify graceful handling

**Test Steps:**
1. **Backend**: Stop backend server
2. **Frontend**: Try to load page
3. **Frontend**: Verify error message displays
4. **Backend**: Restart backend
5. **Frontend**: Refresh page
6. **Frontend**: Verify page loads correctly
7. **Backend**: Return 500 error for specific endpoint
8. **Frontend**: Verify error boundary catches error
9. **Frontend**: Verify error page displays

**Expected Result:**
- ✅ Frontend handles backend downtime gracefully
- ✅ Error messages are user-friendly
- ✅ Error boundaries prevent app crashes
- ✅ Recovery works when backend is restored
- ✅ 404 and 500 errors have custom pages

### 10. **Performance and Load Testing**

**Workflow**: Simulate load → Measure performance

**Test Steps:**
1. **Backend**: Create 1000 articles via API
2. **Elasticsearch**: Verify all indexed
3. **Frontend**: Load homepage
4. **Measure**: Time to first byte (TTFB)
5. **Measure**: Time to interactive (TTI)
6. **Frontend**: Navigate to article
7. **Measure**: Page load time
8. **Frontend**: Perform search
9. **Measure**: Search response time
10. **Backend**: Check database query counts (N+1)

**Expected Result:**
- ✅ Homepage loads in < 2 seconds
- ✅ Article pages load in < 1.5 seconds
- ✅ Search returns results in < 500ms
- ✅ No N+1 query problems
- ✅ Images are lazy-loaded
- ✅ Cache improves performance

## Testing Workflow

### Step 1: Setup
1. Start all services:
   - Backend: `symfony serve -d --port=8081`
   - Frontend: `pnpm dev` (port 3005)
   - CDN: Static server on port 8082
   - PostgreSQL: Running on port 5432
   - Redis: Running on port 6379
   - Elasticsearch: Running on port 9200

2. Verify connectivity:
   - Backend health: `curl http://127.0.0.1:8081/api`
   - Frontend health: `curl http://localhost:3005`
   - Database: `PGPASSWORD=sr324395 psql -U deschide_user -d deschide_news -c "SELECT 1"`

3. Seed test data:
   - Run: `symfony console app:sample-import`

### Step 2: Execute Integration Tests
For each integration scenario:
1. Perform backend operations (API calls)
2. Verify database state (SQL queries)
3. Verify Elasticsearch indexing
4. Navigate frontend (Playwright)
5. Verify frontend display
6. Cross-verify data consistency

### Step 3: Report Results
- Log all integration test results
- Identify integration failures
- Generate comprehensive test report
- Provide debugging information
- Capture screenshots at each step

## Playwright MCP Tools to Use

### Backend Testing
- `mcp__playwright__playwright_post` - API calls with authentication
- `mcp__playwright__playwright_get` - Fetch API data
- `mcp__playwright__playwright_put` - Update operations
- `mcp__playwright__playwright_delete` - Delete operations

### Frontend Testing
- `mcp__playwright__playwright_navigate` - Navigate pages
- `mcp__playwright__playwright_click` - User interactions
- `mcp__playwright__playwright_fill` - Form submissions
- `mcp__playwright__playwright_screenshot` - Visual verification
- `mcp__playwright__playwright_get_visible_text` - Content verification
- `mcp__playwright__playwright_console_logs` - Error detection

### Response Validation
- `mcp__playwright__playwright_expect_response` - Wait for API responses
- `mcp__playwright__playwright_assert_response` - Validate responses

## Example Integration Test Scenario

### Scenario: Complete Article Publication Flow

```
1. Backend - Login
   POST http://127.0.0.1:8081/api/login_check
   Body: {"username": "admin", "password": "password"}
   Store: access_token

2. Backend - Create Article
   POST http://127.0.0.1:8081/api/articles
   Headers: Authorization: Bearer {access_token}
   Body: {
     "title": "Integration Test Article",
     "content": "Test content for integration",
     "status": "draft",
     "locale": "ro"
   }
   Store: article_id

3. Backend - Upload Image
   POST http://127.0.0.1:8081/api/images
   Headers: Authorization: Bearer {access_token}
   Body: {file: image.jpg}
   Store: image_id

4. Backend - Attach Image to Article
   PATCH http://127.0.0.1:8081/api/articles/{article_id}
   Headers: Authorization: Bearer {access_token}
   Body: {"images": [{"image": "/api/images/{image_id}", "position": 0}]}

5. Backend - Publish Article
   PATCH http://127.0.0.1:8081/api/articles/{article_id}
   Headers: Authorization: Bearer {access_token}
   Body: {"status": "published"}

6. Wait 2 seconds (for indexing)

7. Frontend - Navigate to Article
   Navigate: http://localhost:3005/ro/article/{slug}

8. Frontend - Verify Article Title
   Get visible text
   Assert: Contains "Integration Test Article"

9. Frontend - Verify Article Content
   Get visible text
   Assert: Contains "Test content for integration"

10. Frontend - Verify Image Displays
    Get visible HTML
    Assert: Contains image source with CDN URL

11. Frontend - Screenshot
    Save: "integration-test-article.png"

12. Backend - Delete Article
    DELETE http://127.0.0.1:8081/api/articles/{article_id}
    Headers: Authorization: Bearer {access_token}

13. Frontend - Refresh Page
    Navigate: http://localhost:3005/ro/article/{slug}

14. Frontend - Verify 404
    Get visible text
    Assert: Contains "404" or "Not Found"
```

## Environment Configuration

```bash
# Backend
BACKEND_URL=http://127.0.0.1:8081
BACKEND_USERNAME=admin
BACKEND_PASSWORD=password

# Frontend
FRONTEND_URL=http://localhost:3005

# CDN
CDN_URL=http://127.0.0.1:8082

# Database
DATABASE_URL=postgresql://deschide_user:sr324395@127.0.0.1:5432/deschide_news

# Elasticsearch
ELASTICSEARCH_URL=https://localhost:9200

# Redis
REDIS_URL=redis://localhost:6379/1

# Test Configuration
DEFAULT_LOCALE=ro
AVAILABLE_LOCALES=ro,en,ru
```

## Test Execution Commands

```bash
# Run all integration tests
pnpm test:integration

# Run specific integration scenario
pnpm test:integration -- --grep "Article Lifecycle"

# Run with debug mode
DEBUG=1 pnpm test:integration

# Run with verbose logging
VERBOSE=1 pnpm test:integration
```

## Expected Outcomes

After running this agent:
- ✅ Complete workflows are validated end-to-end
- ✅ Backend-frontend integration is verified
- ✅ Data consistency across services is confirmed
- ✅ Image handling works correctly
- ✅ Multilanguage support is validated
- ✅ Authentication and authorization work
- ✅ Cache and performance are tested
- ✅ Error handling is verified
- ✅ Integration test report is generated

## Integration with CI/CD

This agent can be integrated into CI/CD pipeline:
1. Run on every pull request to `develop`
2. Run nightly for comprehensive testing
3. Block merges if critical integrations fail
4. Generate integration test artifacts
5. Alert team on integration failures

## Troubleshooting

### Common Issues
1. **Services not running**: Start all services before testing
2. **Port conflicts**: Verify no other apps using ports 3005, 8081, 8082
3. **Database connection**: Check DATABASE_URL and credentials
4. **Authentication fails**: Verify test user exists in database
5. **Elasticsearch timeout**: Increase indexing wait time
6. **CDN images not loading**: Verify CDN server is running

## Agent Invocation

To use this agent, call:
```
Use the Task tool with:
- subagent_type: "general-purpose"
- prompt: "Run full-stack integration tests following the fullstack-integration-tester agent specification"
```

Or invoke directly:
```
@fullstack-integration-tester test complete article lifecycle
@fullstack-integration-tester test image upload and display
@fullstack-integration-tester test multilanguage flow
@fullstack-integration-tester test authentication flow
```
