# Strategia de Testare Smoke & Performanță

**Document**: Smoke Testing & Performance Testing Strategy
**Proiect**: Deschide News App (Monorepo)
**Data**: 2025-12-02
**Versiune**: 1.0

---

## Cuprins

1. [Strategia de Testare Smoke](#1-strategia-de-testare-smoke)
2. [Strategia de Testare Performanță](#2-strategia-de-testare-performanță)
3. [Implementare CI/CD](#3-implementare-cicd)
4. [Instrumente și Configurare](#4-instrumente-și-configurare)
5. [Anexe](#5-anexe)

---

## 1. Strategia de Testare Smoke

### 1.1 Definiție și Scop

**Smoke testing** (Build Verification Testing) este o suită rapidă de teste care verifică dacă funcționalitățile critice ale aplicației funcționează corect după un deployment sau build. Scopul este detectarea timpurie a problemelor majore.

### 1.2 Principii

| Principiu | Descriere |
|-----------|-----------|
| **Viteză** | Toate testele smoke trebuie să ruleze în < 5 minute |
| **Criticitate** | Testează doar funcționalități critice (happy path) |
| **Automatizare** | 100% automatizate, fără intervenție manuală |
| **Fiabilitate** | Zero flaky tests - rezultate consistente |
| **Blocare** | Eșecul smoke tests blochează deployment-ul |

### 1.3 Structura Smoke Tests

```
smoke-tests/
├── backend/
│   ├── health-check.smoke.ts      # Verificări de sănătate
│   ├── api-endpoints.smoke.ts     # Endpoint-uri critice
│   ├── database.smoke.ts          # Conexiune DB
│   └── services.smoke.ts          # Servicii externe
├── frontend/
│   ├── pages-load.smoke.ts        # Încărcarea paginilor
│   ├── navigation.smoke.ts        # Navigare de bază
│   └── auth.smoke.ts              # Autentificare
└── integration/
    ├── api-frontend.smoke.ts      # Backend → Frontend
    └── data-flow.smoke.ts         # Flux de date complet
```

### 1.4 Backend Smoke Tests

#### 1.4.1 Health Check Endpoints

```typescript
// smoke-tests/backend/health-check.smoke.ts

describe('Backend Health Checks', () => {
  const BACKEND_URL = 'http://127.0.0.1:8081';
  const TIMEOUT = 5000;

  test('API is reachable', async () => {
    const response = await fetch(`${BACKEND_URL}/api`, { timeout: TIMEOUT });
    expect(response.status).toBe(200);
    expect(response.headers.get('content-type')).toContain('application/ld+json');
  });

  test('PostgreSQL connection', async () => {
    const response = await fetch(`${BACKEND_URL}/api/health/database`);
    expect(response.status).toBe(200);
    const data = await response.json();
    expect(data.status).toBe('healthy');
  });

  test('Redis connection', async () => {
    const response = await fetch(`${BACKEND_URL}/api/health/redis`);
    expect(response.status).toBe(200);
  });

  test('Elasticsearch connection', async () => {
    const response = await fetch(`${BACKEND_URL}/api/health/elasticsearch`);
    expect(response.status).toBe(200);
  });
});
```

#### 1.4.2 API Endpoints Critice

```typescript
// smoke-tests/backend/api-endpoints.smoke.ts

describe('Critical API Endpoints', () => {
  const BACKEND_URL = 'http://127.0.0.1:8081';

  // Read operations (public)
  test('GET /api/articles returns 200', async () => {
    const response = await fetch(`${BACKEND_URL}/api/articles`);
    expect(response.status).toBe(200);
    const data = await response.json();
    expect(data['hydra:member']).toBeDefined();
  });

  test('GET /api/categories returns 200', async () => {
    const response = await fetch(`${BACKEND_URL}/api/categories`);
    expect(response.status).toBe(200);
  });

  test('GET /api/important_articles_lists returns 200', async () => {
    const response = await fetch(`${BACKEND_URL}/api/important_articles_lists`);
    expect(response.status).toBe(200);
  });

  // Multilingual support
  test('Accept-Language header works for Romanian', async () => {
    const response = await fetch(`${BACKEND_URL}/api/articles`, {
      headers: { 'Accept-Language': 'ro' }
    });
    expect(response.status).toBe(200);
  });

  test('Accept-Language header works for English', async () => {
    const response = await fetch(`${BACKEND_URL}/api/articles`, {
      headers: { 'Accept-Language': 'en' }
    });
    expect(response.status).toBe(200);
  });

  // Authentication endpoint
  test('POST /api/login_check returns 401 without credentials', async () => {
    const response = await fetch(`${BACKEND_URL}/api/login_check`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({})
    });
    expect(response.status).toBe(401);
  });

  // Protected endpoint check
  test('POST /api/articles returns 401 without JWT', async () => {
    const response = await fetch(`${BACKEND_URL}/api/articles`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/ld+json' },
      body: JSON.stringify({ title: 'Test' })
    });
    expect(response.status).toBe(401);
  });
});
```

#### 1.4.3 Database Smoke Tests

```typescript
// smoke-tests/backend/database.smoke.ts

describe('Database Smoke Tests', () => {
  test('Articles table is accessible', async () => {
    const response = await fetch('http://127.0.0.1:8081/api/articles?itemsPerPage=1');
    expect(response.status).toBe(200);
  });

  test('Categories table is accessible', async () => {
    const response = await fetch('http://127.0.0.1:8081/api/categories?itemsPerPage=1');
    expect(response.status).toBe(200);
  });

  test('Authors table is accessible', async () => {
    const response = await fetch('http://127.0.0.1:8081/api/authors?itemsPerPage=1');
    expect(response.status).toBe(200);
  });

  test('Images table is accessible', async () => {
    const response = await fetch('http://127.0.0.1:8081/api/images?itemsPerPage=1');
    expect(response.status).toBe(200);
  });
});
```

### 1.5 Frontend Smoke Tests

#### 1.5.1 Page Load Tests

```typescript
// smoke-tests/frontend/pages-load.smoke.ts

import { test, expect } from '@playwright/test';

test.describe('Frontend Page Load Smoke Tests', () => {
  const FRONTEND_URL = 'http://localhost:3005';

  test('Homepage loads successfully', async ({ page }) => {
    const response = await page.goto(`${FRONTEND_URL}/ro`);
    expect(response?.status()).toBe(200);
    await expect(page).toHaveTitle(/Deschide/i);
  });

  test('Category page loads', async ({ page }) => {
    await page.goto(`${FRONTEND_URL}/ro/category/politica`);
    await expect(page.locator('h1')).toBeVisible();
  });

  test('Article page loads', async ({ page }) => {
    // Navigate to first article from homepage
    await page.goto(`${FRONTEND_URL}/ro`);
    const firstArticle = page.locator('article a').first();
    if (await firstArticle.isVisible()) {
      await firstArticle.click();
      await expect(page.locator('article')).toBeVisible();
    }
  });

  test('Admin login page loads', async ({ page }) => {
    const response = await page.goto(`${FRONTEND_URL}/ro/login`);
    expect(response?.status()).toBe(200);
  });

  test('Archive page loads', async ({ page }) => {
    await page.goto(`${FRONTEND_URL}/ro/archive`);
    await expect(page.locator('main')).toBeVisible();
  });
});
```

#### 1.5.2 Navigation Smoke Tests

```typescript
// smoke-tests/frontend/navigation.smoke.ts

import { test, expect } from '@playwright/test';

test.describe('Navigation Smoke Tests', () => {
  test('Main navigation is functional', async ({ page }) => {
    await page.goto('http://localhost:3005/ro');

    // Header nav exists
    await expect(page.locator('header nav')).toBeVisible();

    // Category links work
    const categoryLink = page.locator('nav a[href*="category"]').first();
    if (await categoryLink.isVisible()) {
      await categoryLink.click();
      await expect(page).toHaveURL(/category/);
    }
  });

  test('Language switcher works', async ({ page }) => {
    await page.goto('http://localhost:3005/ro');

    const langSwitcher = page.locator('[data-testid="language-switcher"]');
    if (await langSwitcher.isVisible()) {
      await langSwitcher.click();
      // Select English
      await page.locator('text=English').click();
      await expect(page).toHaveURL(/\/en\//);
    }
  });

  test('Footer links are present', async ({ page }) => {
    await page.goto('http://localhost:3005/ro');
    await expect(page.locator('footer')).toBeVisible();
  });
});
```

### 1.6 Integration Smoke Tests

```typescript
// smoke-tests/integration/api-frontend.smoke.ts

import { test, expect } from '@playwright/test';

test.describe('API-Frontend Integration Smoke Tests', () => {
  test('Homepage displays articles from API', async ({ page }) => {
    await page.goto('http://localhost:3005/ro');

    // Wait for articles to load
    await page.waitForSelector('article', { timeout: 10000 });

    // At least one article should be visible
    const articles = page.locator('article');
    expect(await articles.count()).toBeGreaterThan(0);
  });

  test('Category page shows filtered content', async ({ page }) => {
    await page.goto('http://localhost:3005/ro/category/politica');

    // Wait for content
    await page.waitForLoadState('networkidle');

    // Content should be present
    await expect(page.locator('main')).toBeVisible();
  });

  test('Search functionality works', async ({ page }) => {
    await page.goto('http://localhost:3005/ro');

    const searchInput = page.locator('input[type="search"], [data-testid="search-input"]');
    if (await searchInput.isVisible()) {
      await searchInput.fill('test');
      await searchInput.press('Enter');
      await page.waitForLoadState('networkidle');
      // Should navigate or show results
      await expect(page.locator('main')).toBeVisible();
    }
  });
});
```

### 1.7 Servicii Externe Smoke Tests

```bash
#!/bin/bash
# smoke-tests/services-check.sh

echo "=== Services Smoke Check ==="

# PostgreSQL
echo -n "PostgreSQL: "
if pg_isready -h 127.0.0.1 -p 5432 -U deschide_user > /dev/null 2>&1; then
    echo "✅ OK"
else
    echo "❌ FAIL"
    exit 1
fi

# Redis
echo -n "Redis: "
if redis-cli -n 1 PING | grep -q PONG; then
    echo "✅ OK"
else
    echo "❌ FAIL"
    exit 1
fi

# Elasticsearch
echo -n "Elasticsearch: "
if curl -s -k https://localhost:9200/_cluster/health -u elastic:WsAEcDWAbQjb5XGUnpvk 2>/dev/null | grep -q '"status"'; then
    echo "✅ OK"
else
    echo "⚠️ WARNING (may require auth)"
fi

# Backend API
echo -n "Backend API: "
if curl -s http://127.0.0.1:8081/api | grep -q '@context'; then
    echo "✅ OK"
else
    echo "❌ FAIL"
    exit 1
fi

# Frontend
echo -n "Frontend: "
if curl -s http://localhost:3005/ro | grep -q '<html'; then
    echo "✅ OK"
else
    echo "❌ FAIL"
    exit 1
fi

# CDN
echo -n "CDN: "
if curl -s http://127.0.0.1:8082 > /dev/null 2>&1; then
    echo "✅ OK"
else
    echo "⚠️ WARNING (may not be running)"
fi

echo ""
echo "=== Smoke Check Complete ==="
```

### 1.8 Matricea Smoke Tests

| Test Category | # Tests | Max Duration | Blocker |
|---------------|---------|--------------|---------|
| Backend Health | 4 | 30s | ✅ Yes |
| Backend API | 7 | 60s | ✅ Yes |
| Database | 4 | 30s | ✅ Yes |
| Frontend Pages | 5 | 90s | ✅ Yes |
| Navigation | 3 | 60s | ⚠️ Warning |
| Integration | 3 | 90s | ⚠️ Warning |
| **TOTAL** | **26** | **< 5 min** | - |

---

## 2. Strategia de Testare Performanță

### 2.1 Obiective

| Obiectiv | Descriere |
|----------|-----------|
| **Baseline** | Stabilirea metricilor de referință |
| **Regresie** | Detectarea degradării performanței |
| **Capacitate** | Determinarea limitelor sistemului |
| **Optimizare** | Identificarea bottleneck-urilor |

### 2.2 Tipuri de Teste de Performanță

```
┌─────────────────────────────────────────────────────────────────┐
│                   PIRAMIDA TESTELOR DE PERFORMANȚĂ               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│                        ┌──────────┐                              │
│                        │  STRESS  │    ← 500+ utilizatori       │
│                        │   TEST   │    ← Găsește breaking point  │
│                        └──────────┘                              │
│                     ┌──────────────────┐                         │
│                     │   LOAD TEST      │  ← 100 utilizatori      │
│                     │   (Sarcină)      │  ← Validează capacitate │
│                     └──────────────────┘                         │
│                  ┌─────────────────────────┐                     │
│                  │   SOAK TEST (Endurance) │ ← 50 users x 1 oră  │
│                  │   Stabilitate în timp    │ ← Memory leaks     │
│                  └─────────────────────────┘                     │
│               ┌────────────────────────────────┐                 │
│               │      PERFORMANCE TEST          │ ← Metrici       │
│               │   (Core Web Vitals, API)       │ ← Lighthouse    │
│               └────────────────────────────────┘                 │
│            ┌───────────────────────────────────────┐             │
│            │         BENCHMARK TEST                │ ← Zilnic    │
│            │  (Response times, throughput)         │ ← CI/CD     │
│            └───────────────────────────────────────┘             │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 2.3 Metrici Target (SLA)

#### 2.3.1 Core Web Vitals (Frontend)

| Metric | Target | Warning | Critical |
|--------|--------|---------|----------|
| **LCP** (Largest Contentful Paint) | < 2.5s | < 4.0s | > 4.0s |
| **INP** (Interaction to Next Paint) | < 200ms | < 500ms | > 500ms |
| **CLS** (Cumulative Layout Shift) | < 0.1 | < 0.25 | > 0.25 |
| **FCP** (First Contentful Paint) | < 1.8s | < 3.0s | > 3.0s |
| **TTFB** (Time to First Byte) | < 600ms | < 1.5s | > 1.5s |

#### 2.3.2 API Response Times (Backend)

| Endpoint | p50 Target | p95 Target | p99 Target |
|----------|------------|------------|------------|
| `GET /api/articles` | < 100ms | < 200ms | < 500ms |
| `GET /api/articles/{id}` | < 80ms | < 150ms | < 300ms |
| `GET /api/categories` | < 50ms | < 100ms | < 200ms |
| `GET /api/important_articles` | < 100ms | < 200ms | < 400ms |
| `POST /api/articles` | < 200ms | < 400ms | < 800ms |
| `GET /api/search` | < 300ms | < 500ms | < 1000ms |

#### 2.3.3 Cache Performance

| Layer | Hit Rate Target | Response Time |
|-------|-----------------|---------------|
| **L1 (APCu)** | > 90% | < 1ms |
| **L2 (Redis)** | > 80% | < 5ms |
| **L3 (CDN/ISR)** | > 70% | < 50ms |

#### 2.3.4 Load Capacity

| Metric | Target |
|--------|--------|
| Concurrent Users | 100 (normal), 500 (peak) |
| Requests/second | > 500 RPS |
| Error Rate | < 0.1% |
| Availability | 99.9% |

### 2.4 Instrumente de Testare Performanță

#### 2.4.1 k6 (Load Testing) - RECOMANDAT

```javascript
// k6/load-test.js

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

// Custom metrics
const errorRate = new Rate('errors');
const articlesTrend = new Trend('articles_duration');

export const options = {
  stages: [
    { duration: '1m', target: 10 },   // Ramp up
    { duration: '3m', target: 50 },   // Stay at 50 users
    { duration: '2m', target: 100 },  // Peak
    { duration: '1m', target: 0 },    // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'],    // 95% requests < 500ms
    http_req_failed: ['rate<0.01'],      // Error rate < 1%
    errors: ['rate<0.05'],               // Custom error rate < 5%
  },
};

const BASE_URL = 'http://127.0.0.1:8081';

export default function () {
  // Homepage API
  const articlesRes = http.get(`${BASE_URL}/api/articles?itemsPerPage=30`, {
    headers: { 'Accept-Language': 'ro' },
  });

  articlesTrend.add(articlesRes.timings.duration);

  check(articlesRes, {
    'articles status is 200': (r) => r.status === 200,
    'articles response < 500ms': (r) => r.timings.duration < 500,
  }) || errorRate.add(1);

  sleep(1);

  // Category page
  const categoriesRes = http.get(`${BASE_URL}/api/categories`);
  check(categoriesRes, {
    'categories status is 200': (r) => r.status === 200,
  });

  sleep(1);

  // Single article
  const singleArticle = http.get(`${BASE_URL}/api/articles/1`);
  check(singleArticle, {
    'article status is 200 or 404': (r) => r.status === 200 || r.status === 404,
  });

  sleep(Math.random() * 2);
}
```

#### 2.4.2 k6 Stress Test

```javascript
// k6/stress-test.js

import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '2m', target: 100 },   // Normal load
    { duration: '5m', target: 200 },   // High load
    { duration: '2m', target: 300 },   // Stress
    { duration: '5m', target: 500 },   // Breaking point
    { duration: '2m', target: 0 },     // Recovery
  ],
  thresholds: {
    http_req_duration: ['p(99)<2000'],   // 99% < 2s (relaxed)
    http_req_failed: ['rate<0.10'],      // Allow 10% errors in stress
  },
};

export default function () {
  const res = http.get('http://127.0.0.1:8081/api/articles');
  check(res, {
    'status is 200': (r) => r.status === 200,
  });
  sleep(0.5);
}
```

#### 2.4.3 k6 Soak Test (Endurance)

```javascript
// k6/soak-test.js

import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '5m', target: 50 },    // Ramp up
    { duration: '60m', target: 50 },   // Soak for 1 hour
    { duration: '5m', target: 0 },     // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500', 'p(99)<1000'],
    http_req_failed: ['rate<0.01'],
  },
};

