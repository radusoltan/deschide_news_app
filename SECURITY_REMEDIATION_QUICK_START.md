# Security Remediation Quick Start Guide

**Task 1.1 - Secret Rotation and .env Security**
**Date:** 2025-12-20

---

## Summary of Findings

**CRITICAL ISSUES:**
1. JWT private key is world-readable (644) - should be 600
2. JWT directory has 777 permissions - should be 700
3. MERCURE_JWT_SECRET is still default value `!ChangeThisMercureHubJWTSecretKey!`
4. Real database password in .env (untracked but risky)

**GOOD NEWS:**
- No real secrets exposed in git history (only placeholders)
- .env is properly gitignored
- No hardcoded passwords in code

---

## Quick Fix Commands (Copy-Paste Ready)

### Step 1: Fix JWT Permissions (NO DOWNTIME)

```bash
cd /var/www/deschide_news_app/apps/backend

# Fix permissions
chmod 700 config/jwt/
chmod 600 config/jwt/private.pem
chmod 644 config/jwt/public.pem

# Verify
ls -la config/jwt/
# Expected:
# drwx------ 2 radu radu 4096 ... .
# -rw------- 1 radu radu 1854 ... private.pem
# -rw-r--r-- 1 radu radu  451 ... public.pem
```

**Impact:** None
**Time:** 10 seconds

---

### Step 2: Rotate MERCURE_JWT_SECRET (5 SEC DOWNTIME)

**Edit `/var/www/deschide_news_app/apps/backend/.env`:**

Find this line:
```bash
MERCURE_JWT_SECRET=!ChangeThisMercureHubJWTSecretKey!
```

Replace with:
```bash
MERCURE_JWT_SECRET=CF7v+xCtdBHKT1tCiPErY5WrXWpmKavU9Ym7A79kfok=
```

**Restart Symfony:**
```bash
cd /var/www/deschide_news_app/apps/backend
symfony server:stop
symfony server:start -d --port=8081
symfony server:status
```

**Impact:** Active Mercure connections dropped (auto-reconnect)
**Time:** 30 seconds

---

### Step 3: Rotate APP_SECRET (USER RE-LOGIN REQUIRED)

**WARNING:** This invalidates all sessions. Coordinate with team.

**Edit `/var/www/deschide_news_app/apps/backend/.env`:**

Find this line:
```bash
APP_SECRET=Kc2qO6piNIVfiHN8koLrX7TiTfo+xudoXsS+xF3nwc4=
```

Replace with:
```bash
APP_SECRET=cbc126b27191be2bac99fdfa7d117c28ab6d315cad11a337226c252ee4a64b7c
```

**Restart and clear cache:**
```bash
cd /var/www/deschide_news_app/apps/backend
symfony server:stop
symfony console cache:clear
symfony server:start -d --port=8081
```

**Impact:** All users must re-login
**Time:** 1 minute

---

### Step 4: Regenerate JWT Keys (ALL TOKENS INVALIDATED)

**WARNING:** This invalidates ALL JWT tokens. Coordinate with team.

```bash
cd /var/www/deschide_news_app/apps/backend

# Backup old keys
mv config/jwt/private.pem config/jwt/private.pem.old.$(date +%Y%m%d)
mv config/jwt/public.pem config/jwt/public.pem.old.$(date +%Y%m%d)
```

**Edit `/var/www/deschide_news_app/apps/backend/.env`:**

Find this line:
```bash
JWT_PASSPHRASE=KMFTYGh5Dye6MwzpCejeZ3oOTodwtzbhhLvF+I3WjD8=
```

Replace with:
```bash
JWT_PASSPHRASE=ai3qA+cdwYuk7M6DSWBlV0phVEtzKzuBzjwoHOmgKlE=
```

**Generate new keys:**
```bash
# Generate new keypair
symfony console lexik:jwt:generate-keypair

# Fix permissions immediately
chmod 700 config/jwt/
chmod 600 config/jwt/private.pem
chmod 644 config/jwt/public.pem

# Clear refresh tokens from database (optional but recommended)
symfony console doctrine:query:sql "TRUNCATE TABLE refresh_tokens"

# Restart
symfony server:stop
symfony console cache:clear
symfony server:start -d --port=8081
```

**Impact:** All users must re-login (all tokens invalidated)
**Time:** 2 minutes

---

### Step 5: Migrate to .env.local Pattern (RECOMMENDED)

**Create `.env.local` with real secrets:**

```bash
cd /var/www/deschide_news_app/apps/backend

# Create .env.local (already gitignored)
nano .env.local
```

