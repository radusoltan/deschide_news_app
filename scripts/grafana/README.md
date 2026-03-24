# Grafana Dashboards for Deschide News

Pre-built Grafana dashboard JSON files and Prometheus alerting rules for monitoring the Deschide News platform.

## Prerequisites

- Grafana 10+ running at `http://localhost:3002`
- Prometheus running at `http://localhost:9090` (configured as a data source in Grafana with UID `prometheus`)
- Prometheus scraping the Deschide News backend metrics endpoint at `http://127.0.0.1:8081/metrics`
- Recommended exporters: `node_exporter`, `redis_exporter`, `postgres_exporter`, `elasticsearch_exporter`, `php-fpm_exporter`

## Dashboards

| File | Dashboard | Description |
|------|-----------|-------------|
| `api-performance.json` | Deschide News -- API Performance | Request rates, p95/p99 latency, error rates, slowest endpoints, PHP-FPM workers, cache hit/miss |
| `infrastructure.json` | Deschide News -- Infrastructure | PostgreSQL connections and query duration, Redis memory and hit ratio, Elasticsearch index size, CPU, memory, disk |
| `business-metrics.json` | Deschide News -- Business Metrics | Articles published per day, page views, active users, translation coverage by locale, image uploads, top viewed articles |

## Importing Dashboards

### Option A: Grafana UI (Manual Import)

1. Open Grafana at `http://localhost:3002`
2. Navigate to **Dashboards** (left sidebar) then click **New > Import**
3. Click **Upload dashboard JSON file** and select one of the `.json` files from this directory
4. Select the Prometheus data source when prompted
5. Click **Import**

Repeat for each dashboard file.

### Option B: Provisioning (Automatic)

Copy the JSON files into the Grafana provisioning directory so dashboards load automatically on startup.

1. Create a provisioning config file:

```yaml
# /etc/grafana/provisioning/dashboards/deschide.yaml
apiVersion: 1
providers:
  - name: "Deschide News"
    orgId: 1
    folder: "Deschide News"
    type: file
    disableDeletion: false
    editable: true
    updateIntervalSeconds: 30
    allowUiUpdates: true
    options:
      path: /var/www/deschide_news_app/scripts/grafana
      foldersFromFilesStructure: false
```

2. Restart Grafana:

```bash
sudo systemctl restart grafana-server
```

The three dashboards will appear under the "Deschide News" folder in Grafana.

### Option C: Grafana HTTP API

```bash
# Import a dashboard via the Grafana API
curl -X POST http://localhost:3002/api/dashboards/db \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_GRAFANA_API_KEY" \
  -d "{\"dashboard\": $(cat api-performance.json), \"overwrite\": true, \"folderId\": 0}"
```

## Alerting Rules

### Loading into Prometheus

Copy the alerting rules file and reference it in your Prometheus configuration:

```bash
sudo cp alerting-rules.yaml /etc/prometheus/rules/deschide-alerting-rules.yaml
```

Add to `prometheus.yml`:

```yaml
rule_files:
  - "rules/deschide-alerting-rules.yaml"
```

Reload Prometheus:

```bash
sudo systemctl reload prometheus
# or
curl -X POST http://localhost:9090/-/reload
```

### Loading into Grafana Unified Alerting

If using Grafana's built-in alerting instead of Prometheus Alertmanager:

1. Go to **Alerting > Alert rules** in Grafana
2. Click **New alert rule** and configure each rule manually based on the expressions in `alerting-rules.yaml`
3. Or use the Grafana provisioning API to import them programmatically

## Data Source Configuration

The dashboards reference a Prometheus data source with UID `prometheus`. If your data source has a different UID, update all three JSON files:

```bash
# Replace the data source UID in all dashboard files
sed -i 's/"uid": "prometheus"/"uid": "YOUR_DATASOURCE_UID"/g' *.json
```

## Prometheus Scrape Configuration

Add these targets to your `prometheus.yml`:

```yaml
scrape_configs:
  - job_name: "deschide"
    scrape_interval: 15s
    metrics_path: "/metrics"
    static_configs:
      - targets: ["127.0.0.1:8081"]

  - job_name: "node"
    static_configs:
      - targets: ["localhost:9100"]

  - job_name: "redis"
    static_configs:
      - targets: ["localhost:9121"]

  - job_name: "postgres"
    static_configs:
      - targets: ["localhost:9187"]

  - job_name: "elasticsearch"
    static_configs:
      - targets: ["localhost:9114"]

  - job_name: "php-fpm"
    static_configs:
      - targets: ["localhost:9253"]
```

## Metric Names Reference

The Deschide News backend exposes metrics under the `deschide_news` namespace (configured in `config/packages/prometheus.yaml`). Key metrics:

| Metric | Type | Description |
|--------|------|-------------|
| `deschide_news_http_request_duration_seconds` | Histogram | HTTP request latency with path and cached labels |
| `deschide_news_cache_hits_total` | Counter | Cache hits by layer (apcu, redis) |
| `deschide_news_cache_misses_total` | Counter | Cache misses by layer |
| `deschide_news_pageviews_total` | Counter | Total page views |
| `deschide_news_active_sessions_current` | Gauge | Current active session count |
| `deschide_news_unique_visitors_total` | Gauge | Unique visitors (by date label) |
| `deschide_news_article_views_total` | Gauge | Views per article (by article_id) |
| `deschide_news_cache_memory_bytes` | Gauge | Cache memory usage by namespace |
| `deschide_news_cache_hit_rate` | Gauge | Cache hit rate by layer |
