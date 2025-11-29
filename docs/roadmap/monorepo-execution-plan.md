# Monorepo Execution Plan

**Project:** Deschide News App Monorepo
**Location:** `/var/www/deschide_news_app/`
**Created:** 2025-11-29
**Status:** Ready for Execution
**Total Phases:** 5
**Total Tasks:** 32
**Estimated Duration:** 4-6 hours

---

## Executive Summary

This execution plan provides a step-by-step guide for restructuring the Deschide News App monorepo based on findings from two comprehensive reports:

1. **Git Repository Optimization Report** - identified 244MB of unnecessary files, 59 uncommitted changes, and .gitignore gaps
2. **Monorepo Restructuring Strategy** - defined documentation reorganization and cleanup procedures

### Key Objectives

| Objective | Current State | Target State | Priority |
|-----------|---------------|--------------|----------|
| Repository untracked size | 244MB | 0MB | CRITICAL |
| Uncommitted files | 59 files | 0 files | HIGH |
| .gitignore completeness | 60% | 95% | HIGH |
| Obsolete documentation | 12 files | 0 files | MEDIUM |
| Documentation structure | Ad-hoc | 5 categories | MEDIUM |

### Expected Outcomes

- **245MB of disk space recovered** (layouts/, .playwright-mcp/, context/)
- **59 files organized** into 12 logical commit groups
- **12 obsolete files removed** from documentation
- **5 clear documentation categories** established
- **Repository health score improved** from 7/10 to 9/10

---

## Phase Overview

```
PHASE 1: Preparation (30 min)
    |
    v
PHASE 2: Cleanup & .gitignore (45 min)
    |
    v
PHASE 3: Documentation Reorganization (45 min)
    |
    v
PHASE 4: Code Commits (2-3 hours)
    |
    v
PHASE 5: Validation & Finalization (30 min)
```

---

## Phase 1: Preparation

**Duration:** 30 minutes
**Lead Agent:** general-purpose (Explore/Plan)
**Objective:** Create safety nets and verify current state

### Task PHASE1-TASK01: Create Full Backup

**Agent:** general-purpose
**Priority:** CRITICAL
**Duration:** 10 min

**Input:**
- Current repository at `/var/www/deschide_news_app/`

**Actions:**
```bash
# Create timestamped backup
cd /var/www/deschide_news_app
mkdir -p ~/backups/deschide_news

# Full backup excluding large runtime directories
tar -czvf ~/backups/deschide_news/pre_restructure_$(date +%Y%m%d_%H%M%S).tar.gz \
  --exclude=node_modules \
  --exclude=vendor \
  --exclude=.git \
  --exclude=var \
  --exclude=layouts \
  --exclude=.playwright-mcp \
  .

# Verify backup
ls -lh ~/backups/deschide_news/
```

**Output:**
- Backup file at `~/backups/deschide_news/pre_restructure_YYYYMMDD_HHMMSS.tar.gz`
- Backup size verified (should be < 50MB)

**Dependencies:** None

**Acceptance Criteria:**
- [ ] Backup file created successfully
- [ ] Backup file is readable (tar -tzf test)
- [ ] Backup size is reasonable (< 50MB)

---

### Task PHASE1-TASK02: Verify Current State

**Agent:** general-purpose
**Priority:** HIGH
**Duration:** 5 min

**Input:**
- Git status output
- Disk usage statistics

**Actions:**
```bash
cd /var/www/deschide_news_app

# Document current state
echo "=== GIT STATUS ===" > /tmp/pre_restructure_state.txt
git status >> /tmp/pre_restructure_state.txt

echo -e "\n=== DISK USAGE ===" >> /tmp/pre_restructure_state.txt
du -sh . >> /tmp/pre_restructure_state.txt
du -sh layouts/ .playwright-mcp/ context/ 2>/dev/null >> /tmp/pre_restructure_state.txt

echo -e "\n=== UNTRACKED FILES COUNT ===" >> /tmp/pre_restructure_state.txt
git status --porcelain | wc -l >> /tmp/pre_restructure_state.txt

# Display summary
cat /tmp/pre_restructure_state.txt
```

**Output:**
- State snapshot at `/tmp/pre_restructure_state.txt`
- Baseline metrics documented

**Dependencies:** None

**Acceptance Criteria:**
- [ ] Current state documented
- [ ] 59 files confirmed in working directory
- [ ] Large directories identified (layouts: ~230MB, .playwright-mcp: ~14MB)

---

### Task PHASE1-TASK03: Create Rollback Script

**Agent:** git-flow-manager
**Priority:** HIGH
**Duration:** 5 min

**Input:**
- Backup location from PHASE1-TASK01

**Actions:**
```bash
cat > ~/backups/deschide_news/rollback.sh << 'EOF'
#!/bin/bash
# Rollback script for Deschide News App restructuring
# Usage: ./rollback.sh

set -e

BACKUP_DIR=~/backups/deschide_news
TARGET_DIR=/var/www/deschide_news_app

echo "Finding latest backup..."
LATEST_BACKUP=$(ls -t $BACKUP_DIR/pre_restructure_*.tar.gz | head -1)

if [ -z "$LATEST_BACKUP" ]; then
    echo "ERROR: No backup found!"
    exit 1
fi

echo "Latest backup: $LATEST_BACKUP"
read -p "Restore from this backup? (y/N) " confirm

if [ "$confirm" != "y" ]; then
    echo "Rollback cancelled."
    exit 0
fi

echo "Restoring backup..."
cd $TARGET_DIR
# Note: This restores files but preserves .git directory
tar -xzvf $LATEST_BACKUP --skip-old-files

echo "Rollback complete. Verify with: git status"
EOF

chmod +x ~/backups/deschide_news/rollback.sh
```

**Output:**
- Executable rollback script at `~/backups/deschide_news/rollback.sh`

**Dependencies:** PHASE1-TASK01

**Acceptance Criteria:**
- [ ] Rollback script created and executable
- [ ] Script correctly references backup location
- [ ] Script has safety confirmation prompt

---

### Task PHASE1-TASK04: Create Git Stash (Safety Net)

**Agent:** git-flow-manager
**Priority:** MEDIUM
**Duration:** 5 min

**Input:**
- Current modified files in working directory

**Actions:**
```bash
cd /var/www/deschide_news_app

# Create named stash with all changes (including untracked)
git stash push -u -m "pre-restructure-backup-$(date +%Y%m%d_%H%M%S)"

# List stashes to confirm
git stash list

# Note: We'll immediately pop this after verification
# This is just a secondary safety mechanism
git stash pop
```

**Output:**
- Stash entry created (if needed for emergency rollback)
- Working directory restored to current state

**Dependencies:** PHASE1-TASK02

**Acceptance Criteria:**
- [ ] Stash operation successful
- [ ] Working directory state preserved
- [ ] Stash can be listed with `git stash list`

---

### Task PHASE1-TASK05: Document MCP Decision

**Agent:** general-purpose
**Priority:** MEDIUM
**Duration:** 5 min

**Input:**
- `.mcp.json` file content
- Team development practices

**Actions:**
```bash
cd /var/www/deschide_news_app

# Check if .mcp.json exists and its content
cat .mcp.json 2>/dev/null || echo "File not found"

# Decision matrix:
# - If Playwright MCP is used by all developers -> COMMIT
# - If personal configuration only -> ADD TO .gitignore
```

