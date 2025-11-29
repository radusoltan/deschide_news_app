# Import Mapper Agent

> **Agent Type**: Data Mapping & Deduplication
> **Purpose**: Manage ID mappings between source systems and Symfony, handle deduplication

---

## Design Philosophy

Following Anthropic's core principles:
1. **Simplicity** - Single responsibility: ID mapping and deduplication
2. **Transparency** - Clear mapping rules and conflict resolution
3. **Well-documented ACI** - Explicit mapping table structure

---

## Overview

This agent manages the relationship between external system IDs and Symfony entity IDs, ensuring data consistency and preventing duplicates across multiple import sources.

### Mapping Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        ID MAPPING ARCHITECTURE                          │
│                                                                         │
│  ┌─────────────────┐   ┌─────────────────┐   ┌─────────────────┐       │
│  │  Newscoop v1    │   │  Newscoop v2    │   │  CSV (Buchis)   │       │
│  │  article_123    │   │  article_456    │   │  item_abc123    │       │
│  └────────┬────────┘   └────────┬────────┘   └────────┬────────┘       │
│           │                     │                     │                 │
│           ▼                     ▼                     ▼                 │
│  ┌──────────────────────────────────────────────────────────────┐      │
│  │                    MAPPING TABLES                             │      │
│  │                                                               │      │
│  │  newscoop_id_mapping:                                        │      │
│  │  ┌─────────────┬──────────────┬─────────────┐               │      │
│  │  │ entity_type │ newscoop_id  │ news_app_id │               │      │
│  │  ├─────────────┼──────────────┼─────────────┤               │      │
│  │  │ article     │ 123          │ 1001        │               │      │
│  │  │ section     │ 5            │ 12          │               │      │
│  │  │ author      │ 42           │ 7           │               │      │
│  │  └─────────────┴──────────────┴─────────────┘               │      │
│  │                                                               │      │
│  │  external_article_mappings:                                  │      │
│  │  ┌─────────────┬──────────────┬────────────┬──────────────┐ │      │
│  │  │ source      │ external_id  │ article_id │ metadata     │ │      │
│  │  ├─────────────┼──────────────┼────────────┼──────────────┤ │      │
│  │  │ csv_buchis  │ abc123       │ 2001       │ {...}        │ │      │
│  │  │ newscoop_v2 │ 456          │ 2002       │ {...}        │ │      │
│  │  └─────────────┴──────────────┴────────────┴──────────────┘ │      │
│  └──────────────────────────────────────────────────────────────┘      │
│                              │                                          │
│                              ▼                                          │
│  ┌──────────────────────────────────────────────────────────────┐      │
│  │                    SYMFONY ENTITIES                           │      │
│  │  articles.id = 1001, 2001, 2002 (unified IDs)                │      │
│  └──────────────────────────────────────────────────────────────┘      │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Mapping Tables

### 1. newscoop_id_mapping (Legacy Newscoop)

**Purpose**: Map Newscoop entities to Symfony entities

```sql
CREATE TABLE newscoop_id_mapping (
    id SERIAL PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,     -- 'article', 'section', 'author', 'image'
    newscoop_id BIGINT NOT NULL,          -- Original Newscoop ID
    news_app_id BIGINT NOT NULL,          -- Symfony entity ID
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(entity_type, newscoop_id)
);

-- Indexes
CREATE INDEX idx_mapping_type_newscoop ON newscoop_id_mapping(entity_type, newscoop_id);
CREATE INDEX idx_mapping_type_newsapp ON newscoop_id_mapping(entity_type, news_app_id);
```

**Entity Types**:

| Entity Type | Newscoop Table | Symfony Entity |
|-------------|----------------|----------------|
| `article` | `Articles.Number` | `Article.id` |
| `section` | `Sections.Number` | `Category.id` |
| `author` | `Authors.id` | `Author.id` |
| `image` | `Images.Id` | `Image.id` |

### 2. external_article_mappings (Generic External Sources)

**Purpose**: Map any external article source to Symfony articles

```sql
-- Already exists as Doctrine entity
CREATE TABLE external_article_mappings (
    id SERIAL PRIMARY KEY,
    article_id INT NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
    source VARCHAR(50) NOT NULL,          -- 'csv_buchis', 'newscoop_v2', etc.
    external_id VARCHAR(255) NOT NULL,    -- Original ID (string for flexibility)
    metadata JSON,                         -- Additional info from source
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(source, external_id)
);

-- Indexes
CREATE INDEX idx_external_source_id ON external_article_mappings(source, external_id);
```

**Sources**:

