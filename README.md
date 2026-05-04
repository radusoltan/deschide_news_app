# Deschide News App

**Multilanguage News Platform** - Modern news application with Symfony backend and Next.js frontend.

[![Backend: Symfony 7.3](https://img.shields.io/badge/Backend-Symfony%207.3-black?logo=symfony)](https://symfony.com)
[![Frontend: Next.js 16](https://img.shields.io/badge/Frontend-Next.js%2016-black?logo=next.js)](https://nextjs.org)
[![PHP: 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php)](https://php.net)
[![TypeScript](https://img.shields.io/badge/TypeScript-5.x-3178C6?logo=typescript)](https://typescriptlang.org)

## 📋 Project Overview

**Deschide News App** is a full-stack multilanguage news platform with unified monorepo architecture:

- **Backend**: Symfony 7.3 (PHP 8.4) - RESTful API with JSON-LD/Hydra
- **Frontend**: Next.js 16 (React 19.2, TypeScript) - Modern web interface
- **Languages**: Romanian (ro), English (en), Russian (ru)
- **Architecture**: Monorepo with unified version control

---

## 🚀 Quick Start

```bash
# Clone the monorepo
git clone git@github.com:radusoltan/deschide_news_app.git
cd deschide_news_app

# Backend (Symfony API)
cd apps/backend
cp .env.example .env.local
composer install
symfony console doctrine:database:create
symfony console doctrine:migrations:migrate
symfony serve -d --port=8081
# → http://127.0.0.1:8081/api

# Frontend (Next.js)
cd ../frontend
cp .env.example .env.local
pnpm install
pnpm dev
# → http://localhost:3005
```

**Pentru setup complet**: Vezi [CLAUDE.md](./CLAUDE.md)

---

## 📖 Documentație

### 🎯 Start Here

| Document | Descriere | Când să-l folosești |
|----------|-----------|---------------------|
| **[CLAUDE.md](./CLAUDE.md)** | Instrucțiuni pentru Claude Code AI + Setup complet | Prima dată când configurezi proiectul |
| **[README.md](./README.md)** | Acest document - Overview proiect | Pentru înțelegerea generală |
| **[docs/GIT_MONOREPO_MIGRATION_PLAN.md](./docs/GIT_MONOREPO_MIGRATION_PLAN.md)** | Plan migrare monorepo | Documentație migrare (Nov 2025) |

### 📚 Documentație Detaliată

| Categorie | Locație | Conținut |
|-----------|---------|----------|
| **Cross-cutting** | [docs/](./docs/) | Monorepo migration, infrastructure, planning |
| **Backend** | [apps/backend/](./apps/backend/) | API, entități, arhitectură backend |
| **Backend Docs** | [apps/backend/docs/](./apps/backend/docs/) | Documentație specifică backend |
| **Frontend** | [apps/frontend/](./apps/frontend/) | Components, routing, setup frontend |
| **Frontend Docs** | [apps/frontend/docs/](./apps/frontend/docs/) | Documentație specifică frontend |
| **Archive** | [archive/](./archive/) | Rapoarte istorice, sprint reports, old code |

---

## 🏗️ Arhitectură

### Stack Tehnologic

```
┌─────────────────────────────────────┐
│      Next.js 16 (Frontend)          │
│  React 19.2 | TypeScript | Tailwind │
│           Port: 3005                │
└──────────────┬──────────────────────┘
               │ REST API (JSON-LD)
               ▼
┌─────────────────────────────────────┐
│      Symfony 7.3 (Backend)          │
│   PHP 8.4 | API Platform | Doctrine │
│           Port: 8081                │
└───┬─────┬─────┬─────┬─────┬─────────┘
    │     │     │     │     │
    ▼     ▼     ▼     ▼     ▼
[PgSQL][Redis][ES][RMQ][Mercure]
```

### Servicii

| Serviciu | Port | Rol |
|----------|------|-----|
| **PostgreSQL 17** | 5432 | Database principal |
| **Redis** | 6379 | Cache, sessions (DB 1) |
| **Elasticsearch** | 9200 | Full-text search |
| **RabbitMQ** | 5672 | Message queue (async) |
| **Mercure** | 3000 | Real-time updates |
| **CDN Server** | 8082 | Static assets |

**Detalii**: Vezi [docs/architecture/README.md](./docs/architecture/README.md)

---

## ✨ Features Principale

### 🗞️ Content Management
- ✅ **Articles** - Sistem complet CRUD cu versioning
- ✅ **Categories** - Ierarhie categorii cu părinte-copil
- ✅ **Authors** - Multi-autor support
- ✅ **Images** - Upload + 10 profile thumbnail auto-generate

### 🌍 Multilanguage
- ✅ **3 Limbi**: Română (ro), Engleză (en), Rusă (ru)
- ✅ **Gedmo Translatable**: Traduceri separate per entitate
- ✅ **URL localizate**: `/ro/articol`, `/en/article`, `/ru/статья`
- ✅ **Fallback**: Auto-fallback la limba default

### 📡 Live Text (Liveblogging)
- ✅ **Real-time updates** via Mercure SSE
- ✅ **Rich editor** Tiptap pentru posts
- ✅ **Colaborare** Multi-user editing
- ✅ **Analytics** Tracking vizualizări
- **Docs**: [docs/features/live-text/](./docs/features/live-text/)

### 📊 Performance Analytics
- ✅ **Article views** tracking
- ✅ **Engagement metrics**
- ✅ **Real-time stats**
- ✅ **Admin dashboard**
- **Docs**: [docs/features/performance-analytics/](./docs/features/performance-analytics/)

### 🔐 Security & Auth
- ✅ **JWT Authentication** (Lexik + Gesdinet Refresh)
- ✅ **Role-based access** (USER, EDITOR, ADMIN, SUPER_ADMIN)
- ✅ **CORS configuration**
- ✅ **Rate limiting**

### 🔍 Search & SEO
- ✅ **Elasticsearch** full-text search multi-language
- ✅ **SEO metadata** dynamic per articol
- ✅ **JSON-LD** structured data
- ✅ **Sitemaps** auto-generate
- ✅ **Open Graph** și Twitter Cards

---

## 📁 Repository Structure (Monorepo)

```
deschide_news_app/              # 🏠 Monorepo root
│
├── .git/                       # Git repository (unified)
├── .gitignore                  # Root gitignore
├── CLAUDE.md                   # 🤖 AI assistant instructions + Setup guide
├── README.md                   # 📖 This file - Project overview
│
├── apps/                       # 📦 Applications
│   ├── backend/               # 🔧 Symfony 7.3 API (PHP 8.4)
│   │   ├── config/           # Configuration files
│   │   ├── src/              # PHP source code
│   │   │   ├── Command/     # Console commands
│   │   │   ├── Controller/  # API controllers
│   │   │   ├── Entity/      # Doctrine entities
│   │   │   ├── State/       # API Platform providers/processors
│   │   │   └── ...
│   │   ├── migrations/       # Database migrations
│   │   ├── docs/             # Backend documentation
│   │   ├── composer.json     # PHP dependencies
│   │   └── README.md         # Backend README
│   │
│   └── frontend/             # 🎨 Next.js 16 (React 19.2, TypeScript)
│       ├── app/              # App Router pages
│       ├── components/       # React components
│       ├── lib/              # Utilities and helpers
│       ├── docs/             # Frontend documentation
│       ├── package.json      # Node dependencies
│       └── README.md         # Frontend README
│
├── docs/                       # 📚 Centralized documentation
│   ├── GIT_MONOREPO_MIGRATION_PLAN.md  # Monorepo migration plan
│   ├── architecture/          # Architecture docs
│   ├── planning/              # Planning documents
│   └── ...
│
├── sprints/                   # 🎯 Sprint planning
├── scripts/                   # 🔧 Deployment & utility scripts
├── .github/
│   └── workflows/            # GitHub Actions CI/CD
│
└── archive/                   # 📦 Historical backups
    ├── monorepo_migration/   # Old directories from migration
    ├── 2025-11-root/
    └── 2025-11-docs-sprints/
```

---

## 🔧 Development

### Comenzi Comune

#### Backend
```bash
cd apps/backend

# Server development
symfony serve -d --port=8081

# Database
symfony console doctrine:migrations:migrate
symfony console doctrine:fixtures:load

# Cache
symfony console cache:clear

# Elasticsearch
symfony console app:elasticsearch:create-index
symfony console app:elasticsearch:index-articles

# Workers (async processing)
symfony console messenger:consume async -vv
```

#### Frontend
```bash
cd apps/frontend

# Development
pnpm dev

# Build production
pnpm build
pnpm start

# Lint
pnpm lint
```

**Detalii**: Vezi [CLAUDE.md](./CLAUDE.md) și [apps/backend/README.md](./apps/backend/README.md) pentru toate comenzile

---

## 📊 Status Proiect

### ✅ Implementat

| Feature | Backend | Frontend | Status |
|---------|---------|----------|--------|
| Articles CRUD | ✅ | ✅ | Production |
| Categories | ✅ | ✅ | Production |
| Authors | ✅ | ✅ | Production |
| Images + Thumbnails | ✅ | ✅ | Production |
| Multilanguage | ✅ | ✅ | Production |
| Live Text | ✅ | ✅ | Production |
| Analytics | ✅ | ✅ | Production |
| Authentication | ✅ | ✅ | Production |
| Search (Elasticsearch) | ✅ | ✅ | Production |
| Real-time (Mercure) | ✅ | ✅ | Production |

### 🎯 Port Configuration

| Application | Port | Status |
|-------------|------|--------|
| Backend (Symfony) | 8081 | ✅ Running |
| Frontend (Next.js) | 3005 | ✅ Running |
| CDN Server | 8082 | ✅ Running |
| PostgreSQL | 5432 | ✅ Active |
| Redis | 6379 | ✅ Active |
| Elasticsearch | 9200 | ✅ Active |
| RabbitMQ | 5672 | ⬜ Optional |
| Mercure | 3000 | ⬜ Optional |

---

## 🧪 Testing

### Backend
```bash
cd apps/backend
vendor/bin/phpunit
vendor/bin/phpstan analyse  # (când e configurat)
```

### Frontend
```bash
cd apps/frontend
pnpm test  # (când sunt configurate)
```

---

## 📚 Documentație Extinsă

### Pentru Dezvoltatori

| Subiect | Document |
|---------|----------|
| **Setup complet** | [CLAUDE.md](./CLAUDE.md) |
| **Backend API** | [apps/backend/docs/](./apps/backend/docs/) |
| **Frontend** | [apps/frontend/docs/](./apps/frontend/docs/) |
| **Monorepo Migration** | [docs/GIT_MONOREPO_MIGRATION_PLAN.md](./docs/GIT_MONOREPO_MIGRATION_PLAN.md) |

### Istoric

| Tip | Locație |
|-----|---------|
| **Sprint Reports** | [archive/2025-11-docs-sprints/](./archive/2025-11-docs-sprints/) |
| **Old Structure** | [archive/monorepo_migration/](./archive/monorepo_migration/) |

---

## 🔗 Link-uri Utile

### Development
- **Backend API**: http://127.0.0.1:8081/api
- **API Docs (Hydra)**: http://127.0.0.1:8081/api/docs.jsonld
- **Frontend**: http://localhost:3005
- **CDN Assets**: http://127.0.0.1:8082/uploads/

### Production (când e configurat)
- **API**: https://api.deschide.md
- **Frontend**: https://deschide.md
- **CDN**: https://cdn.deschide.md

---

## 🤝 Contributing

Pentru contribuții:
1. Consultă [CLAUDE.md](./CLAUDE.md) pentru ghidare AI-assisted development
2. Citește [apps/backend/README.md](./apps/backend/README.md) pentru backend
3. Citește [apps/frontend/README.md](./apps/frontend/README.md) pentru frontend
4. Urmează pattern-urile existente în cod
5. Testează înainte de commit

---

## 📝 Licență

Proprietar - © 2025 Deschide News

---

## 📞 Suport

Pentru probleme sau întrebări:
- Consultă documentația în `docs/` și `apps/*/docs/`
- Vezi rapoartele de sprint în `archive/`
- Review CLAUDE.md pentru ghidare AI

---

**Ultima actualizare**: 14 Noiembrie 2025
**Status**: ✅ Production Ready
**Repository Type**: Monorepo (migrated November 2025)
