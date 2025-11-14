# Performance & Analytics - Quick Start Guide

**For:** Developers, Administrators, Content Managers
**Last Updated:** 2025-11-01

---

## 🚀 Quick Start (5 Minutes)

### Prerequisites

Ensure services are running:

```bash
# Check backend
curl http://127.0.0.1:8081/api

# Check frontend
curl http://localhost:3005

# Check Redis
redis-cli -n 1 PING

# Check PostgreSQL
psql -U deschide_user -d deschide_news -c "SELECT 1"
```

### Access the Admin Dashboard

1. **Login to Admin Panel:**
   ```
   URL: http://localhost:3005/ro/admin
   ```

2. **Navigate to Statistics:**
   ```
   URL: http://localhost:3005/ro/admin/statistics
   ```

3. **View Dashboard:**
   - Site statistics (4 cards)
   - Trending articles table
   - Real-time metrics widget
   - Date range selector

### View Public Trending Section

1. **Go to Homepage:**
   ```
   URL: http://localhost:3005/ro
   ```

2. **Scroll to "Trending Now" section**
   - Located after "Latest News"
   - Shows top 5 trending articles from last 24h

---

## 📊 Using the Admin Dashboard

### Site Statistics Overview

**What you see:**
- **Total Visits** - Sum of all page views in date range
- **Unique Visitors/day** - Average unique visitors per day
- **Bounce Rate** - Percentage of single-page visits
- **Avg Session** - Average time users spend on site

**How to use:**
1. Select date range (Today, Yesterday, 7 days, 30 days)
2. Numbers update automatically
3. Colors indicate metric type (blue, green, yellow, purple)

### Trending Articles Table