| Source | Description | External ID Format |
|--------|-------------|-------------------|
| `csv_buchis` | Buchis CSV export | MongoDB ObjectId (string) |
| `newscoop_v1` | Legacy Newscoop | Article Number (int) |
| `newscoop_v2` | New Newscoop | Article Number (int) |
| `wordpress` | WordPress import | Post ID (int) |

---

## Mapping Operations

### 1. Create Mapping

```php
// Newscoop mapping
$this->defaultConnection->insert('newscoop_id_mapping', [
    'entity_type' => 'article',
    'newscoop_id' => $newscoopNumber,
    'news_app_id' => $article->getId(),
]);

// External mapping
$mapping = new ExternalArticleMapping();
$mapping->setArticle($article);
$mapping->setSource('csv_buchis');
$mapping->setExternalId($externalId);
$mapping->setMetadata([
    'original_slug' => $record['Slug'],
    'import_date' => date('Y-m-d H:i:s'),
]);
$em->persist($mapping);
```

### 2. Lookup Mapping

```php
// Check if already imported (Newscoop)
$existingId = $this->defaultConnection->fetchOne(
    'SELECT news_app_id FROM newscoop_id_mapping WHERE entity_type = ? AND newscoop_id = ?',
    ['article', $newscoopNumber]
);

// Check if already imported (External)
$existingArticle = $this->mappingRepository->findArticleByExternalId(
    'csv_buchis',
    $externalId
);
```

### 3. Resolve Category Mapping

```php
// Get Symfony category ID from Newscoop section number
$categoryId = $this->defaultConnection->fetchOne(
    'SELECT news_app_id FROM newscoop_id_mapping WHERE entity_type = ? AND newscoop_id = ?',
    ['section', $newscoopSectionNumber]
);

if ($categoryId) {
    $category = $this->categoryRepository->find($categoryId);
    $article->setCategory($category);
}
```

### 4. Resolve Author Mapping

```php
// Get Symfony author ID from Newscoop author ID
$authorId = $this->defaultConnection->fetchOne(
    'SELECT news_app_id FROM newscoop_id_mapping WHERE entity_type = ? AND newscoop_id = ?',
    ['author', $newscoopAuthorId]
);

if ($authorId) {
    $author = $this->authorRepository->find($authorId);
    $article->addAuthor($author);
}
```

---

## Invocation Examples

```
@import-mapper check mapping status
@import-mapper find unmapped articles
@import-mapper resolve duplicate external IDs
@import-mapper merge duplicate articles
@import-mapper export mapping report
@import-mapper clean orphan mappings
@import-mapper link newscoop to csv duplicates
```

---

## Deduplication Strategies

### 1. By External ID

```sql
-- Find duplicates by external ID
SELECT source, external_id, COUNT(*) as count
FROM external_article_mappings
GROUP BY source, external_id
HAVING COUNT(*) > 1;
```

### 2. By Slug

```sql
-- Find duplicate slugs
SELECT slug, COUNT(*) as count, GROUP_CONCAT(id) as article_ids
FROM articles
GROUP BY slug
HAVING COUNT(*) > 1;
```

### 3. By Title (Fuzzy)

```sql
-- Find similar titles (requires full-text search or fuzzy matching)
SELECT a1.id, a1.title, a2.id, a2.title
FROM articles a1
JOIN articles a2 ON a1.id < a2.id
WHERE SOUNDEX(a1.title) = SOUNDEX(a2.title)
   OR LEVENSHTEIN(a1.title, a2.title) < 10;
```

### 4. Cross-Source Deduplication

```sql
-- Find articles from different sources that might be duplicates
SELECT 
    eam1.source as source1,
    eam1.external_id as id1,
    eam2.source as source2,
    eam2.external_id as id2,
    a1.title
FROM external_article_mappings eam1
JOIN articles a1 ON eam1.article_id = a1.id
JOIN external_article_mappings eam2 ON eam1.article_id != eam2.article_id
JOIN articles a2 ON eam2.article_id = a2.id
WHERE a1.slug = a2.slug
   OR a1.title = a2.title;
```

---

## Merge Operations

### Merge Duplicate Articles

```php
/**
 * Merge two articles, keeping the first one
 */
public function mergeArticles(int $keepId, int $deleteId): void
{
    // 1. Move all relations to keep article
    $this->moveAuthors($deleteId, $keepId);
    $this->moveImages($deleteId, $keepId);
    $this->moveTags($deleteId, $keepId);
    
    // 2. Update external mappings
    $this->updateMappings($deleteId, $keepId);
    
    // 3. Delete duplicate
    $this->articleRepository->delete($deleteId);
}
```

### SQL Merge Example

