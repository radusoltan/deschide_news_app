# Plan Complet de Restructurare Documentație - Deschide News App

**Data creării**: 5 Noiembrie 2025
**Status**: 📋 Planificat
**Autor**: Analiză automatizată cu Claude Code
**Versiune**: 1.0

---

## 📊 REZUMAT EXECUTIV

### Situația Actuală
Aplicația **Deschide News** a acumulat **~88 documente markdown** în timpul dezvoltării, dintre care:
- **~50 fișiere** sunt rapoarte de execuție și sprint-uri (necesită arhivare)
- **~38 fișiere** sunt documentație activă (necesită reorganizare)
- Documentația este dispersată în 4 locații: root, docs/, backend/, frontend/

### Obiective
1. **Root** - Documentație setup general + arhitectură globală
2. **Backend** - Documentație tehnică backend (API, entități, servicii)
3. **Frontend** - Documentație tehnică frontend (components, routing, UI)
4. **Arhivă** - Păstrarea rapoartelor istorice organizate cronologic

### Beneficii
- ✅ Claritate: Separare documentație activă vs. rapoarte istorice
- ✅ Structură logică: Organizare pe categorii (infrastructure, features, architecture)
- ✅ Mentenanță ușoară: Fiecare folder cu README.md index
- ✅ Onboarding rapid: Documentație esențială ușor de găsit
- ✅ Istoric păstrat: Nimic nu se pierde, doar se arhivează

---

## 📈 STATISTICI INVENTAR

### Distribuție Documente

