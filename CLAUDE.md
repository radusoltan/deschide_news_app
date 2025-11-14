# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 🏗️ Repository Structure: MONOREPO

**IMPORTANT**: This is a monorepo containing both backend and frontend applications.

## Project Overview

**Deschide News App** - A multilanguage news platform with:
- **Backend**: Symfony 7.3 (PHP 8.4) - RESTful API
- **Frontend**: Next.js 16 (React 19.2 / TypeScript) - Web interface
- **Languages**: Romanian (ro), English (en), Russian (ru)
- **Architecture**: Monorepo with unified version control

## Project Structure

```
/var/www/deschide_news_app/    # ROOT MONOREPO
├── .git/                      # Unified git repository
├── apps/
│   ├── backend/              # Symfony 7.3 API (PHP 8.4)
│   │   ├── config/           # Configuration files
│   │   ├── public/           # Web root (index.php)
│   │   ├── src/              # Application code
│   │   ├── migrations/       # Database migrations
│   │   ├── docs/             # Backend documentation
│   │   ├── composer.json     # PHP dependencies
│   │   └── README.md         # Backend README
│   └── frontend/             # Next.js 16 (React 19.2, TypeScript)
│       ├── app/              # App Router pages
│       ├── components/       # React components
│       ├── lib/              # Utilities
│       ├── docs/             # Frontend documentation
│       ├── package.json      # Node dependencies
│       └── README.md         # Frontend README
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

# Quick sample import (6 categories, 300 articles with translations for testing)
symfony console app:sample-import
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

### Backend (Symfony 7.3)

**Location**: `/var/www/deschide_news_app/apps/backend`

- **API Structure**: RESTful JSON API with Hydra/JSON-LD support
- **API Documentation**: `http://127.0.0.1:8081/api/docs.jsonld` (Hydra documentation)
- **API Entrypoint**: `http://127.0.0.1:8081/api`
- **Authentication**: JWT tokens (Lexik JWT + Gesdinet Refresh Token)
- **Database**: PostgreSQL 17 via Doctrine ORM 3.5
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

### Frontend (Next.js 16)

**Location**: `/var/www/deschide_news_app/deschide_frontend`

- **Router**: App Router (`app/` directory)
- **Bundler**: Turbopack (default in Next.js 16)
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
git commit -m "chore(backend): upgrade Symfony to 7.3.1"
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

- **Backend**: PHPUnit tests not yet configured (planned)
- **Test Commands**: Development test commands available in `src/Command/Test/`:
  - `app:test:jwt-token` - Test JWT token generation
  - `app:test:newscoop-connection` - Test Newscoop API connection
  - `app:test:migration-logger` - Test migration logger functionality
- **Frontend**: To be configured
- **Static Analysis**: PHPStan not yet configured (planned for level 8)
- **Code Style**: PHP-CS-Fixer not yet configured (planned)
- **Architecture**: Deptrac not yet configured (planned for layer validation)

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

## Current Development Status

✅ **Completed:**
- Development environment configured
- Backend installed (Symfony 7.3, PHP 8.4)
- Frontend installed (Next.js 16, React 19.2)
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
- Import system from Newscoop CMS implemented
- Image upload and thumbnail generation system
- Article locking mechanism
- JWT authentication configured

⬜ **Current Focus:**
- Frontend development and API integration
- Admin panel features
- Testing infrastructure (PHPUnit, PHPStan, PHP-CS-Fixer)
- Nginx virtual hosts configuration (optional)
