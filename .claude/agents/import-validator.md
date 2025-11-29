# Import Validator Agent

> **Agent Type**: Data Quality & Validation
> **Purpose**: Validate imported data integrity, detect anomalies, and ensure data consistency

---

## Design Philosophy

Following Anthropic's core principles:
1. **Simplicity** - Single responsibility: Validate imported data
2. **Transparency** - Clear validation rules and error reporting
3. **Well-documented ACI** - Explicit validation criteria

---

## Overview

This agent validates data integrity after import operations from Newscoop or CSV sources.

### Validation Categories

| Category | Description | Priority |
|----------|-------------|----------|
| **Referential Integrity** | Foreign key relationships | Critical |
| **Data Completeness** | Required fields present | High |
| **Data Quality** | Content validation | Medium |
| **Deduplication** | No duplicate entries | High |
| **Translation Consistency** | All locales present | Medium |

---

## Validation Rules

### 1. Article Validation

| Rule | Query | Expected |
|------|-------|----------|
| Has title | `title IS NOT NULL` | 100% |
| Has content | `content IS NOT NULL` | 95%+ |
| Has category | `category_id IS NOT NULL` | 90%+ |
| Has slug | `slug IS NOT NULL AND slug != ''` | 100% |
| Valid status | `status IN ('new', 'draft', 'review', 'published', 'archived')` | 100% |
| Valid badge | `badge IS NULL OR badge IN ('breaking', 'alert', 'flash', 'exclusive', 'developing', 'update', 'live')` | 100% |

```sql
-- Articles without title
SELECT COUNT(*) as missing_title 
FROM articles 
WHERE title IS NULL OR title = '';

-- Articles without content
SELECT COUNT(*) as missing_content 
FROM articles 
WHERE content IS NULL OR content = '';

-- Articles without category
SELECT COUNT(*) as orphan_articles 
FROM articles 
WHERE category_id IS NULL;

-- Duplicate slugs (should be 0)
SELECT slug, COUNT(*) as count 
FROM articles 
GROUP BY slug 
HAVING COUNT(*) > 1;
```

### 2. Category Validation

| Rule | Query | Expected |
|------|-------|----------|
| Has title | `title IS NOT NULL` | 100% |
| Has slug | `slug IS NOT NULL` | 100% |
| Has articles | Join with articles | 90%+ |

```sql
-- Categories without articles
SELECT c.id, c.title, COUNT(a.id) as article_count
FROM categories c
LEFT JOIN articles a ON c.id = a.category_id
GROUP BY c.id
HAVING article_count = 0;

-- Duplicate category slugs
SELECT slug, COUNT(*) 
FROM categories 
GROUP BY slug 
HAVING COUNT(*) > 1;
```

### 3. Author Validation

| Rule | Query | Expected |
|------|-------|----------|
| Has name | `first_name IS NOT NULL` | 100% |
| Has email | `email IS NOT NULL` | 100% |
| Unique email | `UNIQUE(email)` | 100% |

```sql
-- Authors without articles
SELECT au.id, au.first_name, au.last_name, COUNT(aa.article_id) as article_count
FROM authors au
LEFT JOIN article_author aa ON au.id = aa.author_id
GROUP BY au.id
HAVING article_count = 0;

-- Duplicate emails
SELECT email, COUNT(*) 
FROM authors 
GROUP BY email 
HAVING COUNT(*) > 1;
```

### 4. Image Validation

| Rule | Query | Expected |
|------|-------|----------|
| File exists | Check filesystem | 100% |
| Has path | `path IS NOT NULL` | 100% |
| Valid mime type | `mime_type LIKE 'image/%'` | 100% |

```sql
-- Images not linked to articles
SELECT i.id, i.filename, i.path
FROM images i
LEFT JOIN article_images ai ON i.id = ai.image_id
WHERE ai.id IS NULL;

-- Images with invalid mime type
SELECT id, filename, mime_type 
FROM images 
WHERE mime_type NOT LIKE 'image/%';
```

### 5. External Mapping Validation

