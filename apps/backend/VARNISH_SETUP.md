# Varnish HTTP Cache Setup Guide

## Overview

This guide walks through installing and configuring Varnish Cache 7.x as an HTTP accelerator for the Deschide News Backend API.

**Expected Performance Improvement:**
- **Before**: 150-260ms average response time
- **After**: 5-10ms for cached requests (90-95% cache hit rate)
- **Improvement**: ~95% reduction in response time

---

## Prerequisites

- Ubuntu 24.04 LTS
- Symfony backend running on port 8081
- Root/sudo access
- Backend already configured with HTTP cache headers (Week 1 optimizations)

---

## Installation

### Step 1: Install Varnish 7.x

```bash
# Update package index
sudo apt update

# Install Varnish
sudo apt install -y varnish

# Verify installation
varnishd -V
# Should show: varnish-7.x.x
```

### Step 2: Copy Configuration Files

```bash
# Backup original config
sudo cp /etc/varnish/default.vcl /etc/varnish/default.vcl.backup

# Copy our optimized VCL configuration
sudo cp /var/www/deschide_news_app/deschide_backend/varnish.vcl /etc/varnish/default.vcl

# Verify syntax
sudo varnishd -C -f /etc/varnish/default.vcl
# Should output compiled VCL without errors
```

### Step 3: Configure Varnish Service