| Locație | Total | Păstrare | Arhivare | Nou |
|---------|-------|----------|----------|-----|
| **Root** | 7 | 2 | 5 | 2 noi |
| **docs/** | 31 | 15 | 16 | - |
| **backend/** | 26 | 2 | 24 | - |
| **backend/docs/** | 13 | 8 | 5 | - |
| **frontend/** | 11 | 11 | 0 | - |
| **TOTAL** | **88** | **38** | **50** | **2** |

### Categorii Arhivare

| Categorie | Număr Fișiere |
|-----------|---------------|
| Rapoarte Sprint | 25 |
| Planuri Vechi | 6 |
| Rapoarte Zilnice | 13 |
| Documente Audit | 6 |
| **TOTAL ARHIVARE** | **50** |

---

## 🗂️ INVENTAR COMPLET & CLASIFICARE

### 1. ROOT `/var/www/deschide_news_app/` (7 fișiere)

| Fișier | Tip | Acțiune | Motivație |
|--------|-----|---------|-----------|
| `CLAUDE.md` | Instrucțiuni | ✅ **PĂSTRARE** | Necesar pentru Claude Code |
| `README.md` | Documentație | ✅ **PĂSTRARE** + UPDATE | README principal |
| `RAPORT_AUDIT_TEHNIC.md` | Raport | 📦 **ARHIVARE** | Raport generat 3 Nov 2025 |
| `INSTRUCTIUNI_RAPORT_ANALYTICS.md` | Instrucțiuni | 📦 **ARHIVARE** | Instrucțiuni specifice GA4 |
| `audit_prepare.md` | Plan | 📦 **ARHIVARE** | Plan vechi de audit |
| `git_repos_setup.md` | Instrucțiuni | 📦 **ARHIVARE** | Instrucțiuni git temporare |
| `DOCUMENTATION_GUIDE.md` | Ghid | 🔄 **INTEGRARE în README** | Conținut de integrat |

**Noi de creat:**
- ⚡ `SETUP.md` - Setup complet (combinat din multiple surse)
- ⚡ `ARCHITECTURE.md` - Arhitectura generală (backend + frontend + infra)

---

### 2. ROOT/docs/ (31 fișiere)

#### A. Documente de Păstrat (15 fișiere)

| Fișier | Categorie | Destinație Nouă |
|--------|-----------|-----------------|
| `README-DOCS.md` | Index | `docs/README.md` |
| `redis-schema.md` | Infrastructure | `docs/infrastructure/redis-schema.md` |
| `statistics-schema.md` | Infrastructure | `docs/infrastructure/statistics-schema.md` |
| `cron-setup.md` | Infrastructure | `docs/infrastructure/cron-setup.md` |
| `monitoring-guide.md` | Infrastructure | `docs/infrastructure/monitoring-guide.md` |
| `admin-stats-api.md` | Feature API | `docs/features/performance-analytics/admin-stats-api.md` |
| `performance-analytics-COMPLETE.md` | Feature | `docs/features/performance-analytics/COMPLETE.md` |
| `QUICKSTART-PERFORMANCE-ANALYTICS.md` | Feature | `docs/features/performance-analytics/README.md` |
| `ADVANCED_ANALYTICS_GUIDE.md` | Feature | `docs/features/performance-analytics/ADVANCED.md` |
| `PHASE6_IMPLEMENTATION_SUMMARY.md` | Planning | `docs/planning/PHASE6_IMPLEMENTATION_SUMMARY.md` |
| `live-text-liveblog-analysis.md` | Feature | `docs/features/live-text/analysis.md` |
| `live-text-roadmap.md` | Feature | `docs/features/live-text/roadmap.md` |
| `archive-import-strategy.md` | Feature | `docs/features/archive-import/strategy.md` |
| `archive-import-sources-analysis.md` | Feature | `docs/features/archive-import/sources-analysis.md` |
| `tags-keywords-implementation-plan.md` | Planning | `docs/planning/tags-keywords-implementation-plan.md` |

#### B. Rapoarte de Arhivat (16 fișiere)

| Fișier | Destinație Arhivă |
|--------|-------------------|
| `DECIZIE_5_TRANSLITERATION_ANALYSIS.md` | `archive/2025-11-docs-decisions/` |
| `URL_STRUCTURE_IMPLEMENTATION_STATUS.md` | `archive/2025-11-docs-decisions/` |
| `sprint-6-testing-deployment.md` | `archive/2025-11-docs-sprints/` |
| `sprint-6-completion-summary.md` | `archive/2025-11-docs-sprints/` |
| `sprint-10-analytics-implementation.md` | `archive/2025-11-docs-sprints/` |
| `sprint-10-complete-summary.md` | `archive/2025-11-docs-sprints/` |
| `live-text-sprint1-completed.md` | `archive/2025-11-docs-sprints/` |
| `live-text-sprint2-completed.md` | `archive/2025-11-docs-sprints/` |
| `live-text-sprint3-completed.md` | `archive/2025-11-docs-sprints/` |
| `live-text-sprint4-completed.md` | `archive/2025-11-docs-sprints/` |
| `live-text-sprint5-completed.md` | `archive/2025-11-docs-sprints/` |
| `live-text-sprint6-completed.md` | `archive/2025-11-docs-sprints/` |
| `live-text-sprint7-completed.md` | `archive/2025-11-docs-sprints/` |
| `live-text-sprint8-completed.md` | `archive/2025-11-docs-sprints/` |
| `live-text-sprint9-completed.md` | `archive/2025-11-docs-sprints/` |

---

### 3. deschide_backend/ ROOT (26 fișiere)

#### A. Documente de Păstrat (2 fișiere)

| Fișier | Acțiune |
|--------|---------|
| `CLAUDE.md` | ✅ **PĂSTRARE** |
| `README.md` | ✅ **PĂSTRARE** + UPDATE |

#### B. Planuri de Arhivat (6 fișiere)

| Fișier | Destinație Arhivă |
|--------|-------------------|
| `CLOUDFLARE_SETUP.md` | `deschide_backend/archive/2025-11-plans/` |
| `LOAD_TESTING_PLAN.md` | `deschide_backend/archive/2025-11-plans/` |
| `MONITORING_SETUP_PLAN.md` | `deschide_backend/archive/2025-11-plans/` |
| `OPTIMIZATION_PLAN.md` | `deschide_backend/archive/2025-11-plans/` |
| `PGBOUNCER_SETUP.md` | `deschide_backend/archive/2025-11-plans/` |
| `VARNISH_SETUP.md` | `deschide_backend/archive/2025-11-plans/` |

#### C. Rapoarte de Arhivat (18 fișiere)

| Categorie | Fișiere | Destinație Arhivă |
|-----------|---------|-------------------|
| Sprint 1 Reports | 4 fișiere (`SPRINT_1_DAY_*.md`) | `deschide_backend/archive/2025-11-sprint-reports/sprint-1/` |
| Sprint 3 Reports | 7 fișiere (`SPRINT_3_*.md`) | `deschide_backend/archive/2025-11-sprint-reports/sprint-3/` |
| Week Reports | 6 fișiere (`WEEK_*.md`) | `deschide_backend/archive/2025-11-sprint-reports/weeks/` |

**Lista completă Sprint 3:**
- `SPRINT_3_CACHE_OPTIMIZATION_REPORT.md`
- `SPRINT_3_DATABASE_OPTIMIZATION_REPORT.md`
- `SPRINT_3_ELASTICSEARCH_OPTIMIZATION_REPORT.md`
- `SPRINT_3_IMAGE_OPTIMIZATION_REPORT.md`
- `SPRINT_3_PERFORMANCE_PROFILING_REPORT.md`
- `SPRINT_3_PHPSTAN_REPORT.md`
- `SPRINT_3_PHP_CS_FIXER_REPORT.md`
- `SPRINT_3_SECURITY_AUDIT_REPORT.md`

---

### 4. deschide_backend/docs/ (13 fișiere)

#### A. Documentație Tehnică de Păstrat (8 fișiere)

| Fișier | Categorie | Destinație Nouă |
|--------|-----------|-----------------|
| `README.md` | Index | ✅ PĂSTRARE + UPDATE |
| `auth_flow.md` | Architecture | `docs/architecture/auth_flow.md` |
| `entities.md` | Architecture | `docs/architecture/entities.md` |
| `multilanguage_model.md` | Architecture | `docs/architecture/multilanguage_model.md` |
| `roles_permissions.md` | Architecture | `docs/architecture/roles_permissions.md` |
| `media_service.md` | Services | `docs/services/media_service.md` |
| `workflow_articles.md` | Services | `docs/services/workflow_articles.md` |
| `api-platform/testing-with-symfony.md` | Testing | `docs/testing/api-platform/testing-with-symfony.md` |

#### B. Documente Audit de Arhivat (5 fișiere)

| Fișier | Destinație Arhivă |
|--------|-------------------|
| `AUDIT_SCOPE.md` | `deschide_backend/archive/2025-11-audit/` |
| `BUSINESS_OVERVIEW.md` | `deschide_backend/archive/2025-11-audit/` |
| `audit_checklist.md` | `deschide_backend/archive/2025-11-audit/` |
| `audit_prepare.md` | `deschide_backend/archive/2025-11-audit/` |
| `sprint-1-completion-report.md` | `deschide_backend/archive/2025-11-sprint-reports/sprint-1/` |

---

### 5. deschide_frontend/ (11 fișiere)

#### Toate Documentele de Păstrat (11 fișiere)

| Fișier | Destinație Nouă |
|--------|-----------------|
| `README.md` | ✅ PĂSTRARE + UPDATE |
| `CODE_SPLITTING_GUIDE.md` | `docs/features/CODE_SPLITTING_GUIDE.md` |
| `SPORT_FEATURES_GUIDE.md` | `docs/features/SPORT_FEATURES_GUIDE.md` |
| `SOCIAL_MEDIA_INTEGRATION_GUIDE.md` | `docs/features/SOCIAL_MEDIA_INTEGRATION_GUIDE.md` |
| `EMBED_CAPABILITY_GUIDE.md` | `docs/features/EMBED_CAPABILITY_GUIDE.md` |
| `docs/IMAGE_MANAGEMENT_IMPLEMENTATION_PLAN.md` | `docs/features/IMAGE_MANAGEMENT_IMPLEMENTATION_PLAN.md` |
| `docs/api_integration.md` | `docs/setup/api_integration.md` |
| `docs/i18n_config.md` | `docs/setup/i18n_config.md` |
| `docs/auth_ui.md` | `docs/ui/auth_ui.md` |
| `docs/routes_public.md` | `docs/routing/routes_public.md` |
| `docs/routes_admin.md` | `docs/routing/routes_admin.md` |
| `docs/README.md` | ✅ PĂSTRARE + UPDATE |

**Notă**: Frontend nu are rapoarte de arhivat - toată documentația este relevantă și activă.

---

## 🎯 STRUCTURA PROPUSĂ (TREE VIEW)

```
/var/www/deschide_news_app/
│
├── CLAUDE.md                           # ✅ Instrucțiuni generale Claude Code
├── README.md                           # ⚡ UPDATE: Overview general, link-uri către sub-proiecte
├── SETUP.md                            # ⚡ NOU: Setup complet (dev + production)
├── ARCHITECTURE.md                     # ⚡ NOU: Arhitectura generală (backend + frontend + infra)
├── DOCUMENTATION_RESTRUCTURING_PLAN.md # ✅ Acest document
│
├── archive/                            # ⚡ NOU: Folder pentru documentație veche
│   ├── README.md                      # Index arhivă cu descrieri
│   │
│   ├── 2025-11-root/                  # Arhivă din root
│   │   ├── RAPORT_AUDIT_TEHNIC.md
│   │   ├── INSTRUCTIUNI_RAPORT_ANALYTICS.md
│   │   ├── audit_prepare.md
│   │   ├── git_repos_setup.md
│   │   └── DOCUMENTATION_GUIDE.md
│   │
│   └── 2025-11-docs-sprints/          # Arhivă rapoarte sprint din docs/
│       ├── README.md                  # Index cu descrieri
│       ├── sprint-6-completion-summary.md
│       ├── sprint-6-testing-deployment.md
│       ├── sprint-10-analytics-implementation.md
│       ├── sprint-10-complete-summary.md
│       ├── live-text-sprint1-completed.md
│       ├── live-text-sprint2-completed.md
│       ├── live-text-sprint3-completed.md
│       ├── live-text-sprint4-completed.md
│       ├── live-text-sprint5-completed.md
│       ├── live-text-sprint6-completed.md
│       ├── live-text-sprint7-completed.md
│       ├── live-text-sprint8-completed.md
│       └── live-text-sprint9-completed.md
│
├── docs/                              # Documentație cross-cutting (afectează ambele)
│   ├── README.md                      # ⚡ UPDATE: Index curat, structurat
│   │
│   ├── infrastructure/                # ⚡ NOU: Infrastructură partajată
│   │   ├── redis-schema.md
│   │   ├── statistics-schema.md
│   │   ├── cron-setup.md
│   │   └── monitoring-guide.md
│   │
│   ├── features/                      # ⚡ NOU: Documentație features cross-cutting
│   │   │
│   │   ├── performance-analytics/
│   │   │   ├── README.md             # REDENUMIRE din QUICKSTART-PERFORMANCE-ANALYTICS.md
│   │   │   ├── COMPLETE.md           # REDENUMIRE din performance-analytics-COMPLETE.md
│   │   │   ├── ADVANCED.md           # REDENUMIRE din ADVANCED_ANALYTICS_GUIDE.md
│   │   │   └── admin-stats-api.md
│   │   │
│   │   ├── live-text/
│   │   │   ├── README.md             # ⚡ NOU: Overview + quick links
│   │   │   ├── roadmap.md
│   │   │   └── analysis.md           # REDENUMIRE din live-text-liveblog-analysis.md
│   │   │
│   │   └── archive-import/
│   │       ├── strategy.md
│   │       └── sources-analysis.md
│   │
│   └── planning/                      # ⚡ NOU: Planuri active
│       ├── tags-keywords-implementation-plan.md
│       └── PHASE6_IMPLEMENTATION_SUMMARY.md
│
├── deschide_backend/
│   ├── CLAUDE.md                      # ✅ Instrucțiuni backend pentru Claude
│   ├── README.md                      # ⚡ UPDATE: Setup backend, comenzi comune
│   │
│   ├── archive/                       # ⚡ NOU: Arhivă backend
│   │   ├── README.md                  # Index arhivă backend
│   │   │
│   │   ├── 2025-11-plans/            # Planuri vechi
│   │   │   ├── CLOUDFLARE_SETUP.md
│   │   │   ├── LOAD_TESTING_PLAN.md
│   │   │   ├── MONITORING_SETUP_PLAN.md
│   │   │   ├── OPTIMIZATION_PLAN.md
│   │   │   ├── PGBOUNCER_SETUP.md
│   │   │   └── VARNISH_SETUP.md
│   │   │
│   │   ├── 2025-11-sprint-reports/   # Rapoarte sprint
│   │   │   ├── README.md             # Index rapoarte
│   │   │   │
│   │   │   ├── sprint-1/
│   │   │   │   ├── SPRINT_1_DAY_1-2_REPORT.md
│   │   │   │   ├── SPRINT_1_DAY_3_REPORT.md
│   │   │   │   ├── SPRINT_1_DAY_4_REPORT.md
│   │   │   │   ├── SPRINT_1_DAY_5_REPORT.md
│   │   │   │   └── sprint-1-completion-report.md
│   │   │   │
│   │   │   ├── sprint-3/
│   │   │   │   ├── SPRINT_3_CACHE_OPTIMIZATION_REPORT.md
│   │   │   │   ├── SPRINT_3_DATABASE_OPTIMIZATION_REPORT.md
│   │   │   │   ├── SPRINT_3_ELASTICSEARCH_OPTIMIZATION_REPORT.md
│   │   │   │   ├── SPRINT_3_IMAGE_OPTIMIZATION_REPORT.md
│   │   │   │   ├── SPRINT_3_PERFORMANCE_PROFILING_REPORT.md
│   │   │   │   ├── SPRINT_3_PHPSTAN_REPORT.md
│   │   │   │   ├── SPRINT_3_PHP_CS_FIXER_REPORT.md
│   │   │   │   └── SPRINT_3_SECURITY_AUDIT_REPORT.md
│   │   │   │
│   │   │   └── weeks/
│   │   │       ├── WEEK_1_OPTIMIZATIONS_REPORT.md
│   │   │       ├── WEEK_2_DAY_1_VARNISH_REPORT.md
│   │   │       ├── WEEK_2_DAY_2_CLOUDFLARE_REPORT.md
│   │   │       ├── WEEK_2_DAY_3_PGBOUNCER_REPORT.md
│   │   │       ├── WEEK_2_DAY_4_LOAD_TESTING_REPORT.md
│   │   │       └── WEEK_2_FINAL_REPORT.md
│   │   │
│   │   └── 2025-11-audit/            # Documente audit
│   │       ├── AUDIT_SCOPE.md
│   │       ├── BUSINESS_OVERVIEW.md
│   │       ├── audit_checklist.md
│   │       └── audit_prepare.md
│   │
│   ├── docs/                          # Documentație backend ACTIVĂ
│   │   ├── README.md                  # ⚡ UPDATE: Index curat
│   │   │
│   │   ├── architecture/              # ⚡ NOU: Arhitectură backend
│   │   │   ├── entities.md
│   │   │   ├── auth_flow.md
│   │   │   ├── multilanguage_model.md
│   │   │   └── roles_permissions.md
│   │   │
│   │   ├── services/                  # ⚡ NOU: Servicii
│   │   │   ├── media_service.md
│   │   │   └── workflow_articles.md
│   │   │
│   │   └── testing/                   # ⚡ NOU: Testing
│   │       └── api-platform/
│   │           └── testing-with-symfony.md
│   │
│   ├── config/
│   ├── migrations/
│   ├── public/
│   ├── src/
│   ├── tests/
│   ├── var/
│   ├── vendor/
│   └── composer.json
│
└── deschide_frontend/
    ├── README.md                      # ⚡ UPDATE: Setup frontend
    │
    ├── docs/                          # Documentație frontend
    │   ├── README.md                  # ⚡ UPDATE: Index curat
    │   │
    │   ├── setup/                     # ⚡ NOU: Setup & Config
    │   │   ├── api_integration.md
    │   │   └── i18n_config.md
    │   │
    │   ├── features/                  # ⚡ NOU: Features frontend
    │   │   ├── CODE_SPLITTING_GUIDE.md
    │   │   ├── SPORT_FEATURES_GUIDE.md
    │   │   ├── SOCIAL_MEDIA_INTEGRATION_GUIDE.md
    │   │   ├── EMBED_CAPABILITY_GUIDE.md
    │   │   └── IMAGE_MANAGEMENT_IMPLEMENTATION_PLAN.md
    │   │
    │   ├── ui/                        # ⚡ NOU: UI Components
    │   │   └── auth_ui.md
    │   │
    │   └── routing/                   # ⚡ NOU: Routing
    │       ├── routes_public.md
    │       └── routes_admin.md
    │
    ├── app/
    ├── public/
    ├── node_modules/
    └── package.json
```

---

## 📋 PLAN DE EXECUȚIE

### FAZA 1: PREGĂTIRE (Fără Mutări) ✅

**Obiectiv**: Validare plan și backup

| # | Task | Descriere | Status |
|---|------|-----------|--------|
| 1.1 | Creare raport analiză | Creare DOCUMENTATION_RESTRUCTURING_PLAN.md | ✅ COMPLET |
| 1.2 | Revizie utilizator | Obținere aprobare de la utilizator | ⏳ În așteptare |
| 1.3 | Backup complet | Backup înainte de orice mutare | ⏳ Pending |

**Comenzi backup:**
```bash
# Backup complet proiect
tar -czf ~/backups/deschide_news_app_backup_$(date +%Y%m%d_%H%M%S).tar.gz \
  /var/www/deschide_news_app \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='var/cache'

# Verificare backup
ls -lh ~/backups/
```

---

### FAZA 2: CREARE STRUCTURĂ FOLDERE

**Obiectiv**: Creare toate folderele necesare pentru noua structură

| # | Task | Locație | Foldere de Creat |
|---|------|---------|------------------|
| 2.1 | Archive root | `/var/www/deschide_news_app/` | `archive/2025-11-root/`, `archive/2025-11-docs-sprints/` |
| 2.2 | Docs structure | `/var/www/deschide_news_app/docs/` | `infrastructure/`, `features/performance-analytics/`, `features/live-text/`, `features/archive-import/`, `planning/` |
| 2.3 | Backend archive | `deschide_backend/` | `archive/2025-11-plans/`, `archive/2025-11-sprint-reports/sprint-1/`, `archive/2025-11-sprint-reports/sprint-3/`, `archive/2025-11-sprint-reports/weeks/`, `archive/2025-11-audit/` |
| 2.4 | Backend docs | `deschide_backend/docs/` | `architecture/`, `services/`, `testing/api-platform/` |
| 2.5 | Frontend docs | `deschide_frontend/docs/` | `setup/`, `features/`, `ui/`, `routing/` |

**Comenzi:**
```bash
# Root archive
mkdir -p /var/www/deschide_news_app/archive/{2025-11-root,2025-11-docs-sprints}

# Docs structure
mkdir -p /var/www/deschide_news_app/docs/{infrastructure,features/{performance-analytics,live-text,archive-import},planning}

# Backend archive
mkdir -p /var/www/deschide_news_app/deschide_backend/archive/{2025-11-plans,2025-11-sprint-reports/{sprint-1,sprint-3,weeks},2025-11-audit}

# Backend docs
mkdir -p /var/www/deschide_news_app/deschide_backend/docs/{architecture,services,testing/api-platform}

# Frontend docs
mkdir -p /var/www/deschide_news_app/deschide_frontend/docs/{setup,features,ui,routing}
```

---

### FAZA 3: ARHIVARE DOCUMENTE VECHI

**Obiectiv**: Mutare rapoarte și documente vechi în foldere archive

#### 3.1 Arhivare Root (5 fișiere)

```bash
cd /var/www/deschide_news_app

# Mutare în arhivă
mv RAPORT_AUDIT_TEHNIC.md archive/2025-11-root/
mv INSTRUCTIUNI_RAPORT_ANALYTICS.md archive/2025-11-root/
mv audit_prepare.md archive/2025-11-root/
mv git_repos_setup.md archive/2025-11-root/
mv DOCUMENTATION_GUIDE.md archive/2025-11-root/
```

#### 3.2 Arhivare Rapoarte Sprint din docs/ (14 fișiere)

```bash
cd /var/www/deschide_news_app/docs

# Mutare sprint reports
mv sprint-6-testing-deployment.md ../archive/2025-11-docs-sprints/
mv sprint-6-completion-summary.md ../archive/2025-11-docs-sprints/
mv sprint-10-analytics-implementation.md ../archive/2025-11-docs-sprints/
mv sprint-10-complete-summary.md ../archive/2025-11-docs-sprints/

# Mutare live-text sprint reports
mv live-text-sprint1-completed.md ../archive/2025-11-docs-sprints/
mv live-text-sprint2-completed.md ../archive/2025-11-docs-sprints/
mv live-text-sprint3-completed.md ../archive/2025-11-docs-sprints/
mv live-text-sprint4-completed.md ../archive/2025-11-docs-sprints/
mv live-text-sprint5-completed.md ../archive/2025-11-docs-sprints/
mv live-text-sprint6-completed.md ../archive/2025-11-docs-sprints/
mv live-text-sprint7-completed.md ../archive/2025-11-docs-sprints/
mv live-text-sprint8-completed.md ../archive/2025-11-docs-sprints/
mv live-text-sprint9-completed.md ../archive/2025-11-docs-sprints/

# Mutare alte documente
mv DECIZIE_5_TRANSLITERATION_ANALYSIS.md ../archive/2025-11-docs-sprints/
mv URL_STRUCTURE_IMPLEMENTATION_STATUS.md ../archive/2025-11-docs-sprints/
```

#### 3.3 Arhivare Backend Plans (6 fișiere)

```bash
cd /var/www/deschide_news_app/deschide_backend

mv CLOUDFLARE_SETUP.md archive/2025-11-plans/
mv LOAD_TESTING_PLAN.md archive/2025-11-plans/
mv MONITORING_SETUP_PLAN.md archive/2025-11-plans/
mv OPTIMIZATION_PLAN.md archive/2025-11-plans/
mv PGBOUNCER_SETUP.md archive/2025-11-plans/
mv VARNISH_SETUP.md archive/2025-11-plans/
```

#### 3.4 Arhivare Backend Sprint Reports (18 fișiere)

```bash
cd /var/www/deschide_news_app/deschide_backend

# Sprint 1 reports
mv SPRINT_1_DAY_1-2_REPORT.md archive/2025-11-sprint-reports/sprint-1/
mv SPRINT_1_DAY_3_REPORT.md archive/2025-11-sprint-reports/sprint-1/
mv SPRINT_1_DAY_4_REPORT.md archive/2025-11-sprint-reports/sprint-1/
mv SPRINT_1_DAY_5_REPORT.md archive/2025-11-sprint-reports/sprint-1/

# Sprint 3 reports
mv SPRINT_3_CACHE_OPTIMIZATION_REPORT.md archive/2025-11-sprint-reports/sprint-3/
mv SPRINT_3_DATABASE_OPTIMIZATION_REPORT.md archive/2025-11-sprint-reports/sprint-3/
mv SPRINT_3_ELASTICSEARCH_OPTIMIZATION_REPORT.md archive/2025-11-sprint-reports/sprint-3/
mv SPRINT_3_IMAGE_OPTIMIZATION_REPORT.md archive/2025-11-sprint-reports/sprint-3/
mv SPRINT_3_PERFORMANCE_PROFILING_REPORT.md archive/2025-11-sprint-reports/sprint-3/
mv SPRINT_3_PHPSTAN_REPORT.md archive/2025-11-sprint-reports/sprint-3/
mv SPRINT_3_PHP_CS_FIXER_REPORT.md archive/2025-11-sprint-reports/sprint-3/
mv SPRINT_3_SECURITY_AUDIT_REPORT.md archive/2025-11-sprint-reports/sprint-3/

# Week reports
mv WEEK_1_OPTIMIZATIONS_REPORT.md archive/2025-11-sprint-reports/weeks/
mv WEEK_2_DAY_1_VARNISH_REPORT.md archive/2025-11-sprint-reports/weeks/
mv WEEK_2_DAY_2_CLOUDFLARE_REPORT.md archive/2025-11-sprint-reports/weeks/
mv WEEK_2_DAY_3_PGBOUNCER_REPORT.md archive/2025-11-sprint-reports/weeks/
mv WEEK_2_DAY_4_LOAD_TESTING_REPORT.md archive/2025-11-sprint-reports/weeks/
mv WEEK_2_FINAL_REPORT.md archive/2025-11-sprint-reports/weeks/
```

#### 3.5 Arhivare Backend Audit Docs (5 fișiere)

```bash
cd /var/www/deschide_news_app/deschide_backend/docs

mv AUDIT_SCOPE.md ../archive/2025-11-audit/
mv BUSINESS_OVERVIEW.md ../archive/2025-11-audit/
mv audit_checklist.md ../archive/2025-11-audit/
mv audit_prepare.md ../archive/2025-11-audit/
mv sprint-1-completion-report.md ../archive/2025-11-sprint-reports/sprint-1/
```

#### 3.6 Creare README.md în Archive

Crearea de index-uri pentru fiecare folder de arhivă cu descrieri clare.

---

### FAZA 4: REORGANIZARE DOCUMENTE ACTIVE

**Obiectiv**: Mutare și redenumire documente active în structura nouă

#### 4.1 Reorganizare docs/ Infrastructure

```bash
cd /var/www/deschide_news_app/docs

mv redis-schema.md infrastructure/
mv statistics-schema.md infrastructure/
mv cron-setup.md infrastructure/
mv monitoring-guide.md infrastructure/
```

#### 4.2 Reorganizare docs/ Features - Performance Analytics

```bash
cd /var/www/deschide_news_app/docs

# Creare folder și mutare cu redenumire
mv QUICKSTART-PERFORMANCE-ANALYTICS.md features/performance-analytics/README.md
mv performance-analytics-COMPLETE.md features/performance-analytics/COMPLETE.md
mv ADVANCED_ANALYTICS_GUIDE.md features/performance-analytics/ADVANCED.md
mv admin-stats-api.md features/performance-analytics/
```

#### 4.3 Reorganizare docs/ Features - Live Text

```bash
cd /var/www/deschide_news_app/docs

mv live-text-roadmap.md features/live-text/roadmap.md
mv live-text-liveblog-analysis.md features/live-text/analysis.md

# Creare README.md pentru live-text (nou)
```

#### 4.4 Reorganizare docs/ Features - Archive Import

```bash
cd /var/www/deschide_news_app/docs

mv archive-import-strategy.md features/archive-import/strategy.md
mv archive-import-sources-analysis.md features/archive-import/sources-analysis.md
```

#### 4.5 Reorganizare docs/ Planning

```bash
cd /var/www/deschide_news_app/docs

mv tags-keywords-implementation-plan.md planning/
mv PHASE6_IMPLEMENTATION_SUMMARY.md planning/
```

#### 4.6 Reorganizare Backend Docs - Architecture

```bash
cd /var/www/deschide_news_app/deschide_backend/docs

mv entities.md architecture/
mv auth_flow.md architecture/
mv multilanguage_model.md architecture/
mv roles_permissions.md architecture/
```

#### 4.7 Reorganizare Backend Docs - Services

```bash
cd /var/www/deschide_news_app/deschide_backend/docs

mv media_service.md services/
mv workflow_articles.md services/
```

#### 4.8 Reorganizare Backend Docs - Testing

```bash
cd /var/www/deschide_news_app/deschide_backend/docs

# Folder api-platform deja există, doar mutăm folderul
mv api-platform testing/
```

#### 4.9 Reorganizare Frontend Docs - Setup

```bash
cd /var/www/deschide_news_app/deschide_frontend/docs

mv api_integration.md setup/
mv i18n_config.md setup/
```

#### 4.10 Reorganizare Frontend Docs - Features

```bash
cd /var/www/deschide_news_app/deschide_frontend

# Mutare din root în docs/features/
mv CODE_SPLITTING_GUIDE.md docs/features/
mv SPORT_FEATURES_GUIDE.md docs/features/
mv SOCIAL_MEDIA_INTEGRATION_GUIDE.md docs/features/
mv EMBED_CAPABILITY_GUIDE.md docs/features/

# Mutare din docs/ în docs/features/
mv docs/IMAGE_MANAGEMENT_IMPLEMENTATION_PLAN.md docs/features/
```

#### 4.11 Reorganizare Frontend Docs - UI

```bash
cd /var/www/deschide_news_app/deschide_frontend/docs

mv auth_ui.md ui/
```

#### 4.12 Reorganizare Frontend Docs - Routing

```bash
cd /var/www/deschide_news_app/deschide_frontend/docs

mv routes_public.md routing/
mv routes_admin.md routing/
```

---

### FAZA 5: CREARE & UPDATE README-URI

**Obiectiv**: Creare/actualizare README.md pentru navigare ușoară

#### 5.1 Documente Noi de Creat

| # | Fișier | Descriere |
|---|--------|-----------|
| 1 | `/var/www/deschide_news_app/SETUP.md` | Ghid complet setup development + production |
| 2 | `/var/www/deschide_news_app/ARCHITECTURE.md` | Arhitectura generală (backend + frontend + infra) |
| 3 | `/var/www/deschide_news_app/docs/features/live-text/README.md` | Overview Live Text cu quick links |
| 4 | `/var/www/deschide_news_app/archive/README.md` | Index arhivă root |
| 5 | `/var/www/deschide_news_app/archive/2025-11-docs-sprints/README.md` | Index rapoarte sprint |
| 6 | `/var/www/deschide_news_app/deschide_backend/archive/README.md` | Index arhivă backend |
| 7 | `/var/www/deschide_news_app/deschide_backend/archive/2025-11-sprint-reports/README.md` | Index rapoarte backend |

#### 5.2 README-uri de Actualizat

| # | Fișier | Actualizări Necesare |
|---|--------|---------------------|
| 1 | `/var/www/deschide_news_app/README.md` | Overview general, link-uri către SETUP.md, ARCHITECTURE.md, sub-proiecte |
| 2 | `/var/www/deschide_news_app/docs/README.md` | Redenumire din README-DOCS.md, restructurare index |
| 3 | `/var/www/deschide_news_app/deschide_backend/README.md` | Update link-uri documentație, comenzi comune |
| 4 | `/var/www/deschide_news_app/deschide_backend/docs/README.md` | Update index cu noua structură |
| 5 | `/var/www/deschide_news_app/deschide_frontend/README.md` | Update link-uri documentație |
| 6 | `/var/www/deschide_news_app/deschide_frontend/docs/README.md` | Update index cu noua structură |

---

### FAZA 6: UPDATE LINK-URI INTERNE

**Obiectiv**: Actualizare link-uri între documente după reorganizare

#### 6.1 Update CLAUDE.md

```bash
# Root CLAUDE.md
/var/www/deschide_news_app/CLAUDE.md
# Update link-uri către:
# - docs/ (noua structură)
# - SETUP.md
# - ARCHITECTURE.md

# Backend CLAUDE.md
/var/www/deschide_news_app/deschide_backend/CLAUDE.md
# Update link-uri către:
# - docs/architecture/
# - docs/services/
# - docs/testing/
```

#### 6.2 Update Link-uri Cross-Document

Verificare și actualizare link-uri în următoarele categorii:
1. **Infrastructure docs** → referințe către alte docs
2. **Features docs** → referințe către API docs, architecture
3. **Backend architecture docs** → referințe reciproce
4. **Frontend docs** → referințe către setup, features

**Comanda de verificare link-uri:**
```bash
# Căutare link-uri relative în toate .md files
cd /var/www/deschide_news_app
grep -r "\.md" --include="*.md" | grep -v "node_modules" | grep -v "vendor"
```

---

### FAZA 7: VALIDARE & TESTARE

**Obiectiv**: Verificare completitudine și corectitudine restructurare

| # | Task | Verificare | Tool |
|---|------|------------|------|
| 7.1 | Verificare fișiere mutate | Toate fișierele în locațiile corecte | `find`, `ls` |
| 7.2 | Verificare link-uri | Link-urile funcționează corect | `grep`, manual check |
| 7.3 | Verificare README-uri | Toate README-urile create/actualizate | Manual check |
| 7.4 | Test onboarding | Persoană nouă poate găsi documentația | Manual test |
| 7.5 | Test navigare | Navigare ușoară între documente | Manual test |

**Comenzi verificare:**
```bash
# Verificare structură foldere
tree -L 3 /var/www/deschide_news_app/archive
tree -L 3 /var/www/deschide_news_app/docs
tree -L 3 /var/www/deschide_news_app/deschide_backend/archive
tree -L 3 /var/www/deschide_news_app/deschide_backend/docs
tree -L 3 /var/www/deschide_news_app/deschide_frontend/docs

# Număr fișiere în archive
find /var/www/deschide_news_app/archive -name "*.md" | wc -l
# Așteptat: 5

find /var/www/deschide_news_app/deschide_backend/archive -name "*.md" | wc -l
# Așteptat: 29 (6 plans + 18 reports + 5 audit)

# Verificare fișiere rămase în root backend (ar trebui doar 2: CLAUDE.md, README.md)
ls /var/www/deschide_news_app/deschide_backend/*.md
```

---

## 📝 CHECKLIST POST-IMPLEMENTARE

### Verificări Tehnice

- [ ] Toate folderele `archive/` create
- [ ] Toate folderele noi `features/`, `infrastructure/`, `architecture/`, etc. create
- [ ] Toate cele 50 fișiere de arhivat mutate în locații corecte
- [ ] Toate cele 38 fișiere active reorganizate
- [ ] Fără fișiere `.md` pierdute (total 88 = arhivate + active + noi)
- [ ] `SETUP.md` și `ARCHITECTURE.md` create în root
- [ ] Toate README-urile create în foldere noi
- [ ] Toate README-urile existente actualizate
- [ ] Link-urile interne verificate și corecte
- [ ] `CLAUDE.md` actualizat cu noile link-uri

### Verificări Funcționale

- [ ] Persoană nouă poate găsi documentația de setup rapid
- [ ] Navigarea între documente este intuitivă
- [ ] Documentația backend separată clar de frontend
- [ ] Rapoartele istorice accesibile dar separate
- [ ] Fiecare folder are descriere clară (README.md)

### Verificări Git (Opțional)

- [ ] Commit cu toate mutările: `git add -A && git commit -m "docs: restructure documentation"`
- [ ] Push în repository
- [ ] Verificare că `git mv` a păstrat istoricul fișierelor

---

## 🚨 ATENȚIE & RECOMANDĂRI

### ⚠️ Înainte de Start

1. **BACKUP OBLIGATORIU**: Fă backup complet înainte de orice mutare
2. **GIT STATUS**: Verifică că nu ai modificări uncommitted importante
3. **TESTING**: Testează pe o copie înainte de a face pe producție
4. **TIME**: Estimare 2-4 ore pentru execuție completă manuală

### ✅ Best Practices

1. **Folosește `git mv`** în loc de `mv` dacă lucrezi în git (păstrează istoric)
2. **Fă commit-uri incrementale**: după fiecare fază majoră
3. **Verifică după fiecare mutare**: nu aștepta până la final
4. **Păstrează terminalul deschis**: pentru a putea face undo rapid
5. **Documentează devierile**: dacă modifici planul, notează de ce

### 🔄 Rollback Plan

Dacă ceva merge prost:

```bash
# Restaurare din backup
tar -xzf ~/backups/deschide_news_app_backup_YYYYMMDD_HHMMSS.tar.gz -C /

# SAU cu git (dacă ai committed)
git reset --hard HEAD~1  # Undo ultimul commit
git clean -fd             # Șterge fișiere untracked
```

---

## 📊 ESTIMĂRI TIMP

| Fază | Durata Estimată | Dificultate |
|------|-----------------|-------------|
| Faza 1: Pregătire | 15 min | ⭐ Ușor |
| Faza 2: Creare Structură | 10 min | ⭐ Ușor |
| Faza 3: Arhivare | 30 min | ⭐⭐ Mediu |
| Faza 4: Reorganizare | 45 min | ⭐⭐ Mediu |
| Faza 5: README-uri | 60 min | ⭐⭐⭐ Dificil |
| Faza 6: Link-uri | 30 min | ⭐⭐ Mediu |
| Faza 7: Validare | 30 min | ⭐⭐ Mediu |
| **TOTAL** | **~3.5 ore** | - |

*Nota: Timpul poate varia în funcție de experiență și dacă automatizezi parțial cu scripturi.*

---

## 📞 CONTACT & SUPORT

Pentru întrebări sau probleme în timpul implementării:
1. Revizualizează acest document
2. Verifică README.md din fiecare folder
3. Consultă backup-ul dacă e nevoie de referință
4. Documentează orice deviații de la plan

---

## 📜 VERSIUNI DOCUMENT

| Versiune | Data | Autor | Modificări |
|----------|------|-------|------------|
| 1.0 | 2025-11-05 | Claude Code Analysis | Versiune inițială completă |

---

**Status Plan**: 📋 **Planificat** - Gata de execuție după aprobare
**Ultima Actualizare**: 5 Noiembrie 2025
**Următorul Pas**: Aprobare → Backup → Execuție Faza 2

---

*Acest document este GHIDUL MASTER pentru restructurarea documentației. Păstrează-l pentru referință viitoare.*
