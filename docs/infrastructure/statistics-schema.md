# Statistics Database Schema - Deschide News App

**Created:** 2025-11-01
**Sprint:** 1 - Foundation & Database Schema
**Purpose:** Document PostgreSQL statistics tables and relationships

---

## Overview

The statistics system uses PostgreSQL to store persistent tracking data with two main layers:

1. **Raw Data Layer** - Detailed page views and sessions
2. **Aggregated Data Layer** - Daily summaries and analytics

**Database:** `deschide_news` (PostgreSQL 17)
**Schema:** `public`

---

## Table: `page_views`

**Purpose:** Raw page view tracking data

### Schema

```sql
CREATE TABLE page_views (
    id BIGSERIAL PRIMARY KEY,
    article_id INT NULL REFERENCES articles(id) ON DELETE SET NULL,
    visitor_id VARCHAR(255) NOT NULL,
    ip_address INET,
    user_agent TEXT,
    referrer TEXT,
    category_id INT NULL REFERENCES categories(id) ON DELETE SET NULL,
    viewed_at TIMESTAMP DEFAULT NOW(),
    session_duration INT
);
```

### Indexes

```sql
CREATE INDEX idx_article_views ON page_views(article_id, viewed_at);
CREATE INDEX idx_visitor ON page_views(visitor_id);
CREATE INDEX idx_viewed_at ON page_views(viewed_at);
```

### Column Details

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | BIGSERIAL | No | Primary key (auto-increment) |
| `article_id` | INT | Yes | Foreign key to articles table |
| `visitor_id` | VARCHAR(255) | No | Unique visitor identifier (UUID or fingerprint) |
| `ip_address` | INET | Yes | Visitor IP address |
| `user_agent` | TEXT | Yes | Browser user agent string |
| `referrer` | TEXT | Yes | HTTP referrer URL |
| `category_id` | INT | Yes | Foreign key to categories table |
| `viewed_at` | TIMESTAMP | No | Timestamp of page view |
| `session_duration` | INT | Yes | Time spent on page (seconds) |

### Relationships

- `article_id` → `articles(id)` (ON DELETE SET NULL)
- `category_id` → `categories(id)` (ON DELETE SET NULL)

### Data Retention

- **Retention Period:** 90 days
- **Cleanup:** Monthly via cron command `app:stats:cleanup`
- **Archival:** Optional export to CSV before deletion

---

## Table: `article_stats_daily`

**Purpose:** Aggregated daily statistics per article

### Schema

```sql
CREATE TABLE article_stats_daily (
    id SERIAL PRIMARY KEY,
    article_id INT NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
    date DATE NOT NULL,
    views INT DEFAULT 0,
    unique_visitors INT DEFAULT 0,
    avg_reading_time INT,
    completion_rate DECIMAL(5,2),
    UNIQUE(article_id, date)
);
```

### Indexes

```sql
CREATE INDEX idx_article_date ON article_stats_daily(article_id, date);
CREATE INDEX idx_date ON article_stats_daily(date);
```

### Column Details

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | SERIAL | No | Primary key (auto-increment) |
| `article_id` | INT | No | Foreign key to articles table |
| `date` | DATE | No | Date of aggregated stats |
| `views` | INT | No | Total views for the day |
| `unique_visitors` | INT | No | Unique visitors for the day |
| `avg_reading_time` | INT | Yes | Average reading time (seconds) |
| `completion_rate` | DECIMAL(5,2) | Yes | Percentage of users who scrolled 100% |

### Constraints

- **Unique Constraint:** `(article_id, date)` - One row per article per day

### Relationships

- `article_id` → `articles(id)` (ON DELETE CASCADE)

### Aggregation Source

Data aggregated from:
- `page_views` table (daily cron job)
- Redis counters (`deschide_news:stats:article:views:{id}`)
- Redis sets (`deschide_news:stats:article:visitors:{id}:{date}`)

### Data Retention

- **Retention Period:** Indefinite (permanent historical data)

---

## Table: `site_stats_daily`

**Purpose:** Site-wide daily statistics

### Schema

```sql
CREATE TABLE site_stats_daily (
    id SERIAL PRIMARY KEY,
    date DATE NOT NULL UNIQUE,
    total_visits INT DEFAULT 0,
    unique_visitors INT DEFAULT 0,
    new_visitors INT DEFAULT 0,
    bounce_rate DECIMAL(5,2),
    avg_session_duration INT
);
```