Varnish needs to listen on a public port (we'll use 6081 for testing, 80 for production).

**Edit systemd service:**

```bash
sudo systemctl edit --full varnish
```

**Modify the ExecStart line:**

```ini
[Service]
ExecStart=/usr/sbin/varnishd \
    -a :6081 \
    -T localhost:6082 \
    -f /etc/varnish/default.vcl \
    -s malloc,512M \
    -p feature=+esi_ignore_https \
    -p feature=+esi_disable_xml_check \
    -p default_ttl=3600 \
    -p default_grace=21600
```

**Parameters explained:**
- `-a :6081` - Listen on port 6081 (change to 80 for production)
- `-T localhost:6082` - Management interface
- `-f /etc/varnish/default.vcl` - VCL configuration file
- `-s malloc,512M` - Use 512MB RAM for cache storage (increase in production: 1G, 2G, 4G)
- `-p default_ttl=3600` - Default TTL: 1 hour
- `-p default_grace=21600` - Grace period: 6 hours

### Step 4: Start Varnish

```bash
# Start Varnish service
sudo systemctl start varnish

# Enable on boot
sudo systemctl enable varnish

# Check status
sudo systemctl status varnish

# Verify it's listening on port 6081
sudo ss -tulpn | grep 6081
```

---

## Configuration Details

### Backend Configuration

The VCL file (`varnish.vcl`) is configured to:

**Backend:**
- Connect to: `127.0.0.1:8081` (Symfony dev server)
- Timeout: 5s connect, 60s first byte

**Caching Rules:**

✅ **Cached:**
- `GET /api/articles` (with pagination, filters)
- `GET /api/categories`
- `GET /api/images`
- `GET /api/authors`
- All public API endpoints

❌ **Not Cached:**
- `POST`, `PUT`, `PATCH`, `DELETE` requests
- `/api/login`, `/api/token`, `/api/refresh` (auth endpoints)
- `/api/admin/*` (admin endpoints)
- Requests with `Authorization` header
- Responses with `Cache-Control: private` or `no-cache`

**Cache Key Components:**
- URL path and query parameters
- Host header
- Normalized language (`X-Language: ro|en|ru`)
- Accept header (for JSON-LD vs JSON negotiation)

**TTL (Time To Live):**
- Respects backend `s-maxage` from Cache-Control header
- Default from Symfony: 3600s (1 hour)
- Fallback: 1800s (30 minutes)
- Grace period: 6 hours (serve stale if backend down)

---

## Testing Varnish

### Test 1: Basic Functionality

```bash
# Request through Varnish (first request - MISS)
curl -I http://127.0.0.1:6081/api/articles?itemsPerPage=5

# Expected headers:
# X-Cache: MISS
# X-Cache-Hits: 0
# Cache-Control: max-age=1800, public, s-maxage=3600
```

```bash
# Second request - should be HIT
curl -I http://127.0.0.1:6081/api/articles?itemsPerPage=5

# Expected headers:
# X-Cache: HIT
# X-Cache-Hits: 1
# Age: <seconds since cached>
```

### Test 2: Response Time Comparison

**Without Varnish (direct to Symfony):**
```bash
time curl -s http://127.0.0.1:8081/api/articles?itemsPerPage=10 > /dev/null

# Expected: 0.20s - 0.30s (200-300ms)
```

**With Varnish (cached):**
```bash
# First request (populate cache)
curl -s http://127.0.0.1:6081/api/articles?itemsPerPage=10 > /dev/null

# Second request (from cache)
time curl -s http://127.0.0.1:6081/api/articles?itemsPerPage=10 > /dev/null

# Expected: 0.005s - 0.010s (5-10ms) ⚡
# Improvement: ~95% reduction!
```

### Test 3: Multilanguage Support

```bash
# Romanian (default)
curl -H "Accept-Language: ro" -I http://127.0.0.1:6081/api/articles
# X-Language: ro

# English
curl -H "Accept-Language: en" -I http://127.0.0.1:6081/api/articles
# X-Language: en

# Russian
curl -H "Accept-Language: ru" -I http://127.0.0.1:6081/api/articles
# X-Language: ru

# Each language is cached separately
```

### Test 4: Cache Purging

```bash
# Purge specific URL
curl -X PURGE http://127.0.0.1:6081/api/articles?itemsPerPage=5

# Expected: 200 Purged

# Ban all articles
curl -X BAN http://127.0.0.1:6081/api/articles

# Expected: 200 Ban added
```

---

## Monitoring Varnish

### Real-time Statistics

```bash
# Live traffic monitor
sudo varnishstat

# Key metrics to watch:
# - cache_hit: Number of cache hits
# - cache_miss: Number of cache misses
# - cache_hitpass: Cache bypasses
# - n_object: Number of cached objects
```

### Hit Rate Calculation

```bash
# Get hit rate over last 60 seconds
sudo varnishstat -1 -f MAIN.cache_hit -f MAIN.cache_miss

# Example output:
# MAIN.cache_hit    1542   # Hits
# MAIN.cache_miss    158   # Misses
# Hit rate: 1542/(1542+158) = 90.7%
```

### View Cache Contents

```bash
# Show all cached objects
sudo varnishadm "ban.list"

# Show backend health
sudo varnishadm "backend.list"
```

### Access Logs

```bash
# Follow Varnish access log
sudo varnishlog

# Filter by URL pattern
sudo varnishlog -q "ReqURL ~ '/api/articles'"

# Show only cache hits
sudo varnishlog -g request -q "VCL_call eq 'HIT'"
```

---

## Performance Benchmarking

### Before/After Comparison

**Test script:**
```bash
# Create benchmark script
cat > /tmp/varnish_benchmark.sh << 'EOF'
#!/bin/bash

echo "=== Varnish Cache Performance Test ==="
echo ""

# Test without Varnish (direct)
echo "Direct to Symfony (port 8081):"
ab -n 100 -c 10 http://127.0.0.1:8081/api/articles?itemsPerPage=10 | grep "Time per request"

echo ""
echo "Through Varnish - First Run (cold cache):"
curl -s http://127.0.0.1:6081/api/articles?itemsPerPage=10 > /dev/null
ab -n 100 -c 10 http://127.0.0.1:6081/api/articles?itemsPerPage=10 | grep "Time per request"

echo ""
echo "Through Varnish - Second Run (warm cache):"
ab -n 100 -c 10 http://127.0.0.1:6081/api/articles?itemsPerPage=10 | grep "Time per request"
EOF

chmod +x /tmp/varnish_benchmark.sh
/tmp/varnish_benchmark.sh
```

**Expected Results:**
- **Direct**: 200-300ms per request
- **Varnish (cold)**: 150-250ms (still hitting backend)
- **Varnish (warm)**: 5-15ms per request ⚡ **95% improvement!**

---

## Production Configuration

### Recommended Settings for Production

**1. Listen on Port 80:**
```bash
sudo systemctl edit --full varnish

# Change:
-a :6081
# To:
-a :80
```

**2. Increase Cache Size:**
```bash
# For production, use 2-4GB RAM cache
-s malloc,2G
```

**3. Remove Debug Headers:**

Edit `/etc/varnish/default.vcl` in `vcl_deliver`:
```vcl
# Remove debug headers in production
unset resp.http.X-Cache;
unset resp.http.X-Cache-Hits;
unset resp.http.X-Varnish;
unset resp.http.Via;
```

**4. Enable Varnish Logging:**
```bash
# Install varnishncsa (access log in Apache format)
sudo apt install varnish-modules

# Start logging service
sudo systemctl start varnishncsa
sudo systemctl enable varnishncsa

# View logs
sudo tail -f /var/log/varnish/varnishncsa.log
```

---

## Integration with API Platform

### Symfony Configuration (Already Done in Week 1)

The backend is already configured with HTTP cache headers:

```yaml
# config/packages/api_platform.yaml
api_platform:
    defaults:
        cache_headers:
            max_age: 1800           # 30 min browser cache
            shared_max_age: 3600    # 1 hour Varnish cache
            public: true
            etag: true
            vary: ['Accept', 'Accept-Language', 'Origin']
```

### Cache Invalidation

When content changes (article update, new article), invalidate Varnish cache:

**Method 1: HTTP PURGE**
```php
// src/Service/CacheInvalidationService.php
class CacheInvalidationService
{
    public function purgeArticle(int $articleId): void
    {
        $client = HttpClient::create();
        $client->request('PURGE', "http://127.0.0.1:6081/api/articles/{$articleId}");
    }

    public function purgeArticlesList(): void
    {
        $client = HttpClient::create();
        $client->request('BAN', 'http://127.0.0.1:6081/api/articles');
    }
}
```

**Method 2: Event Subscriber**
```php
// src/EventSubscriber/CacheInvalidationSubscriber.php
class CacheInvalidationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.response' => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        // Invalidate cache on POST/PUT/DELETE
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            // Trigger BAN or PURGE request to Varnish
        }
    }
}
```

---

## Troubleshooting

### Issue: Varnish Not Caching

**Check:**
```bash
# 1. Verify Cache-Control headers from backend
curl -I http://127.0.0.1:8081/api/articles
# Should show: Cache-Control: max-age=1800, public, s-maxage=3600

# 2. Check Varnish logs
sudo varnishlog -g request -q "ReqURL ~ '/api/articles'"

# 3. Verify VCL is loaded
sudo varnishd -C -f /etc/varnish/default.vcl
```

### Issue: Backend Connection Refused

**Check:**
```bash
# Verify Symfony is running on 8081
curl http://127.0.0.1:8081/api

# Check Varnish backend health
sudo varnishadm "backend.list"
```

### Issue: Always Getting MISS

**Possible causes:**
- Cookies being sent (VCL removes them for `/api/*`)
- Authorization header present (bypasses cache)
- Query parameters in different order (Varnish hashes full URL)
- Different Accept-Language values (cached separately)

**Debug:**
```bash
sudo varnishlog -g request -q "ReqURL ~ '/api/articles'" -i ReqHeader -i VCL_call
```

---

## Next Steps (Week 2)

✅ **Day 1: Varnish Setup** (This document)

🔜 **Day 2: Cloudflare CDN Configuration**
- Global edge caching
- DDoS protection
- SSL/TLS termination
- Expected: 20-50ms response time worldwide

🔜 **Day 3: PgBouncer Connection Pooling**
- Reduce DB connection overhead
- Expected: 10-20ms improvement

🔜 **Day 4-5: Load Testing & Monitoring**
- Stress test with Apache Bench / wrk
- Monitor cache hit rates
- Validate 95% improvement

---

## Expected Results

| Metric | Before | After Varnish | Improvement |
|--------|--------|---------------|-------------|
| **5 items** | 200ms | 5-10ms | **95%** ⚡ |
| **10 items** | 263ms | 5-10ms | **96%** ⚡ |
| **20 items** | 425ms | 5-10ms | **98%** ⚡ |
| **30 items** | 551ms | 5-10ms | **98%** ⚡ |
| **Cache hit rate** | N/A | 90-95% | - |
| **Server load** | 100% | 5-10% | **90% reduction** |

---

**Status**: ✅ Varnish configured and ready to deploy
**Next**: Install Varnish and run performance tests
**ROI**: ⭐⭐⭐⭐⭐ (Extremely High)
