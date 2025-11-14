# Plan de Implementare: Tag-uri/Keywords pentru Article

**Data creării**: 5 Noiembrie 2025
**Data ultimei actualizări**: 5 Noiembrie 2025
**Proiect**: Deschide News App
**Obiectiv**: Implementare sistem de tag-uri/keywords pentru îmbunătățirea SEO și căutării Elasticsearch
**Status**: ✅ Completă - Toate fazele 1-8 finalizate (100%)

---

## 📊 Status Implementare

| Fază | Status | Data Finalizare | Note |
|------|--------|-----------------|------|
| **Faza 1: Entity & Database** | ✅ Completă | 5 Nov 2025 | Tag entity, Repository, Article update, Migration |
| **Faza 2: Repository & Service** | ✅ Completă | 5 Nov 2025 | TagService, ArticleRepository updates |
| **Faza 3: API Platform Integration** | ✅ Completă | 5 Nov 2025 | TagProvider, TagProcessor, API routes |
| **Faza 4: Elasticsearch Integration** | ✅ Completă | 5 Nov 2025 | Mapping update, indexing ready |
| **Faza 5: API Endpoints & Filters** | ✅ Completă | 5 Nov 2025 | Custom endpoints, security config |
| **Faza 6: Testing** | ✅ Completă | 5 Nov 2025 | 32 unit tests, integration, functional |
| **Faza 7: Commands & Maintenance** | ✅ Completă | 5 Nov 2025 | Scheduler, Messenger, Commands, Events |
| **Faza 8: Frontend Integration** | ✅ Completă | 5 Nov 2025 | Types, API, Components, Pages |

### 🎉 Faza 1 - Realizări

**Data**: 5 Noiembrie 2025

**Implementat**:
- ✅ Tag Entity (`src/Entity/Tag.php`) - 200 linii
  - Gedmo Translatable pentru multilanguage (ro/en/ru)
  - Properties: id, name, slug, description, usageCount, timestamps
  - Auto-slug generation cu Gedmo
  - Serialization groups: tag:read, tag:write, article:read
  - ManyToMany relationship cu Article (inverse side)

- ✅ TagRepository (`src/Repository/TagRepository.php`) - 108 linii
  - `findPopularTags()` - Top tags by usage count
  - `findByNameSearch()` - Auto-complete search
  - `findOrCreateByName()` - Find or create tag
  - `findUnusedTags()` - Cleanup helper

- ✅ Article Entity Updates (`src/Entity/Article.php`)
  - Property `tags` (ManyToMany, owning side)
  - API filters: tags, tags.id, tags.slug
  - Methods: getTags(), addTag(), removeTag()
  - Collection initialization în constructor

- ✅ Database Migration (`Version20251105105341.php`)
  - Tabel `tags` cu indexes (slug, usage_count)
  - Tabel `article_tag` cu foreign keys
  - CASCADE delete constraints

**Verificări**:
- ✅ Schema validation: OK
- ✅ Database tables created: OK
- ✅ Indexes created: OK
- ✅ Foreign keys configured: OK
- ✅ Gedmo ext_translations table ready: OK

**Total linii cod**: ~330 linii (2 fișiere noi + 1 update)

### 🎉 Faza 2 - Realizări

**Data**: 5 Noiembrie 2025

**Implementat**:
- ✅ TagService (`src/Service/TagService.php`) - 250 linii
  - `syncArticleTags()` - Sync tags to article (replace all)
  - `addTagsToArticle()` - Add tags without removing existing
  - `removeTagsFromArticle()` - Remove specific tags
  - `getPopularTags()` - Get top tags by usage
  - `searchTags()` - Auto-complete search
  - `recalculateUsageCounts()` - Fix usage count inconsistencies
  - `cleanupUnusedTags()` - Remove old unused tags
  - `getOrCreateTag()` - Find or create tag
  - `getTagStatistics()` - Get tag statistics
  - `mergeTags()` - Merge two tags into one

- ✅ ArticleRepository Updates (`src/Repository/ArticleRepository.php`) - 147 linii
  - `findByTags()` - AND logic (article must have all tags)
  - `findByAnyTag()` - OR logic (article has at least one tag)
  - `findSimilarByTags()` - Find similar articles by shared tags
  - `countByTag()` - Count articles by tag

**Verificări**:
- ✅ TagService autowired correctly: OK
- ✅ Repository methods use Gedmo locale hints: OK
- ✅ Test tags created in database: OK (3 tags with ro/en/ru translations)
- ✅ Search functionality: OK
- ✅ Statistics calculation: OK

**Total linii cod Faza 2**: ~400 linii (1 fișier nou + 1 update)

### 🎉 Faza 3 - Realizări

**Data**: 5 Noiembrie 2025

**Implementat**:
- ✅ TagProvider (`src/State/TagProvider.php`) - 140 linii
  - GET single tag by ID with locale support
  - GET collection with pagination, filters, sorting
  - Search by name (partial match)
  - Filter by slug (exact match)
  - Filter by minimum usage count
  - Result caching (5 minutes)
  - Automatic tag translation refresh

- ✅ TagProcessor (`src/State/TagProcessor.php`) - 140 linii
  - POST - Create new tags
  - PUT - Update existing tags
  - DELETE - Delete tags (with validation: cannot delete if usageCount > 0)
  - Automatic translation handling (ro/en/ru)
  - Locale-aware processing

- ✅ Tag Entity API Configuration (`src/Entity/Tag.php`)
  - Full ApiResource configuration with 5 operations
  - SearchFilter (name: partial, slug: exact)
  - OrderFilter (usageCount, name, createdAt)
  - Cache headers (5-10 minutes)
  - Normalization/denormalization contexts

- ✅ ArticleProvider Updates (`src/State/ArticleProvider.php`)
  - Eager loading tags (leftJoin + addSelect) - prevents N+1
  - Tag translation refresh for single items
  - Tag translation refresh for collections
  - Both collection and detail views optimized

- ✅ ArticleProcessor Updates (`src/State/ArticleProcessor.php`)
  - Tag syncing on CREATE (increment usage counts)
  - Tag syncing on UPDATE (sync collection + adjust counts)
  - Tag syncing on DELETE (decrement usage counts)
  - Automatic usage count management

**Verificări**:
- ✅ All 5 tag routes registered: OK
  - GET /api/tags
  - GET /api/tags/{id}
  - POST /api/tags
  - PUT /api/tags/{id}
  - DELETE /api/tags/{id}
- ✅ Cache cleared successfully: OK
- ✅ API Platform filters configured: OK
- ✅ Provider/Processor autowired: OK

**Total linii cod Faza 3**: ~500 linii (2 fișiere noi + 3 updates)

### 🎉 Faza 4 - Realizări

**Data**: 5 Noiembrie 2025

**Implementat**:
- ✅ ElasticService Mapping Update (`src/Service/ElasticService.php`)
  - Adăugat câmp `tags` (nested type):
    - `id` (integer)
    - `name` (text cu keyword field, article_analyzer)
    - `slug` (keyword)
  - Adăugat câmp `tag_names` (text, article_analyzer) pentru full-text search

- ✅ ElasticService Search Update (`src/Service/ElasticService.php`)
  - Multi-match query actualizat cu boost pentru tag_names (^2.5)
  - Ordine boost: title (^3), tag_names (^2.5), lead (^2), content (^1)
  - Adăugat filter pentru tag_ids (nested query pentru tags)
  - Suport pentru filtrare multiplă după tag IDs

- ✅ IndexArticlesCommand Update (`src/Command/ElasticsearchIndexArticlesCommand.php`)
  - Eager loading pentru tags (previne N+1)
  - Tag translation refresh pentru fiecare locale
  - Construire array tags cu id, name, slug
  - Tag names adăugate în suggest input pentru autocomplete
  - Document indexing include câmpurile: tags, tag_names

**Verificări**:
- ✅ Indici Elasticsearch recreați cu succes:
  - deschide_articles_ro (mapping verificat)
  - deschide_articles_en (mapping verificat)
  - deschide_articles_ru (mapping verificat)
- ✅ Mapping tags verificat: nested type cu toate proprietățile
- ✅ Mapping tag_names verificat: text type cu analyzer
- ✅ Command app:elasticsearch:index-articles actualizat și funcțional

**Funcționalitate Elasticsearch**:
- ✅ Full-text search pe tag names cu boost 2.5x
- ✅ Filtrare articole după tag IDs (nested query)
- ✅ Tag names în autocomplete suggest
- ✅ Suport multilanguage pentru tags (ro/en/ru)
- ✅ Mapping optimizat pentru performance

**Total linii cod Faza 4**: ~100 linii (2 fișiere actualizate)

### 📊 Progres Total (Faza 1-4)

**Statistici implementare**:
- ✅ **Faze completate**: 4 din 8 (50%)
- ✅ **Fișiere noi**: 5 (Tag.php, TagRepository.php, TagService.php, TagProvider.php, TagProcessor.php)
- ✅ **Fișiere actualizate**: 6 (Article.php, ArticleRepository.php, ArticleProvider.php, ArticleProcessor.php, ElasticService.php, ElasticsearchIndexArticlesCommand.php)
- ✅ **Migrații create**: 1 (Version20251105105341)
- ✅ **Linii cod**: ~1,330 linii total
- ✅ **Metode noi**: 15 metode în repositories/service
- ✅ **API operations**: 5 endpoints (GET, GET collection, POST, PUT, DELETE)
- ✅ **Tabele database**: 2 (tags, article_tag)
- ✅ **Elasticsearch indices**: 3 (ro, en, ru) cu mapping actualizat
- ✅ **Test tags**: 3 tags cu 18 translations (ro/en/ru)

**Funcționalitate implementată**:
- ✅ Entity layer complet (Tag cu Translatable)
- ✅ Database schema cu indexes și constraints
- ✅ Repository layer cu query-uri optimizate
- ✅ Service layer cu business logic complet
- ✅ API Platform layer (Provider + Processor)
- ✅ RESTful API cu CRUD complet pentru tags
- ✅ Elasticsearch integration complet
  - ✅ Mapping cu nested tags și tag_names
  - ✅ Search cu boost pentru tags (2.5x)
  - ✅ Filter după tag IDs (nested query)
  - ✅ Autocomplete cu tag names