**Output:**
- Decision documented: COMMIT or IGNORE `.mcp.json`

**Dependencies:** None

**Acceptance Criteria:**
- [ ] MCP configuration reviewed
- [ ] Decision made and documented
- [ ] Decision aligned with team practices

**Recommendation:** COMMIT if Playwright is standard testing tool for all developers

---

## Phase 2: Cleanup and .gitignore Update

**Duration:** 45 minutes
**Lead Agent:** performance-optimizer:performance-engineer
**Objective:** Remove non-essential files and update .gitignore

### Task PHASE2-TASK01: Update Root .gitignore

**Agent:** performance-optimizer:performance-engineer
**Priority:** CRITICAL
**Duration:** 15 min

**Input:**
- Current `.gitignore` file
- Recommended patterns from Git Repository Optimization Report

**Actions:**
```bash
cd /var/www/deschide_news_app

# Backup current .gitignore
cp .gitignore .gitignore.backup

# Create new comprehensive .gitignore
cat > .gitignore << 'EOF'
# ==============================================================================
# Deschide News App Monorepo - Root .gitignore
# ==============================================================================

# ------------------------------------------------------------------------------
# IDEs & Editors
# ------------------------------------------------------------------------------
.idea/
.vscode/
*.sublime-project
*.sublime-workspace
.fleet/

# ------------------------------------------------------------------------------
# Operating Systems
# ------------------------------------------------------------------------------
.DS_Store
.DS_Store?
._*
.Spotlight-V100
.Trashes
ehthumbs.db
Thumbs.db
Desktop.ini
*.swp
*.swo
*~

# ------------------------------------------------------------------------------
# Environment & Secrets (Monorepo Level)
# ------------------------------------------------------------------------------
.env.local
.env*.local
.env.backup
*.pem
*.key
*.crt

# ------------------------------------------------------------------------------
# Testing & QA Artifacts (CRITICAL)
# ------------------------------------------------------------------------------
.playwright-mcp/
**/screenshots/
**/test-results/
**/.playwright/
coverage/
.nyc_output/
**/__snapshots__/
*.spec.js.snap
playwright-report/
test-results/

# ------------------------------------------------------------------------------
# UI/UX Layout References (CRITICAL - 230MB)
# ------------------------------------------------------------------------------
layouts/
# Note: Reference templates only - see docs/UI_TEMPLATES.md for sources

# ------------------------------------------------------------------------------
# Context & Implementation Notes (Temporary)
# ------------------------------------------------------------------------------
context/
.context/
*.context.md
notes/
scratch/
TODO.local.md

# ------------------------------------------------------------------------------
# Build Artifacts (Monorepo Level)
# ------------------------------------------------------------------------------
/node_modules/
/dist/
/build/
/out/

# ------------------------------------------------------------------------------
# Package Manager Locks (Monorepo)
# ------------------------------------------------------------------------------
# Note: Apps have their own lock files
/pnpm-lock.yaml
/package-lock.json
/yarn.lock

# ------------------------------------------------------------------------------
# Archives & Backups
# ------------------------------------------------------------------------------
*.backup
*_OLD/
archive_old/
*.tar.gz
*.zip
*.rar

# Note: archive/ contains historical migration docs - KEEP COMMITTED

# ------------------------------------------------------------------------------
# Logs
# ------------------------------------------------------------------------------
*.log
npm-debug.log*
yarn-debug.log*
yarn-error.log*
pnpm-debug.log*
lerna-debug.log*

# ------------------------------------------------------------------------------
# Temporary Files
# ------------------------------------------------------------------------------
*.tmp
*.temp
.cache/
.parcel-cache/
.turbo/

# ------------------------------------------------------------------------------
# AI/LLM Development Tools
# ------------------------------------------------------------------------------
.cursor/
.claude-cache/
# .mcp.json - INCLUDED: Standard dev tool for Playwright testing

# ------------------------------------------------------------------------------
# Database Files (Development)
# ------------------------------------------------------------------------------
*.db
*.sqlite
*.sqlite3

# ------------------------------------------------------------------------------
# OS & Editor Specific
# ------------------------------------------------------------------------------
.vscode/settings.json
.vscode/launch.json
.idea/workspace.xml
.idea/usage.statistics.xml
EOF

echo "New .gitignore created. Review with: diff .gitignore.backup .gitignore"
```

**Output:**
- Updated `.gitignore` file with comprehensive patterns
- Backup at `.gitignore.backup`

**Dependencies:** PHASE1-TASK01, PHASE1-TASK02

**Acceptance Criteria:**
- [ ] All critical patterns added (layouts/, .playwright-mcp/, context/)
- [ ] Testing artifact patterns included
- [ ] Monorepo-specific patterns added
- [ ] .claude/ NOT in .gitignore (agent configs should be committed)
- [ ] archive/ NOT in .gitignore (historical docs are valuable)

---

### Task PHASE2-TASK02: Remove layouts/ Directory

**Agent:** performance-optimizer:performance-engineer
**Priority:** CRITICAL
**Duration:** 5 min

**Input:**
- `layouts/` directory (~230MB)

**Actions:**
```bash
cd /var/www/deschide_news_app

# Verify size before removal
du -sh layouts/ 2>/dev/null || echo "layouts/ not found"

# Remove UI template references (not application code)
rm -rf layouts/

# Verify removal
ls -la layouts/ 2>/dev/null && echo "ERROR: Directory still exists" || echo "SUCCESS: layouts/ removed"
```

**Output:**
- ~230MB of disk space recovered
- `layouts/` directory removed

**Dependencies:** PHASE2-TASK01 (.gitignore must be updated first)

**Acceptance Criteria:**
- [ ] layouts/ directory removed completely
- [ ] ~230MB recovered
- [ ] No errors during removal

---

### Task PHASE2-TASK03: Remove .playwright-mcp/ Directory

**Agent:** performance-optimizer:performance-engineer
**Priority:** CRITICAL
**Duration:** 5 min

**Input:**
- `.playwright-mcp/` directory (~14MB)

**Actions:**
```bash
cd /var/www/deschide_news_app

# Verify size before removal
du -sh .playwright-mcp/ 2>/dev/null || echo ".playwright-mcp/ not found"

# Remove testing artifacts
rm -rf .playwright-mcp/

# Verify removal
ls -la .playwright-mcp/ 2>/dev/null && echo "ERROR: Directory still exists" || echo "SUCCESS: .playwright-mcp/ removed"
```

**Output:**
- ~14MB of disk space recovered
- `.playwright-mcp/` directory removed

**Dependencies:** PHASE2-TASK01

**Acceptance Criteria:**
- [ ] .playwright-mcp/ directory removed completely
- [ ] ~14MB recovered
- [ ] No errors during removal

---

### Task PHASE2-TASK04: Process context/ Directory

**Agent:** docusaurus-expert
**Priority:** MEDIUM
**Duration:** 10 min

**Input:**
- `context/` directory contents

**Actions:**
```bash
cd /var/www/deschide_news_app

# List context contents
ls -la context/ 2>/dev/null || echo "context/ not found"

# Check for valuable content to migrate
if [ -f "context/design-principles.md" ]; then
    mkdir -p docs/architecture
    mv context/design-principles.md docs/architecture/
    echo "Migrated: design-principles.md -> docs/architecture/"
fi

# Archive or remove remaining context files
if [ -d "context/" ]; then
    # Option 1: Archive valuable content
    # mkdir -p archive/context_backup
    # mv context/* archive/context_backup/

    # Option 2: Remove (if content is truly temporary)
    rm -rf context/
    echo "Removed: context/"
fi
```

