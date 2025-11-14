# Admin Statistics API Documentation

**Created:** 2025-11-01
**Purpose:** REST API endpoints for admin dashboard and analytics
**Related:** `docs/performance-analytics-strategy.md`, `docs/monitoring-guide.md`

---

## 📋 Overview

The Admin Statistics API provides endpoints for retrieving analytics data for the admin dashboard and public trending articles. All endpoints are under the `/api/admin/stats` prefix.

**Base URL:** `http://127.0.0.1:8081/api/admin/stats`

---

## 🔐 Authentication

Most endpoints require **ROLE_ADMIN** access via JWT token. The `/trending` endpoint is public.

### Getting a JWT Token

```bash
# Login to get JWT token
curl -X POST http://127.0.0.1:8081/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "your_password"}'

# Response
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refresh_token": "def502005c8a7..."
}
```

### Using the Token

```bash
curl -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  http://127.0.0.1:8081/api/admin/stats/site
```

---

## 📊 Endpoints

### 1. Article Statistics

**GET** `/api/admin/stats/article/{id}`

Get detailed statistics for a specific article.

**Authentication:** Required (ROLE_ADMIN)

**Parameters:**
- `id` (path, required) - Article ID
- `range` (query, optional) - Date range: `today`, `yesterday`, `7days`, `30days` (default: `7days`)

**Response:**
```json
{
  "article_id": 81,
  "title": "Article Title",
  "current_views": 150,
  "stats": [
    {
      "date": "2025-11-01",
      "views": 45,
      "unique_visitors": 32,
      "avg_reading_time": 120.5,
      "completion_rate": 0.75
    },
    {
      "date": "2025-10-31",
      "views": 67,
      "unique_visitors": 51,
      "avg_reading_time": 115.3,
      "completion_rate": 0.68
    }
  ]
}
```

**Example:**
```bash
# Get article stats for last 7 days
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/article/81?range=7days"

# Get article stats for today
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/article/81?range=today"

# Get article stats for last 30 days
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/article/81?range=30days"
```

**Status Codes:**
- `200 OK` - Success
- `401 Unauthorized` - Missing or invalid JWT token
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Article not found

**Caching:** 60 seconds

---

### 2. Trending Articles

**GET** `/api/admin/stats/trending`

Get top trending articles in the last 24 hours.

**Authentication:** Public (no authentication required)

**Parameters:**
- `limit` (query, optional) - Number of articles to return (default: 10, max: 100)

**Headers:**
- `Accept-Language` (optional) - Locale for translations: `ro`, `en`, `ru` (default: `ro`)

**Response:**
```json
[
  {
    "id": 81,
    "title": "Test Article pentru Redirecturi",
    "slug": "test-article-pentru-redirecturi",
    "category": {
      "id": 17,
      "name": "Test Politică",
      "slug": "test-politica"
    },
    "views_24h": 245,
    "published_at": "2025-10-31T16:18:54+00:00"
  },
  {
    "id": 92,
    "title": "Breaking News Article",
    "slug": "breaking-news-article",
    "category": {
      "id": 5,
      "name": "Știri",
      "slug": "stiri"
    },
    "views_24h": 187,
    "published_at": "2025-11-01T08:30:00+00:00"
  }
]
```

**Example:**
```bash
# Get top 10 trending articles (Romanian)
curl "http://127.0.0.1:8081/api/admin/stats/trending?limit=10"

# Get top 5 trending articles (English)
curl -H "Accept-Language: en" \
  "http://127.0.0.1:8081/api/admin/stats/trending?limit=5"

# Get top 20 trending articles (Russian)
curl -H "Accept-Language: ru" \
  "http://127.0.0.1:8081/api/admin/stats/trending?limit=20"
```

**Status Codes:**
- `200 OK` - Success

**Caching:** 60 seconds per locale

**Notes:**
- Articles without translations for the requested locale will have `null` values for `title` and `slug`
- Category data is always included if available
- Results are sorted by views in descending order

---

### 3. Site-wide Statistics