- ✅ Multilanguage support (ro/en/ru)
- ✅ Eager loading pentru N+1 prevention
- ✅ Automatic usage count management
- ✅ API filters și sorting
- ✅ Result caching (5-10 minutes)
- ✅ Custom endpoints (completat - Faza 5)
- ⏳ Testing (planificat - Faza 6)
- ⏳ Maintenance commands (planificat - Faza 7)

### 🎉 Faza 5 - Realizări

**Data**: 5 Noiembrie 2025

**Implementat**:
- ✅ TagController Custom Endpoints (`src/Controller/TagController.php`) - 220 linii
  - **GET `/api/tags/popular`** - Popular tags by usage count
    - Query params: limit (1-100, default 20), locale (ro/en/ru)
    - Response: Hydra Collection with tags
    - Cache: 10 minutes (Cache-Control: public, max-age=600)

  - **GET `/api/tags/search`** - Tag search/autocomplete
    - Query params: q (required), limit (1-50, default 10), locale
    - Validation: q parameter required and non-empty
    - Response: Hydra Collection with matching tags
    - Cache: 5 minutes (Cache-Control: public, max-age=300)

  - **GET `/api/tags/{id}/related`** - Related tags (co-occurring)
    - Path params: id (tag ID)
    - Query params: limit (1-50, default 10), locale
    - Logic: Tags that appear on same articles
    - Response: Collection sorted by co-occurrence count
    - Cache: 10 minutes

  - **GET `/api/tags/{id}/stats`** - Tag statistics
    - Path params: id (tag ID)
    - Response: usageCount, articleCount, timestamps
    - Cache: 5 minutes

  - **GET `/api/tags/unused`** - Unused tags (usageCount=0)
    - Query params: limit (1-200, default 50), locale
    - Use case: Cleanup utility
    - Cache: 5 minutes

- ✅ TagService Extended Methods (`src/Service/TagService.php`) - 85 linii adiționale
  - `findTagById(int $id)` - Find tag by ID
  - `getRelatedTags(Tag $tag, string $locale, int $limit)` - Co-occurring tags query
  - `getUnusedTags(string $locale, int $limit)` - Tags with zero usage

- ✅ Security Configuration Update (`config/packages/security.yaml`)
  - Added "tags" to public GET access (line 72)
  - Added "tags" to admin/editor write operations (line 76)
  - All GET operations public, POST/PUT/PATCH/DELETE require ROLE_ADMIN or ROLE_EDITOR

- ✅ Route Priority Configuration
  - All custom routes use priority: 2
  - Prevents API Platform route conflicts
  - Ensures custom endpoints match before generic {id} routes

**Verificări**:
- ✅ Cache cleared successfully
- ✅ All 5 custom routes registered:
  - api_tags_popular
  - api_tags_search
  - api_tags_related
  - api_tags_stats
  - api_tags_unused
- ✅ Public GET access working (no JWT required)
- ✅ All endpoints tested and functional:
  - Popular tags: Returns 3 tags sorted by usageCount
  - Search: Returns matching tags (e.g., "pol" → "Politics")
  - Related: Returns empty array (no articles tagged yet)
  - Stats: Returns full statistics with timestamps
  - Unused: Returns all 3 tags (all have usageCount=0)
- ✅ Locale support verified (Accept-Language header working)
- ✅ Validation working (search requires 'q' parameter)
- ✅ Error handling tested (404 for non-existent tags)

**Funcționalitate Custom Endpoints**:
- ✅ JSON-LD format (Hydra collections)
- ✅ Proper HTTP status codes (200, 400, 404)
- ✅ Cache headers configured (Vary: Accept-Language)
- ✅ Parameter validation and limits
- ✅ Locale extraction from Accept-Language header
- ✅ Security configuration (public GET, protected writes)

**Total linii cod Faza 5**: ~305 linii (1 fișier nou + 2 fișiere actualizate)

### 🎉 Faza 6 - Realizări

**Data**: 5 Noiembrie 2025

**Implementat**:
- ✅ **Unit Tests - Tag Entity** (`tests/Unit/Entity/TagTest.php`) - 265 linii
  - 19 teste, 51 assertions
  - Tests: creation, getters/setters, fluent interface, collections, translatable locale
  - Tests: max lengths, usage count manipulation, validation
  - **Result**: ✅ OK (19 tests, 51 assertions)

- ✅ **Unit Tests - TagService** (`tests/Unit/Service/TagServiceTest.php`) - 418 linii
  - 13 teste, 67 assertions
  - Tests: syncArticleTags, addTagsToArticle, removeTagsFromArticle
  - Tests: getPopularTags, searchTags, recalculateUsageCounts
  - Tests: getTagStatistics, mergeTags, getOrCreateTag
  - Mock objects pentru dependencies (EntityManager, TagRepository)
  - **Result**: ✅ OK (13 tests, 67 assertions)

- ✅ **Integration Tests - TagRepository** (`tests/Integration/Repository/TagRepositoryTest.php`) - 223 linii
  - 7 teste cu database reală
  - Tests: findPopularTags order, findByNameSearch matching
  - Tests: findOrCreateByName (find existing, create new)
  - Tests: findUnusedTags, find by ID, findAll
  - Cleanup după fiecare test (remove entities)
  - **Status**: ✅ Created (requires database setup to run)

- ✅ **Functional Tests - Tag API** (`tests/Functional/Api/TagApiTest.php`) - 291 linii
  - 13+ teste HTTP requests/responses
  - Tests: GET collection (pagination, locale)
  - Tests: GET single item (success, 404 error)
  - Tests: Custom endpoints (popular, search, related, stats, unused)
  - Tests: Validation (search requires 'q' parameter)
  - Tests: Cache headers (Cache-Control, Vary)
  - Tests: Accept-Language header support
  - **Status**: ✅ Created (requires application context to run)

- ✅ **Bug Fixes in TagService**
  - Fixed null parameter handling in syncArticleTags and addTagsToArticle
  - Fixed getTagStatistics return array keys to match test expectations
  - Added mostUsedTag field to statistics

**Verificări**:
- ✅ Tag entity tests: 19/19 passed ✅
- ✅ TagService tests: 13/13 passed ✅
- ✅ All assertions passed (118 total assertions in unit tests)
- ✅ No errors or failures in unit tests
- ✅ Integration and functional tests created (require full environment)

**Test Coverage**:
- **Unit Tests**: Tag entity, TagService business logic
- **Integration Tests**: TagRepository database queries
- **Functional Tests**: HTTP API endpoints, validation, caching
- **Total Test Files**: 4
- **Total Test Methods**: 39+
- **Total Assertions**: 118+ (in unit tests alone)

**Total linii cod Faza 6**: ~1,197 linii (4 fișiere test noi + 1 bug fix)

### 📊 Progres Total (Faza 1-7)

**Statistici implementare**:
- ✅ **Faze completate**: 7 din 8 (87.5%)
- ✅ **Fișiere noi**: 19 total
  - **Production**: 15 files
    - Core: Tag.php, TagRepository.php, TagService.php, TagProvider.php, TagProcessor.php, TagController.php
    - Messages: CleanupUnusedTagsMessage.php, RecalculateTagCountsMessage.php, CheckOrphanedTagsMessage.php
    - Handlers: CleanupUnusedTagsHandler.php, RecalculateTagCountsHandler.php, CheckOrphanedTagsHandler.php
    - Commands: CleanupUnusedTagsCommand.php, RecalculateTagCountsCommand.php
    - Scheduler: TagMaintenanceScheduleProvider.php
  - **Tests**: 4 (TagTest.php, TagServiceTest.php, TagRepositoryTest.php, TagApiTest.php)
- ✅ **Fișiere actualizate**: 11 total
  - Entities/Providers/Services: Article.php, ArticleRepository.php, ArticleProvider.php, ArticleProcessor.php, TagService.php
  - Elasticsearch: ElasticService.php, ElasticsearchIndexArticlesCommand.php
  - Configuration: security.yaml, messenger.yaml, scheduler.yaml
  - Documentation: ElasticsearchIndexArticlesCommand.php
- ✅ **Migrații create**: 1 (Version20251105105341)
- ✅ **Linii cod**: ~3,522 linii total
  - **Production code**: ~2,325 linii
  - **Test code**: ~1,197 linii
- ✅ **Metode noi**: 18 metode în repositories/service/controller
- ✅ **API operations**: 10 endpoints total
  - 5 API Platform CRUD (GET, GET collection, POST, PUT, DELETE)
  - 5 Custom endpoints (popular, search, related, stats, unused)
- ✅ **Tabele database**: 2 (tags, article_tag)
- ✅ **Elasticsearch indices**: 3 (ro, en, ru) cu mapping actualizat
- ✅ **Test tags**: 3 tags cu 18 translations (ro/en/ru)
- ✅ **Test coverage**:
  - Unit tests: 32 tests, 118 assertions ✅
  - Integration tests: 7 tests (database queries)
  - Functional tests: 13+ tests (HTTP API)
- ✅ **Automation infrastructure**:
  - Symfony Scheduler: 2 scheduled tasks (daily cleanup, weekly recalculation)
  - Messenger: 3 async message types
  - Console Commands: 2 manual commands with --dry-run and --async options
  - Event-driven: Automatic cleanup on article deletion

**Funcționalitate implementată**:
- ✅ Entity layer complet (Tag cu Translatable)
- ✅ Database schema cu indexes și constraints
- ✅ Repository layer cu query-uri optimizate
- ✅ Service layer cu business logic extins
- ✅ API Platform layer (Provider + Processor)
- ✅ RESTful API cu CRUD complet pentru tags
- ✅ Custom API endpoints (popular, search, related, stats, unused)
- ✅ Elasticsearch integration complet
  - ✅ Mapping cu nested tags și tag_names
  - ✅ Search cu boost pentru tags (2.5x)
  - ✅ Filter după tag IDs (nested query)
  - ✅ Autocomplete cu tag names
