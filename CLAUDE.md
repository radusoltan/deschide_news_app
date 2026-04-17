# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

---

# HARD RULES — NEVER VIOLATE

These rules apply to ALL agents, ALL tasks, ALL sprints. Violations waste time and break workflows.

---

## Rule 1: CLI Entry Point

**ALWAYS** use `symfony console` — **NEVER** use `php bin/console`.

```bash
# CORRECT
symfony console app:translate:articles 123 --force
symfony console doctrine:schema:validate
symfony console cache:clear --env=test
symfony console messenger:consume ai_async --limit=10

# WRONG — will be rejected
php bin/console app:translate:articles 123 --force
php bin/console cache:clear
```

The Symfony CLI binary manages environment variables, PHP version, and worker lifecycle.
There are ZERO exceptions to this rule.

---

## Rule 2: Admin Credentials — Do Not Query the Database

Development admin credentials are defined in environment config. **Do not** run SQL queries, create fixture users, or invent credentials.

```bash
# Read credentials from environment
symfony console debug:dotenv | grep -E 'ADMIN_EMAIL|ADMIN_PASSWORD'

# Authenticate via API
curl -s -X POST http://127.0.0.1:8081/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"email":"${ADMIN_EMAIL}","password":"${ADMIN_PASSWORD}"}' | jq .token

# Use the token for subsequent requests
curl -s http://127.0.0.1:8081/api/articles \
  -H "Authorization: Bearer {token}"
```

If credentials are not in `.env.local`, check `.env.test` or ask Radu. Do NOT:
- Query `app_users` table directly for passwords
- Create new admin users via Doctrine fixtures
- Hardcode credentials in test files or prompts

---

## Rule 3: Testing Strategy — Targeted Tests, Not Full Suite

### During feature development (individual tasks):
Run ONLY the tests relevant to your changes:

```bash
# Find tests related to the class you modified
grep -rn "YourModifiedClass" tests/ --include="*.php" -l

# Run only those tests
./vendor/bin/phpunit tests/Unit/Service/YourServiceTest.php --testdox
./vendor/bin/phpunit tests/Functional/Api/YourControllerTest.php --testdox

# If you modified an entity, also run its repository test
./vendor/bin/phpunit --filter="YourEntity" --testdox
```

For frontend:
```bash
cd apps/frontend
npx jest --testPathPattern="YourComponent" --verbose
```

### During sprint finalization ONLY (QA task / merge prep):
Run the FULL test suite to check for regressions:

```bash
# Backend — full suite
cd apps/backend
./vendor/bin/phpunit --testdox

# Frontend — full suite
cd apps/frontend
npm test -- --watchAll=false
```

**Why:** The full backend suite (~4,300+ tests) takes significant time. Running it after every small change wastes cycles. Targeted tests catch issues immediately; full suite catches regressions at sprint boundary.

---

## Rule 4: Code Quality — Mandatory Checks Before Every Commit

Every commit MUST pass these checks. Run them BEFORE `git commit`:

### Backend (PHP):
```bash
cd apps/backend

# 1. Container lint — catches DI wiring issues
symfony console lint:container

# 2. PHPStan — static analysis at current level
vendor/bin/phpstan analyse src/ --no-progress --memory-limit=1G

# 3. Schema validation — catches entity/DB drift
symfony console doctrine:schema:validate
```

### Frontend (TypeScript/React):
```bash
cd apps/frontend

# 1. ESLint — zero errors allowed
npx eslint . --max-warnings=0

# 2. TypeScript — no type errors
npx tsc --noEmit
```

### What to do when checks fail:

| Check | Failure means | Action |
|-------|--------------|--------|
| `lint:container` | Broken service wiring | Fix DI config before anything else |
| `phpstan` | Type/logic error | Fix the error. Do NOT add to baseline without documenting why |
| `schema:validate` | Entity doesn't match DB | Create a migration: `symfony console make:migration` |
| `eslint` | Code style/logic issue | Fix it. Do NOT disable the rule |
| `tsc --noEmit` | Type error in TS | Fix the type. Do NOT use `// @ts-ignore` |

