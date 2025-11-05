# PgBouncer Connection Pooling Setup Guide

## Overview

This guide walks through installing and configuring PgBouncer, a lightweight connection pooler for PostgreSQL that reduces connection overhead and enables better connection management.

**What is PgBouncer?**

PgBouncer is a connection pooler that sits between your application and PostgreSQL database. Instead of opening a new database connection for every request (expensive), it maintains a pool of persistent connections that are reused.

**Architecture:**
```
Symfony Backend (many requests)
    ↓ (1000+ connections)
PgBouncer (port 6432)
    ↓ (25-100 pooled connections)
PostgreSQL (port 5432)
```

**Expected Performance Improvement:**
- **Connection overhead**: 20-50ms → 1-3ms (95% reduction)
- **Max connections**: 100 → 1000+ (10x capacity)
- **Database load**: Reduced connection churn
- **Query latency**: 10-20ms improvement per request

---

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Authentication Setup](#authentication-setup)
5. [Pool Modes](#pool-modes)
6. [Symfony Integration](#symfony-integration)
7. [Testing](#testing)
8. [Monitoring](#monitoring)
9. [Production Deployment](#production-deployment)
10. [Troubleshooting](#troubleshooting)

---

## Prerequisites

- [x] PostgreSQL 17 installed and running
- [x] Symfony backend configured with DATABASE_URL
- [x] Root/sudo access for installation
- [x] Current database connection working

**Current Setup:**
- PostgreSQL: `localhost:5432`
- Database: `deschide_news`
- User: `deschide_admin` (or your actual user)

---

## 1. Installation

### Ubuntu 24.04 / Debian

```bash
# Update package index
sudo apt update

# Install PgBouncer
sudo apt install -y pgbouncer

# Verify installation
pgbouncer --version
# Should show: PgBouncer 1.21.0 (or newer)

# Check service status
sudo systemctl status pgbouncer
```

### Alternative: Install Latest Version

If you need the latest version:

```bash
# Add PostgreSQL APT repository (if not already added)
sudo sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -

# Update and install
sudo apt update
sudo apt install -y pgbouncer
```

---

## 2. Configuration

### Main Configuration File

PgBouncer uses a single configuration file: `/etc/pgbouncer/pgbouncer.ini`

**Backup original config:**
```bash
sudo cp /etc/pgbouncer/pgbouncer.ini /etc/pgbouncer/pgbouncer.ini.backup
```

### Recommended Configuration

Create optimized configuration for Symfony/Doctrine:

```ini
;; PgBouncer Configuration for Deschide News Backend
;; Optimized for Symfony + Doctrine ORM

;; ============================================================================
;; DATABASES
;; ============================================================================

[databases]
; Database connection format:
; dbname = host=hostname port=5432 dbname=database user=username password=secret

; Deschide News database
deschide_news = host=127.0.0.1 port=5432 dbname=deschide_news

; Use "auth_user" for authentication (see userlist.txt)
; deschide_news = host=127.0.0.1 port=5432 dbname=deschide_news auth_user=pgbouncer

; Optional: Add other databases
; test_db = host=127.0.0.1 port=5432 dbname=test_db

;; ============================================================================
;; PGBOUNCER SETTINGS
;; ============================================================================

[pgbouncer]

;;; Administrative settings

; Unix socket directory
unix_socket_dir = /var/run/postgresql

; Log file location (leave empty to use systemd journal)
logfile = /var/log/postgresql/pgbouncer.log

; PID file location
pidfile = /var/run/postgresql/pgbouncer.pid

;;; Connection settings

; Listen on localhost IPv4 and IPv6
listen_addr = 127.0.0.1
listen_port = 6432

; Authentication type
; trust    = no authentication needed (INSECURE, only for testing!)
; md5      = MD5-based password authentication (RECOMMENDED)
; scram-sha-256 = SCRAM-SHA-256 authentication (more secure, requires PG 10+)
auth_type = md5

; User list file (contains username/password pairs)
auth_file = /etc/pgbouncer/userlist.txt

; Admin users (can connect to pgbouncer console)
admin_users = postgres, deschide_admin

; Stats users (read-only access to stats)
stats_users = stats, postgres

;;; Pool mode
; session     = Server is released back to pool after client disconnects (default)
; transaction = Server is released back to pool after transaction finishes (RECOMMENDED)
; statement   = Server is released back to pool after query finishes (rare use)
pool_mode = transaction

;;; Connection limits

; Maximum number of client connections
max_client_conn = 1000

; Default pool size for each database
default_pool_size = 25

; Minimum number of server connections to keep in pool
min_pool_size = 10

; Maximum number of server connections per database
reserve_pool_size = 15

; How long to keep released connections available for immediate reuse (seconds)
reserve_pool_timeout = 5

; Maximum number of server connections per user/database pair
max_db_connections = 100

; Maximum number of server connections per user
max_user_connections = 100

;;; Timeouts

; Client idle timeout (disconnect idle clients after this time)
; 0 = disabled
client_idle_timeout = 0

; Server idle timeout (close server connections idle for this long)
server_idle_timeout = 600

; How long to wait for a connection slot (0 = wait forever)
server_connect_timeout = 15

; Query timeout (0 = disabled)
query_timeout = 0

; Client login timeout
client_login_timeout = 60

;;; Logging

; Log verbosity level
; 0 = quiet, 1 = critical, 2 = error, 3 = warning, 4 = info, 5 = debug
log_connections = 1
log_disconnections = 1
log_pooler_errors = 1
log_stats = 1

; Log queries (useful for debugging, disable in production for performance)
; log_queries = 0

;;; Performance tuning

; Disable Nagle algorithm (improves latency for small queries)
tcp_keepalive = 1
tcp_keepcnt = 5
tcp_keepidle = 30
tcp_keepintvl = 10

; Server lifetime (maximum age before reconnecting)
; Useful for load balancing and avoiding stale connections
server_lifetime = 3600

; Server check delay (how often to check server connections)
server_check_delay = 30

; DNS lookup caching
dns_max_ttl = 15

; DNS lookup timeout
dns_nxdomain_ttl = 15

;;; TLS/SSL (optional, recommended for production)

; client_tls_sslmode = disable
; client_tls_ca_file = /etc/ssl/certs/ca-certificates.crt
; client_tls_cert_file = /etc/pgbouncer/client.crt
; client_tls_key_file = /etc/pgbouncer/client.key

; server_tls_sslmode = prefer
; server_tls_ca_file = /etc/ssl/certs/ca-certificates.crt

;;; Advanced settings

; Cancellation of long-running queries
cancel_wait_timeout = 10

; Application name (shown in pg_stat_activity)
application_name_add_host = 1

; Ignore startup parameters (for compatibility)
ignore_startup_parameters = extra_float_digits

; Disable dangerous commands in transaction pooling mode
disable_pqexec = 0

;; ============================================================================
;; CONSOLE ACCESS
;; ============================================================================

; Admin console is available at:
; psql -h 127.0.0.1 -p 6432 -U postgres -d pgbouncer
```

**Key Settings Explained:**

1. **`pool_mode = transaction`**: Best for Symfony/Doctrine
   - Server connection released after each transaction
   - Allows multiple requests to share connections
   - Compatible with most ORMs

2. **`max_client_conn = 1000`**: Application can open 1000 connections
   - Supports high concurrency

3. **`default_pool_size = 25`**: PgBouncer maintains 25 real PostgreSQL connections
   - 1000 clients → 25 database connections (40x reduction!)

4. **`auth_type = md5`**: Password authentication
   - Secure and compatible

---

## 3. Authentication Setup

### Create User List File

PgBouncer needs to authenticate users. Create `/etc/pgbouncer/userlist.txt`:

**Option 1: Manual Creation (Recommended for Security)**

```bash
# Create userlist.txt
sudo nano /etc/pgbouncer/userlist.txt
```

Add your database users in format:
```
"username" "md5<md5_hash_of_password>"
```

**Generate MD5 hash:**
```bash
# Replace 'your_password' and 'deschide_admin' with actual values
echo -n "your_password deschide_admin" | md5sum | awk '{print "md5"$1}'
```

Example:
```
"deschide_admin" "md5a1b2c3d4e5f6..."
"postgres" "md5f6e5d4c3b2a1..."
```

**Option 2: Extract from PostgreSQL (Easier)**

```bash
# Extract users and passwords from PostgreSQL
sudo -u postgres psql -Atq -c "SELECT usename, passwd FROM pg_shadow" -d postgres > /tmp/userlist.txt

# Copy to PgBouncer directory
sudo cp /tmp/userlist.txt /etc/pgbouncer/userlist.txt
sudo rm /tmp/userlist.txt

# Set permissions
sudo chown postgres:postgres /etc/pgbouncer/userlist.txt
sudo chmod 600 /etc/pgbouncer/userlist.txt
```

### Set Correct Permissions

```bash
# Configuration file
sudo chown postgres:postgres /etc/pgbouncer/pgbouncer.ini
sudo chmod 640 /etc/pgbouncer/pgbouncer.ini

# User list file
sudo chown postgres:postgres /etc/pgbouncer/userlist.txt
sudo chmod 600 /etc/pgbouncer/userlist.txt

# Log file
sudo touch /var/log/postgresql/pgbouncer.log
sudo chown postgres:postgres /var/log/postgresql/pgbouncer.log
sudo chmod 640 /var/log/postgresql/pgbouncer.log
```

---

## 4. Pool Modes Explained

PgBouncer supports three pool modes. Choose based on your application needs:

### Session Pooling (pool_mode = session)

**How it works:**
- Connection assigned to client when they connect
- Released when client disconnects
- Most compatible (supports all PostgreSQL features)

**Pros:**
- ✅ Fully compatible with prepared statements
- ✅ Supports session-level settings
- ✅ Works with LISTEN/NOTIFY

**Cons:**
- ❌ Less efficient (client holds connection entire session)
- ❌ Connection not released between requests

**Use case:** Complex applications with session state

### Transaction Pooling (pool_mode = transaction) ⭐ **RECOMMENDED**

**How it works:**
- Connection assigned for duration of transaction
- Released after COMMIT or ROLLBACK
- Next transaction can get different connection

**Pros:**
- ✅ Efficient (40-100x client-to-server ratio)
- ✅ Works well with Symfony/Doctrine
- ✅ Compatible with most ORMs

**Cons:**
- ❌ No prepared statements across transactions
- ❌ No session-level settings (SET, temp tables)
- ❌ No LISTEN/NOTIFY

**Use case:** Web applications, REST APIs, most Symfony apps ✅

### Statement Pooling (pool_mode = statement)

**How it works:**
- Connection released after each SQL statement
- Most aggressive pooling

**Pros:**
- ✅ Maximum efficiency

**Cons:**
- ❌ No transactions spanning multiple statements
- ❌ Very restrictive
- ❌ Rarely usable

**Use case:** Specialized read-only queries

**Recommendation for Symfony:** Use **transaction** mode ✅

---

## 5. Symfony Integration

### Update DATABASE_URL

Change your Symfony configuration to use PgBouncer:

**Before (direct PostgreSQL):**
```bash
DATABASE_URL="postgresql://deschide_admin:password@127.0.0.1:5432/deschide_news?serverVersion=17&charset=utf8"
```

**After (via PgBouncer):**
```bash
DATABASE_URL="postgresql://deschide_admin:password@127.0.0.1:6432/deschide_news?serverVersion=17&charset=utf8"
```

**Changes:**
- Port: `5432` → `6432` (PgBouncer port)

### Update .env.local

```bash
# Edit .env.local
nano /var/www/deschide_news_app/deschide_backend/.env.local

# Change port from 5432 to 6432
# Before:
# DATABASE_URL="postgresql://deschide_admin:your_password@127.0.0.1:5432/deschide_news?serverVersion=17&charset=utf8"

# After:
DATABASE_URL="postgresql://deschide_admin:your_password@127.0.0.1:6432/deschide_news?serverVersion=17&charset=utf8"
```

### Doctrine Configuration (Optional Adjustments)

For transaction pooling, ensure Doctrine doesn't use persistent connections:

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        # Don't use persistent connections with PgBouncer
        options:
            !php/const PDO::ATTR_PERSISTENT: false
```

### Clear Symfony Cache

```bash
cd /var/www/deschide_news_app/deschide_backend
symfony console cache:clear
```

---

## 6. Start PgBouncer

### Enable and Start Service

```bash
# Enable PgBouncer to start on boot
sudo systemctl enable pgbouncer

# Start PgBouncer
sudo systemctl start pgbouncer

# Check status
sudo systemctl status pgbouncer

# Should show: active (running)
```

### Verify PgBouncer is Running

```bash
# Check if listening on port 6432
sudo ss -tulpn | grep 6432

# Should show:
# tcp   LISTEN 0   128   127.0.0.1:6432   0.0.0.0:*   users:(("pgbouncer",pid=12345,fd=6))
```

### View Logs

```bash
# Follow logs in real-time
sudo tail -f /var/log/postgresql/pgbouncer.log

# Or use journalctl
sudo journalctl -u pgbouncer -f
```

---

## 7. Testing

### Test 1: Direct Connection to PgBouncer

```bash
# Connect to PgBouncer admin console
psql -h 127.0.0.1 -p 6432 -U postgres -d pgbouncer

# Run commands:
pgbouncer=# SHOW POOLS;
pgbouncer=# SHOW STATS;
pgbouncer=# SHOW DATABASES;
pgbouncer=# \q
```

### Test 2: Application Database Connection

```bash
# Connect to application database via PgBouncer
psql -h 127.0.0.1 -p 6432 -U deschide_admin -d deschide_news

# Test query
SELECT COUNT(*) FROM articles;

# Exit
\q
```

### Test 3: Symfony Connection

```bash
# Test Doctrine connection
symfony console dbal:run-sql "SELECT 1"

# Should output: 1

# Run a migration (dry-run)
symfony console doctrine:migrations:migrate --dry-run

# Should connect successfully via PgBouncer
```

### Test 4: Performance Comparison

**Without PgBouncer (port 5432):**
```bash
time for i in {1..100}; do
    psql -h 127.0.0.1 -p 5432 -U deschide_admin -d deschide_news -c "SELECT 1" > /dev/null 2>&1
done

# Note the time (e.g., 8.5 seconds)
```

**With PgBouncer (port 6432):**
```bash
time for i in {1..100}; do
    psql -h 127.0.0.1 -p 6432 -U deschide_admin -d deschide_news -c "SELECT 1" > /dev/null 2>&1
done

# Note the time (e.g., 3.2 seconds)
# Should be 2-3x faster!
```

---

## 8. Monitoring

### Admin Console Commands

Connect to PgBouncer console:
```bash
psql -h 127.0.0.1 -p 6432 -U postgres -d pgbouncer
```

**Useful commands:**

```sql
-- Show all pools and their status
SHOW POOLS;

-- Columns explained:
-- database:  Database name
-- user:      Username
-- cl_active: Active client connections
-- cl_waiting: Clients waiting for a connection
-- sv_active: Active server connections
-- sv_idle:   Idle server connections
-- sv_used:   Server connections used recently
-- sv_tested: Server connections being tested
-- maxwait:   How long oldest client has been waiting (seconds)

-- Show statistics
SHOW STATS;

-- Show configuration
SHOW CONFIG;

-- Show databases
SHOW DATABASES;

-- Show client connections
SHOW CLIENTS;

-- Show server connections
SHOW SERVERS;

-- Reload configuration (without restarting)
RELOAD;

-- Pause all activity
PAUSE;

-- Resume activity
RESUME;

-- Close all connections and exit
SHUTDOWN;
```

### Key Metrics to Monitor

**1. Pool Efficiency**
```sql
SHOW POOLS;
```

**Good indicators:**
- `cl_active` > `sv_active`: Connection pooling working (more clients than servers)
- `sv_idle` > 0: Idle connections available
- `maxwait` = 0: No clients waiting

**Warning signs:**
- `maxwait` > 1: Clients waiting for connections (increase pool size)
- `sv_active` = `max_db_connections`: Pool exhausted (increase limits)

**2. Query Statistics**
```sql
SHOW STATS;
```

**Key columns:**
- `total_xact_count`: Total transactions processed
- `total_query_count`: Total queries processed
- `total_received`: Bytes received from clients
- `total_sent`: Bytes sent to clients
- `total_xact_time`: Total transaction time (microseconds)
- `avg_xact_time`: Average transaction time

**3. Connection Distribution**
```sql
SHOW CLIENTS;
SHOW SERVERS;
```

Check that connections are distributed properly.

### Automated Monitoring Script

Create `/usr/local/bin/pgbouncer-stats.sh`:

```bash
#!/bin/bash
# PgBouncer monitoring script

echo "=== PgBouncer Pool Status ==="
psql -h 127.0.0.1 -p 6432 -U postgres -d pgbouncer -c "SHOW POOLS" 2>/dev/null

echo ""
echo "=== Connection Summary ==="
psql -h 127.0.0.1 -p 6432 -U postgres -d pgbouncer -At -c "SHOW POOLS" 2>/dev/null | \
awk -F'|' '{clients+=$4; servers+=$6} END {print "Total clients: " clients "\nTotal servers: " servers "\nRatio: " clients/servers "x"}'

echo ""
echo "=== Recent Stats ==="
psql -h 127.0.0.1 -p 6432 -U postgres -d pgbouncer -c "SHOW STATS" 2>/dev/null
```

Run periodically:
```bash
chmod +x /usr/local/bin/pgbouncer-stats.sh
watch -n 5 /usr/local/bin/pgbouncer-stats.sh
```

---

## 9. Production Deployment

### Pre-Deployment Checklist

- [ ] PgBouncer installed and configured
- [ ] `pgbouncer.ini` optimized (transaction mode, proper pool sizes)
- [ ] `userlist.txt` created with correct credentials
- [ ] File permissions set correctly (640 for config, 600 for userlist)
- [ ] PgBouncer service enabled and running
- [ ] Symfony `.env.local` updated to use port 6432
- [ ] Doctrine tested with PgBouncer
- [ ] Monitoring setup
- [ ] Backup plan ready

### Deployment Steps

1. **Backup current configuration**
   ```bash
   cp .env.local .env.local.backup
   sudo cp /etc/pgbouncer/pgbouncer.ini /etc/pgbouncer/pgbouncer.ini.backup
   ```

2. **Install and configure PgBouncer** (follow steps above)

3. **Update Symfony configuration**
   ```bash
   # Change DATABASE_URL port from 5432 to 6432
   sed -i 's/:5432\//:6432\//g' .env.local
   ```

4. **Clear cache and restart services**
   ```bash
   symfony console cache:clear --env=prod
   sudo systemctl restart php8.4-fpm
   sudo systemctl restart pgbouncer
   ```

5. **Test application**
   ```bash
   curl http://127.0.0.1:8081/api/articles?itemsPerPage=5
   # Should work normally
   ```

6. **Monitor for 24 hours**
   - Watch `SHOW POOLS` for connection health
   - Check application logs for database errors
   - Monitor response times

### Rollback Plan

If issues occur:

```bash
# 1. Change Symfony back to direct PostgreSQL
sed -i 's/:6432\//:5432\//g' .env.local
symfony console cache:clear

# 2. Restart PHP-FPM
sudo systemctl restart php8.4-fpm

# 3. Stop PgBouncer (optional)
sudo systemctl stop pgbouncer
```

---

## 10. Troubleshooting

### Issue 1: PgBouncer Won't Start

**Symptoms:**
```bash
sudo systemctl status pgbouncer
# Status: failed
```

**Check logs:**
```bash
sudo journalctl -u pgbouncer -n 50
```

**Common causes:**

1. **Syntax error in pgbouncer.ini**
   ```bash
   # Test configuration
   pgbouncer -d /etc/pgbouncer/pgbouncer.ini
   ```

2. **Port 6432 already in use**
   ```bash
   sudo ss -tulpn | grep 6432
   # If something else is using it, change listen_port in pgbouncer.ini
   ```

3. **Incorrect file permissions**
   ```bash
   sudo chown postgres:postgres /etc/pgbouncer/*
   sudo chmod 640 /etc/pgbouncer/pgbouncer.ini
   sudo chmod 600 /etc/pgbouncer/userlist.txt
   ```

### Issue 2: Authentication Failed

**Symptoms:**
```bash
psql -h 127.0.0.1 -p 6432 -U deschide_admin -d deschide_news
# psql: error: connection to server at "127.0.0.1", port 6432 failed: FATAL:  no such user
```

**Solutions:**

1. **Check userlist.txt has correct users**
   ```bash
   sudo cat /etc/pgbouncer/userlist.txt
   # Should contain: "deschide_admin" "md5..."
   ```

2. **Regenerate MD5 hash**
   ```bash
   echo -n "your_password deschide_admin" | md5sum | awk '{print "md5"$1}'
   # Copy result to userlist.txt
   ```

3. **Reload PgBouncer**
   ```bash
   psql -h 127.0.0.1 -p 6432 -U postgres -d pgbouncer -c "RELOAD"
   # Or restart service
   sudo systemctl restart pgbouncer
   ```

### Issue 3: Symfony Can't Connect

**Symptoms:**
```bash
symfony console dbal:run-sql "SELECT 1"
# Error: could not connect to server: Connection refused
```

**Solutions:**

1. **Verify PgBouncer is running**
   ```bash
   sudo systemctl status pgbouncer
   sudo ss -tulpn | grep 6432
   ```

2. **Test direct psql connection**
   ```bash
   psql -h 127.0.0.1 -p 6432 -U deschide_admin -d deschide_news -c "SELECT 1"
   # If this works, issue is with Symfony config
   ```

3. **Check DATABASE_URL format**
   ```bash
   grep DATABASE_URL .env.local
   # Should be: postgresql://user:pass@127.0.0.1:6432/dbname
   ```

4. **Clear Symfony cache**
   ```bash
   symfony console cache:clear
   ```

### Issue 4: Prepared Statement Error

**Symptoms:**
```
ERROR: prepared statement "pdo_stmt_..." does not exist
```

**Cause:** Transaction pooling doesn't support prepared statements across transactions

**Solution:** Disable prepared statements in Doctrine:

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        options:
            !php/const PDO::ATTR_EMULATE_PREPARES: true
```

Or use session pooling (less efficient but fully compatible):
```ini
# /etc/pgbouncer/pgbouncer.ini
pool_mode = session
```

### Issue 5: Connection Pool Exhausted

**Symptoms:**
- Clients waiting (`maxwait` > 0 in `SHOW POOLS`)
- Application timeouts

**Solutions:**

1. **Increase pool size**
   ```ini
   # /etc/pgbouncer/pgbouncer.ini
   default_pool_size = 50    # Was 25
   reserve_pool_size = 25    # Was 15
   ```

2. **Increase PostgreSQL max_connections**
   ```sql
   -- Check current limit
   SHOW max_connections;

   -- Increase if needed
   ALTER SYSTEM SET max_connections = 200;
   SELECT pg_reload_conf();
   ```

3. **Check for connection leaks**
   ```sql
   -- View active connections
   SELECT datname, usename, COUNT(*)
   FROM pg_stat_activity
   GROUP BY datname, usename;

   -- Kill idle connections (careful!)
   SELECT pg_terminate_backend(pid)
   FROM pg_stat_activity
   WHERE state = 'idle' AND state_change < now() - interval '10 minutes';
   ```

---

## Performance Benchmarks

### Expected Improvements

**Connection Overhead:**
- Direct PostgreSQL: 20-50ms per connection
- Via PgBouncer: 1-3ms per connection
- **Improvement: 90-95% reduction**

**Concurrent Connections:**
- Direct PostgreSQL: Max 100 connections (typical limit)
- Via PgBouncer: 1000+ clients → 25 database connections
- **Improvement: 40x capacity with same database resources**

**Query Response Time:**
- Impact depends on query complexity
- Simple queries: 5-10ms improvement
- Complex queries: 10-20ms improvement
- **Average: 15ms improvement per request**

### Before/After Comparison

**Scenario: 1000 concurrent API requests**

**Without PgBouncer:**
```
1000 requests × 50ms connection overhead = 50 seconds total overhead
Database: 1000 connections (likely fails if max_connections = 100)
Result: Connection errors, failed requests
```

**With PgBouncer:**
```
1000 requests × 2ms connection overhead = 2 seconds total overhead
Database: 25 pooled connections (well within limits)
Result: All requests succeed, 96% faster
```

---

## Summary

| Metric | Before | After PgBouncer | Improvement |
|--------|--------|-----------------|-------------|
| **Connection time** | 20-50ms | 1-3ms | **95%** |
| **Max concurrent clients** | 100 | 1000+ | **10x** |
| **Database connections** | 100 | 25 | **75% reduction** |
| **Query latency** | Baseline | -15ms | **Faster** |
| **Connection errors** | Frequent | Rare | **Better** |

**Cost:** $0 (open source)

**Complexity:** Low (single config file)

**ROI:** ⭐⭐⭐⭐ (Very High)

---

## Additional Resources

- Official Documentation: https://www.pgbouncer.org/
- Config Reference: https://www.pgbouncer.org/config.html
- FAQ: https://www.pgbouncer.org/faq.html
- GitHub: https://github.com/pgbouncer/pgbouncer

---

**Last Updated**: 4 Noiembrie 2025
**Status**: Ready for implementation
**Recommended Pool Mode**: Transaction (for Symfony/Doctrine)