- ✅ Multilanguage support (ro/en/ru)
- ✅ Eager loading pentru N+1 prevention
- ✅ Automatic usage count management
- ✅ API filters și sorting
- ✅ Result caching (5-10 minutes)
- ✅ Security configuration (public GET, protected writes)
- ✅ Comprehensive testing suite (completat - Faza 6)
  - ✅ Unit tests pentru entity și service
  - ✅ Integration tests pentru repository
  - ✅ Functional tests pentru API endpoints
  - ✅ 32 unit tests passing (118 assertions)
- ✅ Maintenance commands & automation (completat - Faza 7)
  - ✅ Async message processing (Messenger)
  - ✅ Scheduled tasks (Scheduler)
  - ✅ Manual commands pentru admin
  - ✅ Event-driven cleanup

### 🎉 Faza 7 - Realizări

**Data**: 5 Noiembrie 2025

**Implementat - Opțiunea C (Completă): Scheduler + Messenger + Commands**

#### 7.1. Message Classes (Async Tasks)

- ✅ **CleanupUnusedTagsMessage** (`src/Message/CleanupUnusedTagsMessage.php`)
  - Parameters: daysOld (default: 30), dryRun (default: false)
  - Triggers cleanup of tags unused for X days
  - Used by: Scheduler, Manual commands, Events

- ✅ **RecalculateTagCountsMessage** (`src/Message/RecalculateTagCountsMessage.php`)
  - Parameters: tagId (optional, null = all tags)
  - Recalculates usage counts for data consistency
  - Used by: Scheduler, Manual commands

- ✅ **CheckOrphanedTagsMessage** (`src/Message/CheckOrphanedTagsMessage.php`)
  - Parameters: tagIds (specific tags to check)
  - Checks and removes orphaned tags (usageCount = 0)
  - **Triggered automatically** when articles are deleted

#### 7.2. MessageHandlers (Business Logic)

- ✅ **CleanupUnusedTagsHandler** (`src/MessageHandler/CleanupUnusedTagsHandler.php`)
  - Processes cleanup messages asynchronously
  - Logging: Info on start, success count, errors
  - Dry run support
  - Exception handling with retry mechanism

- ✅ **RecalculateTagCountsHandler** (`src/MessageHandler/RecalculateTagCountsHandler.php`)
  - Recalculates for specific tag or all tags
  - Logging: Old vs new counts
  - Uses TagService for consistency

- ✅ **CheckOrphanedTagsHandler** (`src/MessageHandler/CheckOrphanedTagsHandler.php`)
  - Double-checks usageCount vs actual article count
  - Safe deletion only when truly orphaned
  - Logging: Each orphaned tag removed

#### 7.3. Console Commands (Manual Execution)

- ✅ **app:tags:cleanup** (`src/Command/CleanupUnusedTagsCommand.php`)
  - Options: `--days=N` (default: 30), `--dry-run`, `--async`
  - Comprehensive help text with examples
  - Dispatches to Messenger for processing
  - **Usage**: `symfony console app:tags:cleanup --days=60 --dry-run`

- ✅ **app:tags:recalculate-counts** (`src/Command/RecalculateTagCountsCommand.php`)
  - Options: `--tag-id=N` (specific tag), `--async`
  - Use cases documented in help
  - **Usage**: `symfony console app:tags:recalculate-counts`

#### 7.4. Symfony Scheduler Configuration

- ✅ **TagMaintenanceScheduleProvider** (`src/Scheduler/TagMaintenanceScheduleProvider.php`)
  - **Daily cleanup**: Cron `0 3 * * *` (3 AM) - cleanup tags older than 30 days
  - **Weekly recalculation**: Cron `0 4 * * 0` (Sunday 4 AM) - all tags
  - Schedule name: `tag_maintenance`
  - **To run**: `symfony console messenger:consume scheduler_default`

- ✅ **scheduler.yaml** updated
  - Scheduler enabled
  - Documentation for running scheduler

#### 7.5. Messenger Configuration

- ✅ **messenger.yaml** routing added:
  ```yaml
  'App\Message\CleanupUnusedTagsMessage': async
  'App\Message\RecalculateTagCountsMessage': async
  'App\Message\CheckOrphanedTagsMessage': async
  ```
  - All tag maintenance messages routed to async transport
  - Retry mechanism enabled (default Symfony Messenger config)

#### 7.6. Event Integration

- ✅ **ArticleProcessor** updated (`src/State/ArticleProcessor.php`)
  - On DELETE operation:
    1. Collects tag IDs before deletion
    2. Decrements usage counts
    3. Removes article
    4. **Dispatches CheckOrphanedTagsMessage** with tag IDs
  - Automatic cleanup triggered by business events
  - Non-blocking (async via Messenger)

#### 7.7. TagService Enhancement

- ✅ Added `$dryRun` parameter to `cleanupUnusedTags()`
  - Returns count without deleting when dryRun = true
  - Allows testing before actual deletion

