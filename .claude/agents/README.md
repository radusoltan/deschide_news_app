# Agents for Deschide News App

This directory contains specialized agents for developing, testing, data migration, and maintaining the Deschide News multilingual news portal.

> **Design Philosophy**: All agents follow Anthropic's core principles for building effective agents:
> 1. **Simplicity** - Single, focused responsibility per agent
> 2. **Transparency** - Explicit planning steps and visible decision-making
> 3. **Well-documented ACI** - Thorough tool documentation with clear usage patterns

---

## Quick Reference

### All Available Agents

| Agent | Purpose | Type | Invocation |
|-------|---------|------|------------|
| **public-frontend-developer** | Design & develop public frontend UI | Development | `@public-frontend-developer create Hero section` |
| **backend-api-tester** | Tests Symfony API endpoints | Automated Testing | `@backend-api-tester test all endpoints` |
| **frontend-e2e-tester** | Tests Next.js frontend E2E | Automated Testing | `@frontend-e2e-tester test all user flows` |
| **fullstack-integration-tester** | Tests complete workflows | Automated Testing | `@fullstack-integration-tester test article lifecycle` |
| **multilanguage-tester** | Tests i18n/l10n functionality | Automated Testing | `@multilanguage-tester test all locales` |
| **admin-panel-tester** | Tests admin interface | Automated Testing | `@admin-panel-tester test admin functionality` |
| **performance-tester** | Tests performance metrics | Automated Testing | `@performance-tester test performance` |
| **manual-frontend-tester** | Exploratory manual testing | Interactive Testing | `@manual-frontend-tester explore homepage` |
| **e2e-test-scenario-designer** | Design & document E2E test scenarios | Planning | `@e2e-test-scenario-designer create scenarios for [feature]` |
| **seo-specialist** | SEO optimization for multilingual news | Hybrid | `@seo-specialist audit SEO` |
| **security-auditor** | Security vulnerability assessment | Security | `@security-auditor run security audit` |
| **database-engineer** | Database architecture & optimization | Infrastructure | `@database-engineer audit databases` |
| **git-flow-manager** | Git workflow management | Workflow | `@git-flow-manager start feature` |
| **docusaurus-expert** | Documentation site specialist | Development | `@docusaurus-expert create docs` |
| **data-import-orchestrator** | Orchestrate data import pipeline | Data Migration | `@data-import-orchestrator plan full migration` |
| **newscoop-importer** | Import from Newscoop MySQL | Data Migration | `@newscoop-importer import articles --locale=ro` |
| **csv-articles-importer** | Import from CSV files | Data Migration | `@csv-articles-importer import buchis.csv` |
| **import-validator** | Validate imported data | Data Migration | `@import-validator check article integrity` |
| **import-mapper** | Manage ID mappings & deduplication | Data Migration | `@import-mapper resolve duplicates` |

---

## Agent Categories

### 🎨 Development Agents

Agents that create and enhance features:

#### **`public-frontend-developer`** 
- **Purpose**: Design and develop the public-facing frontend interface
- **Skill Integration**: Uses `frontend-design@claude-code-plugins`
- **Specialties**:
  - Distinctive, production-grade UI design
  - News portal specific components (Hero, Article Cards, Breaking News)
  - Multilingual support (ro, en, ru with Cyrillic)
  - Responsive design (mobile-first)
  - Accessibility (WCAG 2.1 AA)
  - Performance optimization
- **Invocation Examples**:
  ```
  @public-frontend-developer create a Hero section with editorial design
  @public-frontend-developer enhance ArticleCard with hover effects
  @public-frontend-developer implement mobile navigation
  @public-frontend-developer establish typography system
  ```

#### **`docusaurus-expert`**
- **Purpose**: Create and maintain documentation sites
- **Invocation**: `@docusaurus-expert create docs`

### 📦 Data Migration Agents ⭐ NEW

Agents for importing data from legacy systems:

#### **`data-import-orchestrator`**
- **Purpose**: Coordinate the complete data migration pipeline
- **Specialties**:
  - Plan import sequence (categories → authors → images → articles)
  - Monitor import progress across sources
  - Handle cross-source deduplication
  - Generate migration reports
- **Invocation Examples**:
  ```
  @data-import-orchestrator plan full migration from Newscoop
  @data-import-orchestrator check import progress
  @data-import-orchestrator generate import report
  ```

#### **`newscoop-importer`**
- **Purpose**: Import data from Newscoop MySQL databases
- **Data Sources**: 
  - Newscoop v1 (legacy)
  - Newscoop v2 (current)
- **Entity Support**: Articles, Categories, Authors, Images, Translations
- **Invocation Examples**:
  ```
  @newscoop-importer test connection
  @newscoop-importer import categories --locale=ro
  @newscoop-importer import articles --limit=1000
  @newscoop-importer import translations for articles 1-100
  ```

#### **`csv-articles-importer`**
- **Purpose**: Import articles from Playwright-scraped CSV files
- **Data Sources**:
  - `buchis.csv` (~5000 articles)
  - `buchis_2.csv` (~3000 articles)
- **Invocation Examples**:
  ```
  @csv-articles-importer analyze CSV structure
  @csv-articles-importer import --file=buchis.csv --limit=500
  @csv-articles-importer continue import --offset=500
  ```

#### **`import-validator`**
- **Purpose**: Validate imported data integrity
- **Validation Types**:
  - Referential integrity (FK relationships)
  - Data completeness (required fields)
  - Deduplication checks
  - Translation coverage
- **Invocation Examples**:
  ```
  @import-validator run full validation
  @import-validator check article integrity
  @import-validator verify translations completeness
  ```

#### **`import-mapper`**
- **Purpose**: Manage ID mappings between systems
- **Features**:
  - Map Newscoop IDs to Symfony IDs
  - Handle external article mappings
  - Resolve duplicate entries
  - Merge duplicate articles
- **Invocation Examples**:
  ```
  @import-mapper check mapping status
  @import-mapper resolve duplicate external IDs
  @import-mapper merge duplicate articles
  ```

### 🤖 Automated Testing Agents

Run predefined test suites with minimal human intervention:

- **`backend-api-tester`** - Systematic API endpoint validation
- **`frontend-e2e-tester`** - Scripted user flow testing
- **`fullstack-integration-tester`** - Complete workflow verification
- **`multilanguage-tester`** - Translation coverage testing
- **`admin-panel-tester`** - Admin CRUD operations
- **`performance-tester`** - Metrics and benchmarking

### 🔍 Interactive Testing Agent

Human-guided exploratory testing:

- **`manual-frontend-tester`** - Exploratory testing like a human QA engineer
  - Adapts testing based on discoveries
  - Documents findings in real-time
  - Investigates edge cases dynamically
  - Ideal for pre-release validation and bug hunting

### 📋 Test Planning Agent

Strategic test coverage planning:

- **`e2e-test-scenario-designer`** - Designs comprehensive E2E test scenarios
  - Analyzes features and maps user journeys
  - Prioritizes scenarios by criticality and risk
  - Documents executable test scenarios with Playwright MCP tools
  - Maintains test coverage matrix
  - Outputs to `.claude/commands/pw-test-*.md` format

### 🗄️ Infrastructure Agent ⭐ NEW

Database architecture, optimization, and security:

- **`database-engineer`** - Multi-database management and optimization
  - PostgreSQL query optimization and indexing
  - Redis cache strategy and memory management
  - Elasticsearch tuning and mapping optimization
  - Database security hardening
  - Backup and recovery procedures
  - Performance monitoring and reporting
  - Gedmo Translatable optimization
  - N+1 query prevention

**Invocation Examples:**
```
@database-engineer run quick health check
@database-engineer analyze slow queries
@database-engineer optimize Redis cache for homepage
@database-engineer review Elasticsearch mapping
@database-engineer audit database permissions
@database-engineer create backup strategy
```

### 🔐 Security Agent

Comprehensive security vulnerability assessment:

- **`security-auditor`** - Security vulnerability testing and protection
  - XSS testing (reflected, stored, DOM-based)
  - SQL injection and query injection detection
  - Authentication & authorization bypass testing
  - Security headers validation
  - CORS & CSRF protection verification
  - Rate limiting & DDoS protection testing
  - Path traversal & file upload security
  - Information disclosure prevention
  - Multilanguage-specific security (Cyrillic payloads)

**Invocation Examples:**
```
@security-auditor run quick security scan
@security-auditor test XSS on all public inputs
@security-auditor verify authentication security
@security-auditor check security headers
@security-auditor perform full OWASP Top 10 audit
```

### 🎯 Specialist Agents

Domain-specific expertise:

- **`seo-specialist`** - Technical SEO, structured data, hreflang
- **`git-flow-manager`** - Git-Flow branching strategy management

---

## Quick Start

### 1. Start Services

```bash
# Backend
cd /var/www/deschide_news_app/apps/backend
symfony serve -d --port=8081

# Frontend
cd /var/www/deschide_news_app/apps/frontend
pnpm dev
```

### 2. Seed Test Data

```bash
cd /var/www/deschide_news_app/apps/backend
symfony console app:sample-import
```

### 3. Use Agents

**Development:**
```
@public-frontend-developer create a distinctive Hero section
@public-frontend-developer implement Breaking News ticker
```

**Data Migration:**
```
@data-import-orchestrator plan full migration
@newscoop-importer import articles --locale=ro --limit=1000
@csv-articles-importer import buchis.csv
@import-validator run full validation
```

**Automated Testing:**
```
@backend-api-tester test all endpoints
@frontend-e2e-tester test homepage
```

**Manual Exploratory Testing:**
```
@manual-frontend-tester explore homepage and report findings
@manual-frontend-tester test mobile viewport on all main pages
```

---

## When to Use Which Agent

### Feature Development

| Task | Recommended Agent |
|------|-------------------|
| New public UI component | `@public-frontend-developer create [component]` |
| Enhance existing component | `@public-frontend-developer enhance [component]` |
| Design system work | `@public-frontend-developer establish [system]` |
| Responsive design | `@public-frontend-developer implement mobile [feature]` |
| Accessibility improvements | `@public-frontend-developer audit accessibility` |

### Data Migration Phase ⭐ NEW

| Scenario | Recommended Agent |
|----------|-------------------|
| Plan migration strategy | `@data-import-orchestrator plan full migration` |
| Import from Newscoop | `@newscoop-importer import [entity]` |
| Import from CSV files | `@csv-articles-importer import [file]` |
| Validate imported data | `@import-validator run full validation` |
| Handle duplicates | `@import-mapper resolve duplicates` |
| Check import progress | `@data-import-orchestrator check import progress` |

### Data Migration Pipeline

```
[MIGRATION WORKFLOW]

1. @data-import-orchestrator plan full migration
        │
        ├── 2. @newscoop-importer import categories
        │
        ├── 3. @newscoop-importer import authors
        │
        ├── 4. @newscoop-importer import images
        │
        ├── 5. @newscoop-importer import articles
        │
        ├── 6. @csv-articles-importer import buchis.csv
        │
        └── 7. @import-validator run full validation
                    │
                    ├── [PASS] → @import-mapper finalize mappings
                    │
                    └── [FAIL] → Fix issues, re-validate
```

### Testing Phase

| Scenario | Recommended Agent |
|----------|-------------------|
| After backend code changes | `@backend-api-tester test affected endpoints` |
| After frontend component changes | `@frontend-e2e-tester test affected pages` |
| New feature implementation | `@fullstack-integration-tester test complete feature flow` |
| Adding/updating translations | `@multilanguage-tester test all locales` |
| Admin panel modifications | `@admin-panel-tester test admin section` |

### Pre-Release Phase

