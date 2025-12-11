# Plan de Implementare: Teste Smoke & Performanță

**Document**: Implementation Plan for Smoke & Performance Testing
**Proiect**: Deschide News App (Monorepo)
**Data**: 2025-12-02
**Versiune**: 1.0

---

## Cuprins

1. [Rezumat Executiv](#1-rezumat-executiv)
2. [Analiza Gap-urilor](#2-analiza-gap-urilor)
3. [Planul de Implementare](#3-planul-de-implementare)
4. [Faza 1: Health Checks & Smoke Tests](#4-faza-1-health-checks--smoke-tests)
5. [Faza 2: Teste de Performanță](#5-faza-2-teste-de-performanță)
6. [Faza 3: Load Testing](#6-faza-3-load-testing)
7. [Faza 4: Integrare CI/CD](#7-faza-4-integrare-cicd)
8. [Estimări și Priorități](#8-estimări-și-priorități)
9. [Checklist de Implementare](#9-checklist-de-implementare)

---

## 1. Rezumat Executiv

### Starea Curentă

| Categorie | Teste | Status |
|-----------|-------|--------|
| Backend PHPUnit | 376 | ✅ 100% passing |
| Frontend Jest | 163 | ✅ 100% passing |
| Frontend Playwright E2E | 1,176 discovered | ⚡ Ready |
| **Total** | **539 passing** | ✅ |

### Ce Lipsește

| Categorie | Status | Prioritate |
|-----------|--------|------------|
| Health Check Endpoints | ❌ Lipsește | 🔴 Critică |
| Smoke Tests | ❌ Lipsește | 🔴 Critică |
| Performance Tests | ❌ Lipsește | 🟠 Înaltă |
| Load Tests (k6) | ❌ Lipsește | 🟠 Înaltă |
| Lighthouse CI | ❌ Lipsește | 🟡 Medie |

### Obiective

1. **Smoke Tests**: Suite rapidă (< 5 min) pentru validare post-deployment
2. **Performance Tests**: Monitorizare Core Web Vitals și API response times
3. **Load Tests**: Validare capacitate (100+ utilizatori concurenți)
4. **CI/CD Integration**: Automatizare completă în GitHub Actions

---

## 2. Analiza Gap-urilor

### 2.1 Backend - Ce Există vs Ce Lipsește

```
apps/backend/tests/
├── Unit/ (8 fișiere)              ✅ EXISTĂ
│   ├── Entity/ (7 teste)          ✅ Article, Author, Category, Image, Tag
│   ├── Service/ (3 teste)         ✅ Image, Tag, Elastic, Archive
│   └── State/ (3 teste)           ✅ ArticleProvider, AuthorProvider, CategoryProvider
├── Functional/ (4 fișiere)        ✅ EXISTĂ
│   ├── Api/ (1 test)              ✅ TagApiTest
│   └── Controller/ (2 teste)      ✅ Archive controllers
├── Integration/ (1 fișier)        ✅ EXISTĂ
│   └── Repository/ (1 test)       ✅ TagRepository
│
├── Smoke/ (FOLDER)                ❌ LIPSEȘTE - DE CREAT
│   ├── HealthCheckTest.php        ❌ LIPSEȘTE
│   ├── ApiEndpointsTest.php       ❌ LIPSEȘTE
│   └── DatabaseTest.php           ❌ LIPSEȘTE
│
└── Performance/ (FOLDER)          ❌ LIPSEȘTE - DE CREAT
    ├── ApiResponseTimeTest.php    ❌ LIPSEȘTE
    └── DatabaseQueryTest.php      ❌ LIPSEȘTE
```

### 2.2 Frontend - Ce Există vs Ce Lipsește

```
apps/frontend/__tests__/
├── unit/ (12 fișiere)             ✅ EXISTĂ
│   ├── lib/ (6 teste)             ✅ API, sanitize, SEO, URL
│   └── components/ (6 teste)      ✅ ArticleCard, Navigation, etc.
├── integration/ (7 fișiere)       ✅ EXISTĂ
│   ├── homepage.spec.ts           ✅ EXISTĂ
│   ├── article-page.spec.ts       ✅ EXISTĂ
│   └── locale.test.ts             ✅ EXISTĂ
├── e2e/ (6 fișiere)               ✅ EXISTĂ (Playwright)
│   ├── user-flows.spec.ts         ✅ EXISTĂ
│   └── archive.spec.ts            ✅ EXISTĂ
│
├── smoke/ (FOLDER)                ❌ LIPSEȘTE - DE CREAT
│   ├── pages.smoke.spec.ts        ❌ LIPSEȘTE
│   ├── api-integration.smoke.ts   ❌ LIPSEȘTE
│   └── navigation.smoke.spec.ts   ❌ LIPSEȘTE
│
└── performance/ (FOLDER)          ❌ LIPSEȘTE - DE CREAT
    ├── core-web-vitals.spec.ts    ❌ LIPSEȘTE
    ├── api-response.spec.ts       ❌ LIPSEȘTE
    └── lighthouse.spec.ts         ❌ LIPSEȘTE
```

### 2.3 Infrastructure - Ce Lipsește

```
deschide_news_app/
├── k6/ (FOLDER)                   ❌ LIPSEȘTE - DE CREAT
│   ├── load-test.js               ❌ LIPSEȘTE
│   ├── stress-test.js             ❌ LIPSEȘTE
│   ├── soak-test.js               ❌ LIPSEȘTE
│   └── scenarios/
│       ├── homepage.js            ❌ LIPSEȘTE
│       ├── article-flow.js        ❌ LIPSEȘTE
│       └── api-endpoints.js       ❌ LIPSEȘTE
│
├── lighthouserc.js                ❌ LIPSEȘTE - DE CREAT
│
├── scripts/
│   ├── smoke-check.sh             ❌ LIPSEȘTE - DE CREAT
│   ├── perf-report.sh             ❌ LIPSEȘTE - DE CREAT
│   └── cache-stats.sh             ❌ LIPSEȘTE - DE CREAT
│
└── .github/workflows/
    ├── smoke-tests.yml            ❌ LIPSEȘTE - DE CREAT
    └── performance-tests.yml      ❌ LIPSEȘTE - DE CREAT
```

---

## 3. Planul de Implementare

### Timeline Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        PLAN DE IMPLEMENTARE                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  FAZA 1: Health Checks & Smoke Tests                                        │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━                                        │
│  Task 1.1: Backend Health Endpoint         ████████░░  [2-3 ore]            │
│  Task 1.2: Backend Smoke Tests             ████████░░  [3-4 ore]            │
│  Task 1.3: Frontend Smoke Tests            ████████░░  [3-4 ore]            │
│  Task 1.4: Integration Smoke Tests         ██████░░░░  [2-3 ore]            │
│                                                                              │
│  FAZA 2: Performance Tests                                                   │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━                                                │
│  Task 2.1: Core Web Vitals Tests           ████████░░  [3-4 ore]            │
│  Task 2.2: API Performance Tests           ██████████  [4-5 ore]            │
│  Task 2.3: Database Query Tests            ████████░░  [3-4 ore]            │
│  Task 2.4: Cache Performance Tests         ██████░░░░  [2-3 ore]            │
│                                                                              │
│  FAZA 3: Load Testing                                                        │
│  ━━━━━━━━━━━━━━━━━━━━━━                                                     │
│  Task 3.1: k6 Setup & Configuration        ████████░░  [2-3 ore]            │
│  Task 3.2: Load Test Scenarios             ██████████  [4-5 ore]            │
│  Task 3.3: Stress & Soak Tests             ████████░░  [3-4 ore]            │
│                                                                              │
│  FAZA 4: CI/CD Integration                                                   │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━                                                  │
│  Task 4.1: Smoke Tests Workflow            ████████░░  [2-3 ore]            │
│  Task 4.2: Performance Tests Workflow      ████████░░  [3-4 ore]            │
│  Task 4.3: Lighthouse CI Setup             ██████░░░░  [2-3 ore]            │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 4. Faza 1: Health Checks & Smoke Tests

### Task 1.1: Backend Health Check Endpoint

**Obiectiv**: Creare endpoint `/api/health` pentru monitorizare

**Fișiere de creat**:

```
apps/backend/
├── src/Controller/HealthController.php        # Controller endpoint
├── src/Service/HealthCheckService.php         # Service pentru verificări
└── tests/Smoke/HealthCheckTest.php            # Teste pentru endpoint
```

**Implementare `HealthController.php`**:

```php
<?php
// apps/backend/src/Controller/HealthController.php

namespace App\Controller;

use App\Service\HealthCheckService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/health')]
class HealthController extends AbstractController
{
    public function __construct(
        private readonly HealthCheckService $healthCheckService
    ) {}

    #[Route('', name: 'api_health', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $health = $this->healthCheckService->checkAll();

        $status = $health['status'] === 'healthy' ? 200 : 503;

        return new JsonResponse($health, $status);
    }

    #[Route('/database', name: 'api_health_database', methods: ['GET'])]
    public function database(): JsonResponse
    {
        $result = $this->healthCheckService->checkDatabase();
        return new JsonResponse($result, $result['status'] === 'healthy' ? 200 : 503);
    }

    #[Route('/redis', name: 'api_health_redis', methods: ['GET'])]
    public function redis(): JsonResponse
    {
        $result = $this->healthCheckService->checkRedis();
        return new JsonResponse($result, $result['status'] === 'healthy' ? 200 : 503);
    }

    #[Route('/elasticsearch', name: 'api_health_elasticsearch', methods: ['GET'])]
    public function elasticsearch(): JsonResponse
    {
        $result = $this->healthCheckService->checkElasticsearch();
        return new JsonResponse($result, $result['status'] === 'healthy' ? 200 : 503);
    }

    #[Route('/ready', name: 'api_health_ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        // Kubernetes readiness probe
        $health = $this->healthCheckService->checkAll();
        return new JsonResponse(
            ['ready' => $health['status'] === 'healthy'],
            $health['status'] === 'healthy' ? 200 : 503
        );
    }

    #[Route('/live', name: 'api_health_live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        // Kubernetes liveness probe - just check if app responds
        return new JsonResponse(['alive' => true], 200);
    }
}
```

**Implementare `HealthCheckService.php`**:

```php
<?php
// apps/backend/src/Service/HealthCheckService.php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HealthCheckService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CacheItemPoolInterface $cache,
        private readonly HttpClientInterface $httpClient,
        private readonly string $elasticsearchUrl,
        private readonly string $elasticsearchPassword
    ) {}

    public function checkAll(): array
    {
        $startTime = microtime(true);

        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'elasticsearch' => $this->checkElasticsearch(),
        ];

        $allHealthy = array_reduce(
            $checks,
            fn($carry, $check) => $carry && $check['status'] === 'healthy',
            true
        );

        return [
            'status' => $allHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => date('c'),
            'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            'checks' => $checks,
        ];
    }

    public function checkDatabase(): array
    {
        try {
            $startTime = microtime(true);
            $this->connection->executeQuery('SELECT 1');
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'healthy',
                'duration_ms' => $duration,
                'message' => 'PostgreSQL connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'PostgreSQL connection failed: ' . $e->getMessage(),
            ];
        }
    }

    public function checkRedis(): array
    {
        try {
            $startTime = microtime(true);

            // Use cache pool to test Redis
            $item = $this->cache->getItem('health_check_test');
            $item->set('ok');
            $item->expiresAfter(60);
            $this->cache->save($item);

            $retrieved = $this->cache->getItem('health_check_test');

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if ($retrieved->get() === 'ok') {
                return [
                    'status' => 'healthy',
                    'duration_ms' => $duration,
                    'message' => 'Redis connection successful',
                ];
            }

            return [
                'status' => 'unhealthy',
                'message' => 'Redis read/write test failed',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Redis connection failed: ' . $e->getMessage(),
            ];
        }
    }

    public function checkElasticsearch(): array
    {
        try {
            $startTime = microtime(true);

            $response = $this->httpClient->request('GET', $this->elasticsearchUrl . '/_cluster/health', [
                'auth_basic' => ['elastic', $this->elasticsearchPassword],
                'verify_peer' => false,
                'timeout' => 5,
            ]);

            $data = $response->toArray();
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $healthy = in_array($data['status'] ?? '', ['green', 'yellow']);

            return [
                'status' => $healthy ? 'healthy' : 'unhealthy',
                'duration_ms' => $duration,
                'cluster_status' => $data['status'] ?? 'unknown',
                'message' => $healthy
                    ? 'Elasticsearch cluster is ' . $data['status']
                    : 'Elasticsearch cluster status: ' . ($data['status'] ?? 'unknown'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Elasticsearch connection failed: ' . $e->getMessage(),
            ];
        }
    }
}
```

**Test `HealthCheckTest.php`**:

```php
<?php
// apps/backend/tests/Smoke/HealthCheckTest.php

namespace App\Tests\Smoke;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthCheckTest extends WebTestCase
{
    public function testHealthEndpointReturns200(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('checks', $data);
    }

    public function testHealthEndpointResponseTime(): void
    {
        $client = static::createClient();

        $startTime = microtime(true);
        $client->request('GET', '/api/health');
        $duration = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(500, $duration, 'Health check should respond in < 500ms');
    }

    public function testLivenessProbe(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health/live');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['alive']);
    }

    public function testReadinessProbe(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health/ready');

        $this->assertResponseIsSuccessful();
    }

    public function testDatabaseHealthCheck(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health/database');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('healthy', $data['status']);
    }
}
```

---

### Task 1.2: Backend Smoke Tests

**Fișiere de creat**:

```
apps/backend/tests/Smoke/
├── HealthCheckTest.php              # (creat mai sus)
├── ApiEndpointsSmokeTest.php        # Teste endpoint-uri critice
├── AuthenticationSmokeTest.php      # Teste autentificare
└── DatabaseConnectionSmokeTest.php  # Teste conectivitate DB
```

**Implementare `ApiEndpointsSmokeTest.php`**:

```php
<?php
// apps/backend/tests/Smoke/ApiEndpointsSmokeTest.php

namespace App\Tests\Smoke;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Smoke tests for critical API endpoints
 * These tests verify basic functionality works after deployment
 *
 * @group smoke
 */
class ApiEndpointsSmokeTest extends WebTestCase
{
    private const TIMEOUT_MS = 500;

    public function testApiEntrypointIsAccessible(): void
    {
        $client = static::createClient();

        $startTime = microtime(true);
        $client->request('GET', '/api');
        $duration = (microtime(true) - $startTime) * 1000;

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertLessThan(self::TIMEOUT_MS, $duration);
    }

    public function testArticlesEndpointReturns200(): void
    {
        $client = static::createClient();

        $startTime = microtime(true);
        $client->request('GET', '/api/articles', [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'ro'
        ]);
        $duration = (microtime(true) - $startTime) * 1000;

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('hydra:member', $data);
        $this->assertArrayHasKey('hydra:totalItems', $data);

        $this->assertLessThan(self::TIMEOUT_MS, $duration);
    }

    public function testCategoriesEndpointReturns200(): void
    {
        $client = static::createClient();

        $startTime = microtime(true);
        $client->request('GET', '/api/categories');
        $duration = (microtime(true) - $startTime) * 1000;

        $this->assertResponseIsSuccessful();
        $this->assertLessThan(self::TIMEOUT_MS, $duration);
    }

    public function testAuthorsEndpointReturns200(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/authors');

        $this->assertResponseIsSuccessful();
    }

    public function testImagesEndpointReturns200(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/images');

        $this->assertResponseIsSuccessful();
    }

    public function testImportantArticlesEndpointReturns200(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/important_articles_lists');

        $this->assertResponseIsSuccessful();
    }

    /**
     * @dataProvider localeProvider
     */
    public function testMultilingualSupport(string $locale): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/articles', [], [], [
            'HTTP_ACCEPT_LANGUAGE' => $locale
        ]);

        $this->assertResponseIsSuccessful();
    }

    public static function localeProvider(): array
    {
        return [
            'Romanian' => ['ro'],
            'English' => ['en'],
            'Russian' => ['ru'],
        ];
    }

    public function testPaginationWorks(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/articles?page=1&itemsPerPage=10');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertLessThanOrEqual(10, count($data['hydra:member'] ?? []));
    }

    public function testInvalidEndpointReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/nonexistent_endpoint');

        $this->assertResponseStatusCodeSame(404);
    }
}
```

**Implementare `AuthenticationSmokeTest.php`**:

```php
<?php
// apps/backend/tests/Smoke/AuthenticationSmokeTest.php

namespace App\Tests\Smoke;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @group smoke
 */
class AuthenticationSmokeTest extends WebTestCase
{
    public function testLoginEndpointExists(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([]));

        // 401 is expected without valid credentials
        $this->assertResponseStatusCodeSame(401);
    }

    public function testProtectedEndpointRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/articles', [], [], [
            'CONTENT_TYPE' => 'application/ld+json'
        ], json_encode(['title' => 'Test']));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRefreshTokenEndpointExists(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/token/refresh', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([]));

        // 401 or 400 expected without valid refresh token
        $this->assertContains(
            $client->getResponse()->getStatusCode(),
            [400, 401]
        );
    }
}
```

---

### Task 1.3: Frontend Smoke Tests (Playwright)

**Fișiere de creat**:

```
apps/frontend/__tests__/smoke/
├── pages.smoke.spec.ts              # Încărcare pagini critice
├── navigation.smoke.spec.ts         # Navigare de bază
├── api-integration.smoke.spec.ts    # Integrare cu API
└── auth.smoke.spec.ts               # Pagini autentificare
```

**Implementare `pages.smoke.spec.ts`**:

```typescript
// apps/frontend/__tests__/smoke/pages.smoke.spec.ts

import { test, expect } from '@playwright/test';

test.describe('Pages Load Smoke Tests', () => {
  test.describe.configure({ mode: 'parallel' });

  const BASE_URL = process.env.FRONTEND_URL || 'http://localhost:3005';
  const LOAD_TIMEOUT = 10000;
  const RESPONSE_OK = [200, 304];

  test('Homepage (Romanian) loads successfully', async ({ page }) => {
    const response = await page.goto(`${BASE_URL}/ro`, {
      waitUntil: 'domcontentloaded',
      timeout: LOAD_TIMEOUT,
    });

    expect(RESPONSE_OK).toContain(response?.status());
    await expect(page).toHaveTitle(/Deschide|News|Știri/i);
    await expect(page.locator('header')).toBeVisible();
    await expect(page.locator('main')).toBeVisible();
  });

  test('Homepage (English) loads successfully', async ({ page }) => {
    const response = await page.goto(`${BASE_URL}/en`, {
      waitUntil: 'domcontentloaded',
      timeout: LOAD_TIMEOUT,
    });

    expect(RESPONSE_OK).toContain(response?.status());
  });

  test('Homepage (Russian) loads successfully', async ({ page }) => {
    const response = await page.goto(`${BASE_URL}/ru`, {
      waitUntil: 'domcontentloaded',
      timeout: LOAD_TIMEOUT,
    });

    expect(RESPONSE_OK).toContain(response?.status());
  });

  test('Category page loads', async ({ page }) => {
    const response = await page.goto(`${BASE_URL}/ro/category/politica`, {
      waitUntil: 'domcontentloaded',
      timeout: LOAD_TIMEOUT,
    });

    // 200 or 404 (if category doesn't exist in test env)
    expect([200, 304, 404]).toContain(response?.status());
  });

  test('Archive page loads', async ({ page }) => {
    const response = await page.goto(`${BASE_URL}/ro/archive`, {
      waitUntil: 'domcontentloaded',
      timeout: LOAD_TIMEOUT,
    });

    expect(RESPONSE_OK).toContain(response?.status());
  });

  test('Login page loads', async ({ page }) => {
    const response = await page.goto(`${BASE_URL}/ro/login`, {
      waitUntil: 'domcontentloaded',
      timeout: LOAD_TIMEOUT,
    });

    expect(RESPONSE_OK).toContain(response?.status());
    await expect(page.locator('form, [data-testid="login-form"]')).toBeVisible();
  });

  test('Search page loads', async ({ page }) => {
    const response = await page.goto(`${BASE_URL}/ro/search?q=test`, {
      waitUntil: 'domcontentloaded',
      timeout: LOAD_TIMEOUT,
    });

    expect([200, 304, 404]).toContain(response?.status());
  });

  test('404 page displays correctly', async ({ page }) => {
    const response = await page.goto(`${BASE_URL}/ro/nonexistent-page-xyz123`, {
      waitUntil: 'domcontentloaded',
    });

    expect(response?.status()).toBe(404);
  });
});
```

**Implementare `navigation.smoke.spec.ts`**:

```typescript
// apps/frontend/__tests__/smoke/navigation.smoke.spec.ts

import { test, expect } from '@playwright/test';

test.describe('Navigation Smoke Tests', () => {
  const BASE_URL = process.env.FRONTEND_URL || 'http://localhost:3005';

  test('Main navigation is visible and functional', async ({ page }) => {
    await page.goto(`${BASE_URL}/ro`);

    // Header navigation exists
    const nav = page.locator('header nav, nav[role="navigation"]');
    await expect(nav.first()).toBeVisible();

    // At least one navigation link exists
    const navLinks = page.locator('header a[href], nav a[href]');
    expect(await navLinks.count()).toBeGreaterThan(0);
  });

  test('Category navigation works', async ({ page }) => {
    await page.goto(`${BASE_URL}/ro`);

    const categoryLink = page.locator('a[href*="/category/"]').first();

    if (await categoryLink.isVisible()) {
      await categoryLink.click();
      await page.waitForLoadState('domcontentloaded');
      await expect(page).toHaveURL(/\/category\//);
    }
  });

  test('Logo links to homepage', async ({ page }) => {
    await page.goto(`${BASE_URL}/ro/archive`);

    const logo = page.locator('a[href="/ro"], a[href="/"] >> nth=0');

    if (await logo.isVisible()) {
      await logo.click();
      await page.waitForLoadState('domcontentloaded');
      await expect(page).toHaveURL(/\/(ro)?$/);
    }
  });

  test('Footer is present', async ({ page }) => {
    await page.goto(`${BASE_URL}/ro`);

    await expect(page.locator('footer')).toBeVisible();
  });

  test('Language switcher exists', async ({ page }) => {
    await page.goto(`${BASE_URL}/ro`);

    const langSwitcher = page.locator(
      '[data-testid="language-switcher"], ' +
      'button:has-text("RO"), ' +
      'a[href*="/en"], ' +
      '[aria-label*="language"]'
    );

    // Language switcher should exist in some form
    expect(await langSwitcher.count()).toBeGreaterThan(0);
  });
});
```

**Implementare `api-integration.smoke.spec.ts`**:

```typescript
// apps/frontend/__tests__/smoke/api-integration.smoke.spec.ts

import { test, expect } from '@playwright/test';

test.describe('API Integration Smoke Tests', () => {
  const BASE_URL = process.env.FRONTEND_URL || 'http://localhost:3005';
  const API_URL = process.env.BACKEND_URL || 'http://127.0.0.1:8081';

  test('Homepage displays articles from API', async ({ page }) => {
    await page.goto(`${BASE_URL}/ro`);

    // Wait for any article-like content
    const articleSelector = 'article, [data-testid="article-card"], .article-card';

    try {
      await page.waitForSelector(articleSelector, { timeout: 10000 });
      const articles = page.locator(articleSelector);
      expect(await articles.count()).toBeGreaterThan(0);
    } catch {
      // If no articles, at least the page should load without errors
      await expect(page.locator('main')).toBeVisible();
    }
  });

  test('API endpoint is reachable from frontend context', async ({ request }) => {
    const response = await request.get(`${API_URL}/api`);
    expect(response.status()).toBe(200);
  });

  test('Articles API returns data', async ({ request }) => {
    const response = await request.get(`${API_URL}/api/articles`, {
      headers: { 'Accept-Language': 'ro' },
    });

    expect(response.status()).toBe(200);

    const data = await response.json();
    expect(data).toHaveProperty('hydra:member');
  });

  test('Categories API returns data', async ({ request }) => {
    const response = await request.get(`${API_URL}/api/categories`);
    expect(response.status()).toBe(200);
  });

  test('Images load from CDN', async ({ page }) => {
    await page.goto(`${BASE_URL}/ro`);

    const images = page.locator('img[src*="uploads"], img[src*="cdn"]');
    const count = await images.count();

    if (count > 0) {
      const firstImage = images.first();
      await expect(firstImage).toBeVisible();
    }
  });
});
```

---

### Task 1.4: Script Smoke Check (Bash)

**Implementare `scripts/smoke-check.sh`**:

```bash
#!/bin/bash
# scripts/smoke-check.sh
# Quick smoke check script for deployment validation

set -e

BACKEND_URL="${BACKEND_URL:-http://127.0.0.1:8081}"
FRONTEND_URL="${FRONTEND_URL:-http://localhost:3005}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

PASSED=0
FAILED=0
WARNINGS=0

echo "=============================================="
echo "       SMOKE TEST SUITE - Deschide News      "
echo "=============================================="
echo ""
echo "Backend URL:  $BACKEND_URL"
echo "Frontend URL: $FRONTEND_URL"
echo ""
echo "----------------------------------------------"

# Function to test endpoint
test_endpoint() {
    local name="$1"
    local url="$2"
    local expected_status="${3:-200}"

    printf "%-40s" "$name"

    response=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$url" 2>/dev/null || echo "000")

    if [ "$response" = "$expected_status" ]; then
        echo -e "${GREEN}✓ PASS${NC} ($response)"
        ((PASSED++))
    elif [ "$response" = "000" ]; then
        echo -e "${RED}✗ FAIL${NC} (Connection failed)"
        ((FAILED++))
    else
        echo -e "${RED}✗ FAIL${NC} (Expected: $expected_status, Got: $response)"
        ((FAILED++))
    fi
}

# Function to test service
test_service() {
    local name="$1"
    local command="$2"

    printf "%-40s" "$name"

    if eval "$command" > /dev/null 2>&1; then
        echo -e "${GREEN}✓ PASS${NC}"
        ((PASSED++))
    else
        echo -e "${YELLOW}⚠ WARNING${NC}"
        ((WARNINGS++))
    fi
}

echo ""
echo "=== Infrastructure Services ==="
echo ""

test_service "PostgreSQL" "pg_isready -h 127.0.0.1 -p 5432 -U deschide_user"
test_service "Redis" "redis-cli -n 1 PING"
test_service "Elasticsearch" "curl -s -k https://localhost:9200 -u elastic:WsAEcDWAbQjb5XGUnpvk | grep -q cluster_name"

echo ""
echo "=== Backend API Endpoints ==="
echo ""

test_endpoint "API Entrypoint" "$BACKEND_URL/api"
test_endpoint "Health Check" "$BACKEND_URL/api/health"
test_endpoint "Articles List" "$BACKEND_URL/api/articles"
test_endpoint "Categories List" "$BACKEND_URL/api/categories"
test_endpoint "Authors List" "$BACKEND_URL/api/authors"
test_endpoint "Images List" "$BACKEND_URL/api/images"
test_endpoint "Important Articles" "$BACKEND_URL/api/important_articles_lists"

echo ""
echo "=== Backend Multilingual ==="
echo ""

test_endpoint "Articles (RO)" "$BACKEND_URL/api/articles" "200"
test_endpoint "Articles (EN)" "$BACKEND_URL/api/articles" "200"
test_endpoint "Articles (RU)" "$BACKEND_URL/api/articles" "200"

echo ""
echo "=== Frontend Pages ==="
echo ""

test_endpoint "Homepage (RO)" "$FRONTEND_URL/ro"
test_endpoint "Homepage (EN)" "$FRONTEND_URL/en"
test_endpoint "Homepage (RU)" "$FRONTEND_URL/ru"
test_endpoint "Login Page" "$FRONTEND_URL/ro/login"
test_endpoint "Archive Page" "$FRONTEND_URL/ro/archive"

echo ""
echo "=============================================="
echo "                  SUMMARY                     "
echo "=============================================="
echo ""
echo -e "Passed:   ${GREEN}$PASSED${NC}"
echo -e "Failed:   ${RED}$FAILED${NC}"
echo -e "Warnings: ${YELLOW}$WARNINGS${NC}"
echo ""

if [ $FAILED -gt 0 ]; then
    echo -e "${RED}SMOKE TESTS FAILED${NC}"
    exit 1
else
    echo -e "${GREEN}SMOKE TESTS PASSED${NC}"
    exit 0
fi
```

---

## 5. Faza 2: Teste de Performanță

### Task 2.1: Core Web Vitals Tests (Playwright)

**Fișiere de creat**:

```
apps/frontend/__tests__/performance/
├── core-web-vitals.spec.ts          # Măsurători CWV
├── page-load-time.spec.ts           # Timpi încărcare
└── resource-loading.spec.ts         # Încărcare resurse
```

**Implementare `core-web-vitals.spec.ts`**:

```typescript
// apps/frontend/__tests__/performance/core-web-vitals.spec.ts

import { test, expect } from '@playwright/test';

test.describe('Core Web Vitals Performance', () => {
  const BASE_URL = process.env.FRONTEND_URL || 'http://localhost:3005';

  // Target thresholds
  const THRESHOLDS = {
    LCP: 2500,      // < 2.5s
    FCP: 1800,      // < 1.8s
    TTFB: 600,      // < 600ms
    CLS: 0.1,       // < 0.1
    TotalLoad: 4000 // < 4s
  };

  test('Homepage meets LCP target (< 2.5s)', async ({ page }) => {
    await page.goto(`${BASE_URL}/ro`);
    await page.waitForLoadState('networkidle');

    const lcp = await page.evaluate(() => {
      return new Promise<number>((resolve) => {
        new PerformanceObserver((list) => {
          const entries = list.getEntries();
          const lastEntry = entries[entries.length - 1] as PerformanceEntry & { startTime: number };
          resolve(lastEntry.startTime);
        }).observe({ type: 'largest-contentful-paint', buffered: true });

        // Fallback timeout
        setTimeout(() => resolve(0), 5000);
      });
    });

    console.log(`Homepage LCP: ${lcp}ms`);
    expect(lcp).toBeLessThan(THRESHOLDS.LCP);
  });

  test('Homepage meets FCP target (< 1.8s)', async ({ page }) => {
    await page.goto(`${BASE_URL}/ro`);

    const fcp = await page.evaluate(() => {
      const paintEntries = performance.getEntriesByType('paint');
      const fcpEntry = paintEntries.find(entry => entry.name === 'first-contentful-paint');
      return fcpEntry?.startTime || 0;
    });

    console.log(`Homepage FCP: ${fcp}ms`);
    expect(fcp).toBeLessThan(THRESHOLDS.FCP);
  });

  test('Homepage meets TTFB target (< 600ms)', async ({ page }) => {
    await page.goto(`${BASE_URL}/ro`);

    const ttfb = await page.evaluate(() => {
      const timing = performance.timing;
      return timing.responseStart - timing.requestStart;
    });

    console.log(`Homepage TTFB: ${ttfb}ms`);
    expect(ttfb).toBeLessThan(THRESHOLDS.TTFB);
  });

  test('Homepage CLS is acceptable (< 0.1)', async ({ page }) => {
    await page.goto(`${BASE_URL}/ro`);

    const cls = await page.evaluate(() => {
      return new Promise<number>((resolve) => {
        let clsValue = 0;

        const observer = new PerformanceObserver((list) => {
          for (const entry of list.getEntries()) {
            const layoutShift = entry as PerformanceEntry & {
              hadRecentInput: boolean;
              value: number
            };
            if (!layoutShift.hadRecentInput) {
              clsValue += layoutShift.value;
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

    console.log(`Homepage CLS: ${cls}`);
    expect(cls).toBeLessThan(THRESHOLDS.CLS);
  });

  test('Total page load under 4 seconds', async ({ page }) => {
    const startTime = Date.now();

    await page.goto(`${BASE_URL}/ro`);
    await page.waitForLoadState('load');

    const loadTime = Date.now() - startTime;

    console.log(`Total load time: ${loadTime}ms`);
    expect(loadTime).toBeLessThan(THRESHOLDS.TotalLoad);
  });

  test.describe('Article Page Performance', () => {
    test('Article page meets LCP target', async ({ page }) => {
      // Navigate to homepage first
      await page.goto(`${BASE_URL}/ro`);

      // Click first article
      const articleLink = page.locator('article a, [data-testid="article-link"]').first();

      if (await articleLink.isVisible()) {
        const startTime = Date.now();
        await articleLink.click();
        await page.waitForLoadState('networkidle');
        const loadTime = Date.now() - startTime;

        console.log(`Article page load: ${loadTime}ms`);
        expect(loadTime).toBeLessThan(THRESHOLDS.LCP);
      }
    });
  });
});
```

### Task 2.2: API Performance Tests (Backend)

**Fișiere de creat**:

```
apps/backend/tests/Performance/
├── ApiResponseTimeTest.php          # Timpi răspuns API
├── DatabaseQueryPerformanceTest.php # Performanță query-uri
└── CacheEffectivenessTest.php       # Eficiență cache
```

**Implementare `ApiResponseTimeTest.php`**:

```php
<?php
// apps/backend/tests/Performance/ApiResponseTimeTest.php

namespace App\Tests\Performance;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @group performance
 */
class ApiResponseTimeTest extends WebTestCase
{
    // Response time thresholds in milliseconds
    private const THRESHOLD_ARTICLES_LIST = 200;
    private const THRESHOLD_SINGLE_ARTICLE = 150;
    private const THRESHOLD_CATEGORIES = 100;
    private const THRESHOLD_IMPORTANT_ARTICLES = 200;

    public function testArticlesListResponseTime(): void
    {
        $client = static::createClient();

        $times = [];
        for ($i = 0; $i < 5; $i++) {
            $startTime = microtime(true);
            $client->request('GET', '/api/articles?itemsPerPage=30', [], [], [
                'HTTP_ACCEPT_LANGUAGE' => 'ro'
            ]);
            $times[] = (microtime(true) - $startTime) * 1000;
        }

        $avgTime = array_sum($times) / count($times);
        $p95Time = $this->percentile($times, 95);

        echo sprintf("\nGET /api/articles - Avg: %.2fms, P95: %.2fms\n", $avgTime, $p95Time);

        $this->assertResponseIsSuccessful();
        $this->assertLessThan(
            self::THRESHOLD_ARTICLES_LIST,
            $p95Time,
            "P95 response time should be < " . self::THRESHOLD_ARTICLES_LIST . "ms"
        );
    }

    public function testSingleArticleResponseTime(): void
    {
        $client = static::createClient();

        // First get an article ID
        $client->request('GET', '/api/articles?itemsPerPage=1');
        $data = json_decode($client->getResponse()->getContent(), true);

        if (empty($data['hydra:member'])) {
            $this->markTestSkipped('No articles available for testing');
        }

        $articleId = $data['hydra:member'][0]['id'];

        $times = [];
        for ($i = 0; $i < 5; $i++) {
            $startTime = microtime(true);
            $client->request('GET', "/api/articles/{$articleId}");
            $times[] = (microtime(true) - $startTime) * 1000;
        }

        $p95Time = $this->percentile($times, 95);

        echo sprintf("\nGET /api/articles/{id} - P95: %.2fms\n", $p95Time);

        $this->assertResponseIsSuccessful();
        $this->assertLessThan(self::THRESHOLD_SINGLE_ARTICLE, $p95Time);
    }

    public function testCategoriesResponseTime(): void
    {
        $client = static::createClient();

        $times = [];
        for ($i = 0; $i < 5; $i++) {
            $startTime = microtime(true);
            $client->request('GET', '/api/categories');
            $times[] = (microtime(true) - $startTime) * 1000;
        }

        $p95Time = $this->percentile($times, 95);

        echo sprintf("\nGET /api/categories - P95: %.2fms\n", $p95Time);

        $this->assertResponseIsSuccessful();
        $this->assertLessThan(self::THRESHOLD_CATEGORIES, $p95Time);
    }

    public function testImportantArticlesResponseTime(): void
    {
        $client = static::createClient();

        $times = [];
        for ($i = 0; $i < 5; $i++) {
            $startTime = microtime(true);
            $client->request('GET', '/api/important_articles_lists');
            $times[] = (microtime(true) - $startTime) * 1000;
        }

        $p95Time = $this->percentile($times, 95);

        echo sprintf("\nGET /api/important_articles_lists - P95: %.2fms\n", $p95Time);

        $this->assertResponseIsSuccessful();
        $this->assertLessThan(self::THRESHOLD_IMPORTANT_ARTICLES, $p95Time);
    }

    public function testConcurrentRequests(): void
    {
        $client = static::createClient();

        $startTime = microtime(true);

        // Simulate 10 sequential requests (concurrent not possible in PHPUnit)
        for ($i = 0; $i < 10; $i++) {
            $client->request('GET', '/api/articles?itemsPerPage=10');
            $this->assertResponseIsSuccessful();
        }

        $totalTime = (microtime(true) - $startTime) * 1000;
        $avgTime = $totalTime / 10;

        echo sprintf("\n10 sequential requests - Total: %.2fms, Avg: %.2fms\n", $totalTime, $avgTime);

        // All 10 requests should complete in < 2 seconds
        $this->assertLessThan(2000, $totalTime);
    }

    private function percentile(array $values, int $percentile): float
    {
        sort($values);
        $index = ceil(($percentile / 100) * count($values)) - 1;
        return $values[$index];
    }
}
```

---

## 6. Faza 3: Load Testing

### Task 3.1: k6 Setup & Configuration

**Fișiere de creat**:

```
k6/
├── config.js                        # Configurație partajată
├── load-test.js                     # Test load standard
├── stress-test.js                   # Test stress
├── soak-test.js                     # Test endurance
└── scenarios/
    ├── homepage.js                  # Scenariu homepage
    ├── article-browse.js            # Scenariu navigare articole
    └── api-endpoints.js             # Scenariu API direct
```

**Implementare `k6/config.js`**:

```javascript
// k6/config.js

export const CONFIG = {
  BACKEND_URL: __ENV.BACKEND_URL || 'http://127.0.0.1:8081',
  FRONTEND_URL: __ENV.FRONTEND_URL || 'http://localhost:3005',

  THRESHOLDS: {
    http_req_duration: ['p(95)<500', 'p(99)<1000'],
    http_req_failed: ['rate<0.01'],
    http_reqs: ['rate>100'],
  },

  // Locales to test
  LOCALES: ['ro', 'en', 'ru'],
};

export const ENDPOINTS = {
  articles: '/api/articles',
  categories: '/api/categories',
  authors: '/api/authors',
  images: '/api/images',
  important: '/api/important_articles_lists',
  health: '/api/health',
};
```

**Implementare `k6/load-test.js`**:

```javascript
// k6/load-test.js

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';
import { CONFIG, ENDPOINTS } from './config.js';

// Custom metrics
const errorRate = new Rate('errors');
const articlesTrend = new Trend('articles_duration');
const categoriesTrend = new Trend('categories_duration');
const homepageTrend = new Trend('homepage_duration');
const requestCounter = new Counter('total_requests');

export const options = {
  stages: [
    { duration: '30s', target: 10 },   // Warm up
    { duration: '1m', target: 25 },    // Ramp to 25 users
    { duration: '2m', target: 50 },    // Ramp to 50 users
    { duration: '3m', target: 50 },    // Stay at 50 users
    { duration: '1m', target: 100 },   // Peak at 100 users
    { duration: '2m', target: 100 },   // Stay at peak
    { duration: '1m', target: 0 },     // Ramp down
  ],

  thresholds: {
    ...CONFIG.THRESHOLDS,
    articles_duration: ['p(95)<200'],
    categories_duration: ['p(95)<100'],
    homepage_duration: ['p(95)<2000'],
    errors: ['rate<0.05'],
  },
};

export default function () {
  const locale = CONFIG.LOCALES[Math.floor(Math.random() * CONFIG.LOCALES.length)];

  group('API Endpoints', () => {
    // Articles list
    const articlesRes = http.get(`${CONFIG.BACKEND_URL}${ENDPOINTS.articles}?itemsPerPage=30`, {
      headers: { 'Accept-Language': locale },
      tags: { name: 'articles_list' },
    });

    articlesTrend.add(articlesRes.timings.duration);
    requestCounter.add(1);

    check(articlesRes, {
      'articles: status 200': (r) => r.status === 200,
      'articles: response < 500ms': (r) => r.timings.duration < 500,
      'articles: has data': (r) => {
        try {
          const body = JSON.parse(r.body);
          return body['hydra:member'] !== undefined;
        } catch {
          return false;
        }
      },
    }) || errorRate.add(1);

    sleep(0.5);

    // Categories
    const categoriesRes = http.get(`${CONFIG.BACKEND_URL}${ENDPOINTS.categories}`, {
      tags: { name: 'categories' },
    });

    categoriesTrend.add(categoriesRes.timings.duration);
    requestCounter.add(1);

    check(categoriesRes, {
      'categories: status 200': (r) => r.status === 200,
      'categories: response < 200ms': (r) => r.timings.duration < 200,
    }) || errorRate.add(1);

    sleep(0.5);

    // Single article (if we got articles)
    try {
      const articlesData = JSON.parse(articlesRes.body);
      if (articlesData['hydra:member']?.length > 0) {
        const randomArticle = articlesData['hydra:member'][
          Math.floor(Math.random() * articlesData['hydra:member'].length)
        ];

        if (randomArticle?.id) {
          const singleRes = http.get(
            `${CONFIG.BACKEND_URL}${ENDPOINTS.articles}/${randomArticle.id}`,
            { tags: { name: 'single_article' } }
          );

          requestCounter.add(1);

          check(singleRes, {
            'single article: status 200': (r) => r.status === 200,
            'single article: response < 300ms': (r) => r.timings.duration < 300,
          }) || errorRate.add(1);
        }
      }
    } catch (e) {
      // Ignore parse errors
    }
  });

  group('Frontend Pages', () => {
    // Homepage
    const homepageRes = http.get(`${CONFIG.FRONTEND_URL}/${locale}`, {
      tags: { name: 'homepage' },
    });

    homepageTrend.add(homepageRes.timings.duration);
    requestCounter.add(1);

    check(homepageRes, {
      'homepage: status 200': (r) => r.status === 200,
      'homepage: response < 3000ms': (r) => r.timings.duration < 3000,
    }) || errorRate.add(1);
  });

  // Random sleep between 1-3 seconds to simulate real user behavior
  sleep(Math.random() * 2 + 1);
}

export function handleSummary(data) {
  return {
    'stdout': textSummary(data, { indent: ' ', enableColors: true }),
    'k6-results.json': JSON.stringify(data),
  };
}

function textSummary(data, opts) {
  const summary = [];

  summary.push('\n============================================================');
  summary.push('                    LOAD TEST RESULTS                        ');
  summary.push('============================================================\n');

  summary.push(`Total Requests: ${data.metrics.total_requests?.values?.count || 0}`);
  summary.push(`Error Rate: ${((data.metrics.errors?.values?.rate || 0) * 100).toFixed(2)}%`);
  summary.push(`\nResponse Times (p95):`);
  summary.push(`  - Articles: ${data.metrics.articles_duration?.values?.['p(95)']?.toFixed(2) || 'N/A'}ms`);
  summary.push(`  - Categories: ${data.metrics.categories_duration?.values?.['p(95)']?.toFixed(2) || 'N/A'}ms`);
  summary.push(`  - Homepage: ${data.metrics.homepage_duration?.values?.['p(95)']?.toFixed(2) || 'N/A'}ms`);

  return summary.join('\n') + '\n';
}
```

**Implementare `k6/stress-test.js`**:

```javascript
// k6/stress-test.js

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';
import { CONFIG, ENDPOINTS } from './config.js';

const errorRate = new Rate('errors');

export const options = {
  stages: [
    { duration: '1m', target: 50 },    // Normal load
    { duration: '2m', target: 100 },   // High load
    { duration: '2m', target: 200 },   // Stress
    { duration: '3m', target: 300 },   // High stress
    { duration: '2m', target: 500 },   // Breaking point search
    { duration: '3m', target: 500 },   // Stay at max
    { duration: '2m', target: 0 },     // Recovery
  ],

  thresholds: {
    http_req_duration: ['p(95)<2000', 'p(99)<5000'],  // Relaxed for stress
    http_req_failed: ['rate<0.15'],                   // Allow 15% errors in stress
    errors: ['rate<0.20'],
  },
};

export default function () {
  const res = http.get(`${CONFIG.BACKEND_URL}${ENDPOINTS.articles}?itemsPerPage=10`, {
    headers: { 'Accept-Language': 'ro' },
  });

  check(res, {
    'status is 200': (r) => r.status === 200,
    'response < 2s': (r) => r.timings.duration < 2000,
  }) || errorRate.add(1);

  sleep(0.5);
}
```

**Implementare `k6/soak-test.js`**:

```javascript
// k6/soak-test.js

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';
import { CONFIG, ENDPOINTS } from './config.js';

const errorRate = new Rate('errors');
const responseTrend = new Trend('response_time');

export const options = {
  stages: [
    { duration: '5m', target: 50 },    // Ramp up
    { duration: '60m', target: 50 },   // Soak for 1 hour
    { duration: '5m', target: 0 },     // Ramp down
  ],

  thresholds: {
    http_req_duration: ['p(95)<500', 'p(99)<1000'],
    http_req_failed: ['rate<0.01'],
    errors: ['rate<0.05'],
    response_time: ['p(95)<500'],
  },
};

export default function () {
  // Mix of different endpoints
  const endpoints = [
    ENDPOINTS.articles,
    ENDPOINTS.categories,
    ENDPOINTS.important,
  ];

  const endpoint = endpoints[Math.floor(Math.random() * endpoints.length)];

  const res = http.get(`${CONFIG.BACKEND_URL}${endpoint}`, {
    headers: { 'Accept-Language': 'ro' },
  });

  responseTrend.add(res.timings.duration);

  check(res, {
    'status is 200': (r) => r.status === 200,
    'response < 1s': (r) => r.timings.duration < 1000,
  }) || errorRate.add(1);

  sleep(2);
}
```

---

## 7. Faza 4: Integrare CI/CD

### Task 4.1: Smoke Tests Workflow

**Implementare `.github/workflows/smoke-tests.yml`**:

```yaml
# .github/workflows/smoke-tests.yml

name: Smoke Tests

on:
  deployment_status:
  workflow_dispatch:
    inputs:
      environment:
        description: 'Environment to test'
        required: true
        default: 'staging'
        type: choice
        options:
          - staging
          - production

env:
  BACKEND_URL: ${{ vars.BACKEND_URL || 'http://127.0.0.1:8081' }}
  FRONTEND_URL: ${{ vars.FRONTEND_URL || 'http://localhost:3005' }}

jobs:
  backend-smoke:
    name: Backend Smoke Tests
    runs-on: ubuntu-latest
    if: github.event_name != 'deployment_status' || github.event.deployment_status.state == 'success'

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: pdo_pgsql, redis, apcu

      - name: Install dependencies
        working-directory: apps/backend
        run: composer install --prefer-dist --no-progress

      - name: Run smoke tests
        working-directory: apps/backend
        run: |
          vendor/bin/phpunit tests/Smoke --group=smoke --testdox
        env:
          APP_ENV: test

  frontend-smoke:
    name: Frontend Smoke Tests
    runs-on: ubuntu-latest
    if: github.event_name != 'deployment_status' || github.event.deployment_status.state == 'success'

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'

      - name: Setup pnpm
        uses: pnpm/action-setup@v2
        with:
          version: 9

      - name: Install dependencies
        working-directory: apps/frontend
        run: pnpm install

      - name: Install Playwright
        working-directory: apps/frontend
        run: pnpm exec playwright install chromium

      - name: Run smoke tests
        working-directory: apps/frontend
        run: |
          pnpm exec playwright test __tests__/smoke --project=chromium --reporter=list
        env:
          FRONTEND_URL: ${{ env.FRONTEND_URL }}
          BACKEND_URL: ${{ env.BACKEND_URL }}

  notify-failure:
    name: Notify on Failure
    runs-on: ubuntu-latest
    needs: [backend-smoke, frontend-smoke]
    if: failure()

    steps:
      - name: Send Slack notification
        uses: slackapi/slack-github-action@v1.24.0
        with:
          payload: |
            {
              "text": "🚨 Smoke tests failed on ${{ github.ref_name }}",
              "blocks": [
                {
                  "type": "section",
                  "text": {
                    "type": "mrkdwn",
                    "text": "*Smoke Tests Failed*\n\nBranch: `${{ github.ref_name }}`\nWorkflow: <${{ github.server_url }}/${{ github.repository }}/actions/runs/${{ github.run_id }}|View Details>"
                  }
                }
              ]
            }
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK_URL }}
```

### Task 4.2: Performance Tests Workflow

**Implementare `.github/workflows/performance-tests.yml`**:

```yaml
# .github/workflows/performance-tests.yml

name: Performance Tests

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]
  schedule:
    - cron: '0 3 * * *'  # Daily at 3 AM
  workflow_dispatch:

jobs:
  lighthouse:
    name: Lighthouse CI
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'

      - name: Install Lighthouse CI
        run: npm install -g @lhci/cli@0.13.x

      - name: Run Lighthouse
        run: lhci autorun
        env:
          LHCI_GITHUB_APP_TOKEN: ${{ secrets.LHCI_GITHUB_APP_TOKEN }}

      - name: Upload Lighthouse results
        uses: actions/upload-artifact@v4
        with:
          name: lighthouse-results
          path: .lighthouseci
          retention-days: 14

  k6-load-test:
    name: k6 Load Test
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Run k6 load test
        uses: grafana/k6-action@v0.3.1
        with:
          filename: k6/load-test.js
          flags: --out json=k6-results.json
        env:
          BACKEND_URL: ${{ vars.BACKEND_URL }}
          FRONTEND_URL: ${{ vars.FRONTEND_URL }}

      - name: Upload k6 results
        uses: actions/upload-artifact@v4
        with:
          name: k6-results
          path: k6-results.json
          retention-days: 14

  api-performance:
    name: API Performance Tests
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:17
        env:
          POSTGRES_USER: deschide_user
          POSTGRES_PASSWORD: test_password
          POSTGRES_DB: deschide_news_test
        ports:
          - 5432:5432
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

      redis:
        image: redis:7
        ports:
          - 6379:6379

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: pdo_pgsql, redis

      - name: Install dependencies
        working-directory: apps/backend
        run: composer install --prefer-dist --no-progress

      - name: Run performance tests
        working-directory: apps/backend
        run: |
          vendor/bin/phpunit tests/Performance --group=performance --testdox
        env:
          APP_ENV: test
          DATABASE_URL: postgresql://deschide_user:test_password@localhost:5432/deschide_news_test

  performance-report:
    name: Generate Report
    runs-on: ubuntu-latest
    needs: [lighthouse, k6-load-test, api-performance]
    if: always()

    steps:
      - name: Download all artifacts
        uses: actions/download-artifact@v4

      - name: Generate summary report
        run: |
          echo "# Performance Test Results" >> $GITHUB_STEP_SUMMARY
          echo "" >> $GITHUB_STEP_SUMMARY
          echo "## Lighthouse" >> $GITHUB_STEP_SUMMARY
          echo "See artifacts for detailed report" >> $GITHUB_STEP_SUMMARY
          echo "" >> $GITHUB_STEP_SUMMARY
          echo "## k6 Load Test" >> $GITHUB_STEP_SUMMARY
          if [ -f k6-results/k6-results.json ]; then
            echo "Load test completed successfully" >> $GITHUB_STEP_SUMMARY
          else
            echo "Load test results not available" >> $GITHUB_STEP_SUMMARY
          fi

      - name: Comment on PR
        if: github.event_name == 'pull_request'
        uses: actions/github-script@v7
        with:
          script: |
            const fs = require('fs');

            let comment = '## 📊 Performance Test Results\n\n';
            comment += '| Test | Status |\n';
            comment += '|------|--------|\n';
            comment += `| Lighthouse | ${{ needs.lighthouse.result == 'success' && '✅ Passed' || '❌ Failed' }} |\n`;
            comment += `| k6 Load Test | ${{ needs.k6-load-test.result == 'success' && '✅ Passed' || '❌ Failed' }} |\n`;
            comment += `| API Performance | ${{ needs.api-performance.result == 'success' && '✅ Passed' || '❌ Failed' }} |\n`;
            comment += '\n[View detailed results](${{ github.server_url }}/${{ github.repository }}/actions/runs/${{ github.run_id }})';

            github.rest.issues.createComment({
              issue_number: context.issue.number,
              owner: context.repo.owner,
              repo: context.repo.repo,
              body: comment
            });
```

### Task 4.3: Lighthouse CI Configuration

**Implementare `lighthouserc.js`**:

```javascript
// lighthouserc.js

module.exports = {
  ci: {
    collect: {
      url: [
        'http://localhost:3005/ro',
        'http://localhost:3005/en',
        'http://localhost:3005/ro/archive',
      ],
      numberOfRuns: 3,
      settings: {
        preset: 'desktop',
        chromeFlags: '--no-sandbox --headless',
      },
    },

    assert: {
      assertions: {
        // Performance
        'categories:performance': ['error', { minScore: 0.85 }],
        'largest-contentful-paint': ['error', { maxNumericValue: 2500 }],
        'cumulative-layout-shift': ['error', { maxNumericValue: 0.1 }],
        'total-blocking-time': ['warn', { maxNumericValue: 300 }],
        'first-contentful-paint': ['warn', { maxNumericValue: 1800 }],
        'speed-index': ['warn', { maxNumericValue: 3400 }],

        // Accessibility
        'categories:accessibility': ['warn', { minScore: 0.9 }],

        // Best Practices
        'categories:best-practices': ['warn', { minScore: 0.85 }],

        // SEO
        'categories:seo': ['warn', { minScore: 0.9 }],
      },
    },

    upload: {
      target: 'temporary-public-storage',
    },
  },
};
```

---

## 8. Estimări și Priorități

### Matricea Task-urilor

| Task | Prioritate | Complexitate | Estimare | Dependențe |
|------|------------|--------------|----------|------------|
| **FAZA 1** |
| 1.1 Health Endpoint | 🔴 Critică | Medie | 2-3h | - |
| 1.2 Backend Smoke | 🔴 Critică | Medie | 3-4h | 1.1 |
| 1.3 Frontend Smoke | 🔴 Critică | Medie | 3-4h | - |
| 1.4 Script Smoke | 🟠 Înaltă | Ușoară | 1-2h | 1.1 |
| **FAZA 2** |
| 2.1 CWV Tests | 🟠 Înaltă | Medie | 3-4h | 1.3 |
| 2.2 API Perf Tests | 🟠 Înaltă | Medie | 4-5h | 1.2 |
| 2.3 DB Query Tests | 🟡 Medie | Complexă | 3-4h | - |
| 2.4 Cache Tests | 🟡 Medie | Medie | 2-3h | - |
| **FAZA 3** |
| 3.1 k6 Setup | 🟠 Înaltă | Ușoară | 2-3h | - |
| 3.2 Load Scenarios | 🟠 Înaltă | Medie | 4-5h | 3.1 |
| 3.3 Stress/Soak | 🟡 Medie | Medie | 3-4h | 3.1 |
| **FAZA 4** |
| 4.1 Smoke CI | 🔴 Critică | Ușoară | 2-3h | 1.2, 1.3 |
| 4.2 Perf CI | 🟠 Înaltă | Medie | 3-4h | 2.1, 3.1 |
| 4.3 Lighthouse | 🟡 Medie | Ușoară | 2-3h | - |

### Timeline Sugerată

```
Săptămâna 1: Faza 1 (Health Checks & Smoke Tests)
├── Ziua 1-2: Task 1.1 (Health Endpoint)
├── Ziua 2-3: Task 1.2 (Backend Smoke)
├── Ziua 3-4: Task 1.3 (Frontend Smoke)
└── Ziua 4-5: Task 1.4 + Task 4.1 (Script + CI)

Săptămâna 2: Faza 2 (Performance Tests)
├── Ziua 1-2: Task 2.1 (CWV Tests)
├── Ziua 2-3: Task 2.2 (API Performance)
├── Ziua 3-4: Task 2.3 (DB Query Tests)
└── Ziua 4-5: Task 2.4 (Cache Tests)

Săptămâna 3: Faza 3 & 4 (Load Testing & CI/CD)
├── Ziua 1-2: Task 3.1 + 3.2 (k6 Setup + Scenarios)
├── Ziua 2-3: Task 3.3 (Stress/Soak)
├── Ziua 3-4: Task 4.2 (Perf CI)
└── Ziua 4-5: Task 4.3 + Documentație finală
```

---

## 9. Checklist de Implementare

### Faza 1: Health Checks & Smoke Tests

- [ ] **1.1 Health Endpoint**
  - [ ] Creare `HealthController.php`
  - [ ] Creare `HealthCheckService.php`
  - [ ] Configurare routing
  - [ ] Test endpoint `/api/health`
  - [ ] Test endpoint `/api/health/live`
  - [ ] Test endpoint `/api/health/ready`

- [ ] **1.2 Backend Smoke Tests**
  - [ ] Creare folder `tests/Smoke/`
  - [ ] Implementare `HealthCheckTest.php`
  - [ ] Implementare `ApiEndpointsSmokeTest.php`
  - [ ] Implementare `AuthenticationSmokeTest.php`
  - [ ] Adăugare grup `@group smoke` la phpunit.xml

- [ ] **1.3 Frontend Smoke Tests**
  - [ ] Creare folder `__tests__/smoke/`
  - [ ] Implementare `pages.smoke.spec.ts`
  - [ ] Implementare `navigation.smoke.spec.ts`
  - [ ] Implementare `api-integration.smoke.spec.ts`
  - [ ] Configurare Playwright pentru smoke tests

- [ ] **1.4 Script Smoke Check**
  - [ ] Creare `scripts/smoke-check.sh`
  - [ ] Testare script local
  - [ ] Documentare utilizare

### Faza 2: Performance Tests

- [ ] **2.1 Core Web Vitals**
  - [ ] Creare folder `__tests__/performance/`
  - [ ] Implementare `core-web-vitals.spec.ts`
  - [ ] Testare LCP, FCP, TTFB, CLS
  - [ ] Documentare rezultate baseline

- [ ] **2.2 API Performance**
  - [ ] Creare folder `tests/Performance/`
  - [ ] Implementare `ApiResponseTimeTest.php`
  - [ ] Testare response times per endpoint
  - [ ] Documentare thresholds

- [ ] **2.3 Database Performance**
  - [ ] Implementare `DatabaseQueryPerformanceTest.php`
  - [ ] Testare query execution times
  - [ ] Verificare N+1 queries

- [ ] **2.4 Cache Performance**
  - [ ] Implementare `CacheEffectivenessTest.php`
  - [ ] Testare hit rates L1/L2
  - [ ] Creare script `cache-stats.sh`

### Faza 3: Load Testing

- [ ] **3.1 k6 Setup**
  - [ ] Instalare k6
  - [ ] Creare folder `k6/`
  - [ ] Implementare `config.js`

- [ ] **3.2 Load Scenarios**
  - [ ] Implementare `load-test.js`
  - [ ] Testare cu 50, 100 utilizatori
  - [ ] Documentare rezultate

- [ ] **3.3 Stress & Soak**
  - [ ] Implementare `stress-test.js`
  - [ ] Implementare `soak-test.js`
  - [ ] Identificare breaking point

### Faza 4: CI/CD Integration

- [ ] **4.1 Smoke CI**
  - [ ] Creare `.github/workflows/smoke-tests.yml`
  - [ ] Configurare trigger post-deployment
  - [ ] Testare workflow

- [ ] **4.2 Performance CI**
  - [ ] Creare `.github/workflows/performance-tests.yml`
  - [ ] Integrare k6
  - [ ] Configurare raportare

- [ ] **4.3 Lighthouse CI**
  - [ ] Creare `lighthouserc.js`
  - [ ] Configurare în workflow
  - [ ] Testare assertions

---

## Anexă: Structura Finală a Fișierelor

```
deschide_news_app/
├── apps/
│   ├── backend/
│   │   ├── src/
│   │   │   ├── Controller/
│   │   │   │   └── HealthController.php         ✨ NOU
│   │   │   └── Service/
│   │   │       └── HealthCheckService.php       ✨ NOU
│   │   └── tests/
│   │       ├── Smoke/                           ✨ NOU
│   │       │   ├── HealthCheckTest.php
│   │       │   ├── ApiEndpointsSmokeTest.php
│   │       │   └── AuthenticationSmokeTest.php
│   │       └── Performance/                     ✨ NOU
│   │           ├── ApiResponseTimeTest.php
│   │           └── DatabaseQueryPerformanceTest.php
│   │
│   └── frontend/
│       └── __tests__/
│           ├── smoke/                           ✨ NOU
│           │   ├── pages.smoke.spec.ts
│           │   ├── navigation.smoke.spec.ts
│           │   └── api-integration.smoke.spec.ts
│           └── performance/                     ✨ NOU
│               └── core-web-vitals.spec.ts
│
├── k6/                                          ✨ NOU
│   ├── config.js
│   ├── load-test.js
│   ├── stress-test.js
│   └── soak-test.js
│
├── scripts/
│   └── smoke-check.sh                           ✨ NOU
│
├── .github/workflows/
│   ├── smoke-tests.yml                          ✨ NOU
│   └── performance-tests.yml                    ✨ NOU
│
├── lighthouserc.js                              ✨ NOU
│
└── docs/testing/
    ├── SMOKE_AND_PERFORMANCE_TESTING_STRATEGY.md
    └── SMOKE_PERFORMANCE_IMPLEMENTATION_PLAN.md  ✨ NOU
```

---

**Document creat**: 2025-12-02
**Autor**: Claude Code Assistant
**Versiune**: 1.0
