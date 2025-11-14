# CLAUDE.md - Deschide News Backend

**Backend API** pentru platforma de știri Deschide News - Symfony 7.3 (PHP 8.4)

---

## 📋 Project Overview

**Deschide News Backend** este un RESTful API construit cu:
- **Framework**: Symfony 7.3
- **PHP Version**: 8.4
- **API Platform**: 3.x (JSON-LD/Hydra)
- **Database**: PostgreSQL 17
- **ORM**: Doctrine ORM 3.5
- **Authentication**: JWT (Lexik JWT + Gesdinet Refresh Token)
- **Search**: Elasticsearch 8.x
- **Cache**: Redis (DB 1)
- **Message Queue**: RabbitMQ via Symfony Messenger
- **Real-time**: Mercure Hub

---

## 📁 Directory Structure

```
deschide_backend/
├── config/                      # Configuration YAML files
│   ├── packages/               # Bundle configurations
│   ├── routes/                 # Route definitions
│   └── jwt/                    # JWT keys (private.pem, public.pem)
├── migrations/                  # Doctrine migrations
├── public/                      # Web root
│   ├── index.php              # Entry point
│   └── uploads/               # Uploaded files (images, etc.)
├── src/
│   ├── Command/               # Console commands
│   │   ├── Elasticsearch/     # ES commands (index, create-index)
│   │   ├── Import/            # Import commands (from Newscoop)
│   │   ├── LiveText/          # LiveText commands
│   │   └── ...                # Other commands
│   ├── Controller/            # API controllers
│   ├── Dto/                   # Data Transfer Objects
│   ├── Entity/                # Doctrine entities
│   ├── Enum/                  # PHP Enums
│   ├── EventListener/         # Doctrine event listeners
│   ├── EventSubscriber/       # Symfony event subscribers
│   ├── Message/               # Messenger message classes
│   ├── MessageHandler/        # Messenger handlers
│   ├── Repository/            # Custom repositories
│   ├── Service/               # Business logic services
│   ├── State/                 # API Platform State Providers/Processors
│   ├── Transformer/           # Data transformers
│   └── Validator/             # Custom validators
├── tests/                      # PHPUnit tests
│   ├── Unit/                  # Unit tests
│   ├── Integration/           # Integration tests
│   └── Functional/            # Functional/API tests
├── var/                        # Cache, logs (not in git)
├── vendor/                     # Composer dependencies (not in git)
├── dev-tools/                  # Archived test code (not in git)
│   ├── test-commands/         # Test commands (archived)
│   └── test-entities/         # Test entities (archived)
├── .env.example               # Environment template (committed)
├── .env.local                 # Local overrides (NOT committed)
├── .env.test                  # Test environment (committed)
├── composer.json              # PHP dependencies
└── CLAUDE.md                  # This file
```

---

## 🔐 Environment Configuration

### Files Overview

| File | Status | Purpose |
|------|--------|---------|
| `.env.example` | ✅ Committed | Template with placeholder values |
| `.env.local` | ❌ NOT committed | Local development configuration |
| `.env.test` | ✅ Committed | Test environment configuration |
| `.env` | ❌ Deleted | Removed (redundant, was in .gitignore) |
| `.env.dev` | ❌ Deleted | Removed (redundant) |

### Setup Instructions

**First time setup:**
```bash
# Copy the example file to create your local configuration
cp .env.example .env.local

# Edit .env.local with your actual values
nano .env.local
```

**Important**: Never commit `.env.local` to git! It contains sensitive credentials.

### Required Environment Variables

#### Database
```bash
# Main PostgreSQL database
DATABASE_URL="postgresql://deschide_admin:YOUR_PASSWORD@127.0.0.1:5432/deschide?serverVersion=16&charset=utf8"

# Newscoop MySQL database (for data import)
NEWSCOOP_DATABASE_URL="mysql://root:YOUR_PASSWORD@127.0.0.1:3306/newscoop?serverVersion=10.5&charset=utf8mb4"
```

