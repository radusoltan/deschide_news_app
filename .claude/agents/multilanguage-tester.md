---
name: multilanguage-tester
description: |
  Specialized agent for Deschide News multilingual news portal.

Examples:
- "@multilanguage-tester [task description]"
tools:
  - Read
  - mcp__playwright__browser_navigate
  - mcp__playwright__browser_snapshot
  - mcp__playwright__browser_evaluate
model: claude-sonnet-4-6
permissionMode: default
color: green
---

# Multilanguage Tester Agent

**Scope**: Backend API + Frontend (Romanian, English, Russian)

## Agent Description

This agent validates the complete multilanguage implementation including:
- Content translation (articles, categories, UI elements)
- Locale detection and switching
- URL structure per locale
- Fallback mechanisms
- RTL support (if applicable)
- Date/time formatting per locale
- Number formatting per locale

## Supported Locales

- **Romanian (ro)** - Default locale
- **English (en)** - Secondary locale
- **Russian (ru)** - Third locale

## Testing Scope

### 1. **Backend Translation Testing**

#### Gedmo Translatable Entity Testing

**Entities with Translations:**
- Article (title, summary, content, metaTitle, metaDescription, slug)
- Category (name, slug, description)

**Test Cases:**
- ✅ Create entity with Romanian translation (default)
- ✅ Add English translation to entity
- ✅ Add Russian translation to entity
- ✅ Fetch entity with `Accept-Language: ro` returns Romanian
- ✅ Fetch entity with `Accept-Language: en` returns English
- ✅ Fetch entity with `Accept-Language: ru` returns Russian
- ✅ Missing translation falls back to default locale (ro)
- ✅ Related entities (category in article) are translated
- ✅ Embedded entities (author in article) are translated if applicable
- ✅ Collection items are translated correctly

#### API Translation Headers

**Test Cases:**
- ✅ No `Accept-Language` header uses default locale (ro)
- ✅ `Accept-Language: en` returns English content
- ✅ `Accept-Language: ru` returns Russian content
- ✅ `Accept-Language: en-US` works (language code without region)
- ✅ `Accept-Language: en,ro;q=0.9` respects priority
- ✅ Invalid locale falls back to default (ro)
- ✅ Query parameter `?locale=en` overrides header (if implemented)

#### Translation Persistence

**Test Cases:**
- ✅ Create article with Romanian content
- ✅ Add English translation via PATCH
- ✅ Verify both translations persisted in database
- ✅ Update Romanian translation
- ✅ Verify English translation unchanged
- ✅ Delete English translation
- ✅ Verify Romanian translation remains

### 2. **Frontend Locale Routing**

#### URL Structure

**Expected URL Patterns:**
```
http://localhost:3005/ro              → Romanian homepage
http://localhost:3005/en              → English homepage
http://localhost:3005/ru              → Russian homepage
http://localhost:3005/ro/article/{slug}   → Romanian article
http://localhost:3005/en/article/{slug}   → English article
http://localhost:3005/ru/article/{slug}   → Russian article
http://localhost:3005/ro/category/{slug}  → Romanian category
```

**Test Cases:**
- ✅ Navigate to `/ro` displays Romanian content
- ✅ Navigate to `/en` displays English content
- ✅ Navigate to `/ru` displays Russian content
- ✅ Navigate to `/` redirects to default locale `/ro`
- ✅ Invalid locale `/invalid` redirects to `/ro`
- ✅ Locale prefix is preserved during navigation
- ✅ Article slugs are locale-specific
- ✅ Category slugs are locale-specific

#### Locale Detection

**Test Cases:**
- ✅ Browser language detection works (Accept-Language header)
- ✅ User can manually select locale
- ✅ Selected locale persists in cookie/localStorage
- ✅ Locale preference is remembered across sessions
- ✅ User can override detected locale

### 3. **Frontend UI Translation**

#### Static Text Translation

**Elements to Test:**
- Navigation menu labels
- Button labels (Read more, Share, etc.)
- Form labels and placeholders
- Error messages
- Loading states
- Empty states
- Footer text
- Meta tags (title, description)

**Test Cases:**
- ✅ All static text translates when locale changes
- ✅ Navigation menu items are translated
- ✅ Form labels are in correct language
- ✅ Error messages are translated
- ✅ Loading indicators show translated text
- ✅ 404 page is translated
- ✅ No untranslated text visible to user

#### Dynamic Content Translation

**Test Cases:**
- ✅ Article titles are translated
- ✅ Article content is translated
- ✅ Category names are translated
- ✅ Author names are displayed correctly (may not translate)
- ✅ Dates are formatted per locale
- ✅ Numbers are formatted per locale
- ✅ Currency is formatted per locale (if applicable)

### 4. **Locale Switching Flow**

#### User-Initiated Locale Change

**Test Steps:**
1. Navigate to Romanian homepage (`/ro`)
2. Verify content is in Romanian
3. Click locale switcher
4. Select English
5. Verify URL changes to `/en`
6. Verify content changes to English
7. Verify navigation maintains current page context
8. Click on article
9. Verify article URL is `/en/article/{slug}`
10. Verify article content is in English

