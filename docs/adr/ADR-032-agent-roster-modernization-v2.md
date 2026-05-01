---
adr: 32
title: Agent Roster Modernization v2 — MCP Integration & Model Tier Distribution
status: Accepted
date_proposed: 2026-04-30
date_decided: 2026-05-01
extends:
  - adr: 31
    sections: [D1]
related: [31, 27, 24]
sprint: 60
author: Radu
---

# ADR-032 — Agent Roster Modernization v2 — MCP Integration & Model Tier Distribution

## Status

Accepted (decided 2026-05-01).

## Context

### Roster pre-modernization

Before Sprint 60 modernization, the `.claude/agents/` roster contained 30 active agents. Model distribution was inconsistent: the majority ran on unspecified aliases (`sonnet`, `claude-sonnet-3.5`) with no tier discipline. Gaps existed for:

- **Backend Symfony implementation** — no dedicated agent; work was handled ad-hoc by Claude Code directly
- **Production deployment** — no agent owned the FRA1 (DigitalOcean Frankfurt) pipeline, blue-green deploy, rollback, SSL, and nginx config
- **Destructive documentation writes** — no single owner for Notion/Obsidian destructive operations; multiple agents had overlapping partial access, increasing blast radius risk

### Pilot MCP rationale (3 tests, 3/3 PASS)

MCP Notion + Obsidian servers were added to `.mcp.json` at project root. Before granting write access broadly, a 3-test pilot was run:

- **Test 1 (Notion read)**: `workflow-orchestrator` fetched the active sprint page and sprint tasks from Notion Sprints DB and Tasks DB. Read-only validation confirmed the agent could navigate the schema correctly without any writes.
- **Test 2 (Obsidian read + scoped write)**: `e2e-test-scenario-designer` read ADRs from Obsidian, then `email-press-redactor` appended a pipeline log entry via `patch_note`. Confirmed scoped append-only Obsidian write without clobbering existing content. Pilot test artifact: `.claude/commands/pw-test-locale-switcher-disabled.md`.
- **Test 3 (Notion create)**: `e2e-test-scenario-designer` created a tracking Task in the Notion Tasks DB linked to the active sprint. Confirmed correct field mapping (Status, Sprint relation, Actual hrs) against the Tasks DB schema.

All 3 tests passed. Write access promoted from pilot scope to 4-agent grant (see D4).

### ADR-027 numbering conflict (background — resolved)

The vault previously had a duplicate ADR-027 entry titled `multi-agent-orchestration-discipline`. The repo's canonical ADR-027 was `article-provider-locale-gate-scope`. The conflict was resolved during Sprint 60 recovery: the vault entry was renumbered to ADR-031, and both vault and repo now agree that canonical ADR-027 = `article-provider-locale-gate-scope`. This ADR documents the resolution for traceability.

### YAML frontmatter bug

Approximately 15 agents have broken YAML in their `---` frontmatter blocks (unquoted colons, missing required fields, incorrect type literals) that block them at Claude Code registry load. Sprint 60 spot-fixed 2 of them (`e2e-test-scenario-designer`, `public-frontend-developer`) during pilot test setup; bulk fix of the remaining ~15 is deferred post-v1.5.0. Separately, Sprint 60 added explicit `model:` fields to 15 working agents (with valid YAML but no tier assignment) as part of the model tier discipline sweep.

## Decision

### D1 — Roster additions

Three new agents were added in Sprint 60:

| Agent | Scope |
|---|---|
| `backend-developer` | Backend Symfony 8 service code: State Providers/Processors, Voters, custom API Platform resources, Doctrine repositories. Excludes: fixture creation (fixture-engineer), DB tuning (database-engineer), raw migrations (database-engineer). |
| `deployment-specialist` | FRA1 production deploy pipeline (DigitalOcean Frankfurt): blue-green deploys, rollback procedures, SSL/TLS config, nginx config, Supervisor worker management in production. Does NOT own staging or local dev resets. |
| `documentation-keeper` | Single source of truth for all destructive and structural writes to Notion workspace and Obsidian vault. Owns sprint close-outs, ADR creation, vault restructuring (move/delete/rename), Notion sprint/task lifecycle finalization. All other agents delegate destructive ops here. |

### D2 — Roster archival

`premium-ui-designer` was moved to `.claude/agents/_archive/premium-ui-designer.md`.

Reason: its scope was fully subsumed by `public-frontend-developer` (which absorbed the "Premium Polish & Micro-interactions" section) combined with the `frontend-design` skill. No active sprint dispatched `premium-ui-designer` post-Sprint 55. Archival is non-destructive: the file is preserved in `_archive/` with a successor note.