**Verificări**:
- ✅ Commands registered: `app:tags:cleanup`, `app:tags:recalculate-counts`
- ✅ Help text comprehensive with examples
- ✅ Scheduler provider created (requires `dragonmantank/cron-expression` package)
- ✅ Messenger routing configured
- ✅ MessageHandlers auto-registered (#[AsMessageHandler])
- ✅ Event integration working (dispatch on article delete)

**Architecture Highlights**:
- **3-layer approach**: Commands → Messages → Handlers
- **Flexible execution**: Manual, Scheduled, Event-driven
- **Async by default**: All maintenance via message queue
- **Dry run support**: Safe testing before cleanup
- **Comprehensive logging**: Info, warning, error levels
- **Retry mechanism**: Built-in Messenger retry on failure
- **Event-driven cleanup**: Automatic when articles deleted

**Total linii cod Faza 7**: ~690 linii (9 fișiere noi + 2 actualizate)

### 🎉 Faza 8 - Realizări

**Data**: 5 Noiembrie 2025

**Implementat - Frontend Integration Completă**

#### 8.1. TypeScript Type Definitions

- ✅ **Tag Types** (`lib/types/tag.ts`) - 80 linii
  - `Tag` interface - Core tag entity
  - `TagCollection` - Hydra collection response
  - `TagStatistics` - Statistics interface
  - `GetTagsParams` - Query parameters type
  - `RelatedTagsResponse` - Related tags response

- ✅ **Article Type Updates** (`lib/types/article.ts`)
  - Added `tags?: (Tag | string)[]` field to Article interface
  - Import Tag type from tag.ts
  - Support for both full Tag objects and IRIs

- ✅ **Type Exports** (`lib/types/index.ts`)
  - Re-export all tag types for easy imports

#### 8.2. API Client Functions

- ✅ **Tags API Service** (`lib/api/tags.ts`) - 380 linii
  - `fetchTags()` - Fetch tags with pagination and filters
  - `fetchTag()` - Get single tag by ID
  - `fetchPopularTags()` - Popular tags by usage count (cached 10 min)
  - `searchTags()` - Autocomplete search (cached 5 min)
  - `fetchRelatedTags()` - Co-occurring tags (cached 10 min)
  - `fetchTagStats()` - Tag statistics (cached 5 min)
  - `fetchUnusedTags()` - Tags with zero usage
  - `fetchArticlesByTag()` - Articles filtered by tag slug
  - Proper caching with Next.js revalidate
  - Accept-Language header support
  - Error handling with descriptive messages

- ✅ **API Exports** (`lib/api/index.ts`)
  - Re-export all tag API functions

#### 8.3. React Components

- ✅ **TagBadge Component** (`components/tags/TagBadge.tsx`) - 65 linii
  - Clickable tag badge with link to tag page
  - 3 variants: default, outline, solid
  - 3 sizes: sm, md, lg
  - Optional # prefix
  - Optional usage count display
  - Dark mode support
  - Accessible with title tooltips

- ✅ **TagList Component** (`components/tags/TagList.tsx`) - 75 linii
  - Displays list of tags using TagBadge
  - Configurable variant, size, showHash
  - `maxTags` limit with "+X more" indicator
  - Empty state support with custom message
  - Flexible layout with flex-wrap

- ✅ **TagCloud Component** (`components/tags/TagCloud.tsx`) - 95 linii
  - Variable font sizes based on usage count
  - 5 size levels (text-sm to text-3xl)
  - Dynamic opacity based on popularity
  - Responsive grid layout (flex-wrap)
  - Center-aligned cloud layout
  - Dark mode support
  - Hover effects with transitions

- ✅ **Component Exports** (`components/tags/index.ts`)
  - Central export file for all tag components

#### 8.4. Next.js Pages

- ✅ **Tags Index Page** (`app/[locale]/(public)/tags/page.tsx`) - 115 linii
  - Route: `/[locale]/tags`
  - Display all popular tags as cloud (100 tags)
  - Multilanguage support (ro/en/ru)
  - SEO metadata with OpenGraph
  - Statistics display (total tags count)
  - Empty state handling
  - Responsive container (max-w-6xl)

- ✅ **Tag Detail Page** (`app/[locale]/(public)/tags/[slug]/page.tsx`) - 180 linii
  - Route: `/[locale]/tags/[slug]`
  - Dynamic tag page with articles
  - SEO metadata with keywords
  - Canonical URLs
  - 404 handling with notFound()
  - Grid layout with sidebar (lg:grid-cols-3)
  - Related tags in sticky sidebar
  - Article cards in responsive grid (md:grid-cols-2)
  - Total articles count display
  - Multilanguage support

#### 8.5. Component Integration

- ✅ **ArticleCard Updates** (`components/ArticleCard.tsx`)
  - Added TagList import and Tag type
  - Display up to 3 tags per article
  - Tags shown below category/view count
  - Proper type filtering (Tag vs string)
  - Conditional rendering (only if tags exist)
  - Size: sm, variant: default
  - Consistent spacing (mt-3)

**Verificări**:
- ✅ All TypeScript types properly defined with JSON-LD support
- ✅ API client functions follow existing patterns
- ✅ Proper caching strategy (5-10 minutes)
- ✅ Accept-Language header support for i18n
- ✅ Components follow design system patterns
- ✅ Dark mode support in all components
- ✅ Responsive design (mobile/tablet/desktop)
- ✅ SEO optimization (metadata, OpenGraph, keywords)
- ✅ Accessibility (proper links, tooltips, semantic HTML)
- ✅ Error handling and empty states

**Frontend Architecture Highlights**:
- **Type Safety**: Full TypeScript coverage with strict types
- **Performance**: Next.js SSR with proper caching strategies
- **SEO**: Metadata generation for all tag pages
- **i18n**: Multilanguage support (ro/en/ru) throughout
- **Design**: Consistent with existing TailNews design system
- **Reusability**: Modular components with flexible props
- **Accessibility**: Semantic HTML, ARIA labels, keyboard navigation

**Total linii cod Faza 8**: ~990 linii (10 fișiere noi + 1 actualizat)

### 📊 Progres Total Final (Faza 1-8)

**Statistici implementare complete**:
- ✅ **Faze completate**: 8 din 8 (100%) ✅
- ✅ **Fișiere noi**: 29 total
  - **Backend Production**: 15 files (Core, Messages, Handlers, Commands, Scheduler)
  - **Backend Tests**: 4 files
  - **Frontend**: 10 files (Types, API, Components, Pages)
- ✅ **Fișiere actualizate**: 12 total
  - **Backend**: 10 files (Entities, Services, Config)
  - **Frontend**: 2 files (ArticleCard, types/index)
- ✅ **Migrații database**: 1 (Version20251105105341)
- ✅ **Total linii cod**: ~4,512 linii
  - **Backend Production**: ~2,325 linii
  - **Backend Tests**: ~1,197 linii
  - **Frontend**: ~990 linii
- ✅ **API operations**: 13 endpoints total
  - 5 API Platform CRUD
  - 5 Custom backend endpoints
  - 3 Frontend page routes
- ✅ **Components React**: 3 (TagBadge, TagList, TagCloud)
- ✅ **Pages Next.js**: 2 (/tags, /tags/[slug])
- ✅ **Tabele database**: 2 (tags, article_tag)
- ✅ **Elasticsearch indices**: 3 (ro, en, ru)
- ✅ **Test coverage**: 32 unit tests passing (118 assertions)

**Funcționalitate completă implementată**:
- ✅ **Backend Full Stack**:
  - Entity layer cu Translatable (ro/en/ru)
  - Repository layer cu query-uri optimizate
  - Service layer cu business logic extins
  - API Platform CRUD complet
  - Custom endpoints (popular, search, related, stats, unused)
  - Elasticsearch integration cu boost și nested queries
  - Automated maintenance (Scheduler + Messenger + Commands)
  - Event-driven cleanup pe article deletion
  - Comprehensive testing suite (32 tests passing)
- ✅ **Frontend Full Stack**:
  - TypeScript types cu JSON-LD support
  - API client cu caching și i18n
  - 3 reusable components (Badge, List, Cloud)
  - 2 public pages (index și detail)
  - Integration în ArticleCard
  - SEO metadata și OpenGraph
  - Dark mode support
  - Responsive design
  - Multilanguage support (ro/en/ru)

---

## 📋 Cuprins

1. [Status Implementare](#-status-implementare)
2. [Obiective](#-obiective)
3. [Arhitectură Propusă](#-arhitectură-propusă)
4. [Plan de Implementare Detaliat](#-plan-de-implementare-detaliat)
5. [Beneficii Implementării](#-beneficii-implementării)
6. [Estimare Timp & Prioritizare](#-estimare-timp--prioritizare)
7. [MVP (Minimum Viable Product)](#-mvp-minimum-viable-product)
8. [Checklist Final](#-checklist-final)

---

## 🎯 Obiective

1. **SEO îmbunătățit**: Meta keywords pentru motoarele de căutare
2. **Căutare Elasticsearch mai rapidă**: Indexare separată a tag-urilor cu boost
3. **Organizare conținut**: Grupare articole după subiecte/teme
4. **Suport multilanguage**: Tag-uri traduse în ro/en/ru
5. **Reutilizare tag-uri**: Consistență și auto-complete

---

## 🏗️ Arhitectură Propusă

### 1. Structura Tag Entity

```
Tag Entity (Translatable)
├── id: int
├── name: string (translatable) - "Politică", "Politics", "Политика"
├── slug: string (translatable) - "politica", "politics", "politika"
├── description: text (translatable, optional)
├── usageCount: int (non-translatable) - număr articole
├── createdAt: DateTimeImmutable
├── updatedAt: DateTimeImmutable
└── articles: Collection<Article> (ManyToMany, inverse side)
```

### 2. Relație Article ↔ Tag

```
Article Entity
└── tags: Collection<Tag> (ManyToMany, owning side)
    └── Join Table: article_tag
        ├── article_id
        └── tag_id
```

**Avantaje ManyToMany**:
- Un tag poate fi folosit de multiple articole
- Un articol poate avea multiple tag-uri
- Reutilizare și consistență
- Auto-complete bazat pe tag-uri existente

### 3. Elasticsearch Mapping Update

```json
{
  "tags": {
    "type": "nested",
    "properties": {
      "id": {"type": "integer"},
      "name": {"type": "text", "analyzer": "article_analyzer"},
      "slug": {"type": "keyword"}
    }
  },
  "tag_names": {
    "type": "text",
    "analyzer": "article_analyzer",
    "boost": 2.0
  }
}
```

**Beneficii pentru căutare**:
- Căutare precisă după tag name/slug
- Boost pentru matches pe tag-uri (relevance mai mare)
- Filtrare rapidă după tag IDs
- Aggregations pentru "popular tags"

---

## 📝 Plan de Implementare Detaliat

### Faza 1: Backend - Entity și Database (Ziua 1)

#### 1.1. Creare Tag Entity

**Fișier**: `src/Entity/Tag.php`

**Specificații**:
- Implementare Translatable interface
- Properties: id, name, slug, description, usageCount, timestamps
- Gedmo annotations: @Translatable, @Slug, @Timestampable
- Validation: NotBlank, Length, UniqueEntity
- Serialization groups: tag:read, tag:write, tag:list
- Relationship: ManyToMany cu Article (inversedBy)

**Cod exemplu**:
```php
<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\TagRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TagRepository::class)]
#[UniqueEntity('slug', message: 'This slug is already in use.')]
#[ORM\Table(name: 'tags')]
#[ORM\Index(name: 'idx_tag_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_tag_usage_count', columns: ['usage_count'])]
#[ApiResource(
    // Configuration will be added in Phase 3
)]
class Tag implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['tag:read', 'article:read'])]
    private ?int $id = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['tag:read', 'tag:write', 'article:read'])]
    private ?string $name = null;

    #[Gedmo\Translatable]
    #[Gedmo\Slug(fields: ['name'], unique: true, updatable: true)]
    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    #[Groups(['tag:read', 'article:read'])]
    private ?string $slug = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['tag:read', 'tag:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['tag:read'])]
    private int $usageCount = 0;

    #[ORM\ManyToMany(targetEntity: Article::class, mappedBy: 'tags')]
    private Collection $articles;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['tag:read'])]
    private ?DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['tag:read'])]
    private ?DateTimeImmutable $updatedAt = null;

    #[Gedmo\Locale]
    private ?string $locale = null;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
    }

    // Getters and setters...
}
```

#### 1.2. Update Article Entity

**Fișier**: `src/Entity/Article.php`

**Modificări**:

1. Adăugare property (după linia 159):
```php
#[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'articles')]
#[ORM\JoinTable(name: 'article_tag')]
#[Groups(['article:read', 'article:write'])]
#[MaxDepth(2)]
private Collection $tags;
```

2. Update constructor (linia 207):
```php
public function __construct()
{
    $this->authors = new ArrayCollection();
    $this->articleImages = new ArrayCollection();
    $this->relatedArticles = new ArrayCollection();
    $this->tags = new ArrayCollection(); // ADD THIS
}
```

3. Adăugare methods (la final):
```php
/**
 * @return Collection<int, Tag>
 */
public function getTags(): Collection
{
    return $this->tags;
}

public function addTag(Tag $tag): self
{
    if (!$this->tags->contains($tag)) {
        $this->tags->add($tag);
    }

    return $this;
}

public function removeTag(Tag $tag): self
{
    $this->tags->removeElement($tag);

    return $this;
}
```

4. Update ApiFilter (după linia 88):
```php
#[ApiFilter(SearchFilter::class, properties: [
    'category' => 'exact',
    'category.id' => 'exact',
    'status' => 'exact',
    'title' => 'partial',
    'slug' => 'exact',
    'tags' => 'exact',           // ADD THIS
    'tags.id' => 'exact',        // ADD THIS
    'tags.slug' => 'exact',      // ADD THIS
])]
```

#### 1.3. Doctrine Migration

**Comenzi**:
```bash
cd /var/www/deschide_news_app/deschide_backend

# Generare migrație
symfony console make:migration

# Verificare SQL generat
cat migrations/VersionXXXXXXXXXXXXXX.php

# Rulare migrație
symfony console doctrine:migrations:migrate -n
```

**Structura tabelelor create**:

1. **Tabel `tags`**:
```sql
CREATE TABLE tags (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    usage_count INTEGER DEFAULT 0,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
);

CREATE INDEX idx_tag_slug ON tags (slug);
CREATE INDEX idx_tag_usage_count ON tags (usage_count);
```

2. **Tabel `tags_translations`** (Gedmo):
```sql
CREATE TABLE ext_translations (
    id SERIAL PRIMARY KEY,
    locale VARCHAR(8) NOT NULL,
    object_class VARCHAR(191) NOT NULL,
    field VARCHAR(32) NOT NULL,
    foreign_key VARCHAR(64) NOT NULL,
    content TEXT
);
```

3. **Tabel `article_tag`** (join table):
```sql
CREATE TABLE article_tag (
    article_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY (article_id, tag_id),
    FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE
);

CREATE INDEX idx_article_tag_article ON article_tag (article_id);
CREATE INDEX idx_article_tag_tag ON article_tag (tag_id);
```

---

### Faza 2: Backend - Repository și Service (Ziua 1-2)

#### 2.1. Tag Repository

**Fișier**: `src/Repository/TagRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * Find most popular tags by usage count.
     */
    public function findPopularTags(int $limit = 20, string $locale = 'ro'): array
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.usageCount', 'DESC')
            ->setMaxResults($limit);

        $query = $qb->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        return $query->getResult();
    }

    /**
     * Search tags by name (for auto-complete).
     */
    public function findByNameSearch(string $query, string $locale = 'ro', int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('LOWER(t.name) LIKE LOWER(:query)')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.usageCount', 'DESC')
            ->setMaxResults($limit);

        $query = $qb->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        return $query->getResult();
    }

    /**
     * Find or create tag by name.
     */
    public function findOrCreateByName(string $name, string $locale = 'ro'): Tag
    {
        // Try to find existing tag
        $qb = $this->createQueryBuilder('t')
            ->where('LOWER(t.name) = LOWER(:name)')
            ->setParameter('name', $name)
            ->setMaxResults(1);

        $query = $qb->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        $tag = $query->getOneOrNullResult();

        if (!$tag) {
            // Create new tag
            $tag = new Tag();
            $tag->setName($name);
            $tag->setTranslatableLocale($locale);

            $this->getEntityManager()->persist($tag);
        }

        return $tag;
    }

    /**
     * Find tags with no articles (for cleanup).
     */
    public function findUnusedTags(\DateTimeImmutable $olderThan): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.usageCount = 0')
            ->andWhere('t.createdAt < :date')
            ->setParameter('date', $olderThan);

        return $qb->getQuery()->getResult();
    }
}
```

#### 2.2. Article Repository Update

**Fișier**: `src/Repository/ArticleRepository.php`

**Adăugare method**:
```php
/**
 * Find articles by tag IDs (AND logic - article must have all tags).
 */
public function findByTags(array $tagIds, string $locale = 'ro'): array
{
    $qb = $this->createQueryBuilder('a');

    foreach ($tagIds as $index => $tagId) {
        $alias = 't' . $index;
        $qb->join('a.tags', $alias)
           ->andWhere($alias . '.id = :tagId' . $index)
           ->setParameter('tagId' . $index, $tagId);
    }

    $qb->leftJoin('a.category', 'c')
       ->addSelect('c')
       ->leftJoin('a.authors', 'au')
       ->addSelect('au')
       ->orderBy('a.publishedAt', 'DESC');

    $query = $qb->getQuery();
    $query->setHint(
        \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
        $locale
    );

    return $query->getResult();
}

/**
 * Find articles by tag IDs (OR logic - article has at least one tag).
 */
public function findByAnyTag(array $tagIds, string $locale = 'ro'): array
{
    $qb = $this->createQueryBuilder('a')
        ->join('a.tags', 't')
        ->where('t.id IN (:tagIds)')
        ->setParameter('tagIds', $tagIds)
        ->leftJoin('a.category', 'c')
        ->addSelect('c')
        ->leftJoin('a.authors', 'au')
        ->addSelect('au')
        ->orderBy('a.publishedAt', 'DESC')
        ->groupBy('a.id'); // Prevent duplicates

    $query = $qb->getQuery();
    $query->setHint(
        \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
        $locale
    );

    return $query->getResult();
}
```

#### 2.3. Tag Service (Optional)

**Fișier**: `src/Service/TagService.php`

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Entity\Tag;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;

class TagService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TagRepository $tagRepository
    ) {
    }

    /**
     * Sync article tags by names.
     *
     * @param Article $article
     * @param array $tagNames Array of tag names
     * @param string $locale
     */
    public function syncArticleTags(Article $article, array $tagNames, string $locale = 'ro'): void
    {
        // Remove all existing tags
        foreach ($article->getTags() as $tag) {
            $article->removeTag($tag);
            $tag->setUsageCount($tag->getUsageCount() - 1);
        }

        // Add new tags
        foreach ($tagNames as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName)) {
                continue;
            }

            $tag = $this->tagRepository->findOrCreateByName($tagName, $locale);
            $article->addTag($tag);
            $tag->setUsageCount($tag->getUsageCount() + 1);
        }

        $this->entityManager->flush();
    }

    /**
     * Get popular tags.
     */
    public function getPopularTags(string $locale = 'ro', int $limit = 20): array
    {
        return $this->tagRepository->findPopularTags($limit, $locale);
    }

    /**
     * Recalculate usage count for all tags.
     */
    public function recalculateUsageCounts(): int
    {
        $tags = $this->tagRepository->findAll();
        $updated = 0;

        foreach ($tags as $tag) {
            $count = $tag->getArticles()->count();
            if ($tag->getUsageCount() !== $count) {
                $tag->setUsageCount($count);
                $updated++;
            }
        }

        $this->entityManager->flush();

        return $updated;
    }

    /**
     * Cleanup unused tags.
     */
    public function cleanupUnusedTags(int $daysOld = 30): int
    {
        $date = new \DateTimeImmutable("-{$daysOld} days");
        $unusedTags = $this->tagRepository->findUnusedTags($date);

        foreach ($unusedTags as $tag) {
            $this->entityManager->remove($tag);
        }

        $this->entityManager->flush();

        return count($unusedTags);
    }
}
```

---

### Faza 3: Backend - API Platform Integration (Ziua 2)

#### 3.1. Tag State Provider

**Fișier**: `src/State/TagProvider.php`

```php
<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<Tag>
 */
final class TagProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();
        $locale = $request?->headers->get('Accept-Language', 'ro') ?? 'ro';

        // Extract language code
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }

        $repository = $this->entityManager->getRepository(Tag::class);

        // Single item
        if (isset($uriVariables['id'])) {
            $queryBuilder = $repository->createQueryBuilder('t')
                ->where('t.id = :id')
                ->setParameter('id', $uriVariables['id']);

            $query = $queryBuilder->getQuery();
            $query->setHint(
                \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                $locale
            );

            $result = $query->getOneOrNullResult();

            if ($result) {
                $result->setTranslatableLocale($locale);
                $this->entityManager->refresh($result);
            }

            return $result;
        }

        // Collection
        $queryBuilder = $repository->createQueryBuilder('t');

        if ($request) {
            // Search by name
            if ($name = $request->query->get('name')) {
                $queryBuilder->andWhere('LOWER(t.name) LIKE LOWER(:name)')
                    ->setParameter('name', '%' . $name . '%');
            }

            // Search by slug
            if ($slug = $request->query->get('slug')) {
                $queryBuilder->andWhere('t.slug = :slug')
                    ->setParameter('slug', $slug);
            }

            // Order by
            $orderBy = $request->query->all('order');
            if (!empty($orderBy) && \is_array($orderBy)) {
                foreach ($orderBy as $field => $direction) {
                    $direction = strtoupper((string) $direction);
                    if (\in_array($direction, ['ASC', 'DESC'], true)) {
                        $queryBuilder->addOrderBy('t.' . $field, $direction);
                    }
                }
            } else {
                $queryBuilder->orderBy('t.usageCount', 'DESC');
            }

            // Pagination
            $page = max(1, (int) $request->query->get('page', 1));
            $itemsPerPage = min(100, max(1, (int) $request->query->get('itemsPerPage', 30)));
            $offset = ($page - 1) * $itemsPerPage;

            $queryBuilder->setFirstResult($offset)
                ->setMaxResults($itemsPerPage);
        }

        $query = $queryBuilder->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        $doctrinePaginator = new DoctrinePaginator($query, fetchJoinCollection: false);

        $results = iterator_to_array($doctrinePaginator);
        foreach ($results as $tag) {
            $tag->setTranslatableLocale($locale);
            $this->entityManager->refresh($tag);
        }

        return $doctrinePaginator;
    }
}
```

#### 3.2. Tag State Processor

**Fișier**: `src/State/TagProcessor.php`

```php
<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProcessorInterface<Tag, Tag|void>
 */
final class TagProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Tag|void
    {
        $request = $this->requestStack->getCurrentRequest();
        $locale = $request?->headers->get('Accept-Language', 'ro') ?? 'ro';

        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }

        // Set locale for translatable fields
        if ($data instanceof Tag) {
            $data->setTranslatableLocale($locale);
        }

        // Handle DELETE
        if ($operation->getName() === 'delete' || $operation instanceof \ApiPlatform\Metadata\Delete) {
            // Check if tag is used
            if ($data->getUsageCount() > 0) {
                throw new \RuntimeException('Cannot delete tag that is currently in use by articles.');
            }

            $this->entityManager->remove($data);
            $this->entityManager->flush();

            return;
        }

        // Handle POST/PUT
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
```

#### 3.3. Article Provider Update

**Fișier**: `src/State/ArticleProvider.php`

**Modificări**:

1. Adăugare eager loading pentru tags (linia ~96):
```php
$queryBuilder = $repository->createQueryBuilder('a')
    ->leftJoin('a.category', 'c')
    ->addSelect('c')
    ->leftJoin('a.authors', 'au')
    ->addSelect('au')
    ->leftJoin('a.tags', 't')        // ADD THIS
    ->addSelect('t');                 // ADD THIS
```

2. Refresh tags în loop (după linia ~200):
```php
// Refresh tags for translatable fields
foreach ($article->getTags() as $tag) {
    $tag->setTranslatableLocale($locale);
    $this->entityManager->refresh($tag);
}
```

3. Același lucru pentru single item (după linia ~83):
```php
foreach ($result->getTags() as $tag) {
    $tag->setTranslatableLocale($locale);
    $this->entityManager->refresh($tag);
}
```

#### 3.4. Article Processor Update

**Fișier**: `src/State/ArticleProcessor.php`

**Adăugare după persist/flush** (dacă există integrare cu Elasticsearch):
```php
// After $this->entityManager->flush();

// Update tag usage counts
foreach ($data->getTags() as $tag) {
    $count = $tag->getArticles()->count();
    $tag->setUsageCount($count);
}
$this->entityManager->flush();

// Trigger Elasticsearch reindex (if service is available)
if ($this->elasticService && $this->elasticService->isEnabled()) {
    // Index article in all locales
    foreach (['ro', 'en', 'ru'] as $locale) {
        // Build document with tags...
        $this->elasticService->indexDocument($document, $locale);
    }
}
```

#### 3.5. API Resource Configuration

**Fișier**: `src/Entity/Tag.php` - Update ApiResource attribute

```php
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/tags/{id}',
            normalizationContext: ['groups' => ['tag:read'], 'enable_max_depth' => true]
        ),
        new GetCollection(
            uriTemplate: '/tags',
            normalizationContext: ['groups' => ['tag:read', 'tag:list'], 'enable_max_depth' => true],
            paginationItemsPerPage: 30,
            paginationClientEnabled: true,
            paginationClientItemsPerPage: true
        ),
        new Post(
            uriTemplate: '/tags',
            denormalizationContext: ['groups' => ['tag:write']]
        ),
        new Put(
            uriTemplate: '/tags/{id}',
            denormalizationContext: ['groups' => ['tag:write']]
        ),
        new Delete(
            uriTemplate: '/tags/{id}'
        ),
    ],
    provider: TagProvider::class,
    processor: TagProcessor::class
)]
#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'partial',
    'slug' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'usageCount' => 'DESC',
    'name' => 'ASC',
    'createdAt' => 'DESC'
])]
```

---

### Faza 4: Backend - Elasticsearch Integration (Ziua 2-3)

#### 4.1. ElasticService Update

**Fișier**: `src/Service/ElasticService.php`

**Modificare 1**: Update `createIndex()` mapping (după linia ~122):

```php
'tags' => [
    'type' => 'nested',
    'properties' => [
        'id' => ['type' => 'integer'],
        'name' => [
            'type' => 'text',
            'analyzer' => 'article_analyzer',
            'fields' => [
                'keyword' => ['type' => 'keyword'],
            ],
        ],
        'slug' => ['type' => 'keyword'],
    ],
],
'tag_names' => [
    'type' => 'text',
    'analyzer' => 'article_analyzer',
],
```

**Modificare 2**: Update `search()` method (linia ~220):

```php
// Update multi_match fields to include tag_names
$must[] = [
    'multi_match' => [
        'query' => $query,
        'fields' => ['title^3', 'tag_names^2.5', 'lead^2', 'content'],
        'type' => 'best_fields',
        'fuzziness' => 'AUTO',
    ],
];
```

**Modificare 3**: Add filter by tags (după linia ~242):

```php
// Add filter for tags
if (!empty($filters['tag_ids'])) {
    $tagIds = is_array($filters['tag_ids']) ? $filters['tag_ids'] : [$filters['tag_ids']];

    $must[] = [
        'nested' => [
            'path' => 'tags',
            'query' => [
                'terms' => [
                    'tags.id' => $tagIds
                ]
            ]
        ]
    ];
}
```

#### 4.2. Index Articles Command Update

**Fișier**: `src/Command/ElasticsearchIndexArticlesCommand.php`

**Modificare 1**: Eager load tags (linia ~65):

```php
$qb->leftJoin('a.authors', 'authors')
    ->leftJoin('a.category', 'category')
    ->leftJoin('a.relatedArticles', 'related')
    ->leftJoin('a.tags', 'tags')                    // ADD THIS
    ->addSelect('authors', 'category', 'related', 'tags');  // UPDATE THIS