#### JWT Authentication
```bash
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=YOUR_SECURE_PASSPHRASE_HERE
```

**Generate JWT keys:**
```bash
symfony console lexik:jwt:generate-keypair
```

#### Elasticsearch
```bash
ELASTICSEARCH_HOST="https://localhost:9200"
ELASTICSEARCH_USER=elastic
ELASTICSEARCH_PASSWORD=YOUR_ES_PASSWORD
ELASTICSEARCH_API_KEY=YOUR_ES_API_KEY_BASE64
ELASTICSEARCH_VERIFY_SSL=0
```

#### Mercure (Real-time)
```bash
MERCURE_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET=!ChangeThisMercureHubJWTSecretKey!
```

#### Other Services
```bash
# CORS configuration
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'

# Messenger transport
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

# Webflow API (if needed)
WEBFLOW_API_KEY=your_webflow_api_key_here
```

---

## 🚀 Common Commands

### Development Server

```bash
# Start Symfony development server
symfony serve -d --port=8081

# Check server status
symfony server:status

# View logs
symfony server:log

# Stop server
symfony server:stop
```

**Access**: http://127.0.0.1:8081

### Database Management

```bash
# Create database
symfony console doctrine:database:create

# Run migrations
symfony console doctrine:migrations:migrate

# Create migration after entity changes
symfony console make:migration

# Validate schema
symfony console doctrine:schema:validate

# Show migration status
symfony console doctrine:migrations:status
```

### Code Generation

```bash
# Create new entity
symfony console make:entity

# Create controller
symfony console make:controller

# Create command
symfony console make:command
```

### Elasticsearch

```bash
# Create indices for all locales (ro, en, ru)
symfony console app:elasticsearch:create-index
symfony console app:elasticsearch:create-image-index

# Index existing content
symfony console app:elasticsearch:index-articles
symfony console app:elasticsearch:index-images
```

### Import from Newscoop CMS

```bash
# Import categories
symfony console app:import:categories

# Import authors
symfony console app:import:authors

# Import articles with relations
symfony console app:import:articles-with-relations

# Import images
symfony console app:import:images

# Generate thumbnails
symfony console app:import:generate-thumbnails

# Import translations (ru, en)
symfony console app:import:translations
```

### Maintenance

```bash
# Clear cache
symfony console cache:clear

# Warm cache
symfony console cache:warmup

# Cleanup expired article locks
symfony console app:cleanup-expired-locks

# Publish scheduled articles
symfony console app:publish-scheduled-articles

# Redirect management
symfony console app:redirects:cleanup
symfony console app:redirects:consolidate
```

### Testing

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit tests/Unit
vendor/bin/phpunit tests/Functional

# Run with coverage
vendor/bin/phpunit --coverage-html var/coverage
```

---

## 🌐 API Documentation

### Access Points

- **API Entrypoint**: http://127.0.0.1:8081/api
- **Hydra Documentation**: http://127.0.0.1:8081/api/docs.jsonld
- **OpenAPI/Swagger**: http://127.0.0.1:8081/api/docs (if configured)

### Main Resources

| Resource | Endpoint | Description |
|----------|----------|-------------|
| Articles | `/api/articles` | News articles (translatable) |
| Categories | `/api/categories` | Article categories |
| Authors | `/api/authors` | Article authors |
| Images | `/api/images` | Uploaded images |
| Thumbnails | `/api/thumbnails` | Generated thumbnails |
| Thumbnail Profiles | `/api/thumbnail_profiles` | Thumbnail variants (10 profiles) |
| Article Images | `/api/article_images` | Article-Image associations |
| Important Articles | `/api/important_articles_lists` | Featured articles lists |

### Testing Endpoints

```bash
# Get all articles (Romanian - default)
curl -H "Accept-Language: ro" http://127.0.0.1:8081/api/articles

# Get articles in English
curl -H "Accept-Language: en" http://127.0.0.1:8081/api/articles

# Get single article
curl http://127.0.0.1:8081/api/articles/1

# Filter by category
curl "http://127.0.0.1:8081/api/articles?category=5"