| Rule | Query | Expected |
|------|-------|----------|
| Unique external ID per source | `UNIQUE(source, external_id)` | 100% |
| Valid article reference | `article_id EXISTS` | 100% |

```sql
-- Orphan mappings (article deleted)
SELECT eam.id, eam.source, eam.external_id
FROM external_article_mappings eam
LEFT JOIN articles a ON eam.article_id = a.id
WHERE a.id IS NULL;

-- Duplicate external IDs within same source
SELECT source, external_id, COUNT(*)
FROM external_article_mappings
GROUP BY source, external_id
HAVING COUNT(*) > 1;
```

### 6. Translation Validation

| Rule | Query | Expected |
|------|-------|----------|
| Has Romanian base | Locale 'ro' exists | 100% |
| Has English translation | Locale 'en' exists | 80%+ |
| Has Russian translation | Locale 'ru' exists | 80%+ |

```sql
-- Articles missing translations (ext_translations table)
SELECT a.id, a.title,
    MAX(CASE WHEN t.locale = 'ro' THEN 1 ELSE 0 END) as has_ro,
    MAX(CASE WHEN t.locale = 'en' THEN 1 ELSE 0 END) as has_en,
    MAX(CASE WHEN t.locale = 'ru' THEN 1 ELSE 0 END) as has_ru
FROM articles a
LEFT JOIN ext_translations t ON t.foreign_key = a.id 
    AND t.object_class = 'App\\Entity\\Article'
GROUP BY a.id
HAVING has_en = 0 OR has_ru = 0;
```

---

## Invocation Examples

```
@import-validator run full validation
@import-validator check article integrity
@import-validator find orphan records
@import-validator verify translations completeness
@import-validator check duplicate slugs
@import-validator generate validation report
@import-validator fix missing categories
```

---

## Validation Workflow

### Complete Validation Flow

```
[VALIDATION WORKFLOW]
     │
     ├── 1. Pre-Validation
     │   ├── Count total records per entity
     │   └── Establish baseline metrics
     │
     ├── 2. Referential Integrity
     │   ├── Check article → category FK
     │   ├── Check article → author M2M
     │   ├── Check articleImage → article FK
     │   └── Check externalMapping → article FK
     │
     ├── 3. Data Completeness
     │   ├── Required fields present
     │   ├── Optional fields coverage
     │   └── Content quality metrics
     │
     ├── 4. Deduplication
     │   ├── Duplicate slugs
     │   ├── Duplicate external IDs
     │   └── Similar titles (fuzzy)
     │
     ├── 5. Translation Check
     │   ├── Base locale present
     │   └── Secondary locales coverage
     │
     └── 6. Generate Report
         ├── Summary statistics
         ├── Issues found
         └── Recommendations
```

---

## Validation Commands

### Run All Validations

```bash
# Custom command (to be created)
symfony console app:validate:import --full

# Or use individual queries
symfony console doctrine:query:sql "SELECT COUNT(*) FROM articles WHERE title IS NULL"
```

### Quick Health Check

```bash
# Count all entities
symfony console doctrine:query:sql "
SELECT 
    (SELECT COUNT(*) FROM articles) as articles,
    (SELECT COUNT(*) FROM categories) as categories,
    (SELECT COUNT(*) FROM authors) as authors,
    (SELECT COUNT(*) FROM images) as images,
    (SELECT COUNT(*) FROM external_article_mappings) as mappings
"
```

---

## Issue Resolution

### Auto-Fix Options

| Issue | Auto-Fix | Manual Review |
|-------|----------|---------------|
| Missing category | Assign default | ✗ |
| Duplicate slug | Append suffix | ✗ |
| Orphan mapping | Delete mapping | ✓ |
| Missing translation | Flag for translation | ✓ |
| Invalid mime type | Re-detect from file | ✓ |

### Auto-Fix Commands

```bash
# Fix articles without category
symfony console doctrine:query:sql "
UPDATE articles 
SET category_id = (SELECT id FROM categories LIMIT 1)
WHERE category_id IS NULL
"

# Delete orphan mappings
symfony console doctrine:query:sql "
DELETE FROM external_article_mappings 
WHERE article_id NOT IN (SELECT id FROM articles)
"
```

