# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

---

# ⛔ HARD RULES — NEVER VIOLATE

These rules apply to ALL agents, ALL tasks, ALL sprints. Violations waste time and break workflows.

---

## Rule 1: CLI Entry Point

**ALWAYS** use `symfony console` — **NEVER** use `php bin/console`.

```bash
# ✅ CORRECT
symfony console app:translate:articles 123 --force
symfony console doctrine:schema:validate
symfony console cache:clear --env=test
symfony console messenger:consume ai_async --limit=10

# ❌ WRONG — will be rejected
php bin/console app:translate:articles 123 --force
php bin/console cache:clear
```

The Symfony CLI binary manages environment variables, PHP version, and worker lifecycle.
There are ZERO exceptions to this rule.

---

## Rule 2: Admin Credentials — Do Not Query the Database

Development admin credentials are defined in environment config. **Do not** run SQL queries, create fixture users, or invent credentials.

```bash
# Read credentials from environment
symfony console debug:dotenv | grep -E 'ADMIN_EMAIL|ADMIN_PASSWORD'

# Authenticate via API (the field is "username", NOT "email")
curl -s -X POST http://127.0.0.1:8081/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"password"}' | jq .token

# Use the token for subsequent requests
curl -s http://127.0.0.1:8081/api/articles \
  -H "Authorization: Bearer {token}"
```

> **Authentication note:** the JWT login endpoint expects `username`, NOT `email`. The User entity exposes both fields, but `getUserIdentifier()` returns `username` (Symfony default). The canonical dev seed user is `admin`. Confirmed 2026-04-26 against `/api/login_check`.

If credentials are not in `.env.local`, check `.env.test` or ask Radu. Do NOT:
- Query `app_users` table directly for passwords
- Create new admin users via Doctrine fixtures
- Hardcode credentials in test files or prompts

---

## Rule 3: Testing Strategy — Targeted Tests, Not Full Suite

### During feature development (individual tasks):
Run ONLY the tests relevant to your changes:

```bash
# Find tests related to the class you modified
grep -rn "YourModifiedClass" tests/ --include="*.php" -l

# Run only those tests
./vendor/bin/phpunit tests/Unit/Service/YourServiceTest.php --testdox
./vendor/bin/phpunit tests/Functional/Api/YourControllerTest.php --testdox

# If you modified an entity, also run its repository test
./vendor/bin/phpunit --filter="YourEntity" --testdox
```

For frontend:
```bash
cd apps/frontend
npx jest --testPathPattern="YourComponent" --verbose
```

### During sprint finalization ONLY (QA task / merge prep):
Run the FULL test suite to check for regressions:

```bash
# Backend — full suite
cd apps/backend
./vendor/bin/phpunit --testdox

# Frontend — full suite
cd apps/frontend
npm test -- --watchAll=false
```

**Why:** The full backend suite (~4,300+ tests) takes significant time. Running it after every small change wastes cycles. Targeted tests catch issues immediately; full suite catches regressions at sprint boundary.

---

## Rule 4: Code Quality — Mandatory Checks Before Every Commit

Every commit MUST pass these checks. Run them BEFORE `git commit`:

### Backend (PHP):
```bash
cd apps/backend

# 1. Container lint — catches DI wiring issues
symfony console lint:container

# 2. PHPStan — static analysis at current level
vendor/bin/phpstan analyse src/ --no-progress --memory-limit=1G

# 3. Schema validation — catches entity/DB drift
symfony console doctrine:schema:validate
```

### Frontend (TypeScript/React):
```bash
cd apps/frontend

# 1. ESLint — zero errors allowed
npx eslint . --max-warnings=0

# 2. TypeScript — no type errors
npx tsc --noEmit
```

### What to do when checks fail:

| Check | Failure means | Action |
|-------|--------------|--------|
| `lint:container` | Broken service wiring | Fix DI config before anything else |
| `phpstan` | Type/logic error | Fix the error. Do NOT add to baseline without documenting why |
| `schema:validate` | Entity doesn't match DB | Create a migration: `symfony console make:migration` |
| `eslint` | Code style/logic issue | Fix it. Do NOT disable the rule |
| `tsc --noEmit` | Type error in TS | Fix the type. Do NOT use `// @ts-ignore` |

### PHPStan baseline policy:
- Adding to baseline is acceptable ONLY for pre-existing issues outside your task scope
- New code you write must pass cleanly
- If ratcheting to a higher level, document in ADR and coordinate with sprint plan

---

## Rule 5: Project Conventions (Quick Reference)

| Rule | Correct | Wrong |
|------|---------|-------|
| Romanian diacritics | ș (U+0219), ț (U+021B) | ş (cedilla), ţ (cedilla) |
| Commit format | `feat(scope): description [TSK-XX]` | `update stuff` |
| Git flow | `feature/sprint-XX` → `develop` (--no-ff) → `main` | Direct push to main |
| Elasticsearch | Native `elasticsearch-php` via HTTPS | FOSElasticaBundle |
| Admin UI | API Platform + Next.js | EasyAdmin |
| Social/API clients | Symfony HttpClient | Third-party bundles |
| Gemini CLI | One call per locale (avoids truncation) | All locales in one call |
| Redis policy | `volatile-lru` | `allkeys-lru` (breaks tag invalidation) |
| Next.js routing | `proxy.ts` (not `middleware.ts`) | middleware.ts |
| Frontend dev server | `pnpm dev` | PM2 / `npm start` (PM2 is for production only) |

---

## Rule 6: Context First — Read Before You Act

Before starting ANY task, you MUST gather context from Obsidian and Notion. These tools exist specifically so agents have full project awareness. Skipping this step leads to duplicate work, contradictory implementations, and broken assumptions.

### Before every task:

**Step 1 — Read the sprint and task from Notion:**
```
# Find the current sprint and your assigned task
# Notion Sprints DB: f8922999-91ba-4384-8496-25a3606520b9
# Notion Tasks DB: 2f696048-60ac-4af9-9db9-83600149977f

- Read the sprint description to understand the overall goal
- Read the specific task description, acceptance criteria, and any agent handoff notes
- Check task dependencies (are there blocking/blocked tasks?)
- Check what other tasks in the sprint are Done vs In Progress
```

**Step 2 — Read relevant Obsidian context:**

| Starting work on... | Read first |
|---------------------|------------|
| Any task | `CLAUDE.md` (this file) + today's daily note `40_Agent_Workspace/Daily/YYYY-MM-DD.md` |
| Backend feature | `20_Architecture/Data_Model.md` + `20_Architecture/API_Endpoints.md` |
| Frontend feature | `context/DESIGN_QUICK_REFERENCE.md` (in repo) |
| AI/LLM work | `20_Architecture/Decisions/ADR-008*` through `ADR-011*` |
| Aggregator/source work | `30_Engineering_Context/Sources_and_Aggregators.md` |
| Any architectural change | `20_Architecture/Decisions/` (scan for related ADRs) |
| Clustering/editorial | `20_Architecture/Content_Pipeline.md` |
| New sprint | Previous sprint's execution log: `50_Audit/sprint-NN-execution-log.md` |

