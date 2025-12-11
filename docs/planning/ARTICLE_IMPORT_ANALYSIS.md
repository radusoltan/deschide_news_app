# Business Analysis Report: Article Import Strategy

**Project:** Deschide News App - Legacy Content Migration
**Date:** 2025-11-29
**Status:** Planning Phase
**Analyst:** Business Analytics Team

---

## Executive Summary

This report provides a comprehensive business analysis for importing approximately 147,000 articles from legacy Newscoop CMS to the new Deschide News App platform. The analysis includes volume estimates, prioritization strategy, batch processing recommendations, risk assessment, and success metrics.

**Key Findings:**
- Total estimated articles: ~147,000 (spanning 2016-2024)
- Recommended import order: Categories → Authors → Images → Articles → Translations
- Optimal batch size: 500 articles per batch
- Estimated total import time: 6-8 days (including validation)
- Critical success factor: Data validation at each stage

---

## 1. Volume Analysis

### 1.1 Article Distribution by Year

Based on documentation analysis and typical news platform patterns:

| Year Range | Volume Estimate | Status Target | Business Priority | Notes |
|------------|----------------|---------------|-------------------|-------|
| **2024** | ~18,000 | PUBLISHED | CRITICAL | Current year content |
| **2023** | ~17,000 | PUBLISHED | CRITICAL | Recent content, high SEO value |
| **2022** | ~22,000 | PUBLISHED | HIGH | Still relevant, good traffic |
| **2021** | ~23,000 | PUBLISHED | HIGH | Transition to archive threshold |
| **2020** | ~12,500 | ARCHIVED | MEDIUM | Archive candidate (4+ years) |
| **2019** | ~15,800 | ARCHIVED | MEDIUM | Historical content |
| **2018** | ~14,300 | ARCHIVED | MEDIUM | Historical content |
| **2017** | ~13,200 | ARCHIVED | LOW | Historical content |
| **2016** | ~11,320 | ARCHIVED | LOW | Historical content |
| **TOTAL** | **~147,120** | - | - | 8+ years of content |

**Distribution Summary:**
- **Active Articles (2021-2024):** ~80,000 articles (54.4%)
- **Archive Articles (2016-2020):** ~67,120 articles (45.6%)

### 1.2 Supporting Data Volume Estimates

| Entity Type | Estimated Count | Source Database | Notes |
|-------------|----------------|-----------------|-------|
| **Categories** | ~25-35 | Newscoop taxonomy | Main navigation categories |
| **Authors** | ~150-200 | Newscoop users | Active and historical authors |
| **Images (Original)** | ~85,000-95,000 | Newscoop media library | ~0.65 images per article avg |
| **Thumbnails** | ~850,000-950,000 | To be generated | 10 profiles x images |
| **Translations** | ~294,000 | Newscoop translations | 2 avg translations per article |
| **Article-Image Relations** | ~95,000-105,000 | Newscoop associations | Many-to-many relationships |

**Storage Requirements:**
- Original images: ~120-150 GB (estimated)
- Generated thumbnails: ~180-220 GB (WebP compression)
- Database: ~8-12 GB (PostgreSQL with indexes)
- **Total storage needed:** ~300-380 GB

---

## 2. Import Priority & Execution Order

### 2.1 Correct Import Sequence (Foreign Key Dependencies)

**CRITICAL:** Import order must respect database foreign key constraints.

```
PHASE 1: Foundation Data (No Dependencies)
├── 1. Categories (independent)
├── 2. Authors (independent)
└── 3. Thumbnail Profiles (independent, pre-configured)

PHASE 2: Media Assets
├── 4. Images (depends on: none)
└── 5. Thumbnails (depends on: Images, ThumbnailProfiles)
    └── Generated asynchronously via Messenger queue

PHASE 3: Articles & Relations
├── 6. Articles (depends on: Categories, Authors)
├── 7. Article Translations (depends on: Articles)
└── 8. Article-Image Relations (depends on: Articles, Images)
```

### 2.2 Priority Matrix

| Import Phase | Business Impact | Technical Risk | Execution Priority |
|--------------|-----------------|----------------|-------------------|
| Categories | HIGH | LOW | 1 (FIRST) |
| Authors | HIGH | LOW | 2 |
| Images | CRITICAL | MEDIUM | 3 |
| Articles | CRITICAL | HIGH | 4 |
| Translations | CRITICAL | HIGH | 5 |
| Article-Image Relations | HIGH | MEDIUM | 6 |
| Thumbnails | MEDIUM | LOW | 7 (Async) |

**Rationale:**
- **Categories first:** Required by articles (foreign key)
- **Authors second:** Required by articles (foreign key)
- **Images third:** Can be imported independently, needed before article-image relations
- **Articles fourth:** Core content, depends on categories + authors
- **Translations fifth:** Immediately after articles (same batch recommended)
- **Article-Image relations sixth:** Links articles to images
- **Thumbnails last:** Generated asynchronously (non-blocking)

---

## 3. Batch Processing Strategy

### 3.1 Recommended Batch Sizes

| Entity Type | Batch Size | Batches Needed | Est. Time/Batch | Total Time |
|-------------|-----------|----------------|-----------------|-----------|
| **Categories** | ALL (25-35) | 1 | 30 seconds | 30 seconds |
| **Authors** | ALL (150-200) | 1 | 2 minutes | 2 minutes |
| **Images** | 1,000 | 85-95 | 8-12 minutes | 12-18 hours |
| **Articles** | 500 | 294 | 5-8 minutes | 24-40 hours |
| **Translations** | 1,000 | 294 | 3-5 minutes | 15-25 hours |
| **Article-Image** | 2,000 | 50-55 | 2-4 minutes | 2-4 hours |
| **Thumbnails** | Async queue | N/A | Background | 48-72 hours |

