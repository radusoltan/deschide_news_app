# Security Environment Variables Documentation

**Document Version:** 1.0
**Created:** 2025-12-17
**Related:** NEXTJS_ALIGNMENT_PLAN.md - Phase 1 Security

---

## Overview

This document describes all security-related environment variables required for the Deschide News frontend application. These variables must be properly configured for production deployment.

---

## Required Variables

### SESSION_SECRET

**Purpose:** Encrypts session data stored in HTTP-only cookies using JWT.

**Location Used:**
- `lib/auth/session.ts:16`

**Requirements:**
- Minimum 32 characters
- Use cryptographically secure random string
- Never commit to version control
- Rotate periodically

**Example:**
```bash
# Generate a secure secret
openssl rand -base64 32

# .env.local
SESSION_SECRET=your-32-character-or-longer-random-string
```

**Security Impact:** If compromised, attackers can forge sessions and impersonate users.

---

### REVALIDATE_SECRET

**Purpose:** Authenticates cache invalidation requests from the Symfony backend.

**Location Used:**
- `app/api/revalidate/route.ts`

**Requirements:**
- Minimum 32 characters
- Must match the backend's `FRONTEND_REVALIDATE_SECRET`
- Use cryptographically secure random string

**Example:**
```bash
# Generate a secure secret
openssl rand -hex 32

# .env.local
REVALIDATE_SECRET=your-64-character-hex-string
```

**Security Impact:** If compromised, attackers can trigger cache invalidation, potentially causing DoS or stale content issues.

---

## Public Variables

These variables are exposed to the client and should NOT contain sensitive data.

### NEXT_PUBLIC_API_URL

**Purpose:** Backend API base URL for client-side requests.

**Example:**
```bash
# Development
NEXT_PUBLIC_API_URL=http://127.0.0.1:8081

# Production
NEXT_PUBLIC_API_URL=https://api.deschide.md
```

### NEXT_PUBLIC_CDN_URL

**Purpose:** CDN URL for serving images and static assets.

**Example:**
```bash
# Development
NEXT_PUBLIC_CDN_URL=http://127.0.0.1:8082

# Production
NEXT_PUBLIC_CDN_URL=https://cdn.deschide.md
```

### NEXT_PUBLIC_DEFAULT_LOCALE

**Purpose:** Default locale for the application.

**Example:**
```bash
NEXT_PUBLIC_DEFAULT_LOCALE=ro
```

### NEXT_PUBLIC_AVAILABLE_LOCALES

**Purpose:** Comma-separated list of available locales.

**Example:**
```bash
NEXT_PUBLIC_AVAILABLE_LOCALES=ro,en,ru
```

---

## Backend Integration Variables

These variables are required for proper backend integration.

### For Symfony Backend (.env)

The following variables must be configured in the Symfony backend to enable cache invalidation:

```bash
# URL of the Next.js revalidation endpoint
FRONTEND_REVALIDATE_URL=https://deschide.md/api/revalidate

# Secret for authenticating revalidation requests (must match REVALIDATE_SECRET)
FRONTEND_REVALIDATE_SECRET=your-64-character-hex-string
```

---

## Environment Files

### Development (.env.local)

```bash
# Session encryption (change this!)
SESSION_SECRET=development-secret-change-in-production-now

# Cache revalidation (optional for local dev)
REVALIDATE_SECRET=local-dev-revalidate-secret

# API URLs
NEXT_PUBLIC_API_URL=http://127.0.0.1:8081
NEXT_PUBLIC_CDN_URL=http://127.0.0.1:8082

# Localization
NEXT_PUBLIC_DEFAULT_LOCALE=ro
NEXT_PUBLIC_AVAILABLE_LOCALES=ro,en,ru
```

### Production (.env.production)

```bash
# Session encryption (USE STRONG SECRET!)
SESSION_SECRET=<generate with: openssl rand -base64 32>

# Cache revalidation
REVALIDATE_SECRET=<generate with: openssl rand -hex 32>

# API URLs
NEXT_PUBLIC_API_URL=https://api.deschide.md
NEXT_PUBLIC_CDN_URL=https://cdn.deschide.md

# Localization
NEXT_PUBLIC_DEFAULT_LOCALE=ro
NEXT_PUBLIC_AVAILABLE_LOCALES=ro,en,ru
```

---

## Security Checklist

Before deploying to production, verify:

- [ ] `SESSION_SECRET` is at least 32 characters and unique
- [ ] `SESSION_SECRET` is not the default development value
- [ ] `REVALIDATE_SECRET` is configured and matches backend
- [ ] No secrets are committed to version control
- [ ] `.env.local` and `.env.production` are in `.gitignore`
- [ ] Production secrets are stored securely (e.g., Vault, AWS Secrets Manager)

---

## Generating Secure Secrets

### Using OpenSSL

```bash
# For SESSION_SECRET (base64, 32+ chars)
openssl rand -base64 32

# For REVALIDATE_SECRET (hex, 64 chars)
openssl rand -hex 32
```

### Using Node.js

```javascript
// For SESSION_SECRET
require('crypto').randomBytes(32).toString('base64')

// For REVALIDATE_SECRET
require('crypto').randomBytes(32).toString('hex')
```

### Using Python

```python
import secrets

# For SESSION_SECRET
print(secrets.token_urlsafe(32))

# For REVALIDATE_SECRET
print(secrets.token_hex(32))
```

---

## Rotation Procedure

When rotating secrets:

1. **SESSION_SECRET:**
   - Generate new secret
   - Deploy with new secret
   - All existing sessions will be invalidated (users must re-login)

2. **REVALIDATE_SECRET:**
   - Generate new secret
   - Update frontend first, then backend
   - Brief window where revalidation may fail

---

## Troubleshooting

### "Session expired" errors after deployment

- Likely cause: `SESSION_SECRET` was changed
- Solution: Users need to re-login

### "Revalidation failed" errors

- Check `REVALIDATE_SECRET` matches between frontend and backend
- Check `FRONTEND_REVALIDATE_URL` in backend is correct
- Check rate limiting isn't blocking requests

### "Invalid token" errors

- Check `SESSION_SECRET` is properly set
- Check for trailing whitespace in environment variable
- Verify secret is at least 32 characters

---

## References

- [Next.js Environment Variables](https://nextjs.org/docs/app/building-your-application/configuring/environment-variables)
- [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- [12 Factor App - Config](https://12factor.net/config)