export default function () {
  http.get('http://127.0.0.1:8081/api/articles');
  sleep(2);
}
```

### 2.5 Lighthouse CI Configuration

```javascript
// lighthouserc.js

module.exports = {
  ci: {
    collect: {
      url: [
        'http://localhost:3005/ro',
        'http://localhost:3005/ro/category/politica',
        'http://localhost:3005/ro/article/test-article',
      ],
      numberOfRuns: 3,
      settings: {
        preset: 'desktop',
      },
    },
    assert: {
      assertions: {
        'categories:performance': ['error', { minScore: 0.9 }],
        'categories:accessibility': ['warn', { minScore: 0.9 }],
        'categories:best-practices': ['warn', { minScore: 0.9 }],
        'categories:seo': ['warn', { minScore: 0.9 }],

        // Core Web Vitals
        'largest-contentful-paint': ['error', { maxNumericValue: 2500 }],
        'cumulative-layout-shift': ['error', { maxNumericValue: 0.1 }],
        'total-blocking-time': ['error', { maxNumericValue: 200 }],
        'first-contentful-paint': ['warn', { maxNumericValue: 1800 }],
        'speed-index': ['warn', { maxNumericValue: 3400 }],
      },
    },
    upload: {
      target: 'temporary-public-storage',
    },
  },
};
```

### 2.6 Playwright Performance Tests

```typescript
// tests/performance/core-web-vitals.spec.ts