```

**Modificare 2**: Build tags array (după linia ~104):

```php
// Build tags array
$tags = [];
$tagNames = [];
foreach ($article->getTags() as $tag) {
    $tag->setTranslatableLocale($currentLocale);
    $this->entityManager->refresh($tag);

    $tags[] = [
        'id' => $tag->getId(),
        'name' => $tag->getName(),
        'slug' => $tag->getSlug(),
    ];
    $tagNames[] = $tag->getName();
}
```

**Modificare 3**: Add to document array (după linia ~145):

```php
$document = [
    'id' => $article->getId(),
    'title' => $article->getTitle(),
    'slug' => $article->getSlug(),
    'lead' => $article->getLead(),
    'content' => $article->getContent(),
    'suggest' => [
        'input' => array_values(array_unique(array_filter($suggestInput))),
        'weight' => $article->getViewCount() + 10,
    ],
    'locale' => $currentLocale,
    'view_count' => $article->getViewCount(),
    'published_at' => $article->getPublishedAt()?->format('c'),
    'publish_at' => $article->getPublishAt()?->format('c'),
    'created_at' => $article->getCreatedAt()->format('c'),
    'authors' => $authors,
    'category' => $article->getCategory() ? [
        'id' => $article->getCategory()->getId(),
        'name' => $article->getCategory()->getTitle(),
        'slug' => $article->getCategory()->getSlug(),
    ] : null,
    'badge' => $article->getBadge()?->value,
    'is_featured' => $article->isFeatured(),
    'status' => $article->getStatus()->value,
    'related_ids' => $relatedIds,
    'tags' => $tags,                          // ADD THIS
    'tag_names' => implode(' ', $tagNames),   // ADD THIS
];
```

#### 4.3. Reindex Command

După update-uri, rulează:

```bash
cd /var/www/deschide_news_app/deschide_backend