**Output:**
- Valuable content migrated to `docs/architecture/`
- Temporary files removed
- `context/` directory removed

**Dependencies:** PHASE2-TASK01

**Acceptance Criteria:**
- [ ] design-principles.md migrated if exists
- [ ] Temporary context files removed
- [ ] context/ directory removed

---

### Task PHASE2-TASK05: Commit .gitignore Update

**Agent:** git-flow-manager
**Priority:** HIGH
**Duration:** 5 min

**Input:**
- Updated `.gitignore` file
- Removed directories confirmation

**Actions:**
```bash
cd /var/www/deschide_news_app

# Stage .gitignore
git add .gitignore

# Commit .gitignore update
git commit -m "chore: update .gitignore for monorepo patterns

- Add testing artifact patterns (.playwright-mcp/, screenshots/)
- Add UI layout exclusions (layouts/ - 230MB reference templates)
- Add context/temp file patterns
- Add monorepo-specific patterns
- Keep .claude/ for agent configurations
- Keep archive/ for historical documentation

This prevents 245MB of non-essential files from being tracked."

echo "Committed .gitignore update"
git log -1 --oneline
```

**Output:**
- Committed .gitignore update to `develop` branch

**Dependencies:** PHASE2-TASK01, PHASE2-TASK02, PHASE2-TASK03, PHASE2-TASK04

**Acceptance Criteria:**
- [ ] .gitignore changes committed
- [ ] Commit message follows conventional commits
- [ ] No other files included in commit

---

### Task PHASE2-TASK06: Verify Cleanup Results

**Agent:** performance-optimizer:performance-engineer
**Priority:** HIGH
**Duration:** 5 min

**Input:**
- Post-cleanup repository state

**Actions:**
```bash
cd /var/www/deschide_news_app

# Check untracked files
echo "=== UNTRACKED FILES ==="
git status --porcelain | grep "^??" | wc -l

# Check repository size
echo -e "\n=== REPOSITORY SIZE ==="
du -sh .git/
du -sh . --exclude=.git

# Verify large directories removed
echo -e "\n=== LARGE DIRECTORIES CHECK ==="
ls -d layouts/ .playwright-mcp/ context/ 2>/dev/null && echo "WARNING: Some directories still exist" || echo "SUCCESS: All large directories removed"

# Compare before/after
echo -e "\n=== SPACE RECOVERED ==="
echo "Expected: ~245MB recovered"
```

**Output:**
- Cleanup verification report
- Disk space recovery confirmed

**Dependencies:** PHASE2-TASK02, PHASE2-TASK03, PHASE2-TASK04

**Acceptance Criteria:**
- [ ] layouts/, .playwright-mcp/, context/ no longer exist
- [ ] ~245MB recovered
- [ ] No unexpected files removed

---

## Phase 3: Documentation Reorganization

**Duration:** 45 minutes
**Lead Agent:** docusaurus-expert
**Objective:** Restructure documentation into clear categories

### Task PHASE3-TASK01: Remove Obsolete Root Files

**Agent:** docusaurus-expert
**Priority:** HIGH
**Duration:** 10 min

**Input:**
- List of 6 obsolete files in root directory

**Files to Remove:**
| File | Reason |
|------|--------|
| `CATEGORIES_IMPORT_ANALYSIS.md` | Import task completed |
| `CATEGORIES_IMPORT_COMPLETED.md` | One-time execution report |
| `DOCUMENTATION_RESTRUCTURING_COMPLETION.md` | Old meta-report |
| `DOCUMENTATION_RESTRUCTURING_PLAN.md` | Old plan - executed |
| `OPTION_C_IMPLEMENTATION_PLAN.md` | Plan implemented |
| `ghid_dev_prod.md` | Replaced by other docs |

**Actions:**
```bash
cd /var/www/deschide_news_app

# Remove obsolete root files (only if they exist)
for file in \
    CATEGORIES_IMPORT_ANALYSIS.md \
    CATEGORIES_IMPORT_COMPLETED.md \
    DOCUMENTATION_RESTRUCTURING_COMPLETION.md \
    DOCUMENTATION_RESTRUCTURING_PLAN.md \
    OPTION_C_IMPLEMENTATION_PLAN.md \
    ghid_dev_prod.md
do
    if [ -f "$file" ]; then
        rm -f "$file"
        echo "Removed: $file"
    else
        echo "Not found: $file"
    fi
done

# Verify
ls -la *.md
```

**Output:**
- 6 obsolete files removed from root

**Dependencies:** PHASE2-TASK05

**Acceptance Criteria:**
- [ ] All 6 obsolete files removed (if they existed)
- [ ] Essential files preserved (CLAUDE.md, README.md, etc.)
- [ ] No accidental deletions

---

### Task PHASE3-TASK02: Remove Obsolete docs/ Files

**Agent:** docusaurus-expert
**Priority:** HIGH
**Duration:** 10 min

**Input:**
- List of 6 obsolete files in docs/ directory

**Files to Remove:**
| File | Reason |
|------|--------|
| `ADMIN_LAYOUT_ALIGNMENT_REPORT.md` | One-time UI fix |
| `PHASE1_AUTH_TEST_REPORT.md` | Test execution completed |
| `PHASE2_ARTICLES_TEST_REPORT.md` | Test execution completed |
| `PHASE2_FIXES_REPORT.md` | Fixes completed |
| `PHASE3_CATEGORIES_TEST_REPORT.md` | Test execution completed |
| `GIT_MONOREPO_MIGRATION_PLAN.md` | Migration completed |
| `SLUG_TRANSLITERATION_SUMMARY.md` | Redundant with main doc |

**Actions:**
```bash
cd /var/www/deschide_news_app/docs

# Remove obsolete docs files (only if they exist)
for file in \
    ADMIN_LAYOUT_ALIGNMENT_REPORT.md \
    PHASE1_AUTH_TEST_REPORT.md \
    PHASE2_ARTICLES_TEST_REPORT.md \
    PHASE2_FIXES_REPORT.md \
    PHASE3_CATEGORIES_TEST_REPORT.md \
    GIT_MONOREPO_MIGRATION_PLAN.md \
    SLUG_TRANSLITERATION_SUMMARY.md
do
    if [ -f "$file" ]; then
        rm -f "$file"
        echo "Removed: docs/$file"
    else
        echo "Not found: docs/$file"
    fi
done

# List remaining docs
ls -la *.md 2>/dev/null || echo "No .md files in docs root"
```

**Output:**
- 7 obsolete files removed from docs/

**Dependencies:** PHASE2-TASK05

**Acceptance Criteria:**
- [ ] All obsolete test reports removed
- [ ] Valuable documentation preserved
- [ ] No accidental deletions

---

### Task PHASE3-TASK03: Create Documentation Categories

**Agent:** docusaurus-expert
**Priority:** MEDIUM
**Duration:** 5 min

**Input:**
- Target documentation structure (5 categories)

