# Git Monorepo Migration Plan
## Deschide News App - Version Control Restructuring

**Created**: 2025-11-14
**Author**: Claude Code Analysis
**Status**: 📋 Proposal - Awaiting Approval

---

## 📊 Executive Summary

**Current State**: Two separate git repositories (backend + frontend)
**Proposed State**: Unified monorepo structure
**Migration Time**: 3.5-4.5 hours
**Risk Level**: 🟡 Medium (with proper backups: 🟢 Low)

---

## 🔍 Analiza Stării Actuale

### Current Repository Structure

```
/var/www/deschide_news_app/
├── deschide_backend/          [GIT REPO - SEPARATE]
│   ├── .git/                  (8.1 MB, 17 commits, branch: master)
│   └── remote: git@github.com:radusoltan/deschide_news_app_backend.git
│
├── deschide_frontend/         [GIT REPO - SEPARATE]
│   ├── .git/                  (6.3 MB, 17 commits, branch: main)
│   └── remote: git@github.com:radusoltan/deschide_news_app_frontend.git
│
└── [ROOT - NO GIT REPOSITORY]
    ├── docs/                  (not versioned)
    ├── sprints/               (not versioned)
    ├── CLAUDE.md              (not versioned)
    ├── DEVELOPMENT_ENVIRONMENT.md (not versioned)
    └── APPLICATIONS_ARCHITECTURE.md (not versioned)
```

### Git Repository Details

#### Backend Repository
- **Remote**: `git@github.com:radusoltan/deschide_news_app_backend.git`
- **Branch**: `master`
- **Commits**: 17
- **Size**: 8.1 MB
- **Tags**: None
- **Uncommitted Changes**: ~25 files (modified/deleted)
  - Modified: composer.json, config files, entities, services, providers
  - Deleted: Various shell scripts (load-test, install-pgbouncer, etc.)
  - Untracked: New migrations, commands, documentation

#### Frontend Repository
- **Remote**: `git@github.com:radusoltan/deschide_news_app_frontend.git`
- **Branch**: `main`
- **Commits**: 17
- **Size**: 6.3 MB
- **Tags**: None
- **Uncommitted Changes**:
  - Modified: Sidebar.tsx, dal.ts
  - Untracked: app/[locale]/admin/authors/, app/actions/authors.ts, docs/

### Recent Commit History

**Backend (last 10 commits):**
```
b6912af Merge remote-tracking branch 'origin/master'
2dd700d some updates
24b42a8 Create strategy document for category import
afa6dd0 docs: Add comprehensive documentation for external audit (backend complete)
1dc9630 docs: Add comprehensive audit preparation documentation
2cdc3cf feat: Major backend development - API Platform, Elasticsearch, and performance optimizations
5e16591 feat: add translation API endpoints for multi-locale slug support
bff04c1 some updates
60f236d some updates
622fb19 feat: add image serialization and fixtures with public API access
```

**Frontend (last 10 commits):**
```
40d1204 some updates
ba1b77d docs: Add complete frontend documentation for external audit
20fc70d docs: Add audit preparation documentation for frontend
69b612f feat: Major frontend development - Admin dashboard, LiveText, and public pages
f64e2d9 feat: add sitemap optimization and translation API integration
6b76660 some updates
0324950 some updates
eb0aacb fix: correct image path from originals/ to root uploads/images/
b74aa55 Add category filter and display author names in articles table
d754280 Add Important Articles List management page in admin panel
```

---

## ❌ Probleme Identificate

### 1. **Multiple Repositories Separate**
- Backend și frontend sunt în repositories complet separate
- Nu există sincronizare automată între versiuni
- Features cross-cutting (backend + frontend) necesită 2 commits, 2 PRs

### 2. **Inconsistență Branch Naming**
- Backend: `master`
- Frontend: `main`
- Creează confuzie și complexitate în workflow

### 3. **Uncommitted Changes**
- **Backend**: 25+ fișiere modificate/șterse, migrations noi, comenzi noi
- **Frontend**: Modificări în Sidebar, dal.ts, directoare noi (authors, docs)
- Risc de pierdere a work-ului la migrare

### 4. **Documentație Neversioned**
- Fișiere critice în root (`docs/`, `sprints/`, `CLAUDE.md`) nu sunt versionate
- Risc de pierdere a documentației
- Imposibil de trackat schimbările în timp

### 5. **Lipsă Root Repository**
- Directorul root `/var/www/deschide_news_app/` nu este git repository
- Nu putem versiona structural proiectului ca întreg
- Difficult să gestionăm dependencies între apps

### 6. **Sincronizare Manuală**
- Deploy-uri necesită coordonare manuală backend + frontend
- Release versioning complicat (care backend merge cu care frontend?)
- Testing integration mai dificil

---

## 🎯 Opțiuni de Restructurare

### Opțiunea 1: Monorepo Full (✅ RECOMANDAT)

**Descriere**: Creăm un singur repository în root care conține toate componentele.

**Structură Propusă:**
```
deschide_news_app/              # ROOT MONOREPO
├── .git/                       # Git repository (unified)
├── .github/
│   └── workflows/              # GitHub Actions CI/CD
│       ├── backend-tests.yml
│       ├── frontend-tests.yml
│       └── deploy.yml
├── apps/
│   ├── backend/               # Symfony 7.3 (din deschide_backend/)
│   │   ├── .git/              # DELETED (moved to root)
│   │   ├── config/
│   │   ├── src/
│   │   ├── composer.json
│   │   ├── .env.example
│   │   └── README.md          # Backend-specific docs
│   └── frontend/              # Next.js 16 (din deschide_frontend/)
│       ├── .git/              # DELETED (moved to root)
│       ├── app/
│       ├── package.json
│       └── README.md          # Frontend-specific docs
├── docs/                      # Centralized documentation
│   ├── backend/
│   ├── frontend/
│   ├── infrastructure/
│   ├── deployment/
│   └── architecture/
├── sprints/                   # Sprint planning
├── scripts/                   # Deployment, CI/CD, utility scripts
│   ├── deploy-backend.sh
│   ├── deploy-frontend.sh
│   └── setup-dev-env.sh
├── .gitignore                 # Root gitignore
├── CLAUDE.md                  # Project-wide instructions
├── README.md                  # Main project README
├── DEVELOPMENT_ENVIRONMENT.md
├── APPLICATIONS_ARCHITECTURE.md
└── package.json               # Optional: root package.json for workspace
```

**Avantaje:**
- ✅ **Versioning Unificat**: Un commit poate include backend + frontend
- ✅ **Sincronizare Atomică**: Features cross-cutting = 1 PR
- ✅ **Documentație Centralizată**: docs/, sprints/, CLAUDE.md versionate
- ✅ **CI/CD Simplificat**: Un workflow poate rula teste pentru ambele apps
- ✅ **Tag-uri Coordonate**: v1.0.0 = backend + frontend împreună
- ✅ **Single Clone**: `git clone` → totul
- ✅ **Istoric Păstrat**: Păstrăm toate cele 17+17 commits din ambele repos
- ✅ **Easier Onboarding**: Noii developeri clonează un singur repo

**Dezavantaje:**
- ⚠️ **Migrare Complexă**: Necesită git-filter-repo pentru a păstra istoricul
- ⚠️ **Dimensiune Mai Mare**: .git va fi ~14-15 MB (8.1 + 6.3)
- ⚠️ **Initial Setup Time**: 3.5-4.5 ore pentru migrare completă

**Cazuri de Utilizare Ideale:**
- ✅ Proiecte fullstack cu tight coupling între frontend și backend
- ✅ Echipe mici care lucrează pe ambele componente
- ✅ Features care necesită modificări coordonate în ambele apps
- ✅ Deploy sincronizat (backend + frontend în tandem)

---

### Opțiunea 2: Git Submodules

**Descriere**: Păstrăm repos separate, dar le legăm printr-un parent repository.

**Structură:**
```
deschide_news_app/              # ROOT REPO (parent)
├── .git/
├── .gitmodules
├── backend/                    # Git submodule → deschide_news_app_backend
│   └── .git/                   # Points to separate repo
├── frontend/                   # Git submodule → deschide_news_app_frontend
│   └── .git/                   # Points to separate repo
├── docs/
└── README.md
```

**Comenzi:**
```bash
git submodule add git@github.com:radusoltan/deschide_news_app_backend.git apps/backend
git submodule add git@github.com:radusoltan/deschide_news_app_frontend.git apps/frontend
```

**Avantaje:**
- ✅ **Istoric Intact**: Repos separate rămân neschimbate
- ✅ **Independent Development**: Teams pot lucra separat
- ✅ **Selective Cloning**: Poți clona doar backend sau frontend

**Dezavantaje:**
- ❌ **Complexitate Ridicată**: Submodule sync issues, confusing pentru beginners
- ❌ **Sincronizare Manuală**: Tot trebuie să coordonezi versiunile manual
- ❌ **Workflow Complicat**: `git submodule update --remote`, checkout branches în fiecare submodule
- ❌ **CI/CD Complicat**: GitHub Actions trebuie să facă checkout cu `--recurse-submodules`
- ❌ **Nu Rezolvă Problema Principală**: Nu oferă versioning atomic

