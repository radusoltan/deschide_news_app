# Newscoop Import Execution Plan

**Project:** Deschide News App - Legacy Article Import
**Date:** 2025-11-29
**Status:** 📋 READY FOR EXECUTION

---

## Executive Summary

This plan details the import of **173,670 articles** from the legacy Newscoop CMS into the new Deschide News App. The import leverages existing infrastructure (13 import commands, ARCHIVED status support) and requires minimal code modifications.

### Key Metrics

| Metric | Value |
|--------|-------|
| Total Articles | 173,670 |
| Romanian (ro) | 144,659 (83.3%) |
| Russian (ru) | 27,865 (16.0%) |
| English (en) | 1,146 (0.7%) |
| Current DB Articles | 81 |
| Estimated Duration | 12-14 days |
| Risk Level | Low (existing infrastructure) |

---

## Phase 1: Pre-Import Preparation (Day 1-2)

### Task 1.1: Database Backup
```bash
# Backup current PostgreSQL database
pg_dump -U deschide_user -h localhost deschide_news > ~/backups/deschide_news/pre_import_$(date +%Y%m%d_%H%M%S).sql

# Backup Newscoop source (read-only verification)
mysqldump -u root -p newscoop Articles ArticleAuthors ArticleImages > ~/backups/newscoop_export_$(date +%Y%m%d).sql
```

### Task 1.2: Verify Connections
```bash
cd /var/www/deschide_news_app/apps/backend

# Test Newscoop MySQL connection
symfony console app:test:newscoop-connection

# Verify PostgreSQL connection
symfony console doctrine:query:sql "SELECT COUNT(*) FROM article"
```

### Task 1.3: Configuration Review
**File:** `apps/backend/config/packages/doctrine.yaml`

Verify secondary connection exists:
```yaml
doctrine:
    dbal:
        connections:
            newscoop:
                driver: pdo_mysql
                host: '%env(NEWSCOOP_DB_HOST)%'
                port: '%env(NEWSCOOP_DB_PORT)%'
                dbname: '%env(NEWSCOOP_DB_NAME)%'
                user: '%env(NEWSCOOP_DB_USER)%'
                password: '%env(NEWSCOOP_DB_PASSWORD)%'
                charset: utf8mb4
```

### Task 1.4: Environment Variables
**File:** `apps/backend/.env.local`

Required variables:
```bash
NEWSCOOP_DB_HOST=localhost
NEWSCOOP_DB_PORT=3306
NEWSCOOP_DB_NAME=newscoop
NEWSCOOP_DB_USER=newscoop_user
NEWSCOOP_DB_PASSWORD=your_password
```

---

## Phase 2: Category & Author Import (Day 3-4)

### Task 2.1: Import Categories
```bash
# Import categories from Newscoop (creates translations for ro, ru, en)
symfony console app:import:categories --dry-run
symfony console app:import:categories

# Verify
symfony console doctrine:query:sql "SELECT COUNT(*) FROM category"
```

**Expected:** ~50-100 categories with translations

### Task 2.2: Import Authors
```bash
# Import authors with multilingual data
symfony console app:import:authors --dry-run
symfony console app:import:authors

# Verify
symfony console doctrine:query:sql "SELECT COUNT(*) FROM author"
```

**Expected:** ~200-500 authors

### Task 2.3: Validate Relationships
```bash
# Check category hierarchy
symfony console doctrine:query:sql "SELECT c.id, ct.name, ct.locale FROM category c JOIN category_translation ct ON c.id = ct.translatable_id ORDER BY c.id LIMIT 20"

# Check author data
symfony console doctrine:query:sql "SELECT id, first_name, last_name, slug FROM author LIMIT 20"
```

---

## Phase 3: Image Import (Day 5-7)

### Task 3.1: Import Images (Batch Mode)
```bash
# Phase 1: Import image metadata only (fast)
symfony console app:import:images --metadata-only --batch-size=1000

# Phase 2: Copy image files
symfony console app:import:images --files-only --batch-size=500

# Verify counts
symfony console doctrine:query:sql "SELECT COUNT(*) FROM image"
```

**Expected:** ~50,000-100,000 images

### Task 3.2: Generate Thumbnails (Async)
```bash
# Start thumbnail generation (async via Messenger)
symfony console app:import:generate-thumbnails --batch-size=100

# Monitor progress
symfony console messenger:stats

# Start workers (multiple terminals)
symfony console messenger:consume async -vv --time-limit=3600
```

**Thumbnail Profiles Generated:**
- hero_big (1920x1080)
- hero_small (800x600)
- article_main (1600x900)
- card_large (800x600)
- card_medium (600x400)
- card_small (400x300)
- list_item (300x200)