**Actions:**
```bash
cd /var/www/deschide_news_app/docs

# Create new category directories
mkdir -p architecture
mkdir -p guides
mkdir -p audits
mkdir -p roadmap
mkdir -p testing

# Verify structure
echo "Created directories:"
ls -d */ 2>/dev/null
```

**Output:**
- 5 documentation category directories created

**Dependencies:** PHASE3-TASK01, PHASE3-TASK02

**Acceptance Criteria:**
- [ ] architecture/ directory created
- [ ] guides/ directory created
- [ ] audits/ directory created
- [ ] roadmap/ directory created
- [ ] testing/ directory created

---

### Task PHASE3-TASK04: Migrate Files to Categories

**Agent:** docusaurus-expert
**Priority:** MEDIUM
**Duration:** 10 min

**Input:**
- Files to migrate based on restructuring strategy

**File Migrations:**
| Source | Destination | Category |
|--------|-------------|----------|
| `PLAN_SEPARARE_DEV_PROD.md` | `docs/guides/dev-prod-separation.md` | Guides |
| `FACEBOOK_AUTO_POSTING_PLAN.md` | `docs/roadmap/facebook-auto-posting.md` | Roadmap |
| `docs/DOCUSAURUS_IMPLEMENTATION_PLAN.md` | `docs/roadmap/docusaurus-site.md` | Roadmap |
| `docs/SLUG_TRANSLITERATION.md` | `apps/backend/docs/slug-transliteration.md` | Backend |
| `docs/SEO_AUDIT_REPORT.md` | `docs/audits/seo-audit.md` | Audits |
| `docs/PROJECT_AUDIT_REPORT.md` | `docs/audits/project-audit.md` | Audits |
| `docs/BUSINESS_METRICS_REPORT.md` | `docs/audits/business-metrics.md` | Audits |
| `docs/TESTING_AGENTS_GUIDE.md` | `docs/testing/agents-guide.md` | Testing |
| `docs/ADMIN_PANEL_TEST_SUITE.md` | `docs/testing/admin-panel-suite.md` | Testing |

**Actions:**
```bash
cd /var/www/deschide_news_app

# Root level migrations
[ -f "PLAN_SEPARARE_DEV_PROD.md" ] && mv PLAN_SEPARARE_DEV_PROD.md docs/guides/dev-prod-separation.md && echo "Migrated: dev-prod-separation.md"
[ -f "FACEBOOK_AUTO_POSTING_PLAN.md" ] && mv FACEBOOK_AUTO_POSTING_PLAN.md docs/roadmap/facebook-auto-posting.md && echo "Migrated: facebook-auto-posting.md"

# Docs level migrations
cd docs
[ -f "DOCUSAURUS_IMPLEMENTATION_PLAN.md" ] && mv DOCUSAURUS_IMPLEMENTATION_PLAN.md roadmap/docusaurus-site.md && echo "Migrated: docusaurus-site.md"
[ -f "SLUG_TRANSLITERATION.md" ] && mv SLUG_TRANSLITERATION.md ../apps/backend/docs/slug-transliteration.md && echo "Migrated: slug-transliteration.md"
[ -f "SEO_AUDIT_REPORT.md" ] && mv SEO_AUDIT_REPORT.md audits/seo-audit.md && echo "Migrated: seo-audit.md"
[ -f "PROJECT_AUDIT_REPORT.md" ] && mv PROJECT_AUDIT_REPORT.md audits/project-audit.md && echo "Migrated: project-audit.md"
[ -f "BUSINESS_METRICS_REPORT.md" ] && mv BUSINESS_METRICS_REPORT.md audits/business-metrics.md && echo "Migrated: business-metrics.md"
[ -f "TESTING_AGENTS_GUIDE.md" ] && mv TESTING_AGENTS_GUIDE.md testing/agents-guide.md && echo "Migrated: agents-guide.md"
[ -f "ADMIN_PANEL_TEST_SUITE.md" ] && mv ADMIN_PANEL_TEST_SUITE.md testing/admin-panel-suite.md && echo "Migrated: admin-panel-suite.md"

echo "Migration complete. Verify structure:"
find . -name "*.md" -type f | head -20
```

**Output:**
- Files migrated to appropriate categories

**Dependencies:** PHASE3-TASK03

**Acceptance Criteria:**
- [ ] All files migrated to correct categories
- [ ] No broken links (internal references may need update)
- [ ] Backend docs moved to apps/backend/docs/

---

### Task PHASE3-TASK05: Create UI Templates Documentation

**Agent:** docusaurus-expert
**Priority:** LOW
**Duration:** 5 min

**Input:**
- Reference UI templates that were removed (layouts/)

**Actions:**
```bash
cd /var/www/deschide_news_app/docs

# Create UI Templates reference document
cat > guides/ui-templates.md << 'EOF'
# UI/UX Templates Reference

This project references the following UI templates for design inspiration.

## Important Note

UI template files are **NOT** included in the repository to keep it lightweight.
They are listed in `.gitignore` under the `layouts/` directory.

## Referenced Templates

### Flowbite Admin Dashboard
- **Source:** https://github.com/themesberg/flowbite-admin-dashboard
- **License:** MIT
- **Usage:** Reference for admin panel design
- **Local Path:** `layouts/flowbite-admin-dashboard/` (gitignored)

### TailNews Template
- **Source:** (commercial or custom)
- **Usage:** Reference for public news layout
- **Local Path:** `layouts/tailnews/` (gitignored)

## Local Installation (Optional)

If you need these templates for reference during development:

```bash
mkdir -p layouts
cd layouts

# Flowbite Admin Dashboard
git clone https://github.com/themesberg/flowbite-admin-dashboard

# Other templates - obtain from source
```

## Design System

Our actual implementation uses:
- **Tailwind CSS 4** - Utility-first CSS
- **Shadcn/UI** - React component library
- **Custom components** - Located in `apps/frontend/components/`

For component documentation, see `apps/frontend/docs/ui/`.
EOF

echo "Created: docs/guides/ui-templates.md"
```

**Output:**
- `docs/guides/ui-templates.md` created

**Dependencies:** PHASE2-TASK02 (after layouts/ removed)

**Acceptance Criteria:**
- [ ] UI Templates documentation created
- [ ] Download links included
- [ ] Explains why layouts/ is gitignored

---

### Task PHASE3-TASK06: Commit Documentation Changes

**Agent:** git-flow-manager
**Priority:** HIGH
**Duration:** 5 min

**Input:**
- All documentation changes from PHASE3

**Actions:**
```bash
cd /var/www/deschide_news_app

# Stage all documentation changes
git add docs/
git add apps/backend/docs/slug-transliteration.md 2>/dev/null || true

# Check what was deleted (will show as deleted in status)
git status

# Commit documentation restructure
git commit -m "docs: restructure documentation into logical categories

Removed obsolete files:
- 6 root-level execution reports
- 7 docs-level test reports and migration plans

Created documentation categories:
- docs/architecture/ - Design and architecture docs
- docs/guides/ - Operational guides
- docs/audits/ - Audit reports
- docs/roadmap/ - Future planning
- docs/testing/ - Test documentation

Migrations:
- Moved backend-specific docs to apps/backend/docs/
- Created UI templates reference guide

This improves documentation discoverability and removes outdated content."

echo "Committed documentation changes"
git log -1 --oneline
```

**Output:**
- Documentation restructure committed

**Dependencies:** PHASE3-TASK01 through PHASE3-TASK05

