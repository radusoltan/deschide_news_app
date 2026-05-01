# Agents for Deschide News App

Acest director conține agenții specializați pentru dezvoltare, testare,
migrare date și mentenanța portalului multilingv Deschide News.

> **Design Philosophy** — toți agenții urmează principiile Anthropic
> *"Building Effective Agents"*:
> 1. **Simplicity** — o responsabilitate focalizată per agent
> 2. **Transparency** — pași de planificare expliciți, decizii vizibile
> 3. **Well-documented ACI** — instrumentar și pattern-uri bine documentate

---

## Stack curent de modele

> Actualizat: **2026-05-01** (după consolidare)

| Tier | Model | Identifier API | Folosit de |
|------|-------|---------------|-----------|
| 🟣 **Opus 4.7** | `claude-opus-4-7` | $5/$25 per MTok | 6 agenți (decizii strategice + multi-agent) |
| 🔵 **Sonnet 4.6** | `claude-sonnet-4-6` | $3/$15 per MTok | 24 agenți (dezvoltare + testing + specialitate) |
| 🟢 **Haiku 4.5** | `claude-haiku-4-5-20251001` | $1/$5 per MTok | 2 agenți (volume mare, rule-based) |

**Total: 32 agenți activi.** (Excluzând `_archive/` și fișierele auxiliare.)

---

## Quick Reference — toți agenții activi

### 🟣 Opus 4.7 — decision-making strategic / multi-agent / evidence-bound (6)

| Agent | Purpose |
|-------|---------|
| `workflow-orchestrator` | Master coordinator pentru workflow-uri multi-agent complexe |
| `ai-integration-engineer` | LLM routing strategy (Gemini ↔ Claude) și design fail-safe |
| `security-auditor` | Detecție vulnerabilități full-stack, evidence-bound |
| `database-engineer` | PostgreSQL + Redis + Elasticsearch tuning și arhitectură |
| `design-system-architect` | Tailwind 4 `@theme` tokens, oklch, dark mode (single source of truth) |
| `e2e-test-scenario-designer` | Design + prioritizare scenarii test (planning, NU execuție) |

### 🔵 Sonnet 4.6 — dezvoltare, testing, specialitate (24)

#### Dezvoltare Frontend (4)
| Agent | Purpose |
|-------|---------|
| `public-frontend-developer` | UI public Next.js 16 + secțiunea "Premium Polish" (consolidată) |
| `design-review` | Audit PR front-end (Stripe/Linear-grade) |
| `accessibility-auditor` | WCAG 2.2 AA, EAA compliance, read-only |
| `telegram-distribution-specialist` | Telegram Instant View + OG images |

#### Dezvoltare Backend & Infrastructure (5)
| Agent | Purpose |
|-------|---------|
| `backend-developer` | Servicii Symfony 8 + API Platform + Doctrine (NEW) |
| `cache-sync-specialist` | Cache L1/L2/L3 + ODR (On-Demand Revalidation) |
| `fixture-engineer` | Doctrine DataFixtures + Gedmo Translatable |
| `deployment-specialist` | Deploy FRA1 (DigitalOcean Frankfurt) (NEW) |
| `dev-reset-orchestrator` | Pipeline `app:dev:reset` end-to-end |

#### SEO & Editorial (3)
| Agent | Purpose |
|-------|---------|
| `seo-specialist` | JSON-LD, Core Web Vitals, multilingual SEO |
| `docusaurus-expert` | Site documentație Docusaurus v2/v3 |
| `email-press-redactor` | Procesare comunicate de presă din Zoho Mail + creare task-uri editor (NEW: MCP Notion+Obsidian) |

#### Documentation & Coordination (1)
| Agent | Purpose |
|-------|---------|
| `documentation-keeper` | Single source of truth pentru scrieri destructive Notion+Obsidian (NEW 2026-05-01) |

#### Data Migration (4)
| Agent | Purpose |
|-------|---------|
| `data-import-orchestrator` | Plan și coordonare migrare Newscoop + CSV |
| `newscoop-importer` | Import Newscoop MySQL → Symfony |
| `csv-articles-importer` | Import buchis*.csv (Playwright-scraped) |
| `import-mapper` | Mapare ID-uri externe + merge duplicate |

#### Automated Testing (6)
| Agent | Purpose |
|-------|---------|
| `backend-api-tester` | Test API Symfony (REST + JWT + i18n) |
| `frontend-e2e-tester` | E2E Playwright pe frontend Next.js |
| `fullstack-integration-tester` | Workflow complet (BE+FE+DB+CDN+ES) |
| `multilanguage-tester` | Validare i18n RO/EN/RU + fallback |
| `admin-panel-tester` | CRUD admin + locking articole |
| `performance-tester` | Core Web Vitals + ISR + cache hit rate |

#### Manual Testing (1)
| Agent | Purpose |
|-------|---------|
| `manual-frontend-tester` | Testare exploratorie Playwright |

### 🟢 Haiku 4.5 — volume mare, rule-based, decizii simple (2)

| Agent | Purpose |
|-------|---------|
| `import-validator` | Rule-based validation post-import (SQL queries fixe) |
| `git-flow-manager` | Git Flow (feature/release/hotfix) cu reguli rigide |

---

## Convenții transversale

### Color coding