### Task 3.3: Verify Image Storage
```bash
# Check storage usage
du -sh /var/www/deschide_news_app/apps/backend/public/uploads/images/
du -sh /var/www/deschide_news_app/apps/backend/public/uploads/thumbnails/

# Verify CDN accessibility
curl -I http://127.0.0.1:8082/uploads/images/sample_image.jpg
```

---

## Phase 4: Article Import - Main Content (Day 8-11)

### Task 4.1: Import Strategy by Date Range

**Priority 1: Recent Articles (2020-2025)** - Active content
```bash
symfony console app:import:articles \
    --date-from="2020-01-01" \
    --date-to="2025-12-31" \
    --status=published \
    --batch-size=500
```

**Priority 2: Archive Articles (2015-2019)** - Set as ARCHIVED
```bash
symfony console app:import:articles \
    --date-from="2015-01-01" \
    --date-to="2019-12-31" \
    --status=archived \
    --archive-reason=OLD_CONTENT \
    --batch-size=500
```

**Priority 3: Legacy Articles (pre-2015)** - Set as ARCHIVED
```bash
symfony console app:import:articles \
    --date-from="2000-01-01" \
    --date-to="2014-12-31" \
    --status=archived \
    --archive-reason=OLD_CONTENT \
    --batch-size=500
```

### Task 4.2: Import by Language (Alternative Approach)
```bash
# Romanian articles (largest set - run overnight)
symfony console app:import:articles --locale=ro --batch-size=1000

# Russian articles
symfony console app:import:articles --locale=ru --batch-size=1000

# English articles
symfony console app:import:articles --locale=en --batch-size=500
```

### Task 4.3: Progress Monitoring
```bash
# Real-time progress
watch -n 5 "symfony console doctrine:query:sql \"SELECT status, COUNT(*) as count FROM article GROUP BY status\""

# Check for errors
tail -f var/log/import.log | grep -E "(ERROR|WARN)"

# Memory usage
ps aux | grep php
```

---

## Phase 5: Translation Import (Day 12-13)

### Task 5.1: Import Article Translations
```bash
# Import translations for existing articles
symfony console app:import:translations --entity=Article --batch-size=500

# Verify translation coverage
symfony console doctrine:query:sql "
    SELECT locale, COUNT(*) as count
    FROM article_translation
    GROUP BY locale
"
```

### Task 5.2: Import Category Translations
```bash
symfony console app:import:translations --entity=Category
```

### Task 5.3: Validate Translations
```bash
# Check Romanian translations
curl -H "Accept-Language: ro" http://127.0.0.1:8081/api/articles?itemsPerPage=5

# Check Russian translations
curl -H "Accept-Language: ru" http://127.0.0.1:8081/api/articles?itemsPerPage=5

# Check English translations
curl -H "Accept-Language: en" http://127.0.0.1:8081/api/articles?itemsPerPage=5
```

---

## Phase 6: Post-Import Validation (Day 14)

### Task 6.1: Data Integrity Checks
```bash
# Total article count
symfony console doctrine:query:sql "SELECT COUNT(*) FROM article"

# Status distribution
symfony console doctrine:query:sql "
    SELECT status, COUNT(*) as count
    FROM article
    GROUP BY status
    ORDER BY count DESC
"

# Archive reason distribution
symfony console doctrine:query:sql "
    SELECT archive_reason, COUNT(*) as count
    FROM article
    WHERE archive_reason IS NOT NULL
    GROUP BY archive_reason
"

# Articles by year
symfony console doctrine:query:sql "
    SELECT EXTRACT(YEAR FROM published_at) as year, COUNT(*) as count
    FROM article
    WHERE published_at IS NOT NULL
    GROUP BY year
    ORDER BY year DESC
"
```

### Task 6.2: Elasticsearch Indexing
```bash
# Create/update indices
symfony console app:elasticsearch:create-index --force
symfony console app:elasticsearch:create-image-index --force

# Index all articles
symfony console app:elasticsearch:index-articles --batch-size=1000

# Index images
symfony console app:elasticsearch:index-images --batch-size=500

# Verify search
curl -X GET "localhost:9200/deschide_articles/_count"
```

### Task 6.3: API Endpoint Verification
```bash
# Test public articles endpoint
curl "http://127.0.0.1:8081/api/articles?status=published&itemsPerPage=10" | jq '.hydra:totalItems'

# Test archived articles endpoint
curl "http://127.0.0.1:8081/api/archived_articles?itemsPerPage=10" | jq '.hydra:totalItems'

# Test important articles
curl "http://127.0.0.1:8081/api/important_articles" | jq '.hydra:totalItems'

# Test search
curl "http://127.0.0.1:8081/api/articles?search=moldova" | jq '.hydra:totalItems'
```

### Task 6.4: Frontend Verification
```bash
# Start frontend if not running
cd /var/www/deschide_news_app/apps/frontend
pnpm dev

# Verify pages load:
# - Homepage: http://localhost:3005/ro
# - Category page: http://localhost:3005/ro/politica
# - Article page: http://localhost:3005/ro/politica/sample-article
# - Archive page: http://localhost:3005/ro/archive (when implemented)
```

