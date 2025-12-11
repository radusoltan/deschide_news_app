# Plan de Dezvoltare Production-Ready

**Data Generării:** 2025-12-01
**Bazat pe:** 6 Audituri Comprehensive (Code Review, Database, SEO, Security, Business)
**Durată Estimată:** 4-6 săptămâni (4 sprinturi de 1-1.5 săptămâni)

---

## Executive Summary

### Scoruri Audit

| Domeniu | Scor | Status |
|---------|------|--------|
| Backend (Symfony API) | 7.5/10 | ⚠️ Necesită îmbunătățiri |
| Frontend (Next.js) | 7.2/10 | ⚠️ Necesită îmbunătățiri |
| Database & Performance | 8.5/10 | ✅ Bun |
| SEO & Core Web Vitals | 7.5/10 | ⚠️ Necesită îmbunătățiri |
| Securitate | 6.5/10 | 🔴 Critic |
| Business Readiness | 6.5/10 | 🔴 Critic |

**Scor Mediu: 7.3/10** → Necesită ~30% îmbunătățire pentru production-ready

### Probleme Critice Identificate

1. **🔴 SECURITATE**: Secrete expuse în git history (JWT keys, .env)
2. **🔴 SECURITATE**: Elasticsearch SSL verification dezactivat
3. **🔴 CALITATE COD**: 39 erori ESLint, 44 console.log în producție
4. **🔴 TESTE**: Coverage frontend doar 1.29% (target: 70%)
5. **🔴 PERFORMANCE**: Redis cache hit rate doar 4% (target: >80%)
6. **🔴 CONȚINUT**: Doar 81 articole (minimum recomandat: 500+)

---

## Sprint 1: Securitate & Cleanup Critic (Săptămâna 1-1.5)

**Prioritate:** CRITICĂ
**Efort:** 40-50 ore
**Obiectiv:** Eliminarea vulnerabilităților de securitate și code cleanup

### 1.1 Securitate - URGENT (20h)

| Task | Severitate | Efort | Status |
|------|-----------|-------|--------|
| Regenerare completă JWT keypair | 🔴 Critical | 2h | ⬜ |
| Rotire toate secretele expuse în git | 🔴 Critical | 4h | ⬜ |
| Eliminare .env.example cu credențiale reale | 🔴 Critical | 1h | ⬜ |
| Activare Elasticsearch SSL verification | 🔴 Critical | 3h | ⬜ |
| Implementare rate limiting pe endpoints publice | 🔴 High | 4h | ⬜ |
| Restricționare CORS pe /api/embed | 🔴 High | 2h | ⬜ |
| Adăugare security headers (CSP, HSTS, X-Frame-Options) | 🔴 High | 4h | ⬜ |

**Comenzi necesare:**
```bash
# Regenerare JWT keys
cd /var/www/deschide_news_app/apps/backend
rm -f config/jwt/private.pem config/jwt/public.pem
symfony console lexik:jwt:generate-keypair

# Git history cleanup (ATENȚIE - operațiune distructivă)
git filter-branch --force --index-filter \
  'git rm --cached --ignore-unmatch config/jwt/*.pem .env .env.local' \
  --prune-empty --tag-name-filter cat -- --all
```

### 1.2 Code Cleanup - Frontend (15h)

| Task | Severitate | Efort | Status |
|------|-----------|-------|--------|
| Fix toate 39 erori ESLint | 🔴 Critical | 6h | ⬜ |
| Fix 34 warning-uri ESLint | 🟡 Medium | 3h | ⬜ |
| Eliminare 44 console.log din producție | 🔴 Critical | 2h | ⬜ |
| Înlocuire 30+ tipuri `any` cu tipuri corecte | 🟡 High | 4h | ⬜ |

**Comenzi:**
```bash
cd /var/www/deschide_news_app/apps/frontend

# Identificare probleme
pnpm lint

# Auto-fix unde posibil
pnpm lint --fix

# Găsire console.log
grep -r "console.log" --include="*.ts" --include="*.tsx" app/ components/ lib/
```

### 1.3 Code Cleanup - Backend (10h)

| Task | Severitate | Efort | Status |
|------|-----------|-------|--------|
| Creare phpunit.xml.dist | 🔴 Critical | 1h | ⬜ |
| Configurare PHPStan (level 5 minim) | 🟡 High | 3h | ⬜ |
| Configurare PHP-CS-Fixer | 🟡 High | 2h | ⬜ |
| Update dependențe Doctrine | 🟡 High | 4h | ⬜ |