**What you see:**
- Rank (#1, #2, #3, etc.)
- Article title (clickable to edit page)
- Category badge
- View count (last 24 hours)
- Trend indicator (🔥 or 📈)

**How to use:**
1. Click article title to edit
2. Monitor which content is performing well
3. Top 3 get special indicators
4. Auto-refreshes every 5 minutes

### Real-Time Stats Widget

**What you see:**
- Active sessions (users currently browsing)
- Unique visitors today
- Top 3 trending articles right now
- Last updated timestamp
- Live indicator (green pulse)

**How to use:**
1. Watch live data update every 5 seconds
2. Pause updates with "Pause" button
3. Resume with "Resume" button
4. Monitor current site activity

### Date Range Filter

**Options:**
- **Today** - Current day only
- **Yesterday** - Previous day
- **Last 7 Days** - Week view
- **Last 30 Days** - Month view

**How to use:**
1. Click dropdown in header
2. Select desired range
3. Page reloads with new data
4. URL updates with `?range=` parameter

---

## 🔥 Viewing Trending Articles

### On Homepage (Public)

**Location:** After "Latest News" section

**Features:**
- Top 5 trending articles from last 24h
- #1 article has special badge
- Rank numbers for top 3 (gold, silver, bronze)
- Category badges
- View counts
- Trending icons
- "Last 24 hours" label

**Responsive:**
- Desktop: 3 columns
- Tablet: 2 columns
- Mobile: 1 column

### View Count Badges

**Where shown:**
- Article cards throughout site
- Only articles with >100 views

**Format:**
- Eye icon + formatted number
- Examples:
  - 150 views → "150 views"
  - 1,234 views → "1.2k views"
  - 15,678 views → "15.7k views"
  - 1,234,567 views → "1.2M views"

---

## 🛠️ For Developers

### API Endpoints

#### 1. Get Trending Articles (Public)

```bash
curl http://127.0.0.1:8081/api/admin/stats/trending?limit=10
```

**Response:**
```json
[
  {
    "id": 81,
    "title": "Article Title",
    "slug": "article-slug",
    "category": {
      "id": 17,
      "name": "Category Name",
      "slug": "category-slug"
    },
    "views_24h": 245,
    "published_at": "2025-10-31T16:18:54+00:00"
  }
]
```

#### 2. Get Article Statistics (Admin)

```bash
# Get JWT token
TOKEN=$(symfony console app:test:jwt-token 2>/dev/null | grep "^eyJ" | tail -1)

# Request stats
curl -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/article/81?range=7days"
```

**Response:**
```json
{
  "article_id": 81,
  "title": "Article Title",
  "current_views": 245,
  "stats": [
    {
      "date": "2025-11-01",
      "views": 45,
      "unique_visitors": 32,
      "avg_reading_time": 120.5,
      "completion_rate": 0.75
    }
  ]
}
```

#### 3. Get Site Statistics (Admin)

```bash
curl -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/site?range=7days"
```

#### 4. Get Real-Time Stats (Admin)

```bash
curl -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/realtime"
```

**Response:**
```json
{
  "timestamp": 1761994746,
  "active_sessions": 45,
  "unique_visitors_today": 1247,
  "trending_now": [
    { "article_id": 81, "views": 245 },
    { "article_id": 92, "views": 187 }
  ]
}
```

### Frontend Components

#### Import Trending Section

```typescript
import { TrendingArticles } from '@/components/public/TrendingArticles';

export default function HomePage() {
  return (
    <>
      {/* Other content */}
      <TrendingArticles locale="ro" limit={5} />
    </>
  );
}
```

#### Import View Badge

```typescript
import { ViewCountBadge } from '@/components/public/ViewCountBadge';

<ViewCountBadge views={1234} />
// Output: "1.2k views"
```

#### Use API Client

```typescript
import { getTrendingArticles, getSiteStats } from '@/lib/api/statistics';

// Server component
const trending = await getTrendingArticles(10, 'ro');
const stats = await getSiteStats('7days', token);

// Client component
import { fetchRealTimeStatsClient } from '@/lib/api/statistics';
const realtime = await fetchRealTimeStatsClient(token);
```

---

## 📈 Monitoring (Prometheus & Grafana)

### Access Dashboards

**Prometheus:**
```
URL: http://localhost:9090
```

**Grafana:**
```
URL: http://localhost:3002
Username: admin
Password: admin (change on first login)
```

### Key Metrics to Watch

1. **Cache Hit Rate**
   - Target: >80%
   - Query: `deschide_news_cache_hit_rate`

2. **Response Time (P95)**
   - Target: <500ms
   - Query: `histogram_quantile(0.95, rate(deschide_news_http_request_duration_seconds_bucket[5m]))`

3. **Unique Visitors**
   - Query: `deschide_news_unique_visitors_total`

4. **Page Views Rate**
   - Query: `rate(deschide_news_pageviews_total[5m])`

### Grafana Dashboard

1. Login to Grafana
2. Go to Dashboards → Browse
3. Search for "Deschide"
4. Open "Deschide Performance & Analytics"

**Features:**
- 8 panels showing key metrics
- Auto-refresh every 5s
- Adjustable time range
- Alert thresholds configured

---

## 🔧 Maintenance Tasks

### Daily Tasks

**1. Check Aggregation Logs:**
```bash
tail -f /var/log/deschide-aggregation.log
```

**2. Verify Cron Jobs Running:**
```bash
crontab -l | grep deschide
```

**Expected:**
```
0 2 * * * cd /var/www/deschide_news_app/deschide_backend && php bin/console app:aggregate-daily-stats
```

### Weekly Tasks

**1. Review Metrics in Grafana:**
- Check cache hit rate trends
- Monitor response time patterns
- Verify no error spikes

**2. Check Redis Memory:**
```bash
redis-cli -n 1 INFO memory
```

**3. Verify Database Size:**
```bash
psql -U deschide_user -d deschide_news -c "SELECT pg_size_pretty(pg_database_size('deschide_news'))"
```

### Monthly Tasks

**1. Run Manual Cleanup (if needed):**
```bash
symfony console app:cleanup-old-stats
```

**2. Review Top Performing Content:**
- Check trending articles in admin dashboard
- Analyze what content resonates with users

**3. Performance Review:**
- Check average response times
- Review cache efficiency
- Plan optimizations if needed

---

## 🐛 Troubleshooting

### Issue: Trending Section Empty

**Symptoms:** Homepage trending section doesn't show

**Solutions:**
1. Check if Redis has trending data:
   ```bash
   redis-cli -n 1 ZRANGE deschide_news:stats:trending:24h 0 -1 WITHSCORES
   ```

2. If empty, simulate some views:
   ```bash
   redis-cli -n 1 ZINCRBY deschide_news:stats:trending:24h 10 "article:81"
   ```

3. Refresh homepage

### Issue: Admin Dashboard Shows "Authentication Required"

**Symptoms:** Real-time widget shows auth error

**Solutions:**
1. Check if logged in to admin panel
2. Verify JWT token in cookies (browser DevTools)
3. Generate new token if expired:
   ```bash
   symfony console app:test:jwt-token
   ```

### Issue: Stats Not Updating

**Symptoms:** Numbers seem stale

**Solutions:**
1. Check cache TTL (60 seconds for stats endpoints)
2. Wait 60 seconds and refresh
3. Verify aggregation cron ran:
   ```bash
   tail -20 /var/log/deschide-aggregation.log
   ```

4. Manual aggregation:
   ```bash
   symfony console app:aggregate-daily-stats
   ```

### Issue: Slow Page Loads

**Symptoms:** Admin dashboard takes >2 seconds

**Solutions:**
1. Check cache hit rate in Grafana
2. Verify Redis is running:
   ```bash
   redis-cli -n 1 PING
   ```

3. Check database connections:
   ```bash
   psql -U deschide_user -d deschide_news -c "SELECT count(*) FROM pg_stat_activity"
   ```

4. Review Symfony profiler (dev mode only)

---

## 📚 Additional Resources

### Documentation

- **Full API Reference:** `docs/admin-stats-api.md`
- **Monitoring Setup:** `docs/monitoring-guide.md`
- **Cron Configuration:** `docs/cron-setup.md`
- **Testing Guide:** `docs/sprint-6-testing-deployment.md`
- **Complete System:** `docs/performance-analytics-COMPLETE.md`

### Commands Reference

```bash
# Backend
symfony serve                          # Start dev server
symfony console cache:clear            # Clear cache
symfony console app:aggregate-daily-stats  # Run aggregation
symfony console app:test:jwt-token     # Generate test token

# Frontend
pnpm dev                              # Start dev server
pnpm build                            # Production build
pm2 restart deschide_frontend         # Restart production

# Redis
redis-cli -n 1 PING                   # Check connection
redis-cli -n 1 KEYS "deschide_news:*" # List all keys
redis-cli -n 1 INFO memory            # Memory usage

# PostgreSQL
psql -U deschide_user -d deschide_news  # Connect to database
```

### Support

- **Documentation:** `/var/www/deschide_news_app/docs/`
- **Logs:** `/var/log/deschide-*.log`
- **Monitoring:** http://localhost:3002 (Grafana)

---

## ✅ Quick Checklist

Before using the system, verify:

- [ ] Backend running on port 8081
- [ ] Frontend running on port 3005
- [ ] Redis accessible (port 6379)
- [ ] PostgreSQL accessible (port 5432)
- [ ] Cron jobs configured
- [ ] Admin login working
- [ ] Prometheus scraping metrics
- [ ] Grafana dashboard accessible

If all checked, system is ready to use! 🎉

---

**Quick Start Complete!**

For detailed information, see full documentation in `/docs` directory.

**Last Updated:** 2025-11-01
**Version:** 1.0
