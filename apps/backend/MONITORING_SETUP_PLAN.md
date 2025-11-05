# Week 2 - Day 5: Performance Monitoring Setup Plan

**Project**: Deschide News Backend - HTTP Cache Layer Monitoring
**Date**: November 4, 2025
**Status**: 🚧 In Progress

---

## 📋 Objectives

### Primary Goals

1. Setup Prometheus metrics collection for all components
2. Create Grafana dashboards for visualization
3. Configure alerting rules for critical metrics
4. Document performance baselines
5. Create comprehensive Week 2 final report

### Success Criteria

- ✅ Prometheus collecting metrics from all services
- ✅ Grafana dashboards created (4 dashboards minimum)
- ✅ Alerting rules configured (5 rules minimum)
- ✅ Performance baselines documented
- ✅ Week 2 final report completed

---

## 🎯 Monitoring Stack

### Current Infrastructure

**Already Running**:
- ✅ **Prometheus** - Port 9090 (metrics collection)
- ✅ **Grafana** - Port 3002 (visualization)

**Services to Monitor**:
1. **Varnish Cache** (port 6081)
   - Cache hit/miss rates
   - Backend requests
   - Response times
   - Memory usage

2. **PgBouncer** (port 6432)
   - Connection pool status
   - Active/waiting clients
   - Query statistics
   - Pool efficiency

3. **PostgreSQL** (port 5432)
   - Active connections
   - Query performance
   - Database size
   - Locks and conflicts

4. **Symfony Backend** (port 8081)
   - API response times
   - Request throughput
   - Error rates
   - Memory/CPU usage

---

## 🔧 Implementation Plan

### Phase 1: Metrics Exporters Setup

#### 1.1 Varnish Exporter

**Tool**: `prometheus-varnish-exporter`

**Metrics Exposed**:
- `varnish_main_cache_hit` - Cache hits
- `varnish_main_cache_miss` - Cache misses
- `varnish_main_client_req` - Total client requests
- `varnish_main_backend_req` - Backend requests
- `varnish_main_backend_conn` - Backend connections

**Installation**:
```bash
# Install varnish exporter
sudo apt install prometheus-varnish-exporter

# Or download binary
wget https://github.com/jonnenauha/prometheus_varnish_exporter/releases/download/1.6/prometheus_varnish_exporter-1.6.linux-amd64.tar.gz
tar xvf prometheus_varnish_exporter-1.6.linux-amd64.tar.gz
sudo mv prometheus_varnish_exporter /usr/local/bin/

# Run exporter (default port 9131)
prometheus_varnish_exporter
```

**Prometheus Config**:
```yaml
scrape_configs:
  - job_name: 'varnish'
    static_configs:
      - targets: ['localhost:9131']
```

#### 1.2 PostgreSQL Exporter

**Tool**: `postgres_exporter`

**Metrics Exposed**:
- `pg_stat_database_*` - Database statistics
- `pg_stat_activity_*` - Connection activity
- `pg_locks_*` - Lock information
- `pg_stat_bgwriter_*` - Background writer stats

**Installation**:
```bash
# Install postgres exporter
wget https://github.com/prometheus-community/postgres_exporter/releases/download/v0.15.0/postgres_exporter-0.15.0.linux-amd64.tar.gz
tar xvf postgres_exporter-0.15.0.linux-amd64.tar.gz
sudo mv postgres_exporter /usr/local/bin/

# Set environment variable
export DATA_SOURCE_NAME="postgresql://deschide_admin:sr324395@localhost:5432/deschide?sslmode=disable"

# Run exporter (default port 9187)
postgres_exporter
```

**Prometheus Config**:
```yaml
scrape_configs:
  - job_name: 'postgresql'
    static_configs:
      - targets: ['localhost:9187']
```

#### 1.3 PgBouncer Exporter

**Tool**: Custom script or `pgbouncer_exporter`

**Metrics Exposed**:
- `pgbouncer_pools_*` - Pool statistics
- `pgbouncer_stats_*` - Query statistics
- `pgbouncer_databases_*` - Database stats