import { test, expect } from '@playwright/test';

test.describe('Core Web Vitals Performance', () => {
  test('Homepage meets Core Web Vitals targets', async ({ page }) => {
    await page.goto('http://localhost:3005/ro');

    // Wait for full load
    await page.waitForLoadState('networkidle');

    // Get performance metrics
    const metrics = await page.evaluate(() => {
      const timing = performance.timing;
      const paint = performance.getEntriesByType('paint');

      return {
        ttfb: timing.responseStart - timing.requestStart,
        fcp: paint.find(p => p.name === 'first-contentful-paint')?.startTime || 0,
        domContentLoaded: timing.domContentLoadedEventEnd - timing.navigationStart,
        loadComplete: timing.loadEventEnd - timing.navigationStart,
      };
    });

    console.log('Performance Metrics:', metrics);

    // Assertions
    expect(metrics.ttfb).toBeLessThan(600);
    expect(metrics.fcp).toBeLessThan(1800);
    expect(metrics.domContentLoaded).toBeLessThan(2500);
    expect(metrics.loadComplete).toBeLessThan(4000);
  });

  test('Article page LCP under 2.5s', async ({ page }) => {
    // Navigate to article
    await page.goto('http://localhost:3005/ro');
    const articleLink = page.locator('article a').first();

    if (await articleLink.isVisible()) {
      const startTime = Date.now();
      await articleLink.click();
      await page.waitForLoadState('networkidle');
      const loadTime = Date.now() - startTime;

      expect(loadTime).toBeLessThan(2500);
    }
  });

  test('No layout shift during page load', async ({ page }) => {
    await page.goto('http://localhost:3005/ro');

    // Monitor for layout shifts
    const cls = await page.evaluate(() => {
      return new Promise((resolve) => {
        let clsValue = 0;
        const observer = new PerformanceObserver((list) => {
          for (const entry of list.getEntries()) {
            if (!(entry as any).hadRecentInput) {
              clsValue += (entry as any).value;
            }
          }
        });

        observer.observe({ type: 'layout-shift', buffered: true });

        setTimeout(() => {
          observer.disconnect();
          resolve(clsValue);
        }, 3000);
      });
    });

    expect(cls).toBeLessThan(0.1);
  });
});
```

### 2.7 API Performance Tests

```typescript
// tests/performance/api-performance.spec.ts