### D3 — Model tier distribution

All 32 active agents were assigned to one of three explicit tiers. Assignment criteria:

| Tier | Model | Criteria | Agents assigned |
|---|---|---|---|
| **Opus 4.7** | `claude-opus-4-7` | Orchestration, multi-step planning, architectural decisions, evidence-bound audit, ADR drafting | `workflow-orchestrator`, `ai-integration-engineer`, `security-auditor`, `database-engineer`, `design-system-architect`, `e2e-test-scenario-designer` (6 agents) |
| **Sonnet 4.6** | `claude-sonnet-4-6` | Implementation work, code generation, single-domain expertise, editorial, documentation | All remaining 24 agents (backend-developer, frontend developers, testers, importers, documentation-keeper, etc.) |
| **Haiku 4.5** | `claude-haiku-4-5-20251001` | Read-only audits, quick lookups, rule-based deterministic checks with high invocation volume | `import-validator`, `git-flow-manager` (2 agents) |

**Note on documentation-keeper tier**: Sonnet 4.6 was chosen deliberately over Opus 4.7. Documentation work requires discipline and precision, not creative synthesis. Sonnet 4.6 is the correct trade-off: lower cost, adequate capability for structured write operations, reduced hallucination risk on rule-following tasks.

### D4 — MCP integration policy

MCP servers `notion` (HTTP, official Anthropic) and `obsidian` (`@bitbonsai/mcpvault`) were declared in `.mcp.json`. Per-agent access is governed by the `tools:` field in each agent's YAML frontmatter.

**Default scope (all agents not listed below)**: no MCP tools — Notion and Obsidian access is blocked by omission from `tools:`.

**Pilot scope — 4 agents with MCP access after 3/3 test pass:**

| Agent | Notion access | Obsidian access | Rationale |
|---|---|---|---|
| `workflow-orchestrator` | Read + write (create, update, move; no schema changes) | Read + write (no destructive ops — no delete_note, no move_note across top-level folders) | Orchestrator needs to create tasks, update sprint status, and write sprint logs as part of multi-agent workflow coordination |
| `documentation-keeper` | Full (including move-pages, duplicate-page) | Full (including delete_note, move_note, move_file) | Owns all destructive documentation ops by design; STOP gates enforce oversight before every destructive call |
| `e2e-test-scenario-designer` | Read + create pages (creates tracking Tasks linked to active sprint) | Read-only (reference ADRs and sprint logs during scenario design) | Needs to create Tasks in Notion Tasks DB; does not need Obsidian write — scenario files go to repo `.claude/commands/` |
| `email-press-redactor` | Search + create pages (creates editorial review tasks for editors post-publish) | Read + append-only patch_note (appends to pipeline log; never overwrites) | Needs Notion task creation for editor review workflow; needs Obsidian log append for pipeline traceability |

**Promotion to write scope criteria** (for future agents requesting MCP write access): minimum 3-test pilot pattern — (1) read-only validation of target schema, (2) scoped write test with human review of output, (3) integration test in realistic sprint context. No bulk promotion.

### D5 — ADR-027 conflict resolution (documentary)

The vault entry previously numbered ADR-027 (`multi-agent-orchestration-discipline`) was renumbered to ADR-031. The repo's ADR-027 (`article-provider-locale-gate-scope`) is now the canonical 027 in both vault and repo. This decision is documentary — the execution occurred during Sprint 60 recovery. Both vault and repo agree as of 2026-05-01.

### D6 — Canonical agent-task matrix (extends ADR-031 D1)

The agent-task matching matrix in ADR-031 D1 mapped task types to AI engines (Claude Code, Gemini, Codex) but did not enumerate the agent roster. With 32 named agents and 3 new additions (backend-developer, deployment-specialist, documentation-keeper) that were absent from D1, this decision declares the canonical post-modernization agent-to-task mapping.

**Canonical agent roster (32 active, post-Sprint 60):**

#### Opus 4.7 — Strategic decision-making (6)

| Agent | Primary task domain |
|---|---|
| `workflow-orchestrator` | Multi-agent coordination, sprint planning, STOP-gate enforcement |
| `ai-integration-engineer` | LLM routing (Gemini/Claude), provider registry design, fail-safe architecture |
| `security-auditor` | Full-stack vulnerability detection, evidence-bound audit |
| `database-engineer` | PostgreSQL + Redis + Elasticsearch tuning and architecture |
| `design-system-architect` | Tailwind 4 `@theme` tokens, oklch color space, dark mode — single source of truth |
| `e2e-test-scenario-designer` | E2E scenario design, coverage matrix, Playwright step mapping, Notion task creation |