**Total Sequential Import Time:** ~54-90 hours (2.2-3.7 days)
**Total with Async Processing:** ~6-8 days (including thumbnail generation)

### 3.2 Batch Size Decision Matrix

**Why 500 articles per batch?**

| Batch Size | Memory Usage | Error Risk | Recovery Time | Recommendation |
|-----------|--------------|-----------|---------------|----------------|
| 100 | ~50 MB | LOW | Fast (1-2 min) | Too slow (14+ batches/day) |
| **500** | **~200 MB** | **MEDIUM** | **Acceptable (5-8 min)** | **OPTIMAL** ✓ |
| 1,000 | ~400 MB | HIGH | Slow (10-15 min) | Risk of timeout |
| 5,000 | ~2 GB | VERY HIGH | Very slow (50+ min) | Not recommended |

**Optimization Strategy:**
```php
// Memory management per batch
foreach ($batch as $article) {
    $this->entityManager->persist($article);

    // Flush every 50 entities
    if ($count % 50 === 0) {
        $this->entityManager->flush();
        $this->entityManager->clear(); // Free memory
        gc_collect_cycles(); // Force garbage collection
    }
}
$this->entityManager->flush(); // Final flush
```

### 3.3 Performance Optimization

**Database Optimizations:**
```sql
-- Temporarily disable triggers during bulk import (if safe)
ALTER TABLE articles DISABLE TRIGGER ALL;

-- Disable indexes during import (rebuild after)
DROP INDEX idx_article_published_at;
DROP INDEX idx_article_slug;

-- Re-enable after import
ALTER TABLE articles ENABLE TRIGGER ALL;
CREATE INDEX idx_article_published_at ON articles(published_at);
CREATE INDEX idx_article_slug ON articles(slug);
```

**Import Performance Targets:**
- Articles: ~150-200 per minute (sustained)
- Images: ~80-100 per minute (including upload)
- Translations: ~300-400 per minute (bulk insert)

---

## 4. Risk Assessment & Mitigation

### 4.1 Critical Risks

| Risk Category | Probability | Impact | Severity | Mitigation Strategy |
|--------------|-------------|--------|----------|---------------------|
| **Data Corruption** | MEDIUM | CRITICAL | 🔴 HIGH | - Validate each record<br>- Dry-run mode first<br>- Checksum verification |
| **Memory Exhaustion** | MEDIUM | HIGH | 🟡 MEDIUM | - Batch processing<br>- `entityManager->clear()`<br>- Increase PHP memory limit |
| **Duplicate Entries** | HIGH | MEDIUM | 🟡 MEDIUM | - Unique constraint checks<br>- Slug collision handling<br>- ID mapping table |
| **Timeout Errors** | MEDIUM | MEDIUM | 🟡 MEDIUM | - Increase PHP max_execution_time<br>- Resume from last batch<br>- Progress tracking |
| **Foreign Key Violations** | LOW | CRITICAL | 🔴 HIGH | - Strict import order<br>- Validate references<br>- Transaction rollback |
| **Image Upload Failures** | MEDIUM | MEDIUM | 🟡 MEDIUM | - Retry mechanism (3x)<br>- Log failed uploads<br>- Manual recovery script |
| **Translation Mismatches** | MEDIUM | HIGH | 🟡 MEDIUM | - Locale validation<br>- Fallback to default locale<br>- Missing translation report |
| **Slug Conflicts** | HIGH | LOW | 🟢 LOW | - Auto-increment suffix<br>- Slug uniqueness check<br>- Transliteration validation |

### 4.2 Mitigation Implementation

**1. Data Validation Pipeline**
```php
class ArticleImportValidator
{
    public function validate(array $data): ValidationResult
    {
        $errors = [];

        // Required fields
        if (empty($data['title'])) {
            $errors[] = 'Title is required';
        }

        // Foreign key validation
        if (!$this->categoryExists($data['category_id'])) {
            $errors[] = "Category {$data['category_id']} not found";
        }

        if (!$this->authorExists($data['author_id'])) {
            $errors[] = "Author {$data['author_id']} not found";
        }

        // Date validation
        if (!$this->isValidDate($data['published_at'])) {
            $errors[] = 'Invalid published_at date';
        }

        // Slug uniqueness
        if ($this->slugExists($data['slug'])) {
            $errors[] = "Slug '{$data['slug']}' already exists";
        }

        return new ValidationResult(empty($errors), $errors);
    }
}
```

**2. Resume Mechanism**
```php
// Store progress in database
CREATE TABLE import_progress (
    id SERIAL PRIMARY KEY,
    entity_type VARCHAR(50),
    total_count INT,
    imported_count INT,
    failed_count INT,
    last_batch_id INT,
    status VARCHAR(20),
    started_at TIMESTAMP,
    updated_at TIMESTAMP
);

// Resume from last successful batch
$progress = $this->getImportProgress('articles');
$startBatch = $progress->last_batch_id + 1;
```

**3. Rollback Strategy**
```php
// Transaction per batch
$this->entityManager->beginTransaction();

try {
    // Import batch
    foreach ($batch as $article) {
        $this->importArticle($article);
    }

    $this->entityManager->flush();
    $this->entityManager->commit();

} catch (\Exception $e) {
    $this->entityManager->rollback();
    $this->logger->error('Batch import failed', [
        'batch_id' => $batchId,
        'error' => $e->getMessage()
    ]);

    // Continue with next batch or halt
    return false;
}
```

### 4.3 Disaster Recovery Plan

**If import fails catastrophically:**

