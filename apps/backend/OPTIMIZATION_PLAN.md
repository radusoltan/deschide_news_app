# Plan de Optimizare și Curățare - Backend Deschide News

**Data creării**: 4 Noiembrie 2025
**Status**: 📋 Planificat
**Durata totală**: 8 săptămâni (4 sprinturi × 2 săptămâni)

---

## 📊 Analiza Situației Actuale

### Structura Codului
- **215 fișiere PHP** în `src/`
- **32 entități** în `src/Entity/`
- **30+ comenzi** în `src/Command/`
- **17 migrații** database
- **14 servicii** în `src/Service/`
- **11 controllere** în `src/Controller/`

### Probleme Identificate

#### 🔴 Critice (Prioritate Înaltă)
1. **Comenzi de test în producție**: 15+ comenzi de test care nu ar trebui să existe
   - `TestCategoryChangeListenerCommand.php`
   - `TestRedirectCommandsCommand.php`
   - `TestRedirectManagementApiCommand.php`
   - `TestSlugAvailabilityApiCommand.php`
   - `TestSlugChangeListenerCommand.php`
   - `TestSlugLookupApiCommand.php`
   - `TestTransliterationCommand.php`
   - `TestValidatorCommand.php`
   - `TestReservedSlugCommand.php`
   - `TestCacheCommand.php` (în `Command/Test/`)
   - `TestJwtTokenCommand.php`
   - `TestMigrationLoggerCommand.php`
   - `TestNewscoopConnectionCommand.php`
   - `TestThumbnailGenerationCommand.php`

2. **Entități de test în producție**:
   - `src/Entity/TestArticle.php`
   - `src/Entity/TestArticleTranslation.php`

3. **Lipsa testelor PHPUnit**: Doar 2 teste vs. 215 fișiere PHP
   - Există: `tests/Validator/ReservedSlugValidatorTest.php`
   - Există: `tests/Service/SlugLookupServiceTest.php`
   - Lipsesc: teste pentru ~95% din cod

#### 🟡 Importante (Prioritate Medie)
4. **Fișiere .env multiple** (5 fișiere care pot crea confuzie):
   - `.env` (commited - ⚠️ pericol securitate)
   - `.env.dev`
   - `.env.example` ✅
   - `.env.local` (local overrides) ✅
   - `.env.test` ✅

5. **Documentație incompletă**:
   - Există doar: `docs/sprint-1-completion-report.md`
   - Lipsesc: docs pentru LiveText, Analytics, Import, Images, etc.

6. **Cache neoptimizat**:
   - 17M în `var/cache/dev`
   - Lipsește configurare APCu/OPcache

#### 🟢 Minor (Îmbunătățiri)
7. **Organizare comenzi**: Multe comenzi în directorul principal vs. subdirectoare
8. **Code coverage**: Lipsește raportare acoperire teste
9. **Static analysis**: PHPStan/Psalm nu configurate
10. **Code style**: PHP-CS-Fixer nu configurat

---

## 🎯 SPRINTURILE DE OPTIMIZARE

---

## 📦 SPRINT 1: Curățare și Testare (Săptămâni 1-2)

**Obiectiv**: Eliminare cod de test din producție și crearea infrastructurii de testare

### Săptămâna 1: Curățare Cod de Test

#### Ziua 1-2: Eliminare Comenzi de Test
**Task-uri**:
- [ ] Creare director `dev-tools/test-commands/` pentru arhivare
- [ ] Mutare comenzi de test din `src/Command/` în `dev-tools/`
- [ ] Ștergere director `src/Command/Test/`
- [ ] Actualizare `.gitignore` pentru excluderea `dev-tools/`

**Comenzi de mutat/șters** (15 fișiere):
```bash
# Comenzi principale
src/Command/TestCategoryChangeListenerCommand.php
src/Command/TestRedirectCommandsCommand.php
src/Command/TestRedirectManagementApiCommand.php
src/Command/TestSlugAvailabilityApiCommand.php
src/Command/TestSlugChangeListenerCommand.php
src/Command/TestSlugLookupApiCommand.php
src/Command/TestTransliterationCommand.php
src/Command/TestValidatorCommand.php
src/Command/TestReservedSlugCommand.php

# Comenzi din subdirector
src/Command/Test/TestCacheCommand.php
src/Command/Test/TestJwtTokenCommand.php
src/Command/Test/TestMigrationLoggerCommand.php
src/Command/Test/TestNewscoopConnectionCommand.php
src/Command/Test/TestThumbnailGenerationCommand.php
```

