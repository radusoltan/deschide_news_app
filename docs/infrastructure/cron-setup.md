# Cron Jobs Setup - Performance & Analytics

**Created:** 2025-11-01
**Purpose:** Background jobs for statistics aggregation, cache management, and trending articles
**Related:** `docs/performance-analytics-strategy.md`

---

## 📋 Overview

This document describes the cron jobs required for the Performance & Analytics system to function properly. These jobs handle:
- Daily aggregation of statistics from Redis and PostgreSQL
- Trending articles calculation
- Old data cleanup (GDPR compliance)
- Cache warming for popular content

---

## 🕐 Cron Jobs Configuration

### Daily Aggregation (1 AM)

Aggregates yesterday's statistics from Redis and `page_views` into daily summary tables (`article_stats_daily`, `site_stats_daily`).

**Schedule:** Daily at 1:00 AM

```bash
0 1 * * * cd /var/www/deschide_news_app/deschide_backend && symfony console app:stats:aggregate --date=yesterday >> /var/log/deschide/aggregate.log 2>&1
```

**What it does:**
- Reads page views from PostgreSQL `page_views` table
- Counts unique visitors per article
- Calculates average reading time
- Aggregates site-wide statistics (total visits, bounce rate, avg session duration)
- Stores results in `article_stats_daily` and `site_stats_daily` tables

**Monitoring:**
```bash
tail -f /var/log/deschide/aggregate.log
```

**Manual execution:**
```bash
# Aggregate yesterday's data
symfony console app:stats:aggregate

# Aggregate specific date
symfony console app:stats:aggregate --date=2025-10-31

# Dry run (no database writes)
symfony console app:stats:aggregate --dry-run
```

---

### Trending Articles Update (Hourly)

Updates the trending articles list based on views in the last 24 hours.

**Schedule:** Every hour

```bash
0 * * * * cd /var/www/deschide_news_app/deschide_backend && symfony console app:stats:trending --hours=24 >> /var/log/deschide/trending.log 2>&1
```

**What it does:**
- Fetches trending articles from Redis sorted set (`trending:24h`)
- Enriches with article details (title, category)
- Caches results for API endpoints

**Manual execution:**
```bash
# Default (last 24 hours, top 10)
symfony console app:stats:trending

# Custom parameters
symfony console app:stats:trending --hours=12 --limit=20
```

---

### Cleanup Old Data (Monthly)

Deletes raw `page_views` data older than 90 days. **Aggregated statistics are preserved.**

**Schedule:** Monthly on the 1st at 2:00 AM

```bash
0 2 1 * * cd /var/www/deschide_news_app/deschide_backend && symfony console app:stats:cleanup --days=90 --force >> /var/log/deschide/cleanup.log 2>&1
```

**What it does:**
- Counts `page_views` older than 90 days
- Optionally archives to CSV before deletion
- Deletes old records
- Preserves aggregated stats in `article_stats_daily` and `site_stats_daily`

**Manual execution:**
```bash
# Cleanup with prompt
symfony console app:stats:cleanup --days=90

# With archive (export to CSV first)
symfony console app:stats:cleanup --days=90 --archive

# Force (no confirmation)
symfony console app:stats:cleanup --days=90 --force

# Archive location: /var/www/deschide_news_app/deschide_backend/var/archives/
```

---

### Cache Warming (Every 6 Hours)

Pre-warms cache with popular content to improve hit rate.

**Schedule:** Every 6 hours (00:00, 06:00, 12:00, 18:00)

```bash
0 */6 * * * cd /var/www/deschide_news_app/deschide_backend && symfony console app:cache:warm --popular=100 >> /var/log/deschide/cache-warm.log 2>&1
```

**What it does:**
- Fetches top 100 trending articles
- Pre-loads article cache for all locales (ro, en, ru)
- Pre-loads category cache
- Reduces cache misses for popular content

**Manual execution:**
```bash
# Default (100 articles, all locales)
symfony console app:cache:warm

# Custom parameters
symfony console app:cache:warm --popular=50 --locales=ro,en
```