**Acceptance Criteria:**
- [ ] All documentation changes in single commit
- [ ] Commit message explains what was done
- [ ] No unrelated files included

---

## Phase 4: Code Commits

**Duration:** 2-3 hours
**Lead Agent:** git-flow-manager
**Objective:** Organize 59 modified files into 12 logical commit groups

### Commit Groups Overview

| Group | Scope | Files | Branch Type | Agent |
|-------|-------|-------|-------------|-------|
| 1 | Backend | 6 | feature | git-flow-manager |
| 2 | Frontend | 2 | feature | git-flow-manager |
| 3 | Fullstack | 9 | feature | git-flow-manager |
| 4 | Frontend | 4 | feature | git-flow-manager |
| 5 | Backend | 3 | feature | git-flow-manager |
| 6 | Frontend | 8 | feature | git-flow-manager |
| 7 | Frontend | 4 | feature | git-flow-manager |
| 8 | Frontend | 2 | feature | git-flow-manager |
| 9 | Backend | 1 | bugfix | git-flow-manager |
| 10 | Docs | 11 | docs | docusaurus-expert |
| 11 | Backend | 1 | docs | git-flow-manager |
| 12 | Root | 1 | chore | git-flow-manager |

---

### Task PHASE4-GROUP01: Slug Transliteration Feature

**Agent:** git-flow-manager
**Priority:** HIGH
**Duration:** 15 min

**Files (6):**
```
apps/backend/src/Service/RomanianSlugger.php (NEW)
apps/backend/src/EventSubscriber/SluggableTransliteratorSubscriber.php (NEW)
apps/backend/src/Command/TestSlugGenerationCommand.php (NEW)
apps/backend/src/Entity/Article.php (MODIFIED)
apps/backend/src/Entity/Author.php (MODIFIED)
apps/backend/config/services.yaml (MODIFIED)
```

**Actions:**
```bash
cd /var/www/deschide_news_app

# Start feature branch
git flow feature start backend-slug-transliteration

# Stage files
git add apps/backend/src/Service/RomanianSlugger.php
git add apps/backend/src/EventSubscriber/SluggableTransliteratorSubscriber.php
git add apps/backend/src/Command/TestSlugGenerationCommand.php
git add apps/backend/src/Entity/Article.php
git add apps/backend/src/Entity/Author.php
git add apps/backend/config/services.yaml

# Commit
git commit -m "feat(backend): implement Romanian slug transliteration

- Add RomanianSlugger service for proper diacritics handling
- Add SluggableTransliteratorSubscriber for automatic slug generation
- Update Article and Author entities with slug configuration
- Add test command for slug generation validation

Handles Romanian characters: a, i, s, t correctly"

# Finish feature (merge to develop)
git flow feature finish backend-slug-transliteration
```

**Output:**
- Feature merged to develop
- 6 files committed

**Dependencies:** PHASE3-TASK06

**Acceptance Criteria:**
- [ ] All 6 files committed
- [ ] Feature branch created and merged
- [ ] Commit message follows convention

---

### Task PHASE4-GROUP02: Admin Delete Functionality

**Agent:** git-flow-manager
**Priority:** HIGH
**Duration:** 10 min

**Files (2):**
```
apps/frontend/app/[locale]/admin/articles/components/DeleteArticleButton.tsx (NEW)
apps/frontend/app/[locale]/admin/categories/components/DeleteCategoryModal.tsx (NEW)
```

**Actions:**
```bash
cd /var/www/deschide_news_app

git flow feature start frontend-delete-components

git add apps/frontend/app/[locale]/admin/articles/components/DeleteArticleButton.tsx
git add apps/frontend/app/[locale]/admin/categories/components/DeleteCategoryModal.tsx

git commit -m "feat(frontend): add delete functionality to admin panel

- Add DeleteArticleButton component with confirmation dialog
- Add DeleteCategoryModal with cascading delete warning
- Integrate with server actions for data persistence"

git flow feature finish frontend-delete-components
```

**Output:**
- 2 files committed

**Dependencies:** PHASE4-GROUP01

**Acceptance Criteria:**
- [ ] Delete components committed
- [ ] Feature branch completed

---

### Task PHASE4-GROUP03: Image & CDN Integration

**Agent:** git-flow-manager
**Priority:** HIGH
**Duration:** 15 min

**Files (9):**
```
Backend (4):
apps/backend/src/DataFixtures/ImageFixtures.php (MODIFIED)
apps/backend/src/Entity/Image.php (MODIFIED)
apps/backend/src/State/ArticleProvider.php (MODIFIED)
apps/backend/src/State/ArticleProcessor.php (MODIFIED)

Frontend (5):
apps/frontend/components/article/ArticleImage.tsx (MODIFIED)
apps/frontend/lib/api/images.ts (MODIFIED)
apps/frontend/components/admin/images/CropModal.tsx (MODIFIED)
apps/frontend/app/[locale]/(public)/components/home/important.tsx (MODIFIED)
apps/frontend/public/images/ (NEW - og-default.jpg, og-default.svg)
```

**Actions:**
```bash
cd /var/www/deschide_news_app

git flow feature start fullstack-cdn-integration

# Backend files
git add apps/backend/src/DataFixtures/ImageFixtures.php
git add apps/backend/src/Entity/Image.php
git add apps/backend/src/State/ArticleProvider.php
git add apps/backend/src/State/ArticleProcessor.php

# Frontend files
git add apps/frontend/components/article/ArticleImage.tsx
git add apps/frontend/lib/api/images.ts
git add apps/frontend/components/admin/images/CropModal.tsx
git add "apps/frontend/app/[locale]/(public)/components/home/important.tsx"
git add apps/frontend/public/images/

git commit -m "feat(fullstack): integrate CDN for image serving

Backend:
- Update Image entity with CDN-friendly path structure
- Enhance ArticleProvider with eager loading for images
- Update ArticleProcessor for image handling
- Add development image fixtures

Frontend:
- Update ArticleImage component to use CDN URLs
- Add default OG images for social sharing (og-default.jpg, og-default.svg)
- Update image API integration
- Fix CropModal image preview
- Update important articles component"

git flow feature finish fullstack-cdn-integration
```

**Output:**
- 9 files committed

**Dependencies:** PHASE4-GROUP02

**Acceptance Criteria:**
- [ ] All 9 files committed
- [ ] Both backend and frontend changes in single feature

---

### Task PHASE4-GROUP04: Slug Lookup & Reserved Slugs

**Agent:** git-flow-manager
**Priority:** MEDIUM
**Duration:** 10 min

**Files (4):**
```
apps/frontend/lib/utils/slug.ts (NEW)
apps/frontend/lib/utils/__tests__/ (NEW - test directory)
apps/frontend/lib/constants/reserved-slugs.ts (MODIFIED)
apps/frontend/lib/api/slug-lookup.ts (MODIFIED)
```

**Actions:**
```bash
cd /var/www/deschide_news_app

git flow feature start frontend-slug-system

git add apps/frontend/lib/utils/slug.ts
git add apps/frontend/lib/utils/__tests__/
git add apps/frontend/lib/constants/reserved-slugs.ts
git add apps/frontend/lib/api/slug-lookup.ts

git commit -m "feat(frontend): enhance slug utilities and validation

- Add slug generation utilities (slug.ts)
- Add unit tests for slug functions
- Update reserved slugs list for routing
- Improve slug lookup API integration"

git flow feature finish frontend-slug-system
```