**Rezultat așteptat**: -15 fișiere, ~2,000 linii de cod eliminate

---

#### Ziua 3: Eliminare Entități de Test
**Task-uri**:
- [ ] Ștergere `src/Entity/TestArticle.php`
- [ ] Ștergere `src/Entity/TestArticleTranslation.php`
- [ ] Verificare referințe în cod (grep)
- [ ] Creare migrație pentru ștergerea tabelelor `test_article` (dacă există)
- [ ] Rulare migrație pe dev

**Verificări**:
```bash
# Caută referințe la TestArticle
grep -r "TestArticle" src/ --exclude-dir=vendor
grep -r "test_article" src/ --exclude-dir=vendor
```

**Rezultat așteptat**: -2 entități, tabel de test eliminat

---

#### Ziua 4: Curățare Fișiere .env
**Task-uri**:
- [ ] **URGENT**: Ștergere `.env` din repository (commited by mistake)
- [ ] Adăugare `.env` în `.gitignore` (dacă nu există)
- [ ] Ștergere `.env.dev` (duplicat)
- [ ] Verificare că `.env.example` este up-to-date
- [ ] Documentare în README: "Copiază `.env.example` la `.env.local`"

**Comenzi**:
```bash
# Șterge .env din git history (IMPORTANT!)
git rm --cached .env
git commit -m "Remove .env from repository (security fix)"

# Șterge .env.dev (redundant)
rm .env.dev
```

**⚠️ Atenție Securitate**: Dacă `.env` conține secrets (API keys, passwords), acestea trebuie regenerate!

**Rezultat așteptat**: -2 fișiere .env, risc securitate eliminat

---

#### Ziua 5: Pregătire pentru Testare
**Task-uri**:
- [ ] Instalare PHPUnit 11+ (dacă nu e instalat)
- [ ] Creare `phpunit.xml` configurat corect
- [ ] Creare structură directoare: `tests/Unit/`, `tests/Integration/`, `tests/Functional/`
- [ ] Configurare bootstrap test în `tests/bootstrap.php`
- [ ] Instalare `symfony/test-pack`

**Configurare phpunit.xml**:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
        <testsuite name="Functional">
            <directory>tests/Functional</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory suffix=".php">src</directory>
        </include>
        <exclude>
            <directory>src/DataFixtures</directory>
            <directory>src/Kernel.php</directory>
        </exclude>
    </coverage>
</phpunit>
```

**Rezultat așteptat**: Infrastructură de testare configurată

---

### Săptămâna 2: Crearea Testelor PHPUnit

#### Ziua 6-7: Teste pentru Entități Core
**Task-uri**:
- [ ] Teste pentru `Article` entity (validări, relații, translations)
- [ ] Teste pentru `Category` entity
- [ ] Teste pentru `Author` entity
- [ ] Teste pentru `Image` entity

**Exemple teste**:
```php
// tests/Unit/Entity/ArticleTest.php
class ArticleTest extends TestCase
{
    public function testArticleCreation(): void
    {
        $article = new Article();
        $article->setTitle('Test Article');

        $this->assertEquals('Test Article', $article->getTitle());
    }