**Test Cases:**
- ✅ Locale switcher displays all available languages
- ✅ Current locale is highlighted in switcher
- ✅ Clicking locale changes URL
- ✅ Page content updates without full reload (if SPA)
- ✅ Switching locale preserves current page (homepage → homepage)
- ✅ Switching locale preserves article context (article → same article)
- ✅ Switching locale preserves category context
- ✅ Browser back button works after locale switch

### 5. **Translation Fallback Mechanism**

#### Missing Translations

**Test Scenarios:**
1. Article has Romanian translation only
2. User switches to English
3. Application should display Romanian content (fallback)

**Test Cases:**
- ✅ Missing English translation shows Romanian
- ✅ Missing Russian translation shows Romanian
- ✅ Fallback is transparent to user
- ✅ No broken content or empty fields
- ✅ Related entities fall back correctly
- ✅ Navigation still works with fallback content

### 6. **SEO and Meta Tags**

#### Locale-Specific SEO

**Test Cases:**
- ✅ `<html lang="ro">` for Romanian pages
- ✅ `<html lang="en">` for English pages
- ✅ `<html lang="ru">` for Russian pages
- ✅ `hreflang` tags present for alternate locales
- ✅ Canonical URLs include locale prefix
- ✅ Meta title is translated
- ✅ Meta description is translated
- ✅ Open Graph tags are translated
- ✅ Twitter Card tags are translated
- ✅ Structured data includes correct language

**Example hreflang tags:**
```html
<link rel="alternate" hreflang="ro" href="http://localhost:3005/ro/article/slug" />
<link rel="alternate" hreflang="en" href="http://localhost:3005/en/article/slug" />
<link rel="alternate" hreflang="ru" href="http://localhost:3005/ru/article/slug" />
<link rel="alternate" hreflang="x-default" href="http://localhost:3005/ro/article/slug" />
```

### 7. **Date and Time Formatting**

**Test Cases:**
- ✅ Romanian: "25 noiembrie 2025, 14:30"
- ✅ English: "November 25, 2025, 2:30 PM"
- ✅ Russian: "25 ноября 2025 г., 14:30"
- ✅ Relative dates: "acum 2 ore" (ro), "2 hours ago" (en), "2 часа назад" (ru)
- ✅ Date format matches locale conventions
- ✅ Timezone is consistent across locales

### 8. **Number and Currency Formatting**

**Test Cases:**
- ✅ Romanian: "1.234,56" (decimal comma, thousand dot)
- ✅ English: "1,234.56" (decimal dot, thousand comma)
- ✅ Russian: "1 234,56" (decimal comma, thousand space)
- ✅ Article view count formatted per locale
- ✅ Currency symbols positioned correctly (if applicable)

### 9. **Search with Multilanguage**

**Test Cases:**
- ✅ Search in Romanian returns Romanian articles
- ✅ Search in English returns English articles
- ✅ Search in Russian returns Russian articles
- ✅ Search query is locale-aware
- ✅ Search results highlight correct translation
- ✅ No results message is translated

### 10. **Admin Panel Multilanguage**

**Test Cases:**
- ✅ Admin can create article in Romanian
- ✅ Admin can add English translation to article
- ✅ Admin can add Russian translation to article
- ✅ Translation form has tabs/sections for each locale
- ✅ Admin can switch between translation tabs
- ✅ Required fields are marked per locale
- ✅ Saving one translation doesn't affect others
- ✅ Preview shows correct translation
- ✅ Admin UI labels are translated (if multi-locale admin)

## Testing Workflow

### Step 1: Setup
1. Verify backend running on http://127.0.0.1:8081
2. Verify frontend running on http://localhost:3005
3. Verify test articles exist with translations
4. Verify database has translation tables

### Step 2: Execute Tests

#### Backend Translation Tests
```bash
# Test Romanian (default)
curl -H "Accept-Language: ro" http://127.0.0.1:8081/api/articles/1

# Test English
curl -H "Accept-Language: en" http://127.0.0.1:8081/api/articles/1

# Test Russian
curl -H "Accept-Language: ru" http://127.0.0.1:8081/api/articles/1
```

#### Frontend Translation Tests
```
1. Navigate to http://localhost:3005/ro
2. Screenshot: "homepage-ro.png"
3. Extract visible text
4. Verify Romanian labels present

5. Click locale switcher → Select "English"
6. Verify URL: http://localhost:3005/en
7. Screenshot: "homepage-en.png"
8. Extract visible text
9. Verify English labels present

10. Navigate to article
11. Verify URL: http://localhost:3005/en/article/{slug}
12. Screenshot: "article-en.png"
13. Verify article content is English
```

### Step 3: Report Results
- Compare screenshots across locales
- Verify all content is translated
- Identify missing translations
- Report fallback occurrences
- Generate multilanguage test report

## Playwright MCP Tools to Use