1. **Immediate Actions (< 5 minutes)**
   ```bash
   # Stop import process
   symfony console messenger:stop-workers

   # Database snapshot
   pg_dump -h localhost -U deschide_user deschide_news > emergency_backup_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Assessment (5-15 minutes)**
   ```sql
   -- Check imported counts
   SELECT
       'articles' as entity, COUNT(*) as count FROM articles
   UNION ALL
   SELECT 'categories', COUNT(*) FROM categories
   UNION ALL
   SELECT 'authors', COUNT(*) FROM authors
   UNION ALL
   SELECT 'images', COUNT(*) FROM images;

   -- Check for orphaned records
   SELECT COUNT(*) FROM articles WHERE category_id NOT IN (SELECT id FROM categories);
   SELECT COUNT(*) FROM articles WHERE author_id NOT IN (SELECT id FROM authors);
   ```

3. **Recovery Options**
   - **Option A:** Rollback to pre-import backup (full reset)
   - **Option B:** Clean partial import and resume from last good batch
   - **Option C:** Manual data correction + continue import

4. **Clean Partial Import Script**
   ```sql
   -- Delete all imported data (in reverse dependency order)
   DELETE FROM article_images;
   DELETE FROM translations WHERE translatable_id IN (SELECT id FROM articles);
   DELETE FROM articles;
   DELETE FROM images;
   DELETE FROM authors WHERE id > 1000; -- Preserve existing
   DELETE FROM categories WHERE id > 100; -- Preserve existing

   -- Reset sequences
   ALTER SEQUENCE articles_id_seq RESTART WITH 1;
   ALTER SEQUENCE images_id_seq RESTART WITH 1;

   -- Vacuum database
   VACUUM FULL;
   ```

---

## 5. Success Metrics & Validation

### 5.1 Import Success Criteria

| Metric | Target | Measurement Method | Red Flag Threshold |
|--------|--------|-------------------|-------------------|
| **Import Success Rate** | ≥99.5% | (Imported / Total) × 100 | <95% |
| **Data Integrity** | 100% | Foreign key validation | Any FK violations |
| **Duplicate Records** | 0 | Unique constraint checks | >0 |
| **Average Import Speed** | 150+ articles/min | Time tracking per batch | <100 articles/min |
| **Memory Usage** | <512 MB peak | Server monitoring | >1 GB |
| **Error Rate** | <0.5% | Failed records / Total | >5% |
| **Translation Coverage** | ≥95% | Translations / Articles | <80% |
| **Image Association Rate** | ≥85% | Articles with images | <70% |

### 5.2 Post-Import Validation Queries

**Data Completeness Checks:**
```sql
-- 1. Total counts verification
SELECT
    'Articles' as entity, COUNT(*) as count, 147000 as expected
FROM articles
UNION ALL
SELECT 'Categories', COUNT(*), 30 FROM categories
UNION ALL
SELECT 'Authors', COUNT(*), 180 FROM authors
UNION ALL
SELECT 'Images', COUNT(*), 90000 FROM images;

-- 2. Foreign key integrity
SELECT
    'Orphaned articles (no category)' as issue,
    COUNT(*) as count
FROM articles WHERE category_id NOT IN (SELECT id FROM categories)
UNION ALL
SELECT
    'Orphaned articles (no author)',
    COUNT(*)
FROM articles WHERE author_id NOT IN (SELECT id FROM authors);

-- 3. Translation coverage
SELECT
    locale,
    COUNT(*) as translation_count,
    (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM articles)) as coverage_percent
FROM translations
WHERE translatable = 'App\\Entity\\Article'
GROUP BY locale
ORDER BY locale;

-- 4. Publication date distribution
SELECT
    EXTRACT(YEAR FROM published_at) as year,
    COUNT(*) as article_count,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM articles), 2) as percent
FROM articles
WHERE published_at IS NOT NULL
GROUP BY year
ORDER BY year DESC;

-- 5. Status distribution
SELECT
    status,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM articles), 2) as percent
FROM articles
GROUP BY status;

-- Expected output:
-- published: ~80,000 (54%)
-- archived: ~67,000 (46%)

-- 6. Image association rate
SELECT
    'Articles with images' as metric,
    COUNT(DISTINCT article_id) as count,
    ROUND(COUNT(DISTINCT article_id) * 100.0 / (SELECT COUNT(*) FROM articles), 2) as percent
FROM article_images;

-- Target: ≥85% of articles should have at least 1 image

-- 7. Slug uniqueness validation
SELECT
    slug,
    COUNT(*) as duplicate_count
FROM articles
GROUP BY slug
HAVING COUNT(*) > 1;

-- Expected: 0 rows (no duplicates)

-- 8. Missing required fields
SELECT
    'Articles missing title' as issue, COUNT(*) as count
FROM articles WHERE title IS NULL OR title = ''
UNION ALL
SELECT 'Articles missing content', COUNT(*)
FROM articles WHERE content IS NULL OR content = ''
UNION ALL
SELECT 'Articles missing slug', COUNT(*)
FROM articles WHERE slug IS NULL OR slug = '';

-- Expected: All counts should be 0
```

**Data Quality Checks:**
```sql
-- 9. Date consistency
SELECT
    'Future published dates' as issue,
    COUNT(*) as count
FROM articles
WHERE published_at > NOW();

-- 10. Invalid locale codes
SELECT DISTINCT locale
FROM translations
WHERE locale NOT IN ('ro', 'en', 'ru');

-- Expected: 0 rows (only valid locales)

-- 11. Broken image paths
SELECT
    id,
    filename,
    path
FROM images
WHERE path IS NULL OR path = '' OR path NOT LIKE '%.%';

-- 12. Article word count distribution (quality check)
SELECT
    CASE
        WHEN LENGTH(content) < 500 THEN 'Very Short (<500 chars)'
        WHEN LENGTH(content) < 2000 THEN 'Short (500-2000)'
        WHEN LENGTH(content) < 5000 THEN 'Medium (2000-5000)'
        ELSE 'Long (>5000)'
    END as content_length,
    COUNT(*) as article_count
