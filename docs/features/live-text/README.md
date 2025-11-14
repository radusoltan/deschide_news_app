# Live Text Feature - Documentație

**Status**: ✅ Implementat și Activ
**Versiune**: 1.0
**Ultima actualizare**: 5 Noiembrie 2025

---

## 📖 Overview

**Live Text** este un feature de transmisiuni live (liveblogging) pentru Deschide News, permițând jurnaliștilor să publice actualizări în timp real despre evenimente importante.

### Caracteristici Principale

- ✅ **Posts în timp real** - Actualizări instant cu Mercure SSE
- ✅ **Rich media** - Suport pentru imagini, video embeds, tweets
- ✅ **Colaborare** - Multiple persoane pot edita simultan
- ✅ **Pinned posts** - Posts importante fixate în top
- ✅ **Editor Tiptap** - Editor rich-text modern
- ✅ **Multilingv** - Suport pentru română, engleză, rusă
- ✅ **SEO optimized** - Metadata, JSON-LD, sitemaps
- ✅ **Analytics** - Tracking vizualizări și engagement

---

## 📚 Documentație Disponibilă

| Document | Descriere | Link |
|----------|-----------|------|
| **Roadmap** | Plan complet de implementare (9 sprinturi) | [roadmap.md](./roadmap.md) |
| **Analysis** | Analiză competitivă și best practices | [analysis.md](./analysis.md) |
| **Sprint Reports** | Rapoarte detaliate implementare | [/archive/2025-11-docs-sprints/](../../../archive/2025-11-docs-sprints/) |

---

## 🚀 Quick Start

### Backend API Endpoints

```bash
# Get all live texts
GET /api/live_texts

# Get single live text with posts
GET /api/live_texts/{id}

# Get posts for a live text
GET /api/live_text_posts?liveText={id}&order[publishedAt]=DESC

# Real-time updates via Mercure
GET https://mercure.hub/.well-known/mercure?topic=https://api.deschide.local/live_texts/{id}
```

### Frontend Routes

```bash
# Public view
/[locale]/live/[slug]

# Admin - List
/[locale]/admin/live-text

# Admin - Create/Edit
/[locale]/admin/live-text/create
/[locale]/admin/live-text/edit/[id]
```

---

## 🏗️ Arhitectură

### Backend (Symfony)

```
deschide_backend/src/
├── Entity/
│   ├── LiveText.php              # Entitate principală
│   ├── LiveTextPost.php          # Posts individuale
│   ├── LiveTextCollaborator.php  # Colaboratori
│   └── LiveTextView.php          # Analytics tracking
│
├── State/
│   ├── LiveTextProvider.php      # Data provider
│   └── LiveTextPostProvider.php
│
├── Service/
│   └── LiveTextAnalyticsService.php  # Analytics
│
└── Repository/
    ├── LiveTextRepository.php
    └── LiveTextViewRepository.php
```

### Frontend (Next.js)

```
deschide_frontend/app/
├── [locale]/
│   ├── live/
│   │   └── [slug]/
│   │       └── page.tsx          # Public view
│   │
│   └── admin/
│       └── live-text/
│           ├── page.tsx          # List view
│           ├── create/
│           └── edit/[id]/
│
└── components/
    ├── live-text/
    │   ├── LiveTextViewer.tsx    # Public component
    │   ├── LiveTextPost.tsx
    │   └── LiveTextEditor.tsx    # Admin editor
    │
    └── tiptap/
        └── TiptapEditor.tsx      # Rich text editor
```

---

## 📊 Faze de Implementare

| Sprint | Tema | Status | Raport |
|--------|------|--------|--------|
| **1** | Foundation & Core Entities | ✅ Complete | [sprint1-completed.md](../../../archive/2025-11-docs-sprints/live-text-sprint1-completed.md) |
| **2** | API Resources & Providers | ✅ Complete | [sprint2-completed.md](../../../archive/2025-11-docs-sprints/live-text-sprint2-completed.md) |
| **3** | Real-time Updates (Mercure) | ✅ Complete | [sprint3-completed.md](../../../archive/2025-11-docs-sprints/live-text-sprint3-completed.md) |
| **4** | Frontend Components | ✅ Complete | [sprint4-completed.md](../../../archive/2025-11-docs-sprints/live-text-sprint4-completed.md) |
| **5** | Collaboration Features | ✅ Complete | [sprint5-completed.md](../../../archive/2025-11-docs-sprints/live-text-sprint5-completed.md) |
| **6** | Admin Panel | ✅ Complete | [sprint6-completed.md](../../../archive/2025-11-docs-sprints/live-text-sprint6-completed.md) |
| **7** | Public Interface | ✅ Complete | [sprint7-completed.md](../../../archive/2025-11-docs-sprints/live-text-sprint7-completed.md) |
| **8** | Testing & Polish | ✅ Complete | [sprint8-completed.md](../../../archive/2025-11-docs-sprints/live-text-sprint8-completed.md) |
| **9** | Deployment & Monitoring | ✅ Complete | [sprint9-completed.md](../../../archive/2025-11-docs-sprints/live-text-sprint9-completed.md) |

---

## 🔧 Tehnologii Utilizate

### Backend
- **Symfony 7.3** - Framework PHP
- **API Platform** - REST API cu Hydra/JSON-LD
- **Doctrine ORM** - Database ORM
- **Gedmo Translatable** - Multilingv
- **Mercure** - Real-time push via SSE

### Frontend
- **Next.js 16** - React framework
- **React 19.2** - UI library
- **TypeScript** - Type safety
- **Tiptap** - Rich text editor
- **TailwindCSS** - Styling
- **SWR** - Data fetching
- **EventSource** - Mercure SSE client

---

## 📖 Pentru Mai Multe Detalii

### Documentație Completă
- **Roadmap complet**: Vezi [roadmap.md](./roadmap.md) pentru:
  - Plan detaliat 9 sprinturi
  - Features breakdown
  - Timeline și dependencies
  - Technical requirements

- **Analiză competitivă**: Vezi [analysis.md](./analysis.md) pentru:
  - Comparație cu alte platforme (Guardian, BBC, NYT)
  - Best practices liveblogging
  - UX patterns
  - Technical approaches

### Rapoarte Sprint
Pentru detalii tehnice despre implementare, vezi rapoartele individuale de sprint în:
```
/archive/2025-11-docs-sprints/live-text-sprint[1-9]-completed.md
```

---

## 🔗 Link-uri Utile

- **Performance Analytics**: [/docs/features/performance-analytics/](../performance-analytics/)
- **Backend Docs**: [/deschide_backend/docs/](../../../deschide_backend/docs/)
- **Frontend Docs**: [/deschide_frontend/docs/](../../../deschide_frontend/docs/)

---

**Întrebări sau probleme?** Consultă documentația sau rapoartele de sprint pentru detalii tehnice.