### Indexes

```sql
CREATE INDEX idx_site_stats_date ON site_stats_daily(date);
```

### Column Details

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | SERIAL | No | Primary key (auto-increment) |
| `date` | DATE | No | Date of aggregated stats (unique) |
| `total_visits` | INT | No | Total page views for the day |
| `unique_visitors` | INT | No | Unique visitors for the day |
| `new_visitors` | INT | No | First-time visitors |
| `bounce_rate` | DECIMAL(5,2) | Yes | Percentage of single-page sessions |
| `avg_session_duration` | INT | Yes | Average session duration (seconds) |

### Constraints

- **Unique Constraint:** `date` - One row per date

### Aggregation Source

Data aggregated from:
- `page_views` table
- `sessions` table
- Redis HyperLogLog (`deschide_news:stats:site:visitors:{date}`)

### Data Retention

- **Retention Period:** Indefinite (permanent historical data)

---

## Table: `sessions`

**Purpose:** User session tracking

### Schema

```sql
CREATE TABLE sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    visitor_id VARCHAR(255) NOT NULL,
    ip_address INET,
    user_agent TEXT,
    referrer TEXT,
    started_at TIMESTAMP DEFAULT NOW(),
    ended_at TIMESTAMP,
    page_count INT DEFAULT 0,
    duration INT
);
```

### Indexes

```sql
CREATE INDEX idx_started_at ON sessions(started_at);
CREATE INDEX idx_visitor_id ON sessions(visitor_id);
```

### Column Details

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | UUID | No | Primary key (auto-generated UUID) |
| `visitor_id` | VARCHAR(255) | No | Unique visitor identifier |
| `ip_address` | INET | Yes | Visitor IP address |
| `user_agent` | TEXT | Yes | Browser user agent string |
| `referrer` | TEXT | Yes | HTTP referrer URL |
| `started_at` | TIMESTAMP | No | Session start timestamp |
| `ended_at` | TIMESTAMP | Yes | Session end timestamp |
| `page_count` | INT | No | Number of pages viewed in session |
| `duration` | INT | Yes | Session duration (seconds) |

### Session Lifecycle

1. **Session Start:** Created when user lands on site
2. **Session Active:** `page_count` incremented on each page view
3. **Session End:** `ended_at` and `duration` set when user leaves

### Data Retention

- **Retention Period:** 90 days
- **Cleanup:** Monthly via cron command `app:stats:cleanup`

---

## Entity Relationships Diagram

```
┌─────────────────┐
│    articles     │
│─────────────────│
│ id (PK)         │
└────────┬────────┘
         │
         │ 1:N
         │
┌────────▼─────────────────┐
│  article_stats_daily     │
│──────────────────────────│
│ id (PK)                  │
│ article_id (FK) ◄────────┼─ CASCADE
│ date                     │
│ views                    │
│ unique_visitors          │
│ UNIQUE(article_id, date) │
└──────────────────────────┘


┌─────────────────┐
│    articles     │
│─────────────────│
│ id (PK)         │
└────────┬────────┘
         │
         │ 1:N
         │
┌────────▼────────────┐
│    page_views       │
│─────────────────────│
│ id (PK)             │
│ article_id (FK) ◄───┼─ SET NULL
│ visitor_id          │
│ viewed_at           │
│ session_duration    │
└─────────────────────┘


┌────────────────────┐
│   site_stats_daily │
│────────────────────│
│ id (PK)            │
│ date (UNIQUE)      │
│ total_visits       │
│ unique_visitors    │
│ bounce_rate        │
└────────────────────┘


┌─────────────────┐
│    sessions     │
│─────────────────│
│ id (PK, UUID)   │
│ visitor_id      │
│ started_at      │
│ ended_at        │
│ page_count      │
│ duration        │
└─────────────────┘
```

---

## Sample Queries

### Get Article Stats for Date Range

```sql
SELECT
    a.id,
    a.title,
    asd.date,
    asd.views,
    asd.unique_visitors,
    asd.avg_reading_time,
    asd.completion_rate
FROM article_stats_daily asd
JOIN articles a ON asd.article_id = a.id
WHERE asd.date BETWEEN '2025-11-01' AND '2025-11-07'
ORDER BY asd.views DESC
LIMIT 10;
```