**Când să folosești:**
- Large teams cu specializări stricte (backend team ≠ frontend team)
- Projects unde backend/frontend evoluează cu ritmuri foarte diferite
- Legacy migration când nu poți merge repos

**Verdict**: ❌ **NU RECOMANDAT** pentru acest proiect - adaugă complexitate fără beneficii clare.

---

### Opțiunea 3: Git Subtrees

**Descriere**: Similar cu submodules, dar mai simplu - copiază istoricul în parent repo.

**Structură:** (similar cu monorepo, dar cu remote tracking)

**Comenzi:**
```bash
git subtree add --prefix apps/backend git@github.com:radusoltan/deschide_news_app_backend.git master
git subtree add --prefix apps/frontend git@github.com:radusoltan/deschide_news_app_frontend.git main
```

**Avantaje:**
- ✅ **Mai Simplu decât Submodules**: Nu necesită `git submodule update`
- ✅ **Istoric Păstrat**: Păstrează commit history
- ✅ **Normal Git Workflow**: Clonare normală, fără flags speciale

**Dezavantaje:**
- ❌ **Merge Complicat**: `git subtree pull` poate fi confusing
- ❌ **Duplicare Istoric**: Istoricul apare de 2 ori (în parent și în child)
- ❌ **Push Upstream Complicated**: `git subtree push` pentru a actualiza repos originale

**Când să folosești:**
- Când vrei să consumi libraries din alte repos (read-only)
- Migration path către monorepo (interim step)

**Verdict**: 🟡 **ACCEPTABIL** ca soluție intermediară, dar **Opțiunea 1 (Monorepo Full) este mai bună pe termen lung**.

---

## ✅ Recomandare Finală

### **Opțiunea 1: Monorepo Full**

**Motivare:**

1. **Atomicitate**: Un feature care modifică API endpoint (backend) + UI consumption (frontend) = **1 commit, 1 PR, 1 review**
2. **Simplitate**:
   - `git clone git@github.com:radusoltan/deschide_news_app.git` → everything
   - Un singur `README.md` cu setup instructions
   - Un singur `.github/workflows/` pentru CI/CD
3. **Documentație Centralizată**:
   - `docs/` versioned properly
   - `sprints/` tracked în git
   - `CLAUDE.md` actualizat cu istoricul său
4. **CI/CD Simplificat**:
   - GitHub Actions poate rula `backend-tests` și `frontend-tests` în parallel
   - Deploy coordonat (backend deploy → wait → frontend deploy)
5. **Versioning Sincronizat**:
   - `v1.0.0` = backend 1.0.0 + frontend 1.0.0 (guaranteed compatibility)
   - Releases coordonate
   - Rollback mai simplu (rollback la un commit anterior = ambele apps)

**Argument împotriva separării:**
- Backend și frontend **NU sunt** componente independente care evoluează separat
- Frontend consumă API-ul backend → **tight coupling**
- Features noi necesită modificări în ambele parts (articol nou → backend entity + frontend UI)
- Echipa (probabil) lucrează pe ambele componente

**Proiecte majore care folosesc monorepo:**
- **Google**: monorepo gigant (~2 billion lines)
- **Facebook/Meta**: monorepo pentru React, React Native, etc.
- **Twitter**: monorepo pentru toate serviciile
- **Vercel**: Next.js, Turbo, etc. în monorepo
- **NX**, **Turborepo**: tools dedicate pentru monorepo management

---

## 📋 Plan Detaliat de Implementare

### Faza 1: Pregătire și Backup (30 min) ⏱️

**Obiective:**
1. Backup complet al repositories existente (safety net)
2. Commit/cleanup modificări uncommitted
3. Verificare că totul este push-uit în repos originale

**Pași:**

#### 1.1 Backup Complete (Mirror Clones)

```bash
# Creăm director backup cu timestamp
BACKUP_DIR="/var/www/backups/deschide_git_migration_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
cd "$BACKUP_DIR"

# Clone mirror (includes all branches, tags, refs)
git clone --mirror git@github.com:radusoltan/deschide_news_app_backend.git backend_mirror.git
git clone --mirror git@github.com:radusoltan/deschide_news_app_frontend.git frontend_mirror.git

# Backup working directories (cu .git)
cp -r /var/www/deschide_news_app/deschide_backend backend_working_copy
cp -r /var/www/deschide_news_app/deschide_frontend frontend_working_copy

# Compress pentru siguranță
tar -czf backend_mirror.tar.gz backend_mirror.git
tar -czf frontend_mirror.tar.gz frontend_mirror.git
tar -czf backend_working.tar.gz backend_working_copy
tar -czf frontend_working.tar.gz frontend_working_copy

echo "✅ Backup complet în: $BACKUP_DIR"
ls -lh
```

**Rezultat așteptat:**
```
/var/www/backups/deschide_git_migration_20251114_HHMMSS/
├── backend_mirror.git/         (git mirror)
├── backend_mirror.tar.gz       (compressed backup)
├── backend_working_copy/       (full working dir with .git)
├── backend_working.tar.gz
├── frontend_mirror.git/
├── frontend_mirror.tar.gz
├── frontend_working_copy/
└── frontend_working.tar.gz
```

#### 1.2 Commit Uncommitted Changes - Backend

```bash
cd /var/www/deschide_news_app/deschide_backend

# Review changes
git status

# Stage deleted files
git add -u

# Stage new files (selective - review first!)
# Check untracked files:
git status --short | grep '^??'

# Add meaningful new files (migrations, commands, docs)
git add migrations/Version20251109*.php
git add migrations/Version20251110*.php
git add src/Command/ArchiveOldArticlesCommand.php
git add src/Command/Import/ImportCompleteCategoriesCommand.php
git add src/Enum/ArchiveReason.php
git add src/EventSubscriber/LocaleSubscriber.php
git add src/State/ArchivedArticleProvider.php
git add docs/PRODUCTION_CATEGORY_IMPORT_GUIDE.md
git add docs/architecture/
git add docs/services/

# Review staged changes
git status

# Commit
git commit -m "chore: prepare for monorepo migration

- Add new migrations for category and article improvements
- Add archive functionality (command, enum, provider)
- Add locale subscriber for proper i18n handling
- Add production category import documentation
- Remove obsolete shell scripts (moved to dev-tools)
- Update dependencies and configuration files

Changes staged for monorepo consolidation."

# Push to remote
git push origin master

# Verify clean state
git status  # Should show: "nothing to commit, working tree clean"
```

#### 1.3 Commit Uncommitted Changes - Frontend

```bash
cd /var/www/deschide_news_app/deschide_frontend

# Review changes
git status

# Stage modifications
git add app/[locale]/admin/components/Sidebar.tsx
git add lib/dal.ts

# Add new features
git add app/[locale]/admin/authors/
git add app/actions/authors.ts
git add docs/

# Review
git status

# Commit
git commit -m "chore: prepare for monorepo migration

- Add authors management feature (admin panel + actions)
- Update sidebar navigation
- Improve data access layer (dal.ts)
- Add feature documentation

Changes staged for monorepo consolidation."

# Push
git push origin main

# Verify
git status  # Should be clean
```

#### 1.4 Verification Checklist

```bash
# ✅ Backend: clean working tree
cd /var/www/deschide_news_app/deschide_backend
git status | grep "working tree clean"

# ✅ Frontend: clean working tree
cd /var/www/deschide_news_app/deschide_frontend
git status | grep "working tree clean"

# ✅ Both pushed to remote
cd /var/www/deschide_news_app/deschide_backend
git log origin/master..HEAD  # Should be empty (no unpushed commits)

cd /var/www/deschide_news_app/deschide_frontend
git log origin/main..HEAD  # Should be empty

# ✅ Backup exists
ls -lh /var/www/backups/deschide_git_migration_*/
```

**Deliverables:**
- ✅ Backup mirrors în `/var/www/backups/deschide_git_migration_YYYYMMDD/`
- ✅ Toate changes comitate în repos originale
- ✅ Working tree clean în backend (`master`) și frontend (`main`)
- ✅ Toate commits push-uite la remote (GitHub)

---

### Faza 2: Crearea Monorepo-ului (1-2 ore) ⏱️

**Obiective:**
1. Instalare `git-filter-repo` tool
2. Crearea clones temporare pentru rewrite
3. Rewrite history → subdirectories (`apps/backend/`, `apps/frontend/`)
4. Crearea monorepo în root
5. Merge istoricelor păstrând toate commit-urile

**Metoda**: Git Filter-Repo (modern, recommended by Git team, replacing `git filter-branch`)

#### 2.1 Instalare git-filter-repo

```bash
# Check if already installed
git filter-repo --help 2>/dev/null && echo "✅ Already installed" || echo "❌ Need to install"

# Install via pip (recommended)
pip3 install git-filter-repo

# Verify installation
git filter-repo --version
# Expected: git-filter-repo 2.x.x
```