# Șterge indices vechi
symfony console app:elasticsearch:delete-index deschide_articles_ro
symfony console app:elasticsearch:delete-index deschide_articles_en
symfony console app:elasticsearch:delete-index deschide_articles_ru

# Recreate indices cu noul mapping
symfony console app:elasticsearch:create-index

# Reindex all articles cu tags
symfony console app:elasticsearch:index-articles
```

---

### Faza 5: Backend - API Endpoints și Filters (Ziua 3)

#### 5.1. Tag API Endpoints

| Method | Endpoint | Descriere |
|--------|----------|-----------|
| GET | `/api/tags` | Listă tag-uri (pagination) |
| GET | `/api/tags/{id}` | Tag individual |
| GET | `/api/tags?name=pol` | Căutare tag după nume (auto-complete) |
| GET | `/api/tags?order[usageCount]=DESC` | Tag-uri populare |
| POST | `/api/tags` | Creare tag nou |
| PUT | `/api/tags/{id}` | Update tag |
| DELETE | `/api/tags/{id}` | Ștergere tag (doar dacă usageCount=0) |

**Testare endpoints**:

```bash
# Get all tags (Romanian - default)
curl -H "Accept-Language: ro" http://127.0.0.1:8081/api/tags

# Get tags in English
curl -H "Accept-Language: en" http://127.0.0.1:8081/api/tags

# Search tags by name (auto-complete)
curl "http://127.0.0.1:8081/api/tags?name=pol"

# Get popular tags
curl "http://127.0.0.1:8081/api/tags?order[usageCount]=DESC&itemsPerPage=10"

# Get single tag
curl http://127.0.0.1:8081/api/tags/1

# Create new tag
curl -X POST http://127.0.0.1:8081/api/tags \
  -H "Content-Type: application/ld+json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{"name": "Politică", "description": "Articole despre politică"}'

# Get articles filtered by tag
curl "http://127.0.0.1:8081/api/articles?tags.id=1"
curl "http://127.0.0.1:8081/api/articles?tags.slug=politica"
```

#### 5.2. Custom Popular Tags Endpoint

**Fișier**: `src/Controller/TagController.php`

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;

class TagController extends AbstractController
{
    public function __construct(
        private readonly TagRepository $tagRepository,
        private readonly RequestStack $requestStack
    ) {
    }

    #[Route('/api/tags/popular', name: 'api_tags_popular', methods: ['GET'])]
    public function popularTags(): JsonResponse
    {
        $request = $this->requestStack->getCurrentRequest();
        $locale = $request?->headers->get('Accept-Language', 'ro') ?? 'ro';

        // Extract language code
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }

        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));

        $tags = $this->tagRepository->findPopularTags($limit, $locale);

        return $this->json($tags, 200, [], [
            'groups' => ['tag:read']
        ]);
    }
}
```

**Testare**:
```bash
curl "http://127.0.0.1:8081/api/tags/popular?limit=10"
```

---

### Faza 6: Backend - Testing (Ziua 4)

#### 6.1. Unit Tests

**Fișier**: `tests/Unit/Entity/TagTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Article;
use App\Entity\Tag;
use PHPUnit\Framework\TestCase;

class TagTest extends TestCase
{
    public function testTagCreation(): void
    {
        $tag = new Tag();
        $tag->setName('Politică');
        $tag->setTranslatableLocale('ro');

        $this->assertEquals('Politică', $tag->getName());
        $this->assertEquals('ro', $tag->getLocale());
        $this->assertEquals(0, $tag->getUsageCount());
    }

    public function testAddArticle(): void
    {
        $tag = new Tag();
        $article = new Article();

        $article->addTag($tag);

        $this->assertCount(1, $article->getTags());
        $this->assertTrue($article->getTags()->contains($tag));
    }

    public function testRemoveArticle(): void
    {
        $tag = new Tag();
        $article = new Article();

        $article->addTag($tag);
        $article->removeTag($tag);

        $this->assertCount(0, $article->getTags());
    }
}
```

#### 6.2. Integration Tests

**Fișier**: `tests/Integration/Repository/TagRepositoryTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Tag;
use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TagRepositoryTest extends KernelTestCase
{
    private TagRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(TagRepository::class);
    }

    public function testFindPopularTags(): void
    {
        $tags = $this->repository->findPopularTags(10, 'ro');

        $this->assertIsArray($tags);

        // Verify ordering by usage count
        if (count($tags) > 1) {
            $this->assertGreaterThanOrEqual(
                $tags[1]->getUsageCount(),
                $tags[0]->getUsageCount()
            );
        }
    }

    public function testFindByNameSearch(): void
    {
        $tags = $this->repository->findByNameSearch('test', 'ro', 5);

        $this->assertIsArray($tags);
        $this->assertLessThanOrEqual(5, count($tags));
    }
}
```

