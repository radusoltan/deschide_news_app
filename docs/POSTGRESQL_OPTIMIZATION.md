# PostgreSQL Optimization Guide

## Current Configuration Analysis

**Date:** 2025-11-29
**Database:** deschide
**PostgreSQL Version:** 17.x
**System RAM:** 11GB total (development machine)

### Current Settings (Development)

| Parameter | Current Value | Unit | Context | Notes |
|-----------|--------------|------|---------|-------|
| `shared_buffers` | 16384 (128MB) | 8kB | postmaster | Too small, default value |
| `effective_cache_size` | 524288 (4GB) | 8kB | user | Reasonable for 11GB RAM |
| `work_mem` | 4096 (4MB) | kB | user | Too small for complex queries |
| `random_page_cost` | 4.0 | - | user | Default for HDD, not SSD |
| `max_connections` | 100 | - | postmaster | Acceptable |
| `log_min_duration_statement` | -1 | ms | superuser | Disabled (logging off) |

**Analysis:**
- ❌ `shared_buffers` is at default 128MB (only ~1% of RAM) - PostgreSQL recommendation is 25% of RAM
- ❌ `random_page_cost` is 4.0 (HDD default) - should be 1.1-1.5 for SSD
- ❌ `work_mem` is only 4MB - too small for sorting and joins on large datasets
- ✅ `effective_cache_size` is 4GB - reasonable estimate of OS cache
- ❌ Query logging is disabled - no slow query detection

### Database Statistics

**Top 10 Tables by Row Count (as of 2025-11-29):**

| Table | Rows | Last Analyzed | Purpose |
|-------|------|--------------|---------|
| `ext_translations` | 917 | 2025-11-29 13:29 | Gedmo translations storage |
| `thumbnails` | 162 | 2025-11-29 13:29 | Generated image thumbnails |
| `article_image` | 150 | 2025-11-29 13:29 | Article-Image associations |
| `article_author` | 147 | 2025-11-29 13:29 | Article-Author associations |
| `live_text_posts` | 90 | 2025-11-29 13:29 | Live text updates |
| `articles` | 81 | 2025-11-29 13:29 | Main articles table |
| `related_articles` | 69 | 2025-11-29 13:29 | Article relationships |
| `images` | 41 | 2025-11-29 13:29 | Original uploaded images |
| `authors` | 14 | 2025-11-29 13:29 | Authors/journalists |

**Note:** Statistics updated via `ANALYZE` command on 2025-11-29.

---

## Recommended Settings for Production (16GB RAM Server)

### Memory Configuration

```ini
# Shared memory for PostgreSQL (25% of RAM for dedicated server)
shared_buffers = 4GB

# OS disk cache estimate (75% of RAM)
effective_cache_size = 12GB

# Memory for sorting and hash operations per connection
work_mem = 64MB

# Memory for maintenance operations (VACUUM, CREATE INDEX, ALTER TABLE)
maintenance_work_mem = 512MB

# Background writer settings
bgwriter_delay = 200ms
bgwriter_lru_maxpages = 100
bgwriter_lru_multiplier = 2.0
```

### Disk I/O Configuration (for SSD)

```ini
# Cost of random page fetch (1.1 for SSD, 4.0 for HDD)
random_page_cost = 1.1

# Cost of sequential page fetch
seq_page_cost = 1.0

# Number of concurrent disk I/O operations (for SSD)
effective_io_concurrency = 200

# Enable parallel queries
max_parallel_workers_per_gather = 4
max_parallel_workers = 8
max_worker_processes = 8
```

### Query Planner

```ini
# Planner cost constants
cpu_tuple_cost = 0.01
cpu_index_tuple_cost = 0.005
cpu_operator_cost = 0.0025

# Default statistics target (100 = more detailed statistics)
default_statistics_target = 100
```

### Write-Ahead Log (WAL)

```ini
# WAL writer settings for better performance
wal_buffers = 16MB
wal_writer_delay = 200ms
checkpoint_completion_target = 0.9

# WAL level for replication (if needed)
wal_level = replica
max_wal_senders = 3
```

### Logging and Monitoring

```ini
# Log slow queries (queries taking more than 1 second)
log_min_duration_statement = 1000

# Log checkpoints for monitoring
log_checkpoints = on

# Log connections and disconnections
log_connections = on
log_disconnections = on

# Log line format
log_line_prefix = '%t [%p]: [%l-1] user=%u,db=%d,app=%a,client=%h '

# Rotate logs daily
log_filename = 'postgresql-%Y-%m-%d_%H%M%S.log'
log_rotation_age = 1d
log_rotation_size = 100MB
```