**Installation**:
```bash
# Install pgbouncer exporter
go install github.com/prometheus-community/pgbouncer_exporter@latest

# Or use Python script
pip install pgbouncer-exporter

# Run exporter (default port 9127)
pgbouncer_exporter --pgbouncer-connection-string="postgres://deschide_admin:sr324395@localhost:6432/pgbouncer"
```

**Prometheus Config**:
```yaml
scrape_configs:
  - job_name: 'pgbouncer'
    static_configs:
      - targets: ['localhost:9127']
```

#### 1.4 Symfony Metrics

**Tool**: Custom metrics endpoint using Prometheus PHP client

**Installation**:
```bash
# Install Prometheus Bundle
composer require artprima/prometheus-metrics-bundle

# Or use symfony/prometheus-bundle
composer require symfony/prometheus-bundle
```

**Metrics to Expose**:
- `symfony_http_requests_total` - Total requests
- `symfony_http_request_duration_seconds` - Response times
- `symfony_http_requests_errors_total` - Error count
- `symfony_cache_hits_total` - Application cache hits
- `symfony_cache_misses_total` - Application cache misses

**Endpoint**: `http://localhost:8081/metrics`

**Prometheus Config**:
```yaml
scrape_configs:
  - job_name: 'symfony'
    static_configs:
      - targets: ['localhost:8081']
    metrics_path: '/metrics'
```

---

### Phase 2: Prometheus Configuration

**File**: `/etc/prometheus/prometheus.yml`

**Complete Configuration**:
```yaml
global:
  scrape_interval: 15s
  evaluation_interval: 15s
  external_labels:
    cluster: 'deschide-backend'
    environment: 'development'

# Alertmanager configuration (optional)
alerting:
  alertmanagers:
    - static_configs:
        - targets: ['localhost:9093']

# Load rules once and periodically evaluate them
rule_files:
  - "alerts.yml"

# Scrape configurations
scrape_configs:
  # Prometheus itself
  - job_name: 'prometheus'
    static_configs:
      - targets: ['localhost:9090']

  # Varnish Cache
  - job_name: 'varnish'
    static_configs:
      - targets: ['localhost:9131']
    relabel_configs:
      - source_labels: [__address__]
        target_label: instance
        replacement: 'varnish-cache'

  # PostgreSQL Database
  - job_name: 'postgresql'
    static_configs:
      - targets: ['localhost:9187']
    relabel_configs:
      - source_labels: [__address__]
        target_label: instance
        replacement: 'postgresql-main'

  # PgBouncer Connection Pool
  - job_name: 'pgbouncer'
    static_configs:
      - targets: ['localhost:9127']
    relabel_configs:
      - source_labels: [__address__]
        target_label: instance
        replacement: 'pgbouncer-pool'

  # Symfony Backend
  - job_name: 'symfony'
    static_configs:
      - targets: ['localhost:8081']
    metrics_path: '/metrics'
    relabel_configs:
      - source_labels: [__address__]
        target_label: instance
        replacement: 'symfony-backend'

  # Node Exporter (system metrics)
  - job_name: 'node'
    static_configs:
      - targets: ['localhost:9100']
    relabel_configs:
      - source_labels: [__address__]
        target_label: instance
        replacement: 'backend-server'
```

---

### Phase 3: Alerting Rules

**File**: `/etc/prometheus/alerts.yml`

**Critical Alerts**:

```yaml
groups:
  - name: cache_alerts
    interval: 30s
    rules:
      # Alert if cache hit rate drops below 80%
      - alert: LowCacheHitRate
        expr: |
          (rate(varnish_main_cache_hit[5m]) /
           (rate(varnish_main_cache_hit[5m]) + rate(varnish_main_cache_miss[5m]))) < 0.80
        for: 5m
        labels:
          severity: warning
          component: varnish
        annotations:
          summary: "Cache hit rate below 80%"
          description: "Varnish cache hit rate is {{ $value | humanizePercentage }}. Expected > 80%."

      # Alert if cache hit rate drops below 50%
      - alert: CriticalCacheHitRate
        expr: |
          (rate(varnish_main_cache_hit[5m]) /
           (rate(varnish_main_cache_hit[5m]) + rate(varnish_main_cache_miss[5m]))) < 0.50
        for: 2m
        labels:
          severity: critical
          component: varnish
        annotations:
          summary: "CRITICAL: Cache hit rate below 50%"
          description: "Varnish cache hit rate is {{ $value | humanizePercentage }}. Immediate investigation required."

  - name: connection_pool_alerts
    interval: 30s
    rules:
      # Alert if clients are waiting for connections
      - alert: PgBouncerClientsWaiting
        expr: pgbouncer_pools_cl_waiting > 5
        for: 1m
        labels:
          severity: warning
          component: pgbouncer
        annotations:
          summary: "PgBouncer has waiting clients"
          description: "{{ $value }} clients waiting for database connections. Consider increasing pool size."

      # Alert if pool is exhausted
      - alert: PgBouncerPoolExhausted
        expr: pgbouncer_pools_sv_idle == 0 and pgbouncer_pools_cl_waiting > 10
        for: 2m
        labels:
          severity: critical
          component: pgbouncer
        annotations:
          summary: "CRITICAL: Connection pool exhausted"
          description: "No idle connections available and {{ $value }} clients waiting."

  - name: performance_alerts
    interval: 30s
    rules:
      # Alert if p95 response time exceeds 500ms
      - alert: HighResponseTime
        expr: histogram_quantile(0.95, rate(symfony_http_request_duration_seconds_bucket[5m])) > 0.5
        for: 5m
        labels:
          severity: warning
          component: symfony
        annotations:
          summary: "High API response times"
          description: "p95 response time is {{ $value }}s. Expected < 500ms."

      # Alert if error rate exceeds 1%
      - alert: HighErrorRate
        expr: |
          (rate(symfony_http_requests_errors_total[5m]) /
           rate(symfony_http_requests_total[5m])) > 0.01
        for: 5m
        labels:
          severity: warning
          component: symfony
        annotations:
          summary: "High error rate detected"
          description: "Error rate is {{ $value | humanizePercentage }}. Expected < 1%."

  - name: database_alerts
    interval: 30s
    rules:
      # Alert if too many active connections
      - alert: HighDatabaseConnections
        expr: pg_stat_activity_count > 80
        for: 5m
        labels:
          severity: warning
          component: postgresql
        annotations:
          summary: "High number of database connections"
          description: "{{ $value }} active connections. Max recommended: 100."

      # Alert if database locks detected
      - alert: DatabaseLocksDetected
        expr: pg_locks_count > 10
        for: 2m
        labels:
          severity: warning
          component: postgresql
        annotations:
          summary: "Database locks detected"
          description: "{{ $value }} locks currently held. May indicate contention."
```

---

### Phase 4: Grafana Dashboards

#### Dashboard 1: HTTP Cache Performance

**Panels**:
1. **Cache Hit Rate** (gauge)
   - Query: `rate(varnish_main_cache_hit[5m]) / (rate(varnish_main_cache_hit[5m]) + rate(varnish_main_cache_miss[5m]))`
   - Target: > 85%

2. **Cache Hits vs Misses** (time series)
   - Query: `rate(varnish_main_cache_hit[1m])` and `rate(varnish_main_cache_miss[1m])`

3. **Requests Per Second** (graph)
   - Query: `rate(varnish_main_client_req[1m])`

4. **Backend Requests** (graph)
   - Query: `rate(varnish_main_backend_req[1m])`

5. **Cache Efficiency** (stat)
   - Query: `1 - (rate(varnish_main_backend_req[5m]) / rate(varnish_main_client_req[5m]))`
   - Shows % of requests served from cache

#### Dashboard 2: Connection Pool Monitoring

**Panels**:
1. **Pool Status** (table)
   - Active clients: `pgbouncer_pools_cl_active`
   - Waiting clients: `pgbouncer_pools_cl_waiting`
   - Active servers: `pgbouncer_pools_sv_active`
   - Idle servers: `pgbouncer_pools_sv_idle`

2. **Pool Efficiency** (gauge)
   - Query: `pgbouncer_pools_cl_active / pgbouncer_pools_sv_active`
   - Target: > 10:1