# Pagination
curl "http://127.0.0.1:8081/api/articles?page=2&itemsPerPage=10"

# Get API documentation
curl http://127.0.0.1:8081/api/docs.jsonld | jq '.supportedClass[] | {"@id", "title"}'
```

---

## 🏗️ Architecture Patterns

### API Platform State Pattern

**Providers** (data retrieval):
```
src/State/*Provider.php
```
- Handle GET operations
- Apply locale-aware queries with Gedmo HINT_TRANSLATABLE_LOCALE
- Implement eager loading to prevent N+1 queries

**Processors** (data mutations):
```
src/State/*Processor.php
```
- Handle POST, PUT, PATCH, DELETE operations
- Validate and transform data
- Trigger events and side effects

### Multilanguage (Gedmo Translatable)

- **Strict mode** with `HINT_INNER_JOIN`
- **Locales**: ro (default), en, ru
- Translations stored in separate `*_translations` tables
- Locale set via `Accept-Language` header or `?locale=ro` param

Example in Provider:
```php
$query = $repository->createQueryBuilder('a')
    ->setHint(\Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE, $locale)
    ->getQuery();
```

### Eager Loading Pattern

Prevent N+1 queries with `leftJoin` + `addSelect`:

```php
$qb = $repository->createQueryBuilder('a')
    ->leftJoin('a.category', 'c')
    ->addSelect('c')
    ->leftJoin('a.author', 'au')
    ->addSelect('au')
    ->leftJoin('a.articleImages', 'ai')
    ->addSelect('ai')
    ->leftJoin('ai.image', 'img')
    ->addSelect('img');
```

### Serialization Groups

API responses use groups to control exposure:
- `article:read` - GET operations
- `article:write` - POST/PUT operations
- `article:detail` - Detailed view with relations
- `MaxDepth(2)` - Prevent circular references

---

## 🗄️ Key Entities

### Core Entities

**Article**
- Translatable fields: title, lead, content, metaTitle, metaDescription
- Status: draft, published, scheduled, archived
- Badge: breaking, exclusive, analysis, opinion, video, photo_gallery
- Relationships: Category (ManyToOne), Author (ManyToOne), ArticleImages (OneToMany)

**Category**
- Translatable: name, description
- Hierarchical (parent-child)
- Slug generation with transliteration

**Author**
- name, slug, bio, email, avatar
- Relationships: Articles (OneToMany)

**Image**
- Original images with metadata
- VichUploader integration
- Automatic thumbnail generation (10 profiles)

**Thumbnail**
- Generated variants (WebP format)
- Profiles: hero_big, hero_small, article_main, card_large, etc.

### Special Entities

**ArticleLock**
- Prevents concurrent editing
- Auto-cleanup with `app:cleanup-expired-locks`

**ImportantArticlesList**
- Featured/important articles management
- Position-based ordering

**UrlRedirect**
- Manages 301/302 redirects
- Slug changes tracking

---

## 📝 Recent Changes (Sprint 1 - Optimization)

### Ziua 1-2: Eliminare Comenzi de Test ✅
- **15 comenzi de test** mutate în `dev-tools/test-commands/`
- **3,166 linii** de cod eliminate din producție
- Directorul `src/Command/Test/` șters

### Ziua 3: Eliminare Entități de Test ✅
- **5 fișiere** mutate în `dev-tools/test-entities/`
- **373 linii** de cod eliminate
- Entități TestArticle și TestArticleTranslation eliminate
- Tabele database `test_articles` și `test_article_translations` șterse
- Migrație creată: `Version20251104164742.php`

### Ziua 4: Curățare Fișiere .env ✅
- Fișierul `.env.dev` șters (redundant)
- Fișierul `.env.example` actualizat cu toate variabilele necesare
- Git history verificat: fără secrets expuse
- Documentație actualizată pentru configurare corectă

**Total Sprint 1 (Ziua 1-4)**:
- ✅ 20 fișiere eliminate din producție
- ✅ 3,539 linii de cod curățate
- ✅ 2 tabele database eliminate
- ✅ Configurare .env optimizată

---

## 🧪 Testing Strategy

### Directory Structure
```
tests/
├── Unit/              # Unit tests (entities, services)
├── Integration/       # Integration tests (repositories, DB)
└── Functional/        # API/HTTP tests
```

### Running Tests

```bash
# All tests
vendor/bin/phpunit

# Specific suite
vendor/bin/phpunit tests/Unit

# With coverage
vendor/bin/phpunit --coverage-html var/coverage
```

### Existing Tests
- `tests/Validator/ReservedSlugValidatorTest.php` - Slug validation
- `tests/Service/SlugLookupServiceTest.php` - Slug lookup (23 tests)

---

## 🔍 Code Quality Tools

### Static Analysis (Planned)
```bash
# PHPStan (level 8)
vendor/bin/phpstan analyse
```

### Code Style (Planned)
```bash
# PHP-CS-Fixer (PER standard)
vendor/bin/php-cs-fixer fix
```

### Architecture Validation (Planned)
```bash
# Deptrac
vendor/bin/deptrac analyse
```

---

## 🚨 Security Notes

### Secrets Management

**❌ NEVER commit**:
- `.env.local` (contains real credentials)
- `config/jwt/*.pem` (JWT keys)
- Any file with real passwords, API keys, tokens

**✅ Safe to commit**:
- `.env.example` (placeholder values only)
- `.env.test` (test environment)
- `.gitignore` (should exclude sensitive files)

### Password/Key Rotation

If secrets were accidentally committed:
1. Remove from git history: `git filter-branch` or BFG Repo-Cleaner
2. **Regenerate all exposed credentials immediately**:
   - Database passwords
   - JWT passphrase (`lexik:jwt:generate-keypair`)
   - Elasticsearch password
   - API keys (Webflow, etc.)
3. Update `.env.local` with new credentials

---

## 📚 Additional Documentation

- **Main Project CLAUDE.md**: `/var/www/deschide_news_app/CLAUDE.md`
- **Development Environment**: `/var/www/deschide_news_app/DEVELOPMENT_ENVIRONMENT.md`
- **Infrastructure**: `/var/www/deschide_news_app/APPLICATIONS_ARCHITECTURE.md`
- **Optimization Plan**: `OPTIMIZATION_PLAN.md`
- **Sprint Reports**: `SPRINT_1_DAY_*_REPORT.md`

---

## 🐛 Common Issues

### "Database connection failed"
- Check `.env.local` has correct `DATABASE_URL`
- Verify PostgreSQL is running: `systemctl status postgresql`
- Test connection: `psql -h localhost -U deschide_admin -d deschide`

### "JWT keys not found"
```bash
symfony console lexik:jwt:generate-keypair
```

### "Elasticsearch connection failed"
- Check `ELASTICSEARCH_HOST` in `.env.local`
- Verify ES is running: `curl -k https://localhost:9200`
- Check credentials: `ELASTICSEARCH_USER` and `ELASTICSEARCH_PASSWORD`

### "Migrations failed"
```bash
# Check migration status
symfony console doctrine:migrations:status

# Rollback last migration
symfony console doctrine:migrations:execute --down "DoctrineMigrations\VersionXXXXXX"
```

---

## 📞 Development Workflow

1. **Pull latest code**: `git pull`
2. **Install dependencies**: `composer install`
3. **Setup environment**: `cp .env.example .env.local` (first time)
4. **Run migrations**: `symfony console doctrine:migrations:migrate`
5. **Start server**: `symfony serve -d --port=8081`
6. **Run tests**: `vendor/bin/phpunit`
7. **Make changes**: Edit code in `src/`
8. **Test changes**: Use curl or Postman to test API
9. **Commit**: `git add . && git commit -m "message"`

---

**Last Updated**: 4 Noiembrie 2025
**Status**: ✅ Production-ready (after Sprint 1 optimization)
**PHP Version**: 8.4
**Symfony Version**: 7.3
