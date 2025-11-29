# Monorepo Restructuring Strategy

**Data:** 29 Noiembrie 2025
**Autor:** Software Architect & Tech Lead
**Status:** Plan de Implementare

---

## Sumar Executiv

Acest document prezintă strategia completă pentru:
1. **Restructurarea documentației** - Curățare și organizare logică
2. **Optimizarea repository-ului Git** - Reducere dimensiune, .gitignore, commit strategy
3. **Consolidarea monorepo** - Structură finală optimizată

**Rezultat așteptat:**
- Repository redus de la ~250MB la ~20MB
- 12 fișiere obsolete eliminate
- Structură documentație clară în 5 categorii
- .gitignore optimizat pentru monorepo

---

## Partea 1: Fișiere de Eliminat

### 1.1 Rapoarte de Execuție Obsolete (ROOT)

| Fișier | Motiv Eliminare |
|--------|-----------------|
| `CATEGORIES_IMPORT_ANALYSIS.md` | Task completat - import finalizat |
| `CATEGORIES_IMPORT_COMPLETED.md` | Raport execuție one-time |
| `DOCUMENTATION_RESTRUCTURING_COMPLETION.md` | Meta-raport - restructurare veche |
| `DOCUMENTATION_RESTRUCTURING_PLAN.md` | Plan vechi - executat |
| `OPTION_C_IMPLEMENTATION_PLAN.md` | Plan implementat și finalizat |
| `ghid_dev_prod.md` | Înlocuit de alte documente |

### 1.2 Rapoarte de Execuție Obsolete (/docs/)

| Fișier | Motiv Eliminare |
|--------|-----------------|
| `ADMIN_LAYOUT_ALIGNMENT_REPORT.md` | Fix UI one-time - nu mai e relevant |
| `PHASE1_AUTH_TEST_REPORT.md` | Raport test execuție - completat |
| `PHASE2_ARTICLES_TEST_REPORT.md` | Raport test execuție - completat |
| `PHASE2_FIXES_REPORT.md` | Raport fix-uri - completat |
| `PHASE3_CATEGORIES_TEST_REPORT.md` | Raport test execuție - completat |
| `GIT_MONOREPO_MIGRATION_PLAN.md` | Migrare completată |

### 1.3 Foldere de Eliminat

| Folder | Motiv | Dimensiune |
|--------|-------|------------|
| `layouts/` | Template-uri UI reference, NU cod aplicație | ~230MB |
| `.playwright-mcp/` | Artifacts testare, screenshots | ~14MB |
| `context/` | Fișiere temporare context | ~40KB |

**Total spațiu recuperat: ~245MB**

---

## Partea 2: Fișiere de Mutat

### 2.1 De la ROOT la /docs/

| Fișier Sursă | Destinație | Categorie |
|--------------|------------|-----------|
| `PLAN_SEPARARE_DEV_PROD.md` | `/docs/guides/dev-prod-separation.md` | Ghid operațional |
| `FACEBOOK_AUTO_POSTING_PLAN.md` | `/docs/roadmap/facebook-auto-posting.md` | Planificare viitor |

### 2.2 De la /docs/ la alte locații

| Fișier Sursă | Destinație | Motiv |
|--------------|------------|-------|
| `SLUG_TRANSLITERATION.md` | `/apps/backend/docs/slug-transliteration.md` | Doc tehnică backend |
| `SLUG_TRANSLITERATION_SUMMARY.md` | Arhivare sau DELETE | Sumar implementare |
| `DOCUSAURUS_IMPLEMENTATION_PLAN.md` | `/docs/roadmap/` | Planificare viitor |

### 2.3 De la /context/

| Fișier Sursă | Destinație | Motiv |
|--------------|------------|-------|
| `design-principles.md` | `/docs/architecture/design-principles.md` | Doc arhitectură |
| `DELETE_CATEGORY_IMPLEMENTATION.md` | DELETE sau `/archive/` | Context temporar |

---

## Partea 3: Structura Finală Documentație