    public function testSlugGeneration(): void
    {
        $article = new Article();
        $article->setTitle('Articol de Test în Română');

        // Test transliteration
        $this->assertEquals('articol-de-test-in-romana', $article->getSlug());
    }
}
```

**Target**: 30+ teste noi

---

#### Ziua 8-9: Teste pentru Servicii
**Task-uri**:
- [ ] Teste pentru `ImageService` (upload, thumbnail generation)
- [ ] Teste pentru `ElasticService` (indexing, search)
- [ ] Teste pentru `TransliterationService`
- [ ] Teste extinse pentru `SlugLookupService` (deja există 23, adăugați edge cases)

**Target**: 40+ teste noi

---

#### Ziua 10: Teste pentru API Endpoints
**Task-uri**:
- [ ] Teste funcționale pentru `/api/articles`
- [ ] Teste pentru `/api/categories`
- [ ] Teste pentru `/api/images`
- [ ] Teste pentru `/api/slug/*` endpoints
- [ ] Teste pentru `/api/redirects/*` endpoints

**Exemplu test API**:
```php
// tests/Functional/Api/ArticleApiTest.php
class ArticleApiTest extends WebTestCase
{
    public function testGetArticles(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/articles');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    }
}
```

**Target**: 25+ teste noi

---

**SPRINT 1 - Rezultate Așteptate**:
- ✅ -17 fișiere de test eliminate
- ✅ -2 entități de test eliminate
- ✅ -2 fișiere .env redundante
- ✅ +95 teste PHPUnit noi
- ✅ Infrastructură testare completă
- ✅ Code coverage: ~30-40%

---

## 📚 SPRINT 2: Documentație și Organizare (Săptămâni 3-4)

**Obiectiv**: Documentare completă a codului și organizarea comenzilor

### Săptămâna 3: Documentație API și Entități

#### Ziua 11-12: Documentație Entități
**Task-uri**:
- [ ] Creare `docs/entities/` directory
- [ ] Documentare `Article.md` - fields, relationships, validations
- [ ] Documentare `Category.md`
- [ ] Documentare `Author.md`
- [ ] Documentare `Image.md` și `Thumbnail.md`
- [ ] Documentare `LiveText*.md` (toate entitățile LiveText)
- [ ] Documentare `UrlRedirect.md`

**Template Entitate**:
```markdown
# Article Entity

## Overview
Entitatea principală pentru articole de știri.

## Fields
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| id | int | Yes | Primary key |
| title | string(255) | Yes | Article title (translatable) |
| slug | string(255) | Yes | URL-friendly slug |
...

## Relationships
- ManyToOne: Category
- ManyToOne: Author
- OneToMany: ArticleImage

## Validations
- Title: NotBlank, Length(min: 3, max: 255)
- Slug: ReservedSlug validator

## Translations
Translatable fields: title, lead, content (using Gedmo)
Locales: ro, en, ru

## Usage Examples
...
```

**Rezultat așteptat**: 15+ documente entități

---

#### Ziua 13: Documentație API Endpoints
**Task-uri**:
- [ ] Creare `docs/api/` directory
- [ ] Documentare `articles-api.md` (CRUD, filters, pagination)
- [ ] Documentare `categories-api.md`
- [ ] Documentare `images-api.md`
- [ ] Documentare `live-text-api.md`
- [ ] Documentare `slug-lookup-api.md` (deja parțial în sprint-1-report)
- [ ] Documentare `redirects-api.md`

**Template API**:
```markdown
# Articles API

## Endpoints

### GET /api/articles
List all articles with pagination.

**Query Parameters**:
- page: int (default: 1)
- itemsPerPage: int (default: 30)
- locale: string (ro|en|ru)
- status: string (draft|published|scheduled)
- category: int (category ID)

**Response** (200):
...

### POST /api/articles
Create new article (requires authentication).
...
```

**Rezultat așteptat**: 8+ documente API

---

#### Ziua 14: Documentație Servicii
**Task-uri**:
- [ ] Creare `docs/services/` directory
- [ ] Documentare `ImageService.md` (upload flow, thumbnails)
- [ ] Documentare `ElasticService.md` (indexing strategy, queries)
- [ ] Documentare `SlugLookupService.md`
- [ ] Documentare `LiveText*.md` (analytics, A/B testing, notifications)
- [ ] Documentare `Import/` services

**Rezultat așteptat**: 10+ documente servicii

---

#### Ziua 15: README și Ghid Dezvoltator
**Task-uri**:
- [ ] Actualizare `README.md` cu structura completă
- [ ] Creare `docs/DEVELOPER_GUIDE.md`
- [ ] Creare `docs/TESTING_GUIDE.md`
- [ ] Creare `docs/DEPLOYMENT_GUIDE.md`

**Rezultat așteptat**: Documentație completă pentru dezvoltatori

---

### Săptămâna 4: Organizare Comenzi și Cod

#### Ziua 16-17: Reorganizare Comenzi
**Task-uri**:
- [ ] Creare subdirectoare în `src/Command/`:
  - `Elasticsearch/` - comenzi ES (4 comenzi)
  - `Redirect/` - comenzi redirect (4 comenzi)
  - `Stats/` - comenzi statistici (3 comenzi)
  - `Maintenance/` - comenzi întreținere
- [ ] Mutare comenzi în subdirectoare corespunzătoare
- [ ] Actualizare namespace-uri și autoloading
- [ ] Testare că toate comenzile funcționează

**Structură finală**:
```
src/Command/
├── Elasticsearch/
│   ├── CreateIndexCommand.php
│   ├── CreateImageIndexCommand.php
│   ├── IndexArticlesCommand.php
│   └── IndexImagesCommand.php
├── Redirect/
│   ├── CleanupCommand.php
│   ├── ConsolidateCommand.php
│   ├── HealthCommand.php
│   └── StatsCommand.php
├── Stats/
│   ├── AggregateStatsCommand.php
│   ├── CleanupStatsCommand.php
│   └── TrendingArticlesCommand.php
├── Maintenance/
│   ├── CleanupExpiredLocksCommand.php
│   ├── CacheClearCommand.php
│   └── CacheWarmCommand.php
├── Import/
│   └── (existing import commands)
└── LiveText/
    └── (existing LiveText commands)
```

**Rezultat așteptat**: Comenzi organizate logic în 6 categorii

---

#### Ziua 18-19: Refactoring și Optimizări Minore
**Task-uri**:
- [ ] Eliminare cod comentat din fișiere
- [ ] Curățare import-uri nefolosite
- [ ] Standardizare PHPDoc comments
- [ ] Adăugare type hints unde lipsesc (PHP 8.4 features)
- [ ] Verificare și actualizare deprecated code

**Tool-uri**:
```bash
# Găsește import-uri nefolosite
composer require --dev friendsofphp/php-cs-fixer

# Rulează PHP-CS-Fixer
vendor/bin/php-cs-fixer fix src/ --dry-run --diff
```

**Rezultat așteptat**: Cod mai curat, mai consistent

---

#### Ziua 20: Documentație Comenzi
**Task-uri**:
- [ ] Creare `docs/commands/` directory
- [ ] Documentare fiecare comandă cu:
  - Scop și utilizare
  - Opțiuni și argumente
  - Exemple practice
  - Best practices (când să rulezi, frecvență)
- [ ] Creare `docs/COMMANDS_INDEX.md` cu listă completă

**Rezultat așteptat**: 25+ comenzi documentate

---

**SPRINT 2 - Rezultate Așteptate**:
- ✅ +45 documente markdown create
- ✅ Comenzi organizate în 6 categorii
- ✅ README complet actualizat
- ✅ Developer Guide complet
- ✅ Cod refactorizat și curățat

---

## 🔧 SPRINT 3: Optimizare și Performanță (Săptămâni 5-6)

**Obiectiv**: Îmbunătățirea performanței și configurarea instrumentelor de analiză

### Săptămâna 5: Cache și Database Optimization

#### Ziua 21-22: Optimizare Cache
**Task-uri**:
- [ ] Configurare APCu pentru cache metadata
- [ ] Configurare OPcache pentru PHP bytecode
- [ ] Configurare Redis pentru session și cache aplicație
- [ ] Implementare cache tags pentru invalidare granulară
- [ ] Creare comenzi pentru warm-up cache optimizat

**Configurare APCu** (`config/packages/cache.yaml`):
```yaml
framework:
    cache:
        app: cache.adapter.redis
        system: cache.adapter.redis

        pools:
            cache.metadata:
                adapter: cache.adapter.apcu

            cache.doctrine.orm.default.query:
                adapter: cache.adapter.redis

            cache.doctrine.orm.default.result:
                adapter: cache.adapter.redis
```

**Rezultat așteptat**: Timp răspuns redus cu 30-50%

---

#### Ziua 23: Optimizare Query-uri Database
**Task-uri**:
- [ ] Instalare și configurare Symfony Profiler în dev
- [ ] Instalare DoctrineBundle debug toolbar
- [ ] Audit query-uri N+1 cu profiler
- [ ] Adăugare eager loading în State Providers unde lipsește
- [ ] Creare indecși database pentru query-uri lente
- [ ] Optimizare query-uri în Repository-uri

**Verificare N+1**:
```bash
# Activează SQL logging în dev
symfony server:log
# Rulează endpoint și verifică număr query-uri
curl http://127.0.0.1:8081/api/articles?page=1
```

**Rezultat așteptat**: -50% query-uri pe page load

---

#### Ziua 24: Optimizare Elasticsearch
**Task-uri**:
- [ ] Review mapping-uri Elasticsearch (optimizare fields)
- [ ] Configurare bulk indexing pentru import
- [ ] Implementare async reindexing cu Messenger
- [ ] Optimizare query-uri de căutare
- [ ] Creare alias-uri pentru zero-downtime reindex

**Rezultat așteptat**: Indexare 5x mai rapidă, search 2x mai rapid

---

#### Ziua 25: Optimizare Imagini
**Task-uri**:
- [ ] Review thumbnail profiles (verificare dimensiuni optime)
- [ ] Implementare lazy generation pentru thumbnails rare
- [ ] Optimizare calitate WebP (balance size/quality)
- [ ] Implementare cleanup pentru thumbnails nefolosite
- [ ] Verificare VichUploader configuration

**Rezultat așteptat**: -30% spațiu stocare imagini

---

### Săptămâna 6: Static Analysis și Code Quality

#### Ziua 26-27: Configurare PHPStan
**Task-uri**:
- [ ] Instalare PHPStan cu Symfony extension
- [ ] Creare `phpstan.neon` configurat pentru nivel 6 (apoi 8)
- [ ] Creare PHPStan baseline pentru erori existente
- [ ] Rezolvare erori nivel 6 (critical)
- [ ] Configurare PHPStan în CI/CD

**phpstan.neon**:
```neon
parameters:
    level: 6
    paths:
        - src
    excludePaths:
        - src/Kernel.php
        - src/DataFixtures
    symfony:
        containerXmlPath: var/cache/dev/App_KernelDevDebugContainer.xml
    ignoreErrors:
        # Baseline - vor fi rezolvate gradual
```

**Rezultat așteptat**: 0 erori PHPStan nivel 6

---

#### Ziua 28: Configurare PHP-CS-Fixer
**Task-uri**:
- [ ] Instalare PHP-CS-Fixer
- [ ] Creare `.php-cs-fixer.php` cu PER coding standard
- [ ] Rulare fix pe tot codul
- [ ] Commit rezultate
- [ ] Adăugare în pre-commit hook

**.php-cs-fixer.php**:
```php
<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        '@PHP84Migration' => true,
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder);
```

**Rezultat așteptat**: Cod consistent, 100% PER standard

---

#### Ziua 29: Security Audit
**Task-uri**:
- [ ] Rulare `symfony security:check`
- [ ] Update dependențe vulnerabile
- [ ] Review CORS configuration
- [ ] Review JWT expiration times
- [ ] Audit SQL injection risks (parametrizare)
- [ ] Verificare XSS în serialization
- [ ] Review file upload security

**Rezultat așteptat**: 0 vulnerabilități cunoscute

---

#### Ziua 30: Performance Profiling
**Task-uri**:
- [ ] Instalare Blackfire sau Tideways pentru profiling
- [ ] Profile endpoints critice (articles list, article detail)
- [ ] Identificare bottlenecks
- [ ] Optimizare codepath-uri lente
- [ ] Creare benchmark pentru comparație

**Rezultat așteptat**: Profile complet de performanță

---

**SPRINT 3 - Rezultate Așteptate**:
- ✅ Performanță îmbunătățită cu 40-60%
- ✅ PHPStan nivel 6 implementat
- ✅ Cod formatat consistent (PER standard)
- ✅ 0 vulnerabilități securitate
- ✅ Cache optimizat (APCu + Redis)

---

## 🚀 SPRINT 4: CI/CD și Monitoring (Săptămâni 7-8)

**Obiectiv**: Automatizare și monitoring pentru mentenanță continuă

### Săptămâna 7: CI/CD Pipeline

#### Ziua 31-32: GitHub Actions / GitLab CI
**Task-uri**:
- [ ] Creare `.github/workflows/ci.yml` sau `.gitlab-ci.yml`
- [ ] Job: Run PHPUnit tests
- [ ] Job: Run PHPStan analysis
- [ ] Job: Run PHP-CS-Fixer check
- [ ] Job: Security check
- [ ] Job: Build assets
- [ ] Configurare badge-uri în README

**GitHub Actions Example**:
```yaml
name: CI

on: [push, pull_request]

jobs:
  tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: vendor/bin/phpunit

  phpstan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
      - name: Run PHPStan
        run: vendor/bin/phpstan analyse
```

**Rezultat așteptat**: Pipeline CI complet funcțional

---

#### Ziua 33: Database Seeding și Fixtures
**Task-uri**:
- [ ] Creare fixtures pentru dev environment
- [ ] Fixtures: 10 categorii, 50 autori, 500 articole
- [ ] Fixtures: 100 imagini cu thumbnails
- [ ] Comandă: `app:db:seed` pentru reset complet
- [ ] Documentare process de seeding

**Rezultat așteptat**: Dev environment rapid de setup

---

#### Ziua 34: Deployment Scripts
**Task-uri**:
- [ ] Creare `bin/deploy.sh` script
- [ ] Steps: backup DB, pull code, composer install, migrations, cache clear
- [ ] Creare `bin/rollback.sh` script
- [ ] Testare pe staging environment

**Rezultat așteptat**: Deploy automatizat, zero-downtime

---

#### Ziua 35: Cron Jobs și Schedulers
**Task-uri**:
- [ ] Configurare Symfony Scheduler
- [ ] Schedule: `app:publish-scheduled-articles` (every 5 min)
- [ ] Schedule: `app:cleanup-expired-locks` (hourly)
- [ ] Schedule: `app:redirects:cleanup` (weekly)
- [ ] Schedule: `app:aggregate-stats` (daily)
- [ ] Documentare toate cron jobs

**Rezultat așteptat**: Mentenanță automată configurată

---

### Săptămâna 8: Monitoring și Finalizare

#### Ziua 36-37: Monitoring și Alerting
**Task-uri**:
- [ ] Configurare Prometheus metrics export
- [ ] Creare Grafana dashboard pentru:
  - Request rate și latency
  - Error rate
  - Database query performance
  - Cache hit rate
  - Elasticsearch performance
- [ ] Configurare alerte pentru:
  - Error rate > 1%
  - Response time > 500ms
  - Disk space < 10%

**Rezultat așteptat**: Monitoring complet operațional

---

#### Ziua 38: Logging și Debugging
**Task-uri**:
- [ ] Configurare Monolog cu multiple canale
- [ ] Separate log files: app.log, security.log, elasticsearch.log
- [ ] Configurare log rotation (7 zile retention)
- [ ] Integrare cu Sentry pentru error tracking
- [ ] Creare comenzi pentru log analysis

**Rezultat așteptat**: Logging centralizat și structurat

---

#### Ziua 39: Documentation Finalization
**Task-uri**:
- [ ] Review și update toate documentele markdown
- [ ] Creare `CHANGELOG.md` cu toate schimbările
- [ ] Creare `MIGRATION_GUIDE.md` (dacă sunt breaking changes)
- [ ] Update `CLAUDE.md` cu noua structură
- [ ] Creare video walkthrough (optional)

**Rezultat așteptat**: Documentație 100% completă

---

#### Ziua 40: Final Review și Cleanup
**Task-uri**:
- [ ] Review complet cod (code review session)
- [ ] Verificare toate testele (target: >70% coverage)
- [ ] Verificare toate comenzile funcționează
- [ ] Verificare toate endpoint-urile API
- [ ] Ștergere TODO comments vechi
- [ ] Creare raport final de optimizare

**Rezultat așteptat**: Backend production-ready, optimizat

---

**SPRINT 4 - Rezultate Așteptate**:
- ✅ CI/CD pipeline complet
- ✅ Monitoring și alerting operațional
- ✅ Deploy automatizat
- ✅ Cron jobs configurate
- ✅ Logging centralizat
- ✅ Documentație finalizată

---

## 📈 Metrici de Succes

### Înainte de Optimizare
- ❌ 17 fișiere de test în producție
- ❌ 2 entități de test în producție
- ❌ 2 teste PHPUnit (0.9% coverage)
- ❌ 5 fișiere .env (risc securitate)
- ❌ 1 document markdown (incomplet)
- ❌ Comenzi dezorganizate
- ❌ Fără PHPStan/PHP-CS-Fixer
- ❌ Fără CI/CD
- ❌ Performance neoptimizată

### După Optimizare (Target)
- ✅ 0 fișiere de test în producție
- ✅ 0 entități de test
- ✅ 95+ teste PHPUnit (70%+ coverage)
- ✅ 3 fișiere .env (securizate)
- ✅ 50+ documente markdown (complet)
- ✅ Comenzi organizate în 6 categorii
- ✅ PHPStan nivel 6+ operațional
- ✅ PHP-CS-Fixer (PER standard)
- ✅ CI/CD pipeline complet
- ✅ Performance +50% improvement
- ✅ Monitoring și alerting activ

---

## 🎯 Prioritizare

### Must Have (Critical)
1. ✅ **Sprint 1, Săpt 1**: Eliminare comenzi test și entități test
2. ✅ **Sprint 1, Săpt 1**: Fix .env security issue
3. ✅ **Sprint 1, Săpt 2**: Teste PHPUnit pentru componente core
4. ✅ **Sprint 3, Săpt 5**: Optimizare cache și database

### Should Have (Important)
5. ✅ **Sprint 2**: Documentație completă
6. ✅ **Sprint 3, Săpt 6**: PHPStan și PHP-CS-Fixer
7. ✅ **Sprint 4, Săpt 7**: CI/CD pipeline

### Nice to Have (Improvements)
8. ✅ **Sprint 2, Săpt 4**: Reorganizare comenzi
9. ✅ **Sprint 4, Săpt 8**: Monitoring avansat
10. ✅ **Sprint 4, Săpt 8**: Video walkthrough

---

## 📋 Checklist Final

### Cod
- [ ] 0 comenzi de test în src/
- [ ] 0 entități de test în src/
- [ ] Toate comenzile organizate logic
- [ ] PHPStan nivel 6+ passing
- [ ] PHP-CS-Fixer (PER standard) applied
- [ ] 0 vulnerabilități securitate

### Testare
- [ ] 95+ teste PHPUnit
- [ ] Code coverage >70%
- [ ] Toate testele passing
- [ ] Integration tests pentru API

### Documentație
- [ ] 50+ documente markdown
- [ ] README.md complet
- [ ] DEVELOPER_GUIDE.md
- [ ] Toate entitățile documentate
- [ ] Toate API endpoints documentate
- [ ] Toate comenzile documentate

### Infrastructure
- [ ] CI/CD pipeline funcțional
- [ ] Monitoring operațional
- [ ] Logging centralizat
- [ ] Deploy scripts create
- [ ] Cron jobs configurate

### Performance
- [ ] Cache optimizat (APCu + Redis)
- [ ] Query-uri optimizate (0 N+1)
- [ ] Elasticsearch optimizat
- [ ] Response time <200ms (avg)

---

## 🚀 Cum să Începi

### Pas 1: Backup
```bash
# Backup database
pg_dump deschide_news > backup_$(date +%Y%m%d).sql

# Backup code
git commit -am "Pre-optimization backup"
git tag v1.0-pre-optimization
```

### Pas 2: Creează Branch
```bash
git checkout -b optimization/sprint-1-cleanup
```

### Pas 3: Începe Sprint 1, Ziua 1
```bash
# Citește planul pentru ziua 1
cat OPTIMIZATION_PLAN.md | grep -A 20 "Ziua 1-2: Eliminare Comenzi de Test"

# Creează director arhivă
mkdir -p dev-tools/test-commands

# Începe mutarea fișierelor...
```

---

## 📞 Contact și Suport

**Întrebări sau probleme?**
- Review acest plan cu echipa
- Creează issue-uri pentru fiecare task
- Folosește Jira/Trello pentru tracking

**Succes cu optimizarea! 🎉**

---

**Ultimă actualizare**: 4 Noiembrie 2025
**Versiune**: 1.0
**Status**: 📋 Gata de implementare