FROM articles
GROUP BY
    CASE
        WHEN LENGTH(content) < 500 THEN 'Very Short (<500 chars)'
        WHEN LENGTH(content) < 2000 THEN 'Short (500-2000)'
        WHEN LENGTH(content) < 5000 THEN 'Medium (2000-5000)'
        ELSE 'Long (>5000)'
    END
ORDER BY MIN(LENGTH(content));
```

### 5.3 Automated Validation Script

```php
// src/Command/ValidateImportCommand.php
class ValidateImportCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Import Validation Report');

        // 1. Count validation
        $expectedCounts = [
            'articles' => 147000,
            'categories' => 30,
            'authors' => 180,
            'images' => 90000,
        ];

        $results = [];
        foreach ($expectedCounts as $entity => $expected) {
            $actual = $this->countEntities($entity);
            $results[] = [
                $entity,
                $expected,
                $actual,
                $this->calculateVariance($expected, $actual),
                $this->getStatus($expected, $actual)
            ];
        }

        $io->table(
            ['Entity', 'Expected', 'Actual', 'Variance', 'Status'],
            $results
        );

        // 2. Integrity checks
        $io->section('Data Integrity');
        $integrityIssues = $this->checkIntegrity();

        if (empty($integrityIssues)) {
            $io->success('All integrity checks passed!');
        } else {
            $io->error('Integrity issues found:');
            $io->listing($integrityIssues);
            return Command::FAILURE;
        }

        // 3. Quality metrics
        $io->section('Quality Metrics');
        $qualityMetrics = $this->calculateQualityMetrics();
        $io->table(
            ['Metric', 'Value', 'Target', 'Status'],
            $qualityMetrics
        );

        return Command::SUCCESS;
    }
}
```

---

## 6. Timeline & Project Phases

### 6.1 Detailed Timeline (12-Day Sprint)

| Phase | Days | Tasks | Deliverables | Go/No-Go Gate |
|-------|------|-------|--------------|---------------|
| **PHASE 0: Preparation** | 2 days | - Environment setup<br>- Backup strategy<br>- Dry-run testing | - Import scripts tested<br>- Database backups<br>- Rollback plan | ✓ All scripts pass dry-run |
| **PHASE 1: Foundation** | 1 day | - Import categories<br>- Import authors<br>- Validate relationships | - 30 categories<br>- 180 authors<br>- Validation report | ✓ 100% success rate |
| **PHASE 2: Media** | 3 days | - Import images<br>- Start thumbnail queue<br>- Validate uploads | - 90,000 images<br>- Background thumbnails | ✓ ≥95% image success |
| **PHASE 3: Content** | 4 days | - Import articles (batches)<br>- Import translations<br>- Link images | - 147,000 articles<br>- 294,000 translations<br>- Image relations | ✓ ≥99% article success |
| **PHASE 4: Validation** | 1 day | - Run validation suite<br>- Fix data issues<br>- Performance testing | - Validation report<br>- Issue resolution log | ✓ All critical issues resolved |
| **PHASE 5: Post-Import** | 1 day | - Archive old articles<br>- Rebuild indexes<br>- Clear caches | - Archive status set<br>- Optimized database | ✓ Performance targets met |

**Total Duration:** 12 days (with buffer for issues)
**Recommended Window:** 14 days (includes 2-day buffer)

### 6.2 Daily Progress Tracking

```markdown
## Daily Import Report Template

**Date:** YYYY-MM-DD
**Phase:** [Phase Name]
**Lead:** [Person Name]

### Progress Summary
- Total imported today: X,XXX articles
- Cumulative total: XXX,XXX / 147,000 (XX%)
- Success rate: XX.X%
- Average speed: XXX articles/min

### Issues Encountered
1. [Issue description] - [Resolution status]
2. ...

### Metrics
| Metric | Target | Actual | Status |
|--------|--------|--------|--------|
| Import speed | 150/min | XXX/min | ✓/✗ |
| Error rate | <0.5% | X.X% | ✓/✗ |
| Memory usage | <512 MB | XXX MB | ✓/✗ |

### Next Steps
- [ ] Task 1
- [ ] Task 2