import { test, expect } from '@playwright/test';

test.describe('API Performance Tests', () => {
  const BACKEND_URL = 'http://127.0.0.1:8081';

  test('Articles list responds under 200ms', async ({ request }) => {
    const startTime = Date.now();
    const response = await request.get(`${BACKEND_URL}/api/articles`);
    const duration = Date.now() - startTime;

    expect(response.status()).toBe(200);
    expect(duration).toBeLessThan(200);

    console.log(`GET /api/articles: ${duration}ms`);
  });

  test('Single article responds under 150ms', async ({ request }) => {
    const startTime = Date.now();
    const response = await request.get(`${BACKEND_URL}/api/articles/1`);
    const duration = Date.now() - startTime;

    // 200 or 404 is fine
    expect([200, 404]).toContain(response.status());
    expect(duration).toBeLessThan(150);

    console.log(`GET /api/articles/1: ${duration}ms`);
  });

  test('Categories respond under 100ms', async ({ request }) => {
    const startTime = Date.now();
    const response = await request.get(`${BACKEND_URL}/api/categories`);
    const duration = Date.now() - startTime;

    expect(response.status()).toBe(200);
    expect(duration).toBeLessThan(100);
  });

  test('Concurrent requests handled efficiently', async ({ request }) => {
    const promises = Array(10).fill(null).map(() =>
      request.get(`${BACKEND_URL}/api/articles`)
    );

    const startTime = Date.now();
    const responses = await Promise.all(promises);
    const duration = Date.now() - startTime;

    responses.forEach(r => expect(r.status()).toBe(200));

    // 10 concurrent requests should complete in < 1s
    expect(duration).toBeLessThan(1000);

    console.log(`10 concurrent requests: ${duration}ms (${duration/10}ms avg)`);
  });
});
```

### 2.8 Database Performance Monitoring

```sql
-- scripts/db-performance-check.sql