---

## Import Commands Reference

### Available Commands (13 total)

| Command | Purpose | Status |
|---------|---------|--------|
| `app:import:categories` | Import categories | ✅ Ready |
| `app:import:authors` | Import authors | ✅ Ready |
| `app:import:images` | Import images | ✅ Ready |
| `app:import:articles` | Import articles | ✅ Ready |
| `app:import:translations` | Import translations | ✅ Ready |
| `app:import:generate-thumbnails` | Generate thumbnails | ✅ Ready |
| `app:sample-import` | Quick sample (300 articles) | ✅ Ready |
| `app:test:newscoop-connection` | Test DB connection | ✅ Ready |
| `app:test:migration-logger` | Test logging | ✅ Ready |

### Command Options

```bash
# Common options for import commands:
--batch-size=N      # Process N items per batch (default: 100)
--dry-run           # Preview without changes
--force             # Skip confirmation prompts
--date-from         # Filter by date range start
--date-to           # Filter by date range end
--locale            # Filter by language (ro, ru, en)
--status            # Set article status (published, archived)
--archive-reason    # Set archive reason for old articles

# Thumbnail options:
--profile           # Generate specific profile only
--regenerate        # Regenerate existing thumbnails
```

---

## Rollback Procedures

### Full Rollback
```bash
# Stop all workers
symfony console messenger:stop-workers

# Restore PostgreSQL backup
psql -U deschide_user -h localhost deschide_news < ~/backups/deschide_news/pre_import_YYYYMMDD_HHMMSS.sql

# Clear Elasticsearch indices
curl -X DELETE "localhost:9200/deschide_articles"
curl -X DELETE "localhost:9200/deschide_images"

# Clear Redis cache
redis-cli -n 1 FLUSHDB

# Remove uploaded files (if needed)
rm -rf /var/www/deschide_news_app/apps/backend/public/uploads/images/*
rm -rf /var/www/deschide_news_app/apps/backend/public/uploads/thumbnails/*
```

### Partial Rollback (by date)
```bash
# Delete articles imported after specific date
symfony console doctrine:query:sql "
    DELETE FROM article
    WHERE created_at > '2025-11-29 00:00:00'
    AND id NOT IN (SELECT id FROM article WHERE id <= 81)
"
```

---

## Resource Requirements

### Hardware
- **CPU:** 4+ cores recommended for parallel thumbnail generation
- **RAM:** 8GB minimum (16GB recommended)
- **Disk:** 50GB free for images + thumbnails
- **Network:** Stable connection to Newscoop database

### Software
- PHP 8.4 with sufficient memory_limit (512M minimum)
- Symfony Messenger workers (2-4 workers)
- ImageMagick/GD for thumbnail generation

### Time Estimates

| Phase | Duration | Parallelizable |
|-------|----------|----------------|
| Phase 1: Preparation | 2 days | No |
| Phase 2: Categories/Authors | 2 days | Partially |
| Phase 3: Images | 3 days | Yes (workers) |
| Phase 4: Articles | 4 days | Yes (by locale) |
| Phase 5: Translations | 2 days | Yes |
| Phase 6: Validation | 1 day | No |
| **Total** | **12-14 days** | - |

---

## Success Criteria

### Minimum Requirements
- [ ] All 173,670 articles imported
- [ ] Translations available for 3 locales (ro, ru, en)
- [ ] Images accessible via CDN
- [ ] Thumbnails generated for 10 profiles
- [ ] Elasticsearch indices populated
- [ ] API endpoints returning correct data

### Quality Metrics
- [ ] < 1% import errors
- [ ] 100% category mapping
- [ ] > 95% author mapping
- [ ] > 90% image association
- [ ] Search response < 200ms
- [ ] API response < 500ms

---

## Post-Import Tasks

1. **Update ImportantArticlesList** - Configure homepage featured articles
2. **Clear all caches** - Redis, Symfony, CDN
3. **Warm Elasticsearch** - Run search queries to warm cache
4. **Monitor performance** - Check Grafana dashboards
5. **Verify frontend** - Test all public pages
6. **Create sitemap** - Generate XML sitemap for SEO
7. **Notify stakeholders** - Report import completion

---

## Contact & Support

- **Import Issues:** Check `var/log/import.log`
- **Performance Issues:** Check Grafana at http://localhost:3002
- **Database Issues:** PostgreSQL logs at `/var/log/postgresql/`
- **Elasticsearch Issues:** Check http://localhost:9200/_cluster/health

---

**Plan Created:** 2025-11-29
**Status:** Ready for execution
**Estimated Completion:** Day 14 after start

🤖 Generated with Claude Code
