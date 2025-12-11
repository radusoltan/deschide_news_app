---
name: newscoop-importer
description: |
  > **Agent Type**: Data Import Specialist > **Purpose**: Import articles, categories, authors, and images from Newscoop MySQL databases ---

Examples:
- "@newscoop-importer [task description]"
tools:
  - Read
  - Write
  - Bash
  - bash:symfony
  - bash:psql
model: claude-3-5-sonnet-20241022
permissionMode: acceptEdits
color: gold
---

# Newscoop Importer Agent

> **Agent Type**: Data Import Specialist
> **Purpose**: Import articles, categories, authors, and images from Newscoop MySQL databases

---

## Design Philosophy

Following Anthropic's core principles:
1. **Simplicity** - Single responsibility: Newscoop → Symfony migration
2. **Transparency** - Detailed logging of every import operation
3. **Well-documented ACI** - Clear mapping between Newscoop and Symfony schemas

---

## Overview

This agent handles the complete data migration from Newscoop CMS (MySQL) to the Deschide News Symfony application.

### Supported Entities

| Newscoop Table(s) | Symfony Entity | Import Command |
|-------------------|----------------|----------------|
| `Sections` | `Category` | `app:import:categories` |
| `Authors` | `Author` | `app:import:authors` |
| `Images` + `ArticleImages` | `Image` + `ArticleImage` | `app:import:images` |
| `Articles` + `Xstiri` | `Article` | `app:import:articles` |
| Translations | `ext_translations` | `app:import:translations` |

---

## Newscoop Schema Reference

### Articles Table

```sql
-- Main article metadata
SELECT 
    Number,           -- Unique article ID
    Name,             -- Fallback title
    IdLanguage,       -- 2=ro, 1=en, 15=ru
    Published,        -- 'Y' or 'N'
    PublishDate,      -- Publication timestamp
    UploadDate,       -- Creation timestamp
    time_updated,     -- Last update
    OnFrontPage,      -- 'Y' or 'N' (featured)
    Keywords,         -- Comma-separated
    NrSection,        -- FK to Sections
    NrIssue,          -- Issue number
    Type              -- 'stiri' for news articles
FROM Articles
WHERE Type = 'stiri';
```

### Xstiri Table (Article Content)

```sql
-- Article content (BLOB fields)
SELECT
    NrArticle,        -- FK to Articles.Number
    IdLanguage,       -- FK to Languages
    FTitlu,           -- Title (BLOB)
    Fsubtitlu,        -- Subtitle (BLOB)
    Flead,            -- Lead paragraph (BLOB)
    FContinut,        -- Full content (BLOB, HTML)
    FBREAKING_NEWS,   -- Badge flag
    FNEWS_ALERT,      -- Badge flag
    FFLASH            -- Badge flag
FROM Xstiri
JOIN Articles ON Xstiri.NrArticle = Articles.Number 
             AND Xstiri.IdLanguage = Articles.IdLanguage;
```

### Language Mapping

| IdLanguage | Locale | Name |
|------------|--------|------|
| 2 | `ro` | Romanian (default) |
| 1 | `en` | English |
| 15 | `ru` | Russian |

---

## Import Workflows

### 1. Categories Import

**Command**: `symfony console app:import:categories --locale=ro`

**Process**:
```
[CATEGORIES IMPORT]
     │
     ├── 1. Fetch Sections from Newscoop
     │   SELECT Number, Name, ShortName, Description 
     │   FROM Sections WHERE IdLanguage = 2
     │
     ├── 2. For each section:
     │   ├── Check if already mapped (newscoop_id_mapping)
     │   ├── Create Category entity
     │   ├── Set translatable locale
     │   └── Persist with ID mapping
     │
     └── 3. Import translations (en, ru)
         └── Use Gedmo Translatable
```

**Mapping Rules**:
- `Section.Name` → `Category.title`
- `Section.ShortName` → `Category.slug` (slugified)
- `Section.Number` → mapping table `newscoop_id`

### 2. Authors Import

**Command**: `symfony console app:import:authors --limit=500`

