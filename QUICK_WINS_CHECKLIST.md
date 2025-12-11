# Quick Wins Checklist - Deschide News App

## Week 1: Critical Performance & ISR Fixes

### Backend Performance (2 hours)
- [ ] Increase PHP-FPM `pm.max_children = 150`
- [ ] Enable OPcache JIT: `opcache.jit=1255`
- [ ] Increase PostgreSQL `max_connections = 200`
- [ ] Restart PHP-FPM and PostgreSQL

**Expected Impact:** 50% reduction in error rate under load

### Frontend ISR Fixes (1 hour)
- [ ] Remove `export const dynamic = 'force-dynamic'` from article pages
- [ ] Add `export const revalidate = 60` to homepage
- [ ] Verify ISR working with `curl -I http://localhost:3005/ro`

**Expected Impact:** 80% faster article page loads (cached)

### Security Hardening (2 hours)
- [ ] Rotate all JWT keys (`symfony console lexik:jwt:generate-keypair --overwrite`)
- [ ] Enable Elasticsearch SSL: `verify_certs: true` in config
- [ ] Move credentials to environment variables

**Expected Impact:** Close critical security vulnerabilities

---

## Week 2: Analytics & Content

### Analytics Setup (3 hours)
- [ ] Create Google Analytics 4 property
- [ ] Install `@vercel/analytics` or `react-ga4`
- [ ] Add tracking to all pages
- [ ] Configure goals and events

**Expected Impact:** Ability to measure business metrics

### Content Migration (3 days)
```bash
cd /var/www/deschide_news_app/apps/backend
symfony console app:import:categories-direct
symfony console app:import:articles --limit=500
symfony console app:import:generate-thumbnails
```

**Expected Impact:** 500+ articles ready for launch

---

## Week 3: Feature Completion

### Enable Search (1 hour)
- [ ] Remove feature flag disabling search
- [ ] Test search across all locales
- [ ] Verify Elasticsearch queries

### On-Demand Revalidation (4 hours)
- [ ] Uncomment Messenger transports in `config/packages/messenger.yaml`
- [ ] Configure `FRONTEND_REVALIDATE_URL` in backend `.env`
- [ ] Configure `REVALIDATE_SECRET` in frontend `.env.local`
- [ ] Test ODR with article update

**Expected Impact:** Real-time cache updates

### Favicon & Branding (2 hours)
- [ ] Create favicon files (16x16, 32x32, 192x192, 512x512)
- [ ] Add to `apps/frontend/public/`
- [ ] Update metadata in `layout.tsx`

---

## Week 4: Production Readiness

### Monitoring (3 hours)
- [ ] Install Sentry for error tracking
- [ ] Configure uptime monitoring (UptimeRobot or similar)
- [ ] Set up Grafana dashboards
- [ ] Configure alerting rules

### Testing (2 hours)
- [ ] Run smoke tests: `./scripts/smoke-check.sh`
- [ ] Run load tests: `./scripts/run-load-tests.sh`
- [ ] Verify all 539 tests passing
- [ ] Test deployment process

### Documentation (2 hours)
- [ ] Update deployment runbook
- [ ] Document rollback procedures
- [ ] Create incident response plan
- [ ] Update README with production URLs

---

## Success Criteria

### Performance Targets
- ✅ p95 Response Time: < 500ms (uncached)
- ✅ p95 Response Time: < 100ms (cached)
- ✅ Error Rate: < 1% at 500 concurrent users
- ✅ Cache Hit Rate: > 80%

### Content Targets
- ✅ Minimum 500 articles
- ✅ All categories have content
- ✅ All translations complete

### Technical Targets
- ✅ All 539 tests passing
- ✅ Lighthouse score: 90+ (mobile)
- ✅ Core Web Vitals: Green (LCP < 2.5s, CLS < 0.1)
- ✅ Zero critical security vulnerabilities

### Business Targets
- ✅ Google Analytics 4 tracking active
- ✅ Search functionality enabled
- ✅ On-Demand Revalidation working
- ✅ Error monitoring configured

---

## Time Estimate

| Week | Hours | Focus |
|------|-------|-------|
| Week 1 | 5h | Performance + Security |
| Week 2 | 27h | Analytics + Content (3 days import) |
| Week 3 | 7h | Features |
| Week 4 | 7h | Production Ready |
| **Total** | **46h** | **~6 work days** |

---

## Priority Order

1. **P0 - Launch Blockers** (Must Fix):
   - Content migration (500+ articles)
   - Analytics (GA4)
   - Security hardening

2. **P1 - Performance** (Should Fix):
   - PHP-FPM configuration
   - ISR fixes
   - ODR implementation

3. **P2 - Features** (Nice to Have):
   - Search enabled
   - Monitoring
   - Favicon

---

**Generated**: 2025-12-09
**Next Review**: After Week 1 completion