### Connection Management

```ini
# Maximum connections (adjust based on application needs)
max_connections = 100

# Superuser reserved connections
superuser_reserved_connections = 3
```

---

## Recommended Settings for Development (11GB RAM Machine)

For development environment with 11GB RAM (like current setup):

```ini
# Memory settings (more conservative for shared machine)
shared_buffers = 2GB              # ~18% of RAM
effective_cache_size = 8GB        # ~70% of RAM
work_mem = 32MB                   # Half of production
maintenance_work_mem = 256MB      # Half of production

# SSD optimization
random_page_cost = 1.1
effective_io_concurrency = 200

# Logging (useful for development)
log_min_duration_statement = 500  # Log queries > 500ms
log_checkpoints = on
log_connections = off             # Too noisy for dev
log_disconnections = off

# Connection limit (lower for dev)
max_connections = 50
```

---

## How to Apply Configuration Changes

### Method 1: Edit postgresql.conf (Requires sudo/root)

1. **Locate postgresql.conf:**
   ```bash
   sudo -u postgres psql -c "SHOW config_file;"
   ```

2. **Edit the file:**
   ```bash
   sudo nano /etc/postgresql/17/main/postgresql.conf
   ```

3. **Add or modify settings:**
   ```ini
   # Add recommended settings from above
   shared_buffers = 2GB
   effective_cache_size = 8GB
   work_mem = 32MB
   random_page_cost = 1.1
   # ... etc
   ```

4. **Restart PostgreSQL:**
   ```bash
   sudo systemctl restart postgresql
   ```

5. **Verify changes:**
   ```bash
   PGPASSWORD='iIzmHACi7+W+yq9NFRT2FeadUPAmgEna' psql -h 127.0.0.1 -U deschide_admin -d deschide -c "SHOW shared_buffers;"
   ```

### Method 2: ALTER SYSTEM (Requires superuser, but no file editing)

```sql
-- Connect as superuser
sudo -u postgres psql

-- Apply settings
ALTER SYSTEM SET shared_buffers = '2GB';
ALTER SYSTEM SET effective_cache_size = '8GB';
ALTER SYSTEM SET work_mem = '32MB';
ALTER SYSTEM SET random_page_cost = 1.1;
ALTER SYSTEM SET effective_io_concurrency = 200;
ALTER SYSTEM SET log_min_duration_statement = 500;
ALTER SYSTEM SET log_checkpoints = on;

-- Reload configuration (for settings with context = 'user')
SELECT pg_reload_conf();

-- For settings with context = 'postmaster', restart is required
-- Exit psql and run: sudo systemctl restart postgresql
```

### Method 3: Session-Level Settings (No sudo required, temporary)

For development/testing without modifying system-wide config:

```sql
-- Set for current session only
SET work_mem = '64MB';
SET random_page_cost = 1.1;

-- Verify
SHOW work_mem;
SHOW random_page_cost;
```

**Note:** Session-level settings are lost when connection closes.

---

## Extensions for Monitoring

### pg_stat_statements (Query Performance Tracking)

**Status:** Available (version 1.12) but NOT installed - requires superuser

**Verification Results (2025-11-29):**
- Extension available: YES
- Extension installed: NO
- Installation attempt: FAILED (permission denied - must be superuser)
- Current user: deschide_admin (does not have superuser privileges)

**Purpose:**
- Track execution statistics of all SQL statements
- Identify slow queries and performance bottlenecks
- Find queries that consume most resources
- Analyze query patterns and optimize performance
- Monitor database workload over time

**Prerequisites:**
- PostgreSQL superuser access (postgres user or equivalent)
- Server restart capability (for preload configuration)
- Sufficient server memory for statistics tracking

**Installation Steps (Requires Superuser):**

#### Step 1: Configure PostgreSQL to Preload the Extension

Edit `postgresql.conf` (requires root/sudo access):

```bash
# Locate postgresql.conf
sudo -u postgres psql -c "SHOW config_file;"

# Edit the file
sudo nano /etc/postgresql/18/main/postgresql.conf
```

Add or modify these settings:

```ini
# Shared preload libraries (requires restart)
shared_preload_libraries = 'pg_stat_statements'

# pg_stat_statements configuration
pg_stat_statements.track = all              # Track all statements (top-level + nested)
pg_stat_statements.max = 10000              # Maximum number of statements tracked
pg_stat_statements.track_utility = on       # Track utility commands (CREATE, DROP, etc.)
pg_stat_statements.track_planning = on      # Track query planning time (PG 13+)
```

**Configuration Options Explained:**
- `track = all`: Tracks both top-level queries and nested queries (functions, triggers)
- `max = 10000`: Stores statistics for up to 10,000 distinct queries (default is 5000)
- `track_utility = on`: Includes DDL statements (CREATE, ALTER, DROP, VACUUM)
- `track_planning = on`: Separates planning time from execution time (useful for optimization)

#### Step 2: Restart PostgreSQL

```bash
# Restart PostgreSQL service
sudo systemctl restart postgresql

# Verify service is running
sudo systemctl status postgresql
```

#### Step 3: Create the Extension

```sql
-- Connect as superuser
sudo -u postgres psql -d deschide

-- Create extension
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;

-- Verify installation
\dx pg_stat_statements

-- Test query
SELECT COUNT(*) FROM pg_stat_statements;
```

Expected output:
```
List of installed extensions
      Name          | Version |   Schema   |                        Description
--------------------+---------+------------+-----------------------------------------------------------
 pg_stat_statements | 1.12    | public     | track planning and execution statistics of all SQL statements
```

#### Step 4: Verify Extension is Working

```sql
-- Check if statistics are being collected
SELECT
    COUNT(*) as total_queries,
    SUM(calls) as total_calls
FROM pg_stat_statements;

-- View sample queries
SELECT
    substring(query, 1, 60) as query_preview,
    calls,
    round(total_exec_time::numeric, 2) as total_ms
FROM pg_stat_statements
ORDER BY calls DESC
LIMIT 5;
```

**Usage Examples:**

```sql
-- Top 10 slowest queries by average execution time
SELECT
    substring(query, 1, 100) as query_preview,
    calls,
    round(total_exec_time::numeric, 2) as total_ms,
    round(mean_exec_time::numeric, 2) as mean_ms,
    round(max_exec_time::numeric, 2) as max_ms,
    round(stddev_exec_time::numeric, 2) as stddev_ms
FROM pg_stat_statements
ORDER BY mean_exec_time DESC
LIMIT 10;

-- Top 10 queries by total execution time (most resource-consuming)
SELECT
    substring(query, 1, 100) as query_preview,
    calls,
    round(total_exec_time::numeric, 2) as total_ms,
    round((total_exec_time / SUM(total_exec_time) OVER ()) * 100, 2) as percent_total
FROM pg_stat_statements
ORDER BY total_exec_time DESC
LIMIT 10;

-- Top 10 most frequently called queries
SELECT
    substring(query, 1, 100) as query_preview,
    calls,
    round(total_exec_time::numeric, 2) as total_ms,
    round(mean_exec_time::numeric, 2) as mean_ms
FROM pg_stat_statements
ORDER BY calls DESC
LIMIT 10;

-- Queries with high planning time (optimization candidates)
SELECT
    substring(query, 1, 100) as query_preview,
    calls,
    round(total_plan_time::numeric, 2) as total_plan_ms,
    round(mean_plan_time::numeric, 2) as mean_plan_ms,
    round(total_exec_time::numeric, 2) as total_exec_ms,
    round(mean_exec_time::numeric, 2) as mean_exec_ms
FROM pg_stat_statements
WHERE mean_plan_time > 10  -- Planning takes more than 10ms
ORDER BY mean_plan_time DESC
LIMIT 10;

-- Queries that cause most disk I/O (temp files)
SELECT
    substring(query, 1, 100) as query_preview,
    calls,
    round(mean_exec_time::numeric, 2) as mean_ms,
    shared_blks_hit,
    shared_blks_read,
    shared_blks_written,
    temp_blks_written
FROM pg_stat_statements
WHERE temp_blks_written > 0
ORDER BY temp_blks_written DESC
LIMIT 10;

-- Reset statistics (useful after optimization)
SELECT pg_stat_statements_reset();
```

**Monitoring Recommendations:**

1. **Weekly Review:**
   - Identify slowest queries
   - Check for queries with high call count
   - Look for queries causing disk I/O

2. **Monthly Analysis:**
   - Compare performance trends
   - Identify queries that degrade over time
   - Plan index optimizations