-- 1. Slow queries (> 100ms)
SELECT
    query,
    calls,
    total_time / calls as avg_time_ms,
    rows / calls as avg_rows
FROM pg_stat_statements
WHERE total_time / calls > 100
ORDER BY total_time DESC
LIMIT 10;

-- 2. Table sizes and bloat
SELECT
    schemaname || '.' || relname AS table,
    pg_size_pretty(pg_total_relation_size(relid)) AS total_size,
    n_live_tup AS live_tuples,
    n_dead_tup AS dead_tuples,
    ROUND(100.0 * n_dead_tup / NULLIF(n_live_tup + n_dead_tup, 0), 2) AS dead_percent
FROM pg_stat_user_tables
ORDER BY pg_total_relation_size(relid) DESC
LIMIT 10;

-- 3. Index usage
SELECT
    schemaname || '.' || relname AS table,
    indexrelname AS index,
    idx_scan AS scans,
    idx_tup_read AS tuples_read,
    idx_tup_fetch AS tuples_fetched
FROM pg_stat_user_indexes
WHERE idx_scan = 0
ORDER BY pg_relation_size(indexrelid) DESC
LIMIT 10;

-- 4. Cache hit ratio
SELECT
    'index hit rate' AS metric,
    ROUND(100.0 * sum(idx_blks_hit) / NULLIF(sum(idx_blks_hit + idx_blks_read), 0), 2) AS ratio