**Add these lines to `.env.local`:**
```bash
# Real secrets (NOT committed to git)
APP_SECRET=cbc126b27191be2bac99fdfa7d117c28ab6d315cad11a337226c252ee4a64b7c
DATABASE_URL="postgresql://deschide_admin:sr324395@127.0.0.1:5432/deschide?serverVersion=18&charset=utf8"
NEWSCOOP_DATABASE_URL="mysql://root:sr324395@127.0.0.1:3306/newscoop?serverVersion=10.5&charset=utf8mb4"
JWT_PASSPHRASE=ai3qA+cdwYuk7M6DSWBlV0phVEtzKzuBzjwoHOmgKlE=
MERCURE_JWT_SECRET=CF7v+xCtdBHKT1tCiPErY5WrXWpmKavU9Ym7A79kfok=
ELASTICSEARCH_PASSWORD=your_actual_elasticsearch_password
```

**Set secure permissions:**
```bash
chmod 600 .env.local
ls -la .env.local
# Expected: -rw------- 1 radu radu ... .env.local
```

**Update `.env` with placeholders:**
```bash
nano .env
```

Replace these lines:
```bash
APP_SECRET=CHANGE_IN_ENV_LOCAL
DATABASE_URL="postgresql://deschide_admin:CHANGE_IN_ENV_LOCAL@127.0.0.1:5432/deschide?serverVersion=18&charset=utf8"
NEWSCOOP_DATABASE_URL="mysql://root:CHANGE_IN_ENV_LOCAL@127.0.0.1:3306/newscoop?serverVersion=10.5&charset=utf8mb4"
JWT_PASSPHRASE=CHANGE_IN_ENV_LOCAL
MERCURE_JWT_SECRET=CHANGE_IN_ENV_LOCAL
```

**Verify .env.local is not tracked:**
```bash
git status | grep .env.local
# Should return nothing

git ls-files .env.local
# Should return nothing
```

**Impact:** None (Symfony prioritizes .env.local over .env)
**Time:** 5 minutes

---

## Verification Checklist

After completing steps above, verify:

```bash
cd /var/www/deschide_news_app/apps/backend

# Check JWT permissions
ls -la config/jwt/
# Expected:
# drwx------ ... .
# -rw------- ... private.pem
# -rw-r--r-- ... public.pem

# Check .env.local permissions (if created)
ls -la .env.local
# Expected: -rw------- ... .env.local

# Verify .env is not tracked in git
git ls-files .env
# Expected: (no output)

# Verify .env.local is not tracked in git
git ls-files .env.local
# Expected: (no output)

# Test application
curl http://127.0.0.1:8081/api
# Expected: 200 OK with JSON response

# Test database connection
symfony console doctrine:query:sql "SELECT COUNT(*) FROM articles"
# Expected: Returns article count

# Check Symfony server status
symfony server:status
# Expected: Running on 127.0.0.1:8081
```

---

## Rollback Procedure (If Issues Occur)

If something breaks after rotation:

```bash
cd /var/www/deschide_news_app/apps/backend

# Restore from backup
cp .env.backup.20251220_134000 .env

# Restore old JWT keys (if regenerated)
mv config/jwt/private.pem.old.20251220 config/jwt/private.pem
mv config/jwt/public.pem.old.20251220 config/jwt/public.pem

# Restart
symfony server:stop
symfony console cache:clear
symfony server:start -d --port=8081
```

---

## Security Checklist Going Forward

- [ ] JWT permissions fixed (Step 1) - DONE
- [ ] MERCURE_JWT_SECRET rotated (Step 2) - DONE
- [ ] APP_SECRET rotated (Step 3) - SCHEDULED
- [ ] JWT keys regenerated (Step 4) - SCHEDULED
- [ ] .env.local pattern implemented (Step 5) - SCHEDULED
- [ ] Database passwords rotated - REQUIRES DBA
- [ ] Pre-commit hook installed - NEXT SPRINT
- [ ] Secret rotation policy documented - NEXT SPRINT
- [ ] Calendar reminders set for quarterly rotation - NEXT SPRINT

---

## Support

**Full Report:** `/var/www/deschide_news_app/SECURITY_ASSESSMENT_TASK_1_1.md`

**Questions:**
- Check REMEDIATION_PLAN.md for full context
- Review Symfony docs: https://symfony.com/doc/current/configuration.html
- Contact DBA for database password rotation

---

**Last Updated:** 2025-12-20 13:40 UTC