**Comenzi:**
```bash
cd /var/www/deschide_news_app/apps/backend

# Adaugă tool-uri de quality
composer require --dev phpstan/phpstan phpstan/phpstan-symfony phpstan/phpstan-doctrine
composer require --dev friendsofphp/php-cs-fixer

# Rulează analiză
vendor/bin/phpstan analyse src --level=5
vendor/bin/php-cs-fixer fix --dry-run --diff
```

### Deliverables Sprint 1
- [ ] Toate secretele rotite și securizate
- [ ] Zero erori ESLint
- [ ] Zero console.log în cod producție
- [ ] PHPStan level 5 fără erori
- [ ] Elasticsearch cu SSL valid
- [ ] Rate limiting activ pe /api/*

---

## Sprint 2: Performance & Caching (Săptămâna 2-3)

**Prioritate:** HIGH
**Efort:** 35-45 ore
**Obiectiv:** Optimizarea cache și performance

### 2.1 Redis Cache Optimization (15h)

| Task | Severitate | Efort | Status |
|------|-----------|-------|--------|
| Diagnostic cache hit rate (curent: 4%) | 🔴 Critical | 2h | ⬜ |
| Configurare corectă TTL per tip conținut | 🔴 Critical | 4h | ⬜ |
| Implementare cache warming | 🟡 High | 4h | ⬜ |
| Adăugare tag-based invalidation | 🟡 High | 3h | ⬜ |
| Monitorizare cache metrics în Grafana | 🟡 Medium | 2h | ⬜ |

**TTL Strategy:**
```yaml
# Recomandat în config/packages/cache.yaml
cache:
  pools:
    articles.cache:
      default_lifetime: 3600      # 1 oră pentru articole
    categories.cache:
      default_lifetime: 86400     # 24 ore pentru categorii
    homepage.cache:
      default_lifetime: 300       # 5 minute pentru homepage
    breaking_news.cache:
      default_lifetime: 60        # 1 minut pentru breaking news
```

### 2.2 Elasticsearch Fix (10h)

| Task | Severitate | Efort | Status |
|------|-----------|-------|--------|
| Fix autentificare Elasticsearch | 🔴 Critical | 3h | ⬜ |
| Regenerare certificate SSL | 🔴 Critical | 2h | ⬜ |
| Reindexare conținut | 🟡 High | 2h | ⬜ |
| Testare search functionality | 🟡 High | 3h | ⬜ |

**Comenzi:**
```bash
# Verificare status
curl -k -u elastic:password https://localhost:9200/_cluster/health

# Reindexare
symfony console app:elasticsearch:create-index --force
symfony console app:elasticsearch:index-articles
```

### 2.3 Database Optimization (10h)

| Task | Severitate | Efort | Status |
|------|-----------|-------|--------|
| Configurare connection timeout | 🔴 Critical | 2h | ⬜ |
| Optimizare slow queries (>100ms) | 🟡 High | 4h | ⬜ |
| Adăugare query result cache | 🟡 Medium | 2h | ⬜ |
| Implementare read replica (pregătire) | 🟢 Low | 2h | ⬜ |

**Configurare doctrine.yaml:**
```yaml
doctrine:
  dbal:
    options:
      'connect_timeout': 5
      'statement_timeout': '30s'
  orm:
    second_level_cache:
      enabled: true
      region_cache_driver:
        type: pool
        pool: doctrine.second_level_cache_pool
```

### Deliverables Sprint 2
- [ ] Cache hit rate >60% (target final: 80%)
- [ ] Elasticsearch funcțional cu search activ
- [ ] Connection timeouts configurate
- [ ] Cache warming automatizat
- [ ] Grafana dashboards pentru cache metrics

---

## Sprint 3: Teste & Calitate (Săptămâna 3-4)

**Prioritate:** HIGH
**Efort:** 40-50 ore
**Obiectiv:** Creșterea test coverage și stabilității

### 3.1 Frontend Test Coverage (25h)

| Task | Severitate | Efort | Status |
|------|-----------|-------|--------|
| Setup Jest cu coverage reporting | 🟡 High | 2h | ⬜ |
| Teste componente critice (Hero, Cards, Navigation) | 🔴 Critical | 8h | ⬜ |
| Teste hooks și utilities | 🟡 High | 5h | ⬜ |
| Teste integrare API | 🟡 High | 5h | ⬜ |
| Teste E2E flows critice | 🟡 High | 5h | ⬜ |

**Target:** Coverage de la 1.29% la minim 40% (realist pentru sprint)

**Componente prioritare pentru teste:**
1. `components/article/ArticleCard.tsx`
2. `components/layout/Header.tsx`
3. `components/layout/Navigation.tsx`
4. `app/[locale]/page.tsx` (Homepage)
5. `lib/api/articles.ts`

### 3.2 Backend Test Enhancement (15h)

| Task | Severitate | Efort | Status |
|------|-----------|-------|--------|
| Adăugare teste pentru State Providers | 🟡 High | 5h | ⬜ |
| Teste pentru cache invalidation | 🟡 High | 3h | ⬜ |
| Teste pentru Elasticsearch service | 🟡 High | 3h | ⬜ |
| Teste pentru Image processing | 🟡 Medium | 2h | ⬜ |
| Integration tests API cu fixtures | 🟡 Medium | 2h | ⬜ |

**Status curent:** 376 teste OK - adăugăm ~50 teste noi

### 3.3 CI/CD Pipeline (10h)

| Task | Severitate | Efort | Status |
|------|-----------|-------|--------|
| GitHub Actions workflow pentru tests | 🟡 High | 4h | ⬜ |
| Lint checks în CI | 🟡 High | 2h | ⬜ |
| Coverage reporting în CI | 🟡 Medium | 2h | ⬜ |
| Deploy automation (staging) | 🟢 Medium | 2h | ⬜ |

**GitHub Actions exemplu:**
```yaml
# .github/workflows/ci.yml
name: CI
on: [push, pull_request]
jobs:
  backend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - run: composer install
      - run: vendor/bin/phpstan analyse
      - run: vendor/bin/phpunit

  frontend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: pnpm/action-setup@v2
      - run: pnpm install
      - run: pnpm lint
      - run: pnpm test --coverage
```

### Deliverables Sprint 3
- [ ] Frontend coverage >40%
- [ ] Backend 420+ teste
- [ ] CI/CD pipeline funcțional
- [ ] Automatic lint checks
- [ ] Coverage reports în PR

---

## Sprint 4: SEO, Business & Polish (Săptămâna 5-6)

**Prioritate:** MEDIUM-HIGH
**Efort:** 35-45 ore
**Obiectiv:** Pregătire finală pentru lansare

### 4.1 SEO Completare (15h)

| Task | Severitate | Efort | Status |
|------|-----------|-------|--------|
| Adăugare logo.png și favicon-uri | 🔴 Critical | 2h | ⬜ |
| Configurare Google Search Console verification | 🟡 High | 1h | ⬜ |
| Configurare Bing Webmaster Tools | 🟡 Medium | 1h | ⬜ |
| Optimizare imagini (3 aspect ratios) | 🟡 High | 4h | ⬜ |
| Verificare și fix schema.org markup | 🟡 High | 3h | ⬜ |
| Test Telegram Instant View | 🟡 Medium | 2h | ⬜ |
| Verificare Core Web Vitals | 🟡 High | 2h | ⬜ |

**Imagini necesare:**
```
public/
├── logo.png (400x400, pentru schema.org)
├── favicon.ico
├── favicon-16x16.png
├── favicon-32x32.png
├── apple-touch-icon.png (180x180)
└── og-image.png (1200x630)
```

### 4.2 Analytics & Monitoring (10h)

| Task | Severitate | Efort | Status |
|------|-----------|-------|--------|
| Integrare Google Analytics 4 | 🔴 Critical | 2h | ⬜ |
| Integrare Plausible/alternativă privacy-friendly | 🟡 Medium | 2h | ⬜ |
| Setup error tracking (Sentry) | 🟡 High | 3h | ⬜ |
| Configurare alerting în Grafana | 🟡 Medium | 3h | ⬜ |

### 4.3 Content & Features (10h)

| Task | Severitate | Efort | Status |
|------|-----------|-------|--------|
| Activare search pe frontend | 🔴 Critical | 4h | ⬜ |
| Verificare și fix language switcher | 🟡 High | 2h | ⬜ |
| Optimizare author pages | 🟡 Medium | 2h | ⬜ |
| Review și fix broken links | 🟡 Medium | 2h | ⬜ |

### 4.4 Content Strategy (Paralel)

| Task | Responsabil | Timeline |
|------|-------------|----------|
| Plan import conținut (target: 500+ articole) | Editorial | 2-4 săptămâni |
| Configurare RSS feeds pentru import | Dev | 4h |
| Setup social media sharing | Dev | 2h |

### Deliverables Sprint 4
- [ ] Logo și favicon-uri configurate
- [ ] GA4 activ cu tracking
- [ ] Search funcțional pe site
- [ ] Core Web Vitals green (>90)
- [ ] Error tracking activ
- [ ] Toate verificările SEO passed

---

## Checklist Pre-Launch

### Securitate
- [ ] Toate secretele rotite
- [ ] JWT keys noi generate
- [ ] Elasticsearch SSL valid
- [ ] CORS restrictiv
- [ ] Rate limiting activ
- [ ] Security headers configurate
- [ ] Git history curățat de secrete

### Performance
- [ ] Cache hit rate >80%
- [ ] API response <200ms (cached)
- [ ] LCP <2.5s
- [ ] INP <200ms
- [ ] CLS <0.1
- [ ] No N+1 queries

### Calitate
- [ ] Zero erori ESLint
- [ ] Zero console.log
- [ ] PHPStan level 5 passed
- [ ] Test coverage frontend >40%
- [ ] Backend 400+ tests passed
- [ ] CI/CD pipeline activ

### SEO & Business
- [ ] Google Analytics activ
- [ ] Search Console verificat
- [ ] Sitemap valid
- [ ] Schema.org complet
- [ ] Favicon-uri toate dimensiunile
- [ ] OG images configurate

### Monitoring
- [ ] Prometheus metrics activ
- [ ] Grafana dashboards
- [ ] Error tracking (Sentry)
- [ ] Alerting configurat
- [ ] Log aggregation

---

## Estimare Efort Total

| Sprint | Efort (ore) | Durată | Focus |
|--------|-------------|--------|-------|
| Sprint 1 | 45h | 1-1.5 săpt | Securitate & Cleanup |
| Sprint 2 | 40h | 1-1.5 săpt | Performance & Cache |
| Sprint 3 | 50h | 1.5 săpt | Teste & CI/CD |
| Sprint 4 | 40h | 1-1.5 săpt | SEO & Polish |
| **TOTAL** | **175h** | **5-6 săpt** | |

**Echipă recomandată:**
- 1 Backend Developer (senior)
- 1 Frontend Developer (senior)
- 1 DevOps (part-time)
- 1 QA Engineer (Sprint 3-4)

---

## Riscuri și Mitigare

| Risc | Impact | Probabilitate | Mitigare |
|------|--------|---------------|----------|
| Git history cleanup problematic | High | Medium | Backup complet înainte, comunicare echipă |
| Elasticsearch migration issues | Medium | Medium | Testing pe staging, rollback plan |
| Test coverage target missed | Medium | Low | Focus pe componente critice |
| Content gap (81 vs 500 articles) | High | High | Plan import paralel, RSS automation |
| Cache invalidation bugs | Medium | Medium | Testing extensiv, feature flags |

---

## Success Metrics

### Target Scoruri Post-Implementation

| Domeniu | Curent | Target | Îmbunătățire |
|---------|--------|--------|--------------|
| Backend | 7.5/10 | 9.0/10 | +20% |
| Frontend | 7.2/10 | 8.5/10 | +18% |
| Database | 8.5/10 | 9.5/10 | +12% |
| SEO | 7.5/10 | 9.0/10 | +20% |
| Securitate | 6.5/10 | 9.0/10 | +38% |
| Business | 6.5/10 | 8.0/10 | +23% |
| **MEDIA** | **7.3/10** | **8.8/10** | **+21%** |

### KPIs de Monitorizat

| Metric | Target | Măsurare |
|--------|--------|----------|
| Cache Hit Rate | >80% | Redis metrics |
| API Latency (p95) | <200ms | Prometheus |
| Error Rate | <0.1% | Sentry |
| Test Coverage (FE) | >40% | Jest |
| Lighthouse Score | >90 | PageSpeed |
| Uptime | >99.9% | Grafana |

---

## Următorii Pași Imediați

1. **URGENT (Astăzi)**:
   - Backup complet al repository-ului
   - Documentare toate secretele curente
   - Inițiere rotire JWT keys

2. **Săptămâna Aceasta**:
   - Începere Sprint 1 (Securitate)
   - Setup branch feature/production-ready
   - Alocare resurse echipă

3. **Review Points**:
   - Demo Sprint 1: Sfârșitul săptămânii 1.5
   - Demo Sprint 2: Sfârșitul săptămânii 3
   - Demo Sprint 3: Sfârșitul săptămânii 4.5
   - Launch Review: Sfârșitul săptămânii 6

---

**Document generat automat pe baza auditurilor din 2025-12-01**
**Menținut de:** Development Team
**Ultima actualizare:** 2025-12-01
