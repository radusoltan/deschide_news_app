# Monitoring Guide - Performance & Analytics

**Created:** 2025-11-01
**Purpose:** Comprehensive guide for Prometheus metrics and Grafana dashboards
**Related:** `docs/performance-analytics-strategy.md`, `docs/cron-setup.md`

---

## 📋 Overview

This guide covers the monitoring setup using Prometheus and Grafana for the Deschide News App. The system exposes metrics about:
- Cache performance (hit rate, memory usage)
- HTTP request durations
- Page views and visitor statistics
- Article popularity (trending)
- Active sessions

---

## 🔍 Metrics Exposed

### Cache Metrics

| Metric | Type | Labels | Description |
|--------|------|--------|-------------|
| `deschide_news_cache_hits_total` | Counter | `layer` | Total cache hits by layer (redis) |
| `deschide_news_cache_misses_total` | Counter | `layer` | Total cache misses by layer |
| `deschide_news_cache_hit_rate` | Gauge | `layer` | Calculated cache hit rate (0-1) |
| `deschide_news_cache_memory_bytes` | Gauge | `namespace` | Cache memory usage in bytes |

**Example:**
```promql
# Cache hit rate calculation
sum(rate(deschide_news_cache_hits_total[5m]))
/
(sum(rate(deschide_news_cache_hits_total[5m])) + sum(rate(deschide_news_cache_misses_total[5m])))
```

### Performance Metrics

| Metric | Type | Labels | Description |
|--------|------|--------|-------------|
| `deschide_news_http_request_duration_seconds` | Histogram | `path`, `cached` | HTTP request duration in seconds |
| `deschide_news_active_sessions_current` | Gauge | - | Current number of active sessions |

**Buckets:** `[0.01, 0.05, 0.1, 0.2, 0.5, 1.0, 2.0, 5.0]` seconds

**Example:**
```promql
# P95 response time for cached requests
histogram_quantile(0.95, rate(deschide_news_http_request_duration_seconds_bucket{cached="true"}[5m]))

# P95 response time for uncached requests
histogram_quantile(0.95, rate(deschide_news_http_request_duration_seconds_bucket{cached="false"}[5m]))
```

### Statistics Metrics

| Metric | Type | Labels | Description |
|--------|------|--------|-------------|
| `deschide_news_pageviews_total` | Counter | - | Total number of page views |
| `deschide_news_unique_visitors_total` | Gauge | `date` | Unique visitors count by date |
| `deschide_news_article_views_total` | Gauge | `article_id` | Total views per article (last 24h) |

**Example:**
```promql
# Top 10 trending articles
topk(10, deschide_news_article_views_total)

# Pageviews rate (req/s)
rate(deschide_news_pageviews_total[5m])

# Unique visitors today
deschide_news_unique_visitors_total{date=~".*"}
```

### System Metrics

| Metric | Type | Labels | Description |
|--------|------|--------|-------------|
| `php_info` | Gauge | `version` | PHP version information |

---

## 🌐 Accessing Dashboards

### Grafana Dashboard

**URL:** http://localhost:3002
**Default Credentials:**
- Username: `admin`
- Password: `admin`

**Dashboard:** "Deschide Performance & Analytics"

**Navigation:**
1. Login to Grafana
2. Go to Dashboards (left sidebar)
3. Search for "Deschide"
4. Click on "Deschide Performance & Analytics"

**Dashboard Features:**
- Auto-refresh: 5 seconds
- Time range: Last 6 hours (default, adjustable)
- 8 panels showing key metrics
- Real-time updates

### Prometheus UI

**URL:** http://localhost:9090

**Useful Pages:**
- **Targets:** http://localhost:9090/targets
  - Check if `deschide_backend` target is UP
  - Shows scrape status and last scrape time
- **Graph:** http://localhost:9090/graph
  - Run PromQL queries
  - Visualize metrics
- **Alerts:** http://localhost:9090/alerts
  - View active alerts
  - Check alert rules

---

## 📊 Grafana Dashboard Panels

### Panel 1: Cache Hit Rate (Gauge)