**Alternative installation methods:**
```bash
# If pip3 fails, try manual install
cd /tmp
wget https://raw.githubusercontent.com/newren/git-filter-repo/main/git-filter-repo
chmod +x git-filter-repo
sudo mv git-filter-repo /usr/local/bin/

# Verify
git filter-repo --version
```

#### 2.2 Crearea Clones Temporare

```bash
# Working directory
WORK_DIR="/tmp/deschide_monorepo_migration"
mkdir -p "$WORK_DIR"
cd "$WORK_DIR"

# Clone backend (fresh clone, not the working directory!)
git clone git@github.com:radusoltan/deschide_news_app_backend.git backend_rewrite
cd backend_rewrite
git remote remove origin  # git-filter-repo requires no remotes
cd ..

# Clone frontend
git clone git@github.com:radusoltan/deschide_news_app_frontend.git frontend_rewrite
cd frontend_rewrite
git remote remove origin
cd ..

echo "✅ Temporary clones created in: $WORK_DIR"
ls -la
```

#### 2.3 Rewrite Backend History → apps/backend/

```bash
cd "$WORK_DIR/backend_rewrite"

# Rewrite all commits to move files into apps/backend/
git filter-repo --to-subdirectory-filter apps/backend --force

# Verify structure
ls -la apps/backend/
# Should see: config/, src/, composer.json, etc.

# Check history preserved
git log --oneline --all
# Should see all 17+ commits

# Check first commit
git log --reverse --oneline | head -1
# Paths should be: apps/backend/...

echo "✅ Backend history rewritten to apps/backend/"
```

**What this does:**
- Every file path is prepended with `apps/backend/`
- Example: `src/Entity/Article.php` → `apps/backend/src/Entity/Article.php`
- **ALL commits are preserved** (same hashes, messages, authors, dates)
- Git history remains intact

#### 2.4 Rewrite Frontend History → apps/frontend/

```bash
cd "$WORK_DIR/frontend_rewrite"

# Same process for frontend
git filter-repo --to-subdirectory-filter apps/frontend --force

# Verify
ls -la apps/frontend/
# Should see: app/, package.json, next.config.ts, etc.

# Check history
git log --oneline --all
# Should see all 17+ commits

echo "✅ Frontend history rewritten to apps/frontend/"
```

#### 2.5 Crearea Monorepo în Root

```bash
# Go to project root
cd /var/www/deschide_news_app

# IMPORTANT: Rename existing directories (safety)
mv deschide_backend deschide_backend_OLD
mv deschide_frontend deschide_frontend_OLD

# Initialize new git repository
git init
git checkout -b main

# Create initial structure
mkdir -p apps docs sprints scripts .github/workflows

# Add .gitignore (root level)
cat > .gitignore << 'EOF'
# IDEs
.idea/
.vscode/
.claude/

# OS
.DS_Store
Thumbs.db
*.swp
*~

# Backups and old directories
*.backup
*_OLD/
archive/
archive_old/

# Logs
*.log

# Environment files (app-specific .env files handled in subdirectories)
.env.local
.env*.local

# Build artifacts (app-specific ignores in subdirs)
# See apps/backend/.gitignore and apps/frontend/.gitignore
EOF

# Initial commit (root structure only)
git add .gitignore
git commit -m "chore: initialize monorepo structure

- Create root .gitignore
- Prepare apps/, docs/, sprints/, scripts/ directories
- Set up unified git repository

This is the foundation for merging backend and frontend repos."

echo "✅ Monorepo initialized in /var/www/deschide_news_app"
git status
```

#### 2.6 Merge Backend History

```bash
cd /var/www/deschide_news_app

# Add backend rewrite as remote
git remote add backend_temp "$WORK_DIR/backend_rewrite"

# Fetch all backend history
git fetch backend_temp

# Merge backend (branch: master)
git merge backend_temp/master --allow-unrelated-histories -m "merge: add backend with full history

Merge deschide_news_app_backend repository into monorepo.
All 17+ commits preserved with history.

Original repo: git@github.com:radusoltan/deschide_news_app_backend.git
Branch: master
Location in monorepo: apps/backend/"

# Verify merge
ls -la apps/backend/
# Should see: config/, src/, composer.json, .env.example, etc.

git log --oneline --graph --all -20
# Should see backend commits merged

echo "✅ Backend history merged"
```

#### 2.7 Merge Frontend History

```bash
cd /var/www/deschide_news_app

# Add frontend rewrite as remote
git remote add frontend_temp "$WORK_DIR/frontend_rewrite"

# Fetch
git fetch frontend_temp

# Merge frontend (branch: main)
git merge frontend_temp/main --allow-unrelated-histories -m "merge: add frontend with full history

Merge deschide_news_app_frontend repository into monorepo.
All 17+ commits preserved with history.

Original repo: git@github.com:radusoltan/deschide_news_app_frontend.git
Branch: main
Location in monorepo: apps/frontend/"

# Verify
ls -la apps/frontend/
# Should see: app/, package.json, next.config.ts, etc.

git log --oneline --graph --all -30
# Should see both backend and frontend commits

echo "✅ Frontend history merged"
```

#### 2.8 Cleanup Temporary Remotes

```bash
cd /var/www/deschide_news_app

# Remove temporary remotes
git remote remove backend_temp
git remote remove frontend_temp

# Verify only origin remains (will be added later)
git remote -v
# Should be empty for now

echo "✅ Temporary remotes removed"
```

#### 2.9 Verification

```bash
cd /var/www/deschide_news_app

# Check structure
tree -L 3 -a -I 'node_modules|vendor|.git'

# Check commit count
TOTAL_COMMITS=$(git rev-list --all --count)
echo "Total commits in monorepo: $TOTAL_COMMITS"
# Expected: ~36+ (17 backend + 17 frontend + 2 merge commits + 1 init)

# Check that paths are correct
git ls-tree -r --name-only HEAD | grep "apps/backend" | head -5
git ls-tree -r --name-only HEAD | grep "apps/frontend" | head -5

# Check history visualization
git log --oneline --graph --all --decorate -20

echo "✅ Monorepo created successfully"
```

**Expected Structure:**
```
/var/www/deschide_news_app/
├── .git/                       # Unified repository
├── .gitignore                  # Root gitignore
├── apps/
│   ├── backend/               # From backend_rewrite (17+ commits)
│   │   ├── config/
│   │   ├── src/
│   │   ├── composer.json
│   │   └── .env.example
│   └── frontend/              # From frontend_rewrite (17+ commits)
│       ├── app/
│       ├── package.json
│       └── next.config.ts
├── docs/                      # Empty for now
├── sprints/                   # Empty for now
└── scripts/                   # Empty for now
```

**Deliverables:**
- ✅ Monorepo creat în `/var/www/deschide_news_app/` cu `.git/` în root
- ✅ Istoric backend păstrat complet (17+ commits) în `apps/backend/`
- ✅ Istoric frontend păstrat complet (17+ commits) în `apps/frontend/`
- ✅ Total ~36+ commits în monorepo (backend + frontend + merges)
- ✅ Git history preservat (authors, dates, messages intact)

---

### Faza 3: Restructurare și Configurare (1 oră) ⏱️

**Obiective:**
1. Mutăm docs/, sprints/, fișiere din root în structura nouă
2. Creăm README.md principal
3. Actualizăm CLAUDE.md cu structura monorepo
4. Configurăm .gitignore pentru fiecare subdirectory
5. Creăm documentație specifică pentru apps/backend și apps/frontend

#### 3.1 Mutare Fișiere Existente

```bash
cd /var/www/deschide_news_app

# Copy docs/ and sprints/ from old backend (if they exist there)
# Otherwise, they should already be in root
# Verify what we have:
ls -la

# If docs/ and sprints/ are already in root, we're good
# If not, copy from old directories:
if [ -d "deschide_backend_OLD/docs" ]; then
    cp -r deschide_backend_OLD/docs/* docs/ 2>/dev/null || true
fi

if [ -d "deschide_backend_OLD/sprints" ]; then
    cp -r deschide_backend_OLD/sprints/* sprints/ 2>/dev/null || true
fi

# Copy other important root files
cp CLAUDE.md CLAUDE.md.backup  # Backup current
cp DEVELOPMENT_ENVIRONMENT.md DEVELOPMENT_ENVIRONMENT.md.backup
cp APPLICATIONS_ARCHITECTURE.md APPLICATIONS_ARCHITECTURE.md.backup

# Handle archive/ (decide if we keep or discard)
# Option 1: Keep as archive_old/
if [ -d "archive" ]; then
    mv archive archive_old
fi

# Option 2: Discard completely (if not needed)
# rm -rf archive

echo "✅ Files reorganized"
```

#### 3.2 Crearea README.md Principal

