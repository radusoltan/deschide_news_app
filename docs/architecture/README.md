# Architecture Overview - Deschide News App

**Ultima actualizare**: 5 Noiembrie 2025
**Versiune**: 1.0
**Status**: Production Ready

---

## 📋 Overview

**Deschide News** este o platformă media multilinguală (română, engleză, rusă) construită cu arhitectură modernă separată între backend API și frontend SPA.

### Stack Tehnologic

```
┌─────────────────────────────────────────────────────────┐
│                     FRONTEND                            │
│  Next.js 16 | React 19.2 | TypeScript | TailwindCSS    │
│                   Port: 3005                            │
└─────────────────────┬───────────────────────────────────┘
                      │ HTTP/REST API
                      │ JSON-LD / Hydra
                      ▼
┌─────────────────────────────────────────────────────────┐
│                     BACKEND                             │
│   Symfony 7.3 | PHP 8.4 | API Platform | Doctrine      │
│                   Port: 8081                            │
└───────┬──────┬──────┬──────┬──────┬──────┬─────────────┘
        │      │      │      │      │      │
        ▼      ▼      ▼      ▼      ▼      ▼
     ┌────┐ ┌────┐ ┌────┐ ┌────┐ ┌────┐ ┌────┐
     │PG  │ │Redis│ │ES  │ │RMQ │ │Merc│ │CDN │
     │SQL │ │    │ │    │ │    │ │ure │ │    │
     └────┘ └────┘ └────┘ └────┘ └────┘ └────┘
```

---

## 🏗️ Arhitectură High-Level

### 1. Backend - Symfony API

**Rol**: REST API, business logic, data persistence

**Tehnologii**:
- **Framework**: Symfony 7.3 (PHP 8.4)
- **API**: API Platform 3.x (Hydra/JSON-LD)
- **ORM**: Doctrine ORM 3.5
- **Auth**: JWT (Lexik + Gesdinet Refresh Token)
- **Validation**: Symfony Validator
- **Multilingv**: Gedmo Translatable

**Port**: 8081
**URL**: `http://127.0.0.1:8081`
**API Entrypoint**: `http://127.0.0.1:8081/api`

### 2. Frontend - Next.js SPA

**Rol**: User interface, SSR/SSG, SEO optimization

**Tehnologii**:
- **Framework**: Next.js 16 (App Router)
- **UI Library**: React 19.2
- **Language**: TypeScript
- **Styling**: TailwindCSS 4
- **Data Fetching**: SWR, fetch
- **Editor**: Tiptap (rich text)

**Port**: 3005
**URL**: `http://localhost:3005`

### 3. Servicii Partajate

| Serviciu | Port | Rol | Namespace |
|----------|------|-----|-----------|
| **PostgreSQL** | 5432 | Database principal | `deschide_news` DB |
| **Redis** | 6379 | Cache, sessions | DB 1, prefix `deschide_news:*` |
| **Elasticsearch** | 9200 | Full-text search | Index `deschide_articles` |
| **RabbitMQ** | 5672 | Message queue | Vhost `/` |
| **Mercure** | 3000 | Real-time push | Topic `deschide_news/*` |
| **CDN Server** | 8082 | Static assets | `uploads/` |

---

## 📊 Data Flow Architecture

### Request Flow

```
User Browser
     │
     ▼
┌─────────────────┐
│  Next.js (SSR)  │  ◄── Server-side rendering
│   Port: 3005    │
└────────┬────────┘
         │ API Request
         ▼
┌─────────────────┐
│  Symfony API    │  ◄── Business logic
│   Port: 8081    │
└────┬────┬───┬───┘
     │    │   │
     ▼    ▼   ▼
   [DB][Cache][Search]
```

### Real-time Updates Flow

```
Admin publishes → Symfony → Mercure Hub → SSE → Client browser
                     │
                     └──→ Database
```

---

## 🗄️ Database Architecture

### PostgreSQL Schema

**Database**: `deschide_news`
**User**: `deschide_admin`

#### Core Tables

| Table | Descriere | Rows (est.) |
|-------|-----------|-------------|
| `article` | Articole principale | ~50,000 |
| `article_translation` | Traduceri articole (Gedmo) | ~100,000 |
| `category` | Categorii | ~50 |
| `category_translation` | Traduceri categorii | ~100 |
| `author` | Autori | ~100 |
| `image` | Imagini originale | ~20,000 |
| `thumbnail` | Thumbnail-uri generate | ~200,000 |
| `thumbnail_profile` | Profile thumbnail (10) | 10 |
| `article_image` | Relație articol-imagine | ~50,000 |