**GET** `/api/admin/stats/site`

Get site-wide statistics for a date range.

**Authentication:** Required (ROLE_ADMIN)

**Parameters:**
- `range` (query, optional) - Date range: `today`, `yesterday`, `7days`, `30days` (default: `7days`)

**Response:**
```json
{
  "realtime": {
    "unique_visitors_today": 1247
  },
  "stats": [
    {
      "date": "2025-11-01",
      "total_visits": 4523,
      "unique_visitors": 1247,
      "new_visitors": 312,
      "bounce_rate": 0.42,
      "avg_session_duration": 245
    },
    {
      "date": "2025-10-31",
      "total_visits": 5891,
      "unique_visitors": 1589,
      "new_visitors": 421,
      "bounce_rate": 0.38,
      "avg_session_duration": 267
    }
  ]
}
```

**Field Descriptions:**
- `total_visits` - Total page views
- `unique_visitors` - Unique visitor count
- `new_visitors` - First-time visitors
- `bounce_rate` - Percentage of single-page sessions (0-1)
- `avg_session_duration` - Average session duration in seconds

**Example:**
```bash
# Get site stats for last 7 days
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/site?range=7days"

# Get site stats for today
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/site?range=today"

# Get site stats for last 30 days
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/site?range=30days"
```

**Status Codes:**
- `200 OK` - Success
- `401 Unauthorized` - Missing or invalid JWT token
- `403 Forbidden` - Insufficient permissions

**Caching:** 60 seconds

---

### 4. Real-time Statistics

**GET** `/api/admin/stats/realtime`

Get current real-time statistics (no caching).

**Authentication:** Required (ROLE_ADMIN)

**Parameters:** None

**Response:**
```json
{
  "timestamp": 1761994746,
  "active_sessions": 45,
  "unique_visitors_today": 1247,
  "trending_now": [
    {
      "article_id": 81,
      "views": 245
    },
    {
      "article_id": 92,
      "views": 187
    },
    {
      "article_id": 103,
      "views": 156
    },
    {
      "article_id": 67,
      "views": 134
    },
    {
      "article_id": 88,
      "views": 121
    }
  ]
}
```

**Field Descriptions:**
- `timestamp` - Unix timestamp of response
- `active_sessions` - Number of currently active user sessions
- `unique_visitors_today` - Unique visitors count for today
- `trending_now` - Top 5 trending articles (article_id and views only)

**Example:**
```bash
# Get real-time stats
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/realtime"
```

**Status Codes:**
- `200 OK` - Success
- `401 Unauthorized` - Missing or invalid JWT token
- `403 Forbidden` - Insufficient permissions

**Caching:** None (always fresh data)

**Notes:**
- This endpoint is designed for polling from admin dashboard
- No caching applied to ensure real-time data
- Lightweight response for frequent polling

---

## 🔧 Configuration

### Security Configuration

**File:** `/var/www/deschide_news_app/deschide_backend/config/packages/security.yaml`

```yaml
access_control:
    # Public trending endpoint (for frontend)
    - { path: ^/api/admin/stats/trending, roles: PUBLIC_ACCESS }

    # Admin statistics endpoints (require ROLE_ADMIN)
    - { path: ^/api/admin/stats, roles: ROLE_ADMIN }
```

**Important:** The order matters! More specific paths must come before general paths.

### Caching

All endpoints except `/realtime` use Redis caching with 60-second TTL:

```php
// Cache key format
"api:stats:article:{$id}:{$dateRange}"
"api:stats:trending:{$limit}:{$locale}"
"api:stats:site:{$dateRange}"
```

To clear cache manually:
```bash
# Clear all stats cache
symfony console cache:pool:clear cache.app

# Or use Redis CLI
redis-cli -n 1
> KEYS deschide_news:cache:api:stats:*
> DEL deschide_news:cache:api:stats:article:81:7days
```

---

## 📈 Frontend Integration Examples

### React Component - Trending Articles