### Get Site-Wide Stats Summary

```sql
SELECT
    date,
    total_visits,
    unique_visitors,
    bounce_rate,
    avg_session_duration
FROM site_stats_daily
WHERE date >= CURRENT_DATE - INTERVAL '30 days'
ORDER BY date DESC;
```

### Get Trending Articles (Most Views This Week)

```sql
SELECT
    a.id,
    a.title,
    SUM(asd.views) as total_views,
    SUM(asd.unique_visitors) as total_unique_visitors
FROM article_stats_daily asd
JOIN articles a ON asd.article_id = a.id
WHERE asd.date >= CURRENT_DATE - INTERVAL '7 days'
GROUP BY a.id, a.title
ORDER BY total_views DESC
LIMIT 10;
```

### Calculate Bounce Rate for Date

```sql
SELECT
    DATE(started_at) as date,
    COUNT(*) as total_sessions,
    COUNT(*) FILTER (WHERE page_count = 1) as bounced_sessions,
    ROUND(
        COUNT(*) FILTER (WHERE page_count = 1)::DECIMAL / COUNT(*) * 100,
        2
    ) as bounce_rate_percent
FROM sessions
WHERE started_at >= '2025-11-01' AND started_at < '2025-11-02'
GROUP BY DATE(started_at);
```

---

## Data Flow

### 1. Real-Time Layer (Redis)

```
User visits article
↓
PageViewSubscriber increments Redis counters
↓
Redis: deschide_news:stats:article:views:123 (INCR)
Redis: deschide_news:stats:article:visitors:123:2025-11-01 (SADD)
Redis: deschide_news:stats:site:visitors:2025-11-01 (PFADD)
Redis: deschide_news:stats:trending:24h (ZINCRBY)
```

### 2. Async Persistence (RabbitMQ → PostgreSQL)

```
PageViewEvent dispatched
↓
RabbitMQ: deschide_news_stats queue
↓
PageViewHandler consumes message
↓
INSERT INTO page_views (...)
```

### 3. Daily Aggregation (Cron)

```
Cron: app:stats:aggregate --date=yesterday
↓
Read Redis counters
Read page_views table
Calculate averages
↓
INSERT INTO article_stats_daily (...)
INSERT INTO site_stats_daily (...)
```

---

## Maintenance Commands

### Daily Aggregation

```bash
# Run at 1 AM daily
symfony console app:stats:aggregate --date=yesterday
```

### Cleanup Old Data

```bash
# Run monthly
symfony console app:stats:cleanup --days=90
```

### Check Data Integrity

```sql
-- Verify aggregation for yesterday
SELECT COUNT(*) FROM article_stats_daily WHERE date = CURRENT_DATE - INTERVAL '1 day';

-- Verify no duplicates
SELECT article_id, date, COUNT(*)
FROM article_stats_daily
GROUP BY article_id, date
HAVING COUNT(*) > 1;
```

---

## Performance Considerations

### Index Strategy

- **Composite indexes** on `(article_id, date)` for fast date range queries
- **Simple indexes** on frequently filtered columns (`viewed_at`, `started_at`)
- **Unique constraints** prevent duplicate aggregations

### Partitioning (Future Enhancement)

For large datasets, consider table partitioning:

```sql
-- Partition page_views by month
CREATE TABLE page_views_2025_11 PARTITION OF page_views
    FOR VALUES FROM ('2025-11-01') TO ('2025-12-01');
```

---

## Related Files

- **Entities:** `/var/www/deschide_news_app/deschide_backend/src/Entity/`
  - `PageView.php`
  - `ArticleStatsDaily.php`
  - `SiteStatsDaily.php`
  - `Session.php`

- **Repositories:** `/var/www/deschide_news_app/deschide_backend/src/Repository/`
  - `PageViewRepository.php`
  - `ArticleStatsDailyRepository.php`
  - `SiteStatsDailyRepository.php`
  - `SessionRepository.php`

- **Migration:** `/var/www/deschide_news_app/deschide_backend/migrations/Version20251101101454.php`

---

## Related Documentation

- **Redis Schema:** `docs/redis-schema.md`
- **Performance Strategy:** `docs/performance-analytics-strategy.md`
- **Sprint Plan:** `sprints/sprint-1-foundation-database.md`

---

**Status:** ✅ Completed (Sprint 1)
**Last Updated:** 2025-11-01