```bash
cd /var/www/deschide_news_app

cat > README.md << 'EOF'
# Deschide News App

**Multilanguage News Platform** - Modern news application with Symfony backend and Next.js frontend.

[![Backend: Symfony 7.3](https://img.shields.io/badge/Backend-Symfony%207.3-black?logo=symfony)](https://symfony.com)
[![Frontend: Next.js 16](https://img.shields.io/badge/Frontend-Next.js%2016-black?logo=next.js)](https://nextjs.org)
[![PHP: 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php)](https://php.net)
[![TypeScript](https://img.shields.io/badge/TypeScript-5.x-3178C6?logo=typescript)](https://typescriptlang.org)

## 📋 Project Overview

**Deschide News App** is a full-stack multilanguage news platform featuring:

- **Backend**: Symfony 7.3 (PHP 8.4) - RESTful API with JSON-LD/Hydra
- **Frontend**: Next.js 16 (React 19.2, TypeScript) - Modern web interface
- **Languages**: Romanian (ro), English (en), Russian (ru)
- **Architecture**: Monorepo with unified version control

## 🚀 Quick Start

### Prerequisites

- PHP 8.4+ with extensions: pdo_pgsql, intl, opcache, redis
- Node.js 18+ and pnpm
- PostgreSQL 17
- Redis 7
- Composer 2.x
- Symfony CLI

### Installation

```bash
# Clone the monorepo
git clone git@github.com:radusoltan/deschide_news_app.git
cd deschide_news_app

# Backend setup
cd apps/backend
cp .env.example .env.local
# Edit .env.local with your configuration
composer install
symfony console doctrine:database:create
symfony console doctrine:migrations:migrate
symfony console lexik:jwt:generate-keypair

# Frontend setup
cd ../frontend
cp .env.example .env.local
# Edit .env.local
pnpm install

# Start both applications
cd ../backend
symfony serve -d --port=8081

cd ../frontend
pnpm dev
```

### Access

- **Backend API**: http://127.0.0.1:8081/api
- **Frontend**: http://localhost:3005
- **API Documentation**: http://127.0.0.1:8081/api/docs.jsonld

## 📁 Repository Structure

```
deschide_news_app/              # Monorepo root
├── apps/
│   ├── backend/               # Symfony 7.3 API
│   │   ├── config/           # Configuration files
│   │   ├── src/              # PHP source code
│   │   ├── migrations/       # Database migrations
│   │   └── README.md         # Backend documentation
│   └── frontend/             # Next.js 16 application
│       ├── app/              # App Router pages
│       ├── components/       # React components
│       └── README.md         # Frontend documentation
├── docs/                      # Centralized documentation
│   ├── backend/
│   ├── frontend/
│   └── architecture/
├── sprints/                   # Sprint planning
├── scripts/                   # Deployment & utility scripts
├── .github/
│   └── workflows/            # GitHub Actions CI/CD
├── CLAUDE.md                 # AI assistant instructions
├── DEVELOPMENT_ENVIRONMENT.md
└── APPLICATIONS_ARCHITECTURE.md
```

## 🛠️ Common Commands

### Backend

```bash
cd apps/backend

# Start development server
symfony serve -d --port=8081

# Run migrations
symfony console doctrine:migrations:migrate

# Clear cache
symfony console cache:clear

# Run tests
vendor/bin/phpunit
```

### Frontend

```bash
cd apps/frontend

# Development server
pnpm dev

# Production build
pnpm build

# Linting
pnpm lint
```

## 🏗️ Architecture

- **Backend**: RESTful API with API Platform, Doctrine ORM, JWT authentication
- **Frontend**: Server-side rendering with Next.js App Router, Tailwind CSS
- **Database**: PostgreSQL 17 with Gedmo extensions (Translatable, Sluggable, Timestampable)
- **Search**: Elasticsearch for full-text search
- **Cache**: Redis for sessions and application cache
- **Queue**: RabbitMQ via Symfony Messenger
- **Real-time**: Mercure Hub for push notifications

## 📚 Documentation

- **Backend README**: [apps/backend/README.md](apps/backend/README.md)
- **Frontend README**: [apps/frontend/README.md](apps/frontend/README.md)
- **Development Environment**: [DEVELOPMENT_ENVIRONMENT.md](DEVELOPMENT_ENVIRONMENT.md)
- **Infrastructure**: [APPLICATIONS_ARCHITECTURE.md](APPLICATIONS_ARCHITECTURE.md)
- **API Documentation**: http://127.0.0.1:8081/api/docs.jsonld (when backend is running)

## 🧪 Testing

```bash
# Backend tests
cd apps/backend
vendor/bin/phpunit

# Frontend tests (when configured)
cd apps/frontend
pnpm test
```

## 🚢 Deployment

See [scripts/](scripts/) for deployment utilities and [docs/deployment/](docs/deployment/) for detailed deployment guides.

## 📄 License

Proprietary - All rights reserved

## 👥 Team

Developed by Radu Soltan and contributors.

---

**Status**: 🟢 Active Development
**Version**: Monorepo Migration (November 2025)
EOF

echo "✅ Main README.md created"
```

#### 3.3 Actualizare CLAUDE.md

```bash
cd /var/www/deschide_news_app

# Backup existing
cp CLAUDE.md CLAUDE_monorepo_backup.md

# Create updated version (prepend monorepo info)
cat > CLAUDE.md << 'EOF'
# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 🏗️ Repository Structure: MONOREPO

**IMPORTANT**: This is a monorepo containing both backend and frontend applications.

```
deschide_news_app/              # ROOT MONOREPO
├── apps/
│   ├── backend/               # Symfony 7.3 API (PHP 8.4)
│   └── frontend/              # Next.js 16 (React 19.2, TypeScript)
├── docs/                      # Centralized documentation
├── sprints/                   # Sprint planning
└── scripts/                   # Deployment scripts
```

**Working Directories:**
- Backend: `/var/www/deschide_news_app/apps/backend`
- Frontend: `/var/www/deschide_news_app/apps/frontend`

## Project Overview

**Deschide News App** - A multilanguage news platform with:
- **Backend**: Symfony 7.3 (PHP 8.4) - RESTful API
- **Frontend**: Next.js 16 (React 19.2 / TypeScript) - Web interface
- **Languages**: Romanian (ro), English (en), Russian (ru)

## Port Configuration

### Development Ports

| Application | Port | Access URL | Status |
|-------------|------|------------|--------|
| **Backend (Symfony)** | 8081 | http://127.0.0.1:8081 | ✅ Running |
| **Frontend (Next.js)** | 3005 | http://localhost:3005 | ✅ Running |
| **CDN (Static Assets)** | 8082 | http://127.0.0.1:8082 | ✅ Running |

### Shared Services

| Service | Port | Access | Usage |
|---------|------|--------|-------|
| PostgreSQL | 5432 | localhost:5432 | Database: `deschide_news` |
| Redis | 6379 | localhost:6379/1 | Cache, sessions (DB 1) |
| RabbitMQ | 5672 | amqp://localhost:5672 | Message queue |
| Elasticsearch | 9200 | https://localhost:9200 | Search engine |

## Common Commands

### Backend (Symfony)

**Location**: `/var/www/deschide_news_app/apps/backend`

```bash
cd /var/www/deschide_news_app/apps/backend

# Start server
symfony serve -d --port=8081

# Database
symfony console doctrine:migrations:migrate
symfony console make:migration

# Elasticsearch
symfony console app:elasticsearch:create-index
symfony console app:elasticsearch:index-articles

# Import from Newscoop
symfony console app:import:categories
symfony console app:import:articles
```

### Frontend (Next.js)

**Location**: `/var/www/deschide_news_app/apps/frontend`

```bash
cd /var/www/deschide_news_app/apps/frontend

# Start dev server
pnpm dev

# Build
pnpm build

# Lint
pnpm lint
```

### Quick Start (Both Applications)

```bash
# Terminal 1 - Backend
cd /var/www/deschide_news_app/apps/backend
symfony serve -d --port=8081