```
/var/www/deschide_news_app/
│
├── CLAUDE.md                    # Instrucțiuni AI (PĂSTRAT)
├── README.md                    # Prezentare proiect (PĂSTRAT)
├── ARCHITECTURE.md              # Arhitectură high-level (PĂSTRAT)
├── SETUP.md                     # Setup development (PĂSTRAT)
│
├── docs/                        # Hub centralizat documentație
│   ├── README.md                # Index navigare docs
│   │
│   ├── architecture/            # Documentație arhitectură
│   │   └── design-principles.md
│   │
│   ├── guides/                  # Ghiduri operaționale
│   │   ├── dev-prod-separation.md
│   │   └── deployment.md
│   │
│   ├── testing/                 # Documentație testare
│   │   ├── ADMIN_PANEL_TEST_SUITE.md
│   │   └── TESTING_AGENTS_GUIDE.md
│   │
│   ├── audits/                  # Rapoarte audit (referință)
│   │   ├── SEO_AUDIT_REPORT.md
│   │   ├── PROJECT_AUDIT_REPORT.md
│   │   └── BUSINESS_METRICS_REPORT.md
│   │
│   ├── roadmap/                 # Planificare viitor
│   │   ├── facebook-auto-posting.md
│   │   └── docusaurus-site.md
│   │
│   ├── infrastructure/          # Docs infrastructură (PĂSTRAT)
│   ├── features/                # Docs features (PĂSTRAT)
│   └── planning/                # Docs planificare (PĂSTRAT)
│
├── apps/
│   ├── backend/docs/            # Documentație specifică backend
│   │   ├── README.md
│   │   ├── api-platform/
│   │   ├── architecture/
│   │   ├── configs/
│   │   ├── examples/
│   │   ├── services/
│   │   └── slug-transliteration.md (NOU)
│   │
│   └── frontend/docs/           # Documentație specifică frontend
│       ├── README.md
│       ├── features/
│       ├── routing/
│       ├── setup/
│       └── ui/
│
├── archive/                     # Documente istorice
│   ├── README.md
│   ├── 2025-11-docs-sprints/
│   ├── 2025-11-root/
│   └── monorepo_migration/
│
├── sprints/                     # Planificare sprint-uri
│   └── README.md
│
└── .claude/                     # Configurații AI agent
    ├── agents/
    └── commands/
```

---

## Partea 4: Optimizare Git Repository

### 4.1 .gitignore Recomandat

```gitignore
# ===========================================
# DESCHIDE NEWS APP - MONOREPO .GITIGNORE
# ===========================================

# ----- IDEs & Editors -----
.idea/
.vscode/
*.swp
*~

# ----- OS Files -----
.DS_Store
Thumbs.db

# ----- Environment -----
.env.local
.env*.local

# ----- Testing Artifacts -----
.playwright-mcp/
**/screenshots/
**/test-results/
**/__snapshots__/
coverage/
.nyc_output/

# ----- UI Layout References -----
# Template-uri reference UI - NU cod aplicație
layouts/

# ----- Temporary Context -----
context/
.context/
*.context.md

# ----- Build & Dependencies -----
# (gestionate în subdirectoare apps/)

# ----- Logs -----
*.log

# ----- Backups -----
*.backup
*_OLD/

# ----- Large Files -----
*.sql.gz
*.tar.gz
```

### 4.2 Pattern-uri de SCOS din .gitignore

| Pattern | Motiv |
|---------|-------|
| `.claude/` | SCOS - Agent configs TREBUIE comitate |
| `archive/` | SCOS - Istoricul proiectului e valoros |

### 4.3 Strategia de Commit

Modificările curente trebuie organizate în **12 commit-uri logice**:

| # | Scope | Files | Commit Message |
|---|-------|-------|----------------|
| 1 | backend | 4 | `feat(backend): add Romanian slug transliteration` |
| 2 | frontend | 2 | `feat(frontend): add article delete functionality` |
| 3 | frontend | 2 | `feat(frontend): add category delete modal` |
| 4 | backend | 2 | `fix(backend): update article and image entities` |
| 5 | frontend | 9 | `feat(frontend): integrate CDN for images` |
| 6 | backend | 3 | `refactor(backend): update cache invalidation` |
| 7 | backend | 3 | `feat(backend): update API processors` |
| 8 | frontend | 5 | `fix(frontend): update admin article forms` |
| 9 | frontend | 5 | `fix(frontend): update public pages routing` |
| 10 | frontend | 4 | `feat(frontend): add slug utilities and tests` |
| 11 | docs | 8 | `docs: add test reports and SEO audit` |
| 12 | chore | 2 | `chore: update CLAUDE.md and configs` |

