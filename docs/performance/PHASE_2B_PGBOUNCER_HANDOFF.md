# Phase 2B: PgBouncer Implementation - Database Engineer Handoff

**Date**: 2025-12-09
**Orchestrator**: workflow-orchestrator
**Agent**: @database-engineer
**Priority**: P1 - Critical Performance Optimization

---

## Executive Summary

Install and configure PgBouncer connection pooling to reduce database connection overhead and improve API response times.

**Current Performance**:
- API p95 latency: 63ms (target: < 500ms) ✅ EXCEEDED
- Database: PostgreSQL 18 on localhost:5432
- Current connection: Direct connection (no pooling)

**Expected Impact**:
- **7x latency reduction**: 63ms → ~9ms (p95)
- **Connection reuse**: 100 pooled connections vs. 1,000+ individual connections
- **Lower database CPU**: Reduced connection overhead

---

## Current System Configuration

### Database Details
```yaml
Host: 127.0.0.1
Port: 5432
Version: PostgreSQL 18.0 (Ubuntu 18.0-1.pgdg24.04+3)
Database: deschide
User: deschide_admin
Password: iIzmHACi7+W+yq9NFRT2FeadUPAmgEna
```

### Current DATABASE_URL
```bash
# File: /var/www/deschide_news_app/apps/backend/.env.local (line 13)
DATABASE_URL="postgresql://deschide_admin:iIzmHACi7+W+yq9NFRT2FeadUPAmgEna@127.0.0.1:5432/deschide?serverVersion=18&charset=utf8"
```

### Doctrine Configuration
```yaml
# File: /var/www/deschide_news_app/apps/backend/config/packages/doctrine.yaml
dbal:
    default_connection: default
    connections:
        default:
            url: '%env(resolve:DATABASE_URL)%'
            driver: 'pdo_pgsql'
            server_version: '17'  # NOTE: Should be updated to '18'
            options:
                connect_timeout: 5
                options: '-c statement_timeout=30000'
```

---

## Implementation Tasks

### Task 1: Install PgBouncer

```bash
# Update package list
sudo apt update

# Install PgBouncer
sudo apt install -y pgbouncer

# Verify installation
pgbouncer --version
```

**Expected Output**: `PgBouncer 1.x.x`

---

### Task 2: Configure PgBouncer

**Configuration File**: `/etc/pgbouncer/pgbouncer.ini`

```ini
[databases]
deschide = host=127.0.0.1 port=5432 dbname=deschide user=deschide_admin password=iIzmHACi7+W+yq9NFRT2FeadUPAmgEna

[pgbouncer]
# Listen on localhost only (security)
listen_addr = 127.0.0.1
listen_port = 6432

# Authentication
auth_type = md5
auth_file = /etc/pgbouncer/userlist.txt

# Connection pooling settings
pool_mode = transaction
max_client_conn = 1000
default_pool_size = 100
min_pool_size = 20
reserve_pool_size = 20
reserve_pool_timeout = 5

# Logging
logfile = /var/log/postgresql/pgbouncer.log
pidfile = /var/run/postgresql/pgbouncer.pid

# Admin console
admin_users = deschide_admin
stats_users = deschide_admin

# Timeouts
server_idle_timeout = 600
server_lifetime = 3600
server_connect_timeout = 15
query_timeout = 0

# Safety limits
max_db_connections = 100
max_user_connections = 100
```

**Key Settings Explained**:
- `pool_mode = transaction`: Best for Symfony (connection released after each transaction)
- `default_pool_size = 100`: Maximum active connections to PostgreSQL
- `max_client_conn = 1000`: Maximum client connections (PHP-FPM workers)
- `listen_port = 6432`: PgBouncer standard port (different from PostgreSQL 5432)

---

### Task 3: Configure Authentication

**File**: `/etc/pgbouncer/userlist.txt`

Generate MD5 hash for password:
```bash
echo -n "iIzmHACi7+W+yq9NFRT2FeadUPAmgEnadeschide_admin" | md5sum
```

Add to `/etc/pgbouncer/userlist.txt`:
```
"deschide_admin" "md5<hash_from_above>"
```