### Blockers
- None / [Blocker description]
```

### 6.3 Environment-Specific Timelines

**Development Environment:**
- **Purpose:** Testing import scripts, validation logic
- **Volume:** Sample dataset (1,000 articles)
- **Timeline:** 2-3 days for script development + testing
- **Success Criteria:** 100% success rate on sample data

**Staging Environment:**
- **Purpose:** Full dress rehearsal with complete dataset
- **Volume:** Full 147,000 articles
- **Timeline:** 12 days (full import + validation)
- **Success Criteria:** ≥99.5% success rate, all validation passed

**Production Environment:**
- **Purpose:** Final production import
- **Volume:** Full 147,000 articles
- **Timeline:** 10 days (optimized scripts from staging)
- **Success Criteria:** ≥99.8% success rate, zero critical issues
- **Maintenance Window:** Weekend deployment recommended

---

## 7. Resource Requirements

### 7.1 Infrastructure

| Resource | Requirement | Current Capacity | Scaling Needed |
|----------|-------------|------------------|----------------|
| **Database** | PostgreSQL 17+ | ✓ Available | Temp increase `work_mem` to 256MB |
| **Storage** | 400 GB free space | Check required | Add if <500 GB available |
| **RAM** | 8 GB minimum | Check server | Recommended 16 GB during import |
| **CPU** | 4+ cores | Check server | Background thumbnail generation |
| **PHP Memory** | 1 GB limit | Default 512 MB | Increase to 1-2 GB |
| **PHP Max Execution** | 600s (10 min) | Default 60s | Increase temporarily |
| **Workers** | 4-6 Messenger workers | To be configured | For thumbnail queue |

### 7.2 Human Resources

| Role | Responsibility | Time Commitment | Critical Phases |
|------|---------------|-----------------|-----------------|
| **Backend Developer** | Script development, debugging | Full-time (12 days) | All phases |
| **Database Admin** | Performance tuning, backups | Part-time (4 hours/day) | Phase 0, 3, 5 |
| **QA Engineer** | Validation testing | Full-time (6 days) | Phase 4-5 |
| **Project Manager** | Coordination, reporting | Part-time (2 hours/day) | All phases |
| **DevOps Engineer** | Infrastructure scaling | On-call | Phase 0, 2, 3 |

### 7.3 Configuration Changes

**PHP Configuration (php.ini):**
```ini
memory_limit = 2048M          # Increase from 512M
max_execution_time = 600      # 10 minutes (from 60s)
post_max_size = 100M          # For image uploads
upload_max_filesize = 50M     # For image uploads
```

**PostgreSQL Configuration (postgresql.conf):**
```ini
work_mem = 256MB              # Increase from 4MB (for sorting/joins)
maintenance_work_mem = 512MB  # For index creation
max_connections = 200         # Ensure enough connections
shared_buffers = 2GB          # Increase if server has RAM
effective_cache_size = 6GB    # 3/4 of server RAM
```

**Symfony Configuration (messenger.yaml):**
```yaml
framework:
    messenger:
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    auto_setup: false
                retry_strategy:
                    max_retries: 3
                    delay: 1000           # 1 second
                    multiplier: 2         # Exponential backoff
                    max_delay: 0

        routing:
            'App\Message\GenerateThumbnailMessage': async
```

---

## 8. Command Execution Plan

### 8.1 Import Command Sequence

```bash
#!/bin/bash
# import_all.sh - Master import script

set -e  # Exit on error

echo "==================================="
echo "Deschide News - Full Import Script"
echo "==================================="
echo ""

# Configuration
BACKEND_DIR="/var/www/deschide_news_app/apps/backend"
LOG_DIR="/var/www/deschide_news_app/logs/import"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Create log directory
mkdir -p "$LOG_DIR"

cd "$BACKEND_DIR"

# PHASE 0: Pre-Import Validation
echo "[PHASE 0] Pre-Import Validation..."
symfony console app:validate:newscoop-connection 2>&1 | tee "$LOG_DIR/phase0_validation_$TIMESTAMP.log"

# PHASE 1: Foundation Data
echo "[PHASE 1] Importing Foundation Data..."

echo "  → Importing Categories..."
symfony console app:import:categories 2>&1 | tee "$LOG_DIR/phase1_categories_$TIMESTAMP.log"

echo "  → Importing Authors..."
symfony console app:import:authors 2>&1 | tee "$LOG_DIR/phase1_authors_$TIMESTAMP.log"

echo "[PHASE 1] Complete. Validating..."
symfony console app:validate:foundation 2>&1 | tee "$LOG_DIR/phase1_validation_$TIMESTAMP.log"

# PHASE 2: Media Assets
echo "[PHASE 2] Importing Media Assets..."

echo "  → Importing Images (this will take several hours)..."
symfony console app:import:images --batch-size=1000 2>&1 | tee "$LOG_DIR/phase2_images_$TIMESTAMP.log"

echo "  → Starting thumbnail generation queue..."
symfony console messenger:consume async -vv --time-limit=3600 &
WORKER_PID=$!

echo "[PHASE 2] Images imported. Thumbnails generating in background (PID: $WORKER_PID)"

# PHASE 3: Articles & Translations
echo "[PHASE 3] Importing Articles & Translations..."

echo "  → Importing Articles (batch processing, ~40 hours estimated)..."
symfony console app:import:articles --batch-size=500 2>&1 | tee "$LOG_DIR/phase3_articles_$TIMESTAMP.log"

echo "  → Importing Translations..."
symfony console app:import:translations --batch-size=1000 2>&1 | tee "$LOG_DIR/phase3_translations_$TIMESTAMP.log"

echo "[PHASE 3] Complete. Validating..."
symfony console app:validate:articles 2>&1 | tee "$LOG_DIR/phase3_validation_$TIMESTAMP.log"

# PHASE 4: Validation
echo "[PHASE 4] Running Full Validation Suite..."
symfony console app:validate:import-complete 2>&1 | tee "$LOG_DIR/phase4_full_validation_$TIMESTAMP.log"

# Check validation exit code
if [ $? -ne 0 ]; then
    echo "❌ VALIDATION FAILED! Check logs in $LOG_DIR"
    exit 1
fi

echo "✅ Validation passed!"

# PHASE 5: Post-Import Optimization
echo "[PHASE 5] Post-Import Optimization..."

echo "  → Archiving old articles (2016-2020)..."
symfony console app:archive:old-articles --years=4 2>&1 | tee "$LOG_DIR/phase5_archive_$TIMESTAMP.log"

echo "  → Rebuilding database indexes..."
symfony console doctrine:schema:update --force 2>&1 | tee "$LOG_DIR/phase5_indexes_$TIMESTAMP.log"

echo "  → Clearing caches..."
symfony console cache:clear --env=prod

echo "  → Running database vacuum..."
psql -U deschide_user -d deschide_news -c "VACUUM ANALYZE;"

echo ""
echo "==================================="
echo "✅ IMPORT COMPLETE!"
echo "==================================="
echo ""
echo "Summary:"
echo "  - Categories: Check phase1 logs"
echo "  - Authors: Check phase1 logs"
echo "  - Images: Check phase2 logs"
echo "  - Articles: Check phase3 logs"
echo "  - Validation: Check phase4 logs"
echo ""
echo "Logs location: $LOG_DIR"
echo ""
echo "Next steps:"
echo "  1. Review validation report"
echo "  2. Test frontend functionality"
echo "  3. Performance testing"
echo ""
```

### 8.2 Individual Command Reference

**Categories Import:**
```bash
symfony console app:import:categories \
    --dry-run           # Test mode (no DB writes)
    --verbose           # Show detailed output

