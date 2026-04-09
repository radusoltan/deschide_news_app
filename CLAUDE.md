# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 🏗️ Repository Structure: MONOREPO

**IMPORTANT**: This is a monorepo containing both backend and frontend applications.

## Project Overview

**Deschide News App** - A multilanguage news platform with:
- **Backend**: Symfony 8.0 (PHP 8.5) - RESTful API
- **Frontend**: Next.js 16.2 (React 19.2 / TypeScript) - Web interface
- **Languages**: Romanian (ro), English (en), Russian (ru)
- **Architecture**: Monorepo with unified version control

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
│   ├── design_principles_and_features.md  # Full design guide
│   ├── DESIGN_QUICK_REFERENCE.md          # Quick reference
│   └── reseach_result.md                  # Research data
├── docs/                     # Centralized documentation
│   ├── GIT_MONOREPO_MIGRATION_PLAN.md  # Monorepo migration
│   └── ...
├── sprints/                  # Sprint planning
├── scripts/                  # Deployment scripts
├── archive/                  # Historical backups
│   └── monorepo_migration/   # Old directories
├── .gitignore                # Root gitignore
├── CLAUDE.md                 # This file
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

**Message Queue (RabbitMQ):**
```bash
# Note: Messenger transports are currently commented out in config/packages/messenger.yaml
# To enable async processing:
# 1. Uncomment transport configuration in messenger.yaml
# 2. Configure routing for your message classes
# 3. Then run workers:

# Start workers (async processing)
symfony console messenger:consume async -vv

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

# Run PHPStan (level 8)
vendor/bin/phpstan analyse

# Code style (PHP-CS-Fixer)
vendor/bin/php-cs-fixer fix

# Architecture validation
vendor/bin/deptrac analyse
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

# Start dev server on port 3005
pnpm dev

# Or use PM2 (persistent)
pm2 start ecosystem.config.js
pm2 logs deschide_frontend
pm2 stop deschide_frontend
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

# Terminal 3 - Start Workers (optional, for async tasks)
cd /var/www/deschide_news_app/apps/backend
symfony console messenger:consume async -vv
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
- **Database**: PostgreSQL 18 via Doctrine ORM 3.5
- **Multilanguage**: Gedmo Translatable (strict mode with HINT_INNER_JOIN)
- **Message Queue**: RabbitMQ via Symfony Messenger
- **Cache**: Redis (DB 1, prefix: `deschide_news:*`)
- **Search**: Elasticsearch (index: `deschide_articles`)
- **Real-time**: Mercure Hub (topics: `deschide_news/*`)

**Directory Structure:**
- `src/Entity/` - Doctrine entities (Article, Category, Author, Image, etc.)
- `src/State/` - API Platform State Providers and Processors (locale-aware queries)
- `src/Controller/` - API controllers (for custom endpoints)
- `src/Service/` - Business logic (ImageService, ElasticService, etc.)
- `src/Repository/` - Custom queries with Gedmo hints
- `src/Message/` - Message classes for async processing
- `src/MessageHandler/` - Message handlers
- `src/Command/` - Console commands (Import/, Test/, Elasticsearch, etc.)
- `src/Dto/` - Data Transfer Objects
- `src/Enum/` - PHP Enums (ArticleStatus, ArticleBadge, etc.)
- `src/EventListener/` - Doctrine event listeners
- `src/EventSubscriber/` - Symfony event subscribers
- `src/Transformer/` - Data transformers
- `src/Validator/` - Custom validators
- `src/DataFixtures/` - Database fixtures for testing
- `config/` - YAML configuration files

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
| **Test Articles** | `/api/test_articles` | Test endpoint for development |

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

### Frontend (Next.js 16.2)

**Location**: `/var/www/deschide_news_app/deschide_frontend`

- **Router**: App Router (`app/` directory)
- **Bundler**: Turbopack (default in Next.js 16.2)
- **Styling**: Tailwind CSS 4
- **API Integration**: Fetch to Symfony backend
- **Multilanguage**: To be configured (custom library)
- **Real-time**: Mercure SSE subscription
- **State Management**: To be determined

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

- **PHPUnit**: ✅ **376 tests, 1,351 assertions - ALL PASSING**
- **Test Suites**:
  - Entity tests (Article, Author, Category, Image, Tag, User)
  - API integration tests (CRUD operations, authentication)
  - Service tests (Image processing, Elasticsearch, translations)
  - State Provider tests (API Platform providers)
- **Run Tests**:
  ```bash
  cd /var/www/deschide_news_app/apps/backend
  XDEBUG_MODE=off vendor/bin/phpunit tests/ --no-coverage
  ```
- **Test Commands**: Development test commands available in `src/Command/Test/`:
  - `app:test:jwt-token` - Test JWT token generation
  - `app:test:newscoop-connection` - Test Newscoop API connection
  - `app:test:migration-logger` - Test migration logger functionality
- **Static Analysis**: PHPStan not yet configured (planned for level 8)
- **Code Style**: PHP-CS-Fixer not yet configured (planned)
- **Architecture**: Deptrac not yet configured (planned for layer validation)

### Frontend Testing

- **Jest**: ✅ **163 tests - ALL PASSING**
- **Test Suites**:
  - Component tests (SafeHtml, ArticleBody, StructuredData)
  - Integration tests (API integration, navigation)
  - Utility tests (sanitization, validation)
- **Run Tests**:
  ```bash
  cd /var/www/deschide_news_app/apps/frontend
  pnpm test
  ```
- **Playwright**: ⚡ **1,176 E2E tests discovered and ready**
- **Run E2E Tests**:
  ```bash
  cd /var/www/deschide_news_app/apps/frontend
  pnpm test:e2e:ui    # UI mode
  pnpm test:e2e       # Headless
  ```

### Test Status

📊 **Latest Report**: `docs/reports/TEST_STATUS_REPORT.md` (2025-11-30)
- Backend: 376 tests ✅
- Frontend Jest: 163 tests ✅
- Frontend Playwright: 1,176 tests discovered ⚡
- **Total: 539 tests passing (100% success rate)**

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

## Current Development Status

✅ **Completed:**
- Development environment configured
- Backend installed (Symfony 8.0, PHP 8.5)
- Frontend installed (Next.js 16.2, React 19.2)
- Both applications running on dedicated ports (8081, 3005)
- Port allocation documented (no conflicts)
- Environment variables configured

✅ **Also Completed:**
- StofDoctrineExtensionsBundle (Gedmo) configured
- NelmioCors configuration updated
- PostgreSQL database created and populated
- Base entities created (User, Author, Category, Article, Image, Thumbnail, ThumbnailProfile, ArticleImage, ArticleLock, ImportantArticlesList, RefreshToken)
- Migrations run successfully
- API Platform state providers/processors implemented
- Elasticsearch integration configured
- Import system from Newscoop CMS implemented (13 commands available)
- Image upload and thumbnail generation system
- Article locking mechanism
- JWT authentication configured
- **Testing infrastructure (PHPUnit + Jest + Playwright)**
- **All tests passing (539 tests, 100% success rate)**
- **Multi-tier caching (APCu L1, Redis L2, Next.js ISR L3)**
- **Rate limiting for API endpoints (general, login, write, image operations)**
- **CSP headers configured for CDN integration**

✅ **Import Status (from Newscoop CMS):**
- **Authors:** 282/282 (100% complete)
- **Categories:** 17/18 (94% complete, 28 available with translations)
- **Images:** 1,788/155,332 (1.2% - WebP conversion, import paused)
- **Articles:** 1,000/173,670 (0.6% - Romanian only)
- **Translations:** 0/290,000 (0% - EN/RU not imported yet)
- **Article-Image Links:** 0 (not imported yet)
- **Infrastructure:** All 13 import commands functional and tested

⬜ **Current Focus:**
- Frontend development and API integration
- Admin panel features
- Full-scale import execution (remaining 172K articles + images + translations)
- Code quality tools (PHPStan, PHP-CS-Fixer)
- Nginx virtual hosts configuration (optional)
- CI/CD pipeline setup