**Position:** Top-left
**Type:** Gauge
**Query:**
```promql
sum(rate(deschide_news_cache_hits_total[5m]))
/
(sum(rate(deschide_news_cache_hits_total[5m])) + sum(rate(deschide_news_cache_misses_total[5m])))
```

**Settings:**
- Unit: Percent (0.0-1.0)
- Thresholds:
  - Red: <0.6 (60%)
  - Yellow: 0.6-0.8 (60%-80%)
  - Green: >0.8 (80%)
- Min: 0, Max: 1

**What it shows:** Current cache hit rate. Target: >80%

### Panel 2: Response Time P95 (Time Series)

**Position:** Top-center
**Type:** Time series
**Queries:**
```promql
# Cached requests (P95)
histogram_quantile(0.95, rate(deschide_news_http_request_duration_seconds_bucket{cached="true"}[5m]))

# Uncached requests (P95)
histogram_quantile(0.95, rate(deschide_news_http_request_duration_seconds_bucket{cached="false"}[5m]))
```

**Settings:**
- Unit: seconds (s)
- Y-axis: Min 0
- Legend: Bottom
- Lines + Points

**What it shows:** 95th percentile response time comparison (cached vs uncached)

### Panel 3: Unique Visitors Today (Stat)

**Position:** Top-right
**Type:** Stat
**Query:**
```promql
deschide_news_unique_visitors_total{date=~".*"}
```

**Settings:**
- Color: Blue
- Show: Value + Sparkline
- Graph mode: Area

**What it shows:** Today's unique visitor count

### Panel 4: Top 10 Trending Articles (Table)

**Position:** Middle-left
**Type:** Table
**Query:**
```promql
topk(10, deschide_news_article_views_total)
```

**Settings:**
- Columns: article_id, Value (views)
- Sort: Value descending
- Table display

**What it shows:** Most viewed articles in last 24 hours

### Panel 5: Cache Memory Usage (Time Series)

**Position:** Middle-center
**Type:** Time series (stacked)
**Query:**
```promql
deschide_news_cache_memory_bytes{namespace=~"cache|stats"}
```

**Settings:**
- Unit: bytes (IEC - MB, GB)
- Stack: True
- Legend: namespace
- Fill opacity: 50%

**What it shows:** Memory usage by cache namespace over time

### Panel 6: Pageviews Per Second (Time Series)

**Position:** Middle-right
**Type:** Time series
**Query:**
```promql
rate(deschide_news_pageviews_total[5m])
```

**Settings:**
- Unit: ops (operations per second)
- Fill opacity: 20%
- Line width: 2

**What it shows:** Traffic rate (requests/second)

### Panel 7: Active Sessions (Stat)

**Position:** Bottom-left
**Type:** Stat
**Query:**
```promql
deschide_news_active_sessions_current
```

**Settings:**
- Color: Green
- Show: Value
- Font size: Large

**What it shows:** Current number of active user sessions

### Panel 8: Cache Operations Rate (Time Series)

**Position:** Bottom-center
**Type:** Time series
**Queries:**
```promql
# Cache hits
rate(deschide_news_cache_hits_total[5m])

# Cache misses
rate(deschide_news_cache_misses_total[5m])
```

**Settings:**
- Unit: ops (operations/sec)
- Legend: Hits (green), Misses (red)
- Stack: False

**What it shows:** Cache hit and miss rates over time

---

## 🔔 Alerting Rules

### Alert 1: Low Cache Hit Rate

**Severity:** Warning
**Condition:** Cache hit rate < 60% for 5 minutes
**Query:**
```promql
sum(rate(deschide_news_cache_hits_total[5m]))
/
(sum(rate(deschide_news_cache_hits_total[5m])) + sum(rate(deschide_news_cache_misses_total[5m])))
< 0.6
```

**Message:**
```
Cache hit rate dropped below 60%.

Possible causes:
- Cache memory full (check Redis memory)
- Recent cache clear/invalidation
- Cache TTL too short
- Traffic spike with new content

Actions:
1. Check Redis memory: redis-cli -n 1 INFO memory
2. Review cache invalidation logs
3. Check recent deployments
4. Consider increasing cache memory limit
```