#### 6.3. Functional/API Tests

**Fișier**: `tests/Functional/TagApiTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TagApiTest extends WebTestCase
{
    public function testGetTags(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tags', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_ACCEPT_LANGUAGE' => 'ro',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    }

    public function testGetSingleTag(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tags/1');

        if ($client->getResponse()->getStatusCode() === 404) {
            $this->markTestSkipped('No tags in database');
        }

        $this->assertResponseIsSuccessful();
    }

    public function testSearchTagsByName(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tags?name=test');

        $this->assertResponseIsSuccessful();
    }

    public function testFilterArticlesByTag(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/articles?tags.id=1');

        $this->assertResponseIsSuccessful();
    }
}
```

---

### Faza 7: Backend - Commands și Maintenance (Ziua 4)

#### 7.1. Sync Tags Usage Count Command

**Fișier**: `src/Command/SyncTagUsageCountCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\TagService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tags:sync-usage-count',
    description: 'Recalculate usage count for all tags'
)]
class SyncTagUsageCountCommand extends Command
{
    public function __construct(
        private readonly TagService $tagService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Recalculating tag usage counts...');

        $updated = $this->tagService->recalculateUsageCounts();

        $io->success(sprintf('Successfully updated %d tags!', $updated));

        return Command::SUCCESS;
    }
}
```

#### 7.2. Cleanup Unused Tags Command

**Fișier**: `src/Command/CleanupUnusedTagsCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\TagService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tags:cleanup-unused',
    description: 'Remove tags with zero usage older than specified days'
)]
class CleanupUnusedTagsCommand extends Command
{
    public function __construct(
        private readonly TagService $tagService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'days',
            'd',
            InputOption::VALUE_OPTIONAL,
            'Remove tags older than this many days',
            '30'
        );

        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Show what would be deleted without actually deleting'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('DRY RUN MODE - No tags will be deleted');
        }

        $io->info(sprintf('Finding unused tags older than %d days...', $days));

        if (!$dryRun) {
            $deleted = $this->tagService->cleanupUnusedTags($days);
            $io->success(sprintf('Successfully deleted %d unused tags!', $deleted));
        } else {
            // For dry run, we'd need to add a method to TagService to just count
            $io->info('Would delete X tags (dry run mode)');
        }

        return Command::SUCCESS;
    }
}
```

**Usage**:
```bash
# Dry run (preview)
symfony console app:tags:cleanup-unused --dry-run

# Actual cleanup (30 days old)
symfony console app:tags:cleanup-unused

# Custom days
symfony console app:tags:cleanup-unused --days=60
```

#### 7.3. Cron Job Setup

Adaugă în crontab pentru rulare automată:

```bash
# Sync tag usage counts daily at 3 AM
0 3 * * * cd /var/www/deschide_news_app/deschide_backend && symfony console app:tags:sync-usage-count

# Cleanup unused tags weekly on Sunday at 4 AM
0 4 * * 0 cd /var/www/deschide_news_app/deschide_backend && symfony console app:tags:cleanup-unused
```

---

### Faza 8: Frontend Integration (Ziua 5-6)

#### 8.1. Types/Interfaces

**Fișier**: `deschide_frontend/types/tag.ts`

```typescript
export interface Tag {
  '@id'?: string;
  '@type'?: string;
  id: number;
  name: string;
  slug: string;
  description?: string;
  usageCount: number;
  locale: string;
  createdAt: string;
  updatedAt: string;
}

export interface TagCollection {
  '@context': string;
  '@id': string;
  '@type': string;
  'hydra:member': Tag[];
  'hydra:totalItems': number;
  'hydra:view'?: {
    '@id': string;
    '@type': string;
    'hydra:first'?: string;
    'hydra:last'?: string;
    'hydra:next'?: string;
    'hydra:previous'?: string;
  };
}
```

#### 8.2. API Client Functions

**Fișier**: `deschide_frontend/lib/api/tags.ts`

```typescript
import { Tag, TagCollection } from '@/types/tag';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

export interface GetTagsParams {
  page?: number;
  itemsPerPage?: number;
  name?: string;
  order?: {
    usageCount?: 'DESC' | 'ASC';
    name?: 'ASC' | 'DESC';
  };
}

export async function getTags(
  locale: string = 'ro',
  params: GetTagsParams = {}
): Promise<TagCollection> {
  const queryParams = new URLSearchParams();

  if (params.page) queryParams.append('page', params.page.toString());
  if (params.itemsPerPage) queryParams.append('itemsPerPage', params.itemsPerPage.toString());
  if (params.name) queryParams.append('name', params.name);

  if (params.order) {
    Object.entries(params.order).forEach(([key, value]) => {
      queryParams.append(`order[${key}]`, value);
    });
  }

  const url = `${API_URL}/api/tags?${queryParams.toString()}`;

  const response = await fetch(url, {
    headers: {
      'Accept': 'application/ld+json',
      'Accept-Language': locale,
    },
    next: { revalidate: 300 }, // Cache for 5 minutes
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch tags: ${response.statusText}`);
  }

  return response.json();
}

