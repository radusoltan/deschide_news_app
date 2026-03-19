# Next.js 16 Alignment Plan for Deschide News

**Document Version:** 1.0
**Created:** 2025-12-17
**Target Version:** Next.js 16.0.10 (Current)
**React Version:** 19.2.0

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Security Audit Checklist](#2-security-audit-checklist)
3. [Data Fetching Alignment](#3-data-fetching-alignment)
4. [Caching Strategy Overhaul](#4-caching-strategy-overhaul)
5. [Server Actions Review](#5-server-actions-review)
6. [Advanced Features Evaluation](#6-advanced-features-evaluation)
7. [Production Readiness Checklist](#7-production-readiness-checklist)
8. [Implementation Roadmap](#8-implementation-roadmap)

---

## 1. Executive Summary

### Current State Assessment

| Component | Current Status | Alignment Level |
|-----------|---------------|-----------------|
| **Next.js Version** | 16.0.10 (patched) | SECURE |
| **React Version** | 19.2.0 | CURRENT |
| **Middleware** | `middleware.ts` (deprecated) | NEEDS MIGRATION |
| **Caching** | `next: { revalidate }` | NEEDS ALIGNMENT |
| **Server Actions** | Basic implementation | NEEDS REVIEW |
| **Error Handling** | Missing global handlers | INCOMPLETE |
| **Parallel Routes** | Not implemented | OPPORTUNITY |
| **Data Fetching** | Traditional patterns | NEEDS MODERNIZATION |

### Target State

- Migrate `middleware.ts` to `proxy.ts`
- Implement `use cache` directive with `cacheTag()`
- Add comprehensive error boundary hierarchy
- Implement Data Access Layer pattern
- Add authorization checks in all Server Actions
- Evaluate Parallel Routes for admin dashboard

### Risk Assessment

| Risk | Severity | Mitigation |
|------|----------|------------|
| Security vulnerabilities | HIGH | Already patched (16.0.10) |
| Middleware deprecation | MEDIUM | Plan migration to proxy.ts |
| Caching inconsistency | MEDIUM | Implement use cache directive |
| Missing error pages | LOW | Add global-error.tsx |

---

## 2. Security Audit Checklist

### 2.1 CVE Status (RESOLVED)

The project is on **Next.js 16.0.10** which includes patches for:
- **CVE-2025-55184**: Server Actions authorization bypass
- **CVE-2025-55183**: Middleware security vulnerability

**Status:** SECURE - No action required for these CVEs.

### 2.2 Server Actions Authorization

**Current Implementation Review:**

File: `/var/www/deschide_news_app/apps/frontend/lib/auth/actions.ts`

```typescript
// CURRENT: Has basic session validation
export async function refreshSessionToken(): Promise<RefreshResult> {
  const cookie = (await cookies()).get('session')?.value;
  if (!cookie) {
    return { success: false, error: 'No session found' };
  }
  // ... rest of implementation
}
```

**Required Improvements:**

| Check | Status | Action Needed |
|-------|--------|---------------|
| Session validation in all actions | PARTIAL | Audit all action files |
| Input validation with Zod | NOT IMPLEMENTED | Add Zod schemas |
| Authorization re-check | PARTIAL | Add role checks |
| Rate limiting | NOT IMPLEMENTED | Add rate limiting |

**Recommended Pattern:**

```typescript
// lib/auth/authorize.ts
import { getSession } from '@/lib/auth/session';
import { z } from 'zod';

export async function authorizeAction<T>(
  schema: z.ZodSchema<T>,
  data: unknown,
  requiredRoles?: string[]
): Promise<{ success: true; data: T; session: SessionPayload } | { success: false; error: string }> {
  // 1. Get session
  const session = await getSession();
  if (!session) {
    return { success: false, error: 'Unauthorized: No session' };
  }

  // 2. Check roles if required
  if (requiredRoles && !requiredRoles.some(role => session.user.roles.includes(role))) {
    return { success: false, error: 'Forbidden: Insufficient permissions' };
  }

  // 3. Validate input
  const result = schema.safeParse(data);
  if (!result.success) {
    return { success: false, error: 'Validation failed: ' + result.error.message };
  }

  return { success: true, data: result.data, session };
}
```

### 2.3 Environment Variables Review

**Current Analysis:**

| Variable | Location | Security Status |
|----------|----------|-----------------|
| `SESSION_SECRET` | lib/auth/session.ts:16 | NEEDS PRODUCTION VALUE |
| `NEXT_PUBLIC_API_URL` | Multiple files | OK (public) |
| `NEXT_PUBLIC_CDN_URL` | Multiple files | OK (public) |
| `REVALIDATE_SECRET` | Not implemented | NEEDS IMPLEMENTATION |

**Required Actions:**

1. **SESSION_SECRET**: Ensure strong secret in production
   ```bash
   # .env.local (production)
   SESSION_SECRET=<32+ character random string>
   ```

2. **Implement REVALIDATE_SECRET** for On-Demand Revalidation:
   ```bash
   REVALIDATE_SECRET=<secure random string>
   FRONTEND_REVALIDATE_URL=https://deschide.md/api/revalidate
   ```

### 2.4 CSP Implementation Status

**Current Configuration** (next.config.mjs):

```javascript
// CURRENT: Basic CSP implemented
{
  key: 'Content-Security-Policy',
  value: [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' 'unsafe-eval'",  // NEEDS REVIEW
    "style-src 'self' 'unsafe-inline'",                  // NEEDS REVIEW
    // ...
  ].join('; '),
}
```

**Recommendations:**

| Directive | Current | Recommended | Priority |
|-----------|---------|-------------|----------|
| script-src | unsafe-inline, unsafe-eval | Nonce-based | MEDIUM |
| style-src | unsafe-inline | Nonce-based for production | LOW |
| frame-ancestors | 'none' | Keep current | OK |
| form-action | 'self' | Keep current | OK |

### 2.5 CSRF Protection

**Current Status:** Implicit via SameSite cookies

**Recommended Enhancement:**

```typescript
// lib/auth/csrf.ts
import { cookies, headers } from 'next/headers';
import crypto from 'crypto';

export async function generateCsrfToken(): Promise<string> {
  const token = crypto.randomBytes(32).toString('hex');
  (await cookies()).set('csrf-token', token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'strict',
  });
  return token;
}

export async function validateCsrfToken(token: string): Promise<boolean> {
  const storedToken = (await cookies()).get('csrf-token')?.value;
  return storedToken === token && token.length > 0;
}
```

---

## 3. Data Fetching Alignment

### 3.1 Current Patterns Audit

**Pattern 1: Direct fetch with revalidate** (Most Common)

Location: `/var/www/deschide_news_app/apps/frontend/lib/api/articles.ts`

```typescript
// CURRENT PATTERN
const response = await fetch(url.toString(), {
  method: 'GET',
  headers,
  next: {
    revalidate: 60, // Time-based revalidation
  },
});
```

**Pattern 2: Server Components with async data**

Location: `/var/www/deschide_news_app/apps/frontend/app/[locale]/(public)/page.tsx`

```typescript
// CURRENT: Good pattern - parallel data fetching
const [videosResponse, showsResponse] = await Promise.all([
  fetchHomepageVideos(12, locale),
  fetchVideoShows(locale),
]);
```

### 3.2 Next.js 16 Official Patterns

**Pattern A: use cache with cacheTag**

```typescript
// lib/data/articles.ts
import { cacheTag } from 'next/cache';

export async function getArticlesByCategory(categoryId: number, locale: string) {
  'use cache';
  cacheTag(`articles-category-${categoryId}`, `locale-${locale}`);

  const response = await fetch(`${API_URL}/api/articles?categoryId=${categoryId}`, {
    headers: { 'Accept-Language': locale },
  });

  return response.json();
}
```

**Pattern B: React cache() for ORM queries**

```typescript
// lib/data/cached-queries.ts
import { cache } from 'react';

export const getCategories = cache(async (locale: string) => {
  const response = await fetch(`${API_URL}/api/categories`, {
    headers: { 'Accept-Language': locale },
    cache: 'force-cache',
  });
  return response.json();
});
```

**Pattern C: Streaming with Suspense**

```typescript
// app/[locale]/(public)/page.tsx
import { Suspense } from 'react';

export default function HomePage({ params }) {
  return (
    <>
      {/* Critical content - render immediately */}
      <ImportantList locale={locale} />

      {/* Deferred content - stream when ready */}
      <Suspense fallback={<LatestNewsSkeleton />}>
        <LatestNews locale={locale} />
      </Suspense>

      <Suspense fallback={<VideoSliderSkeleton />}>
        <VideoShowsSlider locale={locale} />
      </Suspense>
    </>
  );
}
```

### 3.3 Migration Plan

| Current File | Current Pattern | Target Pattern | Priority |
|--------------|-----------------|----------------|----------|
| `lib/api/articles.ts` | fetch + revalidate | use cache + cacheTag | HIGH |
| `lib/api/categories.ts` | fetch + revalidate | React cache() | MEDIUM |
| `lib/api/important-articles.ts` | fetch + revalidate | use cache + cacheTag | HIGH |
| Homepage page.tsx | Sequential fetches | Promise.all + Suspense | MEDIUM |

### 3.4 Recommended Data Access Layer

Create a centralized Data Access Layer:

```
lib/
  data/
    index.ts              # Re-exports all data functions
    articles.ts           # Article data access
    categories.ts         # Category data access
    authors.ts            # Author data access
    cache-config.ts       # Cache tag definitions
```

**Example Implementation:**

```typescript
// lib/data/cache-config.ts
export const CACHE_TAGS = {
  articles: 'articles',
  articleById: (id: number) => `article-${id}`,
  articlesByCategory: (catId: number) => `articles-cat-${catId}`,
  categories: 'categories',
  categoryBySlug: (slug: string) => `category-${slug}`,
  authors: 'authors',
  homepage: 'homepage',
} as const;

// lib/data/articles.ts
import { cacheTag } from 'next/cache';
import { CACHE_TAGS } from './cache-config';

export async function getArticle(id: number, locale: string) {
  'use cache';
  cacheTag(CACHE_TAGS.articleById(id), `locale-${locale}`);

  // Data fetching logic
}

export async function getArticlesByCategory(categoryId: number, locale: string, limit = 10) {
  'use cache';
  cacheTag(CACHE_TAGS.articlesByCategory(categoryId), `locale-${locale}`);

  // Data fetching logic
}
```

---

## 4. Caching Strategy Overhaul

### 4.1 Current Caching Implementation

| Content Type | Current TTL | Location |
|--------------|-------------|----------|
| Homepage | 60s (ISR) | page.tsx revalidate |
| Articles by category | 120s | articles.ts fetch |
| Categories | 300s | categories.ts fetch |
| Static assets | 31536000s | middleware.ts |
| HTML pages | 60s | middleware.ts |

### 4.2 Next.js 16 Caching Architecture

**Key Changes in Next.js 16:**
- Fetch requests are NOT cached by default (opt-in model)
- `use cache` directive replaces `unstable_cache`
- `cacheTag()` for tagging cache entries
- `revalidateTag(tag, 'max')` requires cacheLife profile
- `updateTag()` for immediate expiration in Server Actions

### 4.3 New Caching Strategy

```
Cache Hierarchy
===============

L1: React cache()
    - In-memory, per-request deduplication
    - Use for: repeated data access within same request

L2: Next.js use cache
    - Server-side cache with tags
    - Use for: expensive computations, API calls

L3: ISR (Incremental Static Regeneration)
    - Page-level caching
    - Use for: public pages, SEO content

L4: CDN/Edge Cache
    - HTTP caching headers
    - Use for: static assets, images
```

### 4.4 Implementation: use cache Migration

**Before (Current):**
```typescript
// lib/api/articles.ts
export async function fetchLatestArticles(locale?: string, itemsPerPage = 10) {
  const response = await fetch(url.toString(), {
    next: { revalidate: 60 },
  });
  return response.json();
}
```

**After (Next.js 16 Pattern):**
```typescript
// lib/data/articles.ts
import { cacheTag } from 'next/cache';
import { CACHE_TAGS } from './cache-config';

export async function getLatestArticles(locale: string, limit = 10) {
  'use cache';
  cacheTag(CACHE_TAGS.articles, `locale-${locale}`, 'latest');

  const response = await fetch(
    `${API_URL}/api/articles?status=published&order[publishedAt]=desc&itemsPerPage=${limit}`,
    {
      headers: { 'Accept-Language': locale },
      cache: 'force-cache', // Explicit caching
    }
  );

  if (!response.ok) {
    throw new Error(`Failed to fetch articles: ${response.status}`);
  }

  return response.json();
}
```

### 4.5 Cache Tag Strategy

```typescript
// lib/data/cache-config.ts
export const CACHE_TAGS = {
  // Global tags
  all: 'all',

  // Content type tags
  articles: 'articles',
  categories: 'categories',
  authors: 'authors',

  // Specific entity tags
  articleById: (id: number) => `article-${id}`,
  articlesByCategory: (catId: number) => `articles-cat-${catId}`,
  categoryBySlug: (slug: string) => `category-${slug}`,

  // Page tags
  homepage: 'homepage',
  archivePage: (year: number, month?: number) =>
    month ? `archive-${year}-${month}` : `archive-${year}`,

  // Locale tags
  locale: (locale: string) => `locale-${locale}`,
} as const;
```

### 4.6 On-Demand Revalidation Implementation

**Create Revalidation Endpoint:**

```typescript
// app/api/revalidate/route.ts
import { NextRequest, NextResponse } from 'next/server';
import { revalidateTag } from 'next/cache';

export async function POST(request: NextRequest) {
  // 1. Validate secret
  const secret = request.headers.get('x-revalidate-secret');
  if (secret !== process.env.REVALIDATE_SECRET) {
    return NextResponse.json({ error: 'Invalid secret' }, { status: 401 });
  }

  // 2. Parse request
  const { tags, paths } = await request.json();

  try {
    // 3. Revalidate tags with stale-while-revalidate
    if (tags && Array.isArray(tags)) {
      for (const tag of tags) {
        revalidateTag(tag, 'max'); // Use 'max' cacheLife for SWR behavior
      }
    }

    // 4. Revalidate specific paths if needed
    if (paths && Array.isArray(paths)) {
      const { revalidatePath } = await import('next/cache');
      for (const path of paths) {
        revalidatePath(path, 'page');
      }
    }

    return NextResponse.json({
      revalidated: true,
      tags: tags || [],
      paths: paths || [],
      timestamp: Date.now(),
    });
  } catch (error) {
    console.error('[Revalidate API] Error:', error);
    return NextResponse.json({ error: 'Revalidation failed' }, { status: 500 });
  }
}
```

**Backend Integration (Symfony):**

```php
// src/EventSubscriber/CacheInvalidationSubscriber.php
class CacheInvalidationSubscriber implements EventSubscriberInterface
{
    public function onArticleUpdate(ArticleUpdatedEvent $event): void
    {
        $article = $event->getArticle();

        // Send revalidation request to Next.js
        $this->httpClient->request('POST', $_ENV['FRONTEND_REVALIDATE_URL'], [
            'headers' => [
                'x-revalidate-secret' => $_ENV['FRONTEND_REVALIDATE_SECRET'],
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'tags' => [
                    'article-' . $article->getId(),
                    'articles-cat-' . $article->getCategory()->getId(),
                    'homepage',
                ],
            ],
        ]);
    }
}
```

### 4.7 Cache TTL Strategy

| Content Type | Cache Tag | TTL | Invalidation |
|--------------|-----------|-----|--------------|
| Homepage | `homepage` | 60s ISR | On article publish |
| Article detail | `article-{id}` | use cache | On update |
| Category list | `articles-cat-{id}` | use cache | On article publish |
| Categories nav | `categories` | 5 min | On category update |
| Archive pages | `archive-{year}` | 1 hour | Time-based |
| Static pages | N/A | 1 day | Manual |

---

## 5. Server Actions Review

### 5.1 Current Implementation Audit

**Location:** `/var/www/deschide_news_app/apps/frontend/lib/auth/actions.ts`

**Current Actions:**
1. `refreshSessionToken()` - Refreshes JWT tokens
2. `updateSessionTokens()` - Updates session with new tokens

**Issues Identified:**

| Issue | Severity | Location |
|-------|----------|----------|
| No input validation | MEDIUM | Both actions |
| No rate limiting | LOW | Both actions |
| Hardcoded error messages | LOW | Both actions |
| No audit logging | LOW | Both actions |

### 5.2 Server Actions Best Practices

**1. Always validate inputs:**

```typescript
// lib/auth/actions.ts
'use server';

import { z } from 'zod';
import { cookies } from 'next/headers';

const TokensSchema = z.object({
  token: z.string().min(1),
  refresh_token: z.string().min(1),
  refresh_token_expires_at: z.number().positive(),
});

export async function updateSessionTokens(
  newTokens: unknown
): Promise<UpdateResult> {
  // 1. Validate input
  const result = TokensSchema.safeParse(newTokens);
  if (!result.success) {
    return { success: false, error: 'Invalid token format' };
  }

  // 2. Proceed with validated data
  const validatedTokens = result.data;
  // ... rest of implementation
}
```

**2. Implement progressive enhancement:**

```typescript
// components/LoginForm.tsx
'use client';

import { useActionState } from 'react';
import { login } from '@/lib/auth/actions';

export function LoginForm() {
  const [state, action, isPending] = useActionState(login, null);

  return (
    <form action={action}>
      <input type="email" name="email" required />
      <input type="password" name="password" required />
      <button type="submit" disabled={isPending}>
        {isPending ? 'Logging in...' : 'Login'}
      </button>
      {state?.error && <p className="error">{state.error}</p>}
    </form>
  );
}
```

**3. Cache invalidation after mutations:**

```typescript
// lib/actions/article-actions.ts
'use server';

import { revalidateTag, updateTag } from 'next/cache';
import { redirect } from 'next/navigation';

export async function publishArticle(articleId: number) {
  // 1. Validate authorization
  const session = await getSession();
  if (!session || !session.user.roles.includes('ROLE_EDITOR')) {
    return { error: 'Unauthorized' };
  }

  // 2. Perform mutation
  const response = await fetch(`${API_URL}/api/articles/${articleId}/publish`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${session.tokens.accessToken}` },
  });

  if (!response.ok) {
    return { error: 'Failed to publish article' };
  }

  // 3. Invalidate cache BEFORE redirect (Next.js 16 requirement)
  updateTag(`article-${articleId}`); // Immediate expiration for read-your-own-writes
  revalidateTag('homepage', 'max');
  revalidateTag('articles', 'max');

  // 4. Redirect
  redirect(`/article/${articleId}`);
}
```

### 5.3 Error Handling in Server Actions

```typescript
// lib/actions/safe-action.ts
'use server';

import { z } from 'zod';

export type ActionResult<T> =
  | { success: true; data: T }
  | { success: false; error: string; fieldErrors?: Record<string, string[]> };

export async function safeAction<TInput, TOutput>(
  schema: z.ZodSchema<TInput>,
  handler: (data: TInput) => Promise<TOutput>,
  input: unknown
): Promise<ActionResult<TOutput>> {
  try {
    // Validate input
    const result = schema.safeParse(input);
    if (!result.success) {
      return {
        success: false,
        error: 'Validation failed',
        fieldErrors: result.error.flatten().fieldErrors as Record<string, string[]>,
      };
    }

    // Execute handler
    const data = await handler(result.data);
    return { success: true, data };
  } catch (error) {
    console.error('[Server Action Error]', error);
    return {
      success: false,
      error: error instanceof Error ? error.message : 'An unexpected error occurred',
    };
  }
}
```

---

## 6. Advanced Features Evaluation

### 6.1 Parallel Routes Opportunities

**Current Admin Structure:** Not implemented (dashboard not found)

**Recommended Implementation for Admin Dashboard:**

```
app/
  [locale]/
    (admin)/
      dashboard/
        layout.tsx          # Dashboard layout with slots
        page.tsx            # Default dashboard content
        @stats/
          page.tsx          # Statistics panel
          loading.tsx       # Independent loading state
        @articles/
          page.tsx          # Recent articles panel
          loading.tsx
        @activity/
          page.tsx          # Activity feed
          loading.tsx
        default.tsx         # Fallback for unmatched slots
```

**Layout Implementation:**

```typescript
// app/[locale]/(admin)/dashboard/layout.tsx
export default function DashboardLayout({
  children,
  stats,
  articles,
  activity,
}: {
  children: React.ReactNode;
  stats: React.ReactNode;
  articles: React.ReactNode;
  activity: React.ReactNode;
}) {
  return (
    <div className="dashboard-grid">
      <header className="dashboard-header">
        {children}
      </header>
      <aside className="dashboard-stats">
        {stats}
      </aside>
      <main className="dashboard-articles">
        {articles}
      </main>
      <aside className="dashboard-activity">
        {activity}
      </aside>
    </div>
  );
}
```

**Benefits:**
- Independent loading states per panel
- Independent error boundaries
- Better perceived performance
- Cleaner code organization

### 6.2 Proxy Configuration (middleware.ts -> proxy.ts)

**Current Status:** Using deprecated `middleware.ts`

**Migration Steps:**

1. Rename file:
   ```bash
   mv middleware.ts proxy.ts
   ```

2. Update function name:
   ```typescript
   // proxy.ts (AFTER)
   export async function proxy(request: NextRequest) {
     // Same logic as middleware
   }
   ```

3. Or use codemod:
   ```bash
   npx @next/codemod middleware-to-proxy
   ```

**Important Changes:**
- Proxy runs on Node.js runtime (not Edge)
- Edge runtime is deprecated in proxy
- Focus on lightweight routing logic

**Recommended proxy.ts:**

```typescript
// proxy.ts
import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';
import { i18nRouter } from 'next-i18n-router';
import i18nConfig from './i18nConfig';

export async function proxy(request: NextRequest) {
  const path = request.nextUrl.pathname;

  // Skip for static assets
  if (path.startsWith('/.well-known')) {
    return NextResponse.next();
  }

  // Quick auth check (lightweight - no DB calls)
  const isProtectedRoute = path.startsWith('/dashboard') || path.startsWith('/admin');
  const hasSession = request.cookies.has('session');

  if (isProtectedRoute && !hasSession) {
    const loginUrl = new URL('/login', request.url);
    loginUrl.searchParams.set('from', path);
    return NextResponse.redirect(loginUrl);
  }

  // i18n routing
  return i18nRouter(request, i18nConfig);
}

export const config = {
  matcher: [
    '/((?!api|_next/static|_next/image|favicon.ico|robots.txt|.*\\.xml|.*\\.(jpg|jpeg|png|gif|svg|ico|css|js|woff|woff2)).*)',
  ],
};
```

### 6.3 Streaming Boundaries Optimization

**Current:** Limited streaming implementation

**Recommended Pattern:**

```typescript
// app/[locale]/(public)/page.tsx
import { Suspense } from 'react';
import {
  ImportantListSkeleton,
  LatestNewsSkeleton,
  CategorySectionSkeleton,
  VideoSliderSkeleton,
} from '@/components/skeletons';

export default async function HomePage({ params }: PageProps) {
  const { locale } = await params;

  return (
    <>
      {/* Critical above-fold content - no suspense, render immediately */}
      <section className="section-bg-white section-py-md">
        <ImportantList locale={locale} />
      </section>

      {/* Secondary content - stream progressively */}
      <section className="section-bg-white section-py-lg">
        <Suspense fallback={<LatestNewsSkeleton />}>
          <LatestNews locale={locale} />
        </Suspense>
      </section>

      {/* Below-fold content - lower priority streaming */}
      <Suspense fallback={<VideoSliderSkeleton />}>
        <VideoShowsSlider locale={locale} />
      </Suspense>

      {/* Category sections - stream independently */}
      <Suspense fallback={<CategorySectionSkeleton />}>
        <CategorySections locale={locale} />
      </Suspense>
    </>
  );
}
```

---

## 7. Production Readiness Checklist

### 7.1 Missing Error Pages

| File | Status | Required Action |
|------|--------|-----------------|
| `app/global-error.tsx` | MISSING | Create |
| `app/not-found.tsx` | MISSING | Create |
| `app/global-not-found.tsx` | MISSING | Create (with config flag) |
| `app/[locale]/(public)/error.tsx` | MISSING | Create |

**Implementation: global-error.tsx**

```typescript
// app/global-error.tsx
'use client';

import { useEffect } from 'react';

export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    // Log to error reporting service
    console.error('[Global Error]', error);
  }, [error]);

  return (
    <html lang="en">
      <body>
        <div className="min-h-screen flex items-center justify-center bg-gray-100">
          <div className="text-center">
            <h1 className="text-4xl font-bold text-gray-900 mb-4">
              Something went wrong
            </h1>
            <p className="text-gray-600 mb-8">
              We apologize for the inconvenience. Please try again.
            </p>
            <button
              onClick={reset}
              className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
            >
              Try again
            </button>
          </div>
        </div>
      </body>
    </html>
  );
}
```

**Implementation: not-found.tsx**

```typescript
// app/not-found.tsx
import Link from 'next/link';