---

## 🛠️ Installation

### 1. Create Log Directory

```bash
sudo mkdir -p /var/log/deschide
sudo chown www-data:www-data /var/log/deschide
sudo chmod 755 /var/log/deschide
```

### 2. Create Archives Directory

```bash
sudo mkdir -p /var/www/deschide_news_app/deschide_backend/var/archives
sudo chown www-data:www-data /var/www/deschide_news_app/deschide_backend/var/archives
sudo chmod 755 /var/www/deschide_news_app/deschide_backend/var/archives
```

### 3. Edit Crontab

Open crontab for `www-data` user (or your application user):

```bash
sudo crontab -e -u www-data
```

### 4. Add Cron Jobs

Paste all the cron job lines from above:

```bash
# Deschide News App - Performance & Analytics Cron Jobs

# Daily aggregation at 1 AM
0 1 * * * cd /var/www/deschide_news_app/deschide_backend && symfony console app:stats:aggregate --date=yesterday >> /var/log/deschide/aggregate.log 2>&1

# Hourly trending update
0 * * * * cd /var/www/deschide_news_app/deschide_backend && symfony console app:stats:trending --hours=24 >> /var/log/deschide/trending.log 2>&1

# Monthly cleanup (1st day at 2 AM)
0 2 1 * * cd /var/www/deschide_news_app/deschide_backend && symfony console app:stats:cleanup --days=90 --force >> /var/log/deschide/cleanup.log 2>&1

# Cache warm every 6 hours
0 */6 * * * cd /var/www/deschide_news_app/deschide_backend && symfony console app:cache:warm --popular=100 >> /var/log/deschide/aggregate.log 2>&1
```

### 5. Verify Cron Jobs

```bash
sudo crontab -l -u www-data
```

---

## 📊 Monitoring

### Check Log Files

```bash
# View aggregate log
tail -f /var/log/deschide/aggregate.log

# View trending log
tail -f /var/log/deschide/trending.log

# View cleanup log
tail -f /var/log/deschide/cleanup.log

# View cache warm log
tail -f /var/log/deschide/cache-warm.log

# View all logs
tail -f /var/log/deschide/*.log
```

### Verify Daily Aggregation Completed

```sql
-- Check if yesterday's data was aggregated
SELECT * FROM article_stats_daily
WHERE date = CURRENT_DATE - INTERVAL '1 day'
ORDER BY views DESC
LIMIT 10;

-- Check site stats
SELECT * FROM site_stats_daily
ORDER BY date DESC
LIMIT 7;
```

### Check Redis Trending Data

```bash
# Check trending sorted set
redis-cli -n 1 ZRANGE "deschide_news:stats:trending:24h" 0 -1 WITHSCORES

# Count trending articles
redis-cli -n 1 ZCARD "deschide_news:stats:trending:24h"
```

### Check Cache Keys

```bash
# Count cached articles
redis-cli -n 1 KEYS "deschide_news:cache:api:articles:*" | wc -l

# Count cached categories
redis-cli -n 1 KEYS "deschide_news:cache:api:categories:*" | wc -l
```

---

## 🔧 Troubleshooting

### Aggregation Failed

**Symptoms:**
- No new rows in `article_stats_daily` or `site_stats_daily`
- Error messages in `/var/log/deschide/aggregate.log`

**Solutions:**
1. Check PostgreSQL connectivity:
   ```bash
   symfony console dbal:run-sql "SELECT 1"
   ```

2. Check Redis connectivity:
   ```bash
   redis-cli -n 1 PING
   ```

3. Verify migrations are up to date:
   ```bash
   symfony console doctrine:migrations:status
   ```

4. Check logs for specific errors:
   ```bash
   grep ERROR /var/log/deschide/aggregate.log
   ```

5. Run manually in dry-run mode:
   ```bash
   symfony console app:stats:aggregate --dry-run
   ```

---

### Cleanup Deleting Too Much