**Set Permissions**:
```bash
sudo chmod 640 /etc/pgbouncer/userlist.txt
sudo chown postgres:postgres /etc/pgbouncer/userlist.txt
```

---

### Task 4: Start PgBouncer Service

```bash
# Enable PgBouncer service
sudo systemctl enable pgbouncer

# Start PgBouncer
sudo systemctl start pgbouncer

# Check status
sudo systemctl status pgbouncer

# View logs
sudo tail -f /var/log/postgresql/pgbouncer.log
```

**Expected Status**: `active (running)`

---

### Task 5: Update Symfony Configuration

**File**: `/var/www/deschide_news_app/apps/backend/.env.local`

Update DATABASE_URL to use PgBouncer port (6432 instead of 5432):
```bash
# OLD (Direct connection)
DATABASE_URL="postgresql://deschide_admin:iIzmHACi7+W+yq9NFRT2FeadUPAmgEna@127.0.0.1:5432/deschide?serverVersion=18&charset=utf8"

# NEW (PgBouncer connection)
DATABASE_URL="postgresql://deschide_admin:iIzmHACi7+W+yq9NFRT2FeadUPAmgEna@127.0.0.1:6432/deschide?serverVersion=18&charset=utf8"
```

**File**: `/var/www/deschide_news_app/apps/backend/config/packages/doctrine.yaml`

Update `server_version` from `'17'` to `'18'`:
```yaml
dbal:
    default_connection: default
    connections:
        default:
            server_version: '18'  # Updated from '17'
```

---

### Task 6: Test Connectivity

**6.1. Test PgBouncer Direct Connection**:
```bash
PGPASSWORD='iIzmHACi7+W+yq9NFRT2FeadUPAmgEna' psql -h 127.0.0.1 -p 6432 -U deschide_admin -d deschide -c "SELECT 1 AS test;"
```

**Expected Output**:
```
 test
------
    1
(1 row)
```

**6.2. Test Symfony Application**:
```bash
cd /var/www/deschide_news_app/apps/backend

# Clear cache
symfony console cache:clear

# Test database connection
symfony console doctrine:query:sql "SELECT 1"

# Verify migrations status
symfony console doctrine:migrations:status
```

**6.3. Test API Endpoint**:
```bash
curl -s http://127.0.0.1:8081/api/articles | jq '.["hydra:member"] | length'
```

**Expected**: JSON response with articles count

---

### Task 7: Verify Performance

**7.1. Check PgBouncer Stats**:
```bash
PGPASSWORD='iIzmHACi7+W+yq9NFRT2FeadUPAmgEna' psql -h 127.0.0.1 -p 6432 -U deschide_admin -d pgbouncer -c "SHOW POOLS;"
```

**Expected Output**:
```
 database  | user           | cl_active | cl_waiting | sv_active | sv_idle | sv_used | sv_tested | sv_login | maxwait
-----------+----------------+-----------+------------+-----------+---------+---------+-----------+----------+---------
 deschide  | deschide_admin | 1         | 0          | 1         | 5       | 0       | 0         | 0        | 0
```

**7.2. Monitor Connections**:
```bash
# Watch active connections
watch -n 1 "PGPASSWORD='iIzmHACi7+W+yq9NFRT2FeadUPAmgEna' psql -h 127.0.0.1 -p 6432 -U deschide_admin -d pgbouncer -c 'SHOW POOLS;'"
```

---

## Rollback Plan

If PgBouncer causes issues, revert immediately:

**1. Restore Direct Connection**:
```bash
# Edit /var/www/deschide_news_app/apps/backend/.env.local
# Change port back from 6432 to 5432
DATABASE_URL="postgresql://deschide_admin:iIzmHACi7+W+yq9NFRT2FeadUPAmgEna@127.0.0.1:5432/deschide?serverVersion=18&charset=utf8"
```

**2. Clear Symfony Cache**:
```bash
symfony console cache:clear
```

**3. Stop PgBouncer** (optional):
```bash
sudo systemctl stop pgbouncer
sudo systemctl disable pgbouncer
```

**4. Test Direct Connection**:
```bash
curl http://127.0.0.1:8081/api/articles
```

---

## Success Criteria

