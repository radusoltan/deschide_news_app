# Testing Agents Guide

**Comprehensive Testing Framework for Deschide News App**

This guide provides complete documentation for the specialized testing agents built using Playwright MCP to validate all aspects of the Deschide News application.

## Table of Contents

1. [Overview](#overview)
2. [Agent Architecture](#agent-architecture)
3. [Available Testing Agents](#available-testing-agents)
4. [Setup and Prerequisites](#setup-and-prerequisites)
5. [Using Testing Agents](#using-testing-agents)
6. [Test Execution Workflows](#test-execution-workflows)
7. [CI/CD Integration](#cicd-integration)
8. [Troubleshooting](#troubleshooting)
9. [Best Practices](#best-practices)

## Overview

The Deschide News application uses a comprehensive suite of specialized testing agents powered by Playwright MCP (Model Context Protocol). These agents provide automated testing across:

- Backend API endpoints
- Frontend user interfaces
- Full-stack integrations
- Multilanguage functionality
- Admin panel operations
- Performance metrics

### Why Specialized Agents?

Each agent focuses on a specific aspect of the application, providing:
- **Deep expertise** in their domain
- **Comprehensive coverage** of test scenarios
- **Consistent testing** patterns
- **Reusable workflows** across development cycles
- **Clear documentation** of expected behaviors

## Agent Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     Testing Agent Suite                          │
│      (Aligned with Anthropic's Agent Best Practices)            │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                  AUTOMATED AGENTS                        │    │
│  │  ┌──────────────────┐  ┌──────────────────┐             │    │
│  │  │  Backend API     │  │  Frontend E2E    │             │    │
│  │  │  Tester          │  │  Tester          │             │    │
│  │  └──────────────────┘  └──────────────────┘             │    │
│  │                                                          │    │
│  │  ┌──────────────────┐  ┌──────────────────┐             │    │
│  │  │  Full-Stack      │  │  Multilanguage   │             │    │
│  │  │  Integration     │  │  Tester          │             │    │
│  │  └──────────────────┘  └──────────────────┘             │    │
│  │                                                          │    │
│  │  ┌──────────────────┐  ┌──────────────────┐             │    │
│  │  │  Admin Panel     │  │  Performance     │             │    │
│  │  │  Tester          │  │  Tester          │             │    │
│  │  └──────────────────┘  └──────────────────┘             │    │
│  └─────────────────────────────────────────────────────────┘    │
│                                                                   │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │              INTERACTIVE AGENT (NEW)                     │    │
│  │  ┌──────────────────────────────────────────────────┐   │    │
│  │  │  Manual Frontend Tester                           │   │    │
│  │  │  (Exploratory testing like a human QA engineer)   │   │    │
│  │  └──────────────────────────────────────────────────┘   │    │
│  └─────────────────────────────────────────────────────────┘    │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
                            │
                            ▼
                   Playwright MCP Tools
                            │
                            ▼
        ┌───────────────────┴───────────────────┐
        │                                       │
        ▼                                       ▼
  Backend (Symfony)                      Frontend (Next.js)
  http://127.0.0.1:8081                  http://localhost:3005
```

## Available Testing Agents

### 1. Backend API Tester
**Location**: `.claude/agents/backend-api-tester.md`
**Purpose**: Tests all Symfony backend API endpoints

**Capabilities:**
- RESTful API endpoint testing
- Authentication and JWT token validation
- Data validation and error handling
- Multilanguage API responses
- Performance benchmarking
- Hydra/JSON-LD format validation

**Key Test Areas:**
- Articles API (`/api/articles`)
- Categories API (`/api/categories`)
- Authors API (`/api/authors`)
- Images API (`/api/images`)
- Important Articles API (`/api/important_articles_lists`)

**When to Use:**
- After backend code changes
- Before deploying API updates
- When adding new endpoints
- During API versioning

### 2. Frontend E2E Tester
**Location**: `.claude/agents/frontend-e2e-tester.md`
**Purpose**: End-to-end testing of Next.js frontend application

**Capabilities:**
- User flow simulation
- Page navigation testing
- Responsive design validation
- Cross-browser testing
- Mobile device testing
- Visual regression testing

**Key Test Areas:**
- Homepage and hero section
- Article pages and navigation
- Category pages
- Search functionality
- Locale switching
- Responsive layouts

**When to Use:**
- After frontend component changes
- Before major releases
- When adding new features
- For visual regression checks

### 3. Full-Stack Integration Tester
**Location**: `.claude/agents/fullstack-integration-tester.md`
**Purpose**: Tests complete workflows across backend and frontend

**Capabilities:**
- End-to-end workflow validation
- Cross-service integration testing
- Data consistency verification
- CDN integration testing
- Cache validation
- Error propagation testing

**Key Test Areas:**
- Complete article lifecycle
- Image upload and display
- Category-article relationships
- Authentication flows
- Cache invalidation
- Error handling

**When to Use:**
- Before major releases
- After infrastructure changes
- When testing new features
- During staging validation

### 4. Multilanguage Tester
**Location**: `.claude/agents/multilanguage-tester.md`
**Purpose**: Validates i18n/l10n functionality

**Capabilities:**
- Translation validation
- Locale routing testing
- Fallback mechanism testing
- SEO metadata validation
- Date/number formatting
- Content translation verification

**Key Test Areas:**
- Backend translation (Gedmo)
- Frontend locale routing
- UI translation
- Locale switching
- SEO hreflang tags
- Date/time formatting

**When to Use:**
- After adding new translations
- When adding new locales
- Before launching in new markets
- During content updates

### 5. Admin Panel Tester
**Location**: `.claude/agents/admin-panel-tester.md`
**Purpose**: Tests administrative interface

**Capabilities:**
- Admin authentication testing
- CRUD operation validation
- Form validation testing
- Image upload testing
- Real-time features testing
- Responsive admin interface

**Key Test Areas:**
- Admin login/logout
- Article management
- Category management
- Image library
- Important articles management
- User management

**When to Use:**
- After admin UI changes
- Before admin feature releases
- When testing editorial workflows
- During security audits

### 6. Performance Tester
**Location**: `.claude/agents/performance-tester.md`
**Purpose**: Measures and validates performance metrics

**Capabilities:**
- Core Web Vitals measurement
- API response time testing
- Database query profiling
- Load testing
- Memory leak detection
- Network optimization validation

**Key Test Areas:**
- Page load performance
- API response times
- Database queries
- CDN delivery
- Concurrent load handling
- Mobile performance

**When to Use:**
- Before major releases
- After performance optimizations
- During capacity planning
- When investigating slowness

### 7. Manual Frontend Tester (NEW)
**Location**: `.claude/agents/manual-frontend-tester.md`
**Purpose**: Exploratory manual testing of the frontend like a human QA engineer
**Type**: Interactive (human-guided)

> *Aligned with Anthropic's "Building Effective Agents" principles*

**Capabilities:**
- Exploratory testing with adaptive planning
- Real-time documentation with screenshots
- Edge case investigation
- Bug reproduction and analysis
- Accessibility audits
- Mobile/responsive testing
- UX validation

**Key Test Areas:**
- Homepage exploration
- Article page validation
- Locale switching verification
- Mobile responsiveness
- Console error checking
- Network health monitoring
- Accessibility basics

**When to Use:**
- Pre-release exploratory testing
- Bug investigation and reproduction
- UX validation sessions
- When automated tests pass but something "feels wrong"
- Cross-browser/device compatibility checks
- Accessibility audits

**Example Invocations:**
```
@manual-frontend-tester explore homepage and report findings
@manual-frontend-tester test mobile viewport on all main pages
@manual-frontend-tester investigate: "Images not loading on mobile"
@manual-frontend-tester run pre-release exploratory test
```

**Key Difference from Frontend E2E Tester:**
- E2E Tester: Runs predefined, scripted test scenarios
- Manual Tester: Explores dynamically, adapts based on discoveries

## Setup and Prerequisites

### System Requirements

1. **Backend Running**:
   ```bash
   cd /var/www/deschide_news_app/apps/backend
   symfony serve -d --port=8081
   ```

2. **Frontend Running**:
   ```bash
   cd /var/www/deschide_news_app/apps/frontend
   pnpm dev
   ```

3. **CDN Server Running** (for image tests):
   ```bash
   # Simple HTTP server on port 8082
   cd /var/www/deschide_news_app/apps/backend/public
   php -S 127.0.0.1:8082
   ```

4. **Services Running**:
   - PostgreSQL (port 5432)
   - Redis (port 6379)
   - Elasticsearch (port 9200)

### Test Data Setup

Populate database with test data:
```bash
cd /var/www/deschide_news_app/apps/backend
symfony console app:sample-import
```

This creates:
- 6 categories with translations
- 300 articles with translations
- Sample images
- Test admin user

### Playwright MCP Configuration

Playwright MCP is already configured and available through Claude Code. The following MCP tools are used:

**Navigation:**
- `mcp__playwright__playwright_navigate`
- `mcp__playwright__playwright_go_back`
- `mcp__playwright__playwright_go_forward`

**Interaction:**
- `mcp__playwright__playwright_click`
- `mcp__playwright__playwright_fill`
- `mcp__playwright__playwright_select`
- `mcp__playwright__playwright_hover`
- `mcp__playwright__playwright_upload_file`
- `mcp__playwright__playwright_drag`

**Verification:**
- `mcp__playwright__playwright_screenshot`
- `mcp__playwright__playwright_get_visible_text`
- `mcp__playwright__playwright_get_visible_html`
- `mcp__playwright__playwright_console_logs`

**HTTP:**
- `mcp__playwright__playwright_get`
- `mcp__playwright__playwright_post`
- `mcp__playwright__playwright_put`
- `mcp__playwright__playwright_patch`
- `mcp__playwright__playwright_delete`

## Using Testing Agents

### Method 1: Direct Agent Invocation (Recommended)

Use Claude Code to invoke agents directly:

```
@backend-api-tester test all API endpoints
```

```
@frontend-e2e-tester test homepage and navigation
```

```
@fullstack-integration-tester test complete article lifecycle
```

### Method 2: Via Task Tool

For more control, use the Task tool:

```
Use the Task tool with:
- subagent_type: "general-purpose"
- prompt: "Run backend API tests following the backend-api-tester agent specification"
```

### Method 3: Run Existing Playwright Tests

Execute the pre-written Playwright tests:

```bash
# Frontend E2E tests
cd /var/www/deschide_news_app/apps/frontend
pnpm test:e2e

# Integration tests
pnpm test:integration

# Specific test file
pnpm test:e2e __tests__/e2e/article-navigation.spec.ts
```

## Test Execution Workflows

### Complete Test Suite

Run all testing agents in sequence for comprehensive validation:

1. **Backend API Tests** (5-10 minutes)
   ```
   @backend-api-tester test all endpoints
   ```

2. **Frontend E2E Tests** (10-15 minutes)
   ```
   @frontend-e2e-tester test all user flows
   ```

3. **Integration Tests** (15-20 minutes)
   ```
   @fullstack-integration-tester test all workflows
   ```

4. **Multilanguage Tests** (5-10 minutes)
   ```
   @multilanguage-tester test all locales
   ```

5. **Admin Panel Tests** (10-15 minutes)
   ```
   @admin-panel-tester test all admin functionality
   ```

6. **Performance Tests** (10-20 minutes)
   ```
   @performance-tester test full application performance
   ```

7. **Manual Exploratory Tests** (15-45 minutes)
   ```
   @manual-frontend-tester run pre-release exploratory test
   ```

**Total Time**: ~90-120 minutes for complete suite (including manual testing)

### Quick Smoke Test

For rapid validation (5-10 minutes):

```
@backend-api-tester test critical endpoints only
@frontend-e2e-tester test homepage and article page
@multilanguage-tester test default locale only
```

### Exploratory Session (Human-Guided)

For pre-release manual validation (15-30 minutes):

```
@manual-frontend-tester run pre-release exploratory test covering:
- Homepage functionality
- Article navigation
- Locale switching
- Mobile responsiveness
- Console errors
```

### Pre-Release Checklist

Before deploying to production:

1. ✅ Run Backend API Tester
2. ✅ Run Frontend E2E Tester
3. ✅ Run Full-Stack Integration Tester
4. ✅ Run Multilanguage Tester (all locales)
5. ✅ Run Admin Panel Tester
6. ✅ Run Performance Tester
7. ✅ Run Manual Exploratory Tester
8. ✅ Review all test reports
8. ✅ Verify no critical failures
9. ✅ Check performance benchmarks met
10. ✅ Validate error handling

### Feature-Specific Testing

When working on specific features:

**New Article Feature:**
```
@backend-api-tester test articles API
@frontend-e2e-tester test article pages
@fullstack-integration-tester test article lifecycle
```

**Multilanguage Content:**
```
@multilanguage-tester test all locales
@backend-api-tester test translation endpoints
@frontend-e2e-tester test locale switching
```

**Admin Feature:**
```
@admin-panel-tester test specific admin section
@fullstack-integration-tester test admin workflows
```

## CI/CD Integration

### GitHub Actions Example

Create `.github/workflows/test.yml`:

```yaml
name: Testing Suite

on:
  pull_request:
    branches: [develop, main]
  push:
    branches: [develop]

jobs:
  backend-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup Backend
        run: |
          cd apps/backend
          composer install
          symfony console doctrine:migrations:migrate --no-interaction
      - name: Run Backend Tests
        run: |
          # Invoke backend-api-tester agent
          # Or run: pnpm test:api

  frontend-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup Frontend
        run: |
          cd apps/frontend
          pnpm install
      - name: Run E2E Tests
        run: |
          cd apps/frontend
          pnpm test:e2e --project=chromium

  integration-tests:
    needs: [backend-tests, frontend-tests]
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup Services
        run: |
          # Start backend, frontend, database, etc.
      - name: Run Integration Tests
        run: |
          # Invoke fullstack-integration-tester agent
          # Or run: pnpm test:integration
```

### Pre-Commit Hooks

Add to `.git/hooks/pre-commit`:

```bash
#!/bin/bash

echo "Running quick smoke tests..."

# Backend API smoke test
@backend-api-tester test critical endpoints

# Frontend quick test
cd apps/frontend && pnpm test:e2e:headed --grep "smoke"

if [ $? -ne 0 ]; then
  echo "Tests failed. Commit aborted."
  exit 1
fi
```

## Troubleshooting

### Common Issues

#### 1. Services Not Running

**Problem**: Tests fail because backend/frontend not running

**Solution**:
```bash
# Check running services
ss -tulpn | grep -E ":(3005|8081|8082)"

# Start services
cd apps/backend && symfony serve -d --port=8081
cd apps/frontend && pnpm dev
```

#### 2. Port Conflicts

**Problem**: Port already in use

**Solution**:
```bash
# Find process using port
lsof -i :3005
lsof -i :8081

# Kill process or use different port
# Update .env.local and test configurations
```

#### 3. Database Connection Errors

**Problem**: Cannot connect to PostgreSQL

**Solution**:
```bash
# Verify PostgreSQL is running
sudo systemctl status postgresql

# Test connection
PGPASSWORD=sr324395 psql -U deschide_user -d deschide_news -c "SELECT 1"

# Check DATABASE_URL in .env.local
```

#### 4. Test Data Missing

**Problem**: Tests fail because no data in database

**Solution**:
```bash
cd apps/backend
symfony console app:sample-import
```

#### 5. Authentication Failures

**Problem**: Admin tests fail on login

**Solution**:
```bash
# Verify test user exists
cd apps/backend
symfony console doctrine:query:sql "SELECT * FROM users WHERE username='admin'"

# Create test user if missing
symfony console app:create-admin-user
```

#### 6. Playwright Timeout Errors

**Problem**: Tests timeout waiting for elements

**Solution**:
- Increase timeout in Playwright config
- Check if page is actually loading
- Verify network requests are completing
- Check console for JavaScript errors

#### 7. Translation Not Loading

**Problem**: Multilanguage tests fail

**Solution**:
```bash
# Verify translations in database
cd apps/backend
symfony console doctrine:query:sql "SELECT * FROM ext_translations WHERE object_class='Article'"

# Re-import with translations
symfony console app:sample-import
```

## Best Practices

### 1. Test Isolation

- Each test should be independent
- Clean up test data after tests
- Don't rely on test execution order
- Use unique identifiers for test data

### 2. Test Data Management

- Use factories for consistent test data
- Seed database before test suites
- Clean database between test runs (optional)
- Separate test database from development

### 3. Assertions

- Use meaningful assertion messages
- Test both positive and negative cases
- Verify error handling
- Check edge cases

### 4. Screenshots and Evidence

- Capture screenshots on failures
- Save network logs for debugging
- Record videos for complex flows
- Store artifacts for review

### 5. Performance Considerations

- Run heavy tests (load, performance) separately
- Use headless mode for CI/CD
- Parallelize independent tests
- Cache dependencies

### 6. Maintenance

- Update tests when features change
- Review failing tests promptly
- Remove obsolete tests
- Keep test documentation current

### 7. Reporting

- Generate HTML reports
- Track test metrics over time
- Alert team on failures
- Review test coverage

## Test Reports

After running tests, reports are generated:

### Playwright HTML Report
```bash
cd apps/frontend
pnpm test:e2e
# Opens: playwright-report/index.html
```

### Coverage Report
```bash
cd apps/frontend
pnpm test:coverage
# Opens: coverage/index.html
```

### Performance Report
```bash
# Generated by performance-tester agent
# Includes Core Web Vitals, response times, etc.
```

## Further Resources

- **Agent Specifications**: `.claude/agents/*.md`
- **Playwright Config**: `apps/frontend/playwright.config.ts`
- **Test Examples**: `apps/frontend/__tests__/`
- **Playwright Docs**: https://playwright.dev/
- **API Documentation**: http://127.0.0.1:8081/api/docs.jsonld

## Support

For issues or questions:
1. Check agent documentation in `.claude/agents/`
2. Review test examples in `__tests__/`
3. Consult Playwright MCP documentation
4. Ask Claude Code for assistance

## Changelog

### 2025-11-28
- ✅ Added Manual Frontend Tester agent (exploratory testing)
- ✅ Updated pre-release checklist to include manual testing
- ✅ Aligned agents with Anthropic's best practices

### 2025-11-25
- ✅ Created comprehensive testing agent suite
- ✅ Backend API Tester agent
- ✅ Frontend E2E Tester agent
- ✅ Full-Stack Integration Tester agent
- ✅ Multilanguage Tester agent
- ✅ Admin Panel Tester agent
- ✅ Performance Tester agent
- ✅ Documentation and usage guide

---

**Happy Testing!** 🧪