### Alert 2: High Traffic Spike

**Severity:** Info
**Condition:** Page views > 100 req/s for 2 minutes
**Query:**
```promql
rate(deschide_news_pageviews_total[5m]) > 100
```

**Message:**
```
Traffic spike detected (>100 req/s).

Current traffic is significantly higher than normal.

Actions:
1. Monitor server resources (CPU, memory, disk I/O)
2. Check if legitimate traffic or potential DDoS
3. Verify cache hit rate remains high
4. Consider scaling if sustained
```

### Alert 3: Cache Memory Critical

**Severity:** Critical
**Condition:** Cache memory > 250 MB for 5 minutes
**Query:**
```promql
deschide_news_cache_memory_bytes{namespace="cache"} > 250000000
```

**Message:**
```
Cache memory usage critical (>250MB).

Redis is approaching memory limit.

Actions:
1. Review cache TTL settings (may be too long)
2. Clear unnecessary cache: symfony console app:cache:clear
3. Increase Redis maxmemory if needed
4. Check for memory leaks
```

### Alert 4: RabbitMQ Queue Depth High

**Severity:** Warning
**Condition:** Queue depth > 1000 messages for 10 minutes
**Query (requires RabbitMQ exporter):**
```promql
rabbitmq_queue_messages{queue=~"deschide_news.*"} > 1000
```

**Message:**
```
RabbitMQ queue depth high (>1000 messages).

Messages are backing up faster than consumers can process.

Actions:
1. Check if consumers are running: ps aux | grep messenger:consume
2. Scale consumers: start additional worker processes
3. Check for slow queries in handlers
4. Review message failure logs
```

---

## 🔧 Configuration

### Prometheus Scrape Configuration

**File:** `/etc/prometheus/prometheus.yml` (requires sudo access)

**Configuration:**
```yaml
scrape_configs:
  - job_name: 'deschide_backend'
    scrape_interval: 15s
    scrape_timeout: 10s
    static_configs:
      - targets: ['127.0.0.1:8081']
    metrics_path: '/metrics'
    scheme: 'http'
```

**Apply changes:**
```bash
# Restart Prometheus
sudo systemctl restart prometheus

# Check status
sudo systemctl status prometheus

# Verify target
curl http://localhost:9090/targets
```

### Grafana Data Source

**Configuration:**
1. Login to Grafana: http://localhost:3002
2. Go to Configuration → Data Sources
3. Add data source → Prometheus
4. Settings:
   - URL: `http://localhost:9090`
   - Access: Server (default)
   - Scrape interval: 15s
5. Click "Save & Test"

---

## 📈 Common Queries

### Cache Performance

```promql
# Overall cache hit rate
sum(rate(deschide_news_cache_hits_total[5m])) / (sum(rate(deschide_news_cache_hits_total[5m])) + sum(rate(deschide_news_cache_misses_total[5m])))

# Cache hits per second
rate(deschide_news_cache_hits_total[5m])

# Cache memory usage
deschide_news_cache_memory_bytes{namespace="cache"}
```

### Response Times

```promql
# P50 (median) response time
histogram_quantile(0.50, rate(deschide_news_http_request_duration_seconds_bucket[5m]))

# P95 response time
histogram_quantile(0.95, rate(deschide_news_http_request_duration_seconds_bucket[5m]))

# P99 response time
histogram_quantile(0.99, rate(deschide_news_http_request_duration_seconds_bucket[5m]))

# Average response time
rate(deschide_news_http_request_duration_seconds_sum[5m]) / rate(deschide_news_http_request_duration_seconds_count[5m])
```

### Traffic & Visitors

```promql
# Pageviews rate (req/s)
rate(deschide_news_pageviews_total[5m])

# Total pageviews today
increase(deschide_news_pageviews_total[24h])

# Unique visitors today
deschide_news_unique_visitors_total{date=~".*"}

# Top 20 trending articles
topk(20, deschide_news_article_views_total)
```

---

## 🛠️ Troubleshooting

### Metrics Not Showing in Prometheus

**Symptom:** Prometheus shows no metrics for `deschide_backend`