**Output:**
- 4 files committed

**Dependencies:** PHASE4-GROUP03

**Acceptance Criteria:**
- [ ] Slug utilities and tests committed
- [ ] Feature branch completed

---

### Task PHASE4-GROUP05: Cache Invalidation System

**Agent:** git-flow-manager
**Priority:** MEDIUM
**Duration:** 10 min

**Files (3):**
```
apps/backend/src/EventSubscriber/MultiTierCacheInvalidationSubscriber.php (MODIFIED)
apps/backend/src/EventSubscriber/VarnishCacheInvalidationSubscriber.php (MODIFIED)
apps/backend/src/State/CategoryProcessor.php (MODIFIED)
```

**Actions:**
```bash
cd /var/www/deschide_news_app

git flow feature start backend-cache-invalidation

git add apps/backend/src/EventSubscriber/MultiTierCacheInvalidationSubscriber.php
git add apps/backend/src/EventSubscriber/VarnishCacheInvalidationSubscriber.php
git add apps/backend/src/State/CategoryProcessor.php

git commit -m "feat(backend): implement multi-tier cache invalidation

- Update multi-tier cache invalidation subscriber
- Add Varnish cache invalidation integration
- Update CategoryProcessor with cache handling
- Ensure cache consistency across Redis and Varnish"

git flow feature finish backend-cache-invalidation
```

**Output:**
- 3 files committed

**Dependencies:** PHASE4-GROUP04

**Acceptance Criteria:**
- [ ] Cache invalidation files committed
- [ ] Feature branch completed

---

### Task PHASE4-GROUP06: Frontend Routing & SEO

**Agent:** git-flow-manager
**Priority:** MEDIUM
**Duration:** 15 min

**Files (8):**
```
apps/frontend/app/[locale]/(public)/[categorySlug]/[articleSlug]/page.tsx (MODIFIED)
apps/frontend/app/[locale]/(public)/[categorySlug]/page.tsx (MODIFIED)
apps/frontend/app/[locale]/(public)/author/[slug]/page.tsx (MODIFIED)
apps/frontend/app/[locale]/(public)/category/[slug]/page.tsx (MODIFIED)
apps/frontend/app/[locale]/(public)/page.tsx (MODIFIED)
apps/frontend/lib/dal.ts (MODIFIED)
apps/frontend/lib/seo/meta-tags.ts (MODIFIED)
apps/frontend/public/tinymce (MODIFIED - submodule)
```

**Actions:**
```bash
cd /var/www/deschide_news_app

git flow feature start frontend-routing-seo

git add "apps/frontend/app/[locale]/(public)/[categorySlug]/[articleSlug]/page.tsx"
git add "apps/frontend/app/[locale]/(public)/[categorySlug]/page.tsx"
git add "apps/frontend/app/[locale]/(public)/author/[slug]/page.tsx"
git add "apps/frontend/app/[locale]/(public)/category/[slug]/page.tsx"
git add "apps/frontend/app/[locale]/(public)/page.tsx"
git add apps/frontend/lib/dal.ts
git add apps/frontend/lib/seo/meta-tags.ts
git add apps/frontend/public/tinymce

git commit -m "feat(frontend): enhance routing and SEO metadata

- Update dynamic routing for categories and articles
- Improve author page with proper metadata
- Update category pages with SEO enhancements
- Enhance homepage routing
- Update data access layer for better performance
- Improve SEO meta tags generation
- Update TinyMCE submodule"

git flow feature finish frontend-routing-seo
```

**Output:**
- 8 files committed

**Dependencies:** PHASE4-GROUP05

**Acceptance Criteria:**
- [ ] All routing files committed
- [ ] TinyMCE submodule updated
- [ ] Feature branch completed

---

### Task PHASE4-GROUP07: Admin Panel UI/UX

**Agent:** git-flow-manager
**Priority:** MEDIUM
**Duration:** 10 min

**Files (4):**
```
apps/frontend/app/[locale]/admin/articles/ArticlesTableClient.tsx (MODIFIED)
apps/frontend/app/[locale]/admin/articles/components/ArticleForm.tsx (MODIFIED)
apps/frontend/app/[locale]/admin/categories/CategoriesTable.tsx (MODIFIED)
apps/frontend/app/[locale]/admin/categories/components/CategoryForm.tsx (MODIFIED)
```

**Actions:**
```bash
cd /var/www/deschide_news_app

git flow feature start frontend-admin-improvements

git add "apps/frontend/app/[locale]/admin/articles/ArticlesTableClient.tsx"
git add "apps/frontend/app/[locale]/admin/articles/components/ArticleForm.tsx"
git add "apps/frontend/app/[locale]/admin/categories/CategoriesTable.tsx"
git add "apps/frontend/app/[locale]/admin/categories/components/CategoryForm.tsx"

git commit -m "feat(frontend): improve admin panel UX

- Enhance ArticlesTableClient with better filtering
- Update ArticleForm with improved validation
- Improve CategoriesTable performance
- Update CategoryForm with better error handling"

git flow feature finish frontend-admin-improvements
```

**Output:**
- 4 files committed

**Dependencies:** PHASE4-GROUP06

**Acceptance Criteria:**
- [ ] Admin panel improvements committed
- [ ] Feature branch completed

---

### Task PHASE4-GROUP08: Server Actions

**Agent:** git-flow-manager
**Priority:** MEDIUM
**Duration:** 10 min

**Files (2):**
```
apps/frontend/app/actions/articles.ts (MODIFIED)
apps/frontend/app/actions/categories.ts (MODIFIED)
```

**Actions:**
```bash
cd /var/www/deschide_news_app

git flow feature start frontend-server-actions

git add apps/frontend/app/actions/articles.ts
git add apps/frontend/app/actions/categories.ts

git commit -m "feat(frontend): enhance server actions

- Update articles actions with better error handling
- Improve categories actions with validation
- Add revalidation for cache consistency"

git flow feature finish frontend-server-actions
```

**Output:**
- 2 files committed

**Dependencies:** PHASE4-GROUP07

**Acceptance Criteria:**
- [ ] Server actions committed
- [ ] Feature branch completed

---

### Task PHASE4-GROUP09: Backend API Endpoint Fix

**Agent:** git-flow-manager
**Priority:** MEDIUM
**Duration:** 10 min

**Files (1):**
```
apps/backend/src/Controller/Api/SlugController.php (MODIFIED)
```

**Actions:**
```bash
cd /var/www/deschide_news_app

git flow bugfix start slug-controller-fix

git add apps/backend/src/Controller/Api/SlugController.php

git commit -m "fix(backend): improve SlugController error handling

- Add better validation for slug lookups
- Improve error messages for debugging
- Add logging for troubleshooting"

git flow bugfix finish slug-controller-fix
```

**Output:**
- 1 file committed

**Dependencies:** PHASE4-GROUP08

**Acceptance Criteria:**
- [ ] SlugController fix committed
- [ ] Bugfix branch completed

---

### Task PHASE4-GROUP10: Documentation Updates

**Agent:** docusaurus-expert
**Priority:** LOW
**Duration:** 15 min

**Files (remaining docs not yet committed):**
- Any remaining documentation files from the 11 identified

**Note:** Many docs may have been handled in PHASE3. This task handles any remaining.