### PHPStan baseline policy:
- Adding to baseline is acceptable ONLY for pre-existing issues outside your task scope
- New code you write must pass cleanly
- If ratcheting to a higher level, document in ADR and coordinate with sprint plan

---

## Rule 5: Project Conventions (Quick Reference)

| Rule | Correct | Wrong |
|------|---------|-------|
| Romanian diacritics | ș (U+0219), ț (U+021B) | ş (cedilla), ţ (cedilla) |
| Commit format | `feat(scope): description [TSK-XX]` | `update stuff` |
| Git flow | `feature/sprint-XX` → `develop` (--no-ff) → `main` | Direct push to main |
| Elasticsearch | Native `elasticsearch-php` via HTTPS | FOSElasticaBundle |
| Admin UI | API Platform + Next.js | EasyAdmin |
| Social/API clients | Symfony HttpClient | Third-party bundles |
| Gemini CLI | One call per locale (avoids truncation) | All locales in one call |
| Redis policy | `volatile-lru` | `allkeys-lru` (breaks tag invalidation) |
| Next.js routing | `proxy.ts` (not `middleware.ts`) | middleware.ts |
| Frontend dev server | `pnpm dev` | PM2 / `npm start` (PM2 is for production only) |

---

## Rule 6: Context First — Read Before You Act

Before starting ANY task, you MUST gather context from Obsidian and Notion. These tools exist specifically so agents have full project awareness. Skipping this step leads to duplicate work, contradictory implementations, and broken assumptions.

### Before every task:

**Step 1 — Read the sprint and task from Notion:**
```
# Find the current sprint and your assigned task
# Notion Sprints DB: f8922999-91ba-4384-8496-25a3606520b9
# Notion Tasks DB: 2f696048-60ac-4af9-9db9-83600149977f

- Read the sprint description to understand the overall goal
- Read the specific task description, acceptance criteria, and any agent handoff notes
- Check task dependencies (are there blocking/blocked tasks?)
- Check what other tasks in the sprint are Done vs In Progress
```

**Step 2 — Read relevant Obsidian context:**

| Starting work on... | Read first |
|---------------------|------------|
| Any task | `CLAUDE.md` (this file) + today's daily note `40_Agent_Workspace/Daily/YYYY-MM-DD.md` |
| Backend feature | `20_Architecture/Data_Model.md` + `20_Architecture/API_Endpoints.md` |
| Frontend feature | `context/DESIGN_QUICK_REFERENCE.md` (in repo) |
| AI/LLM work | `20_Architecture/Decisions/ADR-008*` through `ADR-011*` |
| Aggregator/source work | `30_Engineering_Context/Sources_and_Aggregators.md` |
| Any architectural change | `20_Architecture/Decisions/` (scan for related ADRs) |
| Clustering/editorial | `20_Architecture/Content_Pipeline.md` |
| Caching/performance | `20_Architecture/Caching_Strategy.md` |
| Image/CDN work | `20_Architecture/CDN_and_Image_Serving.md` |
| New sprint | Previous sprint's execution log: `50_Audit/sprint-NN-execution-log.md` |

**Step 3 — Check for recent decisions and open loops:**
```
# Search Obsidian for recent context
- Check 40_Agent_Workspace/Decision_Log/ for any relevant recent decisions
- Check the last 2-3 daily notes for open loops or known issues
```

### Why this matters:
- Infrastructure often already exists — reading context prevents re-implementing what's already built
- ADRs document WHY decisions were made — ignoring them leads to contradictory implementations
- Sprint context shows what's already done and what's pending — avoids conflicts between parallel tasks
- Daily notes capture workarounds and known issues that aren't in the code

### Do NOT:
- Start coding without reading the task description from Notion
- Assume you know the current state — verify in Obsidian
- Skip ADR review when the task touches architecture
- Ignore daily notes — they contain critical context from recent sessions

---

## Rule 7: Post-Implementation Documentation — Mandatory After Every Task

After completing any task (feature, bugfix, refactor), you MUST update external tracking before reporting "done".
This is NOT optional. Undocumented work is invisible work.

### Step 1: Update Notion task status
```
# Use Notion MCP to update the task:
- Set Status -> "Done"
- Set Actual (hrs) -> actual hours spent
- Add a brief completion note in the task content
```