FROM pg_statio_user_indexes
UNION ALL
SELECT
    'table hit rate',
    ROUND(100.0 * sum(heap_blks_hit) / NULLIF(sum(heap_blks_hit + heap_blks_read), 0), 2)
FROM pg_statio_user_tables;

-- 5. Connection stats
SELECT
    state,
    count(*) as count,
    max(now() - state_change) as longest_duration
FROM pg_stat_activity
WHERE datname = 'deschide_news'
GROUP BY state;
```

### 2.9 Cache Performance Monitoring

```bash
#!/bin/bash
# scripts/cache-performance.sh

echo "=== Cache Performance Report ==="
echo ""

# Redis stats
echo "📦 Redis (L2 Cache):"
echo "-------------------"
redis-cli -n 1 INFO stats | grep -E "keyspace_hits|keyspace_misses|expired_keys"
HIT=$(redis-cli -n 1 INFO stats | grep keyspace_hits | cut -d: -f2 | tr -d '\r')
MISS=$(redis-cli -n 1 INFO stats | grep keyspace_misses | cut -d: -f2 | tr -d '\r')
if [ "$HIT" -gt 0 ] && [ "$MISS" -gt 0 ]; then
    RATIO=$(echo "scale=2; $HIT * 100 / ($HIT + $MISS)" | bc)
    echo "Hit Rate: ${RATIO}%"
fi
echo ""

# Redis memory
echo "💾 Redis Memory:"
redis-cli -n 1 INFO memory | grep -E "used_memory_human|used_memory_peak_human|maxmemory_human"
echo ""

# Database size
echo "🗄️ Database Keys:"
redis-cli -n 1 DBSIZE
echo ""

# APCu (if accessible via PHP)
echo "📦 APCu (L1 Cache):"
echo "-------------------"
php -r "
if (function_exists('apcu_cache_info')) {
    \$info = apcu_cache_info();
    \$hits = \$info['nhits'] ?? 0;
    \$misses = \$info['nmisses'] ?? 0;
    \$total = \$hits + \$misses;
    \$ratio = \$total > 0 ? round((\$hits / \$total) * 100, 2) : 0;
    echo \"Hits: \$hits\nMisses: \$misses\nHit Rate: {\$ratio}%\n\";
} else {
    echo \"APCu not available\n\";
}
" 2>/dev/null || echo "Unable to check APCu"
echo ""
```

### 2.10 Raport de Performanță

```markdown
# Performance Test Report Template

## Summary
- **Date**: YYYY-MM-DD
- **Environment**: Development / Staging / Production
- **Duration**: X hours
- **Status**: ✅ Pass / ⚠️ Warning / ❌ Fail