**Symptoms:**
- Aggregated stats missing
- Important historical data deleted

**Solutions:**
1. **STOP!** The cleanup command only deletes raw `page_views`, not aggregated stats
2. If you need to adjust retention period:
   ```bash
   # Change --days parameter (default: 90)
   symfony console app:stats:cleanup --days=180
   ```

3. Always use `--archive` option for safety:
   ```bash
   symfony console app:stats:cleanup --days=90 --archive
   ```

4. Restore from archive if needed:
   - Archive files are in `/var/www/deschide_news_app/deschide_backend/var/archives/`
   - Import CSV back to database manually

---

### Cache Warm Not Working

**Symptoms:**
- Low cache hit rate
- No cached keys in Redis

**Solutions:**
1. Check Redis memory limits:
   ```bash
   redis-cli -n 1 INFO memory
   ```

2. Verify trending articles exist:
   ```bash
   symfony console app:stats:trending
   ```

3. Check logs:
   ```bash
   tail -f /var/log/deschide/cache-warm.log
   ```

4. Run manually with verbose output:
   ```bash
   symfony console app:cache:warm --popular=50 -v
   ```

---

### Cron Jobs Not Running

**Symptoms:**
- No log files created
- Commands never execute

**Solutions:**
1. Verify cron service is running:
   ```bash
   sudo systemctl status cron
   ```

2. Check crontab syntax:
   ```bash
   sudo crontab -l -u www-data
   ```

3. Test command manually:
   ```bash
   sudo -u www-data bash -c "cd /var/www/deschide_news_app/deschide_backend && symfony console app:stats:aggregate --dry-run"
   ```

4. Check cron logs:
   ```bash
   grep CRON /var/log/syslog
   ```

---

## 📈 Performance Optimization

### Aggregation Performance

**Expected Time:** <5 minutes for 10,000 articles

**Optimization Tips:**
1. Ensure database indexes exist (created by migrations)
2. Run aggregation during low-traffic hours (1 AM)
3. Monitor query performance:
   ```sql
   EXPLAIN ANALYZE
   SELECT COUNT(*) FROM page_views WHERE DATE(viewed_at) = '2025-10-31';
   ```

### Redis Memory Management

**Allocated Memory:** 512 MB (256 MB cache + 256 MB stats)

**Monitor Usage:**
```bash
redis-cli -n 1 INFO memory | grep used_memory_human
```

**If Memory Full:**
1. Reduce cache TTL in `PerformanceService`
2. Reduce `--popular` parameter in cache warm command
3. Increase Redis memory limit

---

## 🔄 Log Rotation

To prevent log files from growing too large, set up log rotation:

```bash
sudo nano /etc/logrotate.d/deschide
```

Add:
```
/var/log/deschide/*.log {
    weekly
    rotate 4
    compress
    missingok
    notifempty
    create 0644 www-data www-data
}
```

Test log rotation:
```bash
sudo logrotate -d /etc/logrotate.d/deschide
sudo logrotate -f /etc/logrotate.d/deschide
```

---

## 📚 Related Documentation

- **Strategy Document:** `/var/www/deschide_news_app/docs/performance-analytics-strategy.md`
- **Redis Schema:** `/var/www/deschide_news_app/docs/redis-schema.md`
- **Statistics Schema:** `/var/www/deschide_news_app/docs/statistics-schema.md`
- **Sprint 4 Plan:** `/var/www/deschide_news_app/sprints/sprint-4-aggregation-cron.md`

---

## ✅ Verification Checklist

After setup, verify:

- [ ] Log directory created and writable
- [ ] Archives directory created and writable
- [ ] Cron jobs added to www-data crontab
- [ ] All commands run successfully manually
- [ ] Log files are being created
- [ ] Aggregation creates database entries
- [ ] Trending updates cache
- [ ] Cleanup respects retention period
- [ ] Cache warm populates Redis keys
- [ ] Log rotation configured

---

**Document Version:** 1.0
**Last Updated:** 2025-11-01
**Maintained By:** Development Team