3. **Waiting Clients Over Time** (graph)
   - Query: `pgbouncer_pools_cl_waiting`
   - Alert threshold: > 5

4. **Total Transactions** (stat)
   - Query: `rate(pgbouncer_stats_total_xact_count[5m])`

#### Dashboard 3: API Performance

**Panels**:
1. **Request Rate** (graph)
   - Query: `rate(symfony_http_requests_total[1m])`

2. **Response Time Percentiles** (graph)
   - p50: `histogram_quantile(0.50, rate(symfony_http_request_duration_seconds_bucket[5m]))`
   - p95: `histogram_quantile(0.95, rate(symfony_http_request_duration_seconds_bucket[5m]))`
   - p99: `histogram_quantile(0.99, rate(symfony_http_request_duration_seconds_bucket[5m]))`

3. **Error Rate** (gauge)
   - Query: `rate(symfony_http_requests_errors_total[5m]) / rate(symfony_http_requests_total[5m])`

4. **Status Codes Distribution** (pie chart)
   - Query: `sum by (status) (rate(symfony_http_requests_total[5m]))`

#### Dashboard 4: System Overview

**Panels**:
1. **Overall Health** (stat panel row)
   - Cache Hit Rate
   - Request Rate
   - Error Rate
   - Pool Status

2. **Performance Trends** (time series)
   - Response times
   - Throughput
   - Cache effectiveness

3. **Resource Usage** (graphs)
   - CPU usage
   - Memory usage
   - Network I/O

---

### Phase 5: Performance Baselines

**Document the following metrics from Day 4 testing**:

#### Baseline Metrics (No Load)
- Response time (cold cache): 444ms
- Response time (warm cache): 3.4ms
- Cache hit rate: 100%

#### Light Load (100 concurrent)
- RPS: 4,644
- p50: 14ms
- p95: 67ms
- p99: 79ms
- Cache hit rate: ~100%
- Error rate: 0%

#### Medium Load (500 concurrent)
- RPS: 4,950
- p50: 88ms
- p95: 224ms
- p99: 308ms
- Cache hit rate: ~99%
- Error rate: 0%

#### Connection Pool Baseline
- Pool size: 25 connections
- Min idle: 10 connections
- Max clients: 1000
- Efficiency: 20:1 ratio
- Waiting clients: 0

---

## 📊 Dashboard JSON Templates

Will be created for import into Grafana:
1. `grafana-dashboard-cache.json`
2. `grafana-dashboard-pool.json`
3. `grafana-dashboard-api.json`
4. `grafana-dashboard-overview.json`

---

## ✅ Implementation Checklist

### Exporters
- [ ] Install Varnish exporter (port 9131)
- [ ] Install PostgreSQL exporter (port 9187)
- [ ] Install PgBouncer exporter (port 9127)
- [ ] Configure Symfony metrics endpoint
- [ ] Install Node exporter (port 9100) for system metrics

### Prometheus
- [ ] Update prometheus.yml with all targets
- [ ] Create alerts.yml with rules
- [ ] Reload Prometheus configuration
- [ ] Verify all targets are UP
- [ ] Test alert rules

### Grafana
- [ ] Access Grafana (localhost:3002)
- [ ] Add Prometheus data source
- [ ] Import/create 4 dashboards
- [ ] Configure alert notifications
- [ ] Set up dashboard variables
- [ ] Save dashboard JSONs

### Documentation
- [ ] Document baseline metrics
- [ ] Create dashboard usage guide
- [ ] Document alert handling procedures
- [ ] Create Week 2 final report

---

## 🚀 Quick Start Commands

```bash
# 1. Check Prometheus status
curl http://localhost:9090/-/healthy

# 2. Check Grafana status
curl http://localhost:3002/api/health

# 3. Reload Prometheus config
curl -X POST http://localhost:9090/-/reload

# 4. Test metric collection
curl http://localhost:9090/api/v1/targets

# 5. Query metrics via API
curl 'http://localhost:9090/api/v1/query?query=up'
```

---

**Status**: 📋 Plan Complete - Ready for Implementation
**Next**: Install exporters and configure Prometheus