```sql
-- Move authors from article 2 to article 1
UPDATE article_author 
SET article_id = :keepId 
WHERE article_id = :deleteId
  AND author_id NOT IN (
      SELECT author_id FROM article_author WHERE article_id = :keepId
  );

-- Update external mappings
UPDATE external_article_mappings 
SET article_id = :keepId 
WHERE article_id = :deleteId;

-- Delete the duplicate article
DELETE FROM articles WHERE id = :deleteId;
```

---

## Conflict Resolution

### Resolution Strategies

| Conflict Type | Strategy | Action |
|---------------|----------|--------|
| Same external ID, different source | Keep both | Normal case |
| Same external ID, same source | Keep first | Log duplicate |
| Same slug | Append suffix | `-1`, `-2`, etc. |
| Same title | Keep both | Different slugs |
| Cross-source duplicate | Manual review | Flag for review |

### Auto-Resolution Rules

```php
class ConflictResolver
{
    public function resolve(Article $existing, array $newData): Resolution
    {
        // Rule 1: Older article wins (preserve history)
        if ($existing->getPublishedAt() < $newData['publishedAt']) {
            return Resolution::KEEP_EXISTING;
        }
        
        // Rule 2: Richer content wins
        if (strlen($existing->getContent()) > strlen($newData['content'])) {
            return Resolution::KEEP_EXISTING;
        }
        
        // Rule 3: Manual review required
        return Resolution::MANUAL_REVIEW;
    }
}
```

---

## Mapping Reports

### Summary Report

```sql
-- Mapping summary by entity type
SELECT 
    entity_type,
    COUNT(*) as mapped_count,
    MIN(created_at) as first_import,
    MAX(created_at) as last_import
FROM newscoop_id_mapping
GROUP BY entity_type;

-- External mapping summary by source
SELECT 
    source,
    COUNT(*) as mapped_count,
    MIN(created_at) as first_import,
    MAX(created_at) as last_import
FROM external_article_mappings
GROUP BY source;
```

### Integrity Report

```sql
-- Orphan mappings (article deleted)
SELECT COUNT(*) as orphan_count
FROM external_article_mappings eam
LEFT JOIN articles a ON eam.article_id = a.id
WHERE a.id IS NULL;

-- Unmapped articles (no external source)
SELECT COUNT(*) as unmapped_count
FROM articles a
LEFT JOIN external_article_mappings eam ON a.id = eam.article_id
LEFT JOIN newscoop_id_mapping nim ON a.id = nim.news_app_id AND nim.entity_type = 'article'
WHERE eam.id IS NULL AND nim.id IS NULL;
```

---

## Cleanup Operations

### Remove Orphan Mappings

```sql
-- Delete orphan external mappings
DELETE FROM external_article_mappings 
WHERE article_id NOT IN (SELECT id FROM articles);

-- Delete orphan newscoop mappings
DELETE FROM newscoop_id_mapping 
WHERE entity_type = 'article' 
  AND news_app_id NOT IN (SELECT id FROM articles);
```

### Reset All Mappings (Dangerous!)

```sql
-- Only use when re-importing everything
TRUNCATE TABLE external_article_mappings;
TRUNCATE TABLE newscoop_id_mapping;
TRUNCATE TABLE newscoop_migration_log;
```

---

## Integration Points

### With Import Agents

```
[Import Flow with Mapping]

@newscoop-importer
      │
      ├── Before import: Check newscoop_id_mapping
      │
      ├── During import: Create mapping entries
      │
      └── After import: @import-mapper verify

@csv-articles-importer
      │
      ├── Before import: Check external_article_mappings
      │
      ├── During import: Create ExternalArticleMapping entities
      │
      └── After import: @import-mapper verify
```

### Handoff Points

| Scenario | Next Agent | Action |
|----------|------------|--------|
| Mapping complete | `@import-validator` | Verify integrity |
| Duplicates found | Manual review | Human decision |
| Orphans detected | Auto-cleanup | Run cleanup SQL |
| Cross-source match | `@import-mapper` | Suggest merge |

---

## Quality Checklist

### Pre-Mapping
- [ ] Source data validated
- [ ] Target entities exist
- [ ] Mapping tables initialized

### During Mapping
- [ ] Unique constraints respected
- [ ] Duplicates logged
- [ ] Conflicts flagged

### Post-Mapping
- [ ] No orphan mappings
- [ ] All imports tracked
- [ ] Report generated

---

## References

- **Entity**: `src/Entity/ExternalArticleMapping.php`
- **Repository**: `src/Repository/ExternalArticleMappingRepository.php`
- **Service**: `src/Service/Import/MigrationLoggerService.php`
- **Table Schema**: `newscoop_id_mapping`, `newscoop_migration_log`
