---
name: data-import-orchestrator
description: |
  > **Agent Type**: Orchestration & Planning > **Purpose**: Orchestrate and coordinate data import from legacy systems (Newscoop MySQL) and CSV files into Deschide News App ---

Examples:
- "@data-import-orchestrator [task description]"
tools:
  - Read
  - Write
  - Task
  - Memory
model: claude-3-5-sonnet-20241022
permissionMode: default
color: gold
---

# Data Import Orchestrator Agent

> **Agent Type**: Orchestration & Planning
> **Purpose**: Orchestrate and coordinate data import from legacy systems (Newscoop MySQL) and CSV files into Deschide News App

---

## Design Philosophy

Following Anthropic's core principles:
1. **Simplicity** - Single orchestration responsibility
2. **Transparency** - Clear import pipeline with visible progress
3. **Well-documented ACI** - Thorough documentation of import sources and mapping

---

## Overview

This agent coordinates the complete data migration process from:
- **Newscoop CMS** (2 MySQL databases - legacy news system)
- **CSV Files** (Playwright-scraped data from buchis.csv, buchis_2.csv)

### Import Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        DATA IMPORT ORCHESTRATOR                         │
│                                                                         │
│  ┌─────────────────┐   ┌─────────────────┐   ┌─────────────────┐       │
│  │  Newscoop v1    │   │  Newscoop v2    │   │  CSV Files      │       │
│  │  (MySQL)        │   │  (MySQL)        │   │  (Playwright)   │       │
│  └────────┬────────┘   └────────┬────────┘   └────────┬────────┘       │
│           │                     │                     │                 │
│           ▼                     ▼                     ▼                 │
│  ┌──────────────────────────────────────────────────────────────┐      │
│  │                    IMPORT PIPELINE                            │      │
│  │                                                               │      │
│  │  1. @newscoop-importer    (MySQL → Symfony)                  │      │
│  │  2. @csv-articles-importer (CSV → Symfony)                   │      │
│  │  3. @import-validator      (Data integrity checks)           │      │
│  │  4. @import-mapper         (ID mapping & deduplication)      │      │
│  └──────────────────────────────────────────────────────────────┘      │
│                              │                                          │
│                              ▼                                          │
│  ┌──────────────────────────────────────────────────────────────┐      │
│  │                    DESCHIDE NEWS APP                          │      │
│  │  - Articles (with translations: ro, en, ru)                  │      │
│  │  - Categories (with translations)                            │      │
│  │  - Authors                                                   │      │
│  │  - Images + Thumbnails                                       │      │
│  │  - External Article Mappings (for deduplication)             │      │
│  └──────────────────────────────────────────────────────────────┘      │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Data Sources

### 1. Newscoop MySQL Databases

**Connection Configuration** (doctrine.yaml):
```yaml
doctrine:
    dbal:
        connections:
            newscoop:
                url: '%env(NEWSCOOP_DATABASE_URL)%'
                driver: pdo_mysql
                charset: utf8mb4
```

**Schema Mapping**:

| Newscoop Entity | Symfony Entity | Notes |
|-----------------|----------------|-------|
| `Articles` + `Xstiri` | `Article` | Join on Number + IdLanguage |
| `Sections` | `Category` | Via NrSection field |
| `Authors` | `Author` | Direct mapping |
| `Images` + `ArticleImages` | `Image` + `ArticleImage` | Many-to-many |
| Language ID 2 | Locale `ro` | Romanian |
| Language ID 1 | Locale `en` | English |
| Language ID 15 | Locale `ru` | Russian |

**Key Fields from Xstiri**:
- `FTitlu` → `title`
- `Fsubtitlu` → subtitle (not used)
- `Flead` → `lead`
- `FContinut` → `content` (BLOB, needs decoding)
- `FBREAKING_NEWS` → `badge: breaking`
- `FNEWS_ALERT` → `badge: alert`
- `FFLASH` → `badge: flash`

### 2. CSV Files (Playwright Scraped)

**Location**: `/var/www/deschide_news_app/migration_strategy/`
- `buchis.csv`
- `buchis_2.csv`

**CSV Schema**:

| CSV Column | Symfony Field | Notes |
|------------|---------------|-------|
| `Item ID` | External mapping | Unique identifier |
| `Title` | `title` | Required |
| `Slug` | `slug` | Auto-increment if duplicate |
| `Lead text` | `lead` | Optional |
| `Content Text` | `content` | HTML content |
| `Category` | `category` | Create if not exists |
| `Author` | `authors` | Parse "First Last" |
| `Main Image` | `articleImages` | Download from URL |
| `Published On` | `publishedAt` | Date parsing |
| `Manual Data` | `publishedAt` | Preferred over Published On |
| `Is Breaking News?` | `badge: breaking` | Boolean |
| `Is News alert?` | `badge: alert` | Boolean |
| `Is Flash News?` | `badge: flash` | Boolean |
| `Is featured?` | `isFeatured` | Boolean |
| `Draft` | Skip article | Filter out drafts |
| `Archived` | Skip article | Filter out archived |

---

## Import Pipeline Steps

### Phase 1: Categories Import

```bash
# From Newscoop
symfony console app:import:categories --locale=ro
symfony console app:import:categories --locale=en
symfony console app:import:categories --locale=ru

# Verify
symfony console doctrine:query:sql "SELECT COUNT(*) FROM categories"
```

**Mapping Table**: `newscoop_id_mapping`
- `entity_type = 'section'`
- `newscoop_id` → `news_app_id`