export default function NotFound() {
  return (
    <div className="min-h-screen flex items-center justify-center">
      <div className="text-center">
        <h1 className="text-6xl font-bold text-gray-900 mb-4">404</h1>
        <h2 className="text-2xl font-semibold text-gray-700 mb-4">
          Page Not Found
        </h2>
        <p className="text-gray-600 mb-8">
          The page you are looking for does not exist.
        </p>
        <Link
          href="/"
          className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
        >
          Go Home
        </Link>
      </div>
    </div>
  );
}
```

### 7.2 SEO Improvements

**Current Status:** Good foundation (sitemap.ts, robots.ts exist)

**Required Improvements:**

| Item | Status | Action |
|------|--------|--------|
| News Sitemap | CONFIGURED | Verify implementation |
| Image Sitemap | CONFIGURED | Verify implementation |
| Archive Sitemap | CONFIGURED | Verify implementation |
| Structured Data | IMPLEMENTED | Audit completeness |
| Meta Tags | IMPLEMENTED | Verify all pages |
| Canonical URLs | PARTIAL | Audit dynamic routes |

### 7.3 Performance Monitoring

**Current:** Basic Web Vitals endpoint exists

**Recommended Enhancements:**

```typescript
// app/api/web-vitals/route.ts
import { NextRequest, NextResponse } from 'next/server';

