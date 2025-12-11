# Documentation Guide

**⚠️ IMPORTANT: This file is NOT in a git repository**

This document explains where to find all project documentation and how to avoid confusion between different documentation locations.

---

## 📁 Documentation Structure Overview

The Deschide News project has documentation in **two separate git repositories**:

### 1️⃣ Backend Repository (Symfony API)

**Location**: `/var/www/deschide_news_app/deschide_backend/`
**Git Remote**: `git@github.com:radusoltan/deschide_news_app_backend.git`
**Branch**: `master`

**Documentation Directory**: `/var/www/deschide_news_app/deschide_backend/docs/`

### 2️⃣ Frontend Repository (Next.js)

**Location**: `/var/www/deschide_news_app/deschide_frontend/`
**Git Remote**: `git@github.com:radusoltan/deschide_news_app_frontend.git`
**Branch**: `main`

**Documentation Directory**: `/var/www/deschide_news_app/deschide_frontend/docs/`

---

## 📖 Where to Find Documentation

### Project-Wide Documentation (Backend Repository)

**Important**: Project-wide documents are stored in **backend repository** to avoid duplication.

```
/var/www/deschide_news_app/deschide_backend/docs/
├── BUSINESS_OVERVIEW.md      ← Project overview for all stakeholders
├── AUDIT_SCOPE.md            ← Audit scope definition
├── audit_prepare.md          ← Original audit preparation plan
├── audit_checklist.md        ← Current progress tracking
└── README.md                 ← Complete backend documentation index
```

**Access via Git**:
```bash
cd /var/www/deschide_news_app/deschide_backend
git pull origin master
cat docs/BUSINESS_OVERVIEW.md
```

**GitHub URLs**:
- Business Overview: `https://github.com/radusoltan/deschide_news_app_backend/blob/master/docs/BUSINESS_OVERVIEW.md`
- Audit Scope: `https://github.com/radusoltan/deschide_news_app_backend/blob/master/docs/AUDIT_SCOPE.md`

---

### Backend Documentation (Backend Repository)

**Location**: `/var/www/deschide_news_app/deschide_backend/docs/`

**Technical Documentation**:
```
/var/www/deschide_news_app/deschide_backend/docs/
├── openapi.jsonld                  # API specification (Hydra/JSON-LD)
├── api_entrypoint.json             # API entrypoint
├── schema.sql                      # Database schema
├── auth_flow.md                    # JWT authentication flow
├── entities.md                     # Entity relationships
├── media_service.md                # Image/thumbnail service
├── multilanguage_model.md          # Gedmo Translatable (ro/en/ru)
├── workflow_articles.md            # Article lifecycle
├── roles_permissions.md            # Access control matrix
├── configs/                        # Backend configurations
│   ├── security.yaml
│   ├── api_platform.yaml
│   ├── doctrine.yaml
│   ├── messenger.yaml
│   └── nelmio_cors.yaml
└── examples/
    └── sample_data.sql             # Sanitized sample data
```

**Access**: See `/var/www/deschide_news_app/deschide_backend/docs/README.md`

---

### Frontend Documentation (Frontend Repository)

**Location**: `/var/www/deschide_news_app/deschide_frontend/docs/`

**Frontend-Specific Documentation**:
```
/var/www/deschide_news_app/deschide_frontend/docs/
├── README.md                       # Frontend documentation index
├── api_integration.md              # API client architecture
├── i18n_config.md                  # Internationalization (ro/en/ru)
├── auth_ui.md                      # Authentication UI flow
├── routes_public.md                # Public pages (12+ routes)
└── routes_admin.md                 # Admin CMS (15+ routes)
```

**Access via Git**:
```bash
cd /var/www/deschide_news_app/deschide_frontend
git pull origin main
cat docs/README.md
```

**GitHub URLs**:
- Frontend Index: `https://github.com/radusoltan/deschide_news_app_frontend/blob/main/docs/README.md`
- API Integration: `https://github.com/radusoltan/deschide_news_app_frontend/blob/main/docs/api_integration.md`

---

## ⚠️ Important: Root Directory is NOT a Git Repository

**This directory** (`/var/www/deschide_news_app/`) is **NOT tracked by git**.

Files in this root directory (like this `DOCUMENTATION_GUIDE.md`, `CLAUDE.md`, `README.md`) are:
- ✅ Local development files
- ✅ Workspace configuration
- ❌ NOT committed to any git repository
- ❌ NOT shared via GitHub

**Why?**
- The backend and frontend are separate git repositories
- The root directory is just a workspace containing both
- Prevents confusion and conflicts between repositories

---

## 🚀 Quick Access Commands

### View Backend Documentation Index

```bash
cd /var/www/deschide_news_app/deschide_backend
cat docs/README.md
```

### View Frontend Documentation Index

```bash
cd /var/www/deschide_news_app/deschide_frontend
cat docs/README.md
```

### Open Business Overview

```bash
cat /var/www/deschide_news_app/deschide_backend/docs/BUSINESS_OVERVIEW.md
```

### Open Audit Scope

```bash
cat /var/www/deschide_news_app/deschide_backend/docs/AUDIT_SCOPE.md
```

### List All Backend Docs

```bash
ls -la /var/www/deschide_news_app/deschide_backend/docs/
```

### List All Frontend Docs

```bash
ls -la /var/www/deschide_news_app/deschide_frontend/docs/
```

---

## 📊 Documentation Mapping

### For External Auditor

**Start Here (Backend Repository)**:
1. `/deschide_backend/docs/BUSINESS_OVERVIEW.md` - Project overview
2. `/deschide_backend/docs/AUDIT_SCOPE.md` - What's in/out of scope
3. `/deschide_backend/docs/README.md` - Backend documentation index
4. `/deschide_frontend/docs/README.md` - Frontend documentation index