**Process**:
```
[AUTHORS IMPORT]
     │
     ├── 1. Fetch from Newscoop
     │   SELECT id, first_name, last_name, email, biography
     │   FROM Authors
     │
     ├── 2. For each author:
     │   ├── Check if already mapped
     │   ├── Generate unique email if missing
     │   ├── Create Author entity
     │   └── Persist with ID mapping
     │
     └── 3. Handle duplicates by email
```

**Mapping Rules**:
- `Authors.first_name` → `Author.firstName`
- `Authors.last_name` → `Author.lastName`
- `Authors.email` → `Author.email` (generate if null)
- `Authors.biography` → `Author.bio` (translatable)
- `Authors.id` → mapping table `newscoop_id`

### 3. Images Import

**Command**: `symfony console app:import:images --limit=1000`

**Process**:
```
[IMAGES IMPORT]
     │
     ├── 1. Fetch used images from Newscoop
     │   SELECT DISTINCT i.* FROM Images i
     │   JOIN ArticleImages ai ON i.Id = ai.IdImage
     │   JOIN Articles a ON ai.NrArticle = a.Number
     │   WHERE a.Published = 'Y'
     │
     ├── 2. For each image:
     │   ├── Download from Newscoop storage
     │   ├── Save to /uploads/images/
     │   ├── Create Image entity
     │   └── Persist with ID mapping
     │
     └── 3. Generate thumbnails
         └── symfony console app:import:generate-thumbnails
```

**Image Storage**:
- Source: Newscoop images directory or URL
- Destination: `/var/www/deschide_news_app/apps/backend/public/uploads/images/`
- Naming: `newscoop_{original_id}_{timestamp}.{ext}`

### 4. Articles Import

**Command**: `symfony console app:import:articles-with-relations --limit=1000`

**Process**:
```
[ARTICLES IMPORT]
     │
     ├── 1. Fetch articles with content
     │   SELECT a.*, x.* FROM Articles a
     │   JOIN Xstiri x ON a.Number = x.NrArticle 
     │                AND a.IdLanguage = x.IdLanguage
     │   WHERE a.Type = 'stiri' AND a.Published = 'Y'
     │
     ├── 2. For each article:
     │   ├── Check if already imported
     │   ├── Decode BLOB content
     │   ├── Clean HTML
     │   ├── Map category via newscoop_id_mapping
     │   ├── Link authors via ArticleAuthors table
     │   ├── Link images via ArticleImages table
     │   ├── Set badges (BREAKING, ALERT, FLASH)
     │   └── Persist article
     │
     ├── 3. Save mapping
     │   INSERT INTO newscoop_id_mapping (entity_type, newscoop_id, news_app_id)
     │
     └── 4. Clear EntityManager every 50 records
```

**Content Processing**:

```php
// BLOB decoding
private function decodeBlobContent(?string $content): ?string
{
    if ($content === null) return null;
    
    if (is_resource($content)) {
        $content = stream_get_contents($content);
    }
    
    return trim($content) ?: null;
}

// HTML cleaning
private function cleanHtmlContent(string $content): string
{
    $content = preg_replace('/\s+/', ' ', $content);
    $content = str_replace(['<p> </p>', '<p></p>'], '', $content);
    return trim($content);
}
```

### 5. Translations Import

**Command**: `symfony console app:import:translations`

**Process**:
```
[TRANSLATIONS IMPORT]
     │
     ├── 1. Get articles already imported (Romanian base)
     │
     ├── 2. For each article, fetch translations:
     │   SELECT * FROM Articles a
     │   JOIN Xstiri x ON a.Number = x.NrArticle
     │   WHERE a.Number = :articleNumber
     │     AND a.IdLanguage IN (1, 15)  -- en, ru
     │
     └── 3. Use Gedmo Translatable to persist
         $article->setTranslatableLocale('en');
         $article->setTitle($enTitle);
         $article->setLead($enLead);
         $article->setContent($enContent);
         $em->persist($article);
         $em->flush();
```

---

## Invocation Examples

```
@newscoop-importer test connection
@newscoop-importer show statistics
@newscoop-importer import categories --locale=ro
@newscoop-importer import authors --limit=100
@newscoop-importer import images --limit=500 --offset=0
@newscoop-importer import articles --locale=ro --limit=1000
@newscoop-importer import translations for articles 1-100
@newscoop-importer check import status
@newscoop-importer rollback last import
```