#### Feature Tables

| Table | Feature | Descriere |
|-------|---------|-----------|
| `live_text` | Live Text | Transmisiuni live |
| `live_text_post` | Live Text | Posts individuale |
| `live_text_collaborator` | Live Text | Colaboratori |
| `live_text_view` | Analytics | Tracking vizualizări |
| `article_lock` | Concurrency | Prevents concurrent editing |
| `important_articles_list` | Homepage | Featured articles |
| `url_redirect` | SEO | 301/302 redirects |
| `refresh_token` | Auth | JWT refresh tokens |

#### Indexare

```sql
-- Performance critical indexes
CREATE INDEX idx_article_status_published ON article(status, published_at);
CREATE INDEX idx_article_category ON article(category_id);
CREATE INDEX idx_article_translation_locale ON article_translation(locale, object_id);
CREATE INDEX idx_live_text_post_live_text ON live_text_post(live_text_id, published_at);
```

---

## 🔍 Search Architecture (Elasticsearch)

### Indices

```
deschide_articles_ro  ─┐
deschide_articles_en  ─┼─> Multi-language search
deschide_articles_ru  ─┘

deschide_images       ─> Image search
```

### Document Structure (Article)

```json
{
  "id": 1,
  "title": "Article Title",
  "lead": "Article lead text",
  "content": "Full article content...",
  "category": { "id": 5, "name": "Politics" },
  "author": { "id": 2, "name": "John Doe" },
  "published_at": "2025-11-05T10:00:00Z",
  "slug": "article-title",
  "locale": "ro"
}
```

### Indexing Strategy

- **Bulk indexing**: via console commands
- **Real-time updates**: via Doctrine event listeners
- **Reindex**: `symfony console app:elasticsearch:index-articles`

---

## 💾 Caching Strategy (Redis)

### Cache Layers

**Redis DB 1** - Namespace: `deschide_news:*`

| Key Pattern | TTL | Descriere |
|-------------|-----|-----------|
| `article:{id}:ro` | 1h | Article cache per locale |
| `category:tree:ro` | 24h | Category hierarchy |
| `homepage:articles:ro` | 15min | Homepage articles list |
| `live_text:{id}:analytics` | 60s | Analytics data |
| `session:{id}` | 30min | User sessions |
| `rate_limit:{ip}:{endpoint}` | 1min | Rate limiting |

### Cache Invalidation

```php
// Automatic invalidation on entity update
$this->cache->delete('article:' . $article->getId() . ':' . $locale);
$this->cache->deleteMultiple(['homepage:articles:ro', 'homepage:articles:en']);
```

---

## 🔐 Authentication & Authorization

### JWT Flow

```
1. User login → POST /api/login_check
                  ↓
2. Backend validates credentials
                  ↓
3. Generate JWT token (15min) + Refresh token (7 days)
                  ↓
4. Return both tokens to client
                  ↓
5. Client stores tokens (httpOnly cookie for refresh)
                  ↓
6. Subsequent requests → Authorization: Bearer {token}
                  ↓
7. Token expires → Use refresh token → Get new token pair
```

### User Roles

| Role | Permissions | Usage |
|------|-------------|-------|
| `ROLE_USER` | Base access | Registered users |
| `ROLE_EDITOR` | Create/edit articles | Journalists |
| `ROLE_ADMIN` | Full access | Administrators |
| `ROLE_SUPER_ADMIN` | System config | System admins |

### Protected Routes (Frontend)

```typescript
/[locale]/admin/*          → ROLE_EDITOR minimum
/[locale]/admin/users/*    → ROLE_ADMIN
/[locale]/admin/settings/* → ROLE_SUPER_ADMIN
```

---

## 🌍 Multilanguage Architecture

### Strategy: Gedmo Translatable (Strict Mode)

**Default Locale**: `ro` (Romanian)
**Available Locales**: `ro`, `en`, `ru`

### Translation Tables

```sql
article
├── id, status, published_at (non-translatable)
└── article_translation
    ├── locale ('ro', 'en', 'ru')
    ├── field ('title', 'lead', 'content', etc.)
    └── content (translated value)
```

### Query with Locale

```php
$query = $repository->createQueryBuilder('a')
    ->setHint(
        \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
        $locale
    )
    ->getQuery();
```

### Frontend Routing