```typescript
// components/TrendingArticles.tsx
import { useEffect, useState } from 'react';

interface TrendingArticle {
  id: number;
  title: string;
  slug: string;
  category: {
    id: number;
    name: string;
    slug: string;
  };
  views_24h: number;
  published_at: string;
}

export function TrendingArticles({ limit = 5 }: { limit?: number }) {
  const [articles, setArticles] = useState<TrendingArticle[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch(`http://127.0.0.1:8081/api/admin/stats/trending?limit=${limit}`, {
      headers: {
        'Accept-Language': 'ro'
      }
    })
      .then(res => res.json())
      .then(data => {
        setArticles(data);
        setLoading(false);
      })
      .catch(err => {
        console.error('Failed to fetch trending:', err);
        setLoading(false);
      });
  }, [limit]);

  if (loading) return <div>Loading...</div>;

  return (
    <div className="trending-articles">
      <h2>Trending Now</h2>
      <ul>
        {articles.map(article => (
          <li key={article.id}>
            <a href={`/articles/${article.slug}`}>
              {article.title}
            </a>
            <span className="views">{article.views_24h} views</span>
            <span className="category">{article.category?.name}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}
```

### React Component - Article Stats Dashboard

```typescript
// components/admin/ArticleStats.tsx
import { useEffect, useState } from 'react';

interface ArticleStats {
  article_id: number;
  title: string;
  current_views: number;
  stats: {
    date: string;
    views: number;
    unique_visitors: number;
    avg_reading_time: number | null;
    completion_rate: number | null;
  }[];
}

export function ArticleStatsChart({ articleId, token }: { articleId: number; token: string }) {
  const [stats, setStats] = useState<ArticleStats | null>(null);
  const [range, setRange] = useState<string>('7days');

  useEffect(() => {
    fetch(`http://127.0.0.1:8081/api/admin/stats/article/${articleId}?range=${range}`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    })
      .then(res => res.json())
      .then(data => setStats(data))
      .catch(err => console.error('Failed to fetch stats:', err));
  }, [articleId, range, token]);

  if (!stats) return <div>Loading...</div>;

  return (
    <div className="article-stats">
      <h2>{stats.title}</h2>
      <div className="current-views">
        Current Views: {stats.current_views}
      </div>

      <select value={range} onChange={e => setRange(e.target.value)}>
        <option value="today">Today</option>
        <option value="yesterday">Yesterday</option>
        <option value="7days">Last 7 Days</option>
        <option value="30days">Last 30 Days</option>
      </select>

      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Views</th>
            <th>Unique Visitors</th>
            <th>Avg Reading Time</th>
            <th>Completion Rate</th>
          </tr>
        </thead>
        <tbody>
          {stats.stats.map(day => (
            <tr key={day.date}>
              <td>{day.date}</td>
              <td>{day.views}</td>
              <td>{day.unique_visitors}</td>
              <td>{day.avg_reading_time ? `${day.avg_reading_time}s` : 'N/A'}</td>
              <td>{day.completion_rate ? `${(day.completion_rate * 100).toFixed(1)}%` : 'N/A'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
```

### React Hook - Real-time Stats Polling

```typescript
// hooks/useRealtimeStats.ts
import { useEffect, useState } from 'react';

interface RealtimeStats {
  timestamp: number;
  active_sessions: number;
  unique_visitors_today: number;
  trending_now: { article_id: number; views: number }[];
}

export function useRealtimeStats(token: string, pollInterval = 5000) {
  const [stats, setStats] = useState<RealtimeStats | null>(null);

  useEffect(() => {
    const fetchStats = () => {
      fetch('http://127.0.0.1:8081/api/admin/stats/realtime', {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      })
        .then(res => res.json())
        .then(data => setStats(data))
        .catch(err => console.error('Failed to fetch realtime stats:', err));
    };

    // Initial fetch
    fetchStats();

    // Poll every 5 seconds
    const interval = setInterval(fetchStats, pollInterval);

    return () => clearInterval(interval);
  }, [token, pollInterval]);

  return stats;
}

// Usage in component
function AdminDashboard({ token }: { token: string }) {
  const realtimeStats = useRealtimeStats(token);

  if (!realtimeStats) return <div>Loading...</div>;

  return (
    <div className="admin-dashboard">
      <div className="stat-card">
        <h3>Active Sessions</h3>
        <div className="value">{realtimeStats.active_sessions}</div>
      </div>
      <div className="stat-card">
        <h3>Unique Visitors Today</h3>
        <div className="value">{realtimeStats.unique_visitors_today}</div>
      </div>
    </div>
  );
}
```

---

## 🧪 Testing

### Manual Testing with curl

```bash
# Set your JWT token
TOKEN="your_jwt_token_here"

# Test article stats
curl -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/article/81?range=7days" | jq '.'

# Test trending (public, no auth)
curl "http://127.0.0.1:8081/api/admin/stats/trending?limit=10" | jq '.'

# Test site stats
curl -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/site?range=30days" | jq '.'

# Test realtime stats
curl -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/realtime" | jq '.'
```

### Automated Testing (PHPUnit)

```php
// tests/Controller/Api/StatsControllerTest.php
namespace App\Tests\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StatsControllerTest extends WebTestCase
{
    private string $jwtToken;

    protected function setUp(): void
    {
        parent::setUp();
        // Get JWT token for admin user
        $this->jwtToken = $this->getAdminToken();
    }

    public function testTrendingEndpointIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/admin/stats/trending?limit=5');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
    }

    public function testArticleStatsRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/admin/stats/article/1');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testArticleStatsWithAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/admin/stats/article/1', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwtToken
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('article_id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('stats', $data);
    }

    private function getAdminToken(): string
    {
        // Implementation to get admin JWT token
        // ...
    }
}
```

---

## 🐛 Troubleshooting

### Issue: 401 Unauthorized

**Cause:** Missing or invalid JWT token

**Solution:**
1. Ensure you're sending the Authorization header: `Authorization: Bearer YOUR_TOKEN`
2. Check that token is not expired (default: 1 hour expiry)
3. Generate a new token if needed: `symfony console app:test:jwt-token`

### Issue: 403 Forbidden

**Cause:** User doesn't have ROLE_ADMIN

**Solution:**
1. Verify user has ROLE_ADMIN in database
2. Check JWT token payload contains ROLE_ADMIN in roles array
3. Clear security cache: `symfony console cache:clear`

### Issue: Empty stats array

**Cause:** No aggregated data for the date range

**Solution:**
1. Check if cron jobs are running: `docs/cron-setup.md`
2. Manually trigger aggregation: `symfony console app:aggregate-daily-stats`
3. Verify data exists in Redis: `redis-cli -n 1 KEYS "deschide_news:stats:*"`

### Issue: Null title/slug in trending

**Cause:** Article doesn't have translation for requested locale

**Solution:**
1. This is expected behavior - articles may not have all translations
2. Frontend should handle null values gracefully
3. Add missing translations via admin panel

### Issue: Stale cached data

**Cause:** Cache TTL is 60 seconds

**Solution:**
1. Wait for cache to expire (max 60 seconds)
2. Manually clear cache: `redis-cli -n 1 DEL "deschide_news:cache:api:stats:*"`
3. For real-time data, use `/realtime` endpoint (no cache)

---

## 🔗 Related Documentation

- **Performance Analytics Strategy:** `/var/www/deschide_news_app/docs/performance-analytics-strategy.md`
- **Monitoring Guide:** `/var/www/deschide_news_app/docs/monitoring-guide.md`
- **Cron Setup:** `/var/www/deschide_news_app/docs/cron-setup.md`
- **Redis Schema:** `/var/www/deschide_news_app/docs/redis-schema.md`

---

## 📞 Support

For API issues:
1. Check this documentation
2. Review Symfony logs: `var/log/dev.log`
3. Test endpoint with curl
4. Verify JWT token is valid
5. Check Redis connection: `redis-cli -n 1 PING`

---

**Document Version:** 1.0
**Last Updated:** 2025-11-01
**Maintained By:** Development Team