3. **After Code Deployment:**
   - Reset statistics: `SELECT pg_stat_statements_reset();`
   - Monitor new query patterns
   - Verify performance improvements

**Troubleshooting:**

If extension creation fails:
```sql
-- Check if shared_preload_libraries is set
SHOW shared_preload_libraries;

-- If not set, PostgreSQL needs restart after updating postgresql.conf
-- Check PostgreSQL logs for errors
sudo tail -f /var/log/postgresql/postgresql-18-main.log
```

Common errors:
- "permission denied to create extension" → Need superuser access
- "extension not found" → shared_preload_libraries not configured or server not restarted
- "could not access file" → PostgreSQL contrib package not installed

**Production Deployment Notes:**

For production environment:
1. Apply configuration during maintenance window (requires restart)
2. Monitor memory usage after enabling (statistics consume RAM)
3. Set up automated reporting (weekly slow query reports)
4. Configure pg_stat_statements.max based on application complexity
5. Consider using external monitoring tools (pgBadger, pganalyze) that parse pg_stat_statements data

**Alternative: Manual Query Logging**

If pg_stat_statements cannot be installed, use query logging:

```ini
# In postgresql.conf
log_min_duration_statement = 1000  # Log queries taking > 1 second
log_line_prefix = '%t [%p]: [%l-1] user=%u,db=%d,app=%a '
log_statement = 'ddl'              # Log DDL statements
```

Then parse logs with tools like pgBadger:
```bash
pgbadger /var/log/postgresql/postgresql-18-main.log -o report.html
```

### Other Useful Extensions

```sql
-- Already installed
SELECT name, installed_version, comment
FROM pg_available_extensions
WHERE installed_version IS NOT NULL
ORDER BY name;

-- Available but not installed
SELECT name, default_version, comment
FROM pg_available_extensions
WHERE installed_version IS NULL
AND name IN ('pg_stat_statements', 'pg_trgm', 'btree_gin', 'btree_gist')
ORDER BY name;
```

---

## Maintenance Tasks

### Regular ANALYZE

Run ANALYZE regularly to keep statistics up-to-date:

```sql
-- Analyze all tables
ANALYZE;

-- Analyze specific table
ANALYZE articles;

-- Analyze with verbose output
ANALYZE VERBOSE articles;
```

**Recommendation:** Set up cron job to run `ANALYZE` weekly or after large data imports.

### VACUUM

Prevent table bloat and reclaim disk space:

```sql
-- Vacuum all tables
VACUUM;

-- Vacuum and analyze together
VACUUM ANALYZE;

-- Vacuum specific table
VACUUM articles;

-- Full vacuum (locks table, use carefully)
VACUUM FULL articles;
```

**Note:** PostgreSQL autovacuum handles this automatically, but manual VACUUM may be needed after bulk operations.

### Reindex

Rebuild indexes for better performance:

```sql
-- Reindex specific table
REINDEX TABLE articles;

-- Reindex specific index
REINDEX INDEX idx_articles_published_at;

-- Reindex entire database (requires exclusive lock)
REINDEX DATABASE deschide;
```

---

## Query Performance Testing

### Test Article Query Performance

```sql
-- Explain query plan (no execution)
EXPLAIN SELECT * FROM articles WHERE status = 'published' ORDER BY published_at DESC LIMIT 10;

-- Explain with execution time
EXPLAIN ANALYZE SELECT * FROM articles WHERE status = 'published' ORDER BY published_at DESC LIMIT 10;

-- Verbose explain (shows detailed stats)
EXPLAIN (ANALYZE, BUFFERS, VERBOSE) SELECT * FROM articles WHERE status = 'published';
```

### Common Performance Queries

```sql
-- Check table sizes
SELECT
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size
FROM pg_tables
WHERE schemaname = 'public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

-- Check index usage
SELECT
    schemaname,
    tablename,
    indexname,
    idx_scan,
    idx_tup_read,
    idx_tup_fetch
FROM pg_stat_user_indexes
ORDER BY idx_scan DESC;

-- Find unused indexes
SELECT
    schemaname,
    tablename,
    indexname,
    idx_scan
FROM pg_stat_user_indexes
WHERE idx_scan = 0
AND indexname NOT LIKE '%_pkey';

-- Check cache hit ratio (should be > 99%)
SELECT
    sum(heap_blks_read) as heap_read,
    sum(heap_blks_hit)  as heap_hit,
    sum(heap_blks_hit) / (sum(heap_blks_hit) + sum(heap_blks_read)) * 100 AS cache_hit_ratio
FROM pg_statio_user_tables;
```

