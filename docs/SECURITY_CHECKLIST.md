# Security Checklist - Deschide News

**Version:** 1.0
**Last Updated:** 2025-11-29
**Remediation Plan:** Complete (Sprints 1-4)

---

## Pre-Deployment Checklist

### Credentials & Secrets

- [x] APP_SECRET regenerated and unique for each environment
- [x] DATABASE_URL does not contain passwords in git (uses .env.local)
- [x] JWT_PASSPHRASE regenerated
- [x] MERCURE_JWT_SECRET regenerated
- [x] .env.local is NOT in git (.gitignore verified)
- [x] .env contains only placeholders/templates

**Verification Commands:**
```bash
# Check .env.local is not tracked
git ls-files | grep -E "\.env\.local$"
# Should return nothing

# Verify secrets are not in git history
git log --all --full-history -- "*.env.local"
# Should return nothing
```

### File Permissions

- [x] `config/jwt/private.pem`: 600 (owner read/write only)
- [x] `config/jwt/public.pem`: 644 (owner read/write, others read)
- [x] `.env.local`: 600 (owner read/write only)
- [x] `var/`: writable only by web server

**Verification Commands:**
```bash
cd /var/www/deschide_news_app/apps/backend

# Check JWT key permissions
stat -c "%a %n" config/jwt/*.pem
# Expected: 600 config/jwt/private.pem
#           644 config/jwt/public.pem

# Check .env.local permissions
stat -c "%a %n" .env.local
# Expected: 600 .env.local
```

### PostgreSQL Security

- [x] Application uses role without superuser privileges
- [x] archive_mode configured (WAL archiving for backups)
- [x] Automated backups configured (daily/weekly/monthly)
- [x] pg_stat_statements documented (requires superuser to install)
- [x] Autovacuum optimized for high-traffic tables
- [x] GDPR data retention implemented (IP anonymization, 90-day cleanup)

**Verification Commands:**
```bash
# Check user privileges
PGPASSWORD='...' psql -h 127.0.0.1 -U deschide_admin -d deschide \
  -c "SELECT rolsuper FROM pg_roles WHERE rolname = 'deschide_admin';"
# Expected: f (false)

# Check autovacuum settings
PGPASSWORD='...' psql -h 127.0.0.1 -U deschide_admin -d deschide \
  -c "SELECT relname, reloptions FROM pg_class WHERE relname = 'page_views';"
```

### Application Security

- [x] APP_ENV=prod
- [x] APP_DEBUG=0
- [x] Rate limiting active (100/min general, 5/min login, 30/min write)
- [x] CSP headers configured (backend SecurityHeadersSubscriber)
- [x] CORS restricted (no wildcard origins)
- [x] XSS sanitization active (DOMPurify in frontend)

**Verification Commands:**
```bash
# Check environment
grep "APP_ENV=prod" .env.local
grep "APP_DEBUG=0" .env.local

# Test rate limiting (should get 429 after ~100 requests)
for i in {1..110}; do
  curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8081/api/articles
done | grep 429

# Check CSP headers
curl -I http://127.0.0.1:8081/api | grep -i "content-security-policy"
```

### Frontend Security

- [x] React strict mode: true
- [x] DOMPurify for unsafe HTML (isomorphic-dompurify)
- [x] Security headers in next.config.mjs
- [x] No dangerouslySetInnerHTML without sanitization

**Verification Commands:**
```bash
# Check strict mode
grep "reactStrictMode" /var/www/deschide_news_app/apps/frontend/next.config.mjs

# Check DOMPurify installed
grep "dompurify" /var/www/deschide_news_app/apps/frontend/package.json
```

---

## Post-Deployment Verification

### API Security Check

```bash
# Verify security headers
curl -I http://127.0.0.1:8081/api | grep -E "(X-Frame|Content-Security|X-Content-Type)"

# Expected output:
# Content-Security-Policy: default-src 'self'; script-src 'self'; ...
# X-Content-Type-Options: nosniff
# X-Frame-Options: DENY
```

### Rate Limiting Test

```bash
# Test general rate limit (100 req/min)
for i in {1..110}; do
  curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8081/api/articles
done | sort | uniq -c

# Expected: ~100 200s, ~10 429s
```

### Backup Verification

```bash
# Check backup directory structure
ls -la /var/backups/postgresql/deschide/daily/
ls -la /var/backups/postgresql/deschide/weekly/
ls -la /var/backups/postgresql/deschide/monthly/

# Test backup script
/var/www/deschide_news_app/scripts/backup-db.sh
```

### PostgreSQL Health Check

```bash
PGPASSWORD='...' psql -h 127.0.0.1 -U deschide_admin -d deschide -c "
SELECT
    current_setting('shared_buffers') as shared_buffers,
    current_setting('effective_cache_size') as effective_cache_size,
    current_setting('work_mem') as work_mem,
    current_setting('random_page_cost') as random_page_cost;
"
```

---

## Monthly Security Tasks

### Week 1
- [ ] Review slow_queries (if pg_stat_statements enabled)
- [ ] Check backup integrity (test restore on staging)
- [ ] Review PostgreSQL logs for suspicious queries

### Week 2
- [ ] Update backend dependencies: `composer update`
- [ ] Update frontend dependencies: `pnpm update`
- [ ] Run security audits: `composer audit`, `pnpm audit`