## Core Web Vitals

| Page | LCP | INP | CLS | FCP | TTFB | Status |
|------|-----|-----|-----|-----|------|--------|
| Homepage | Xms | Xms | X | Xms | Xms | ✅/❌ |
| Article | Xms | Xms | X | Xms | Xms | ✅/❌ |
| Category | Xms | Xms | X | Xms | Xms | ✅/❌ |

## API Response Times

| Endpoint | p50 | p95 | p99 | Target | Status |
|----------|-----|-----|-----|--------|--------|
| GET /api/articles | Xms | Xms | Xms | <200ms | ✅/❌ |
| GET /api/categories | Xms | Xms | Xms | <100ms | ✅/❌ |

## Load Test Results

| Metric | Value | Target | Status |
|--------|-------|--------|--------|
| Max Concurrent Users | X | 100 | ✅/❌ |
| Avg Response Time | Xms | <500ms | ✅/❌ |
| Error Rate | X% | <0.1% | ✅/❌ |
| Throughput | X RPS | >500 | ✅/❌ |

## Cache Performance

| Layer | Hit Rate | Target | Status |
|-------|----------|--------|--------|
| L1 (APCu) | X% | >90% | ✅/❌ |
| L2 (Redis) | X% | >80% | ✅/❌ |
| L3 (CDN) | X% | >70% | ✅/❌ |

## Recommendations
1. ...
2. ...
3. ...
```

---

## 3. Implementare CI/CD

### 3.1 GitHub Actions - Smoke Tests

```yaml
# .github/workflows/smoke-tests.yml

name: Smoke Tests

on:
  deployment_status:
  workflow_dispatch:
  schedule:
    - cron: '*/30 * * * *'  # Every 30 minutes

jobs:
  smoke-tests:
    runs-on: ubuntu-latest
    if: github.event_name != 'deployment_status' || github.event.deployment_status.state == 'success'

    steps:
      - uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'

      - name: Install dependencies
        run: pnpm install

      - name: Run smoke tests
        run: pnpm test:smoke
        env:
          BACKEND_URL: ${{ secrets.BACKEND_URL }}
          FRONTEND_URL: ${{ secrets.FRONTEND_URL }}

      - name: Notify on failure
        if: failure()
        uses: slackapi/slack-github-action@v1.24.0
        with:
          payload: |
            {
              "text": "🚨 Smoke tests failed!",
              "blocks": [
                {
                  "type": "section",
                  "text": {
                    "type": "mrkdwn",
                    "text": "*Smoke Tests Failed* on `${{ github.ref_name }}`\n<${{ github.server_url }}/${{ github.repository }}/actions/runs/${{ github.run_id }}|View Details>"
                  }
                }
              ]
            }
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK }}
```

### 3.2 GitHub Actions - Performance Tests

```yaml
# .github/workflows/performance-tests.yml

name: Performance Tests

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]
  schedule:
    - cron: '0 2 * * *'  # Daily at 2 AM

jobs:
  lighthouse:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'

      - name: Install Lighthouse CI
        run: npm install -g @lhci/cli@0.12.x

      - name: Run Lighthouse CI
        run: lhci autorun
        env:
          LHCI_GITHUB_APP_TOKEN: ${{ secrets.LHCI_GITHUB_APP_TOKEN }}

      - name: Upload results
        uses: actions/upload-artifact@v4
        with:
          name: lighthouse-results
          path: .lighthouseci
          retention-days: 30

  k6-load-test:
    runs-on: ubuntu-latest
    needs: lighthouse

    steps:
      - uses: actions/checkout@v4

      - name: Run k6 load test
        uses: grafana/k6-action@v0.3.1
        with:
          filename: k6/load-test.js
          flags: --out json=results.json

      - name: Upload k6 results
        uses: actions/upload-artifact@v4
        with:
          name: k6-results
          path: results.json

  performance-report:
    runs-on: ubuntu-latest
    needs: [lighthouse, k6-load-test]

    steps:
      - name: Download artifacts
        uses: actions/download-artifact@v4

      - name: Generate report
        run: |
          echo "# Performance Test Results" > report.md
          echo "Date: $(date)" >> report.md
          # Parse and add results

      - name: Comment on PR
        if: github.event_name == 'pull_request'
        uses: actions/github-script@v7
        with:
          script: |
            const fs = require('fs');
            const report = fs.readFileSync('report.md', 'utf8');
            github.rest.issues.createComment({
              issue_number: context.issue.number,
              owner: context.repo.owner,
              repo: context.repo.repo,
              body: report
            });