**Actions:**
```bash
cd /var/www/deschide_news_app

# Check for any remaining untracked docs
git status docs/

# If there are remaining docs, commit them
git add docs/
git commit -m "docs: add remaining documentation updates

- Add any additional guides
- Update existing documentation
- Ensure documentation is current" || echo "No additional docs to commit"
```

**Output:**
- Remaining documentation committed

**Dependencies:** PHASE4-GROUP09

**Acceptance Criteria:**
- [ ] All documentation files committed
- [ ] No orphan docs in working directory

---

### Task PHASE4-GROUP11: Backend Bug Fix Documentation

**Agent:** git-flow-manager
**Priority:** LOW
**Duration:** 5 min

**Files (1):**
```
apps/backend/docs/BUG_FIX_ARTICLE_TITLE_UPDATE.md (NEW)
```

**Actions:**
```bash
cd /var/www/deschide_news_app

# Check if file exists and is untracked
if [ -f "apps/backend/docs/BUG_FIX_ARTICLE_TITLE_UPDATE.md" ]; then
    git add apps/backend/docs/BUG_FIX_ARTICLE_TITLE_UPDATE.md
    git commit -m "docs(backend): add article title update bug fix documentation

- Document the issue and resolution
- Add code examples and testing steps"
else
    echo "File not found or already committed"
fi
```

**Output:**
- Bug fix documentation committed

**Dependencies:** PHASE4-GROUP10

**Acceptance Criteria:**
- [ ] Backend bug fix docs committed

---

### Task PHASE4-GROUP12: Root Configuration Updates

**Agent:** git-flow-manager
**Priority:** LOW
**Duration:** 5 min

**Files (1-2):**
```
CLAUDE.md (MODIFIED)
.mcp.json (NEW - if decided to commit)
```

**Actions:**
```bash
cd /var/www/deschide_news_app

# Stage configuration files
git add CLAUDE.md
git add .mcp.json 2>/dev/null || true

git commit -m "chore: update monorepo configuration

- Update CLAUDE.md with latest structure
- Add CDN configuration details
- Update port allocations
- Add MCP configuration for Playwright testing"
```

**Output:**
- Configuration files committed

**Dependencies:** PHASE4-GROUP11

**Acceptance Criteria:**
- [ ] CLAUDE.md updated and committed
- [ ] .mcp.json committed (if decided)
- [ ] All configuration current

---

## Phase 5: Validation and Finalization

**Duration:** 30 minutes
**Lead Agent:** general-purpose
**Objective:** Verify all changes and create final report

### Task PHASE5-TASK01: Verify Clean Working Directory

**Agent:** general-purpose
**Priority:** CRITICAL
**Duration:** 5 min

**Input:**
- Post-commit repository state

**Actions:**
```bash
cd /var/www/deschide_news_app

echo "=== GIT STATUS ==="
git status

echo -e "\n=== UNCOMMITTED FILES ==="
git status --porcelain | wc -l

echo -e "\n=== UNTRACKED FILES ==="
git status --porcelain | grep "^??" | wc -l

# Should show: nothing to commit, working tree clean
```

**Output:**
- Working directory status (should be clean)

**Dependencies:** All PHASE4 tasks

**Acceptance Criteria:**
- [ ] `git status` shows "nothing to commit, working tree clean"
- [ ] No untracked files (except gitignored ones)
- [ ] No modified files

---

### Task PHASE5-TASK02: Verify Repository Size

**Agent:** performance-optimizer:performance-engineer
**Priority:** HIGH
**Duration:** 5 min

**Input:**
- Repository disk usage

**Actions:**
```bash
cd /var/www/deschide_news_app

echo "=== REPOSITORY SIZE ==="
du -sh .git/
du -sh . --exclude=.git

echo -e "\n=== LARGE DIRECTORIES CHECK ==="
du -sh */ 2>/dev/null | sort -rh | head -10

echo -e "\n=== GITIGNORED LARGE DIRS ==="
ls -d layouts/ .playwright-mcp/ context/ 2>/dev/null && echo "WARNING: Some should be removed" || echo "SUCCESS: All gitignored dirs removed"

echo -e "\n=== COMPARISON ==="
echo "Before: ~250MB untracked"
echo "After: $(du -sh . --exclude=.git | cut -f1)"
```

**Output:**
- Repository size report

**Dependencies:** PHASE5-TASK01

**Acceptance Criteria:**
- [ ] Repository working directory < 50MB
- [ ] .git directory ~20MB
- [ ] No layouts/, .playwright-mcp/, context/ directories

---

### Task PHASE5-TASK03: Verify Documentation Structure

**Agent:** docusaurus-expert
**Priority:** MEDIUM
**Duration:** 5 min

**Input:**
- Documentation directory structure

**Actions:**
```bash
cd /var/www/deschide_news_app

echo "=== DOCUMENTATION STRUCTURE ==="
find docs -type d | sort

echo -e "\n=== DOCS BY CATEGORY ==="
for dir in docs/architecture docs/guides docs/audits docs/roadmap docs/testing; do
    echo -e "\n$dir:"
    ls -1 "$dir" 2>/dev/null || echo "  (empty or missing)"
done

echo -e "\n=== ROOT MD FILES ==="
ls -1 *.md

echo -e "\n=== BACKEND DOCS ==="
ls -1 apps/backend/docs/*.md 2>/dev/null | head -10

echo -e "\n=== FRONTEND DOCS ==="
ls -1 apps/frontend/docs/*.md 2>/dev/null | head -10
```

**Output:**
- Documentation structure verification

**Dependencies:** PHASE5-TASK01

**Acceptance Criteria:**
- [ ] 5 documentation categories exist
- [ ] Essential root files present (CLAUDE.md, README.md)
- [ ] Backend and frontend docs organized

---

### Task PHASE5-TASK04: Verify .gitignore Effectiveness

**Agent:** performance-optimizer:performance-engineer
**Priority:** HIGH
**Duration:** 5 min

**Input:**
- .gitignore file
- Gitignored paths

**Actions:**
```bash
cd /var/www/deschide_news_app

echo "=== .GITIGNORE VERIFICATION ==="

# Test critical patterns
for pattern in layouts .playwright-mcp context .env.local; do
    mkdir -p "$pattern" 2>/dev/null || true
    touch "$pattern/test-file" 2>/dev/null || true

    if git check-ignore -q "$pattern" 2>/dev/null; then
        echo "PASS: $pattern is ignored"
    else
        echo "FAIL: $pattern is NOT ignored"
    fi

    rm -rf "$pattern" 2>/dev/null || true
done

echo -e "\n=== GITIGNORE PATTERNS COUNT ==="
grep -v "^#" .gitignore | grep -v "^$" | wc -l
```

**Output:**
- .gitignore pattern verification

**Dependencies:** PHASE5-TASK01

**Acceptance Criteria:**
- [ ] layouts/ pattern works
- [ ] .playwright-mcp/ pattern works
- [ ] context/ pattern works
- [ ] .env.local pattern works

---

### Task PHASE5-TASK05: Run Application Tests

**Agent:** general-purpose
**Priority:** HIGH
**Duration:** 10 min

**Input:**
- Backend and frontend test suites