| Scenario | Recommended Agent |
|----------|-------------------|
| Design test scenarios | `@e2e-test-scenario-designer create scenarios for [feature]` |
| Generate regression suite | `@e2e-test-scenario-designer generate pre-release checklist` |
| Quick smoke test (5 min) | `@manual-frontend-tester run smoke test` |
| Exploratory testing (15-30 min) | `@manual-frontend-tester explore [area]` |
| Bug investigation | `@manual-frontend-tester investigate: "[issue]"` |
| Performance validation | `@performance-tester test full performance` |
| SEO verification | `@seo-specialist audit technical SEO` |

### Database & Infrastructure Phase ⭐ NEW

| Scenario | Recommended Agent |
|----------|-------------------|
| Quick database health check | `@database-engineer run quick health check` |
| Full performance audit | `@database-engineer perform full database audit` |
| Query optimization | `@database-engineer analyze slow queries` |
| Cache strategy review | `@database-engineer optimize Redis cache` |
| Search optimization | `@database-engineer review Elasticsearch mapping` |
| Pre-migration review | `@database-engineer review migration for [feature]` |
| Backup planning | `@database-engineer create backup strategy` |
| Security hardening | `@database-engineer audit database permissions` |

### Security Testing Phase

| Scenario | Recommended Agent |
|----------|-------------------|
| Quick security check (5 min) | `@security-auditor run quick security scan` |
| Full security audit (1 hour) | `@security-auditor perform full OWASP Top 10 audit` |
| XSS vulnerability testing | `@security-auditor test XSS on all public inputs` |
| API security testing | `@security-auditor test authentication and authorization` |
| Security headers check | `@security-auditor check security headers` |
| Post-fix verification | `@security-auditor verify fix for [vulnerability]` |
| Pre-deployment security | `@security-auditor run pre-deployment security checklist` |

### Test Planning Phase

| Scenario | Recommended Agent |
|----------|-------------------|
| New feature test planning | `@e2e-test-scenario-designer analyze [feature]` |
| Sprint test coverage | `@e2e-test-scenario-designer prioritize for Sprint [X]` |
| Coverage gap analysis | `@e2e-test-scenario-designer review coverage matrix` |
| Bug reproduction scenario | `@e2e-test-scenario-designer create reproduction for [bug]` |

---

## Agent Files

### Development Agents
| File | Description |
|------|-------------|
| `public-frontend-developer.md` | Public frontend UI development |
| `docusaurus-expert.md` | Documentation site specialist |

### Data Migration Agents ⭐ NEW
| File | Description |
|------|-------------|
| `data-import-orchestrator.md` | Migration pipeline orchestration |
| `newscoop-importer.md` | Newscoop MySQL import specialist |
| `csv-articles-importer.md` | CSV file import specialist |
| `import-validator.md` | Data integrity validation |
| `import-mapper.md` | ID mapping & deduplication |

### Testing Agents
| File | Description |
|------|-------------|
| `e2e-test-scenario-designer.md` | E2E test scenario planning and design |
| `backend-api-tester.md` | API endpoint testing specification |
| `frontend-e2e-tester.md` | Frontend E2E testing specification |
| `fullstack-integration-tester.md` | Integration testing specification |
| `multilanguage-tester.md` | i18n/l10n testing specification |
| `admin-panel-tester.md` | Admin panel testing specification |
| `performance-tester.md` | Performance testing specification |
| `manual-frontend-tester.md` | Exploratory manual testing |

### Infrastructure Agents ⭐ NEW
| File | Description |
|------|-------------|
| `database-engineer.md` | Multi-database architecture, optimization & security |

### Security Agents
| File | Description |
|------|-------------|
| `security-auditor.md` | Comprehensive security vulnerability testing |

### Specialist Agents
| File | Description |
|------|-------------|
| `seo-specialist.md` | SEO optimization specification |
| `git-flow-manager.md` | Git workflow management |

---

## Pre-Release Checklist

Before deploying to production:

### Database Health Verified ⭐ NEW
- [ ] PostgreSQL query performance optimized (<100ms p95)
- [ ] All necessary indexes created and verified
- [ ] No N+1 query patterns detected
- [ ] Redis cache hit ratio >95%
- [ ] Elasticsearch indices healthy and optimized
- [ ] Database backups configured and tested
- [ ] Connection pooling properly configured
- [ ] Gedmo translation queries optimized
- [ ] Database user permissions audited
- [ ] Slow query logging enabled for monitoring

### Security Audit Complete
- [ ] XSS vulnerabilities tested (reflected, stored, DOM-based)
- [ ] SQL injection tested on all API endpoints
- [ ] Authentication security verified (JWT)
- [ ] Authorization bypass attempts tested
- [ ] Security headers properly configured
- [ ] CORS properly restricted
- [ ] Rate limiting enabled on sensitive endpoints
- [ ] Input validation complete for all locales
- [ ] File upload security verified
- [ ] No sensitive data exposure in API responses
- [ ] Error messages don't leak system information

### Data Migration Complete
- [ ] All sources imported (Newscoop, CSV)
- [ ] Data validated with `@import-validator`
- [ ] No duplicate entries
- [ ] All mappings finalized
- [ ] Translations imported

### Development Complete
- [ ] UI components created with `@public-frontend-developer`
- [ ] Design system established (typography, colors)
- [ ] Responsive design implemented
- [ ] Accessibility requirements met
- [ ] All three locales supported (ro, en, ru)

### Test Planning
- [ ] Test scenarios designed with `@e2e-test-scenario-designer`
- [ ] Test coverage matrix reviewed
- [ ] Critical user journeys documented
- [ ] Regression suite generated
- [ ] Edge cases identified

### Automated Checks
- [ ] Backend API tests pass
- [ ] Frontend E2E tests pass
- [ ] Integration tests pass
- [ ] All locales work (ro, en, ru)
- [ ] Admin panel functional
- [ ] Performance benchmarks met
- [ ] SEO audit passed

### Manual Verification
- [ ] Exploratory testing completed (using documented scenarios)
- [ ] No console errors
- [ ] Images load from CDN
- [ ] Mobile responsiveness verified
- [ ] Accessibility basics checked

---

## Anthropic Agent Best Practices Applied

All agents in this directory follow these principles from Anthropic's ["Building Effective Agents"](https://www.anthropic.com/engineering/building-effective-agents) and ["Effective Context Engineering"](https://www.anthropic.com/engineering/effective-context-engineering-for-ai-agents):

### 1. Simplicity in Design
- Each agent has a single, focused responsibility
- No complex frameworks - simple, composable patterns
- Start simple, add complexity only when needed

### 2. Transparency
- Explicit planning steps before execution
- Visible decision-making process
- Clear reporting of findings and progress

### 3. Well-documented ACI (Agent-Computer Interface)
- Thorough documentation of available tools
- Clear examples and usage patterns
- Defined guardrails and error recovery

### 4. Context Engineering
- Minimal viable context for each task
- Structured note-taking for long tasks
- Clear handoffs between agents

### 5. Tool Design
- Tools with clear, non-overlapping purposes
- Self-contained, robust to error
- Descriptive parameters and documentation

---

## Documentation

- 📖 **Complete Testing Guide**: `docs/TESTING_AGENTS_GUIDE.md`
- 🏗️ **Project Structure**: `CLAUDE.md`
- 🔧 **Agent Specifications**: `.claude/agents/*.md`
- 🎨 **Frontend Design Skill**: `/mnt/skills/public/frontend-design/SKILL.md`

---

## References

### Anthropic Best Practices
- [Building Effective Agents](https://www.anthropic.com/engineering/building-effective-agents)
- [Effective Context Engineering](https://www.anthropic.com/engineering/effective-context-engineering-for-ai-agents)
- [Writing Tools for Agents](https://www.anthropic.com/engineering/writing-tools-for-agents)
- [Building Agents with Claude Agent SDK](https://www.anthropic.com/engineering/building-agents-with-the-claude-agent-sdk)

### Skills
- Frontend Design: `/mnt/skills/public/frontend-design/SKILL.md`

---

**Happy Building!** 🚀