```

### 3.3 Package.json Scripts

```json
{
  "scripts": {
    "test:smoke": "playwright test --project=smoke --reporter=list",
    "test:smoke:backend": "jest --testPathPattern=smoke-tests/backend",
    "test:smoke:frontend": "playwright test smoke-tests/frontend",

    "test:performance": "npm-run-all test:performance:*",
    "test:performance:lighthouse": "lhci autorun",
    "test:performance:k6": "k6 run k6/load-test.js",
    "test:performance:api": "playwright test tests/performance/api",
    "test:performance:vitals": "playwright test tests/performance/core-web-vitals",

    "test:load": "k6 run k6/load-test.js",
    "test:stress": "k6 run k6/stress-test.js",
    "test:soak": "k6 run k6/soak-test.js",

    "perf:report": "node scripts/generate-perf-report.js"
  }
}
```

---

## 4. Instrumente și Configurare

### 4.1 Instalare Instrumente

```bash
# k6 (Load Testing)
sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update
sudo apt-get install k6

# Lighthouse CI
npm install -g @lhci/cli

# Playwright (already installed)
pnpm add -D @playwright/test
```

### 4.2 Structura Finală

```
deschide_news_app/
├── smoke-tests/
│   ├── backend/
│   │   ├── health-check.smoke.ts
│   │   ├── api-endpoints.smoke.ts
│   │   ├── database.smoke.ts
│   │   └── services.smoke.ts
│   ├── frontend/
│   │   ├── pages-load.smoke.ts
│   │   ├── navigation.smoke.ts
│   │   └── auth.smoke.ts
│   ├── integration/
│   │   ├── api-frontend.smoke.ts
│   │   └── data-flow.smoke.ts
│   └── services-check.sh
├── k6/
│   ├── load-test.js
│   ├── stress-test.js
│   ├── soak-test.js
│   └── scenarios/
│       ├── homepage.js
│       ├── article-flow.js
│       └── search.js
├── tests/
│   └── performance/
│       ├── core-web-vitals.spec.ts
│       ├── api-performance.spec.ts
│       └── cache-performance.spec.ts
├── scripts/
│   ├── cache-performance.sh
│   ├── db-performance-check.sql
│   └── generate-perf-report.js
├── lighthouserc.js
└── docs/testing/
    └── SMOKE_AND_PERFORMANCE_TESTING_STRATEGY.md
```

---

## 5. Anexe

### 5.1 Checklist Pre-Deployment

```markdown
## Pre-Deployment Smoke Test Checklist

### Infrastructure
- [ ] PostgreSQL connection OK
- [ ] Redis connection OK
- [ ] Elasticsearch connection OK
- [ ] Mercure Hub running

### Backend
- [ ] API entrypoint responds (GET /api)
- [ ] Articles endpoint works (GET /api/articles)
- [ ] Categories endpoint works (GET /api/categories)
- [ ] Authentication works (POST /api/login_check)
- [ ] Multilingual support works (Accept-Language header)

### Frontend
- [ ] Homepage loads (< 3s)
- [ ] Navigation works
- [ ] Article pages load
- [ ] Admin panel accessible
- [ ] Language switcher works

### Integration
- [ ] API → Frontend data flow works
- [ ] Images load from CDN
- [ ] Search functionality works
```

### 5.2 Performance Baseline (TBD)

```
| Metric | Baseline | Target |
|--------|----------|--------|
| Homepage LCP | TBD | < 2.5s |
| API p95 | TBD | < 200ms |
| Load capacity | TBD | 100 concurrent |
| Cache hit rate | TBD | > 80% |
```

### 5.3 Alerting Thresholds

| Metric | Warning | Critical | Action |
|--------|---------|----------|--------|
| API p95 | > 300ms | > 500ms | Investigate |
| Error rate | > 0.5% | > 1% | Page on-call |
| LCP | > 3s | > 4s | Investigate |
| Cache hit | < 70% | < 50% | Check cache config |

---

## Concluzie

Această strategie oferă:

1. **Smoke Tests** (26 teste, < 5 minute):
   - Verifică funcționalitățile critice
   - Rulează după fiecare deployment
   - Blochează release-ul în caz de eșec

2. **Performance Tests**:
   - Core Web Vitals monitoring
   - API response time tracking
   - Load/Stress/Soak testing cu k6
   - Lighthouse CI pentru regresia performanței

3. **Integrare CI/CD**:
   - Smoke tests automate la fiecare deployment
   - Performance tests zilnice
   - Rapoarte automate pe PR-uri

**Următorii pași**:
1. Crearea endpoint-ului `/api/health` pentru backend
2. Implementarea testelor smoke
3. Configurarea k6 și Lighthouse CI
4. Stabilirea baseline-ului de performanță
5. Integrarea în GitHub Actions