### Phase 2: Authors Import

```bash
# From Newscoop
symfony console app:import:authors --limit=500

# Verify
symfony console doctrine:query:sql "SELECT COUNT(*) FROM authors"
```

**Mapping Table**: `newscoop_id_mapping`
- `entity_type = 'author'`
- `newscoop_id` → `news_app_id`

### Phase 3: Images Import

```bash
# From Newscoop (with download)
symfony console app:import:images --limit=1000 --offset=0

# Generate thumbnails
symfony console app:import:generate-thumbnails
```

**Storage Location**: `/var/www/deschide_news_app/deschide_backend/public/uploads/images/`

### Phase 4: Articles Import

```bash
# From Newscoop (with relations)
symfony console app:import:articles-with-relations --limit=1000 --offset=0

# From CSV files
symfony console app:import:csv-articles --file=buchis.csv --limit=500
symfony console app:import:csv-articles --file=buchis_2.csv --limit=500
```

**External Mapping Table**: `external_article_mappings`
- `source` (e.g., 'csv_buchis', 'newscoop_v1')
- `external_id` (original system ID)
- `article_id` (Symfony Article ID)
- `metadata` (JSON with original data)

### Phase 5: Translations Import

```bash
# For articles imported from Newscoop
symfony console app:import:translations --article-ids="1,2,3" --locales=en,ru
```

---

## Invocation Examples

```
@data-import-orchestrator plan full migration from Newscoop
@data-import-orchestrator analyze CSV files structure
@data-import-orchestrator run Phase 1: Categories import
@data-import-orchestrator check import progress
@data-import-orchestrator resolve duplicate detection issues
@data-import-orchestrator generate import report
```

---

## Orchestration Workflow

### 1. Pre-Import Analysis

```
[ANALYZE] → Source Assessment
     │
     ├── Test Newscoop connection
     │   └── symfony console doctrine:query:sql "SELECT 1" --connection=newscoop
     │
     ├── Get Newscoop statistics
     │   └── Call NewscoopConnectionService::getStatistics()
     │
     ├── Validate CSV files exist
     │   └── ls -la /var/www/deschide_news_app/migration_strategy/*.csv
     │
     └── Estimate total records to import
```

### 2. Import Execution Order

```
[IMPORT ORDER]
     │
     ├── 1. Categories (no dependencies)
     │
     ├── 2. Authors (no dependencies)
     │
     ├── 3. Images (no dependencies, but slow)
     │
     ├── 4. Articles from Newscoop
     │   └── Requires: categories, authors, images mapped
     │
     ├── 5. Articles from CSV
     │   └── Independent source, uses ExternalArticleMapping
     │
     └── 6. Translations
         └── Requires: articles imported first
```

### 3. Progress Tracking

**Migration Log Table**: `newscoop_migration_log`

```sql
CREATE TABLE newscoop_migration_log (
    id SERIAL PRIMARY KEY,
    entity_type VARCHAR(50),
    newscoop_id VARCHAR(255),
    status VARCHAR(20),  -- 'success', 'error', 'skipped'
    message TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## Error Recovery

### Common Issues & Solutions

| Issue | Detection | Resolution |
|-------|-----------|------------|
| Duplicate slug | Unique constraint violation | Append counter `-1`, `-2`, etc. |
| Missing category | Foreign key error | Create category on-the-fly |
| Image download fail | HTTP 404/timeout | Log warning, continue without image |
| BLOB decode error | Invalid UTF-8 | Use `stream_get_contents()` |
| EntityManager closed | Exception in batch | Call `resetEntityManager()` |

### Rollback Strategy

```bash
# Delete imported articles by source
symfony console doctrine:query:sql "
    DELETE FROM articles 
    WHERE id IN (
        SELECT article_id FROM external_article_mappings 
        WHERE source = 'csv_buchis'
    )
"

# Clear mapping table
symfony console doctrine:query:sql "
    DELETE FROM external_article_mappings 
    WHERE source = 'csv_buchis'
"
```

---

## Handoff to Specialized Agents

| Task | Agent | Invocation |
|------|-------|------------|
| Import from Newscoop | `@newscoop-importer` | `@newscoop-importer import articles --locale=ro` |
| Import from CSV | `@csv-articles-importer` | `@csv-articles-importer import buchis.csv` |
| Validate imported data | `@import-validator` | `@import-validator check article integrity` |
| Map external IDs | `@import-mapper` | `@import-mapper resolve duplicates` |

---

## Configuration Requirements

### Environment Variables

```env
# .env.local
NEWSCOOP_DATABASE_URL="mysql://user:pass@localhost:3306/newscoop_db"
```

### Doctrine Configuration

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        connections:
            default:
                url: '%env(DATABASE_URL)%'
            newscoop:
                url: '%env(NEWSCOOP_DATABASE_URL)%'
                driver: pdo_mysql
                charset: utf8mb4
```

---

## Quality Checklist

### Pre-Import
- [ ] Newscoop connection verified
- [ ] CSV files accessible
- [ ] Disk space sufficient for images
- [ ] Database backup created

### Post-Import
- [ ] Article count matches expected
- [ ] All categories have articles
- [ ] Images downloaded successfully
- [ ] Translations linked correctly
- [ ] No orphan records
- [ ] External mappings complete

---

## References

- **Existing Commands**: `/apps/backend/src/Command/Import/`
- **Services**: `/apps/backend/src/Service/Import/`
- **Entity Mapping**: `ExternalArticleMapping.php`
- **Migration Log**: `MigrationLoggerService.php`