**Step 3 — Check for recent decisions and open loops:**
```
# Search Obsidian for recent context
- Check 40_Agent_Workspace/Decision_Log/ for any relevant recent decisions
- Check the last 2-3 daily notes for open loops or known issues
```

### Why this matters:
- Infrastructure often already exists — reading context prevents re-implementing what's already built
- ADRs document WHY decisions were made — ignoring them leads to contradictory implementations  
- Sprint context shows what's already done and what's pending — avoids conflicts between parallel tasks
- Daily notes capture workarounds and known issues that aren't in the code

### Do NOT:
- Start coding without reading the task description from Notion
- Assume you know the current state — verify in Obsidian
- Skip ADR review when the task touches architecture
- Ignore daily notes — they contain critical context from recent sessions

---

## Rule 7: Post-Implementation Documentation — Mandatory After Every Task

After completing any task (feature, bugfix, refactor), you MUST update external tracking before reporting "done".
This is NOT optional. Undocumented work is invisible work.

### Step 1: Update Notion task status
```
# Use Notion MCP to update the task:
- Set Status → "Done"
- Set Actual (hrs) → actual hours spent
- Add a brief completion note in the task content
```

### Step 2: Update Notion sprint (if last task in sprint)
```
# When all sprint tasks are Done:
- Set Sprint Status → "Review" (not "Done" — Radu reviews first)
- Update sprint end date if different from planned
```

### Step 3: Update Obsidian vault
After implementation, update relevant Obsidian notes:

| What changed | Update where |
|-------------|-------------|
| New entity / API resource | `20_Architecture/Data_Model.md` and `20_Architecture/API_Endpoints.md` |
| Architectural decision | Create new ADR in `20_Architecture/Decisions/ADR-NNN-title.md` |
| New aggregator / source | `30_Engineering_Context/Sources_and_Aggregators.md` |
| Stack version change | `30_Engineering_Context/Stack_Reference.md` |
| New convention / pattern | `30_Engineering_Context/Coding_Conventions.md` |
| Test count changed significantly | `CLAUDE.md` section 5 (Current State) |
| Sprint completed | `50_Audit/sprint-NN-execution-log.md` |
| Daily work | `40_Agent_Workspace/Daily/YYYY-MM-DD.md` |

### Step 4: Update daily note
Append to today's daily note (`40_Agent_Workspace/Daily/YYYY-MM-DD.md`):
```markdown
### Task [TSK-XX] — [Title]
- **Status**: Done
- **Commits**: `feat(scope): description`
- **Files changed**: list key files
- **Tests**: N new, N total green
- **Notes**: anything notable (decisions, workarounds, open issues)
```