```
/ro/article/titlu-articol     → Romanian
/en/article/article-title     → English
/ru/article/название-статьи   → Russian (transliterated)
```

---

## 🔄 Async Processing (RabbitMQ)

### Message Types

| Message | Queue | Handler | Priority |
|---------|-------|---------|----------|
| `GenerateThumbnailMessage` | `async` | `GenerateThumbnailHandler` | High |
| `IndexArticleMessage` | `async` | `IndexArticleHandler` | Medium |
| `SendEmailMessage` | `async` | `SendEmailHandler` | Low |
| `PublishScheduledArticleMessage` | `cron` | `PublishScheduledArticleHandler` | Critical |

### Worker Command

```bash
# Start workers (4 instances recommended)
symfony console messenger:consume async -vv --limit=100 &
symfony console messenger:consume async -vv --limit=100 &
symfony console messenger:consume async -vv --limit=100 &
symfony console messenger:consume async -vv --limit=100 &
```

---

## 📡 Real-time Architecture (Mercure)

### Topics

| Topic | Descriere | Subscribers |
|-------|-----------|-------------|
| `deschide_news/live_texts/{id}` | Live Text updates | Public + Admin |
| `deschide_news/breaking` | Breaking news | All users |
| `deschide_news/admin/locks` | Article locks | Admins only |

### Publishing

```php
$this->mercure->publish([
    'topics' => ['deschide_news/live_texts/' . $liveTextId],
    'data' => json_encode($post)
]);
```

### Subscribing (Frontend)

```typescript
const eventSource = new EventSource(
  `${MERCURE_URL}?topic=deschide_news/live_texts/${id}`
);

eventSource.onmessage = (event) => {
  const post = JSON.parse(event.data);
  updateUI(post);
};
```

---

## 📁 File Storage Architecture

### Directory Structure

```
deschide_backend/public/uploads/
├── images/              ← Original images (VichUploader)
│   └── image_*.{jpg,png,webp}
└── thumbnails/          ← Generated thumbnails
    ├── hero_big/        ← 1920x1080 WebP
    ├── hero_small/      ← 800x600 WebP
    ├── article_main/    ← 1600x900 WebP
    ├── article_inline/  ← 1200x675 WebP
    ├── card_large/      ← 800x600 WebP
    ├── card_medium/     ← 600x400 WebP
    ├── card_small/      ← 400x300 WebP
    ├── list_item/       ← 300x200 WebP
    ├── mobile_hero/     ← 800x600 WebP
    └── gallery/         ← 1920x600 WebP
```

### CDN Integration

```
Development:  http://127.0.0.1:8082/uploads/{path}
Production:   https://cdn.deschide.md/uploads/{path}
```

### Image Processing Flow

```
Upload → Store Original → Dispatch Message → Generate 10 Thumbnails → Store
```

---

## 🎯 API Architecture (API Platform)

### State Provider/Processor Pattern

```php
// Provider (GET operations)
src/State/ArticleProvider.php
├── Applies locale hint
├── Eager loads relations (prevent N+1)
├── Applies filters
└── Returns paginated results

// Processor (POST/PUT/DELETE operations)
src/State/ArticleProcessor.php
├── Validates input
├── Persists to database
├── Dispatches messages (thumbnails, indexing)
└── Returns result
```

### API Response Format (JSON-LD)

```json
{
  "@context": "/api/contexts/Article",
  "@id": "/api/articles/1",
  "@type": "Article",
  "id": 1,
  "title": "Article Title",
  "slug": "article-title",
  "category": {
    "@id": "/api/categories/5",
    "@type": "Category",
    "id": 5,
    "name": "Politics"
  },
  "articleImages": [
    {
      "@id": "/api/article_images/109",
      "image": {
        "@id": "/api/images/50",
        "filename": "image.png",
        "path": "images/image.png"
      }
    }
  ]
}
```

---

## 📊 Performance Optimizations

### Backend

1. **Eager Loading**: Prevent N+1 queries
   ```php
   $qb->leftJoin('a.category', 'c')->addSelect('c')
       ->leftJoin('a.articleImages', 'ai')->addSelect('ai')
       ->leftJoin('ai.image', 'img')->addSelect('img');
   ```

2. **Redis Cache**: API responses, computed data
3. **Doctrine Query Cache**: Reuse compiled queries
4. **OpCache**: PHP bytecode caching
5. **Connection Pooling**: PgBouncer (optional)

### Frontend

