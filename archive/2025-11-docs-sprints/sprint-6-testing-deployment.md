# Sprint 6: Testing & Deployment Guide

**Date:** 2025-11-01
**Sprint:** 6 - Admin Dashboard & User-Facing Features
**Status:** ✅ Ready for Testing

---

## 📋 Overview

This guide provides comprehensive testing procedures and deployment checklist for Sprint 6 components. All backend and frontend features have been implemented and are ready for validation.

---

## ✅ Pre-Testing Checklist

### Backend Requirements
- [x] Symfony server running on port 8081
- [x] PostgreSQL database populated with sample data
- [x] Redis running on port 6379 (DB 1)
- [x] Daily stats aggregation cron jobs configured
- [x] JWT authentication configured

### Frontend Requirements
- [x] Next.js dev server running on port 3005
- [x] Dependencies installed (`pnpm install`)
- [x] Environment variables configured (`.env.local`)
- [x] API endpoints accessible

### Verification Commands
```bash
# Backend
cd /var/www/deschide_news_app/deschide_backend
symfony server:status
symfony console doctrine:query:sql "SELECT COUNT(*) FROM articles"

# Frontend
cd /var/www/deschide_news_app/deschide_frontend
pnpm dev

# Services
redis-cli -n 1 PING  # Should return PONG
psql -U deschide_user -d deschide_news -c "SELECT 1"  # Should return 1
```

---

## 🧪 Backend API Testing

### 1. Trending Articles Endpoint (Public)

**Endpoint:** `GET /api/admin/stats/trending`

**Test Cases:**

```bash
# Test 1: Default parameters (Romanian, 10 articles)
curl -s http://127.0.0.1:8081/api/admin/stats/trending | jq '.'

# Expected: 200 OK, array of trending articles with Romanian translations

# Test 2: English translation
curl -s -H "Accept-Language: en" \
  http://127.0.0.1:8081/api/admin/stats/trending?limit=5 | jq '.'

# Expected: 200 OK, English translations (null if not available)

# Test 3: Russian translation
curl -s -H "Accept-Language: ru" \
  http://127.0.0.1:8081/api/admin/stats/trending?limit=3 | jq '.'

# Test 4: Custom limit
curl -s http://127.0.0.1:8081/api/admin/stats/trending?limit=20 | jq '. | length'

# Expected: Number between 0-20 depending on data
```

**Validation:**
- [ ] Response status is 200
- [ ] Returns array of articles
- [ ] Each article has: id, title, slug, category, views_24h, published_at
- [ ] Category includes: id, name, slug
- [ ] Locale handling works (ro, en, ru)
- [ ] Missing translations return null gracefully
- [ ] Response cached (verify with multiple calls)

### 2. Article Statistics Endpoint (Admin)

**Endpoint:** `GET /api/admin/stats/article/{id}`

**Get Admin Token:**
```bash
TOKEN=$(symfony console app:test:jwt-token 2>/dev/null | grep "^eyJ" | tail -1)
echo "Token: $TOKEN"
```

**Test Cases:**

```bash
# Test 1: Get stats for article ID 81 (7 days)
curl -s -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/article/81?range=7days" | jq '.'

# Expected: 200 OK with article stats

# Test 2: Different date ranges
curl -s -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/article/81?range=today" | jq '.stats | length'

curl -s -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/article/81?range=30days" | jq '.stats | length'

# Test 3: Without authentication
curl -s http://127.0.0.1:8081/api/admin/stats/article/81 | jq '.'

# Expected: 401 Unauthorized

# Test 4: Non-existent article
curl -s -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/article/999999" | jq '.'

# Expected: 404 Not Found
```

**Validation:**
- [ ] Requires authentication (401 without token)
- [ ] Returns article_id, title, current_views, stats array
- [ ] Stats include: date, views, unique_visitors, avg_reading_time, completion_rate
- [ ] Date range filtering works
- [ ] 404 for non-existent articles
- [ ] Response cached for 60 seconds

### 3. Site Statistics Endpoint (Admin)

**Endpoint:** `GET /api/admin/stats/site`

**Test Cases:**

