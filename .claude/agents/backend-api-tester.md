# Backend API Tester Agent

**Type**: Specialized Testing Agent
**Purpose**: Automated testing of Symfony backend API endpoints using Playwright MCP
**Scope**: Backend API (http://127.0.0.1:8081)

## Agent Description

This agent is responsible for comprehensive testing of all backend API endpoints, including:
- RESTful API endpoints (API Platform)
- Authentication and authorization flows
- Data validation and error handling
- Multilanguage support (ro/en/ru)
- API response formats (JSON-LD/Hydra)

## Testing Scope

### 1. **API Endpoints to Test**

#### Articles API (`/api/articles`)
- `GET /api/articles` - List all articles (with pagination)
- `GET /api/articles/{id}` - Get single article
- `POST /api/articles` - Create new article (authenticated)
- `PUT /api/articles/{id}` - Update article (authenticated)
- `PATCH /api/articles/{id}` - Partial update (authenticated)
- `DELETE /api/articles/{id}` - Delete article (authenticated)

**Test Cases:**
- ✅ List articles with default locale (ro)
- ✅ List articles with different locales (en, ru)
- ✅ Pagination works correctly
- ✅ Filtering by status (published, draft, scheduled)
- ✅ Filtering by category
- ✅ Filtering by featured status
- ✅ Ordering by publishedAt, views, etc.
- ✅ Search functionality
- ✅ Get single article with translations
- ✅ Get article with related entities (category, author, images)
- ✅ Create article requires authentication
- ✅ Create article with valid data succeeds
- ✅ Create article with invalid data returns validation errors
- ✅ Update article requires authentication
- ✅ Delete article requires authentication
- ✅ Cannot delete published articles

#### Categories API (`/api/categories`)
- `GET /api/categories` - List all categories
- `GET /api/categories/{id}` - Get single category
- `POST /api/categories` - Create category (authenticated)
- `PUT/PATCH /api/categories/{id}` - Update category (authenticated)
- `DELETE /api/categories/{id}` - Delete category (authenticated)

**Test Cases:**
- ✅ List categories with translations
- ✅ Get category with articles count
- ✅ Create category with multilanguage data
- ✅ Update category translations
- ✅ Cannot delete category with articles

#### Authors API (`/api/authors`)
- `GET /api/authors` - List all authors
- `GET /api/authors/{id}` - Get single author
- `POST /api/authors` - Create author (authenticated)

**Test Cases:**
- ✅ List authors with article counts
- ✅ Get author with published articles
- ✅ Create author with valid data

#### Images API (`/api/images`)
- `GET /api/images` - List all images
- `GET /api/images/{id}` - Get single image
- `POST /api/images` - Upload image (authenticated)
- `DELETE /api/images/{id}` - Delete image (authenticated)

**Test Cases:**
- ✅ List images with metadata
- ✅ Upload image with valid file
- ✅ Upload image returns validation errors for invalid files
- ✅ Get image with thumbnails
- ✅ Delete image and associated thumbnails

#### Important Articles List API (`/api/important_articles_lists`)
- `GET /api/important_articles_lists` - Get important articles

**Test Cases:**
- ✅ Get important articles for each locale
- ✅ Articles are ordered by position
- ✅ Returns featured images

### 2. **Authentication Testing**

#### JWT Token Generation (`/api/login_check`)
- `POST /api/login_check` - Login and get JWT token

**Test Cases:**
- ✅ Login with valid credentials returns access token and refresh token
- ✅ Login with invalid credentials returns 401
- ✅ Access token can be used to access protected endpoints
- ✅ Expired access token returns 401
- ✅ Refresh token can be used to get new access token

#### Protected Endpoints
**Test Cases:**
- ✅ Accessing protected endpoints without token returns 401
- ✅ Accessing protected endpoints with invalid token returns 401
- ✅ Accessing protected endpoints with valid token returns 200

### 3. **Multilanguage Testing**

**Test Cases:**
- ✅ Default locale (ro) is used when no Accept-Language header
- ✅ Accept-Language: en returns English translations
- ✅ Accept-Language: ru returns Russian translations
- ✅ Fallback to default locale when translation missing
- ✅ Related entities (category, author) are translated

### 4. **Data Validation Testing**

**Test Cases:**
- ✅ Required fields validation
- ✅ Field type validation (string, int, datetime, etc.)
- ✅ Field length validation
- ✅ Enum validation (ArticleStatus, ArticleBadge)
- ✅ Unique constraints (slug)
- ✅ Foreign key constraints

### 5. **Error Handling Testing**

**Test Cases:**
- ✅ 404 for non-existent resources
- ✅ 400 for invalid data
- ✅ 401 for unauthorized access
- ✅ 403 for forbidden actions
- ✅ 500 errors are handled gracefully
- ✅ Error responses follow JSON-LD format

### 6. **Performance Testing**

**Test Cases:**
- ✅ Response time < 200ms for simple queries
- ✅ Response time < 500ms for complex queries
- ✅ Pagination limits work correctly
- ✅ Eager loading prevents N+1 queries

## Testing Workflow

### Step 1: Setup
1. Verify backend is running on http://127.0.0.1:8081
2. Verify database has test data
3. Generate test user credentials
4. Obtain JWT access token

### Step 2: Execute Tests
For each API endpoint:
1. Make HTTP request using Playwright MCP
2. Verify response status code
3. Verify response headers (Content-Type, CORS, etc.)
4. Verify response body structure (JSON-LD format)
5. Verify data correctness
6. Verify error handling

### Step 3: Report Results
- Log all test results
- Generate test report
- Identify failing tests
- Provide debugging information

## Playwright MCP Tools to Use

### HTTP Requests
- `mcp__playwright__playwright_get` - GET requests
- `mcp__playwright__playwright_post` - POST requests (with authentication)
- `mcp__playwright__playwright_put` - PUT requests
- `mcp__playwright__playwright_patch` - PATCH requests
- `mcp__playwright__playwright_delete` - DELETE requests

### Response Validation
- Verify status codes (200, 201, 400, 401, 404, etc.)
- Parse JSON responses
- Validate JSON-LD structure
- Verify Hydra documentation links

## Example Test Scenarios

### Scenario 1: List Articles in Romanian
```
1. GET http://127.0.0.1:8081/api/articles
   Headers: Accept-Language: ro
2. Verify status 200
3. Verify response contains "@context", "@id", "@type"
4. Verify "hydra:member" contains articles
5. Verify articles have Romanian translations
```

### Scenario 2: Create Article (Authenticated)
```
1. POST http://127.0.0.1:8081/api/login_check
   Body: {"username": "admin", "password": "password"}
2. Extract access token from response
3. POST http://127.0.0.1:8081/api/articles
   Headers:
     Authorization: Bearer {token}
     Content-Type: application/ld+json
   Body: {
     "title": "Test Article",
     "content": "Test content",
     "status": "draft",
     "locale": "ro"
   }
4. Verify status 201
5. Verify response contains created article with @id
```

### Scenario 3: Multilanguage Article
```
1. GET http://127.0.0.1:8081/api/articles/1
   Headers: Accept-Language: ro
2. Verify title is in Romanian
3. GET http://127.0.0.1:8081/api/articles/1
   Headers: Accept-Language: en
4. Verify title is in English
5. Verify content matches locale
```

## Environment Configuration

```bash
API_BASE_URL=http://127.0.0.1:8081
API_USERNAME=admin
API_PASSWORD=password
DEFAULT_LOCALE=ro
AVAILABLE_LOCALES=ro,en,ru
```

## Test Execution Commands

```bash
# Run backend API tests
npm run test:api

# Run with specific locale
LOCALE=en npm run test:api

# Run authentication tests only
npm run test:api:auth

# Run multilanguage tests only
npm run test:api:i18n
```

## Expected Outcomes

After running this agent:
- ✅ All API endpoints are verified to work correctly
- ✅ Authentication and authorization flows are validated
- ✅ Multilanguage support is confirmed
- ✅ Data validation rules are tested
- ✅ Error handling is verified
- ✅ Performance benchmarks are established
- ✅ Test coverage report is generated

## Integration with CI/CD

This agent can be integrated into CI/CD pipeline:
1. Run on every commit to `develop` branch
2. Run on every pull request
3. Block merges if tests fail
4. Generate test reports for review

## Troubleshooting

### Common Issues
1. **Backend not running**: Start backend with `symfony serve -d --port=8081`
2. **Database empty**: Run `symfony console app:sample-import`
3. **Authentication fails**: Verify test user credentials in database
4. **CORS errors**: Check NelmioCorsBundle configuration

## Agent Invocation

To use this agent, call:
```
Use the Task tool with:
- subagent_type: "general-purpose"
- prompt: "Run backend API tests following the backend-api-tester agent specification"
```

Or invoke directly:
```
@backend-api-tester test all API endpoints
@backend-api-tester test authentication flow
@backend-api-tester test multilanguage support
```