#### Sonnet 4.6 — Implementation and specialization (24)

**Frontend development (4):**

| Agent | Primary task domain |
|---|---|
| `public-frontend-developer` | Public-facing Next.js 16 UI, Bento grid, article pages, Premium Polish |
| `design-review` | PR frontend audit (Stripe/Linear-grade visual review) |
| `accessibility-auditor` | WCAG 2.2 AA, EAA compliance, read-only audit |
| `telegram-distribution-specialist` | Telegram Instant View templates, OG image generation |

**Backend and infrastructure (5):**

| Agent | Primary task domain |
|---|---|
| `backend-developer` | Symfony 8 services, API Platform State Providers/Processors, Voters, Doctrine repositories |
| `cache-sync-specialist` | L1/L2/L3 cache, On-Demand Revalidation (ODR) |
| `fixture-engineer` | Doctrine DataFixtures, Gedmo Translatable test data |
| `deployment-specialist` | FRA1 production deploy, blue-green, rollback, SSL, nginx, Supervisor |
| `dev-reset-orchestrator` | `app:dev:reset` pipeline end-to-end |

**SEO and editorial (3):**

| Agent | Primary task domain |
|---|---|
| `seo-specialist` | JSON-LD structured data, Core Web Vitals, multilingual SEO |
| `docusaurus-expert` | Docusaurus v2/v3 documentation site |
| `email-press-redactor` | Press release email processing, draft article creation, Zoho Mail integration |

**Documentation and coordination (1):**

| Agent | Primary task domain |
|---|---|
| `documentation-keeper` | Destructive Notion/Obsidian writes, sprint close-outs, ADR creation, vault restructuring |

**Data migration (4):**

| Agent | Primary task domain |
|---|---|
| `data-import-orchestrator` | Newscoop + CSV migration plan and coordination |
| `newscoop-importer` | Newscoop MySQL → Symfony entity import |
| `csv-articles-importer` | `buchis*.csv` import (Playwright-scraped articles) |
| `import-mapper` | External ID mapping, duplicate merge |

**Automated testing (6):**

| Agent | Primary task domain |
|---|---|
| `backend-api-tester` | Symfony REST API testing (JWT, i18n, pagination) |
| `frontend-e2e-tester` | E2E Playwright on Next.js frontend |
| `fullstack-integration-tester` | Full-stack workflow (BE + FE + DB + CDN + ES) |
| `multilanguage-tester` | i18n RO/EN/RU validation + locale fallback |
| `admin-panel-tester` | Admin CRUD + article locking |
| `performance-tester` | Core Web Vitals, ISR, cache hit rate |

**Manual testing (1):**

| Agent | Primary task domain |
|---|---|
| `manual-frontend-tester` | Exploratory Playwright testing |

#### Haiku 4.5 — High-volume, rule-based (2)

| Agent | Primary task domain |
|---|---|
| `import-validator` | Rule-based post-import validation (fixed SQL queries) |
| `git-flow-manager` | Git Flow operations (feature/release/hotfix) with rigid rules |

**Agent-engine compatibility (updated from ADR-031 D1):**

| Task type | Primary agent(s) | Engine constraint |
|---|---|---|
| Backend PHP/Symfony implementation | `backend-developer` | Claude Code Sonnet; Gemini excluded (fabrication risk) |
| Frontend TypeScript/React | `public-frontend-developer`, `design-review` | Claude Code; Gemini excluded |
| Git operations | `git-flow-manager` | Claude Code Haiku; Gemini/Codex excluded |
| Evidence-bound reconstruction | `security-auditor`, `documentation-keeper` | Claude Code Opus/Sonnet; Gemini excluded |
| Destructive documentation writes | `documentation-keeper` exclusive | Claude Code Sonnet; no other agent |
| Translation (per ADR-024 am.12) | Gemini CLI exclusive | Gemini only |
| ADR drafting | `documentation-keeper` | Claude Code Sonnet |
| DB/schema migrations | `database-engineer` | Claude Code Opus |
| Production deployment | `deployment-specialist` | Claude Code Sonnet |

## Consequences

### Positive