### Step 2: Update Notion sprint (if last task in sprint)
```
# When all sprint tasks are Done:
- Set Sprint Status -> "Review" (not "Done" — Radu reviews first)
- Update sprint end date if different from planned
```

### Step 3: Update Obsidian vault
After implementation, update relevant Obsidian notes:

| What changed | Update where |
|-------------|-------------|
| New entity / API resource | `20_Architecture/Data_Model.md` and `20_Architecture/API_Endpoints.md` |
| Architectural decision | Create new ADR in `20_Architecture/Decisions/ADR-NNN-title.md` |
| New aggregator / source | `30_Engineering_Context/Sources_and_Aggregators.md` |
| Stack version change | `30_Engineering_Context/Stack_Reference.md` |
| New convention / pattern | `30_Engineering_Context/Coding_Conventions.md` |
| Test count changed significantly | `CLAUDE.md` section 5 (Current State) |
| Sprint completed | `50_Audit/sprint-NN-execution-log.md` |
| Daily work | `40_Agent_Workspace/Daily/YYYY-MM-DD.md` |

### Step 4: Update daily note
Append to today's daily note (`40_Agent_Workspace/Daily/YYYY-MM-DD.md`):
```markdown
### Task [TSK-XX] — [Title]
- **Status**: Done
- **Commits**: `feat(scope): description`
- **Files changed**: list key files
- **Tests**: N new, N total green
- **Notes**: anything notable (decisions, workarounds, open issues)
```

### What NOT to update:
- `10_Notion_Mirror/` — these are synced FROM Notion, never edit manually
- Documents outside your task scope (don't "improve" unrelated docs)

---

# END OF HARD RULES

---

## Project Overview

**Deschide News App** — A trilingual (RO/EN/RU) AI-native news platform.

- **Backend**: Symfony 8.0 (PHP 8.5) — Headless API (API Platform) — port 8081
- **Frontend**: Next.js 16 (React 19 / TypeScript) — port 3005
- **CDN**: Static assets (images, thumbnails) — port 8082
- **Architecture**: Monorepo (`apps/backend/` + `apps/frontend/`)

**Working Directories:**
- Backend: `/var/www/deschide_news_app/apps/backend`
- Frontend: `/var/www/deschide_news_app/apps/frontend`

## Obsidian Knowledge Base

All detailed reference documentation lives in the Obsidian vault (`/mnt/c/Users/Radu/DeschideVault/`). Read these before starting work (see Rule 6).

| Topic | Obsidian Document |
|-------|-------------------|
| System architecture, ports, directory structure | `20_Architecture/Architecture_Overview.md` |
| API endpoints (30 resources + 36 controllers) | `20_Architecture/API_Endpoints.md` |
| Entity relationships, ER diagram | `20_Architecture/Data_Model.md` |
| Content pipeline (aggregation to publication) | `20_Architecture/Content_Pipeline.md` |
| Caching (L1/L2/L3), ODR, TTL strategy | `20_Architecture/Caching_Strategy.md` |
| CDN, image serving, thumbnail profiles | `20_Architecture/CDN_and_Image_Serving.md` |
| Stack versions, essential commands | `30_Engineering_Context/Stack_Reference.md` |
| Coding conventions, git workflow, commits | `30_Engineering_Context/Coding_Conventions.md` |
| Aggregator sources (82 active) | `30_Engineering_Context/Sources_and_Aggregators.md` |
| Architectural decisions (ADR-001 to ADR-014) | `20_Architecture/Decisions/` |

## Design System

All frontend public-facing development MUST follow the design system in `context/`.

| Document | Location |
|----------|----------|
| Full Design Guide | `context/design_principles_and_features.md` |
| Quick Reference | `context/DESIGN_QUICK_REFERENCE.md` |

When working on **public frontend components**, agents MUST use the `frontend-design` skill.
Do NOT use for admin panel, backend, config, or test files.

## Test Status

- Backend PHPUnit: **~4,350+ tests** (all passing, Sprint 47)
- Frontend Jest: **~804+ tests** (all passing, Sprint 47)
- **Total: ~5,150+ tests**
- See Rule 3 for targeted vs full suite policy
- See Rule 4 for mandatory pre-commit checks