---

## Monitoring Checklist

- [ ] **Memory:** `shared_buffers` set to 25% of RAM
- [ ] **Cache:** `effective_cache_size` set to 75% of RAM
- [ ] **Work Memory:** `work_mem` sized appropriately (32-64MB)
- [ ] **Disk I/O:** `random_page_cost` set to 1.1 for SSD
- [ ] **Logging:** Slow query logging enabled (500-1000ms threshold)
- [ ] **Extensions:** `pg_stat_statements` installed and configured
- [ ] **Statistics:** Regular `ANALYZE` scheduled
- [ ] **Vacuum:** Autovacuum enabled and tuned
- [ ] **Indexes:** Unused indexes identified and removed
- [ ] **Cache Hit Ratio:** Above 99%
- [ ] **Connections:** `max_connections` appropriate for workload

---

## Production Deployment Checklist

Before deploying to production with recommended settings:

1. **Backup Configuration:**
   ```bash
   sudo cp /etc/postgresql/17/main/postgresql.conf /etc/postgresql/17/main/postgresql.conf.backup
   ```

2. **Apply Settings Gradually:**
   - Start with memory settings
   - Then disk I/O settings
   - Finally logging and monitoring

3. **Test After Each Change:**
   - Run application test suite
   - Monitor query performance
   - Check system resource usage

4. **Monitor for 24-48 Hours:**
   - Watch for OOM (Out of Memory) errors
   - Monitor disk I/O wait
   - Check connection pool saturation
   - Review slow query logs

5. **Tune Based on Metrics:**
   - Adjust `work_mem` if seeing temp file writes
   - Increase `shared_buffers` if cache hit ratio is low
   - Tune `max_connections` based on actual usage

---

## Connection Pooling with PgBouncer

### Why Use Connection Pooling?

PostgreSQL creates a new process for each connection, which consumes memory (~5-10MB per connection). Connection pooling:
- Reduces memory overhead
- Decreases connection latency (reuses existing connections)
- Improves scalability under high load
- Prevents connection exhaustion attacks

### PgBouncer Installation and Configuration

#### Step 1: Install PgBouncer

```bash
# Ubuntu/Debian
sudo apt-get install pgbouncer

# Verify installation
pgbouncer --version
```

#### Step 2: Configure PgBouncer

Edit `/etc/pgbouncer/pgbouncer.ini`:

```ini
[databases]
# Database connection routing
deschide = host=127.0.0.1 port=5432 dbname=deschide

[pgbouncer]
# Listen settings
listen_addr = 127.0.0.1
listen_port = 6432
auth_type = md5
auth_file = /etc/pgbouncer/userlist.txt

# Pool mode: transaction is recommended for web applications
# - session: Connection assigned for entire client session (like no pooling)
# - transaction: Connection assigned per transaction (recommended)
# - statement: Connection assigned per statement (most aggressive)
pool_mode = transaction

# Pool sizing
default_pool_size = 20          # Connections per user/database pair
min_pool_size = 5               # Minimum connections to maintain
reserve_pool_size = 5           # Extra connections for burst traffic
max_client_conn = 200           # Maximum client connections to accept

# Timeouts
server_connect_timeout = 10     # Seconds to wait for server connection
server_idle_timeout = 600       # Close idle server connections after 10 min
server_lifetime = 3600          # Close server connections after 1 hour
client_idle_timeout = 0         # Don't timeout idle clients (0 = disabled)
client_login_timeout = 60       # Seconds to wait for client auth

# Query settings
query_timeout = 0               # No timeout (rely on statement_timeout in PG)
query_wait_timeout = 120        # Seconds to wait for connection from pool

# Logging
log_connections = 0             # Reduce log noise in production
log_disconnections = 0
log_pooler_errors = 1           # Log pool errors

# Admin console access
admin_users = postgres
stats_users = postgres, deschide_admin
```

#### Step 3: Configure User Authentication

Create `/etc/pgbouncer/userlist.txt`:

```bash
# Get password hash from PostgreSQL
sudo -u postgres psql -c "SELECT concat('\"', usename, '\" \"', passwd, '\"') FROM pg_shadow WHERE usename = 'deschide_admin';"

# Add output to userlist.txt (example format):
# "deschide_admin" "SCRAM-SHA-256$..."
```