1. **Server-Side Rendering**: SEO și performance
2. **Static Generation**: Pagini statice unde e posibil
3. **Image Optimization**: Next.js Image component
4. **Code Splitting**: Bundle optimization
5. **CDN**: Static assets servite de CDN

---

## 📈 Monitoring & Observability

### Metrics (Prometheus)

```
# Backend metrics
symfony_requests_total
symfony_request_duration_seconds
doctrine_query_count
cache_hit_rate

# Business metrics
articles_published_total
live_text_active_sessions
api_rate_limit_exceeded
```

### Logging

```
Backend:  var/log/{env}.log
Frontend: stdout/PM2 logs
Nginx:    /var/log/nginx/access.log
```

### Health Checks

```bash
# Backend
GET /health
→ { "status": "ok", "database": "ok", "redis": "ok" }

# Frontend
GET /api/health
→ { "status": "ok" }
```

---

## 🔐 Security Measures

### Backend

- ✅ JWT authentication cu refresh tokens
- ✅ CORS configuration (NelmioCorsBundle)
- ✅ Rate limiting per IP/endpoint
- ✅ Input validation (Symfony Validator)
- ✅ SQL injection protection (Doctrine ORM)
- ✅ XSS protection (output escaping)
- ✅ CSRF tokens (for forms)
- ✅ Password hashing (Argon2id)

### Frontend

- ✅ HttpOnly cookies pentru refresh tokens
- ✅ CSP headers
- ✅ Input sanitization
- ✅ XSS prevention (React auto-escape)
- ✅ API request validation

---

## 🚀 Deployment Architecture

### Development

```
Local Machine
├── Backend:  symfony serve (port 8081)
├── Frontend: pnpm dev (port 3005)
└── Services: local (PostgreSQL, Redis, etc.)
```

### Production (Recommended)

```
Server(s)
├── Nginx (reverse proxy)
│   ├── api.deschide.md → Backend (PHP-FPM)
│   └── deschide.md → Frontend (Node PM2)
├── PostgreSQL 17 (dedicated server optional)
├── Redis (standalone/cluster)
├── Elasticsearch (cluster recommended)
└── CDN (Cloudflare / custom)
```

---

## 🔗 Documentație Extinsă

### Backend
- **Architecture Details**: [deschide_backend/docs/architecture/](./deschide_backend/docs/architecture/)
- **Services**: [deschide_backend/docs/services/](./deschide_backend/docs/services/)
- **API Docs**: http://127.0.0.1:8081/api/docs.jsonld

### Frontend
- **Features**: [deschide_frontend/docs/features/](./deschide_frontend/docs/features/)
- **Routing**: [deschide_frontend/docs/routing/](./deschide_frontend/docs/routing/)
- **Setup**: [deschide_frontend/docs/setup/](./deschide_frontend/docs/setup/)

### Infrastructure
- **Redis Schema**: [docs/infrastructure/redis-schema.md](./docs/infrastructure/redis-schema.md)
- **Cron Setup**: [docs/infrastructure/cron-setup.md](./docs/infrastructure/cron-setup.md)
- **Monitoring**: [docs/infrastructure/monitoring-guide.md](./docs/infrastructure/monitoring-guide.md)

### Features
- **Live Text**: [docs/features/live-text/](./docs/features/live-text/)
- **Performance Analytics**: [docs/features/performance-analytics/](./docs/features/performance-analytics/)

---

## 📝 Decizie Arhitecturale Importante

### De ce Arhitectură Separată (Backend/Frontend)?

1. **Scalabilitate**: Backend și frontend se pot scala independent
2. **Tehnologie**: Best tools pentru fiecare (Symfony pentru API, Next.js pentru UI)
3. **Echipă**: Backend PHP devs + Frontend React devs pot lucra parallel
4. **Deployment**: Deploy independent, zero downtime
5. **Mobile**: Backend API poate fi refolosit pentru mobile apps

### De ce API Platform?

1. **Rapid development**: CRUD automatic
2. **Standards**: Hydra/JSON-LD out of the box
3. **Flexibility**: Custom providers/processors
4. **Documentation**: Auto-generated API docs

### De ce Gedmo Translatable?

1. **Proven solution**: Battle-tested în production
2. **Strict mode**: Prevents fallback issues
3. **Query hints**: Full control over locale
4. **Separate tables**: Clean schema

---

**Ultima actualizare**: 5 Noiembrie 2025
**Versiune**: 1.0