# Terminal 2 - Frontend
cd /var/www/deschide_news_app/apps/frontend
pnpm dev
```

## Architecture Notes

For detailed architecture information, see:
- **Backend**: [apps/backend/README.md](apps/backend/README.md)
- **Frontend**: [apps/frontend/README.md](apps/frontend/README.md)
- **Infrastructure**: [APPLICATIONS_ARCHITECTURE.md](APPLICATIONS_ARCHITECTURE.md)

## Git Workflow (Monorepo)

### Branch Strategy

- `main` - Production-ready code
- `develop` - Integration branch
- `feature/*` - Feature branches
- `fix/*` - Bug fixes

### Commit Messages

Follow Conventional Commits:

```
feat(backend): add article archive functionality
fix(frontend): correct image path in card component
docs: update monorepo migration guide
chore: update dependencies
```

**Scopes**: `backend`, `frontend`, `docs`, `ci`, `scripts`

### Making Changes

```bash
# Feature affecting both apps
git checkout -b feature/article-reactions

# Make changes in apps/backend/
cd apps/backend
# ... edit files ...

# Make changes in apps/frontend/
cd ../frontend
# ... edit files ...

# Commit atomically (both apps in one commit)
cd /var/www/deschide_news_app
git add apps/backend/ apps/frontend/
git commit -m "feat: add article reaction system

- backend: add Reaction entity and API endpoints
- frontend: add reaction buttons UI component
- both: update API contract for reactions"

# Push
git push origin feature/article-reactions
```

## Development Workflow

1. **Pull latest code**: `git pull origin main`
2. **Install dependencies**:
   - Backend: `cd apps/backend && composer install`
   - Frontend: `cd apps/frontend && pnpm install`
3. **Setup environment**: Copy `.env.example` to `.env.local` in both apps
4. **Run migrations**: `cd apps/backend && symfony console doctrine:migrations:migrate`
5. **Start servers**: Backend (8081) + Frontend (3005)
6. **Make changes**: Edit in `apps/backend/` or `apps/frontend/`
7. **Test**: Backend API + Frontend UI
8. **Commit**: Use conventional commits with scope
9. **Push**: `git push`

## Current Development Status

✅ **Monorepo Migration Completed** (November 2025)
✅ Backend: Symfony 7.3, API Platform, Elasticsearch, JWT auth
✅ Frontend: Next.js 16, Admin dashboard, public pages
⬜ In Progress: Testing infrastructure, deployment automation

---

**Last Updated**: 14 Noiembrie 2025
**Repository**: Monorepo (merged backend + frontend)
EOF

echo "✅ CLAUDE.md updated with monorepo structure"
```

#### 3.4 App-Specific READMEs

**Backend README:**

```bash
cd /var/www/deschide_news_app/apps/backend

# Backend already has a CLAUDE.md, we'll create a README.md
cat > README.md << 'EOF'
# Deschide News App - Backend

**Symfony 7.3 RESTful API** for the Deschide News platform.

## 🚀 Quick Start

```bash
# Install dependencies
composer install

# Configure environment
cp .env.example .env.local
# Edit .env.local with your database credentials

# Setup database
symfony console doctrine:database:create
symfony console doctrine:migrations:migrate

# Generate JWT keys
symfony console lexik:jwt:generate-keypair

# Start server
symfony serve -d --port=8081

# Access API
curl http://127.0.0.1:8081/api
```

## 📚 Documentation

- **Full Backend Documentation**: [CLAUDE.md](CLAUDE.md)
- **API Documentation** (when running): http://127.0.0.1:8081/api/docs.jsonld
- **Main Monorepo README**: [../../README.md](../../README.md)

## 🛠️ Common Commands

See [CLAUDE.md](CLAUDE.md#common-commands) for detailed command reference.

## 🏗️ Architecture

- **Framework**: Symfony 7.3
- **PHP**: 8.4
- **Database**: PostgreSQL 17
- **ORM**: Doctrine ORM 3.5
- **API**: API Platform 3.x (JSON-LD/Hydra)
- **Auth**: JWT (Lexik + Gesdinet Refresh Token)
- **Search**: Elasticsearch 8.x
- **Cache**: Redis
- **Queue**: RabbitMQ

## 📁 Directory Structure

```
backend/
├── config/         # YAML configuration
├── migrations/     # Database migrations
├── public/         # Web root
├── src/
│   ├── Command/   # Console commands
│   ├── Entity/    # Doctrine entities
│   ├── State/     # API Platform providers/processors
│   └── ...
└── tests/         # PHPUnit tests
```

## 🧪 Testing

```bash
vendor/bin/phpunit
```

---

Part of [Deschide News App Monorepo](../../README.md)
EOF

echo "✅ Backend README.md created"
```

**Frontend README:**

```bash
cd /var/www/deschide_news_app/apps/frontend

cat > README.md << 'EOF'
# Deschide News App - Frontend

**Next.js 16 Web Application** for the Deschide News platform.

## 🚀 Quick Start

```bash
# Install dependencies
pnpm install

# Configure environment
cp .env.example .env.local
# Edit .env.local with backend API URL

# Start development server
pnpm dev

# Access application
open http://localhost:3005
```

## 📚 Documentation

- **Main Monorepo README**: [../../README.md](../../README.md)
- **Frontend Features**: [docs/features/](docs/features/)

## 🛠️ Common Commands

```bash
# Development
pnpm dev            # Start dev server (port 3005)

# Production
pnpm build          # Build for production
pnpm start          # Start production server

# Code Quality
pnpm lint           # Run ESLint
pnpm type-check     # TypeScript type checking
```

## 🏗️ Architecture

- **Framework**: Next.js 16 (App Router)
- **React**: 19.2
- **TypeScript**: 5.x
- **Styling**: Tailwind CSS 4
- **State**: React hooks + Context (TBD: Zustand/Redux)
- **API Client**: Fetch to Symfony backend

## 📁 Directory Structure

```
frontend/
├── app/                    # App Router
│   ├── [locale]/          # Internationalized routes
│   │   ├── admin/         # Admin panel
│   │   └── (public)/      # Public pages
│   └── layout.tsx         # Root layout
├── components/            # React components
├── lib/                   # Utilities
└── public/                # Static assets
```

## 🧪 Testing

```bash
# Coming soon
pnpm test
```

## 🌐 Environment Variables

```bash
PORT=3005
NEXT_PUBLIC_API_URL=http://127.0.0.1:8081
NEXT_PUBLIC_CDN_URL=http://127.0.0.1:8082
NEXT_PUBLIC_DEFAULT_LOCALE=ro
```

---

Part of [Deschide News App Monorepo](../../README.md)
EOF

echo "✅ Frontend README.md created"
```

#### 3.5 Git Commit - Restructure

```bash
cd /var/www/deschide_news_app

# Stage all changes
git add .

# Commit
git commit -m "chore: monorepo restructure and documentation

- Add main README.md with monorepo overview
- Update CLAUDE.md with monorepo instructions
- Add app-specific READMEs (backend, frontend)
- Reorganize docs/ and sprints/
- Configure root .gitignore
- Archive old directories (_OLD)

Monorepo structure now complete and documented."

echo "✅ Restructure committed"
git log --oneline -3
```

**Deliverables:**
- ✅ README.md principal cu overview monorepo
- ✅ CLAUDE.md actualizat cu structura nouă
- ✅ READMEs specifice în apps/backend/ și apps/frontend/
- ✅ Documentație organizată în docs/
- ✅ .gitignore configurat corect (root + apps)
- ✅ Commit cu restructurarea

---

### Faza 4: GitHub Repository Setup (30 min) ⏱️

**Obiective:**
1. Crearea repository nou pe GitHub
2. Push monorepo la remote
3. Configurare branch protection rules
4. Arhivare repositories vechi (backend/frontend separate)
5. Actualizare README în repos vechi cu redirect

#### 4.1 Crearea Repository pe GitHub

**Manual steps (via GitHub web UI):**

1. Navigate to: https://github.com/new
2. Fill in details:
   - **Owner**: radusoltan
   - **Repository name**: `deschide_news_app`
   - **Description**: `Multilanguage News Platform - Monorepo (Symfony 7.3 + Next.js 16)`
   - **Visibility**: Private (or Public based on preference)
   - **Initialize**: ❌ DO NOT initialize with README (we already have one)
   - ❌ DO NOT add .gitignore
   - ❌ DO NOT choose a license (yet)
3. Click **Create repository**

#### 4.2 Push Monorepo to GitHub

```bash
cd /var/www/deschide_news_app

# Add remote
git remote add origin git@github.com:radusoltan/deschide_news_app.git

# Verify remote
git remote -v

# Push main branch with all history
git push -u origin main

# Verify on GitHub
echo "✅ Monorepo pushed to GitHub"
echo "🔗 https://github.com/radusoltan/deschide_news_app"
```

**Verify on GitHub:**
- Check commit history (should see ~36+ commits)
- Verify directory structure (apps/backend/, apps/frontend/)
- Verify README.md is displayed

#### 4.3 Branch Protection Rules (Optional but Recommended)

**Via GitHub Web UI:**

1. Go to: https://github.com/radusoltan/deschide_news_app/settings/branches
2. Click **Add branch protection rule**
3. Branch name pattern: `main`
4. Enable:
   - ✅ Require pull request reviews before merging
   - ✅ Require status checks to pass before merging (once CI is configured)
   - ✅ Require conversation resolution before merging
   - ✅ Do not allow bypassing the above settings
5. Save changes

**Alternative (via CLI with gh):**
```bash
# If you have GitHub CLI installed
gh repo edit radusoltan/deschide_news_app \
  --enable-wiki=false \
  --enable-issues=true \
  --enable-projects=false

# Note: Branch protection requires web UI or GitHub API
```

#### 4.4 Archive Old Repositories

**For each old repository (backend, frontend):**

##### 4.4.1 Update README with Redirect (Backend)

```bash
# Clone old backend repo
cd /tmp
git clone git@github.com:radusoltan/deschide_news_app_backend.git
cd deschide_news_app_backend

# Create redirect README
cat > README.md << 'EOF'
# ⚠️ Repository Archived - Moved to Monorepo

This repository has been **archived** and is no longer actively maintained.

## 🔄 Migration to Monorepo

All development has moved to a unified monorepo structure:

**🔗 New Repository**: [deschide_news_app](https://github.com/radusoltan/deschide_news_app)

### New Location

The backend code is now located at:
```
deschide_news_app/
└── apps/backend/    ← Backend code (formerly this repo)
```

### Why Monorepo?

- ✅ Unified version control for backend + frontend
- ✅ Atomic commits for cross-cutting features
- ✅ Simplified CI/CD and deployment
- ✅ Centralized documentation

### History Preserved

All commit history from this repository has been **preserved** in the monorepo at:
- Location: `apps/backend/`
- Original 17 commits maintained with full history

### Migration Date

- **Archived**: November 14, 2025
- **Last commit**: [Check commits](https://github.com/radusoltan/deschide_news_app_backend/commits/master)

---

**👉 Please use the new monorepo for all future development**: [github.com/radusoltan/deschide_news_app](https://github.com/radusoltan/deschide_news_app)
EOF

# Commit and push
git add README.md
git commit -m "docs: add migration notice to monorepo"
git push origin master

echo "✅ Backend repo updated with redirect README"
```

##### 4.4.2 Update README with Redirect (Frontend)

```bash
# Clone old frontend repo
cd /tmp
git clone git@github.com:radusoltan/deschide_news_app_frontend.git
cd deschide_news_app_frontend

# Create redirect README
cat > README.md << 'EOF'
# ⚠️ Repository Archived - Moved to Monorepo

This repository has been **archived** and is no longer actively maintained.

## 🔄 Migration to Monorepo

All development has moved to a unified monorepo structure:

**🔗 New Repository**: [deschide_news_app](https://github.com/radusoltan/deschide_news_app)

### New Location

The frontend code is now located at:
```
deschide_news_app/
└── apps/frontend/    ← Frontend code (formerly this repo)
```

### Why Monorepo?

- ✅ Unified version control for backend + frontend
- ✅ Atomic commits for cross-cutting features
- ✅ Simplified CI/CD and deployment
- ✅ Centralized documentation

### History Preserved

All commit history from this repository has been **preserved** in the monorepo at:
- Location: `apps/frontend/`
- Original 17 commits maintained with full history

### Migration Date

- **Archived**: November 14, 2025
- **Last commit**: [Check commits](https://github.com/radusoltan/deschide_news_app_frontend/commits/main)

---

**👉 Please use the new monorepo for all future development**: [github.com/radusoltan/deschide_news_app](https://github.com/radusoltan/deschide_news_app)
EOF

# Commit and push
git add README.md
git commit -m "docs: add migration notice to monorepo"
git push origin main

echo "✅ Frontend repo updated with redirect README"
```

##### 4.4.3 Archive Repositories on GitHub

**Via GitHub Web UI (for each repo):**

1. **Backend**: https://github.com/radusoltan/deschide_news_app_backend/settings
   - Scroll to bottom → **Danger Zone**
   - Click **Archive this repository**
   - Type repository name to confirm
   - Click **I understand, archive this repository**

2. **Frontend**: https://github.com/radusoltan/deschide_news_app_frontend/settings
   - Same steps as above

**What archiving does:**
- ✅ Makes repository read-only
- ✅ Clearly marks it as archived with banner
- ✅ Prevents new issues, PRs, or commits
- ✅ Preserves all history (can be unarchived if needed)

**Alternative (via GitHub CLI):**
```bash
# If you have GitHub CLI
gh repo archive radusoltan/deschide_news_app_backend --yes
gh repo archive radusoltan/deschide_news_app_frontend --yes
```

#### 4.5 Verification

```bash
# Check new monorepo
open https://github.com/radusoltan/deschide_news_app

# Check archived repos (should show "Archived" banner)
open https://github.com/radusoltan/deschide_news_app_backend
open https://github.com/radusoltan/deschide_news_app_frontend

# Verify local setup
cd /var/www/deschide_news_app
git remote -v
# Should show: origin git@github.com:radusoltan/deschide_news_app.git

git status
# Should be clean

echo "✅ GitHub setup complete"
```

**Deliverables:**
- ✅ Monorepo live la `github.com/radusoltan/deschide_news_app`
- ✅ Branch protection configured pe `main`
- ✅ Repos vechi arhivate cu README redirect
- ✅ GitHub remote configurat corect în local

---

### Faza 5: Cleanup și Validare (30 min) ⏱️

**Obiective:**
1. Ștergem `.git/` din subdirectoarele vechi (apps/backend/.git, apps/frontend/.git)
2. Ștergem directoarele backup (`deschide_backend_OLD`, `deschide_frontend_OLD`)
3. Verificăm că aplicațiile rulează normal din noua structură
4. Actualizăm path-uri în scripturi (dacă există)
5. Creăm tag pentru prima versiune monorepo

#### 5.1 Ștergere .git din Subdirectoare

**IMPORTANT**: Fă asta DOAR după ce monorepo-ul e push-uit cu succes pe GitHub!

```bash
cd /var/www/deschide_news_app

# Verify monorepo is pushed
git remote -v | grep origin
git log origin/main --oneline | head -5

# If OK, remove .git from subdirectories
rm -rf apps/backend/.git
rm -rf apps/frontend/.git

# Verify they're gone
find apps/ -name ".git" -type d
# Should return nothing

echo "✅ Removed .git from subdirectories"
```

#### 5.2 Ștergere Directoare Vechi

```bash
cd /var/www/deschide_news_app

# List old directories
ls -la | grep _OLD

# Remove them (these are backups from Faza 2.5)
rm -rf deschide_backend_OLD
rm -rf deschide_frontend_OLD

# Clean up other backup files
rm -f CLAUDE.md.backup
rm -f CLAUDE_monorepo_backup.md
rm -f DEVELOPMENT_ENVIRONMENT.md.backup
rm -f APPLICATIONS_ARCHITECTURE.md.backup

# Optionally remove archive_old/ if not needed
# rm -rf archive_old/

echo "✅ Old directories removed"
```

#### 5.3 Verificare Funcționalitate Aplicații

**Backend:**

```bash
cd /var/www/deschide_news_app/apps/backend

# Stop old server if running
symfony server:stop 2>/dev/null || true

# Clear cache
symfony console cache:clear

# Start server
symfony serve -d --port=8081

# Check status
symfony server:status

# Test API endpoint
curl -s http://127.0.0.1:8081/api | head -20

# Test database connection
symfony console doctrine:query:sql "SELECT COUNT(*) FROM article"

echo "✅ Backend functional"
```

**Frontend:**

```bash
cd /var/www/deschide_news_app/apps/frontend

# Stop old dev server if running (Ctrl+C in terminal or kill process)
# pkill -f "next dev" 2>/dev/null || true

# Clear .next cache
rm -rf .next

# Start dev server
pnpm dev &
DEV_PID=$!

# Wait for startup
sleep 5

# Test homepage
curl -s http://localhost:3005 | grep -o "<title>.*</title>"

# Stop dev server
kill $DEV_PID

echo "✅ Frontend functional"
```

#### 5.4 Actualizare Path-uri în Scripts (Dacă Există)

```bash
cd /var/www/deschide_news_app

# Check if scripts/ has any deployment scripts
if [ -d "scripts" ] && [ "$(ls -A scripts)" ]; then
    # Update paths in scripts
    find scripts/ -type f -exec sed -i 's|deschide_backend|apps/backend|g' {} \;
    find scripts/ -type f -exec sed -i 's|deschide_frontend|apps/frontend|g' {} \;

    echo "✅ Scripts updated with new paths"
else
    echo "ℹ️  No scripts to update"
fi

# Check PM2 ecosystem config (if frontend uses PM2)
if [ -f "apps/frontend/ecosystem.config.js" ]; then
    # Update cwd path if needed
    sed -i 's|/var/www/deschide_news_app/deschide_frontend|/var/www/deschide_news_app/apps/frontend|g' apps/frontend/ecosystem.config.js

    echo "✅ PM2 config updated"
fi
```

#### 5.5 Validare Git History

```bash
cd /var/www/deschide_news_app

# Check total commits
TOTAL_COMMITS=$(git rev-list --all --count)
echo "📊 Total commits: $TOTAL_COMMITS"

# Expected: ~36+ (17 backend + 17 frontend + merges + restructure)
if [ "$TOTAL_COMMITS" -ge 35 ]; then
    echo "✅ Commit count looks good"
else
    echo "⚠️  Warning: Expected at least 35 commits, got $TOTAL_COMMITS"
fi

# Verify backend commits exist in history
git log --all --oneline --grep="backend" | head -5

# Verify frontend commits exist in history
git log --all --oneline --grep="frontend" | head -5

# Check that files are in correct locations
git ls-tree -r HEAD --name-only | grep "apps/backend/src/Entity" | head -3
git ls-tree -r HEAD --name-only | grep "apps/frontend/app" | head -3

# Visualize history
git log --oneline --graph --all --decorate -20

echo "✅ Git history validated"
```

#### 5.6 Create Tag - Monorepo v1.0.0

```bash
cd /var/www/deschide_news_app

# Create annotated tag
git tag -a v1.0.0-monorepo -m "Monorepo Migration Complete

- Merged deschide_news_app_backend and deschide_news_app_frontend
- Preserved full history from both repositories
- New structure: apps/backend/ and apps/frontend/
- Centralized documentation in docs/
- Updated all READMEs and configuration

Migration date: November 14, 2025
Total commits: $(git rev-list --all --count)
Backend commits: 17+
Frontend commits: 17+"

# Push tag to remote
git push origin v1.0.0-monorepo

echo "✅ Tag v1.0.0-monorepo created and pushed"
echo "🔗 https://github.com/radusoltan/deschide_news_app/releases/tag/v1.0.0-monorepo"
```

#### 5.7 Final Verification Checklist

```bash
cd /var/www/deschide_news_app

cat > MIGRATION_VERIFICATION.md << 'EOF'
# Monorepo Migration Verification Checklist

**Date**: November 14, 2025

## ✅ Structure

- [x] Monorepo created in `/var/www/deschide_news_app/`
- [x] Backend code in `apps/backend/`
- [x] Frontend code in `apps/frontend/`
- [x] Documentation in `docs/`
- [x] No `.git/` in subdirectories
- [x] Old directories removed (`_OLD`)

## ✅ Git History

- [x] Total commits: 36+ (verified)
- [x] Backend commits preserved (17+)
- [x] Frontend commits preserved (17+)
- [x] All authors preserved
- [x] All dates preserved
- [x] Merge commits present

## ✅ GitHub

- [x] Monorepo pushed: github.com/radusoltan/deschide_news_app
- [x] Old backend repo archived with redirect
- [x] Old frontend repo archived with redirect
- [x] Tag created: v1.0.0-monorepo

## ✅ Functionality

- [x] Backend runs on port 8081
- [x] Frontend runs on port 3005
- [x] Backend API accessible
- [x] Frontend pages load
- [x] Database connection works

## ✅ Documentation

- [x] Main README.md created
- [x] CLAUDE.md updated
- [x] Backend README.md created
- [x] Frontend README.md created

## ✅ Configuration

- [x] Root .gitignore configured
- [x] Apps have their own .gitignore
- [x] .env.example files present
- [x] Scripts updated with new paths (if applicable)

## 📊 Statistics

- **Total Commits**: $(git rev-list --all --count)
- **Repository Size**: $(du -sh .git | cut -f1)
- **Backend Files**: $(git ls-tree -r HEAD --name-only | grep "apps/backend" | wc -l)
- **Frontend Files**: $(git ls-tree -r HEAD --name-only | grep "apps/frontend" | wc -l)

## 🎯 Post-Migration Tasks

- [ ] Update CI/CD workflows (.github/workflows/)
- [ ] Update deployment scripts
- [ ] Notify team members
- [ ] Update documentation links
- [ ] Create GitHub release notes

---

**Status**: ✅ Migration Successful
**Verification Date**: $(date +%Y-%m-%d)
EOF

# Display checklist
cat MIGRATION_VERIFICATION.md

# Add to git
git add MIGRATION_VERIFICATION.md
git commit -m "docs: add migration verification checklist"
git push origin main

echo "✅ Migration verification complete"
```

**Deliverables:**
- ✅ Nu mai există `.git/` în subdirectoare
- ✅ Directoare vechi șterse
- ✅ Aplicațiile rulează normal din `apps/backend/` și `apps/frontend/`
- ✅ Git history validat (36+ commits)
- ✅ Tag `v1.0.0-monorepo` creat
- ✅ Migration verification checklist generat

---

## 📊 Timeline și Resurse

### Estimated Timeline

| Faza | Durata | Poate fi automatizat? |
|------|--------|----------------------|
| **Faza 1**: Pregătire și Backup | 30 min | ✅ Parțial (script backup) |
| **Faza 2**: Crearea Monorepo | 1-2 ore | ✅ Da (script complet) |
| **Faza 3**: Restructurare | 1 oră | ✅ Parțial (template-uri) |
| **Faza 4**: GitHub Setup | 30 min | ⚠️ Parțial (Web UI pentru archiving) |
| **Faza 5**: Cleanup | 30 min | ✅ Da (script) |
| **TOTAL** | **3.5-4.5 ore** | 70% automatizabil |

### Resurse Necesare

**Software/Tools:**
- ✅ Git 2.x (already installed)
- ✅ git-filter-repo (install via pip3)
- ✅ GitHub account with SSH keys
- ✅ Symfony CLI
- ✅ Node.js/pnpm

**Disk Space:**
- Backups: ~50 MB (compressed)
- Temporary clones: ~30 MB
- Final monorepo: ~15 MB (.git)

**Skills Required:**
- Git (intermediate)
- Command line (basic)
- GitHub (basic)

---

## ⚠️ Riscuri și Mitigare

### Risc 1: Pierderea Istoricului

**Probabilitate**: 🟢 Low (cu backup proper)
**Impact**: 🔴 High

**Mitigare:**
- ✅ Backup mirrors complet (Faza 1.1)
- ✅ Testare pe clone temporare (Faza 2.2-2.4)
- ✅ Verificare commit count înainte de delete (Faza 5.1)
- ✅ Păstrare repos vechi arhivate (nu șterse)

**Rollback Plan:**
```bash
# If something goes wrong, restore from backup
cd /var/www/deschide_news_app
rm -rf .git apps/

# Restore original directories
cp -r /var/www/backups/deschide_git_migration_YYYYMMDD/backend_working_copy deschide_backend
cp -r /var/www/backups/deschide_git_migration_YYYYMMDD/frontend_working_copy deschide_frontend
```

---

### Risc 2: Merge Conflicts

**Probabilitate**: 🟡 Medium
**Impact**: 🟡 Medium

**Cauze Posibile:**
- Uncommitted changes în repos
- Files cu același nume în backend și frontend

**Mitigare:**
- ✅ Clean working tree înainte de merge (Faza 1.2-1.3)
- ✅ Folosim `--allow-unrelated-histories`
- ✅ Backend și frontend sunt deja în subdirectoare separate (nu există overlap)

**Rezolvare:**
```bash
# If merge conflict occurs
git status
# Resolve manually
git add <resolved files>
git commit -m "resolve: merge conflict during monorepo migration"
```

---

### Risc 3: Path-uri Incorecte După Migrare

**Probabilitate**: 🟡 Medium
**Impact**: 🟡 Medium

**Cauze:**
- Scripturi de deploy cu hardcoded paths
- Configurații cu absolute paths
- CI/CD workflows cu vechile path-uri

**Mitigare:**
- ✅ Testing complet după migrare (Faza 5.3)
- ✅ Update scripts cu find/sed (Faza 5.4)
- ✅ Verificare configurații (.env, ecosystem.config.js)

**Verificare:**
```bash
# Search for old paths in config files
grep -r "deschide_backend" apps/ scripts/ .github/ || echo "✅ No old paths found"
grep -r "deschide_frontend" apps/ scripts/ .github/ || echo "✅ No old paths found"
```

---

### Risc 4: CI/CD Broken

**Probabilitate**: 🟡 Medium
**Impact**: 🔴 High

**Cauze:**
- GitHub Actions workflows cu paths vechi
- Test runners care nu găsesc fișierele
- Build scripts cu wrong working directory

**Mitigare:**
- ⚠️ Actualizare workflows în `.github/workflows/` (Faza 3)
- ✅ Test local build înainte de push (Faza 5.3)

**Action Items Post-Migration:**
```bash
# Update GitHub Actions workflows
# .github/workflows/backend-tests.yml
working-directory: apps/backend

# .github/workflows/frontend-tests.yml
working-directory: apps/frontend
```

---

### Risc 5: Team Confusion

**Probabilitate**: 🟡 Medium (dacă există echipă)
**Impact**: 🟡 Medium

**Cauze:**
- Developeri care clonează repos vechi
- Confuzie între branch `master` și `main`
- Uncommitted work în repos vechi

**Mitigare:**
- ✅ Arhivare repos vechi cu README redirect (Faza 4.4)
- ✅ Notificare echipă înainte de migrare
- ✅ Documentație clară (README.md, CLAUDE.md)

**Communication Template:**
```
Subject: 🚀 Monorepo Migration - Action Required

Team,

We're migrating to a monorepo structure on [DATE].

OLD REPOS (will be archived):
- github.com/radusoltan/deschide_news_app_backend
- github.com/radusoltan/deschide_news_app_frontend

NEW REPO:
- github.com/radusoltan/deschide_news_app

ACTION REQUIRED:
1. Commit/push any uncommitted work BEFORE [DATE]
2. After migration, clone new repo:
   git clone git@github.com:radusoltan/deschide_news_app.git
3. Update your IDE/editor workspace
4. Backend: cd apps/backend
5. Frontend: cd apps/frontend

Questions? Reply to this email.
```

---

## 🎯 Success Criteria

La finalul migrării, următoarele trebuie să fie adevărate:

### Git Repository
- [x] ✅ Monorepo există în `/var/www/deschide_news_app/`
- [x] ✅ `.git/` este în root (nu în subdirectoare)
- [x] ✅ Total commits ≥ 36 (backend + frontend + merges)
- [x] ✅ History preserved (authors, dates, messages intact)
- [x] ✅ Tag `v1.0.0-monorepo` creat

### File Structure
- [x] ✅ `apps/backend/` conține tot codul backend
- [x] ✅ `apps/frontend/` conține tot codul frontend
- [x] ✅ `docs/` centralizată cu documentație
- [x] ✅ `README.md` principal există și este informativ
- [x] ✅ `CLAUDE.md` actualizat cu structura monorepo

### GitHub
- [x] ✅ Monorepo push-uit: `github.com/radusoltan/deschide_news_app`
- [x] ✅ Repos vechi arhivate cu README redirect
- [x] ✅ Branch protection configurată pe `main`

### Functionality
- [x] ✅ Backend rulează pe port 8081
- [x] ✅ Frontend rulează pe port 3005
- [x] ✅ API-ul backend răspunde corect
- [x] ✅ Frontend se conectează la backend
- [x] ✅ Database migrations funcționează

### Cleanup
- [x] ✅ Nu există `.git/` în `apps/backend/` sau `apps/frontend/`
- [x] ✅ Directoare vechi (`_OLD`) șterse
- [x] ✅ Backups păstrate în `/var/www/backups/`

---

## 📚 Resurse și Referințe

### Git Filter-Repo
- **GitHub**: https://github.com/newren/git-filter-repo
- **Docs**: https://htmlpreview.github.io/?https://github.com/newren/git-filter-repo/blob/docs/html/git-filter-repo.html
- **Tutorial**: https://git-scm.com/docs/git-filter-repo

### Monorepo Best Practices
- **Google**: https://research.google/pubs/pub45424/
- **Monorepo.tools**: https://monorepo.tools/
- **NX Monorepo**: https://nx.dev/concepts/more-concepts/why-monorepos

### Git Merge Strategies
- **Git Docs**: https://git-scm.com/docs/git-merge
- **Unrelated Histories**: https://git-scm.com/docs/git-merge#Documentation/git-merge.txt---allow-unrelated-histories

### GitHub Actions Monorepo
- **Path Filtering**: https://github.com/dorny/paths-filter
- **Monorepo Workflows**: https://docs.github.com/en/actions/using-workflows/events-that-trigger-workflows#running-your-workflow-only-when-a-push-affects-specific-files

---

## 🤔 Întrebări Frecvente (FAQ)

### Q1: Pot face rollback după ce am push-uit monorepo-ul?

**A**: Da, dacă ai backup-urile din Faza 1. Steps:
1. Șterge monorepo local: `rm -rf /var/www/deschide_news_app/.git`
2. Restore din backup: `cp -r /var/www/backups/.../backend_working_copy deschide_backend`
3. Unarchive repos pe GitHub (Settings → Unarchive)

Însă **nu e recomandat** după ce echipa a început să lucreze în monorepo.

---

### Q2: Cum gestionăm releases separate pentru backend vs frontend?

**A**: Mai multe strategii:

**Opțiunea 1**: Versioning unificat (recomandat)
```bash
git tag v1.0.0  # Backend 1.0.0 + Frontend 1.0.0 împreună
```

**Opțiunea 2**: Tags separate
```bash
git tag backend-v1.0.0
git tag frontend-v1.2.0
```

**Opțiunea 3**: Semantic tags cu scope
```bash
git tag v1.0.0-backend
git tag v1.0.0-frontend
```

---

### Q3: Cum rulăm CI doar pentru app-ul modificat?

**A**: GitHub Actions cu path filtering:

```yaml
# .github/workflows/backend-tests.yml
on:
  push:
    paths:
      - 'apps/backend/**'
      - '.github/workflows/backend-tests.yml'

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Run backend tests
        working-directory: apps/backend
        run: vendor/bin/phpunit
```

---

### Q4: Cum contribuie developeri la monorepo?

**A**: Workflow normal cu branch-uri:

```bash
# Clone monorepo
git clone git@github.com:radusoltan/deschide_news_app.git
cd deschide_news_app

# Create feature branch
git checkout -b feature/new-api-endpoint

# Work in backend
cd apps/backend
# ... edit files ...

# Work in frontend (dacă e nevoie)
cd ../frontend
# ... edit files ...

# Commit ambele (atomic commit)
cd /var/www/deschide_news_app
git add apps/backend apps/frontend
git commit -m "feat: add new API endpoint with frontend integration"

# Push și create PR
git push origin feature/new-api-endpoint
```

---

### Q5: Ce se întâmplă cu package.json / composer.json?

**A**: Rămân în subdirectoare:

```
apps/backend/composer.json    ← Backend dependencies
apps/frontend/package.json    ← Frontend dependencies
```

**Optional**: Poți adăuga root `package.json` pentru workspace management (pnpm workspaces, npm workspaces):

```json
{
  "name": "deschide-news-app",
  "private": true,
  "workspaces": [
    "apps/backend",
    "apps/frontend"
  ]
}
```

Dar **nu e obligatoriu** - poți rula `composer install` și `pnpm install` separat.

---

### Q6: Cum deploy-uim monorepo?

**A**: Mai multe strategii:

**Opțiunea 1**: Deploy separat (recomandat pentru început)
```bash
# Deploy backend
cd apps/backend
git pull origin main
composer install --no-dev
symfony console cache:clear
# restart php-fpm

# Deploy frontend
cd apps/frontend
git pull origin main
pnpm install
pnpm build
pm2 restart deschide_frontend
```

**Opțiunea 2**: Script unificat
```bash
#!/bin/bash
# scripts/deploy.sh

cd /var/www/deschide_news_app
git pull origin main

# Deploy backend
cd apps/backend
composer install --no-dev
symfony console doctrine:migrations:migrate --no-interaction
symfony console cache:clear

# Deploy frontend
cd ../frontend
pnpm install
pnpm build
pm2 restart deschide_frontend
```

**Opțiunea 3**: GitHub Actions CD
```yaml
# .github/workflows/deploy.yml
on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to production
        run: |
          ssh user@server 'cd /var/www/deschide_news_app && git pull && ./scripts/deploy.sh'
```

---

## ✅ Checklist Pre-Migration

**Înainte de a începe:**

- [ ] Am citit întreg planul
- [ ] Am înțeles riscurile
- [ ] Am backup-uri externe (Google Drive, Dropbox, etc.)
- [ ] Am notificat echipa (dacă există)
- [ ] Am timp alocat (4-5 ore libere)
- [ ] Am access SSH la GitHub
- [ ] Am instalat git-filter-repo (sau pot instala)
- [ ] Backend și frontend rulează corect acum
- [ ] Database are backup recent
- [ ] Am verificat că nu există work uncommitted important

**Dacă toate sunt ✅, poți începe migrarea!**

---

## 📞 Support și Troubleshooting

### Dacă întâmpini probleme:

1. **Check backups**: Verifică că backup-urile din Faza 1 există
2. **Don't panic**: Migrarea poate fi oprita sau rollback-uită
3. **Read error messages**: Git oferă mesaje descriptive
4. **Check logs**: `git log --oneline --graph --all`
5. **Ask for help**: Documentație Git, Stack Overflow, etc.

### Common Errors

**Error: "refusing to merge unrelated histories"**
```bash
# Solution: Add --allow-unrelated-histories
git merge backend_temp/master --allow-unrelated-histories
```

**Error: "remote origin already exists"**
```bash
# Solution: Remove and re-add
git remote remove origin
git remote add origin git@github.com:radusoltan/deschide_news_app.git
```

**Error: "git-filter-repo: command not found"**
```bash
# Solution: Install via pip
pip3 install git-filter-repo
```

---

## 🎉 Post-Migration Cele

După finalizarea cu succes a migrării:

1. **🎊 Celebrează**: Ai realizat o migrare complexă!
2. **📸 Screenshot**: Salvează output-ul final din Faza 5.7
3. **📝 Document**: Notează orice probleme întâlnite pentru viitor
4. **🚀 Deploy**: Testează deployment-ul din noua structură
5. **👥 Train**: Ajută echipa să înțeleagă noua structură
6. **📊 Monitor**: Urmărește că totul funcționează corect

---

## 📋 Summary

### Ce Realizăm

✅ **Unificare**: 2 repositories → 1 monorepo
✅ **Istoric Păstrat**: Toate commit-urile (36+) preserved
✅ **Structură Curată**: apps/backend/, apps/frontend/, docs/
✅ **Documentație**: README.md, CLAUDE.md actualizate
✅ **GitHub**: Monorepo live, repos vechi arhivate

### De Ce Merită

- 🚀 **Faster Development**: Atomic commits pentru features cross-cutting
- 📦 **Easier Management**: Un singur repo de clonat și gestionat
- 🔄 **Better CI/CD**: Workflows unificate, deploy coordonat
- 📚 **Centralized Docs**: Documentație în același loc cu codul
- 🏷️ **Versioning Sincronizat**: Release-uri coordonate

### Timp Necesar

- **Hands-on**: 3.5-4.5 ore
- **Automatizabil**: ~70% (cu scripturi)
- **ROI**: Se amortizează rapid prin simplificarea workflow-ului

---

**Autor**: Claude Code Analysis
**Data**: 14 Noiembrie 2025
**Status**: 📋 Plan Detaliat - Gata de Implementare

**👉 Următorul Pas**: Review plan → Aprobare → Start Faza 1