### Backend Testing
- `mcp__playwright__playwright_get` - Fetch API with Accept-Language header

### Frontend Testing
- `mcp__playwright__playwright_navigate` - Navigate to locale-specific URLs
- `mcp__playwright__playwright_click` - Click locale switcher
- `mcp__playwright__playwright_screenshot` - Capture each locale
- `mcp__playwright__playwright_get_visible_text` - Verify translated text
- `mcp__playwright__playwright_get_visible_html` - Check lang attribute

## Example Test Scenarios

### Scenario 1: Backend Article Translation
```
1. GET http://127.0.0.1:8081/api/articles/140
   Headers: Accept-Language: ro
2. Extract article title
3. Verify: Title is in Romanian

4. GET http://127.0.0.1:8081/api/articles/140
   Headers: Accept-Language: en
5. Extract article title
6. Verify: Title is in English

7. GET http://127.0.0.1:8081/api/articles/140
   Headers: Accept-Language: ru
8. Extract article title
9. Verify: Title is in Russian or falls back to Romanian
```

### Scenario 2: Frontend Locale Switching
```
1. Navigate: http://localhost:3005/ro
2. Get visible text
3. Assert: Contains Romanian text (e.g., "Citește mai mult")
4. Screenshot: "locale-ro.png"

5. Click: Locale switcher button
6. Click: "English" option
7. Wait for navigation
8. Assert URL: http://localhost:3005/en
9. Get visible text
10. Assert: Contains English text (e.g., "Read more")
11. Screenshot: "locale-en.png"

12. Click: Locale switcher button
13. Click: "Русский" option
14. Wait for navigation
15. Assert URL: http://localhost:3005/ru
16. Get visible text
17. Assert: Contains Russian text (e.g., "Читать далее")
18. Screenshot: "locale-ru.png"
```

### Scenario 3: Article Translation Flow
```
1. Navigate: http://localhost:3005/ro/article/test-articol
2. Get article title
3. Store: title_ro
4. Screenshot: "article-ro.png"

5. Click: Locale switcher → English
6. Assert URL: http://localhost:3005/en/article/test-article
7. Get article title
8. Store: title_en
9. Assert: title_en ≠ title_ro
10. Screenshot: "article-en.png"

11. Click: Locale switcher → Русский
12. Assert URL: http://localhost:3005/ru/article/test-статья
13. Get article title
14. Store: title_ru
15. Assert: title_ru ≠ title_ro AND title_ru ≠ title_en
16. Screenshot: "article-ru.png"
```

### Scenario 4: Fallback Mechanism
```
1. Backend: Create article with only Romanian translation
2. GET http://127.0.0.1:8081/api/articles/{id}
   Headers: Accept-Language: en
3. Verify: Returns Romanian content (fallback)
4. Frontend: Navigate to /en/article/{slug}
5. Get article content
6. Assert: Content is Romanian (fallback visible)
7. Assert: No broken/empty content
```

## Environment Configuration

```bash
DEFAULT_LOCALE=ro
AVAILABLE_LOCALES=ro,en,ru
FALLBACK_LOCALE=ro
LOCALE_COOKIE_NAME=locale
LOCALE_DETECTION=browser,cookie,url
```

## Test Execution Commands

```bash
# Run all multilanguage tests
pnpm test:i18n

# Test specific locale
LOCALE=en pnpm test:i18n

# Test fallback mechanism
pnpm test:i18n:fallback

# Test backend translations only
pnpm test:api:i18n

# Test frontend translations only
pnpm test:e2e:i18n
```

## Expected Outcomes

After running this agent:
- ✅ All three locales (ro, en, ru) work correctly
- ✅ Backend returns correct translations per Accept-Language
- ✅ Frontend displays correct language per URL locale
- ✅ Locale switching works seamlessly
- ✅ Fallback mechanism works for missing translations
- ✅ SEO tags are locale-specific
- ✅ Date/number formatting is locale-aware
- ✅ No untranslated content is visible
- ✅ Multilanguage test report is generated

## Integration with CI/CD

This agent can be integrated into CI/CD pipeline:
1. Run on every commit affecting translation files
2. Run on pull requests to ensure no translation regressions
3. Alert if new untranslated strings detected
4. Generate translation coverage report

## Troubleshooting

### Common Issues
1. **Translations not loading**: Verify Gedmo Translatable configured correctly
2. **Wrong locale displayed**: Check Accept-Language header / URL locale
3. **Fallback not working**: Verify default locale configured
4. **SEO tags missing**: Check Next.js metadata generation
5. **Locale switcher not working**: Verify routing configuration

## Agent Invocation

To use this agent, call:
```
Use the Task tool with:
- subagent_type: "general-purpose"
- prompt: "Run multilanguage tests following the multilanguage-tester agent specification"
```

Or invoke directly:
```
@multilanguage-tester test all locales
@multilanguage-tester test backend translations
@multilanguage-tester test frontend locale switching
@multilanguage-tester test fallback mechanism
@multilanguage-tester test SEO tags per locale
```