**Backend Review (Backend Repository)**:
- API Specification: `/deschide_backend/docs/openapi.jsonld`
- Database Schema: `/deschide_backend/docs/schema.sql`
- Authentication: `/deschide_backend/docs/auth_flow.md`
- Permissions: `/deschide_backend/docs/roles_permissions.md`

**Frontend Review (Frontend Repository)**:
- API Integration: `/deschide_frontend/docs/api_integration.md`
- Authentication UI: `/deschide_frontend/docs/auth_ui.md`
- Routes: `/deschide_frontend/docs/routes_public.md`, `routes_admin.md`

---

### For Developers

**Setup & Architecture**:
- Project overview: Backend repo → `docs/BUSINESS_OVERVIEW.md`
- Backend setup: Backend repo → `README.md`
- Frontend setup: Frontend repo → `README.md`

**Backend Development**:
- API docs: Backend repo → `docs/openapi.jsonld`
- Entity relationships: Backend repo → `docs/entities.md`
- Authentication: Backend repo → `docs/auth_flow.md`
- Translations: Backend repo → `docs/multilanguage_model.md`

**Frontend Development**:
- API integration: Frontend repo → `docs/api_integration.md`
- i18n: Frontend repo → `docs/i18n_config.md`
- Public routes: Frontend repo → `docs/routes_public.md`
- Admin routes: Frontend repo → `docs/routes_admin.md`

---

## 🔄 Keeping Documentation Updated

### When Backend Code Changes

**Location**: `/var/www/deschide_news_app/deschide_backend/docs/`

```bash
cd /var/www/deschide_news_app/deschide_backend

# Edit relevant documentation
nano docs/entities.md

# Commit changes
git add docs/
git commit -m "docs: Update entity documentation"
git push origin master
```

### When Frontend Code Changes

**Location**: `/var/www/deschide_news_app/deschide_frontend/docs/`

```bash
cd /var/www/deschide_news_app/deschide_frontend

# Edit relevant documentation
nano docs/routes_admin.md

# Commit changes
git add docs/
git commit -m "docs: Update admin routes"
git push origin main
```

---

## 🔗 GitHub Links

### Backend Repository

**Repository**: https://github.com/radusoltan/deschide_news_app_backend

**Documentation**:
- Documentation Index: `/docs/README.md`
- Business Overview: `/docs/BUSINESS_OVERVIEW.md`
- Audit Scope: `/docs/AUDIT_SCOPE.md`
- API Spec: `/docs/openapi.jsonld`
- All docs: `/docs/`

### Frontend Repository

**Repository**: https://github.com/radusoltan/deschide_news_app_frontend

**Documentation**:
- Documentation Index: `/docs/README.md`
- API Integration: `/docs/api_integration.md`
- i18n Config: `/docs/i18n_config.md`
- All docs: `/docs/`

---

## 📝 Files in Project Root (Not in Git)

**Location**: `/var/www/deschide_news_app/`

These files are **local only** and **NOT tracked by git**:

| File | Purpose | Status |
|------|---------|--------|
| `CLAUDE.md` | Claude Code instructions | ✅ Local only |
| `README.md` | Project root readme | ✅ Local only |
| `DOCUMENTATION_GUIDE.md` | This file | ✅ Local only |
| `DEVELOPMENT_ENVIRONMENT.md` | Environment setup | ✅ Local only |
| `APPLICATIONS_ARCHITECTURE.md` | Infrastructure | ✅ Local only |
| `audit_prepare.md` | Audit plan | ⚠️ Copied to backend repo |
| `git_repos_setup.md` | Git setup notes | ✅ Local only |

**Important**:
- These files are for local development only
- Changes to these files do NOT go to GitHub
- They help with local workspace organization

---

## ✅ Verification Commands

### Check Backend Documentation Status

```bash
cd /var/www/deschide_news_app/deschide_backend
git status
git log --oneline docs/ | head -5
```

### Check Frontend Documentation Status

```bash
cd /var/www/deschide_news_app/deschide_frontend
git status
git log --oneline docs/ | head -5
```

### View Latest Backend Commits

```bash
cd /var/www/deschide_news_app/deschide_backend
git log --oneline -5
```

### View Latest Frontend Commits

```bash
cd /var/www/deschide_news_app/deschide_frontend
git log --oneline -5
```

---

## 🆘 Troubleshooting

### Problem: Can't find documentation

**Solution**: Check which repository the documentation belongs to:
- **Project-wide, API, Backend**: `/deschide_backend/docs/`
- **Frontend, UI, Routes**: `/deschide_frontend/docs/`

### Problem: Documentation out of sync

**Solution**: Pull latest changes:
```bash
cd /var/www/deschide_news_app/deschide_backend
git pull origin master

cd /var/www/deschide_news_app/deschide_frontend
git pull origin main
```

### Problem: Don't know where to add new documentation

**Guideline**:
- **Backend-related** (API, database, entities, auth backend): → `/deschide_backend/docs/`
- **Frontend-related** (UI, routes, components, auth UI): → `/deschide_frontend/docs/`
- **Project-wide** (overview, scope, planning): → `/deschide_backend/docs/`

---

## 📞 Support

**Questions about documentation structure:**
- Read this guide first
- Check backend: `/deschide_backend/docs/README.md`
- Check frontend: `/deschide_frontend/docs/README.md`

**Need to update documentation:**
1. Determine which repository (backend or frontend)
2. Navigate to that repository
3. Edit files in `docs/` directory
4. Commit and push changes
5. Update "Last Updated" date in file

---

**Created**: November 5, 2025
**Purpose**: Avoid confusion about documentation locations
**Status**: Active reference document
**Location**: Local only (not in git)