### Week 3
- [ ] Check autovacuum effectiveness
- [ ] Review dead tuple percentages
- [ ] VACUUM ANALYZE high-traffic tables if needed

### Week 4
- [ ] Rotate APP_SECRET (if policy requires)
- [ ] Review access logs for anomalies
- [ ] Update security checklist if needed

---

## Quarterly Security Tasks

- [ ] Full penetration testing (internal or external)
- [ ] Review and update CORS whitelist
- [ ] Audit JWT token expiration policies
- [ ] Review rate limiting thresholds
- [ ] Update SSL/TLS certificates (if applicable)
- [ ] Review and prune user accounts
- [ ] Update pg_stat_statements analysis

---

## Incident Response Procedures

### 1. Credentials Exposed

**Immediate Actions (within 1 hour):**

```bash
# 1. Regenerate all exposed secrets
cd /var/www/deschide_news_app/apps/backend

# Generate new APP_SECRET
openssl rand -hex 32

# Generate new JWT keys
php bin/console lexik:jwt:generate-keypair --overwrite

# Generate new database password (requires PostgreSQL access)
openssl rand -base64 24

# 2. Update .env.local with new values
nano .env.local

# 3. Clear all caches and tokens
php bin/console cache:clear
redis-cli -n 1 FLUSHDB

# 4. Invalidate all refresh tokens
php bin/console doctrine:query:sql "DELETE FROM refresh_tokens;"

# 5. Restart services
sudo systemctl restart php8.4-fpm
```

**Post-Incident (within 24 hours):**
- Check git history for exposure duration
- Review access logs for unauthorized access
- Notify affected users if data compromised
- Document incident and remediation

### 2. Data Breach

**Immediate Actions:**
1. Contain the breach (block attacker IPs, disable compromised accounts)
2. Preserve evidence (logs, database snapshots)
3. Assess scope (what data was accessed/exfiltrated)

**GDPR Compliance (within 72 hours):**
- Notify Data Protection Officer (DPO)
- Prepare breach notification for authorities
- Document affected data types and users
- Implement additional monitoring

**Post-Incident:**
- Forensic analysis
- User notification (if required)
- Security hardening
- Lessons learned documentation

### 3. DDoS Attack

**Immediate Actions:**

```bash
# 1. Enable strict rate limiting
# Edit config/packages/rate_limiter.yaml
# Reduce limits temporarily (e.g., 10/min instead of 100/min)

# 2. Block attacking IPs at firewall level
sudo iptables -A INPUT -s ATTACKER_IP -j DROP

# Or use fail2ban
sudo fail2ban-client set api-abuse banip ATTACKER_IP

# 3. Enable Cloudflare protection (if available)
# Switch to "Under Attack" mode in Cloudflare dashboard
```

**Mitigation:**
- Contact hosting provider for upstream filtering
- Consider CDN/WAF services (Cloudflare, AWS Shield)
- Review and optimize rate limiting rules
- Implement IP reputation-based blocking

---

## Security Configuration Reference

### Rate Limiting (config/packages/rate_limiter.yaml)

| Limiter | Limit | Window | Protected Endpoints |
|---------|-------|--------|---------------------|
| `api_general` | 100 | 1 minute | All API endpoints |
| `api_login` | 5 | 1 minute | /api/login, /api/token/refresh |
| `api_write` | 30 | 1 minute | POST, PUT, PATCH, DELETE |

### Security Headers (SecurityHeadersSubscriber.php)

| Header | Value | Purpose |
|--------|-------|---------|
| Content-Security-Policy | default-src 'self'; ... | Prevent XSS, clickjacking |
| X-Content-Type-Options | nosniff | Prevent MIME sniffing |
| X-Frame-Options | DENY | Prevent clickjacking |
| X-XSS-Protection | 1; mode=block | Browser XSS filter |
| Referrer-Policy | strict-origin-when-cross-origin | Control referrer info |
| Permissions-Policy | geolocation=(), ... | Disable browser features |

### CORS Configuration (config/packages/nelmio_cors.yaml)

- Allowed origins: `localhost`, `127.0.0.1` (development)
- Allowed methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
- Allowed headers: Content-Type, Authorization, Accept-Language
- Credentials: true (for JWT cookies if used)

---

## Compliance Summary

### GDPR Compliance

- [x] **Data Minimization:** IP addresses anonymized after 7 days
- [x] **Storage Limitation:** Page views deleted after 90 days
- [x] **Purpose Limitation:** Data collected only for analytics
- [x] **Automated Cleanup:** `app:cleanup-page-views` command

### Security Standards

- [x] **OWASP Top 10:** XSS, CSRF, injection protections implemented
- [x] **Authentication:** JWT with secure key management
- [x] **Authorization:** Role-based access control
- [x] **Encryption:** HTTPS required for production
- [x] **Logging:** Security events logged (rate limiting, errors)

---

## Emergency Contacts

| Role | Contact | Responsibility |
|------|---------|----------------|
| DevOps Lead | [TBD] | Server access, infrastructure |
| Security Lead | [TBD] | Incident response, compliance |
| DPO | [TBD] | GDPR breach notification |
| Hosting Provider | [TBD] | DDoS mitigation, infrastructure |

---

**Document Owner:** Development Team
**Review Schedule:** Quarterly
**Next Review:** 2026-02-28