---

## Partea 5: Comenzi de Execuție

### Pasul 1: Backup (OBLIGATORIU)

```bash
# Creare backup înainte de orice modificare
cd /var/www/deschide_news_app
tar -czvf ~/backups/deschide_pre_restructure_$(date +%Y%m%d).tar.gz \
  --exclude=node_modules \
  --exclude=vendor \
  --exclude=.git \
  --exclude=var \
  .
```

### Pasul 2: Eliminare Fișiere Obsolete

```bash
# Root level
rm -f CATEGORIES_IMPORT_ANALYSIS.md
rm -f CATEGORIES_IMPORT_COMPLETED.md
rm -f DOCUMENTATION_RESTRUCTURING_COMPLETION.md
rm -f DOCUMENTATION_RESTRUCTURING_PLAN.md
rm -f OPTION_C_IMPLEMENTATION_PLAN.md
rm -f ghid_dev_prod.md

# Docs folder
rm -f docs/ADMIN_LAYOUT_ALIGNMENT_REPORT.md
rm -f docs/PHASE1_AUTH_TEST_REPORT.md
rm -f docs/PHASE2_ARTICLES_TEST_REPORT.md
rm -f docs/PHASE2_FIXES_REPORT.md
rm -f docs/PHASE3_CATEGORIES_TEST_REPORT.md
rm -f docs/GIT_MONOREPO_MIGRATION_PLAN.md
rm -f docs/SLUG_TRANSLITERATION_SUMMARY.md
```

### Pasul 3: Eliminare Foldere Non-Esențiale

```bash
# UI layouts reference - NU cod aplicație
rm -rf layouts/

# Testing artifacts
rm -rf .playwright-mcp/

# Context temporar
rm -rf context/
```

### Pasul 4: Creare Structură Nouă

```bash
# Creare foldere noi în docs
mkdir -p docs/architecture
mkdir -p docs/guides
mkdir -p docs/audits
mkdir -p docs/roadmap
```

### Pasul 5: Mutare Fișiere

```bash
# Mutări
mv PLAN_SEPARARE_DEV_PROD.md docs/guides/dev-prod-separation.md
mv FACEBOOK_AUTO_POSTING_PLAN.md docs/roadmap/facebook-auto-posting.md
mv docs/DOCUSAURUS_IMPLEMENTATION_PLAN.md docs/roadmap/docusaurus-site.md
mv docs/SLUG_TRANSLITERATION.md apps/backend/docs/slug-transliteration.md

# Mutare design principles (dacă încă există context/)
# mv context/design-principles.md docs/architecture/
```

### Pasul 6: Actualizare .gitignore

```bash
# Backup și actualizare .gitignore
cp .gitignore .gitignore.backup
# Apoi editare manuală conform secțiunii 4.1
```

### Pasul 7: Commit Structurat

```bash
# Commit restructurare documentație
git add -A
git commit -m "chore: restructure documentation and cleanup repository

- Remove 12 obsolete execution reports
- Reorganize docs into logical categories
- Update .gitignore for monorepo patterns
- Remove 245MB of non-essential files (layouts, test artifacts)

🤖 Generated with Claude Code"
```

---

## Partea 6: Metrici Succes

| Metrică | Înainte | După |
|---------|---------|------|
| Fișiere MD în root | 10+ | 4 |
| Dimensiune repository | ~250MB | ~20MB |
| Fișiere obsolete | 12 | 0 |
| Categorii documentație | Ad-hoc | 5 clare |
| Timp găsire doc | Variabil | <30 sec |

---

## Concluzii

### Beneficii Imediate
1. **Repository curat** - 245MB eliminate
2. **Navigare ușoară** - 5 categorii clare în /docs/
3. **Istoric păstrat** - /archive/ pentru documente valoroase
4. **Monorepo optimizat** - Docs specifice în /apps/

### Pași Următori Opționali
1. **Docusaurus** - Site documentație profesionist
2. **Pre-commit hooks** - Validare automată docs
3. **README index** - Auto-generare index docs

---

**Document creat de:** Software Architect & Tech Lead
**Validat de:** Agenți specializați (Product Strategist, Business Analyst)