# Expected output:
# Importing categories...
# ✓ Imported 30 categories
# ✓ 0 errors
# Time: 30 seconds
```

**Authors Import:**
```bash
symfony console app:import:authors \
    --batch-size=50     # Process 50 authors at a time
    --dry-run

# Expected output:
# Importing authors...
# ✓ Imported 180 authors
# ✓ 0 errors
# Time: 2 minutes
```

**Images Import:**
```bash
symfony console app:import:images \
    --batch-size=1000   # Process 1000 images per batch
    --skip-existing     # Skip already imported images
    --retry-failed      # Retry previously failed uploads

# Expected output:
# Importing images...
# [============================] 90000/90000 (100%)
# ✓ Successfully imported: 89,500
# ⚠ Failed: 500 (see error log)
# Time: 15 hours
```

**Articles Import:**
```bash
symfony console app:import:articles \
    --batch-size=500        # 500 articles per batch
    --start-batch=0         # Resume from batch N
    --year=2023             # Import only specific year
    --category=politics     # Import only specific category
    --with-translations     # Import translations in same batch

# Expected output:
# Importing articles...
# Batch 1/294: [============================] 500/500 (100%) - 5 min
# Batch 2/294: [============================] 500/500 (100%) - 5 min
# ...
# Batch 294/294: [============================] 120/120 (100%) - 2 min
#
# ✓ Successfully imported: 146,850 articles
# ✓ Translations: 293,700
# ⚠ Skipped (validation failed): 150
# Time: 38 hours
```

**Validation Command:**
```bash
symfony console app:validate:import-complete \
    --detailed          # Show detailed validation
    --export-report     # Export to JSON/CSV

# Expected output:
# Validation Report
# =================
#
# Entity Counts:
#   ✓ Categories: 30 / 30 (100%)
#   ✓ Authors: 180 / 180 (100%)
#   ✓ Images: 89,500 / 90,000 (99.4%)
#   ✓ Articles: 146,850 / 147,000 (99.9%)
#
# Integrity Checks:
#   ✓ Foreign keys: All valid
#   ✓ Unique constraints: No violations
#   ✓ Translation coverage: 95.2% (ro), 94.8% (en), 92.3% (ru)
#
# Quality Metrics:
#   ✓ Articles with images: 87.3%
#   ✓ Average content length: 2,450 chars
#   ⚠ Articles missing images: 12.7% (18,650 articles)
#
# Overall Status: ✅ PASS (with minor warnings)
```

---

## 9. Monitoring & Reporting

### 9.1 Real-Time Monitoring Dashboard

**Key Metrics to Track:**

```
┌─────────────────────────────────────────────────────────────┐
│                   IMPORT PROGRESS DASHBOARD                 │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Current Phase: PHASE 3 - Articles Import                  │
│  Status: ⏳ IN PROGRESS                                     │
│  Started: 2025-11-29 08:00:00                              │
│  Elapsed: 15h 23m                                          │
│                                                             │
│  ┌───────────────────────────────────────────────────────┐ │
│  │  Articles Imported                                    │ │
│  │  [████████████████████░░░░░░] 85,500 / 147,000 (58%) │ │
│  │  Current Batch: 171/294                               │ │
│  │  Speed: 165 articles/min (avg)                        │ │
│  │  ETA: 18h 15m remaining                               │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
│  Performance Metrics:                                       │
│  ├─ Memory Usage: 425 MB / 2048 MB (21%) ✓                │
│  ├─ CPU Usage: 68% ✓                                       │
│  ├─ Database Connections: 12 / 200 ✓                       │
│  └─ Disk I/O: 45 MB/s ✓                                    │
│                                                             │
│  Error Summary:                                             │
│  ├─ Validation errors: 127 (0.15%) ✓                       │
│  ├─ FK violations: 0 ✓                                      │
│  ├─ Timeout errors: 3 (recovered) ⚠                        │
│  └─ Critical errors: 0 ✓                                    │
│                                                             │
│  Recent Activity:                                           │
│  ├─ [15:22:18] Batch 171 completed: 500 articles           │
│  ├─ [15:22:15] Flushed entities to DB                      │
│  ├─ [15:22:10] Memory cleared (gc_collect_cycles)          │
│  └─ [15:22:05] Starting batch 171/294                      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### 9.2 Automated Alerting

**Alert Thresholds:**

| Condition | Severity | Action |
|-----------|----------|--------|
| Error rate >5% | 🔴 CRITICAL | Stop import, notify team |
| Memory usage >90% | 🟡 WARNING | Reduce batch size |
| Import speed <100/min | 🟡 WARNING | Investigate bottleneck |
| Database connections >150 | 🟡 WARNING | Check for connection leaks |
| Any critical error | 🔴 CRITICAL | Immediate rollback |
| Disk space <100 GB | 🔴 CRITICAL | Stop import |

**Notification Channels:**
- Email: team@deschide.md
- Slack: #deschide-import-status
- SMS: Critical errors only

### 9.3 Progress Tracking Database