```bash
# Test 1: Default (7 days)
curl -s -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/site" | jq '.'

# Test 2: All date ranges
for range in today yesterday 7days 30days; do
  echo "Testing range: $range"
  curl -s -H "Authorization: Bearer $TOKEN" \
    "http://127.0.0.1:8081/api/admin/stats/site?range=$range" | jq '.stats | length'
done

# Test 3: Real-time data
curl -s -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/site" | jq '.realtime'

# Expected: Contains unique_visitors_today
```

**Validation:**
- [ ] Requires ROLE_ADMIN
- [ ] Returns realtime and stats objects
- [ ] Realtime includes unique_visitors_today
- [ ] Stats array has daily records with all metrics
- [ ] Metrics: total_visits, unique_visitors, new_visitors, bounce_rate, avg_session_duration
- [ ] Date range filtering works

### 4. Real-Time Statistics Endpoint (Admin)

**Endpoint:** `GET /api/admin/stats/realtime`

**Test Cases:**

```bash
# Test 1: Get real-time stats
curl -s -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/realtime" | jq '.'

# Test 2: Verify no caching (multiple calls should have different timestamps)
for i in {1..3}; do
  sleep 2
  curl -s -H "Authorization: Bearer $TOKEN" \
    "http://127.0.0.1:8081/api/admin/stats/realtime" | jq '.timestamp'
done

# Expected: Different timestamps each time
```

**Validation:**
- [ ] Requires authentication
- [ ] Returns timestamp, active_sessions, unique_visitors_today, trending_now
- [ ] Trending_now is array of top 5 articles
- [ ] Each trending item has article_id and views
- [ ] No caching (fresh data every time)
- [ ] Timestamp updates on each request

### 5. Security Testing

```bash
# Test 1: Public endpoint without auth
curl -s http://127.0.0.1:8081/api/admin/stats/trending | jq '. | length'
# Expected: 200 OK

# Test 2: Admin endpoint without auth
curl -s http://127.0.0.1:8081/api/admin/stats/site | jq '.'
# Expected: 401 Unauthorized

# Test 3: Invalid token
curl -s -H "Authorization: Bearer invalid_token" \
  http://127.0.0.1:8081/api/admin/stats/site | jq '.'
# Expected: 401 Unauthorized

# Test 4: Expired token (if you have one)
# Expected: 401 Unauthorized
```

**Validation:**
- [ ] `/trending` accessible without auth
- [ ] All other endpoints require authentication
- [ ] Invalid tokens rejected
- [ ] Expired tokens rejected
- [ ] CORS headers present

---

## 🎨 Frontend Component Testing

### 1. Admin Statistics Page

**URL:** `http://localhost:3005/ro/admin/statistics`

**Manual Test Steps:**

1. **Page Load**
   - [ ] Navigate to `/ro/admin/statistics`
   - [ ] Page loads within 2 seconds
   - [ ] No console errors
   - [ ] Header displays correctly

2. **Site Stats Overview Cards**
   - [ ] 4 stat cards display (Total Visits, Unique Visitors, Bounce Rate, Avg Session)
   - [ ] Numbers format correctly (1,234 format)
   - [ ] Icons display for each card
   - [ ] Colors: blue, green, yellow, purple
   - [ ] Hover effects work

3. **Real-Time Stats Widget**
   - [ ] Widget displays on right side
   - [ ] Active sessions count shows
   - [ ] Unique visitors today shows
   - [ ] "Live" indicator pulses (green dot)
   - [ ] Updates every 5 seconds
   - [ ] Pause button works
   - [ ] Resume button works
   - [ ] Last updated timestamp updates
   - [ ] Top 3 trending articles preview shows

