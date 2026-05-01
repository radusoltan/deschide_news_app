# Documentation Index - Deschide News App

**Cross-cutting Documentation** - Features, Infrastructure, Planning

**Ultima actualizare**: 5 Noiembrie 2025

---

## 🗂️ Structura

```
docs/
├── features/               # Features complexe
│   ├── live-text/
│   ├── performance-analytics/
│   └── archive-import/
├── infrastructure/         # Setup infrastructure
│   ├── redis-schema.md
│   ├── statistics-schema.md
│   ├── cron-setup.md
│   └── monitoring-guide.md
└── planning/               # Planuri active
```

---

## ✨ Features

### 📡 Live Text
[features/live-text/](./features/live-text/)

Liveblogging cu real-time updates (Mercure SSE).

**Docs**:
- [README.md](./features/live-text/README.md) - Overview & quickstart
- [roadmap.md](./features/live-text/roadmap.md) - 9 sprinturi plan
- [analysis.md](./features/live-text/analysis.md) - Analiză competitivă

### 📊 Performance Analytics
[features/performance-analytics/](./features/performance-analytics/)

Analytics complet pentru articles și live texts.

**Docs**:
- [README.md](./features/performance-analytics/README.md) - Quickstart
- [COMPLETE.md](./features/performance-analytics/COMPLETE.md) - Sistem complet
- [ADVANCED.md](./features/performance-analytics/ADVANCED.md) - Advanced usage
- [admin-stats-api.md](./features/performance-analytics/admin-stats-api.md) - API reference

### 📥 Archive Import
[features/archive-import/](./features/archive-import/)

Strategie import arhivă.

**Docs**:
- [strategy.md](./features/archive-import/strategy.md)
- [sources-analysis.md](./features/archive-import/sources-analysis.md)

---

## 🏗️ Infrastructure

[infrastructure/](./infrastructure/)

| Document | Descriere |
|----------|-----------|
| [redis-schema.md](./infrastructure/redis-schema.md) | Redis keys, TTL, namespacing |
| [statistics-schema.md](./infrastructure/statistics-schema.md) | PostgreSQL schema analytics |
| [cron-setup.md](./infrastructure/cron-setup.md) | Cron jobs scheduled tasks |
| [monitoring-guide.md](./infrastructure/monitoring-guide.md) | Prometheus + Grafana |

---

## 📋 Planning

[planning/](./planning/)

- [tags-keywords-implementation-plan.md](./planning/tags-keywords-implementation-plan.md)
- [PHASE6_IMPLEMENTATION_SUMMARY.md](./planning/PHASE6_IMPLEMENTATION_SUMMARY.md)

---

## 🔗 Link-uri

- **Backend Docs**: [../deschide_backend/docs/](../deschide_backend/docs/)
- **Frontend Docs**: [../deschide_frontend/docs/](../deschide_frontend/docs/)
- **Archive**: [../archive/](../archive/)
- **Setup**: [../SETUP.md](../SETUP.md)
- **Architecture**: [architecture/README.md](./architecture/README.md)

---

**Ultima actualizare**: 5 Noiembrie 2025
