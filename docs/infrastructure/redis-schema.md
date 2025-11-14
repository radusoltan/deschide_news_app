# Redis Schema Documentation - Deschide News App

**Created:** 2025-11-01
**Sprint:** 1 - Foundation & Database Schema
**Purpose:** Document Redis namespace strategy and key structure

---

## Overview

Redis is used for two primary purposes:
1. **Caching Layer** - Application data caching for performance optimization
2. **Statistics Layer** - Real-time tracking and counters

**Redis Database:** Database 1 (`localhost:6379/1`)
**Total Memory Allocation:** 512 MB
- Cache namespace: 256 MB
- Stats namespace: 256 MB

---

## Namespace Strategy

All keys use the prefix `deschide_news:` to prevent conflicts with other applications sharing the same Redis instance.

```
deschide_news:
├── cache:*          # Caching layer (256 MB)
│   ├── api:articles:{id}:{locale}
│   ├── api:articles:list:page{N}:{locale}
│   ├── api:categories:{id}:{locale}
│   ├── query:{hash}
│   └── fragment:{key}
│
└── stats:*          # Statistics layer (256 MB)
    ├── article:views:{id}
    ├── article:visitors:{id}:{date}
    ├── site:visitors:{date}
    ├── trending:24h
    └── session:active:{sessionId}
```

---

## Cache Namespace Keys

### Article Caching

**Key Pattern:** `deschide_news:cache:api:articles:{id}:{locale}`

**Example:**
```
deschide_news:cache:api:articles:123:ro
deschide_news:cache:api:articles:123:en
deschide_news:cache:api:articles:123:ru
```

**TTL:** 3600 seconds (1 hour)
**Data Type:** String (serialized PHP object)
**Purpose:** Cache individual article data with translations

---

### Article List Caching

**Key Pattern:** `deschide_news:cache:api:articles:list:page{N}:{locale}`

**Example:**
```
deschide_news:cache:api:articles:list:page1:ro
deschide_news:cache:api:articles:list:page2:en
```

**TTL:** 300 seconds (5 minutes)
**Data Type:** String (serialized array)
**Purpose:** Cache paginated article lists

---

### Category Caching

**Key Pattern:** `deschide_news:cache:api:categories:{id}:{locale}`

**Example:**
```
deschide_news:cache:api:categories:5:ro
deschide_news:cache:api:categories:5:en
```

**TTL:** 3600 seconds (1 hour)
**Data Type:** String (serialized PHP object)
**Purpose:** Cache category data with translations

---

### Trending Articles Cache

**Key Pattern:** `deschide_news:cache:api:trending`

**TTL:** 300 seconds (5 minutes)
**Data Type:** String (serialized array)
**Purpose:** Cache pre-computed trending articles list

---

## Statistics Namespace Keys

### Article View Counters

**Key Pattern:** `deschide_news:stats:article:views:{id}`

**Example:**
```
deschide_news:stats:article:views:123
```

**TTL:** None (persistent until aggregated)
**Data Type:** String (integer counter)
**Operations:**
```redis
INCR deschide_news:stats:article:views:123
GET deschide_news:stats:article:views:123
```

**Purpose:** Track total view count per article

---

### Unique Visitors per Article

**Key Pattern:** `deschide_news:stats:article:visitors:{id}:{date}`

**Example:**
```
deschide_news:stats:article:visitors:123:2025-11-01
```

**TTL:** 2592000 seconds (30 days)
**Data Type:** Set
**Operations:**
```redis
SADD deschide_news:stats:article:visitors:123:2025-11-01 "visitor_uuid_here"
SCARD deschide_news:stats:article:visitors:123:2025-11-01
```

**Purpose:** Track unique visitors per article per day

---

### Site-Wide Unique Visitors

**Key Pattern:** `deschide_news:stats:site:visitors:{date}`

**Example:**
```
deschide_news:stats:site:visitors:2025-11-01
```

**TTL:** 2592000 seconds (30 days)
**Data Type:** HyperLogLog
**Operations:**
```redis
PFADD deschide_news:stats:site:visitors:2025-11-01 "visitor_uuid_here"
PFCOUNT deschide_news:stats:site:visitors:2025-11-01
```

**Purpose:** Track site-wide unique visitors per day (memory efficient)

---

### Trending Articles (Sorted Set)

**Key Pattern:** `deschide_news:stats:trending:24h`

