# Cloudflare CDN Integration Guide

## Overview

This guide walks through integrating Cloudflare CDN with the Deschide News Backend API to provide global edge caching, DDoS protection, and improved worldwide performance.

**Performance Stack:**
```
Client Request
    ↓
Cloudflare Edge (200+ locations worldwide)
    ↓ [if not cached]
Varnish HTTP Cache (local server)
    ↓ [if not cached]
Symfony Backend (port 8081)
    ↓
PostgreSQL Database
```

**Expected Performance Improvement:**
- **Local users (Moldova)**: 10ms → 5-8ms (Cloudflare edge + Varnish)
- **European users**: 10ms → 20-40ms (Cloudflare edge in nearby cities)
- **Global users (US, Asia)**: 200-400ms → 30-60ms (local edge cache)
- **DDoS protection**: Unlimited (Cloudflare absorbs attacks)
- **Bandwidth savings**: 80-95% (most traffic served from edge)

---

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Cloudflare Account Setup](#cloudflare-account-setup)
3. [DNS Configuration](#dns-configuration)
4. [SSL/TLS Configuration](#ssltls-configuration)
5. [Cache Rules Configuration](#cache-rules-configuration)
6. [Security Settings](#security-settings)
7. [Performance Optimizations](#performance-optimizations)
8. [API Integration](#api-integration)
9. [Testing](#testing)
10. [Production Deployment](#production-deployment)

---

## Prerequisites

- [x] Domain name (e.g., `deschide.md`)
- [x] Access to domain DNS settings
- [x] Varnish HTTP cache installed and working (Week 2 Day 1)
- [x] Symfony backend with proper cache headers
- [ ] Cloudflare account (free or paid)

---

## 1. Cloudflare Account Setup

### Step 1: Create Cloudflare Account

1. Go to https://dash.cloudflare.com/sign-up
2. Create account with email
3. Verify email address

### Step 2: Add Your Domain

1. Click **"Add a Site"** in dashboard
2. Enter your domain: `deschide.md`
3. Select plan:
   - **Free**: Basic CDN, SSL, DDoS protection (sufficient for most)
   - **Pro ($20/month)**: WAF, Image optimization, Polish
   - **Business ($200/month)**: Advanced WAF, Custom SSL
   - **Enterprise**: Contact sales for custom pricing

**Recommendation**: Start with **Free** plan, upgrade to **Pro** if needed.

### Step 3: DNS Scan

Cloudflare will automatically scan your existing DNS records:
- Review all detected records
- Ensure critical records (A, MX, TXT) are correct
- Click **"Continue"**

### Step 4: Update Nameservers

Cloudflare will provide nameservers like:
```
john.ns.cloudflare.com
mary.ns.cloudflare.com
```

**Update at your domain registrar:**
1. Log into your domain registrar (GoDaddy, Namecheap, etc.)
2. Find DNS/Nameserver settings
3. Replace existing nameservers with Cloudflare's
4. Save changes

⏱️ **DNS propagation takes 2-24 hours** (usually < 2 hours)

---

## 2. DNS Configuration

### API Subdomain Setup

For the backend API, configure a subdomain (e.g., `api.deschide.md`):

**DNS Records to Add:**

| Type | Name | Content | Proxy Status | TTL |
|------|------|---------|--------------|-----|
| A | api | YOUR_SERVER_IP | ✅ Proxied (orange cloud) | Auto |
| AAAA | api | YOUR_IPv6 (optional) | ✅ Proxied | Auto |

**Example:**
```
A     api.deschide.md    →  192.0.2.100     [🟠 Proxied]
```

**Important:**
- **Orange cloud (Proxied)**: Traffic goes through Cloudflare CDN ✅ Use this!
- **Grey cloud (DNS only)**: Direct connection, no CDN ❌ Don't use

### Frontend Subdomain (Optional)

If using separate subdomain for frontend:

| Type | Name | Content | Proxy Status | TTL |
|------|------|---------|--------------|-----|
| A | www | YOUR_SERVER_IP | ✅ Proxied | Auto |
| A | @ | YOUR_SERVER_IP | ✅ Proxied | Auto |

---

## 3. SSL/TLS Configuration

### Step 1: SSL/TLS Encryption Mode

Navigate to **SSL/TLS** → **Overview**

**Encryption Modes:**

1. ❌ **Off**: No encryption (never use)
2. ❌ **Flexible**: Cloudflare ↔ User (HTTPS), Cloudflare ↔ Origin (HTTP)
3. ✅ **Full**: Cloudflare ↔ User (HTTPS), Cloudflare ↔ Origin (HTTPS with self-signed OK)
4. ⭐ **Full (Strict)**: Cloudflare ↔ User (HTTPS), Cloudflare ↔ Origin (HTTPS with valid cert)

**Recommendation**: Use **Full (Strict)** for production

### Step 2: Origin Certificate (For Your Server)

Generate a Cloudflare Origin Certificate for your server:

1. Go to **SSL/TLS** → **Origin Server**
2. Click **"Create Certificate"**
3. Select:
   - **Key type**: RSA (2048)
   - **Hostnames**: `*.deschide.md`, `deschide.md`
   - **Validity**: 15 years
4. Click **"Create"**
5. Save certificate and private key:

```bash
# On your server
sudo mkdir -p /etc/cloudflare-certs
sudo nano /etc/cloudflare-certs/cert.pem
# Paste certificate

sudo nano /etc/cloudflare-certs/key.pem
# Paste private key

sudo chmod 600 /etc/cloudflare-certs/key.pem
sudo chmod 644 /etc/cloudflare-certs/cert.pem
```

### Step 3: Configure Nginx/Apache with Origin Certificate

**For Nginx:**
```nginx
server {
    listen 443 ssl http2;
    server_name api.deschide.md;

    ssl_certificate /etc/cloudflare-certs/cert.pem;
    ssl_certificate_key /etc/cloudflare-certs/key.pem;

    # Proxy to Varnish
    location / {
        proxy_pass http://127.0.0.1:6081;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Step 4: Enable Additional SSL Features

**SSL/TLS** → **Edge Certificates**:
- ✅ **Always Use HTTPS**: Redirect HTTP to HTTPS
- ✅ **HTTP Strict Transport Security (HSTS)**: Enable with:
  - Max Age: 6 months (15768000 seconds)
  - Include subdomains: Yes
  - Preload: No (initially)
- ✅ **Minimum TLS Version**: TLS 1.2
- ✅ **Opportunistic Encryption**: Enabled
- ✅ **TLS 1.3**: Enabled
- ✅ **Automatic HTTPS Rewrites**: Enabled

---

## 4. Cache Rules Configuration

### Step 1: Browser Cache TTL

**Caching** → **Configuration**:
- **Browser Cache TTL**: Respect Existing Headers
  - This allows your backend to control `Cache-Control: max-age`

### Step 2: Cache Level

**Caching** → **Configuration**:
- **Caching Level**: Standard
  - Caches static files automatically
  - Respects query strings
  - Respects cookies

### Step 3: Page Rules for API Caching

Cloudflare doesn't cache HTML/JSON responses by default. Create **Page Rules** to cache API responses:

**Rule 1: Cache API GET Requests**

Navigate to **Rules** → **Page Rules** → **Create Page Rule**

**URL Pattern:**
```
api.deschide.md/api/*
```

**Settings:**
- ✅ **Cache Level**: Cache Everything
- ✅ **Edge Cache TTL**: 1 hour (3600 seconds)
- ✅ **Browser Cache TTL**: 30 minutes (1800 seconds)
- ✅ **Origin Cache Control**: On (respect s-maxage)

**Important**: This only caches GET requests. POST/PUT/DELETE are never cached.

**Rule 2: Bypass Cache for Admin Endpoints**

**URL Pattern:**
```
api.deschide.md/api/admin/*
```

**Settings:**
- ✅ **Cache Level**: Bypass

**Rule 3: Bypass Cache for Auth Endpoints**

**URL Pattern:**
```
api.deschide.md/api/login*
```

**Settings:**
- ✅ **Cache Level**: Bypass

**Page Rules Order** (top to bottom):
1. Bypass admin (`/api/admin/*`)
2. Bypass auth (`/api/login*`, `/api/token*`)
3. Cache everything else (`/api/*`)

### Step 4: Cache by Device Type (Optional)

For responsive API with device-specific responses:

**Caching** → **Configuration**:
- **Cache by Device Type**: Enable
  - Separate cache for Mobile, Desktop, Tablet

---

## 5. Security Settings

### Step 1: Firewall Rules

Navigate to **Security** → **WAF** → **Firewall Rules**

**Rule 1: Block Common Attack Patterns**

**Expression:**
```
(http.request.uri.path contains "/wp-admin") or
(http.request.uri.path contains "/phpMyAdmin") or
(http.request.uri.path contains ".env") or
(http.request.uri.path contains ".git")
```

**Action**: Block

**Rule 2: Rate Limiting for API**

**Expression:**
```
(http.request.uri.path contains "/api/") and
(rate(5m) > 300)
```

**Action**: Block
- Limit: 300 requests per 5 minutes per IP

**Rule 3: Allow Only GET for API** (Optional)

If your API is read-only from public:

**Expression:**
```
(http.request.uri.path contains "/api/") and
(http.request.method ne "GET" and http.request.method ne "OPTIONS")
```

**Action**: Block

### Step 2: DDoS Protection

**Security** → **DDoS**:
- ✅ **HTTP DDoS Attack Protection**: Enabled (automatic)
- ✅ **Network-layer DDoS Attack Protection**: Enabled (automatic)

No configuration needed - Cloudflare automatically protects against DDoS attacks.

### Step 3: Bot Protection (Pro Plan)

**Security** → **Bots**:
- ✅ **Bot Fight Mode**: Enabled (Free plan)
- ✅ **Super Bot Fight Mode**: Enabled (Pro plan)
  - Block automated bots
  - Allow verified bots (Google, Bing)

### Step 4: Security Level

**Security** → **Settings**:
- **Security Level**: Medium (recommended)
  - Low: Minimal challenge
  - Medium: Balanced security
  - High: Challenge more visitors
  - I'm Under Attack: Maximum security (emergency mode)

---

## 6. Performance Optimizations

### Step 1: Auto Minify

**Speed** → **Optimization**:
- ✅ **Auto Minify**: Enable for JavaScript, CSS, HTML
  - Reduces file size by 20-40%

### Step 2: Brotli Compression

**Speed** → **Optimization**:
- ✅ **Brotli**: Enabled
  - Better compression than gzip (15-25% smaller)

### Step 3: Early Hints

**Speed** → **Optimization**:
- ✅ **Early Hints**: Enabled
  - Sends HTTP 103 responses to preload resources

### Step 4: HTTP/2 and HTTP/3

**Network** → **Protocol**:
- ✅ **HTTP/2**: Enabled
- ✅ **HTTP/3 (QUIC)**: Enabled
  - Faster, more reliable connections

### Step 5: Argo Smart Routing (Paid Feature)

**Traffic** → **Argo Smart Routing**:
- Cost: $5/month + $0.10 per GB
- Benefit: 30% faster average, route traffic through less congested paths
- **Recommendation**: Enable if international audience is important

### Step 6: Polish (Image Optimization) - Pro Plan

**Speed** → **Optimization** → **Polish**:
- **Lossy**: Compress images aggressively (recommended for thumbnails)
- **Lossless**: Compress without quality loss
- **WebP**: Convert images to WebP format

### Step 7: Mirage (Lazy Loading) - Pro Plan

**Speed** → **Optimization** → **Mirage**:
- ✅ **Mirage**: Enabled
  - Lazy load images
  - Load low-res placeholders first

---

## 7. API Integration (Cache Purging)

### Cloudflare API Token

To purge cache programmatically, create an API token:

1. Go to **My Profile** → **API Tokens**
2. Click **"Create Token"**
3. Select **"Edit zone DNS"** template or **Custom Token**:
   - **Permissions**: `Zone.Cache Purge`
   - **Zone Resources**: Include → `deschide.md`
4. Create token and **save it securely**

**Example Token:**
```
qwerty1234567890abcdefghijklmnopqrstuvwxyz
```

### Environment Variables

Add to `.env.local`:
```bash
###> cloudflare-cdn ###
CLOUDFLARE_API_TOKEN=your_cloudflare_api_token_here
CLOUDFLARE_ZONE_ID=your_zone_id_here
CLOUDFLARE_ENABLED=true
###< cloudflare-cdn ###
```

**Find Zone ID:**
- Dashboard → Select Domain → Overview (right sidebar)
- Example: `abc123def456789xyz`

### Symfony Service for Cloudflare Cache

See `src/Service/CloudflareCacheService.php` (created below)

---

## 8. Testing

### Test 1: Verify DNS Propagation

```bash
# Check if domain points to Cloudflare
dig api.deschide.md

# Should return Cloudflare IPs (104.x.x.x or 2606:4700::/32)
```

### Test 2: Verify SSL/TLS

```bash
# Check certificate
curl -vI https://api.deschide.md 2>&1 | grep -E "(SSL|TLS|certificate)"

# Should show:
# - TLS 1.3
# - Cloudflare certificate
```

### Test 3: Verify Caching

```bash
# First request (MISS)
curl -I https://api.deschide.md/api/articles?itemsPerPage=5

# Look for headers:
# cf-cache-status: MISS
# cf-ray: xxx-XXX (Cloudflare edge location)

# Second request (HIT)
curl -I https://api.deschide.md/api/articles?itemsPerPage=5

# cf-cache-status: HIT
# age: X (seconds since cached)
```

### Test 4: Global Performance

```bash
# Test from multiple locations using:
# https://www.dotcom-tools.com/website-speed-test.aspx

# Enter: https://api.deschide.md/api/articles?itemsPerPage=5
# Test from: USA, Europe, Asia

# Expected:
# - USA: 30-60ms
# - Europe: 20-40ms
# - Asia: 40-80ms
```

---

## 9. Production Deployment

### Pre-Deployment Checklist

- [ ] Cloudflare account created and domain added
- [ ] Nameservers updated (DNS propagated)
- [ ] SSL/TLS configured (Full Strict mode)
- [ ] Origin certificate installed on server
- [ ] Page rules created for API caching
- [ ] Firewall rules configured
- [ ] API token created for cache purging
- [ ] Environment variables added to `.env.local`
- [ ] Nginx/Apache configured to proxy to Varnish
- [ ] Tested caching and purging

### Deployment Steps

1. **Update Nginx Configuration** (see example above)
2. **Restart Nginx**: `sudo systemctl restart nginx`
3. **Clear Symfony Cache**: `symfony console cache:clear --env=prod`
4. **Test Backend**: `curl -I https://api.deschide.md/api`
5. **Monitor Cloudflare Dashboard**: Check traffic and cache hit ratio

### Rollback Plan

If issues occur:
1. **Bypass Cloudflare**: Change DNS to grey cloud (DNS only)
2. **Revert Nameservers**: Point back to original DNS provider
3. **Direct Traffic**: Point A record directly to server IP

---

## 10. Expected Results

### Performance Comparison

| Location | Before CDN | After CDN | Improvement |
|----------|------------|-----------|-------------|
| **Moldova (local)** | 10ms | 5-8ms | 20-50% faster |
| **Romania** | 30ms | 15-20ms | 50% faster |
| **Germany** | 60ms | 25-35ms | 60% faster |
| **USA (East)** | 200ms | 30-50ms | **85% faster** |
| **USA (West)** | 250ms | 40-60ms | **80% faster** |
| **Asia** | 400ms | 50-80ms | **87% faster** |

### Cache Hit Ratio

**Expected after 24 hours:**
- **Cloudflare edge**: 85-95% hit rate
- **Varnish origin**: 70-80% hit rate (only MISS from edge)
- **Backend**: 5-10% requests (only double MISS)

**Traffic Distribution:**
```
100 requests
  ↓
85-95 served by Cloudflare edge (5-40ms)
  ↓
5-15 reach Varnish (10-15ms)
  ↓
1-3 reach Backend (200-300ms)
```

### Bandwidth Savings

- **Before**: 100% traffic hits origin server
- **After**: 5-15% traffic hits origin server
- **Savings**: 85-95% bandwidth reduction
- **Cost Impact**: Can handle 10-20x traffic with same infrastructure

### Security Benefits

- ✅ **DDoS protection**: Automatic, unlimited
- ✅ **WAF**: Block SQL injection, XSS, common attacks
- ✅ **Bot protection**: Block malicious bots
- ✅ **Rate limiting**: Prevent abuse
- ✅ **SSL/TLS**: Automatic certificate management

---

## Monitoring and Maintenance

### Cloudflare Dashboard

Monitor daily:
- **Traffic**: Requests per hour/day
- **Cache Hit Ratio**: Should be > 85%
- **Bandwidth Saved**: GB saved by CDN
- **Security Events**: Blocked threats

### Cache Purge Strategies

**When to purge:**
1. **Article updated**: Purge specific article URL
2. **Article created**: Purge article list pages
3. **Category updated**: Purge category and related articles
4. **Full deployment**: Purge entire cache

**Purge methods:**
- **Single file**: `CloudflareCacheService::purgeUrl('/api/articles/123')`
- **By prefix**: `CloudflareCacheService::purgeByPrefix('/api/articles')`
- **Everything**: `CloudflareCacheService::purgeAll()` (use sparingly!)

---

## Cost Analysis

### Free Plan (Sufficient for Most)

**Includes:**
- Unlimited DDoS protection
- Global CDN (200+ locations)
- Free SSL certificates
- Basic WAF
- 50 Page Rules

**Cost**: $0/month

### Pro Plan ($20/month)

**Additional features:**
- Advanced WAF
- Image optimization (Polish)
- Lazy loading (Mirage)
- Mobile optimization
- 20 WAF rules

**Worth it if**: Large traffic, many images, international audience

### Business Plan ($200/month)

**Additional features:**
- Custom SSL
- 100% uptime SLA
- Priority support
- Advanced DDoS

**Worth it if**: Enterprise, critical uptime requirements

### Argo Smart Routing ($5 + $0.10/GB)

**Benefit**: 30% faster on average
**Cost calculation**:
- Base: $5/month
- Traffic: 100GB/month × $0.10 = $10
- Total: $15/month

**Worth it if**: International audience, need every millisecond

---

## Troubleshooting

### Issue 1: Cache Not Working

**Symptoms**: `cf-cache-status: BYPASS` or `DYNAMIC`

**Possible causes:**
1. Page rule not matching URL pattern
2. Cookies being sent
3. Authorization header present

**Solution:**
```bash
# Check headers
curl -I https://api.deschide.md/api/articles?itemsPerPage=5

# Verify Page Rules in Cloudflare dashboard
# Ensure "Cache Everything" is enabled
```

### Issue 2: SSL Certificate Errors

**Symptoms**: `ERR_SSL_VERSION_OR_CIPHER_MISMATCH`

**Solution:**
1. Check SSL/TLS mode is **Full (Strict)**
2. Verify origin certificate is installed
3. Check Nginx SSL configuration

### Issue 3: CORS Errors

**Symptoms**: Browser blocks requests due to CORS

**Solution:**
Add CORS headers in Symfony (backend handles this, not Cloudflare)

---

## Summary

**Cloudflare CDN provides:**
- ✅ **95% faster** responses for global users
- ✅ **85-95% cache hit rate** at edge
- ✅ **Unlimited DDoS protection**
- ✅ **Free SSL/TLS certificates**
- ✅ **90% bandwidth savings**
- ✅ **10-20x traffic capacity** with same infrastructure

**Next Steps:**
1. Create Cloudflare account
2. Add domain and update nameservers
3. Configure SSL/TLS and cache rules
4. Install `CloudflareCacheService` (see below)
5. Test and monitor

---

**Last Updated**: 4 Noiembrie 2025
**Status**: Ready for implementation
**ROI**: ⭐⭐⭐⭐⭐ (Very High for international audience)