4. **Trending Articles Table**
   - [ ] Table displays top 10 articles
   - [ ] Rank numbers show (#1, #2, #3 with colors)
   - [ ] Article titles link to edit pages
   - [ ] Category badges display
   - [ ] View counts formatted (1,234)
   - [ ] Trend indicators show (🔥 for top 3, 📈 for others)
   - [ ] Hover states work
   - [ ] "Auto-refreshes every 5 minutes" message shows

5. **Date Range Picker**
   - [ ] Dropdown displays
   - [ ] Options: Today, Yesterday, Last 7 Days, Last 30 Days
   - [ ] Changing range updates data
   - [ ] URL updates with `?range=` parameter

6. **Loading States**
   - [ ] Skeleton loaders display while fetching
   - [ ] Smooth transition to content
   - [ ] No flash of unstyled content

7. **Error Handling**
   - [ ] Graceful error messages if API fails
   - [ ] Retry button available
   - [ ] No page crash

### 2. Homepage Trending Section

**URL:** `http://localhost:3005/ro`

**Manual Test Steps:**

1. **Section Display**
   - [ ] Navigate to homepage
   - [ ] Trending section appears after "Latest News"
   - [ ] Header shows "🔥 Trending Now"
   - [ ] 5 articles display by default

2. **Article Cards**
   - [ ] Grid layout (1 col mobile, 2 tablet, 3 desktop)
   - [ ] #1 trending has special badge
   - [ ] Top 3 have rank numbers with opacity
   - [ ] Category badges show
   - [ ] View counts display
   - [ ] Trending icons show
   - [ ] "Last 24 hours" text displays

3. **Hover Effects**
   - [ ] Cards elevate on hover (shadow increases)
   - [ ] Border color changes to blue
   - [ ] Title color changes to blue
   - [ ] Smooth transitions

4. **Links**
   - [ ] Clicking card navigates to article page
   - [ ] URLs correct: `/{categorySlug}/{articleSlug}`
   - [ ] No broken links

5. **Multi-Language**
   - [ ] Switch to `/en` - English articles show
   - [ ] Switch to `/ru` - Russian articles show
   - [ ] Missing translations show null gracefully

### 3. View Count Badges

**URL:** Any article card location

**Manual Test Steps:**

1. **Badge Display**
   - [ ] Badge shows for articles with >100 views
   - [ ] Badge hidden for articles with <100 views
   - [ ] Eye icon displays
   - [ ] View count formatted correctly

2. **Number Formatting**
   - [ ] 1,234 views → "1.2k views"
   - [ ] 15,678 views → "15.7k views"
   - [ ] 1,234,567 views → "1.2M views"
   - [ ] 150 views → "150 views"
   - [ ] 50 views → No badge

3. **Styling**
   - [ ] Gray text color
   - [ ] Small font size
   - [ ] Inline with category
   - [ ] Right-aligned in card footer

### 4. Loading Skeleton Component

**Test in Admin Page:**

1. **Skeleton Variants**
   - [ ] `stat` variant: Card shape with animated pulse
   - [ ] `table` variant: Rows with varying widths
   - [ ] `card` variant: Standard card layout
   - [ ] `text` variant: Text lines

2. **Animation**
   - [ ] Pulse animation smooth
   - [ ] Gray color scheme
   - [ ] Matches final component size

---

## 📱 Responsive Design Testing

### Desktop (1920x1080)

**Admin Statistics Page:**
- [ ] 3-column grid (2 cols stats, 1 col realtime)
- [ ] Stat cards in 4-column layout
- [ ] Trending table full width
- [ ] No horizontal scroll

**Homepage Trending:**
- [ ] 3 columns
- [ ] Cards well-spaced
- [ ] Images display properly

### Laptop (1366x768)

- [ ] Layout adjusts appropriately
- [ ] No content overflow
- [ ] Readable font sizes

### Tablet (768x1024)

**Admin Page:**
- [ ] Stats stack vertically or in 2 columns
- [ ] Table scrolls horizontally if needed
- [ ] Real-time widget full width

**Homepage:**
- [ ] 2 columns for trending
- [ ] Touch-friendly card sizes

### Mobile (375x667)

**Admin Page:**
- [ ] Single column layout
- [ ] Stat cards stack (2x2 grid)
- [ ] Table scrolls horizontally
- [ ] Real-time widget full width
- [ ] Date picker accessible

**Homepage:**
- [ ] Single column
- [ ] Cards stack vertically
- [ ] Touch targets adequate (44x44px)

---

## 🌐 Browser Compatibility Testing

### Chrome (Latest)
- [ ] All features work
- [ ] No console errors
- [ ] Animations smooth

### Firefox (Latest)
- [ ] Rendering correct
- [ ] Fetch API works
- [ ] Styles applied

### Safari (Latest)
- [ ] iOS compatibility
- [ ] Date formatting correct
- [ ] No layout issues

### Edge (Latest)
- [ ] All functionality works
- [ ] No warnings

---

## ⚡ Performance Testing

### Page Load Times

**Target:** <2 seconds initial load

```bash
# Admin statistics page
curl -w "@curl-format.txt" -o /dev/null -s http://localhost:3005/ro/admin/statistics

# Create curl-format.txt:
cat > curl-format.txt << 'EOF'
time_namelookup:  %{time_namelookup}s\n
time_connect:  %{time_connect}s\n
time_starttransfer:  %{time_starttransfer}s\n
time_total:  %{time_total}s\n
EOF
```

**Checklist:**
- [ ] Admin page loads in <2s
- [ ] Homepage loads in <1.5s
- [ ] API responses cached properly
- [ ] Images lazy load
- [ ] No unnecessary re-renders

### API Performance

```bash
# Measure API response times
for endpoint in trending site realtime; do
  echo "Testing $endpoint..."
  time curl -s -H "Authorization: Bearer $TOKEN" \
    "http://127.0.0.1:8081/api/admin/stats/$endpoint" > /dev/null
done
```

**Targets:**
- [ ] Trending: <200ms (cached <50ms)
- [ ] Site stats: <200ms (cached <50ms)
- [ ] Realtime: <150ms (no cache)

### Real-Time Polling

**Test:** Monitor for memory leaks during polling

1. Open admin stats page
2. Let run for 5 minutes
3. Check browser memory in DevTools
4. [ ] Memory stable (no continuous growth)
5. [ ] CPU usage reasonable (<10%)
6. [ ] No network errors

---

## 🔒 Security Testing

### Authentication

- [ ] Admin endpoints reject unauthenticated requests
- [ ] JWT tokens validated properly
- [ ] Expired tokens rejected
- [ ] Cookie-based auth works for SSR

### Authorization

- [ ] ROLE_ADMIN required for protected endpoints
- [ ] Regular users can't access admin stats
- [ ] Public trending endpoint accessible to all

### Data Validation

- [ ] SQL injection protected (parameterized queries)
- [ ] XSS protection (React escapes by default)
- [ ] CSRF protection (stateless JWT)

### CORS

- [ ] Allowed origins configured
- [ ] Credentials properly handled
- [ ] Preflight requests work

---

## 📊 Data Accuracy Testing

### View Counts

1. Track an article view:
```bash
# Assuming tracking endpoint exists
curl -X POST http://127.0.0.1:8081/api/track/view/81
```

2. Verify count updated:
```bash
# Check Redis
redis-cli -n 1 GET "deschide_news:stats:article:views:81"

# Check trending
curl http://127.0.0.1:8081/api/admin/stats/trending | jq '.[] | select(.id==81) | .views_24h'
```

- [ ] View count increments
- [ ] Trending list updates
- [ ] Real-time stats reflect changes

### Date Range Accuracy

Test that stats match the requested date range:

```bash
# Get stats for different ranges
curl -s -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/site?range=today" | jq '.stats | length'

# Should return 1 (today only)

curl -s -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8081/api/admin/stats/site?range=7days" | jq '.stats | length'

# Should return up to 7
```

- [ ] Today returns current day only
- [ ] Yesterday returns previous day
- [ ] 7days returns up to 7 records
- [ ] 30days returns up to 30 records

---

## 🚀 Deployment Checklist

### Pre-Deployment

- [ ] All tests passing
- [ ] No console errors or warnings
- [ ] Performance targets met
- [ ] Security review complete
- [ ] Documentation up to date

### Environment Configuration

**Production `.env` variables:**

```bash
# Backend
DATABASE_URL=postgresql://user:pass@host:5432/deschide_news
REDIS_URL=redis://host:6379/1
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
CORS_ALLOW_ORIGIN=^https://deschide\.md$

# Frontend
NEXT_PUBLIC_API_URL=https://api.deschide.md
NEXT_PUBLIC_CDN_URL=https://cdn.deschide.md
```

- [ ] Environment variables configured
- [ ] SSL certificates installed
- [ ] Database migrated
- [ ] Redis accessible
- [ ] JWT keys generated

### Backend Deployment

```bash
cd /var/www/deschide_news_app/deschide_backend

# 1. Pull latest code
git pull origin main

# 2. Install dependencies
composer install --no-dev --optimize-autoloader

# 3. Run migrations
php bin/console doctrine:migrations:migrate --no-interaction

# 4. Clear cache
php bin/console cache:clear --env=prod

# 5. Warm up cache
php bin/console cache:warmup --env=prod

# 6. Restart PHP-FPM
sudo systemctl restart php8.4-fpm
```

- [ ] Code deployed
- [ ] Database migrated
- [ ] Cache cleared
- [ ] PHP-FPM restarted

### Frontend Deployment

```bash
cd /var/www/deschide_news_app/deschide_frontend

# 1. Pull latest code
git pull origin main

# 2. Install dependencies
pnpm install --frozen-lockfile

# 3. Build for production
pnpm build

# 4. Restart PM2
pm2 restart deschide_frontend
pm2 save
```

- [ ] Code deployed
- [ ] Dependencies installed
- [ ] Production build created
- [ ] PM2 restarted

### Post-Deployment Verification

```bash
# 1. Check backend health
curl https://api.deschide.md/api/admin/stats/trending

# 2. Check frontend
curl https://deschide.md

# 3. Verify services
pm2 status
systemctl status php8.4-fpm
systemctl status nginx
```

- [ ] Backend accessible
- [ ] Frontend loads
- [ ] No 500 errors
- [ ] SSL working
- [ ] Monitoring active (Prometheus/Grafana)

### Rollback Plan

If issues occur:

```bash
# Backend
cd /var/www/deschide_news_app/deschide_backend
git checkout <previous-commit-hash>
composer install
php bin/console cache:clear
sudo systemctl restart php8.4-fpm

# Frontend
cd /var/www/deschide_news_app/deschide_frontend
git checkout <previous-commit-hash>
pnpm install
pnpm build
pm2 restart deschide_frontend
```

---

## 📈 Monitoring Post-Deployment

### Metrics to Watch

**Grafana Dashboards:**
- [ ] Cache hit rate >80%
- [ ] API response times <500ms
- [ ] Error rate <1%
- [ ] Memory usage stable

**Application Logs:**
```bash
# Backend
tail -f /var/www/deschide_news_app/deschide_backend/var/log/prod.log

# Frontend
pm2 logs deschide_frontend
```

- [ ] No error spikes
- [ ] Normal request patterns
- [ ] No memory leaks

### Health Checks

Set up automated health checks:

```bash
# Cron job to check every 5 minutes
*/5 * * * * curl -f https://api.deschide.md/api/admin/stats/trending || echo "API Down" | mail -s "Alert" admin@deschide.md
```

---

## 📞 Support & Troubleshooting

### Common Issues

**Issue 1: 401 Unauthorized on admin endpoints**
- Check JWT token in cookies
- Verify token not expired
- Check security.yaml configuration

**Issue 2: Empty trending list**
- Verify Redis has data: `redis-cli -n 1 ZRANGE deschide_news:stats:trending:24h 0 -1 WITHSCORES`
- Check article tracking is working
- Run manual aggregation: `symfony console app:aggregate-daily-stats`

**Issue 3: Slow page loads**
- Check cache hit rate in Grafana
- Verify Redis connection
- Check database query performance
- Review network tab in DevTools

**Issue 4: Real-time stats not updating**
- Check browser console for errors
- Verify WebSocket/polling working
- Check token validity
- Verify API endpoint accessible

### Log Locations

- Backend errors: `/var/www/deschide_news_app/deschide_backend/var/log/prod.log`
- Frontend logs: `pm2 logs deschide_frontend`
- Nginx access: `/var/log/nginx/access.log`
- Nginx errors: `/var/log/nginx/error.log`
- Redis logs: `/var/log/redis/redis-server.log`

---

## ✅ Final Sign-Off

Before marking Sprint 6 complete, verify:

- [ ] All backend endpoints tested and working
- [ ] All frontend components rendering correctly
- [ ] Responsive design validated on all breakpoints
- [ ] Browser compatibility confirmed
- [ ] Performance targets met
- [ ] Security requirements satisfied
- [ ] Documentation complete
- [ ] Deployment successful (if deploying)
- [ ] Monitoring configured
- [ ] Team trained on new features

---

**Document Version:** 1.0
**Last Updated:** 2025-11-01
**Next Review:** After deployment

**Approved By:** _________________
**Date:** _________________