```sql
-- Table for tracking import progress
CREATE TABLE import_progress (
    id SERIAL PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    phase VARCHAR(20) NOT NULL,
    total_count INTEGER NOT NULL,
    imported_count INTEGER DEFAULT 0,
    failed_count INTEGER DEFAULT 0,
    skipped_count INTEGER DEFAULT 0,
    current_batch INTEGER DEFAULT 0,
    total_batches INTEGER NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    estimated_completion TIMESTAMP,
    average_speed DECIMAL(10,2), -- records per minute
    error_messages TEXT[],
    last_error_at TIMESTAMP,
    metadata JSONB,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Index for quick lookups
CREATE INDEX idx_import_progress_entity ON import_progress(entity_type);
CREATE INDEX idx_import_progress_status ON import_progress(status);

-- Query current status
SELECT
    entity_type,
    imported_count || ' / ' || total_count as progress,
    ROUND((imported_count * 100.0 / total_count), 2) || '%' as percent,
    current_batch || ' / ' || total_batches as batches,
    status,
    ROUND(average_speed, 2) || ' /min' as speed,
    CASE
        WHEN estimated_completion IS NOT NULL
        THEN estimated_completion::TEXT
        ELSE 'Calculating...'
    END as eta
FROM import_progress
WHERE status != 'completed'
ORDER BY phase, entity_type;
```

---

## 10. Business Recommendations

### 10.1 Strategic Recommendations

**RECOMMENDATION 1: Phased Rollout**
- **Action:** Import in 3 phases (dev → staging → production)
- **Rationale:** Minimize production risk, validate scripts thoroughly
- **Timeline:** +1 week total, but 90% risk reduction
- **Cost:** Additional developer time (~40 hours)
- **ROI:** Prevents catastrophic production failure ($$$)

**RECOMMENDATION 2: Archive Strategy**
- **Action:** Implement archive status during import (not post-import)
- **Rationale:** Set correct status from day 1, avoid bulk update later
- **Implementation:**
  ```php
  // During article import
  if ($article->publishedAt < $archiveThreshold) {
      $article->setStatus(ArticleStatus::ARCHIVED);
  } else {
      $article->setStatus(ArticleStatus::PUBLISHED);
  }
  ```
- **Benefit:** Immediate performance optimization (queries on 80k vs 147k)

**RECOMMENDATION 3: Incremental Import**
- **Action:** Import recent content first (2024 → 2023 → ... → 2016)
- **Rationale:**
  - Business value front-loaded (recent content = more traffic)
  - Early validation of import process
  - Ability to launch with partial data if needed
- **Alternative Sequence:**
  1. Import 2024 articles (18,000) - Go live with current year
  2. Import 2023-2022 (39,000) - Add recent archive
  3. Import 2021-2016 (90,000) - Complete historical archive

**RECOMMENDATION 4: Parallel Processing**
- **Action:** Run image import in parallel with article import
- **Implementation:**
  - Images have no dependency on articles
  - Can be imported simultaneously
  - Reduces total timeline by ~15 hours
- **Risk:** Higher resource usage, needs monitoring
- **Mitigation:** Resource limits on each process

### 10.2 Go/No-Go Decision Framework

**PRE-IMPORT CHECKLIST (Go/No-Go Gate)**

Must achieve 100% on critical items:

**Critical (Must Pass):**
- [ ] Database backup completed and verified
- [ ] Rollback procedure tested
- [ ] Dry-run import successful (sample 1,000 articles)
- [ ] Server resources adequate (RAM, disk, CPU)
- [ ] Newscoop API connection stable
- [ ] Import scripts validated (no syntax errors)
- [ ] Team availability confirmed (12 days)
- [ ] Monitoring dashboard operational

**Important (Should Pass ≥75%):**
- [ ] Performance benchmarks met (150+ articles/min in dry-run)
- [ ] Validation scripts tested
- [ ] Error handling tested
- [ ] Resume mechanism tested
- [ ] Alert system configured
- [ ] Documentation complete

**Nice-to-Have:**
- [ ] Automated testing suite
- [ ] Slack integration for notifications
- [ ] Dashboard UI polished

**GO Decision:** All critical + ≥6/8 important items
**NO-GO Decision:** Any critical item failed, <5 important items

### 10.3 Cost-Benefit Analysis

| Scenario | Cost | Benefit | ROI |
|----------|------|---------|-----|
| **Manual Import** | $0 (internal time) | Slow, error-prone | ❌ Not recommended |
| **Automated Import (Recommended)** | ~$5,000 (80h dev @ $60/h) | Fast, reliable, repeatable | ✅ 300% ROI |
| **Hire External Agency** | $15,000-25,000 | Fast, minimal internal time | ⚠ ROI depends on urgency |
| **Skip Old Content (2016-2019)** | $0 saved | Lose 43,000 articles, SEO impact | ❌ Not recommended |

**Recommended Investment:** $5,000-7,000 for automated import + validation + monitoring

**Expected Business Value:**
- **SEO:** 147,000 indexed pages = ~$50,000/year in organic traffic value
- **Content Archive:** Historical reference, brand authority
- **User Engagement:** Complete content catalog = higher retention
- **Platform Migration:** One-time cost, future-proof platform

**Break-Even:** ~2 months post-launch (based on projected ad revenue + subscriptions)

---

## 11. Conclusion & Next Steps

### 11.1 Executive Summary

**Import Scope:**
- **Total Articles:** 147,000 (2016-2024)
- **Supporting Data:** 30 categories, 180 authors, 90,000 images
- **Timeline:** 12-14 days (staged import + validation)
- **Success Criteria:** ≥99.5% import success rate, zero critical errors

**Critical Success Factors:**
1. **Proper Import Sequence:** Categories → Authors → Images → Articles → Translations
2. **Batch Processing:** 500 articles per batch (optimal balance)
3. **Validation at Each Stage:** Automated validation scripts
4. **Resource Management:** Memory limits, transaction handling
5. **Rollback Capability:** Database snapshots, resume mechanism

**Risks & Mitigation:**
- **Data corruption:** Mitigated by validation pipeline + transactions
- **Memory exhaustion:** Mitigated by batch processing + entity clear
- **Timeout errors:** Mitigated by increased limits + resume capability
- **Foreign key violations:** Mitigated by strict import order