**Actions:**
```bash
# Backend health check
cd /var/www/deschide_news_app/apps/backend
symfony console about 2>&1 | head -20 || echo "Backend check failed"

# Frontend build check
cd /var/www/deschide_news_app/apps/frontend
pnpm build 2>&1 | tail -20 || echo "Frontend build failed"

# Verify API endpoint
curl -s http://127.0.0.1:8081/api 2>&1 | head -5 || echo "API check failed"
```

**Output:**
- Application health verification

**Dependencies:** PHASE5-TASK04

**Acceptance Criteria:**
- [ ] Backend Symfony console works
- [ ] Frontend builds successfully
- [ ] API endpoint responds

---

### Task PHASE5-TASK06: Generate Final Report

**Agent:** general-purpose
**Priority:** MEDIUM
**Duration:** 5 min

**Input:**
- All verification results

**Actions:**
```bash
cd /var/www/deschide_news_app

cat > /tmp/restructure_completion_report.txt << 'EOF'
===============================================
MONOREPO RESTRUCTURING COMPLETION REPORT
===============================================

Date: $(date)
Repository: /var/www/deschide_news_app

METRICS SUMMARY
---------------
Before:
- Untracked files: 59
- Untracked size: ~244MB
- .gitignore completeness: 60%
- Obsolete docs: 12 files

After:
- Untracked files: 0
- Untracked size: 0MB
- .gitignore completeness: 95%
- Obsolete docs: 0 files

COMPLETED PHASES
----------------
[x] Phase 1: Preparation
[x] Phase 2: Cleanup & .gitignore
[x] Phase 3: Documentation Reorganization
[x] Phase 4: Code Commits (12 groups)
[x] Phase 5: Validation

COMMITS CREATED
---------------
1. feat(backend): implement Romanian slug transliteration
2. feat(frontend): add delete functionality to admin panel
3. feat(fullstack): integrate CDN for image serving
4. feat(frontend): enhance slug utilities and validation
5. feat(backend): implement multi-tier cache invalidation
6. feat(frontend): enhance routing and SEO metadata
7. feat(frontend): improve admin panel UX
8. feat(frontend): enhance server actions
9. fix(backend): improve SlugController error handling
10. docs: documentation restructure
11. docs(backend): add bug fix documentation
12. chore: update monorepo configuration

NEXT STEPS
----------
1. Push develop branch to remote
2. Create release if appropriate
3. Monitor for any issues
4. Update team documentation

===============================================
EOF

cat /tmp/restructure_completion_report.txt
```

**Output:**
- Final completion report

**Dependencies:** PHASE5-TASK01 through PHASE5-TASK05

**Acceptance Criteria:**
- [ ] Report generated
- [ ] All metrics documented
- [ ] Next steps identified

---

### Task PHASE5-TASK07: Push to Remote (Optional)

**Agent:** git-flow-manager
**Priority:** MEDIUM
**Duration:** 5 min

**Input:**
- Verified local changes

**Actions:**
```bash
cd /var/www/deschide_news_app

# Review commits before pushing
git log --oneline -15

# Push develop branch
# git push origin develop

echo "Ready to push. Execute: git push origin develop"
```

**Output:**
- Changes ready for push (or pushed)

**Dependencies:** PHASE5-TASK06

**Acceptance Criteria:**
- [ ] All commits reviewed
- [ ] Ready to push to remote
- [ ] No merge conflicts expected

---

## Risk Assessment and Mitigation

### Critical Risks

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| **Accidental file deletion** | HIGH | LOW | Backup created in PHASE1-TASK01, rollback script available |
| **Breaking changes** | HIGH | MEDIUM | Application tests in PHASE5-TASK05, incremental commits |
| **Lost uncommitted work** | HIGH | LOW | Git stash in PHASE1-TASK04, full backup |
| **Wrong files committed** | MEDIUM | LOW | Review before each commit, feature branches |

### Medium Risks

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| **.gitignore patterns too aggressive** | MEDIUM | LOW | Tested in PHASE5-TASK04, patterns reviewed |
| **Documentation links broken** | MEDIUM | MEDIUM | Can fix with follow-up commit |
| **Merge conflicts on remote** | MEDIUM | LOW | Working on develop, no parallel work |

### Low Risks

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| **Repository history confusion** | LOW | LOW | Clear commit messages, logical grouping |
| **Developer confusion** | LOW | MEDIUM | Update team, clear documentation |

---

## Success Metrics Checklist

### Pre-Execution Baseline

- [ ] Repository untracked size: ~244MB
- [ ] Uncommitted files: 59
- [ ] .gitignore patterns: ~20
- [ ] Obsolete docs: 12

### Post-Execution Targets

- [ ] Repository untracked size: 0MB (all tracked or ignored)
- [ ] Uncommitted files: 0
- [ ] .gitignore patterns: ~50
- [ ] Obsolete docs: 0

### Quality Metrics

- [ ] All commits follow conventional commits
- [ ] All feature branches properly closed
- [ ] Documentation structure has 5 categories
- [ ] Application builds successfully
- [ ] API endpoints respond correctly

---

## Timeline

### Estimated Duration: 4-6 hours

| Phase | Duration | Cumulative |
|-------|----------|------------|
| Phase 1: Preparation | 30 min | 0:30 |
| Phase 2: Cleanup | 45 min | 1:15 |
| Phase 3: Documentation | 45 min | 2:00 |
| Phase 4: Commits | 2-3 hours | 4:00-5:00 |
| Phase 5: Validation | 30 min | 4:30-5:30 |
| Buffer | 30 min | 5:00-6:00 |

### Recommended Execution Order

```
Day 1 (if splitting):
- Phase 1: Preparation (30 min)
- Phase 2: Cleanup (45 min)
- Phase 3: Documentation (45 min)
Total: ~2 hours

Day 2 (if splitting):
- Phase 4: Code Commits (2-3 hours)
- Phase 5: Validation (30 min)
Total: ~3 hours
```

---

## Final Checklist

### Before Starting

- [ ] Read this entire plan
- [ ] Ensure no active development on repository
- [ ] Verify disk space available (~500MB free)
- [ ] Confirm access to backup location
- [ ] Have rollback plan ready

### During Execution

- [ ] Follow tasks in order
- [ ] Check acceptance criteria for each task
- [ ] Document any deviations
- [ ] Take breaks between phases

### After Completion

- [ ] Verify working directory is clean
- [ ] Confirm all tests pass
- [ ] Review final report
- [ ] Communicate changes to team
- [ ] Archive execution logs

---

## Agent Assignments Summary

| Agent | Tasks Assigned | Primary Responsibility |
|-------|----------------|------------------------|
| **general-purpose** | 6 tasks | Preparation, verification, reporting |
| **git-flow-manager** | 14 tasks | Git operations, commits, branches |
| **performance-optimizer:performance-engineer** | 5 tasks | Cleanup, size optimization |
| **docusaurus-expert** | 5 tasks | Documentation restructuring |

---

## Appendix: Quick Reference Commands

### Emergency Rollback

```bash
# If something goes wrong:
cd ~/backups/deschide_news
./rollback.sh
```

### Check Status

```bash
cd /var/www/deschide_news_app
git status
git log --oneline -10
du -sh .
```

### Abort Feature

```bash
git flow feature finish --abort
# or
git checkout develop
git branch -D feature/name
```

---

**Document Version:** 1.0
**Created By:** Product Strategist Agent
**Validated By:** Pending
**Status:** Ready for Execution