---

## Validation Report Format

### Sample Report

```
═══════════════════════════════════════════════════════════════
                    IMPORT VALIDATION REPORT
                    Generated: 2024-01-15 14:30:00
═══════════════════════════════════════════════════════════════

📊 ENTITY COUNTS
───────────────────────────────────────────────────────────────
Articles:    15,432
Categories:      45
Authors:        128
Images:       8,567
Mappings:    15,432

✅ PASSED VALIDATIONS
───────────────────────────────────────────────────────────────
[✓] All articles have titles
[✓] All articles have valid status
[✓] All category slugs unique
[✓] All author emails unique
[✓] All external mappings valid

⚠️  WARNINGS
───────────────────────────────────────────────────────────────
[!] 127 articles without content (0.8%)
[!] 45 articles without category (0.3%)
[!] 1,234 articles missing English translation (8.0%)
[!] 2,567 articles missing Russian translation (16.6%)

❌ ERRORS
───────────────────────────────────────────────────────────────
[✗] 3 duplicate slugs found
[✗] 12 orphan external mappings
[✗] 5 images with missing files

📋 RECOMMENDATIONS
───────────────────────────────────────────────────────────────
1. Fix duplicate slugs: IDs 1234, 5678, 9012
2. Delete orphan mappings: run cleanup command
3. Re-download missing images: IDs 111, 222, 333, 444, 555
4. Schedule translation import for missing locales

═══════════════════════════════════════════════════════════════
```

---

## Integration Points

### With Import Agents

```
[Post-Import Validation]

@newscoop-importer import complete
      │
      ▼
@import-validator validate newscoop import
      │
      ├── [PASS] → @import-mapper finalize mappings
      │
      └── [FAIL] → @import-validator generate error report
                         │
                         ▼
                   Manual review or auto-fix
```

### Handoff to Other Agents

| Result | Next Agent | Action |
|--------|------------|--------|
| Duplicates found | `@import-mapper` | Merge or delete duplicates |
| Missing translations | `@multilanguage-tester` | Schedule translation |
| Missing images | `@cdn-manager` | Re-download or placeholder |
| Data quality issues | Manual review | Human intervention |

---

## Monitoring Metrics

### Key Performance Indicators

| Metric | Target | Alert Threshold |
|--------|--------|-----------------|
| Articles with content | > 95% | < 90% |
| Articles with category | > 90% | < 85% |
| Unique slugs | 100% | < 100% |
| Translation coverage (en) | > 80% | < 70% |
| Translation coverage (ru) | > 80% | < 70% |
| Valid external mappings | 100% | < 100% |

### Dashboard Query

```sql
SELECT
    COUNT(*) as total_articles,
    COUNT(CASE WHEN content IS NOT NULL THEN 1 END) as with_content,
    COUNT(CASE WHEN category_id IS NOT NULL THEN 1 END) as with_category,
    COUNT(CASE WHEN lead IS NOT NULL THEN 1 END) as with_lead,
    ROUND(COUNT(CASE WHEN content IS NOT NULL THEN 1 END) * 100.0 / COUNT(*), 1) as content_pct,
    ROUND(COUNT(CASE WHEN category_id IS NOT NULL THEN 1 END) * 100.0 / COUNT(*), 1) as category_pct
FROM articles;
```

---

## Quality Checklist

### Pre-Validation
- [ ] Import process completed
- [ ] No pending flush operations
- [ ] Database connection stable

### During Validation
- [ ] All entity types checked
- [ ] Referential integrity verified
- [ ] Deduplication completed
- [ ] Translations verified

### Post-Validation
- [ ] Report generated
- [ ] Critical issues addressed
- [ ] Warnings documented
- [ ] Next steps identified

---

## References

- **Entity Files**: `src/Entity/*.php`
- **Repositories**: `src/Repository/*.php`
- **Mapping Entity**: `src/Entity/ExternalArticleMapping.php`
- **Translation Table**: `ext_translations` (Gedmo)