| Color | Semnificație | Agenți |
|-------|--------------|--------|
| 🟢 green | Testing & QA | toate testing agents + accessibility-auditor + manual-frontend-tester |
| 🟡 gold | Orchestration & data migration | workflow, dev-reset, data-import-orchestrator + 3× importers |
| 🟣 purple | Frontend development & code-heavy | public-frontend-developer, fixture-engineer, backend-developer |
| 🔵 blue | Infrastructure | database-engineer, cache-sync-specialist, telegram-distribution-specialist |
| 🟧 orange | Deployment | deployment-specialist (NEW) |
| 🔴 red | Destructive / risk | dev-reset-orchestrator, security-auditor |
| 🩵 cyan | Design tokens | design-system-architect |
| 🩷 pink | Design review | design-review |
| 🫖 teal | Editorial | email-press-redactor |

### Permission modes

- **`default`** — agenți read-only sau cu approval gate (testing, audit, planning, infrastructure read-only)
- **`acceptEdits`** — agenți care creează/modifică cod direct (developers, fixture-engineer, importers, telegram-specialist, design-system-architect, email-press-redactor, cache-sync-specialist, csv-articles-importer)

---

## Modificări recente (2026-05-01)

### ✅ MCP Notion + Obsidian disponibile pentru agenți

Adiionare la `.mcp.json` la nivel proiect a server-elor `notion` (HTTP, oficial Anthropic) și `obsidian` (`@bitbonsai/mcpvault`). Acces granular per agent prin frontmatter `tools:`. Pilot scope: 4 agenți cu acces (workflow-orchestrator, documentation-keeper NEW, e2e-test-scenario-designer, email-press-redactor).

### ✅ Agent nou: `documentation-keeper`

- Single source of truth pentru scrieri destructive Notion + Obsidian
- STOP gates explicite pe orice operație ireversibilă
- Owner pentru scrieri ADR și sprint close-outs
- Sonnet 4.6 (Opus over-kill pentru work de înaltă disciplină, nu înaltă creativitate)

### ✅ Upgrade modele (toi cei 30 → 32 agenți)

- 6 agenți → **Opus 4.7** (de la Sonnet 3.5)
- 24 agenți → **Sonnet 4.6** (de la Sonnet 3.5 / `sonnet` alias / Sonnet 4 / Sonnet 4.5)
- 2 agenți → **Haiku 4.5** (de la Sonnet 3.5 / `sonnet`)

### ✅ Consolidări structurale

- ❌ **Eliminat `premium-ui-designer`** — overlap cu `public-frontend-developer`
  - Conținutul mutat în secțiunea "Premium Polish & Micro-interactions" a `public-frontend-developer`
  - Fișierul original păstrat în `_archive/` cu notă de successor
- ✅ **Adăugat `backend-developer`** — gap identificat: nu exista un agent generic pentru servicii Symfony / API Platform / providers / voters (CC făcea ad-hoc)
- ✅ **Adăugat `deployment-specialist`** — gap identificat: roadmap cere "FRA1 production deployment" dar lipsea owner

### 🔮 Pending (în roadmap)

Următoarele consolidări post-v1.5.0:
- Sunset 5 agenți migration după ce migrarea Newscoop+CSV e completă (consolidare în `legacy-data-specialist` arhivat)
- Recodificare color `gold` în 3 categorii distincte (orchestration vs migration vs AI)
- Posibil agent nou pentru CI/CD pipelines (Github Actions / DigitalOcean App Platform)
- Posibil agent nou pentru monitoring/observability (Sentry, logging, alerting)

---

## Cum invoci un agent

```
@<agent-name> [task description]
```

Exemple:
```
@workflow-orchestrator deploy v1.5.0 end-to-end with full pre-release testing
@backend-developer add a new endpoint POST /api/articles/{id}/republish
@deployment-specialist plan blue-green deploy for v1.5.0
@public-frontend-developer create a Hero section with editorial design
@accessibility-auditor audit homepage for WCAG 2.2 AA
@security-auditor run security audit on auth flow
```

---

## Director structure

```
.claude/agents/
├── README.md                          (acest fișier)
├── _TEMPLATE_AGENT.md                 (template pentru noi agenți)
├── _archive/                          (agenți consolidați/retrași)
│   ├── README.md
│   └── premium-ui-designer.md
├── skills/                            (skill-uri shared)
└── 31× *.md                           (agenți activi)
```

---

## Agenți după domeniu (referință cross-cut)

### Cine se ocupă de un articol nou?

```
1. backend-developer        → endpoint API
2. fixture-engineer         → date de test
3. backend-api-tester       → validare endpoint
4. public-frontend-developer → UI articol
5. seo-specialist           → JSON-LD + meta tags
6. cache-sync-specialist    → ODR pentru frontend
7. accessibility-auditor    → WCAG check pe articol
8. telegram-distribution-specialist → IV template
9. multilanguage-tester     → verificare i18n
10. e2e-test-scenario-designer → scenariu E2E
11. frontend-e2e-tester     → execuție E2E
```

### Cine deploy-ează v1.5.0?

```
1. workflow-orchestrator    → coordonator
2. git-flow-manager         → release branch + tag
3. backend-api-tester       → smoke test API
4. fullstack-integration-tester → smoke test workflow
5. performance-tester       → Core Web Vitals în staging
6. security-auditor         → audit de securitate
7. deployment-specialist    → deploy FRA1
8. dev-reset-orchestrator   → reset local pentru următorul sprint
```

---

## References

- **Project root**: `/var/www/deschide_news_app/`
- **CLAUDE.md** — instrucțiuni generale proiect
- **Anthropic Best Practices**:
  - [Building Effective Agents](https://www.anthropic.com/research/building-effective-agents)
  - [Effective Context Engineering](https://www.anthropic.com/engineering/effective-context-engineering-for-ai-agents)
  - [Writing Tools for Agents](https://www.anthropic.com/engineering/writing-tools-for-agents)