**TTL:** 86400 seconds (24 hours)
**Data Type:** Sorted Set
**Operations:**
```redis
ZINCRBY deschide_news:stats:trending:24h 1 "article:123"
ZREVRANGE deschide_news:stats:trending:24h 0 9 WITHSCORES
```

**Purpose:** Track trending articles with view counts (top N)

---

### Active Sessions

**Key Pattern:** `deschide_news:stats:session:active:{sessionId}`

**Example:**
```
deschide_news:stats:session:active:550e8400-e29b-41d4-a716-446655440000
```

**TTL:** 1800 seconds (30 minutes)
**Data Type:** Hash
**Fields:**
- `visitor_id`: Visitor UUID
- `ip`: IP address
- `user_agent`: User agent string
- `started_at`: Session start timestamp
- `page_count`: Number of pages viewed

**Operations:**
```redis
HSET deschide_news:stats:session:active:{id} visitor_id "uuid" ip "192.168.1.1" started_at "1699000000"
HINCRBY deschide_news:stats:session:active:{id} page_count 1
HGETALL deschide_news:stats:session:active:{id}
```

**Purpose:** Track active user sessions in real-time

---

## Cache Invalidation Patterns

### Wildcard Patterns for Invalidation

```redis
# Invalidate all article caches (all locales)
KEYS deschide_news:cache:api:articles:123:*

# Invalidate all article list caches
KEYS deschide_news:cache:api:articles:list:*

# Invalidate all category caches
KEYS deschide_news:cache:api:categories:*

# Invalidate trending cache
DEL deschide_news:cache:api:trending
```

**Note:** Use `SCAN` instead of `KEYS` in production for performance.

---

## TTL Strategy

| Key Type | TTL | Reasoning |
|----------|-----|-----------|
| Article Detail | 3600s (1h) | Rarely changes after publish |
| Article Lists | 300s (5m) | New articles added frequently |
| Categories | 3600s (1h) | Rarely changes |
| Trending | 300s (5m) | Real-time tracking |
| Article Views | None | Persistent until aggregated |
| Unique Visitors | 2592000s (30d) | Historical tracking |
| Active Sessions | 1800s (30m) | Session timeout |

---

## Memory Management

### Memory Allocation

- **Total:** 512 MB
- **Cache:** ~256 MB (deschide_news:cache:*)
- **Stats:** ~256 MB (deschide_news:stats:*)

### Eviction Policy

Configure Redis with:
```
maxmemory 512mb
maxmemory-policy allkeys-lru
```

This ensures Least Recently Used (LRU) keys are evicted when memory limit is reached.

---

## Monitoring Commands

### Check Memory Usage

```bash
redis-cli -n 1 INFO memory
redis-cli -n 1 CONFIG GET maxmemory
```

### Count Keys by Namespace

```bash
redis-cli -n 1 --scan --pattern "deschide_news:cache:*" | wc -l
redis-cli -n 1 --scan --pattern "deschide_news:stats:*" | wc -l
```

### Check Specific Key

```bash
redis-cli -n 1 GET "deschide_news:cache:api:articles:123:ro"
redis-cli -n 1 TTL "deschide_news:cache:api:articles:123:ro"
redis-cli -n 1 TYPE "deschide_news:stats:trending:24h"
```

### Debug Specific Pattern

```bash
redis-cli -n 1 --scan --pattern "deschide_news:stats:article:views:*"
```

---

## Best Practices

1. **Always use namespace prefix** - Prevents conflicts with other apps
2. **Set TTL on all cache keys** - Prevents memory bloat
3. **Use appropriate data types** - HyperLogLog for unique counts, Sorted Sets for rankings
4. **Monitor memory usage** - Track Redis memory consumption daily
5. **Use SCAN instead of KEYS** - For production key searches
6. **Serialize PHP objects** - Use `serialize()`/`unserialize()` for complex data

---

## Configuration Files

- **Framework Cache:** `/var/www/deschide_news_app/deschide_backend/config/packages/cache.yaml`
- **Predis Client:** `/var/www/deschide_news_app/deschide_backend/config/services.yaml`
- **Performance Service:** `/var/www/deschide_news_app/deschide_backend/src/Service/PerformanceService.php`

---

## Related Documentation

- **Statistics Schema:** `docs/statistics-schema.md`
- **Performance Strategy:** `docs/performance-analytics-strategy.md`
- **Sprint Plan:** `sprints/sprint-1-foundation-database.md`

---

**Status:** ✅ Completed (Sprint 1)
**Last Updated:** 2025-11-01