### What NOT to update:
- `10_Notion_Mirror/` — these are synced FROM Notion, never edit manually
- Documents outside your task scope (don't "improve" unrelated docs)

---

# END OF HARD RULES

---

## 🏗️ Repository Structure: MONOREPO

**IMPORTANT**: This is a monorepo containing both backend and frontend applications.

## Project Overview

**Deschide News App** - A multilanguage news platform with:
- **Backend**: Symfony 8.0 (PHP 8.5.3) - Headless API (API Platform)
- **Frontend**: Next.js 16 (React 19 / TypeScript) - Web interface + Admin
- **Languages**: Romanian (ro), English (en), Russian (ru)
- **Architecture**: Monorepo with unified version control
- **AI Pipeline**: Dual-LLM (Gemini CLI for bulk/context, Claude CLI for journalistic polish)
- **Search**: Elasticsearch 9.3 (HTTPS, native client — NOT FOSElasticaBundle)
- **Real-time**: Mercure Hub + SSE
- **Message Queue**: RabbitMQ via Symfony Messenger (5+ async workers)

## Project Structure

```
/var/www/deschide_news_app/    # ROOT MONOREPO
├── .git/                      # Unified git repository
├── apps/
│   ├── backend/              # Symfony 8.0 API (PHP 8.5)
│   │   ├── config/           # Configuration files
│   │   ├── public/           # Web root (index.php)
│   │   ├── src/              # Application code
│   │   ├── migrations/       # Database migrations
│   │   ├── docs/             # Backend documentation
│   │   ├── composer.json     # PHP dependencies
│   │   └── README.md         # Backend README
│   └── frontend/             # Next.js 16.2 (React 19.2, TypeScript)
│       ├── app/              # App Router pages
│       ├── components/       # React components
│       ├── lib/              # Utilities
│       ├── docs/             # Frontend documentation
│       ├── package.json      # Node dependencies
│       └── README.md         # Frontend README
├── context/                  # Design system documentation
├── docs/                     # Centralized documentation
├── sprints/                  # Sprint planning
├── scripts/                  # Deployment & utility scripts
├── .claude/                  # Claude Code agents (25+)
│   └── agents/               # Specialized agent definitions
├── .gemini/                  # Gemini CLI configuration
├── CLAUDE.md                 # This file (agent instructions)
├── GEMINI.md                 # Gemini agent instructions
└── README.md                 # Main project README
```

**Working Directories:**
- Backend: `/var/www/deschide_news_app/apps/backend`
- Frontend: `/var/www/deschide_news_app/apps/frontend`

## Port Configuration

### Development Ports (No Conflicts)

| Application | Port | Access URL | Status |
|-------------|------|------------|--------|
| **Backend (Symfony)** | 8081 | http://127.0.0.1:8081 | ✅ Running |
| **Backend (Nginx)** | 80 | http://api.deschide.local | ⬜ Not configured yet |
| **Frontend (Next.js)** | 3005 | http://localhost:3005 | ✅ Running |
| **Frontend (Nginx)** | 80 | http://deschide.local | ⬜ Not configured yet |
| **CDN (Static Assets)** | 8082 | http://127.0.0.1:8082 | ✅ Running |

### Shared Services (System-wide)

| Service | Port | Access | Usage |
|---------|------|--------|-------|
| PostgreSQL | 5432 | localhost:5432 | Database: `deschide_news` |
| Redis | 6379 | localhost:6379/1 | Cache, sessions (DB 1) |
| RabbitMQ | 5672 | amqp://localhost:5672 | Message queue |
| RabbitMQ Management | 15672 | http://localhost:15672 | Admin UI |
| Elasticsearch | 9200 | https://localhost:9200 | Search engine |
| Mercure | 3000 | http://localhost:3000 | Real-time push |
| Prometheus | 9090 | http://localhost:9090 | Metrics |
| Grafana | 3002 | http://localhost:3002 | Dashboards |

**Note**: All shared services use namespacing to avoid conflicts with other applications (pm_ai, ecom)

## Common Commands

### Backend (Symfony)

**Location**: `/var/www/deschide_news_app/apps/backend`

**Development Server:**
```bash
cd /var/www/deschide_news_app/apps/backend

# Start server on port 8081 (recommended)
symfony serve -d --port=8081

# Check status
symfony server:status

# View logs
symfony server:log

# Stop server
symfony server:stop
```

**Database:**
```bash
# Run migrations
symfony console doctrine:migrations:migrate

# Create migration after entity changes
symfony console make:migration

# Create database
symfony console doctrine:database:create

# Load fixtures (if configured)
symfony console doctrine:fixtures:load
```

**Message Queue (RabbitMQ + Symfony Messenger):**
```bash
# Active workers (managed via Supervisor):
# - ai_async: AI generation, translation, background proposals
# - messenger-translations: article translation pipeline
# - social_media: social distribution (prepped)
# - scheduler_default: PublishScheduledArticles (1 min)
# - scheduler_tag_maintenance: tag cleanup (daily 03:00)
# - scheduler_translation: translation batches (5/15 min)
# - editorial: cluster verification (30 min)

# Check worker status
sudo supervisorctl status

# Check message queue stats
symfony console messenger:stats

# Consume manually (for debugging)
symfony console messenger:consume ai_async -vv --limit=10

# Check failed messages
symfony console messenger:failed:show
```

**Import Commands (Newscoop CMS Migration):**
```bash
# Import data from legacy Newscoop CMS (requires configured connection)
symfony console app:import:categories
symfony console app:import:authors
symfony console app:import:images
symfony console app:import:articles
symfony console app:import:translations

# Generate thumbnails after image import
symfony console app:import:generate-thumbnails

# DEPRECATED: use app:dev:reset instead (fixtures + RSS import)
# symfony console app:sample-import
```

**AI & Clustering Commands:**
```bash
# Translate article to EN+RU
symfony console app:translate:articles {ID} --force

# Generate AI article from cluster
symfony console app:generate-article

# Cluster management
symfony console app:cluster:cleanup --rebuild --since=14d
symfony console app:cluster:cleanup --verify

# Press release dedup
symfony console app:press-release:dedup-urls

# Run aggregators
symfony console app:aggregator:run --dry-run --limit=3
```

**Elasticsearch:**
```bash
# Create indices for all locales (ro, en, ru)
symfony console app:elasticsearch:create-index
symfony console app:elasticsearch:create-image-index

# Index all existing content
symfony console app:elasticsearch:index-articles
symfony console app:elasticsearch:index-images
```

**Article Management:**
```bash
# Cleanup expired article locks (prevents stale edit locks)
symfony console app:cleanup-expired-locks

# Publish scheduled articles (run via cron or scheduler)
symfony console app:publish-scheduled-articles
```

**Editorial Context (AI Agent Access):**
```bash
# Export single article as Markdown (pipe-friendly for AI agents)
symfony console app:context:export article {ID} --format=markdown --locale=ro

# Export article as JSON
symfony console app:context:export article {ID} --format=json --locale=en

# Batch export recent articles
symfony console app:context:export articles --limit=20 --category=politica

# Export topics list
symfony console app:context:export topics --format=json

# Pipe to Gemini CLI
symfony console app:context:export article 42 | gemini -p "Evaluează"

# Write to file
symfony console app:context:export article 42 --output=/tmp/article.md
```

**Dev Reset:**
```bash
# ⚠️ DOAR DEV: Reset complet DB + Cache
symfony console app:dev:reset
symfony console app:dev:reset --skip-fixtures --skip-elasticsearch
```

**Code Quality:**
```bash
# Clear cache
symfony console cache:clear

# Run PHPStan (see HARD RULES above for policy)
vendor/bin/phpstan analyse src/ --no-progress --memory-limit=1G

# Container lint
symfony console lint:container

# Schema validation
symfony console doctrine:schema:validate
```

**Generate Code:**
```bash
# Create entity
symfony console make:entity

# Create controller
symfony console make:controller

# Generate JWT keys
symfony console lexik:jwt:generate-keypair
```

**API Documentation:**
```bash
# View API resources (JSON-LD format)
curl http://127.0.0.1:8081/api | jq '.'

# View full API documentation (Hydra)
curl http://127.0.0.1:8081/api/docs.jsonld | jq '.supportedClass[] | .title'

# Test an endpoint (e.g., list articles)
curl http://127.0.0.1:8081/api/articles

# Test with language header
curl -H "Accept-Language: ro" http://127.0.0.1:8081/api/articles
```

### Frontend (Next.js)

**Location**: `/var/www/deschide_news_app/apps/frontend`

**Development Server:**
```bash
cd /var/www/deschide_news_app/apps/frontend

# Start dev server on port 3005 (ALWAYS use this in development)
pnpm dev

# NOTE: Do NOT use PM2 in development. PM2 is for production only.
```

**Building:**
```bash
# Production build
pnpm build

# Start production server
pnpm start
```

**Linting:**
```bash
pnpm lint
```

### Quick Start (Both Applications)

```bash
# Terminal 1 - Start Backend
cd /var/www/deschide_news_app/apps/backend
symfony serve -d --port=8081

# Terminal 2 - Start Frontend
cd /var/www/deschide_news_app/apps/frontend
pnpm dev

# Terminal 3 - Workers are managed by Supervisor
cd /var/www/deschide_news_app/apps/backend
sudo supervisorctl status              # Check all workers
sudo supervisorctl restart all         # Restart if needed
```

**Verify Applications:**
```bash
# Backend
curl http://127.0.0.1:8081

# Frontend
curl http://localhost:3005

# Check ports
ss -tulpn | grep -E ":(3005|8081)"
```

## Architecture Notes

### Backend (Symfony 8.0)

**Location**: `/var/www/deschide_news_app/apps/backend`

- **API Structure**: RESTful JSON API with Hydra/JSON-LD support
- **API Documentation**: `http://127.0.0.1:8081/api/docs.jsonld` (Hydra documentation)
- **API Entrypoint**: `http://127.0.0.1:8081/api`
- **Authentication**: JWT tokens (Lexik JWT + Gesdinet Refresh Token)
- **Database**: PostgreSQL 18.2 via Doctrine ORM 3.5
- **Multilanguage**: Gedmo Translatable (strict mode with HINT_INNER_JOIN)
- **Message Queue**: RabbitMQ via Symfony Messenger
- **Cache**: Redis 8.0 (DB 1, prefix: `deschide_news:*`, policy: `volatile-lru` — NEVER use `allkeys-lru`)
- **Search**: Elasticsearch 9.3 (HTTPS, index: `deschide_articles_trilingual`) — native `elasticsearch-php`, NOT FOSElasticaBundle
- **Real-time**: Mercure Hub (topics: `deschide_news/*`)

**Directory Structure:**
- `src/Entity/` - Doctrine entities (Article, Category, Author, Image, PressRelease, StoryCluster, BackgroundProposal, AppSettings, etc.)
- `src/State/` - API Platform State Providers and Processors (locale-aware queries)
- `src/Controller/` - API controllers (for custom endpoints)
- `src/Service/` - Business logic
  - `Service/Ai/` - LLM integrations (GeminiCliService, ClaudeCliClient, AiProviderRegistry, LlmRetryExecutor)
  - `Service/Aggregator/` - Content aggregators (RSS, scraping, Telegram, etc.)
  - `Service/Clustering/` - Story clustering, semantic verification, importance scoring
  - `Service/Content/` - Content cleaning, deduplication
  - `Service/Editorial/` - Auto-publish gate, sensitive topic detection
- `src/Repository/` - Custom queries with Gedmo hints
- `src/Message/` - Message classes for async processing
- `src/MessageHandler/` - Message handlers
- `src/Command/` - Console commands (Import/, Elasticsearch/, AI generation, cluster management)
- `src/Dto/` - Data Transfer Objects (ArticleDraft, etc.)
- `src/Enum/` - PHP Enums (ArticleStatus, ArticleBadge, AggregatorSourceType, etc.)
- `src/EventListener/` - Doctrine event listeners
- `src/EventSubscriber/` - Symfony event subscribers
- `config/` - YAML configuration files
- `config/packages/scraping_aggregators.yaml` - Scraper source configs

**Key Patterns:**
- **API Platform State Provider/Processor Pattern**: Custom `Provider` classes in `src/State/` handle data retrieval with locale-aware queries and eager loading. Custom `Processor` classes handle create/update/delete operations. Providers apply Gedmo `HINT_TRANSLATABLE_LOCALE` to all queries for proper translation handling (see `src/State/ArticleProvider.php:58-61`).
- **Dependency Injection**: Constructor-based DI throughout the application
- **Gedmo Extensions**: Translatable (strict mode with HINT_INNER_JOIN), Sluggable, Timestampable
- **Article Locking System**: `ArticleLock` entity prevents concurrent editing. Use `app:cleanup-expired-locks` command to remove stale locks.
- **Image Handling**: VichUploader for file uploads, custom thumbnail generation (Intervention Image) with async processing
- **Serialization Groups**: API responses use groups (`article:read`, `article:write`, etc.) with `MaxDepth(2)` to prevent circular references
- **Eager Loading**: Providers use `leftJoin` + `addSelect` to prevent N+1 queries (see example at line 461-467)
- **Elasticsearch Integration**: Full-text search for articles and images across all locales
- **Import System**: Commands in `src/Command/Import/` for migrating from legacy Newscoop CMS
- **Rate Limiting**: For API endpoints (configuration in API Platform)
- **Prometheus Metrics**: Integration for monitoring

**API Resources & Endpoints:**

The API follows the Hydra/JSON-LD specification for hypermedia-driven APIs. All endpoints are prefixed with `/api`.

| Resource | Endpoint | Description |
|----------|----------|-------------|
| **Articles** | `/api/articles` | News articles with multilanguage support |
| **Categories** | `/api/categories` | Article categories (translatable) |
| **Authors** | `/api/authors` | Article authors |
| **Images** | `/api/images` | Uploaded images (original files) |
| **Thumbnails** | `/api/thumbnails` | Generated thumbnail images |
| **Thumbnail Profiles** | `/api/thumbnail_profiles` | Thumbnail generation profiles (10 variants) |
| **Article Images** | `/api/article_images` | Association between articles and images |
| **Press Releases** | `/api/press_releases` | Aggregated content awaiting editorial review |
| **Story Clusters** | `/api/story_clusters` | AI-grouped related press releases |
| **Background Proposals** | `/api/background_proposals` | AI-generated editorial context blocks |
| **App Settings** | `/api/app_settings` | Runtime configuration (thresholds, feature flags) |

**API Documentation URLs:**
- **Hydra/JSON-LD Documentation**: `GET http://127.0.0.1:8081/api/docs.jsonld`
- **API Entrypoint**: `GET http://127.0.0.1:8081/api`
- **OpenAPI/Swagger** (if configured): `http://127.0.0.1:8081/api/docs` or `/api/docs.json`

**Common HTTP Methods:**
- `GET /api/{resource}` - List collection (with pagination)
- `GET /api/{resource}/{id}` - Get single item
- `POST /api/{resource}` - Create new item
- `PUT /api/{resource}/{id}` - Full update
- `PATCH /api/{resource}/{id}` - Partial update
- `DELETE /api/{resource}/{id}` - Delete item

**Pagination & Filtering:**
- Use query parameters: `?page=1&itemsPerPage=30`
- Language filtering: Add `Accept-Language: ro` header or `?locale=ro` param
- Sorting: `?order[field]=asc|desc`
- Search: `?search=keyword` (implementation varies by resource)

**Response Format (JSON-LD):**
```json
{
  "@context": "/api/contexts/Article",
  "@id": "/api/articles/1",
  "@type": "Article",
  "id": 1,
  "title": "Article Title",
  "slug": "article-title",
  ...
}
```

**Testing API Endpoints:**
```bash
# Get all articles (Romanian - default locale)
curl -H "Accept-Language: ro" http://127.0.0.1:8081/api/articles

# Get articles in English
curl -H "Accept-Language: en" http://127.0.0.1:8081/api/articles

# Get single article
curl http://127.0.0.1:8081/api/articles/1

# Filter articles by category
curl "http://127.0.0.1:8081/api/articles?category=5"

# Filter by status and featured
curl "http://127.0.0.1:8081/api/articles?status=published&isFeatured=true"

# Pagination
curl "http://127.0.0.1:8081/api/articles?page=2&itemsPerPage=10"

# Ordering
curl "http://127.0.0.1:8081/api/articles?order[publishedAt]=DESC"

# Create article (requires authentication)
curl -X POST http://127.0.0.1:8081/api/articles \
  -H "Content-Type: application/ld+json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{"title": "New Article", "content": "..."}'

# Get API documentation
curl http://127.0.0.1:8081/api/docs.jsonld | jq '.supportedClass[] | {"@id", "title"}'
```

**How Locale Handling Works:**
1. Client sends `Accept-Language: ro` (or `en`, `ru`) header with API request
2. State Provider (e.g., `ArticleProvider`) extracts locale from header
3. Provider applies `HINT_TRANSLATABLE_LOCALE` to Doctrine query
4. Gedmo Translatable automatically loads translated fields for that locale
5. If translation doesn't exist for requested locale, falls back to default locale (ro)
6. Related entities (Category, Author) also have locale set and refreshed

**Environment Variables** (`.env.local`):
```bash
DATABASE_URL="postgresql://deschide_user:password@127.0.0.1:5432/deschide_news"
REDIS_URL=redis://localhost:6379/1
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@localhost:5672/%2f/deschide_news_messages
ELASTICSEARCH_URL=https://localhost:9200
MERCURE_URL=http://localhost:3000/.well-known/mercure
CORS_ALLOW_ORIGIN=^http://localhost:3005$|^http://deschide\.local$
```

### Frontend (Next.js 16)

**Location**: `/var/www/deschide_news_app/apps/frontend`

- **Router**: App Router (`app/` directory)
- **Bundler**: Turbopack
- **Styling**: Tailwind CSS 4 + Flowbite React
- **API Integration**: Fetch to Symfony backend (JWT auth)
- **Multilanguage**: Custom i18n with locale routing
- **Real-time**: Mercure SSE subscription
- **Rich Text**: TinyMCE (admin article editor)

**Directory Structure:**
- `app/` - App Router pages and layouts
- `app/layout.js` - Root layout
- `app/page.js` - Home page
- `public/` - Static assets
- `.env.local` - Environment variables

**Environment Variables** (`.env.local`):
```bash
PORT=3005
NEXT_PUBLIC_API_URL=http://api.deschide.local
NEXT_PUBLIC_MERCURE_URL=http://localhost:3000/.well-known/mercure
NEXT_PUBLIC_DEFAULT_LOCALE=ro
NEXT_PUBLIC_AVAILABLE_LOCALES=ro,en,ru
```

**Key Patterns:**
- Server Components for data fetching
- Client Components for interactivity
- Image optimization with Next.js Image
- Turbopack for fast dev builds
- Mercure integration for real-time updates

**Cache tag invariant (T60.10):**
- All `lib/api/*.ts` fetches MUST declare `tags` alongside `revalidate`.
  Without tags, the fetch is invisible to `revalidateTag()` at
  `/api/revalidate` and serves stale data for the full revalidate
  window after a backend change.
- Use only `CACHE_TAGS` constants from `lib/data/cache-config.ts` — do
  not inline string literals.
- See `__tests__/unit/lib/api/cache-tags.test.ts` for the regression
  guard. CI fails if a new fetch lands without tags.

### Cross-cutting Concerns

- **API Communication**: Frontend (port 3005) → Backend (port 8081)
- **CORS**: Configured in NelmioCorsBundle for localhost:3005
- **Authentication Flow**: JWT access tokens + refresh tokens
- **Multilanguage**:
  - Backend: Gedmo Translatable with strict mode
  - Frontend: Custom i18n library (to be implemented)
- **Data Validation**:
  - Server-side: Symfony Validator
  - Client-side: To be implemented
- **Image Handling**:
  - Upload → Backend stores original
  - Async thumbnail generation (10 profiles)
  - Frontend uses Next.js Image with remote patterns
- **Real-time Updates**:
  - Backend publishes to Mercure (`deschide_news/breaking`)
  - Frontend subscribes via SSE

## Caching Strategy (L1/L2/L3)

The application uses a multi-layer caching architecture for optimal performance:

### Cache Hierarchy

| Layer | Technology | Scope | TTL | Purpose |
|-------|------------|-------|-----|---------|
| **L1** | APCu/OPcache | Per-server | 30s-5min | PHP metadata, hot data |
| **L2** | Redis (DB 1) | Shared | 5min-1h | API responses, sessions |
| **L3** | Next.js ISR | Edge/CDN | 60s or ODR | Static HTML pages |

### Data Flow

```
User Request
    │
    ▼
┌─────────────────────────────────────┐
│ L3: Next.js ISR / CDN               │
│ - Homepage: revalidate: 60          │
│ - Articles: On-Demand Revalidation  │
└─────────────────────────────────────┘
    │ MISS
    ▼
┌─────────────────────────────────────┐
│ L2: Redis (Tag-based invalidation)  │
│ - Prefix: deschide_news:*           │
│ - Tags: article_123, category_5     │
└─────────────────────────────────────┘
    │ MISS
    ▼
┌─────────────────────────────────────┐
│ L1: APCu (Local memory)             │
│ - Doctrine metadata                 │
│ - Config cache                      │
└─────────────────────────────────────┘
    │ MISS
    ▼
┌─────────────────────────────────────┐
│ PostgreSQL (Source of truth)        │
└─────────────────────────────────────┘
```

### On-Demand Revalidation (ODR)

When content changes in Symfony, the frontend cache is invalidated immediately:

```
Article Update (Symfony Admin)
    │
    ▼
┌─────────────────────────────────────┐
│ Doctrine PostUpdate Event           │
└─────────────────────────────────────┘
    │
    ├──▶ Redis: invalidateTags(['article_123'])
    │
    └──▶ Symfony Messenger (async)
              │
              ▼
         POST /api/revalidate (Next.js)
              │
              ▼
         revalidatePath('/ro/article/slug')
```

**Frontend Webhook Endpoint:**
```typescript
// apps/frontend/app/api/revalidate/route.ts
export async function POST(request: NextRequest) {
  const secret = request.headers.get('x-revalidate-secret');
  if (secret !== process.env.REVALIDATE_SECRET) {
    return NextResponse.json({ error: 'Invalid' }, { status: 401 });
  }
  const { path, locale } = await request.json();
  revalidatePath(`/${locale}/article/${path}`, 'page');
  revalidatePath(`/${locale}`, 'page'); // Always refresh homepage
  return NextResponse.json({ revalidated: true });
}
```

**Environment Variables:**
```bash
# Backend (.env)
FRONTEND_REVALIDATE_URL=http://localhost:3005/api/revalidate
FRONTEND_REVALIDATE_SECRET=your-secure-secret

# Frontend (.env.local)
REVALIDATE_SECRET=your-secure-secret
```

### Cache TTL Strategy

| Content Type | L1 | L2 | L3 (ISR) | Invalidation |
|--------------|----|----|----------|--------------|
| Homepage | 30s | 60s | revalidate: 60 | Time-based |
| Article | 5min | 1h | ODR | On update |
| Category list | 5min | 30min | revalidate: 60 | Time-based |
| Breaking news | - | 30s | ODR immediate | Sync webhook |

### Agent Reference

For detailed cache management, see:
- `.claude/agents/cache-sync-specialist.md` - ODR implementation
- `.claude/agents/database-engineer.md` - L1/L2 optimization

## CDN and Image Serving

The application uses a separate CDN server for serving static assets (images, thumbnails) to optimize performance and separate concerns.

### CDN Configuration

**Development Environment:**
- **CDN URL**: `http://127.0.0.1:8082`
- **Backend Upload Directory**: `/var/www/deschide_news_app/deschide_backend/public/uploads/`
- **Image Storage Path**: `uploads/images/`
- **Thumbnail Storage Path**: `uploads/thumbnails/`

**Frontend Configuration** (`.env.local`):
```bash
NEXT_PUBLIC_API_URL=http://127.0.0.1:8081
NEXT_PUBLIC_CDN_URL=http://127.0.0.1:8082
```

### Image Path Structure

**API Response Structure:**
When fetching articles with images from the API (e.g., `/api/important_articles`), the response includes:

```json
{
  "article": {
    "id": 140,
    "title": "Article Title",
    "articleImages": [
      {
        "id": 109,
        "image": {
          "filename": "image_69020ece588bd_800x600_ef4444.png",
          "path": "images/image_69020ece588bd_800x600_ef4444.png",
          "width": 800,
          "height": 600,
          "mimeType": "image/png",
          "alt": "Image description"
        },
        "position": 0,
        "isFeatured": true
      }
    ]
  }
}
```

**Building Image URLs in Frontend:**

```typescript
// Original images
const imageUrl = `${process.env.NEXT_PUBLIC_CDN_URL}/uploads/${image.path}`;
// Example: http://127.0.0.1:8082/uploads/images/image_69020ece588bd_800x600_ef4444.png

// Thumbnails (if using thumbnail entities)
const thumbnailUrl = `${process.env.NEXT_PUBLIC_CDN_URL}/uploads/${thumbnail.path}`;
// Example: http://127.0.0.1:8082/uploads/thumbnails/hero_big/image_69020ece588bd.webp
```

### Image Entity Serialization

The API includes images in article responses through proper serialization configuration:

**Article Entity:**
- Property `articleImages` has serialization groups: `['article:read', 'article:detail']`
- Includes `#[MaxDepth(2)]` to prevent circular references
- Enable max depth in API operations: `'enable_max_depth' => true`

**ArticleImage Entity:**
- Links Article ↔ Image with metadata (position, isFeatured)
- Properties serialized with `article:read` group: id, image, position, isFeatured

**Image Entity:**
- All essential properties have `article:read` serialization group
- Includes: filename, path, width, height, mimeType, size, originalFilename, alt

**Important:** The Article entity must have the `getArticleImages()` method for serialization to work:
```php
public function getArticleImages(): Collection
{
    return $this->articleImages;
}
```

### Eager Loading for Performance

To prevent N+1 queries, providers must use eager loading:

```php
// In ArticleProvider.php and ImportantArticlesListProvider.php
$queryBuilder = $repository->createQueryBuilder('a')
    ->leftJoin('a.articleImages', 'ai')
    ->addSelect('ai')
    ->leftJoin('ai.image', 'img')
    ->addSelect('img')
    ->orderBy('ai.position', 'ASC');
```

### Thumbnail Profiles

The system supports 10 thumbnail profiles for different use cases:

| Profile | Dimensions | Format | Usage |
|---------|------------|--------|-------|
| `hero_big` | 1920x1080 | WebP | Hero section main image |
| `hero_small` | 800x600 | WebP | Hero section smaller images |
| `article_main` | 1600x900 | WebP | Article detail main image |
| `article_inline` | 1200x675 | WebP | Inline article images |
| `card_large` | 800x600 | WebP | Large card thumbnails |
| `card_medium` | 600x400 | WebP | Medium card thumbnails |
| `card_small` | 400x300 | WebP | Small card thumbnails |
| `list_item` | 300x200 | WebP | List view thumbnails |
| `mobile_hero` | 800x600 | WebP | Mobile hero images |
| `gallery` | 1920x600 | WebP | Wide gallery images |

**Note:** Thumbnails are generated asynchronously after image upload using Symfony Messenger and stored separately from originals.

## Git Workflow (Git-Flow)

This repository uses **Git-Flow** branching model for organized development and releases.

### Branch Strategy

- **`main`** - Production-ready code (protected, no direct commits)
- **`develop`** - Integration branch for ongoing development (protected)
- **`feature/*`** - Feature development branches (branched from `develop`)
- **`release/*`** - Release preparation branches (branched from `develop`)
- **`hotfix/*`** - Production hotfix branches (branched from `main`)
- **`bugfix/*`** - Bug fixes during development (branched from `develop`)

### Git-Flow Configuration

```bash
# Git-flow is initialized with these settings:
gitflow.branch.master = main
gitflow.branch.develop = develop
gitflow.prefix.feature = feature/
gitflow.prefix.bugfix = bugfix/
gitflow.prefix.release = release/
gitflow.prefix.hotfix = hotfix/
gitflow.prefix.support = support/
```

### Common Git-Flow Commands

**Starting a new feature:**
```bash
# Start new feature from develop
git flow feature start DESK-123-my-feature

# Work on feature...
git add .
git commit -m "feat(backend): implement feature"

# Finish feature (merges to develop and deletes feature branch)
git flow feature finish DESK-123-my-feature

# Push develop
git push origin develop
```

**Creating a release:**
```bash
# Start release from develop
git flow release start 1.1.0

# Update version numbers, CHANGELOG, etc.
# Make final adjustments

# Finish release (merges to main and develop, creates tag)
git flow release finish 1.1.0

# Push everything
git push origin main develop --tags
```

**Emergency hotfix:**
```bash
# Start hotfix from main
git flow hotfix start 1.0.1

# Fix the critical issue
git add .
git commit -m "fix: critical production bug"

# Finish hotfix (merges to main and develop, creates tag)
git flow hotfix finish 1.0.1

# Push everything
git push origin main develop --tags
```

**Bug fix during development:**
```bash
# Start bugfix from develop
git flow bugfix start fix-validation-error

# Fix the bug
git add .
git commit -m "fix(backend): validation error in article form"

# Finish bugfix (merges to develop)
git flow bugfix finish fix-validation-error
```

### Branch Naming Conventions

**For Monorepo (with scope):**

| Type | Pattern | Example | Purpose |
|------|---------|---------|---------|
| Feature | `feature/[scope]-[description]` | `feature/backend-article-reactions` | New features |
| Feature | `feature/[scope]-[description]` | `feature/frontend-admin-dashboard` | Frontend features |
| Feature | `feature/fullstack-[description]` | `feature/fullstack-user-auth` | Full-stack features |
| Bugfix | `bugfix/[description]` | `bugfix/fix-image-upload` | Bug fixes in development |
| Release | `release/[version]` | `release/1.1.0` | Release preparation |
| Hotfix | `hotfix/[version]` | `hotfix/1.0.1` | Production hotfixes |

### Commit Message Convention

Follow **Conventional Commits** specification:

```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

**Types:**
- `feat` - New feature
- `fix` - Bug fix
- `docs` - Documentation changes
- `style` - Code style changes (formatting, no code change)
- `refactor` - Code refactoring
- `perf` - Performance improvements
- `test` - Adding or updating tests
- `chore` - Maintenance tasks (dependencies, config)
- `ci` - CI/CD changes
- `build` - Build system changes

**Scopes:**
- `backend` - Backend (Symfony) changes
- `frontend` - Frontend (Next.js) changes
- `docs` - Documentation
- `ci` - CI/CD
- `infra` - Infrastructure

**Examples:**
```bash
git commit -m "feat(backend): add article reaction system"
git commit -m "fix(frontend): resolve image loading issue in gallery"
git commit -m "docs: update API documentation for categories"
git commit -m "chore(backend): upgrade Symfony to 8.0.1"
```

### Pull Request Process

1. **Create feature branch** from `develop`:
   ```bash
   git flow feature start my-feature
   ```

2. **Make changes and commit** following commit conventions

3. **Push branch** to origin:
   ```bash
   git flow feature publish my-feature
   # OR manually:
   git push -u origin feature/my-feature
   ```

4. **Create Pull Request** on GitHub:
   - **Base:** `develop` (NOT `main`)
   - **Title:** Use conventional commit format
   - **Description:** Explain changes, add screenshots if applicable
   - **Link issues:** Reference related issues/tickets

5. **Request review** from team members

6. **Merge after approval**:
   - Use "Squash and merge" for clean history
   - Delete branch after merge

### Manual Git-Flow (without git-flow tool)

If `git flow` command is not available, you can use standard git commands:

**Feature workflow:**
```bash
# Start feature
git checkout develop
git pull origin develop
git checkout -b feature/my-feature

# Finish feature
git checkout develop
git merge --no-ff feature/my-feature
git branch -d feature/my-feature
git push origin develop
```

**Release workflow:**
```bash
# Start release
git checkout develop
git checkout -b release/1.1.0
# Make version updates...

# Finish release
git checkout main
git merge --no-ff release/1.1.0
git tag -a v1.1.0 -m "Release version 1.1.0"
git checkout develop
git merge --no-ff release/1.1.0
git branch -d release/1.1.0
git push origin main develop --tags
```

### Branch Protection Rules

**Configured on GitHub:**

**`main` branch:**
- ✅ Require pull request reviews before merging (1-2 reviewers)
- ✅ Require status checks to pass before merging
- ✅ Require branches to be up to date before merging
- ✅ Require conversation resolution before merging
- ❌ Do not allow force pushes
- ❌ Do not allow deletions

**`develop` branch:**
- ✅ Require pull request reviews (recommended)
- ✅ Require status checks to pass
- ⚠️ Allow force pushes from admins only (for rebasing, if needed)

## Development Workflow

1. **Backend changes**:
   - Create feature branch: `git flow feature start backend-my-feature`
   - Modify entities → `symfony console make:migration` → `symfony console doctrine:migrations:migrate`
   - Update controllers/services → Clear cache if needed
   - Test with curl or Postman
   - Commit using conventional commits: `git commit -m "feat(backend): description"`
   - Finish feature: `git flow feature finish backend-my-feature`

2. **Frontend changes**:
   - Create feature branch: `git flow feature start frontend-my-feature`
   - Update pages/components in `app/` directory
   - Verify API integration with backend
   - Test responsive design (mobile/tablet/desktop)
   - Check multilanguage support
   - Commit: `git commit -m "feat(frontend): description"`
   - Finish feature: `git flow feature finish frontend-my-feature`

3. **Full-stack features**:
   - Create feature branch: `git flow feature start fullstack-my-feature`
   - Start with backend (entities, migrations, controllers, services)
   - Then frontend (pages, components, API integration)
   - Test end-to-end flow
   - Commit: `git commit -m "feat(fullstack): description"`
   - Finish feature: `git flow feature finish fullstack-my-feature`

4. **Database schema changes**:
   - Always use Doctrine migrations
   - Never manual SQL in production
   - Test migrations on staging first
   - Commit: `git commit -m "feat(backend): add new entity for X"`

5. **Async processing**:
   - Create Message class in `src/Message/`
   - Create MessageHandler in `src/MessageHandler/`
   - Dispatch via MessageBus
   - Test with workers running

## Testing Strategy

### Automated Testing Agents (Playwright MCP)

**Comprehensive test coverage** using specialized agents:

- **Backend API Tester** - Tests all Symfony API endpoints
- **Frontend E2E Tester** - Tests Next.js user interfaces
- **Full-Stack Integration Tester** - Tests complete workflows
- **Multilanguage Tester** - Tests i18n/l10n functionality
- **Admin Panel Tester** - Tests administrative interface
- **Performance Tester** - Tests performance metrics

**Documentation**: `docs/TESTING_AGENTS_GUIDE.md`
**Agent Specs**: `.claude/agents/*.md`

**Quick Commands:**
```bash
# Run existing Playwright tests
cd apps/frontend
pnpm test:e2e              # E2E tests
pnpm test:integration      # Integration tests
pnpm test:e2e:ui          # UI mode (visual debugging)
```

**Agent Invocations:**
```
@backend-api-tester test all endpoints
@frontend-e2e-tester test all user flows
@fullstack-integration-tester test article lifecycle
@multilanguage-tester test all locales
@admin-panel-tester test admin functionality
@performance-tester test performance
```

### Backend Testing

- **PHPUnit**: ✅ **~4,350+ tests - ALL PASSING** (as of Sprint 45)
- **Test Suites**:
  - Entity tests, API integration tests, Service tests, State Provider tests
  - Clustering tests (79 tests), AI pipeline tests, Aggregator tests
  - Content cleaning, auto-publish gate, sensitive topic detection
- **Run Tests** (see HARD RULES for targeted vs full suite policy):
  ```bash
  cd /var/www/deschide_news_app/apps/backend
  # Targeted (during development):
  ./vendor/bin/phpunit --filter="YourTestClass" --testdox
  # Full suite (sprint finalization only):
  XDEBUG_MODE=off ./vendor/bin/phpunit --no-coverage
  ```
- **Static Analysis**: PHPStan configured and enforced (see HARD RULES Rule 4)
- **Container Lint**: `symfony console lint:container` — mandatory pre-commit
- **Schema Validation**: `symfony console doctrine:schema:validate` — mandatory pre-commit

### Frontend Testing

- **Jest**: ✅ **~804+ tests - ALL PASSING** (as of Sprint 45)
- **Test Suites**:
  - Component tests (SafeHtml, ArticleBody, StructuredData, AiBadge, ScoreBreakdown, etc.)
  - Integration tests (API integration, navigation, cluster UI)
  - Utility tests (sanitization, validation)
- **Run Tests**:
  ```bash
  cd /var/www/deschide_news_app/apps/frontend
  # Targeted:
  npx jest --testPathPattern="YourComponent" --verbose
  # Full suite:
  npm test -- --watchAll=false
  ```

### Test Status

📊 **Current counts (Sprint 45, April 2026):**
- Backend PHPUnit: ~4,350+ tests ✅
- Frontend Jest: ~804+ tests ✅
- **Total: ~5,150+ tests passing**
- Zero regressions policy enforced at sprint boundaries

## Important Documentation

- **Development Environment**: `DEVELOPMENT_ENVIRONMENT.md` - Complete setup guide
- **Infrastructure**: `APPLICATIONS_ARCHITECTURE.md` - Shared services details
- **Infrastructure Integration**: `docs/infrastructure-integration.md` - Service integration guide
- **Development Plan**: `sprints/development-plan.md` - 8 sprints roadmap
- **Entity Documentation**: `docs/entity-*.md` - Individual entity specs
- **API Documentation**:
  - Live Hydra/JSON-LD docs: `http://127.0.0.1:8081/api/docs.jsonld`
  - API Entrypoint: `http://127.0.0.1:8081/api`
  - See "API Resources & Endpoints" section above for details

## Design System Documentation

**IMPORTANT**: All frontend public-facing development MUST follow the design system documented in the `context/` folder.

### Design Documents

| Document | Location | Purpose |
|----------|----------|---------|
| **Full Design Guide** | `context/design_principles_and_features.md` | Comprehensive 1600+ line design specification |
| **Quick Reference** | `context/DESIGN_QUICK_REFERENCE.md` | Fast lookup for common design decisions |

### Key Design Decisions

| Aspect | Decision | Rationale |
|--------|----------|-----------|
| **AMP** | NO | Deprecated - Core Web Vitals is the standard |
| **FB Instant Articles** | NO | Discontinued since 2023 |
| **Telegram Instant View** | YES | Major traffic source in Moldova |
| **Layout System** | Bento Grid | Modern trend, high information density |
| **Typography** | Serif body + Sans UI | Readability + Clarity |

### Design System Essentials

**Typography Scale:**
- Hero Title: 48px (desktop) / 32px (mobile) - Sans 800
- Article H1: 40px / 28px - Sans 700
- Card Title: 24px / 20px - Sans 600
- Body Text: 19px / 17px - Serif 400
- Meta: 14px / 13px - Sans 500

**Category Colors:**
```
politica:  #1d4ed8 (Blue)
economie:  #047857 (Green)
societate: #7c3aed (Purple)
sport:     #dc2626 (Red)
cultura:   #b45309 (Amber)
external:  #0891b2 (Cyan)
```

**Responsive Breakpoints:**
```
Mobile:     < 640px    → 1 column
Tablet S:   640-767px  → 2 columns
Tablet:     768-1023px → 3 columns
Desktop S:  1024-1279px → Bento 12-col
Desktop:    >= 1280px   → Bento full
```

**Performance Targets (Core Web Vitals):**
- LCP (Largest Contentful Paint): < 2.5s
- INP (Interaction to Next Paint): < 200ms
- CLS (Cumulative Layout Shift): < 0.1
- Lighthouse Mobile Score: >= 90

### Frontend Design Skill

**IMPORTANT**: When working on **public frontend components** (homepage, article pages, category pages, archive pages, etc.), agents MUST use the `frontend-design` skill:

```
Use Skill: frontend-design:frontend-design
```

This skill provides:
- Production-grade frontend interfaces with high design quality
- Creative, polished code that avoids generic AI aesthetics
- Adherence to the Bento Grid layout system
- Proper typography and color implementation
- Core Web Vitals optimization
- Telegram Instant View compatibility

**When to use frontend-design skill:**
- Building new public-facing pages
- Creating reusable UI components (cards, navigation, hero sections)
- Implementing responsive layouts
- Styling article content display
- Adding visual polish and micro-interactions

**When NOT to use:**
- Admin panel development (internal tools)
- Backend API work
- Configuration files
- Test files

## Current Development Status (Sprint 45, April 2026)

✅ **Core Platform — Complete:**
- Symfony 8.0 headless API + Next.js 16 frontend + admin
- PostgreSQL 18.2, Redis 8.0 (`volatile-lru`), Elasticsearch 9.3 (HTTPS)
- JWT auth, Mercure real-time, multi-tier caching (APCu L1, Redis L2, Next.js ISR L3)
- Gedmo Translatable trilingual (ro/en/ru) with strict mode
- API Platform state providers/processors with eager loading
- Image upload + async thumbnail generation (10 profiles)
- Article locking, rate limiting, CSP headers

✅ **Content Pipeline — Complete:**
- 82 aggregator sources (RSS, scraping, Google News/Alerts, NewsAPI, Bing, Telegram, Facebook, Guardian, ANSA, etc.)
- 10 Moldova-specific scrapers (NewsMaker, TV8, Zugo, Moldova1, Noi.md, Stiri.md, Point.md, UNIMEDIA, Jurnal.md, ZDG.md)
- `PressRelease` as universal gateway → editorial approval → `Article`
- Content cleaning pipeline (6 source-specific cleaners, 70-88% noise removal)
- URL deduplication at import + content hash dedup

✅ **AI Pipeline — Complete:**
- Dual-LLM: Gemini CLI (context crunch/bulk) + Claude CLI (journalistic polish)
- `AiProviderInterface` + `AiProviderRegistry` for provider switching
- Article translation (EN+RU via Gemini, one call per locale)
- Topic detection + batch classification
- Cluster summaries + semantic verification (30-min cron)
- Background generation (3 blocks: Cronologic/Explicativ/Moldova)
- AI article generation (`ArticleWriterService`, content-depth gate)
- Auto-publish gate (confidence≥0.85, sources≥3, words≥300, non-sensitive)
- `SensitiveTopicDetector` (politics/Transnistria/Gagauzia/persons)

✅ **Clustering & Editorial Intelligence — Complete:**
- `StoryCluster` with importance scoring + `scoreBreakdown` JSON
- `SemanticClusterVerifier` (Gemini/Claude gate, fail-open)
- `RelatedArticlesFinder` (ES MLT on own articles)
- Cluster snapshots + diff tracking
- Remove-PR-from-cluster API + UI
- ES tuning: stop words RO/EN/RU, title^3.0 boost, min_score 0.60

⬜ **In Progress / Next:**
- Sprint 45 executing (Editorial Context Intelligence)
- Fix Sprint 40 QA bugs (background-proposals 404 + TinyMCE scroll trap)
- Social Media Distribution (Facebook + Telegram) — architecture ready, implementation deferred
- Production deployment to DigitalOcean FRA1 + Cloudflare CDN
- Platform branding (top candidate: **Acta**, pending nic.md domain check)