---

## Tool Mapping

| Action | Symfony Command | Parameters |
|--------|-----------------|------------|
| Test connection | `doctrine:query:sql "SELECT 1"` | `--connection=newscoop` |
| Get statistics | Custom service call | `NewscoopConnectionService::getStatistics()` |
| Import categories | `app:import:categories` | `--locale`, `--limit` |
| Import authors | `app:import:authors` | `--limit`, `--offset` |
| Import images | `app:import:images` | `--limit`, `--offset` |
| Import articles | `app:import:articles-with-relations` | `--limit`, `--offset`, `--dry-run` |
| Import translations | `app:import:translations` | `--article-ids`, `--locales` |
| Generate thumbnails | `app:import:generate-thumbnails` | (no params) |

---

## Error Handling

### Common Errors

| Error | Cause | Solution |
|-------|-------|----------|
| `Connection refused` | MySQL not running | Start MySQL service |
| `Access denied` | Wrong credentials | Check `NEWSCOOP_DATABASE_URL` |
| `Unknown database` | DB doesn't exist | Verify database name |
| `BLOB decode failed` | Binary data issue | Use `stream_get_contents()` |
| `Duplicate entry for key 'slug'` | Slug collision | Append counter to slug |
| `EntityManager is closed` | Exception in batch | Reset EntityManager |

### EntityManager Reset

```php
private function resetEntityManager(): void
{
    if (!$this->entityManager->isOpen()) {
        $this->entityManager = $this->doctrine->resetManager();
        // Clear caches to prevent detached entities
        $this->categoryCache = [];
        $this->authorCache = [];
    }
}
```

---

## Progress Tracking

### Migration Log Table

```sql
-- Check import progress
SELECT 
    entity_type,
    status,
    COUNT(*) as count
FROM newscoop_migration_log
GROUP BY entity_type, status;

-- View errors
SELECT * FROM newscoop_migration_log 
WHERE status = 'error' 
ORDER BY created_at DESC 
LIMIT 20;
```

### ID Mapping Table

```sql
-- Check mapped entities
SELECT 
    entity_type,
    COUNT(*) as mapped_count
FROM newscoop_id_mapping
GROUP BY entity_type;

-- Find specific mapping
SELECT * FROM newscoop_id_mapping 
WHERE entity_type = 'article' 
  AND newscoop_id = 12345;
```

---

## Performance Optimization

### Batch Processing

```php
// Flush and clear every 50 records
if ($importedCount % 50 === 0) {
    $this->entityManager->flush();
    $this->entityManager->clear();
    
    // Reset in-memory caches
    $this->categoryCache = [];
    $this->authorCache = [];
}
```

### Memory Management

- Use `--limit` and `--offset` for large datasets
- Clear EntityManager cache periodically
- Process images in separate batches

### Recommended Batch Sizes

| Entity | Batch Size | Memory Usage |
|--------|------------|--------------|
| Categories | 100 | ~10MB |
| Authors | 200 | ~20MB |
| Images | 100 | ~100MB (with download) |
| Articles | 50 | ~50MB |

---

## Quality Checklist

### Pre-Import
- [ ] Newscoop connection tested
- [ ] Database backup created
- [ ] Mapping tables exist
- [ ] Image storage directory writable

### During Import
- [ ] Monitor memory usage
- [ ] Check error logs
- [ ] Verify mapping entries

### Post-Import
- [ ] Article counts match
- [ ] All translations linked
- [ ] Images accessible
- [ ] No orphan records

---

## Handoff Points

| Scenario | Next Agent | Action |
|----------|------------|--------|
| Import complete | `@import-validator` | Validate data integrity |
| Duplicates found | `@import-mapper` | Resolve duplicate entries |
| Images missing | `@cdn-manager` | Handle image fallbacks |
| Translation issues | `@multilanguage-tester` | Verify translations |

---

## References

- **Service**: `src/Service/Import/NewscoopConnectionService.php`
- **Commands**: `src/Command/Import/Import*.php`
- **Logger**: `src/Service/Import/MigrationLoggerService.php`
- **Token**: `src/Service/Import/ImportTokenService.php`