**Business Impact:**
- **Immediate:** Complete content catalog available post-import
- **Short-term (3 months):** SEO boost from 147k indexed pages
- **Long-term (12 months):** Content archive drives organic traffic growth

### 11.2 Recommended Action Plan

**IMMEDIATE (This Week):**
1. ✅ **Approve this analysis** - Stakeholder sign-off
2. ✅ **Allocate resources** - Assign backend developer (full-time, 12 days)
3. ✅ **Prepare infrastructure** - Increase server resources (RAM, disk)
4. ✅ **Create backups** - Full database snapshot

**SHORT-TERM (Next Week):**
5. ✅ **Development phase** - Build import scripts + validation
6. ✅ **Dry-run testing** - Test on sample data (1,000 articles)
7. ✅ **Staging import** - Full import on staging environment
8. ✅ **Review results** - Analyze staging import, fix issues

**MEDIUM-TERM (Week 3-4):**
9. ✅ **Production import** - Execute full import on production
10. ✅ **Validation & QA** - Comprehensive validation suite
11. ✅ **Performance tuning** - Optimize database, caches
12. ✅ **Launch** - Go live with complete content catalog

### 11.3 Approval Requirements

**Approvals Needed:**

| Stakeholder | Approval Needed | Deadline |
|-------------|-----------------|----------|
| **CTO** | Technical approach, resource allocation | 2025-12-02 |
| **Product Owner** | Timeline, scope, prioritization | 2025-12-02 |
| **DevOps Lead** | Infrastructure changes, monitoring | 2025-12-03 |
| **QA Lead** | Validation strategy, acceptance criteria | 2025-12-03 |
| **Business Owner** | Budget approval ($5-7k) | 2025-12-04 |

**Decision Deadline:** 2025-12-04
**Import Start Date:** 2025-12-09 (Monday)
**Expected Completion:** 2025-12-20 (Friday)

---

## Appendix A: Technical Specifications

### A.1 Server Requirements

**Minimum Specifications:**
- **OS:** Ubuntu 22.04 LTS (or WSL2 on Windows)
- **PHP:** 8.4+
- **PostgreSQL:** 17+
- **RAM:** 16 GB (8 GB minimum)
- **Storage:** 500 GB free space
- **CPU:** 4 cores minimum (8 cores recommended)
- **Network:** Stable connection to Newscoop API

**Software Dependencies:**
```bash
# PHP Extensions
php8.4-cli
php8.4-fpm
php8.4-pgsql
php8.4-mbstring
php8.4-xml
php8.4-curl
php8.4-intl
php8.4-gd
php8.4-zip

# Symfony CLI
symfony-cli

# PostgreSQL Client
postgresql-client-17

# Image Processing
imagemagick
webp
```

### A.2 Database Schema Changes

**New Tables Created During Import:**
```sql
-- Import progress tracking
CREATE TABLE import_progress (...);

-- Import error log
CREATE TABLE import_errors (
    id SERIAL PRIMARY KEY,
    entity_type VARCHAR(50),
    entity_id INTEGER,
    error_type VARCHAR(100),
    error_message TEXT,
    stack_trace TEXT,
    raw_data JSONB,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Legacy ID mapping (for reference)
CREATE TABLE legacy_id_mapping (
    id SERIAL PRIMARY KEY,
    entity_type VARCHAR(50),
    legacy_id INTEGER,
    new_id INTEGER,
    created_at TIMESTAMP DEFAULT NOW()
);
```

### A.3 Import Script Templates

**Base Import Service:**
```php
abstract class BaseImportService
{
    protected function importBatch(array $batch, string $entityType): ImportResult
    {
        $result = new ImportResult();
        $this->entityManager->beginTransaction();

        try {
            foreach ($batch as $index => $data) {
                try {
                    // Validate
                    $validation = $this->validator->validate($data);
                    if (!$validation->isValid()) {
                        $result->addError($data, $validation->getErrors());
                        continue;
                    }

                    // Transform
                    $entity = $this->transformer->transform($data);

                    // Persist
                    $this->entityManager->persist($entity);
                    $result->incrementImported();

                    // Periodic flush
                    if ($index % 50 === 0) {
                        $this->entityManager->flush();
                        $this->entityManager->clear();
                        gc_collect_cycles();
                    }

                } catch (\Exception $e) {
                    $result->addError($data, $e->getMessage());
                    $this->logger->error("Import error", [
                        'entity_type' => $entityType,
                        'data' => $data,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Final flush
            $this->entityManager->flush();
            $this->entityManager->commit();

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw new ImportException("Batch import failed: " . $e->getMessage());
        }

        return $result;
    }
}
```

---

## Appendix B: Glossary

**Key Terms:**

- **Batch Processing:** Importing records in groups (batches) rather than one-by-one
- **Foreign Key:** Database constraint ensuring referential integrity
- **Dry-Run:** Test mode that simulates import without writing to database
- **Entity Manager:** Doctrine ORM component for database operations
- **Flush:** Writing pending database changes to disk
- **Clear:** Freeing memory by detaching entities from entity manager
- **Validation:** Checking data quality before import
- **Rollback:** Reverting database to previous state
- **Archive Status:** Article status for content older than 4 years
- **Translation Coverage:** Percentage of articles with translations
- **Success Rate:** (Successful imports / Total attempts) × 100

---

**Report Prepared By:** Claude Code - Business Analyst
**Date:** 2025-11-29
**Version:** 1.0
**Next Review:** After staging import completion

**Approval Signatures:**

- [ ] CTO: ___________________ Date: ___________
- [ ] Product Owner: ___________________ Date: ___________
- [ ] DevOps Lead: ___________________ Date: ___________
- [ ] QA Lead: ___________________ Date: ___________

---

**END OF REPORT**