interface WebVitalMetric {
  name: 'LCP' | 'INP' | 'CLS' | 'FCP' | 'TTFB';
  value: number;
  rating: 'good' | 'needs-improvement' | 'poor';
  url: string;
  navigationType: string;
}

export async function POST(request: NextRequest) {
  const metric: WebVitalMetric = await request.json();

  // Log to console in development
  if (process.env.NODE_ENV === 'development') {
    console.log('[Web Vitals]', metric);
  }

  // Send to analytics in production
  if (process.env.NODE_ENV === 'production' && process.env.ANALYTICS_ENDPOINT) {
    await fetch(process.env.ANALYTICS_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        ...metric,
        timestamp: Date.now(),
        userAgent: request.headers.get('user-agent'),
      }),
    });
  }

  return NextResponse.json({ success: true });
}
```

### 7.4 Bundle Optimization

**Current Tools:** Bundle analyzer configured

**Analysis Command:**
```bash
cd /var/www/deschide_news_app/apps/frontend
ANALYZE=true pnpm build
```

**Optimization Checklist:**

| Optimization | Status | Impact |
|--------------|--------|--------|
| Tree shaking | ENABLED | HIGH |
| Code splitting | AUTOMATIC | HIGH |
| Dynamic imports | PARTIAL | MEDIUM |
| Package optimization | CONFIGURED | MEDIUM |
| Image optimization | ENABLED | HIGH |

**Package Optimization (Current):**
```javascript
// next.config.mjs
experimental: {
  optimizePackageImports: ['lucide-react', 'date-fns', '@heroicons/react'],
}
```

**Additional Packages to Optimize:**
```javascript
optimizePackageImports: [
  'lucide-react',
  'date-fns',
  '@heroicons/react',
  'react-icons',  // ADD
  'recharts',     // ADD
  'flowbite-react', // ADD
]
```

---

## 8. Implementation Roadmap

### Phase 1: Security (Week 1-2) - CRITICAL

| Task | Priority | Effort | Owner |
|------|----------|--------|-------|
| Add Zod validation to Server Actions | P0 | 4h | Dev |
| Implement authorization wrapper | P0 | 4h | Dev |
| Create CSRF protection | P1 | 2h | Dev |
| Audit environment variables | P0 | 1h | DevOps |
| Add rate limiting | P1 | 3h | Dev |

**Deliverables:**
- [ ] `lib/auth/authorize.ts` - Authorization wrapper
- [ ] `lib/auth/csrf.ts` - CSRF protection
- [ ] Updated `lib/auth/actions.ts` with validation
- [ ] Environment variables documentation

### Phase 2: Caching Alignment (Week 2-3) - HIGH IMPACT

| Task | Priority | Effort | Owner |
|------|----------|--------|-------|
| Create Data Access Layer structure | P0 | 4h | Dev |
| Implement use cache in articles.ts | P0 | 4h | Dev |
| Implement cache tags strategy | P0 | 3h | Dev |
| Create revalidation endpoint | P0 | 3h | Dev |
| Update backend for ODR | P1 | 4h | Backend |

**Deliverables:**
- [ ] `lib/data/` - Data Access Layer
- [ ] `lib/data/cache-config.ts` - Cache tags
- [ ] `app/api/revalidate/route.ts` - ODR endpoint
- [ ] Backend CacheInvalidationSubscriber

### Phase 3: Data Fetching Optimization (Week 3-4)

| Task | Priority | Effort | Owner |
|------|----------|--------|-------|
| Add Suspense boundaries to homepage | P1 | 3h | Dev |
| Create skeleton components | P1 | 4h | Dev |
| Migrate middleware to proxy | P1 | 2h | Dev |
| Implement streaming for lists | P2 | 3h | Dev |

**Deliverables:**
- [ ] `proxy.ts` - Migrated from middleware
- [ ] `components/skeletons/` - Skeleton components
- [ ] Updated page.tsx files with Suspense

### Phase 4: Error Handling & Production (Week 4-5)

| Task | Priority | Effort | Owner |
|------|----------|--------|-------|
| Create global-error.tsx | P0 | 2h | Dev |
| Create not-found.tsx | P0 | 2h | Dev |
| Create error.tsx for routes | P1 | 3h | Dev |
| Bundle analysis and optimization | P1 | 4h | Dev |

**Deliverables:**
- [ ] `app/global-error.tsx`
- [ ] `app/not-found.tsx`
- [ ] `app/[locale]/(public)/error.tsx`
- [ ] Bundle optimization report

### Phase 5: Advanced Features (Week 5-6) - OPTIONAL

| Task | Priority | Effort | Owner |
|------|----------|--------|-------|
| Evaluate Parallel Routes for admin | P2 | 4h | Dev |
| Implement admin dashboard slots | P2 | 8h | Dev |
| Performance testing | P1 | 4h | QA |

**Deliverables:**
- [ ] Admin dashboard with Parallel Routes
- [ ] Performance test results
- [ ] Final alignment report

---

## Summary

### Quick Wins (Do First)
1. Migrate `middleware.ts` to `proxy.ts` (2h)
2. Create error pages (4h)
3. Add input validation to Server Actions (4h)

### High Impact Changes
1. Implement Data Access Layer with `use cache` (8h)
2. Create on-demand revalidation endpoint (3h)
3. Add Suspense boundaries for streaming (4h)

### Technical Debt to Address
1. CSP hardening (remove unsafe-inline where possible)
2. Bundle optimization (add more packages to optimize)
3. Comprehensive error boundary hierarchy

---

## References

### Official Documentation
- [Next.js 16 Release Notes](https://nextjs.org/blog/next-16)
- [Caching and Revalidating](https://nextjs.org/docs/app/getting-started/caching-and-revalidating)
- [use cache Directive](https://nextjs.org/docs/app/api-reference/directives/use-cache)
- [cacheTag Function](https://nextjs.org/docs/app/api-reference/functions/cacheTag)
- [revalidateTag Function](https://nextjs.org/docs/app/api-reference/functions/revalidateTag)
- [updateTag Function](https://nextjs.org/docs/app/api-reference/functions/updateTag)
- [Proxy File Convention](https://nextjs.org/docs/app/api-reference/file-conventions/proxy)
- [Parallel Routes](https://nextjs.org/docs/app/api-reference/file-conventions/parallel-routes)
- [Error Handling](https://nextjs.org/docs/app/getting-started/error-handling)
- [Authentication Guide](https://nextjs.org/docs/app/guides/authentication)
- [Data Security Guide](https://nextjs.org/docs/app/guides/data-security)

### Security Resources
- [Next.js Security Update 2025-12-11](https://nextjs.org/blog/security-update-2025-12-11)
- [How to Think About Security in Next.js](https://nextjs.org/blog/security-nextjs-server-components-actions)
- [Upgrading to Version 16](https://nextjs.org/docs/app/guides/upgrading/version-16)

### Additional Resources
- [Middleware to Proxy Migration](https://nextjs.org/docs/messages/middleware-to-proxy)
- [Next.js Production Checklist](https://nextjs.org/docs/app/guides/production-checklist)