- Model tier discipline applied: explicit `model:` field added to 15 working agents; spot-fix of broken YAML on 2 pilot agents (`e2e-test-scenario-designer`, `public-frontend-developer`) enabled pilot test execution
- Scoped MCP write blast radius: only 4 pilot agents have Notion/Obsidian write access; remaining 28 agents have read-only or no MCP access
- Agent-task coverage gaps closed: `backend-developer`, `deployment-specialist`, and `documentation-keeper` fill three previously uncovered task domains
- Model tier discipline enforced: all 32 agents have explicit model IDs (no more unspecified `sonnet` aliases)
- `documentation-keeper` single-owner pattern reduces risk of destructive op concurrency

### Negative

- Approximately 15 agents with broken YAML frontmatter remain blocked from registry load; deferred post-v1.5.0
- Format drift between Style A (YAML frontmatter, ADRs 027–032) and earlier ADR styles (markdown-only headers) remains unaddressed; tech debt deferred
- `documentation-keeper` creates a bottleneck for any team member needing fast Obsidian/Notion writes — all destructive ops must route through it

### Neutral

- Agent count grew from 30 (pre-Sprint 60) to 32 (post-Sprint 60)
- Orchestration pattern unchanged: `workflow-orchestrator` remains the entrypoint per ADR-031 D8; ADR-032 does not alter orchestration discipline
- D1 matrix in ADR-031 remains valid for engine-level decisions; ADR-032 D6 adds the agent-level layer on top

## Alternatives considered

### Alt 1: Full bulk YAML fix before v1.5.0

Fixing all ~15 broken YAML frontmatters before the v1.5.0 release tag.

- Pros: clean registry state at tag time
- Cons: v1.5.0 is blocked by functional deliverables, not YAML cosmetics; diverting sprint capacity to YAML churn delays actual features
- **Rejected**: deferred post-v1.5.0. The broken agents are not in the critical path for v1.5.0 functionality.

### Alt 2: Extend MCP read-only access to all agents

Grant all 32 agents read access to Notion and Obsidian by default.

- Pros: any agent can self-contextualize; reduces need for orchestrator to pass context manually
- Cons: most agents do not benefit from Notion/Obsidian context in their primary task flows (e.g., `git-flow-manager`, `import-validator`); adds noise and latency to tool calls
- **Rejected**: access granted on demonstrated need only.

### Alt 3: Grant MCP write access to all 4 pilot agents

Promote all 4 pilot agents (workflow-orchestrator, documentation-keeper, e2e-test-scenario-designer, email-press-redactor) to write scope after 3/3 pilot pass.

- Pros: covers the actual use cases identified (sprint tracking, ADR writes, task creation, pipeline logs)
- Cons: increases blast radius vs a single-agent write owner
- **Adopted** (with STOP gates in documentation-keeper and write-scope constraints per agent — see D4).

### Alt 4: ADR-032 only in vault, consistent with ADR-031 oversight

Keep ADR-032 vault-only to maintain consistency with the pre-existing ADR-031 oversight (ADR-031 existed in vault but not repo).

- Pros: no change to current pattern
- Cons: perpetuates the oversight; the gap was identified as a mistake, not a pattern; ADR-031 was supposed to be mirrored per the 027–030 convention
- **Rejected**: ADR-032 mirrors to repo, and ADR-031 is simultaneously mirrored as part of this same three-file operation.

## Related ADRs

- **ADR-031** (`multi-agent-orchestration-discipline`) — D1 extended by ADR-032 D6 (agent-level task matrix added on top of the engine-level matrix in ADR-031 D1)
- **ADR-027** (`article-provider-locale-gate-scope`) — current canonical ADR-027 after vault renumbering; mentioned here for conflict resolution traceability
- **ADR-024** (`agent-redistribution-unified-dispatcher`) — prior agent topology decision from Sprint 57; ADR-032 extends its roster model

## References

- Sprint 60 execution log: `50_Audit/sprint-60-execution-log.md` (created at sprint close)
- Pilot test artifact: `.claude/commands/pw-test-locale-switcher-disabled.md` (Test 2 — Obsidian scoped write validation)
- Agent roster: `.claude/agents/README.md` (canonical post-modernization roster with model tiers)
- MCP configuration: `.mcp.json` (declared servers: notion, obsidian, playwright)
- Permission scope: `.claude/settings.json` (`enabledMcpjsonServers`: playwright, obsidian, notion)
- Filesystem changes: `.claude/agents/*.md` (model tier updates), `.claude/agents/_archive/premium-ui-designer.md` (archived), `.claude/agents/backend-developer.md`, `.claude/agents/deployment-specialist.md`, `.claude/agents/documentation-keeper.md` (new)