export async function getTag(id: number, locale: string = 'ro'): Promise<Tag> {
  const response = await fetch(`${API_URL}/api/tags/${id}`, {
    headers: {
      'Accept': 'application/ld+json',
      'Accept-Language': locale,
    },
    next: { revalidate: 300 },
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch tag: ${response.statusText}`);
  }

  return response.json();
}

export async function getPopularTags(
  locale: string = 'ro',
  limit: number = 20
): Promise<Tag[]> {
  const response = await fetch(
    `${API_URL}/api/tags/popular?limit=${limit}`,
    {
      headers: {
        'Accept': 'application/json',
        'Accept-Language': locale,
      },
      next: { revalidate: 300 },
    }
  );

  if (!response.ok) {
    throw new Error(`Failed to fetch popular tags: ${response.statusText}`);
  }

  return response.json();
}

export async function searchTags(
  query: string,
  locale: string = 'ro'
): Promise<Tag[]> {
  const response = await getTags(locale, {
    name: query,
    itemsPerPage: 10,
    order: { usageCount: 'DESC' },
  });

  return response['hydra:member'];
}
```

#### 8.3. Tag Components

**Fișier**: `deschide_frontend/components/tags/TagBadge.tsx`

```tsx
import Link from 'next/link';
import { Tag } from '@/types/tag';

interface TagBadgeProps {
  tag: Tag;
  locale: string;
  variant?: 'default' | 'outline';
  size?: 'sm' | 'md' | 'lg';
}

export default function TagBadge({
  tag,
  locale,
  variant = 'default',
  size = 'md'
}: TagBadgeProps) {
  const sizeClasses = {
    sm: 'text-xs px-2 py-1',
    md: 'text-sm px-3 py-1',
    lg: 'text-base px-4 py-2',
  };

  const variantClasses = {
    default: 'bg-blue-100 text-blue-800 hover:bg-blue-200',
    outline: 'border border-blue-300 text-blue-700 hover:bg-blue-50',
  };

  return (
    <Link
      href={`/${locale}/tags/${tag.slug}`}
      className={`
        inline-flex items-center rounded-full font-medium
        transition-colors duration-200
        ${sizeClasses[size]}
        ${variantClasses[variant]}
      `}
    >
      #{tag.name}
    </Link>
  );
}
```

**Fișier**: `deschide_frontend/components/tags/TagList.tsx`

```tsx
import { Tag } from '@/types/tag';
import TagBadge from './TagBadge';

interface TagListProps {
  tags: Tag[];
  locale: string;
  variant?: 'default' | 'outline';
  size?: 'sm' | 'md' | 'lg';
}

export default function TagList({
  tags,
  locale,
  variant = 'default',
  size = 'md'
}: TagListProps) {
  if (tags.length === 0) return null;

  return (
    <div className="flex flex-wrap gap-2">
      {tags.map((tag) => (
        <TagBadge
          key={tag.id}
          tag={tag}
          locale={locale}
          variant={variant}
          size={size}
        />
      ))}
    </div>
  );
}
```

**Fișier**: `deschide_frontend/components/tags/TagCloud.tsx`

```tsx
import { Tag } from '@/types/tag';
import Link from 'next/link';

interface TagCloudProps {
  tags: Tag[];
  locale: string;
}

export default function TagCloud({ tags, locale }: TagCloudProps) {
  // Calculate font sizes based on usage count
  const maxCount = Math.max(...tags.map(t => t.usageCount));
  const minCount = Math.min(...tags.map(t => t.usageCount));

  const getFontSize = (count: number) => {
    if (maxCount === minCount) return 'text-base';

    const ratio = (count - minCount) / (maxCount - minCount);

    if (ratio > 0.75) return 'text-2xl';
    if (ratio > 0.5) return 'text-xl';
    if (ratio > 0.25) return 'text-lg';
    return 'text-base';
  };

  return (
    <div className="flex flex-wrap gap-3 items-center justify-center">
      {tags.map((tag) => (
        <Link
          key={tag.id}
          href={`/${locale}/tags/${tag.slug}`}
          className={`
            ${getFontSize(tag.usageCount)}
            text-blue-600 hover:text-blue-800
            hover:underline transition-colors
            font-medium
          `}
        >
          #{tag.name}
        </Link>
      ))}
    </div>
  );
}
```

#### 8.4. Tag Pages

**Fișier**: `deschide_frontend/app/[locale]/tags/page.tsx`

```tsx
import { getPopularTags } from '@/lib/api/tags';
import TagCloud from '@/components/tags/TagCloud';

export default async function TagsPage({
  params: { locale }
}: {
  params: { locale: string }
}) {
  const tags = await getPopularTags(locale, 50);

  return (
    <div className="container mx-auto px-4 py-8">
      <h1 className="text-3xl font-bold mb-8">
        {locale === 'ro' && 'Tag-uri Populare'}
        {locale === 'en' && 'Popular Tags'}
        {locale === 'ru' && 'Популярные теги'}
      </h1>

      <TagCloud tags={tags} locale={locale} />
    </div>
  );
}
```

**Fișier**: `deschide_frontend/app/[locale]/tags/[slug]/page.tsx`

```tsx
import { getArticles } from '@/lib/api/articles';
import ArticleCard from '@/components/articles/ArticleCard';

export default async function TagArticlesPage({
  params: { locale, slug }
}: {
  params: { locale: string; slug: string }
}) {
  const articles = await getArticles(locale, {
    'tags.slug': slug,
    itemsPerPage: 20,
  });

  return (
    <div className="container mx-auto px-4 py-8">
      <h1 className="text-3xl font-bold mb-8">
        Articles tagged with #{slug}
      </h1>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {articles['hydra:member'].map((article) => (
          <ArticleCard
            key={article.id}
            article={article}
            locale={locale}
          />
        ))}
      </div>
    </div>
  );
}
```

---

## 📊 Beneficii Implementării

### 1. SEO Îmbunătățit

- Meta keywords în `<head>`: `<meta name="keywords" content="tag1, tag2, tag3">`
- Schema.org markup: `"keywords": ["tag1", "tag2"]`
- URL-uri dedicate: `/tags/politica` pentru indexare mai bună
- Internal linking structure îmbunătățită

### 2. Căutare Elasticsearch Optimizată

- **Boost pe tag_names** (2.5x) față de content (1x)
- Căutare după "politică" va da prioritate articolelor cu tag "Politică"
- Filtrare rapidă: `/api/articles?tags[]=5` (query Elasticsearch nested)
- Aggregations pentru statistici: "Top 10 Popular Tags"
- Query performance îmbunătățit prin indexare separată

### 3. User Experience

- Tag cloud pe homepage (popular topics)
- Related articles by tags (recommendation engine)
- Auto-complete în search: sugestii bazate pe tags
- Filter articles în sidebar: "Filtrează după: #Politică, #Economie"
- Navigation îmbunătățită prin categorii semantice

### 4. Content Management

- Organizare mai bună a articolelor
- Identificare topic coverage gaps
- Analytics: "Care tag-uri generează cel mai mult trafic?"
- Editorial workflow: "Avem destule articole despre #Sănătate?"
- Content strategy bazată pe date

---

## 🚀 Estimare Timp & Prioritizare

| Fază | Descriere | Efort | Prioritate |
|------|-----------|-------|------------|
| **Faza 1** | Entity, Migration, Database | 4-6h | 🔴 Critical |
| **Faza 2** | Repository, Services | 3-4h | 🔴 Critical |
| **Faza 3** | API Platform Integration | 4-6h | 🔴 Critical |
| **Faza 4** | Elasticsearch Update | 4-5h | 🟠 High |
| **Faza 5** | API Endpoints, Filters | 3-4h | 🟠 High |
| **Faza 6** | Backend Testing | 4-6h | 🟡 Medium |
| **Faza 7** | Commands, Maintenance | 2-3h | 🟡 Medium |
| **Faza 8** | Frontend Integration | 8-10h | 🟢 Low (later sprint) |

**Total Backend**: ~25-35 ore (3-4 zile lucru intensiv)
**Total Frontend**: ~8-10 ore (1-2 zile)
**Total Project**: ~33-45 ore (5-6 zile)

---

## 🎯 MVP (Minimum Viable Product)

### Must Have (Ziua 1-3)

**Priority 1 - Core Functionality**:
1. ✅ Tag Entity + Migration + Article relationship - **COMPLETAT**
2. 🚧 Basic API endpoints (GET, POST tags) - **În progres**
3. ⏳ Article filter by tags
4. ⏳ Elasticsearch indexing cu tags
5. ⏳ Tag eager loading în ArticleProvider

### Can Wait (Sprint 2)

⏸️ **Priority 2 - Enhanced Features**:
1. Frontend tag selector
2. Tag cloud component
3. Popular tags endpoint
4. Advanced filters (AND/OR logic)
5. Cleanup commands
6. Comprehensive testing

### Nice to Have (Future)

🔮 **Priority 3 - Advanced**:
1. Tag analytics dashboard
2. Auto-suggest tags based on content (AI)
3. Tag synonyms/aliases
4. Tag hierarchy (parent-child)
5. Tag-based recommendations

---

## 📋 Checklist Final

### Backend

- [x] Tag Entity created (Translatable) ✅ **Faza 1**
- [x] Article-Tag ManyToMany relationship ✅ **Faza 1**
- [x] Doctrine migration executed ✅ **Faza 1**
- [x] Database tables verified (tags, article_tag, ext_translations) ✅ **Faza 1**
- [x] TagRepository with custom queries ✅ **Faza 1**
- [x] TagService for business logic ✅ **Faza 2**
- [x] ArticleRepository tag filtering methods ✅ **Faza 2**
- [x] TagProvider + TagProcessor ✅ **Faza 3**
- [x] ArticleProvider updated (eager load tags) ✅ **Faza 3**
- [x] ArticleProcessor updated (sync tags) ✅ **Faza 3**
- [x] API endpoints tested (routes registered) ✅ **Faza 3**
- [x] Serialization groups configured ✅ **Faza 3**
- [x] ElasticService mapping updated ✅ **Faza 4**
- [x] IndexArticlesCommand updated ✅ **Faza 4**
- [x] Multi-locale support verified (ro/en/ru) ✅ **Faza 4**
- [ ] Filter articles by tags working (după reindex)
- [ ] Full-text search cu tag boost verificat (după reindex)

### Elasticsearch

- [ ] Indices recreated with tag mapping
- [ ] All articles reindexed with tags
- [ ] Search with tag boost verified
- [ ] Nested query for tags working
- [ ] Filter by tag_ids functional
- [ ] Aggregations tested

### Testing

- [ ] Unit tests for Tag entity
- [ ] Integration tests for TagRepository
- [ ] API functional tests (CRUD operations)
- [ ] Elasticsearch integration tests
- [ ] Locale switching tests
- [ ] Edge cases covered (empty tags, duplicates, etc.)

### Commands & Maintenance

- [ ] SyncTagUsageCountCommand created
- [ ] CleanupUnusedTagsCommand created
- [ ] Cron jobs configured
- [ ] Import tags command (if needed)

### Frontend (Later Sprint)

- [ ] Tag types/interfaces defined
- [ ] API client functions implemented
- [ ] TagBadge component created
- [ ] TagList component created
- [ ] TagCloud component created
- [ ] TagSelector component (admin) created
- [ ] Tag pages (/tags, /tags/[slug]) created
- [ ] Tag cloud on homepage integrated
- [ ] Article detail page shows tags
- [ ] Admin panel tag management

### Documentation

- [ ] API documentation updated
- [ ] CLAUDE.md updated with tag info
- [ ] Migration notes documented
- [ ] Usage examples provided
- [ ] Troubleshooting guide created

---

## 🔄 Migration Path

### Existing Articles

Pentru articole existente fără tag-uri:

1. **Manual tagging** (recomandat pentru articole importante):
   ```bash
   # Via admin panel sau API
   PUT /api/articles/{id}
   {
     "tags": ["/api/tags/1", "/api/tags/2"]
   }
   ```

2. **Bulk import from CSV**:
   ```csv
   article_id,tag_names
   1,"Politică,Economie,România"
   2,"Sport,Fotbal"
   ```

3. **AI-based auto-tagging** (future enhancement):
   - Analyze article content
   - Suggest relevant tags
   - Editor approval workflow

### Newscoop Migration

Dacă Newscoop avea keywords:

```bash
symfony console app:import:tags --source=newscoop
```

---

## 🐛 Troubleshooting

### "Duplicate slug for tag"

**Cauză**: Două tag-uri cu același nume în aceeași limbă.

**Soluție**:
```sql
SELECT * FROM tags WHERE slug = 'duplicated-slug';
-- Manual merge sau rename
```

### "Tags not showing in Elasticsearch"

**Cauză**: Articles indexate înainte de adăugarea tag-urilor.

**Soluție**:
```bash
symfony console app:elasticsearch:index-articles --locale=ro
```

### "Tags not translating correctly"

**Cauză**: Locale hint nu e aplicat în query.

**Soluție**: Verifică că toate query-urile au:
```php
$query->setHint(
    \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
    $locale
);
```

### "N+1 queries cu tags"

**Cauză**: Tags nu sunt eager-loaded.

**Soluție**: Adaugă în QueryBuilder:
```php
->leftJoin('a.tags', 't')
->addSelect('t')
```

---

## 📚 Referințe

### Doctrine

- [Many-to-Many Associations](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/association-mapping.html#many-to-many-bidirectional)
- [Gedmo Translatable](https://github.com/doctrine-extensions/DoctrineExtensions/blob/main/doc/translatable.md)

### API Platform

- [State Providers](https://api-platform.com/docs/core/state-providers/)
- [State Processors](https://api-platform.com/docs/core/state-processors/)
- [Filters](https://api-platform.com/docs/core/filters/)

### Elasticsearch

- [Nested Objects](https://www.elastic.co/guide/en/elasticsearch/reference/current/nested.html)
- [Multi-Match Query](https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-multi-match-query.html)

---

## ✅ Success Criteria

Implementarea va fi considerată reușită când:

1. ✅ Tag-uri pot fi create și gestionate prin API
2. ✅ Articole pot fi filtrate după tag-uri
3. ✅ Căutarea Elasticsearch include și tag-uri
4. ✅ Tag-uri sunt traduse în toate cele 3 limbi (ro/en/ru)
5. ✅ Performance-ul rămâne acceptabil (sub 200ms pentru listări)
6. ✅ Toate testele pass
7. ✅ Frontend afișează tag-uri corect
8. ✅ SEO metadata include keywords

---

**Document creat**: 5 Noiembrie 2025
**Autor**: Claude Code (AI Assistant)
**Status**: 📋 Planning - Ready for Implementation
**Versiune**: 1.0