**Steps:**
1. Check Prometheus target status: http://localhost:9090/targets
2. Verify backend is running:
   ```bash
   symfony server:status
   ```
3. Test metrics endpoint directly:
   ```bash
   curl http://127.0.0.1:8081/metrics
   ```
4. Check Prometheus logs:
   ```bash
   sudo journalctl -u prometheus -f
   ```
5. Verify firewall allows connections to port 8081

**Solution:** If target shows as "DOWN", restart backend and Prometheus

### Dashboard Shows "No data"

**Symptom:** Grafana panels show "No data" or empty graphs

**Steps:**
1. Verify Prometheus datasource:
   - Configuration → Data Sources → Prometheus
   - Click "Test" button
   - Should show "Data source is working"

2. Check time range:
   - Top-right corner: time range selector
   - Try "Last 1 hour" or "Last 6 hours"

3. Test query directly in Prometheus:
   - Go to http://localhost:9090/graph
   - Paste the panel query
   - Click "Execute"

4. Check if metrics exist:
   ```bash
   curl http://127.0.0.1:8081/metrics | grep deschide_news
   ```

**Solution:** Generate some traffic to populate metrics, then refresh dashboard

### Alerts Not Firing

**Symptom:** Alert conditions met but notifications not received

**Steps:**
1. Check alert rule status in Grafana:
   - Alerting → Alert rules
   - Verify rule is "OK" or "Firing"

2. Test notification channel:
   - Alerting → Notification channels
   - Click "Test" button

3. Check notification channel configuration:
   - Email: verify SMTP settings
   - Slack: verify webhook URL

4. Review Grafana logs:
   ```bash
   sudo journalctl -u grafana-server -f
   ```

**Solution:** Fix notification channel configuration and test again

### High Memory Usage

**Symptom:** Redis memory usage growing continuously

**Steps:**
1. Check Redis memory:
   ```bash
   redis-cli -n 1 INFO memory
   ```

2. Check key count:
   ```bash
   redis-cli -n 1 DBSIZE
   ```

3. Inspect cache keys:
   ```bash
   redis-cli -n 1 KEYS "deschide_news:cache:*" | head -20
   ```

4. Check TTL on keys:
   ```bash
   redis-cli -n 1 TTL "deschide_news:cache:api:articles:1:ro"
   ```

**Solution:**
- Clear cache: `symfony console app:cache:clear --force`
- Adjust TTL in `PerformanceService.php`
- Increase Redis `maxmemory` limit

### Slow Dashboard Load

**Symptom:** Dashboard takes >5 seconds to load

**Steps:**
1. Reduce time range (e.g., Last 1 hour instead of Last 24 hours)
2. Increase refresh interval (10s instead of 5s)
3. Simplify complex queries:
   - Use recording rules in Prometheus
   - Aggregate data with longer intervals

4. Check Prometheus query performance:
   - Status → Query → Slow queries

**Solution:** Optimize queries and adjust dashboard settings

---

## 📸 Screenshots

### Grafana Dashboard Overview
![Dashboard Overview](screenshots/grafana-overview.png)

### Cache Hit Rate Panel
![Cache Hit Rate](screenshots/cache-hit-rate.png)

### Trending Articles Table
![Trending Articles](screenshots/trending-articles.png)

*Note: Screenshots to be added after dashboard creation*

---

## 🔗 Related Documentation

- **Performance Analytics Strategy:** `/var/www/deschide_news_app/docs/performance-analytics-strategy.md`
- **Cron Setup Guide:** `/var/www/deschide_news_app/docs/cron-setup.md`
- **Redis Schema:** `/var/www/deschide_news_app/docs/redis-schema.md`
- **Statistics Schema:** `/var/www/deschide_news_app/docs/statistics-schema.md`

---

## 📞 Support

For issues with monitoring:
1. Check this troubleshooting guide first
2. Review Prometheus/Grafana logs
3. Test metrics endpoint: `curl http://127.0.0.1:8081/metrics`
4. Contact DevOps team if issue persists

---

**Document Version:** 1.0
**Last Updated:** 2025-11-01
**Maintained By:** Development Team