Example userlist.txt:
```
"deschide_admin" "SCRAM-SHA-256$..."
```

#### Step 4: Start PgBouncer

```bash
# Enable and start service
sudo systemctl enable pgbouncer
sudo systemctl start pgbouncer

# Check status
sudo systemctl status pgbouncer

# View logs
sudo journalctl -u pgbouncer -f
```

#### Step 5: Update Symfony Database Connection

Edit `/var/www/deschide_news_app/apps/backend/.env.local`:

```bash
# Change port from 5432 to 6432 (PgBouncer)
DATABASE_URL="postgresql://deschide_admin:PASSWORD@127.0.0.1:6432/deschide?serverVersion=18&charset=utf8"
```

**Important:** When using PgBouncer in transaction mode:
- Prepared statements are NOT supported (set `server_reset_query = DISCARD ALL`)
- Session variables are reset between transactions
- `LISTEN/NOTIFY` won't work across transactions

#### Step 6: Configure Doctrine for PgBouncer

If using prepared statements, disable them in `config/packages/doctrine.yaml`:

```yaml
doctrine:
    dbal:
        # Disable prepared statements for PgBouncer transaction mode
        options:
            # PDO::ATTR_EMULATE_PREPARES
            20: true
```

Or set `server_reset_query` in pgbouncer.ini:
```ini
server_reset_query = DISCARD ALL
```

#### Step 7: Test Connection via PgBouncer

```bash
# Test connection through PgBouncer
psql -h 127.0.0.1 -p 6432 -U deschide_admin -d deschide -c "SELECT 1;"

# Test via Symfony
cd /var/www/deschide_news_app/apps/backend
php bin/console cache:clear
php bin/console doctrine:query:sql "SELECT 1"
```

### PgBouncer Monitoring

#### Admin Console Access

```bash
# Connect to admin console
psql -p 6432 -U postgres pgbouncer

# Show pools status
SHOW POOLS;

# Show statistics
SHOW STATS;

# Show configuration
SHOW CONFIG;

# Show client connections
SHOW CLIENTS;

# Show server connections
SHOW SERVERS;
```

#### Useful Monitoring Queries

```sql
-- Active pools
SHOW POOLS;
-- Columns: database, user, cl_active, cl_waiting, sv_active, sv_idle, sv_used

-- Connection statistics
SHOW STATS;
-- Shows: avg_query_time, total_query_count, etc.

-- Memory usage
SHOW MEM;

-- Configuration verification
SHOW CONFIG;
```

### Production Pool Size Calculation

Formula: `pool_size = (requests_per_second * avg_query_duration_sec) * safety_factor`

Example for Deschide News:
- 100 requests/second peak
- 0.05 seconds average query duration
- Safety factor: 2

`pool_size = (100 * 0.05) * 2 = 10` connections per database

Recommended settings:
```ini
default_pool_size = 15
min_pool_size = 5
reserve_pool_size = 5
max_client_conn = 200
```

### Troubleshooting PgBouncer

1. **Connection refused:**
   ```bash
   # Check if PgBouncer is running
   sudo systemctl status pgbouncer

   # Check listening ports
   ss -tulpn | grep 6432
   ```

2. **Authentication failed:**
   ```bash
   # Verify userlist.txt format
   cat /etc/pgbouncer/userlist.txt

   # Check auth_type matches password format
   # For SCRAM-SHA-256: auth_type = scram-sha-256
   # For MD5: auth_type = md5
   ```

3. **Pool exhaustion:**
   ```sql
   -- In pgbouncer console
   SHOW POOLS;
   -- If cl_waiting > 0, increase pool_size
   ```

4. **Prepared statement errors:**
   - Either disable prepared statements in Doctrine
   - Or use `session` pool mode (less efficient)

---

## References

- [PostgreSQL Performance Tuning](https://wiki.postgresql.org/wiki/Performance_Optimization)
- [PostgreSQL Configuration](https://www.postgresql.org/docs/current/runtime-config.html)
- [PGTune](https://pgtune.leopard.in.ua/) - PostgreSQL configuration wizard
- [pg_stat_statements Documentation](https://www.postgresql.org/docs/current/pgstatstatements.html)

---

**Last Updated:** 2025-11-29
**Maintainer:** Development Team
**Environment:** Deschide News App - Development