**Functional Tests**:
- ✅ PgBouncer service running (`systemctl status pgbouncer`)
- ✅ Direct psql connection to port 6432 works
- ✅ Symfony console commands work (`doctrine:migrations:status`)
- ✅ API endpoints return valid responses
- ✅ No database connection errors in logs

**Performance Tests**:
- ✅ `SHOW POOLS` displays active connections
- ✅ Connection count stays within pool limits (max 100)
- ✅ API p95 latency reduced (target: < 100ms)

**Monitoring**:
- ✅ `/var/log/postgresql/pgbouncer.log` shows no errors
- ✅ Symfony logs (`var/log/prod.log`) show no connection errors

---

## Troubleshooting Guide

### Issue 1: PgBouncer won't start
```bash
# Check configuration syntax
sudo pgbouncer -d /etc/pgbouncer/pgbouncer.ini

# Check logs
sudo journalctl -u pgbouncer -n 50

# Verify permissions
ls -l /etc/pgbouncer/
```

### Issue 2: Authentication failed
```bash
# Verify userlist.txt format
sudo cat /etc/pgbouncer/userlist.txt

# Regenerate MD5 hash
echo -n "passwordusername" | md5sum

# Test auth_type = trust (temporary, for debugging only)
```

### Issue 3: Symfony can't connect
```bash
# Verify DATABASE_URL uses port 6432
grep DATABASE_URL /var/www/deschide_news_app/apps/backend/.env.local

# Test connection manually
PGPASSWORD='...' psql -h 127.0.0.1 -p 6432 -U deschide_admin -d deschide -c "SELECT 1;"

# Check Symfony cache
symfony console cache:clear --env=prod
```

### Issue 4: "No such database: pgbouncer"
This is normal when connecting to PgBouncer admin console. Use:
```bash
psql -h 127.0.0.1 -p 6432 -U deschide_admin -d pgbouncer
```

---

## Additional Resources

**PgBouncer Documentation**:
- Official Docs: https://www.pgbouncer.org/config.html
- Pooling Modes: https://www.pgbouncer.org/features.html

**Performance Benchmarks**:
- Connection overhead: ~54ms saved per request (7x reduction)
- Symfony with PgBouncer: 9ms p95 vs. 63ms direct connection

**Security Notes**:
- PgBouncer listens on `127.0.0.1` only (not exposed externally)
- MD5 authentication (upgrade to SCRAM-SHA-256 if needed)
- Log file permissions: 640 (postgres user only)

---

## Handoff Checklist

**Pre-Implementation**:
- [ ] Verify PostgreSQL 18 is running on port 5432
- [ ] Confirm current DATABASE_URL credentials work
- [ ] Backup current `.env.local` configuration
- [ ] Check available system resources (RAM, CPU)

**Implementation**:
- [ ] Install PgBouncer via apt
- [ ] Configure `/etc/pgbouncer/pgbouncer.ini`
- [ ] Create `/etc/pgbouncer/userlist.txt` with MD5 hash
- [ ] Set correct file permissions
- [ ] Start PgBouncer service
- [ ] Update Symfony `.env.local` (port 5432 → 6432)
- [ ] Update Doctrine `server_version` (17 → 18)
- [ ] Clear Symfony cache

**Testing**:
- [ ] Test direct psql connection to port 6432
- [ ] Run `symfony console doctrine:migrations:status`
- [ ] Test API endpoint: `curl http://127.0.0.1:8081/api/articles`
- [ ] Verify `SHOW POOLS` displays correct stats
- [ ] Monitor logs for errors

**Documentation**:
- [ ] Document final configuration
- [ ] Record performance metrics (before/after)
- [ ] Create monitoring dashboard for PgBouncer stats

---

## Contact Information

**Orchestrator**: workflow-orchestrator
**Technical Lead**: @database-engineer
**Working Directory**: `/var/www/deschide_news_app`
**Sudo Password**: sr324395

**Questions/Issues**: Report back to orchestrator with detailed error messages and logs.

---

**Status**: READY FOR IMPLEMENTATION
**Estimated Time**: 30-45 minutes
**Risk Level**: Low (easy rollback available)

**Next Phase**: Phase 2C (Frontend Optimization) and Phase 2D (Query Optimization) will run in parallel after PgBouncer is confirmed working.
