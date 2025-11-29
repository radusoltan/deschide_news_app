# Docusaurus Documentation Site Implementation Plan

**Project**: Deschide News App
**Date**: 2025-11-14
**Status**: Planning Phase

---

## Executive Summary

This document outlines a comprehensive plan for implementing a Docusaurus-based documentation site for the Deschide News App monorepo. The plan includes installation, migration strategy, structure organization, and deployment considerations.

### Current State

- **No Docusaurus installation exists**
- **~53 documentation files** spread across multiple locations:
  - Root level: 12 markdown files
  - Centralized docs: 21 markdown files
  - Backend docs: 9 markdown files
  - Frontend docs: 11 markdown files

### Proposed State

- **Centralized Docusaurus site** at `/var/www/deschide_news_app/docs-site/`
- **Organized documentation** in 9 main categories
- **Version-controlled** documentation aligned with releases
- **Searchable** and **user-friendly** interface
- **Deployed** to GitHub Pages/Vercel/self-hosted server

---

## Table of Contents

1. [Installation Plan](#installation-plan)
2. [Proposed Documentation Structure](#proposed-documentation-structure)
3. [Migration Strategy](#migration-strategy)
4. [Configuration Details](#configuration-details)
5. [Content Organization](#content-organization)
6. [Documentation Gaps Analysis](#documentation-gaps-analysis)
7. [Versioning Strategy](#versioning-strategy)
8. [Deployment Options](#deployment-options)
9. [Port Allocation](#port-allocation)
10. [Timeline and Effort Estimate](#timeline-and-effort-estimate)
11. [Next Steps](#next-steps)

---

## Installation Plan

### Step 1: Create Docusaurus Site

**Location**: `/var/www/deschide_news_app/docs-site/`

**Installation Command**:
```bash
cd /var/www/deschide_news_app
npx create-docusaurus@latest docs-site classic --typescript
cd docs-site
pnpm install
```

**Technology Stack**:
- **Docusaurus**: v3.x (latest)
- **React**: v18.x
- **TypeScript**: For type safety
- **Preset**: Classic (includes docs, blog, pages)

### Step 2: Clean Default Content

**Files to Remove**:
```bash
# Navigate to docs-site directory
cd /var/www/deschide_news_app/docs-site

# Remove tutorial files
rm -rf docs/tutorial-basics/
rm -rf docs/tutorial-extras/

# Remove default docs
rm docs/intro.md

# Remove default blog posts
rm -rf blog/2019-*
rm -rf blog/2021-*
rm -rf blog/*.md

# Remove default images (except placeholders you want to keep)
rm static/img/docusaurus.png
rm static/img/tutorial/
rm -rf static/img/undraw_*
```

**Files to Keep**:
- `docusaurus.config.ts` - Main configuration
- `sidebars.ts` - Sidebar configuration
- `package.json` - Dependencies
- `src/` - Custom components and pages
- `static/img/favicon.ico` - Replace with custom favicon
- `static/img/logo.svg` - Replace with custom logo

---

## Proposed Documentation Structure

### Directory Tree

```
docs-site/
├── docs/
│   ├── index.md                          # Documentation Home
│   │
│   ├── 01-getting-started/
│   │   ├── index.md                      # Overview
│   │   ├── quick-start.md                # From README.md Quick Start section
│   │   ├── installation.md               # From SETUP.md
│   │   ├── architecture-overview.md      # From ARCHITECTURE.md
│   │   └── monorepo-structure.md         # From GIT_MONOREPO_MIGRATION_PLAN.md
│   │
│   ├── 02-backend/
│   │   ├── index.md                      # Backend overview
│   │   ├── architecture/
│   │   │   ├── entities.md              # From apps/backend/docs/architecture/
│   │   │   ├── auth-flow.md
│   │   │   ├── multilanguage-model.md
│   │   │   └── roles-permissions.md
│   │   ├── api/
│   │   │   ├── api-platform.md          # API Platform overview
│   │   │   ├── endpoints.md             # From CLAUDE.md API Resources section
│   │   │   ├── testing.md               # From apps/backend/docs/api-platform/
│   │   │   └── authentication.md        # JWT authentication
│   │   ├── services/
│   │   │   ├── media-service.md
│   │   │   └── workflow-articles.md
│   │   ├── database/
│   │   │   ├── migrations.md
│   │   │   ├── entities.md
│   │   │   └── fixtures.md
│   │   └── guides/
│   │       ├── category-import.md       # PRODUCTION_CATEGORY_IMPORT_GUIDE.md
│   │       └── commands.md              # Console commands reference
│   │
│   ├── 03-frontend/
│   │   ├── index.md                      # Frontend overview
│   │   ├── setup/
│   │   │   ├── api-integration.md
│   │   │   └── i18n-config.md
│   │   ├── routing/
│   │   │   ├── public-routes.md
│   │   │   └── admin-routes.md
│   │   ├── ui/
│   │   │   └── authentication-ui.md
│   │   └── features/
│   │       ├── code-splitting.md
│   │       ├── embed-capability.md
│   │       ├── image-management.md
│   │       ├── social-media-integration.md
│   │       └── sport-features.md
│   │
│   ├── 04-features/
│   │   ├── index.md                      # Features overview
│   │   ├── live-text/
│   │   │   ├── index.md                  # Overview
│   │   │   ├── roadmap.md
│   │   │   └── competitive-analysis.md
│   │   ├── performance-analytics/
│   │   │   ├── index.md                  # Quickstart
│   │   │   ├── complete-system.md
│   │   │   ├── advanced-usage.md
│   │   │   └── admin-api.md
│   │   └── archive-import/
│   │       ├── strategy.md
│   │       └── sources-analysis.md
│   │
│   ├── 05-infrastructure/
│   │   ├── index.md                      # Infrastructure overview
│   │   ├── redis-schema.md
│   │   ├── statistics-schema.md
│   │   ├── cron-setup.md
│   │   ├── monitoring-guide.md
│   │   ├── elasticsearch.md              # Elasticsearch setup
│   │   ├── rabbitmq.md                   # Message queue setup
│   │   └── mercure.md                    # Real-time updates
│   │
│   ├── 06-deployment/
│   │   ├── index.md                      # Deployment overview
│   │   ├── development.md                # Dev environment setup
│   │   ├── production.md                 # Production setup (PLAN_SEPARARE_DEV_PROD.md)
│   │   ├── cdn-configuration.md          # CDN setup from CLAUDE.md
│   │   └── environment-variables.md      # .env configuration
│   │
│   ├── 07-development/
│   │   ├── index.md                      # Development guide
│   │   ├── git-workflow.md               # Git-flow from CLAUDE.md
│   │   ├── commit-conventions.md         # Conventional commits
│   │   ├── pull-requests.md              # PR process
│   │   └── ai-assisted-development.md    # From CLAUDE.md (Claude Code usage)
│   │
│   ├── 08-planning/
│   │   ├── index.md
│   │   ├── tags-keywords-implementation.md
│   │   ├── phase6-implementation.md
│   │   ├── facebook-auto-posting.md      # FACEBOOK_AUTO_POSTING_PLAN.md
│   │   └── archive-implementation.md     # Archive strategy and plans
│   │
│   └── 09-reference/
│       ├── index.md
│       ├── api-reference.md              # Complete API reference
│       ├── cli-commands.md               # All console commands
│       ├── configuration-files.md        # Config reference
│       └── troubleshooting.md            # Common issues
│
├── blog/                                  # Keep for updates/announcements
│   ├── 2025-01-15-welcome.md
│   └── authors.yml
│
├── src/
│   ├── components/                       # Custom React components
│   │   ├── HomepageFeatures/
│   │   └── ApiEndpoint/
│   ├── css/
│   │   └── custom.css                    # Custom styling
│   └── pages/
│       ├── index.tsx                     # Landing page
│       └── api-explorer.tsx              # Optional: API explorer page
│
├── static/
│   ├── img/                              # Images and assets
│   │   ├── logo.svg                      # Custom logo
│   │   ├── favicon.ico                   # Custom favicon
│   │   └── screenshots/                  # Application screenshots
│   └── files/                            # Downloadable files (PDFs, etc.)
│
├── docusaurus.config.ts                  # Main configuration
├── sidebars.ts                           # Sidebar configuration
├── package.json
├── tsconfig.json
└── README.md                             # Docs site README
```

### Category Breakdown

| Category | Purpose | Source Files |
|----------|---------|--------------|
| **Getting Started** | Quick start, installation, architecture overview | README.md, SETUP.md, ARCHITECTURE.md, CLAUDE.md |
| **Backend** | API, entities, services, database, commands | apps/backend/docs/, CLAUDE.md |
| **Frontend** | UI, routing, features, integrations | apps/frontend/docs/ |
| **Features** | Live text, analytics, archive import | docs/features/, docs/performance-analytics/ |
| **Infrastructure** | Redis, Elasticsearch, RabbitMQ, Mercure | docs/infrastructure/ |
| **Deployment** | Development, production, CDN setup | PLAN_SEPARARE_DEV_PROD.md, CLAUDE.md |
| **Development** | Git workflow, conventions, PR process | CLAUDE.md, docs/git-flow-comprehensive.md |
| **Planning** | Future features, roadmaps | sprints/, docs/archive-strategy/ |
| **Reference** | API reference, CLI commands, troubleshooting | All sources |

---

## Migration Strategy

### Phase 1: Essential Documentation (Priority: High)

**Estimated Time**: 30 minutes

**Files to Migrate**:
1. **Getting Started**:
   - `docs-site/docs/index.md` ← Create overview
   - `docs-site/docs/getting-started/quick-start.md` ← README.md (Quick Start section)
   - `docs-site/docs/getting-started/installation.md` ← SETUP.md
   - `docs-site/docs/getting-started/architecture-overview.md` ← ARCHITECTURE.md
   - `docs-site/docs/getting-started/monorepo-structure.md` ← docs/GIT_MONOREPO_MIGRATION_PLAN.md

**Content Processing**:
- Extract relevant sections from source files
- Add frontmatter with `sidebar_position`, `title`, `description`
- Update internal links to Docusaurus format
- Add Docusaurus admonitions (:::note, :::tip, :::warning)

### Phase 2: Backend Documentation (Priority: High)

**Estimated Time**: 1 hour

**Files to Migrate**:
1. **Architecture**:
   - `apps/backend/docs/architecture/entities.md` → `docs-site/docs/backend/architecture/entities.md`
   - `apps/backend/docs/architecture/auth-flow.md` → `docs-site/docs/backend/architecture/auth-flow.md`
   - `apps/backend/docs/architecture/multilanguage-model.md` → Similar migration
   - `apps/backend/docs/architecture/roles-permissions.md` → Similar migration

2. **API**:
   - Extract API Resources section from CLAUDE.md → `docs-site/docs/backend/api/endpoints.md`
   - `apps/backend/docs/api-platform/` files → `docs-site/docs/backend/api/`
   - Create authentication guide from JWT configuration

3. **Services**:
   - `apps/backend/docs/services/media-service.md` → `docs-site/docs/backend/services/media-service.md`
   - Similar for workflow services

4. **Database**:
   - Create migration guide from CLAUDE.md console commands
   - Document entities from existing docs

5. **Guides**:
   - `docs/PRODUCTION_CATEGORY_IMPORT_GUIDE.md` → `docs-site/docs/backend/guides/category-import.md`
   - Create CLI commands reference from CLAUDE.md

### Phase 3: Frontend Documentation (Priority: High)

**Estimated Time**: 1 hour

**Files to Migrate**:
1. **Setup**:
   - `apps/frontend/docs/setup/api-integration.md` → `docs-site/docs/frontend/setup/api-integration.md`
   - Similar for i18n configuration

2. **Routing**:
   - `apps/frontend/docs/routing/` → `docs-site/docs/frontend/routing/`

3. **UI**:
   - `apps/frontend/docs/ui/` → `docs-site/docs/frontend/ui/`

4. **Features**:
   - All feature docs from `apps/frontend/docs/features/` → `docs-site/docs/frontend/features/`

### Phase 4: Features & Infrastructure (Priority: Medium)

**Estimated Time**: 1 hour

**Files to Migrate**:
1. **Live Text**:
   - `docs/features/live-text/` → `docs-site/docs/features/live-text/`

2. **Performance Analytics**:
   - `docs/performance-analytics/` → `docs-site/docs/features/performance-analytics/`

3. **Archive Import**:
   - `docs/archive-strategy/` → `docs-site/docs/features/archive-import/`

4. **Infrastructure**:
   - `docs/infrastructure/` → `docs-site/docs/infrastructure/`

### Phase 5: Development & Deployment (Priority: Medium)

**Estimated Time**: 30 minutes

**Files to Migrate**:
1. **Development**:
   - Extract Git-Flow section from CLAUDE.md → `docs-site/docs/development/git-workflow.md`
   - Extract commit conventions → `docs-site/docs/development/commit-conventions.md`
   - Extract PR process → `docs-site/docs/development/pull-requests.md`
   - CLAUDE.md usage guide → `docs-site/docs/development/ai-assisted-development.md`

2. **Deployment**:
   - `docs/PLAN_SEPARARE_DEV_PROD.md` → `docs-site/docs/deployment/production.md`
   - Extract CDN section from CLAUDE.md → `docs-site/docs/deployment/cdn-configuration.md`
   - Create environment variables guide from .env examples

### Phase 6: Planning & Reference (Priority: Low)

**Estimated Time**: 1 hour

**Files to Migrate**:
1. **Planning**:
   - `sprints/` and planning docs → `docs-site/docs/planning/`

2. **Reference**:
   - Create API reference from API Platform docs
   - Create CLI commands reference
   - Create troubleshooting guide (new content)

### Content Processing Guidelines

**For Every Markdown File**:

1. **Add Frontmatter**:
```yaml
---
sidebar_position: 1
title: Page Title
description: Brief description for SEO and search
keywords: [keyword1, keyword2]
---
```

2. **Update Internal Links**:
```markdown
# Before
[Link to setup](./SETUP.md)

# After
[Link to setup](/docs/getting-started/installation)
```

3. **Convert Alerts to Admonitions**:
```markdown
# Before
> **Note**: This is important

# After
:::note
This is important
:::
```

4. **Add Language to Code Blocks**:
```markdown
# Before
```
symfony console make:entity
```

# After
```bash
symfony console make:entity
```
```

5. **Use Proper Heading Hierarchy**:
- File title = H1 (one per page, usually from frontmatter)
- Main sections = H2
- Subsections = H3
- Minor subsections = H4

---

## Configuration Details

### docusaurus.config.ts

```typescript
import {themes as prismThemes} from 'prism-react-renderer';
import type {Config} from '@docusaurus/types';
import type * as Preset from '@docusaurus/preset-classic';

const config: Config = {
  title: 'Deschide News App',
  tagline: 'Multilanguage News Platform Documentation',
  favicon: 'img/favicon.ico',

  // Production URL
  url: 'https://docs.deschide.md',
  baseUrl: '/',

  // GitHub pages deployment config
  organizationName: 'radusoltan',
  projectName: 'deschide_news_app',

  onBrokenLinks: 'warn',
  onBrokenMarkdownLinks: 'warn',

  // Internationalization (optional - can add Romanian later)
  i18n: {
    defaultLocale: 'en',
    locales: ['en', 'ro'],
  },

  presets: [
    [
      'classic',
      {
        docs: {
          sidebarPath: './sidebars.ts',
          editUrl: 'https://github.com/radusoltan/deschide_news_app/tree/main/docs-site/',
          showLastUpdateTime: true,
          showLastUpdateAuthor: true,
          breadcrumbs: true,
        },
        blog: {
          showReadingTime: true,
          editUrl: 'https://github.com/radusoltan/deschide_news_app/tree/main/docs-site/',
          blogTitle: 'Deschide News Blog',
          blogDescription: 'Updates and announcements for Deschide News App',
          postsPerPage: 10,
          blogSidebarCount: 'ALL',
        },
        theme: {
          customCss: './src/css/custom.css',
        },
      } satisfies Preset.Options,
    ],
  ],

  themeConfig: {
    image: 'img/social-card.jpg',
    navbar: {
      title: 'Deschide News App',
      logo: {
        alt: 'Deschide Logo',
        src: 'img/logo.svg',
      },
      items: [
        {
          type: 'docSidebar',
          sidebarId: 'docsSidebar',
          position: 'left',
          label: 'Documentation',
        },
        {to: '/blog', label: 'Blog', position: 'left'},
        {
          type: 'localeDropdown',
          position: 'right',
        },
        {
          href: 'https://github.com/radusoltan/deschide_news_app',
          label: 'GitHub',
          position: 'right',
        },
      ],
    },
    footer: {
      style: 'dark',
      links: [
        {
          title: 'Documentation',
          items: [
            {label: 'Getting Started', to: '/docs/getting-started'},
            {label: 'Backend', to: '/docs/backend'},
            {label: 'Frontend', to: '/docs/frontend'},
            {label: 'API Reference', to: '/docs/reference/api-reference'},
          ],
        },
        {
          title: 'Development',
          items: [
            {label: 'Git Workflow', to: '/docs/development/git-workflow'},
            {label: 'Contributing', to: '/docs/development/pull-requests'},
          ],
        },
        {
          title: 'Resources',
          items: [
            {label: 'GitHub', href: 'https://github.com/radusoltan/deschide_news_app'},
            {label: 'API Docs', href: 'http://127.0.0.1:8081/api/docs.jsonld'},
            {label: 'Blog', to: '/blog'},
          ],
        },
      ],
      copyright: `Copyright © ${new Date().getFullYear()} Deschide News. Built with Docusaurus.`,
    },
    prism: {
      theme: prismThemes.github,
      darkTheme: prismThemes.dracula,
      additionalLanguages: ['php', 'bash', 'typescript', 'json', 'yaml', 'nginx'],
    },
    // Search (Algolia - configure later when deployed)
    algolia: {
      appId: 'YOUR_APP_ID',
      apiKey: 'YOUR_SEARCH_API_KEY',
      indexName: 'deschide_news',
      contextualSearch: true,
    },
  } satisfies Preset.ThemeConfig,

  plugins: [
    // Optional: Ideal image plugin for optimized images
    // '@docusaurus/plugin-ideal-image',
  ],
};

export default config;
```

### sidebars.ts

```typescript
import type {SidebarsConfig} from '@docusaurus/plugin-content-docs';

const sidebars: SidebarsConfig = {
  docsSidebar: [
    {
      type: 'doc',
      id: 'index',
      label: 'Documentation Home',
    },
    {
      type: 'category',
      label: 'Getting Started',
      collapsed: false,
      items: [
        'getting-started/index',
        'getting-started/quick-start',
        'getting-started/installation',
        'getting-started/architecture-overview',
        'getting-started/monorepo-structure',
      ],
    },
    {
      type: 'category',
      label: 'Backend',
      collapsed: false,
      items: [
        'backend/index',
        {
          type: 'category',
          label: 'Architecture',
          items: [
            'backend/architecture/entities',
            'backend/architecture/auth-flow',
            'backend/architecture/multilanguage-model',
            'backend/architecture/roles-permissions',
          ],
        },
        {
          type: 'category',
          label: 'API',
          items: [
            'backend/api/api-platform',
            'backend/api/endpoints',
            'backend/api/testing',
            'backend/api/authentication',
          ],
        },
        {
          type: 'category',
          label: 'Services',
          items: [
            'backend/services/media-service',
            'backend/services/workflow-articles',
          ],
        },
        {
          type: 'category',
          label: 'Database',
          items: [
            'backend/database/migrations',
            'backend/database/entities',
            'backend/database/fixtures',
          ],
        },
        {
          type: 'category',
          label: 'Guides',
          items: [
            'backend/guides/category-import',
            'backend/guides/commands',
          ],
        },
      ],
    },
    {
      type: 'category',
      label: 'Frontend',
      collapsed: false,
      items: [
        'frontend/index',
        {
          type: 'category',
          label: 'Setup',
          items: [
            'frontend/setup/api-integration',
            'frontend/setup/i18n-config',
          ],
        },
        {
          type: 'category',
          label: 'Routing',
          items: [
            'frontend/routing/public-routes',
            'frontend/routing/admin-routes',
          ],
        },
        {
          type: 'category',
          label: 'UI',
          items: [
            'frontend/ui/authentication-ui',
          ],
        },
        {
          type: 'category',
          label: 'Features',
          items: [
            'frontend/features/code-splitting',
            'frontend/features/embed-capability',
            'frontend/features/image-management',
            'frontend/features/social-media-integration',
            'frontend/features/sport-features',
          ],
        },
      ],
    },
    {
      type: 'category',
      label: 'Features',
      collapsed: true,
      items: [
        'features/index',
        {
          type: 'category',
          label: 'Live Text',
          items: [
            'features/live-text/index',
            'features/live-text/roadmap',
            'features/live-text/competitive-analysis',
          ],
        },
        {
          type: 'category',
          label: 'Performance Analytics',
          items: [
            'features/performance-analytics/index',
            'features/performance-analytics/complete-system',
            'features/performance-analytics/advanced-usage',
            'features/performance-analytics/admin-api',
          ],
        },
        {
          type: 'category',
          label: 'Archive Import',
          items: [
            'features/archive-import/strategy',
            'features/archive-import/sources-analysis',
          ],
        },
      ],
    },
    {
      type: 'category',
      label: 'Infrastructure',
      collapsed: true,
      items: [
        'infrastructure/index',
        'infrastructure/redis-schema',
        'infrastructure/statistics-schema',
        'infrastructure/cron-setup',
        'infrastructure/monitoring-guide',
        'infrastructure/elasticsearch',
        'infrastructure/rabbitmq',
        'infrastructure/mercure',
      ],
    },
    {
      type: 'category',
      label: 'Deployment',
      collapsed: true,
      items: [
        'deployment/index',
        'deployment/development',
        'deployment/production',
        'deployment/cdn-configuration',
        'deployment/environment-variables',
      ],
    },
    {
      type: 'category',
      label: 'Development',
      collapsed: true,
      items: [
        'development/index',
        'development/git-workflow',
        'development/commit-conventions',
        'development/pull-requests',
        'development/ai-assisted-development',
      ],
    },
    {
      type: 'category',
      label: 'Planning',
      collapsed: true,
      items: [
        'planning/index',
        'planning/tags-keywords-implementation',
        'planning/phase6-implementation',
        'planning/facebook-auto-posting',
        'planning/archive-implementation',
      ],
    },
    {
      type: 'category',
      label: 'Reference',
      collapsed: true,
      items: [
        'reference/index',
        'reference/api-reference',
        'reference/cli-commands',
        'reference/configuration-files',
        'reference/troubleshooting',
      ],
    },
  ],
};

export default sidebars;
```

### Custom CSS (src/css/custom.css)

```css
/**
 * Deschide News App - Custom Docusaurus Styles
 */

:root {
  /* Primary brand colors */
  --ifm-color-primary: #2e8555;
  --ifm-color-primary-dark: #29784c;
  --ifm-color-primary-darker: #277148;
  --ifm-color-primary-darkest: #205d3b;
  --ifm-color-primary-light: #33925d;
  --ifm-color-primary-lighter: #359962;
  --ifm-color-primary-lightest: #3cad6e;

  /* Code styling */
  --ifm-code-font-size: 95%;
  --docusaurus-highlighted-code-line-bg: rgba(0, 0, 0, 0.1);

  /* Font family */
  --ifm-font-family-base: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu,
    Cantarell, Noto Sans, sans-serif;
  --ifm-font-family-monospace: 'JetBrains Mono', 'Fira Code', Monaco, Consolas,
    'Courier New', monospace;
}

/* Dark mode colors */
[data-theme='dark'] {
  --ifm-color-primary: #25c2a0;
  --ifm-color-primary-dark: #21af90;
  --ifm-color-primary-darker: #1fa588;
  --ifm-color-primary-darkest: #1a8870;
  --ifm-color-primary-light: #29d5b0;
  --ifm-color-primary-lighter: #32d8b4;
  --ifm-color-primary-lightest: #4fddbf;
  --docusaurus-highlighted-code-line-bg: rgba(0, 0, 0, 0.3);
}

/* Code block improvements */
.prism-code {
  font-size: 0.875rem;
  line-height: 1.6;
}

/* Improve table styling */
table {
  display: table;
  width: 100%;
  border-collapse: collapse;
}

table th,
table td {
  padding: 0.75rem;
  border: 1px solid var(--ifm-table-border-color);
}

table th {
  background-color: var(--ifm-table-head-background);
  font-weight: 600;
}

/* Admonition customization */
.admonition {
  margin-bottom: 1rem;
}

/* Custom classes for API endpoints */
.api-endpoint {
  display: inline-block;
  padding: 0.2rem 0.5rem;
  margin: 0.2rem;
  border-radius: 4px;
  font-family: var(--ifm-font-family-monospace);
  font-size: 0.875rem;
}

.api-endpoint.get {
  background-color: #61affe;
  color: white;
}

.api-endpoint.post {
  background-color: #49cc90;
  color: white;
}

.api-endpoint.put {
  background-color: #fca130;
  color: white;
}

.api-endpoint.delete {
  background-color: #f93e3e;
  color: white;
}

.api-endpoint.patch {
  background-color: #50e3c2;
  color: white;
}

/* Responsive improvements */
@media screen and (max-width: 996px) {
  table {
    display: block;
    overflow-x: auto;
  }
}
```

---

## Content Organization

### Frontmatter Template

**Standard Page**:
```yaml
---
sidebar_position: 1
title: Page Title
description: Brief description for SEO and search results
keywords: [symfony, api-platform, doctrine, multilanguage]
---
```

**Category Index Page**:
```yaml
---
sidebar_position: 1
title: Category Overview
description: Overview of the category
slug: /category-slug
---
```

### File Naming Conventions

- Use lowercase with hyphens: `api-integration.md`
- Use descriptive names: `multilanguage-model.md` not `ml-model.md`
- Index files for categories: `index.md`
- Match URL structure: `backend/api/endpoints.md` → `/docs/backend/api/endpoints`

### Content Guidelines

**Markdown Standards**:
- One H1 per page (usually from frontmatter title)
- Use proper heading hierarchy (H2 → H3 → H4)
- Include table of contents for long pages (Docusaurus auto-generates)
- Use code blocks with language identifiers
- Use admonitions for notes, tips, warnings, dangers

**Code Block Examples**:
````markdown
```bash
# Bash commands
symfony console make:entity
```

```php
// PHP code
<?php

namespace App\Entity;

class Article
{
    // ...
}
```

```typescript
// TypeScript code
interface ArticleProps {
  id: number;
  title: string;
}
```
````

**Admonitions**:
```markdown
:::note
This is a note
:::

:::tip
This is a helpful tip
:::

:::warning
This is a warning
:::

:::danger
This is dangerous information
:::

:::info
This is informational
:::
```

**Links**:
```markdown
<!-- Internal doc links -->
[Installation Guide](/docs/getting-started/installation)

<!-- External links -->
[API Platform Documentation](https://api-platform.com/docs/)

<!-- Anchor links -->
[Jump to Configuration](#configuration)
```

**Images**:
```markdown
<!-- From static directory -->
![Architecture Diagram](/img/architecture-diagram.png)

<!-- With alt text and title -->
![Entity Relationships](/img/entity-er-diagram.png "Entity Relationship Diagram")
```

---

## Documentation Gaps Analysis

### Missing Documentation

**Critical Gaps** (Need to Create):

1. **Complete CLI Commands Reference**
   - Currently scattered across CLAUDE.md
   - Need comprehensive reference with examples
   - Should include all custom console commands

2. **API Authentication Flow Details**
   - JWT token generation and refresh
   - Authorization headers
   - Token expiration handling
   - CORS configuration

3. **Troubleshooting Guide**
   - Common errors and solutions
   - Database migration issues
   - CORS problems
   - Image upload failures
   - Port conflicts

4. **Performance Optimization Guide**
   - Database query optimization
   - Redis caching strategies
   - Image optimization
   - Frontend bundle optimization

5. **Security Best Practices**
   - Authentication security
   - Input validation
   - XSS prevention
   - SQL injection prevention
   - File upload security

6. **Testing Strategy**
   - Unit testing (PHPUnit)
   - Integration testing
   - E2E testing
   - API testing

7. **Code Style Guidelines**
   - PHP-CS-Fixer configuration
   - ESLint/Prettier for frontend
   - Naming conventions
   - Documentation standards

### Incomplete Documentation

**Needs Expansion**:

1. **Elasticsearch Setup**
   - Installation steps
   - Index creation
   - Mapping configuration
   - Search query examples

2. **RabbitMQ Configuration**
   - Installation and setup
   - Exchange and queue creation
   - Message routing
   - Consumer configuration

3. **Mercure Real-time Updates**
   - Installation guide
   - Hub configuration
   - Topic subscription
   - Frontend SSE implementation

4. **CDN Configuration**
   - Detailed CDN setup
   - Image serving optimization
   - Cache invalidation
   - CloudFront/custom CDN setup

5. **Production Deployment Checklist**
   - Pre-deployment checks
   - Environment configuration
   - Database backup
   - Rollback procedures

### Consolidation Needed

**Scattered Information**:

1. **Planning Documents**
   - Multiple files in `sprints/` directory
   - Archive-related docs across multiple locations
   - Need to organize by feature/timeline

2. **Environment Setup**
   - Information split between CLAUDE.md and SETUP.md
   - Need single source of truth

3. **Entity Documentation**
   - Multiple entity-specific docs in `docs/`
   - Should consolidate into comprehensive entity reference

---

## Versioning Strategy

### Approach: Version by Major Releases

**Recommended Strategy**: Document versioning aligned with application releases

**Configuration**:
```typescript
// In docusaurus.config.ts
versions: {
  current: {
    label: 'v1.1 (Current)',
    path: 'current',
    banner: 'none',
  },
  '1.0': {
    label: 'v1.0',
    path: '1.0',
    banner: 'unmaintained',
  },
},
```

### When to Create New Version

**Version Documentation When**:
1. **Major API changes** (breaking changes)
2. **Significant architecture updates**
3. **Major feature releases** (e.g., Live Text feature)
4. **Database schema breaking changes**

### Versioning Workflow

**Creating a New Version**:
```bash
# When ready to release v1.0
cd /var/www/deschide_news_app/docs-site
npm run docusaurus docs:version 1.0

# This creates:
# - versioned_docs/version-1.0/     (snapshot of current docs)
# - versioned_sidebars/version-1.0-sidebars.json
# - versions.json                    (version registry)
```

**Version Directory Structure**:
```
docs-site/
├── docs/                           # Current/next version (v1.1-dev)
├── versioned_docs/
│   ├── version-1.0/               # v1.0 docs snapshot
│   └── version-0.9/               # v0.9 docs snapshot (if needed)
├── versioned_sidebars/
│   ├── version-1.0-sidebars.json
│   └── version-0.9-sidebars.json
└── versions.json                   # {"1.0": "1.0", "0.9": "0.9"}
```

### Version Banner Configuration

**Show Banners for Old Versions**:
```typescript
presets: [
  [
    'classic',
    {
      docs: {
        versions: {
          current: {
            label: 'Next (v1.1)',
            banner: 'unreleased',
          },
          '1.0': {
            label: 'v1.0 (Stable)',
            banner: 'none',
          },
          '0.9': {
            label: 'v0.9 (Outdated)',
            banner: 'unmaintained',
          },
        },
      },
    },
  ],
],
```

**Banner Types**:
- `none` - No banner (current stable)
- `unreleased` - Next/development version
- `unmaintained` - Old version no longer maintained

### Initial Versioning Recommendation

**For v1.0 Launch**:
1. Complete all documentation migration
2. Review and update all content
3. Deploy without versioning initially
4. Create v1.0 snapshot when ready for production release
5. Continue working on `current` for v1.1 features

---

## Deployment Options

### Option 1: GitHub Pages (Recommended)

**Pros**:
- Free hosting
- Automatic builds with GitHub Actions
- Custom domain support
- HTTPS included
- Easy CI/CD integration

**Setup**:

1. **Configure docusaurus.config.ts**:
```typescript
const config: Config = {
  url: 'https://radusoltan.github.io',
  baseUrl: '/deschide_news_app/',
  organizationName: 'radusoltan',
  projectName: 'deschide_news_app',
  deploymentBranch: 'gh-pages',
  trailingSlash: false,
};
```

2. **Add deploy script to package.json**:
```json
{
  "scripts": {
    "deploy": "docusaurus deploy"
  }
}
```

3. **Manual Deployment**:
```bash
cd /var/www/deschide_news_app/docs-site
GIT_USER=radusoltan pnpm deploy
```

4. **Automatic Deployment with GitHub Actions**:
Create `.github/workflows/deploy-docs.yml`:
```yaml
name: Deploy Docusaurus

on:
  push:
    branches:
      - main
    paths:
      - 'docs-site/**'

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: 20
          cache: 'pnpm'
          cache-dependency-path: docs-site/pnpm-lock.yaml

      - name: Install dependencies
        run: |
          cd docs-site
          pnpm install --frozen-lockfile

      - name: Build
        run: |
          cd docs-site
          pnpm build

      - name: Deploy to GitHub Pages
        uses: peaceiris/actions-gh-pages@v3
        with:
          github_token: ${{ secrets.GITHUB_TOKEN }}
          publish_dir: ./docs-site/build
```

### Option 2: Vercel

**Pros**:
- Excellent performance
- Global CDN
- Preview deployments for PRs
- Free for personal projects
- Zero config deployment

**Setup**:

1. **Connect GitHub Repository**:
   - Go to https://vercel.com
   - Import `deschide_news_app` repository
   - Select `docs-site` as root directory

2. **Build Settings**:
   - Framework: Docusaurus
   - Root Directory: `docs-site`
   - Build Command: `pnpm build`
   - Output Directory: `build`

3. **Environment Variables**: None needed for basic setup

4. **Custom Domain**: Can add `docs.deschide.md`

### Option 3: Netlify

**Pros**:
- Generous free tier
- Great developer experience
- Built-in form handling
- Deploy previews

**Setup**:

1. **netlify.toml** (create in `docs-site/`):
```toml
[build]
  base = "docs-site"
  command = "pnpm build"
  publish = "build"

[[redirects]]
  from = "/*"
  to = "/index.html"
  status = 200
```

2. **Deploy**:
   - Connect GitHub repository
   - Auto-detects Docusaurus
   - Automatic deployments on push

### Option 4: Self-Hosted (Nginx)

**Pros**:
- Full control
- No external dependencies
- Can integrate with existing infrastructure

**Setup**:

1. **Build Locally**:
```bash
cd /var/www/deschide_news_app/docs-site
pnpm build
```

2. **Nginx Configuration** (`/etc/nginx/sites-available/docs.deschide.local`):
```nginx
server {
    listen 80;
    server_name docs.deschide.local;
    root /var/www/deschide_news_app/docs-site/build;

    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # Gzip compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;

    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|ttf)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

3. **Enable Site**:
```bash
sudo ln -s /etc/nginx/sites-available/docs.deschide.local /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

4. **Add to /etc/hosts**:
```
127.0.0.1   docs.deschide.local
```

### Deployment Recommendation

**For This Project**:
1. **Development**: Local build and test
2. **Staging**: Vercel or Netlify (free tier, preview deploys)
3. **Production**: GitHub Pages or self-hosted with nginx

**Suggested Workflow**:
- **GitHub Pages** for official documentation (public)
- **Vercel** for preview deployments on PRs
- Local nginx for internal/development reference

---

## Port Allocation

### Current Port Usage

| Service | Port | Status |
|---------|------|--------|
| Backend (Symfony) | 8081 | ✅ Running |
| Frontend (Next.js) | 3005 | ✅ Running |
| CDN | 8082 | ✅ Running |
| **Mercure** | **3000** | ✅ Running |
| PostgreSQL | 5432 | ✅ Running |
| Redis | 6379 | ✅ Running |
| RabbitMQ | 5672 | ✅ Running |
| RabbitMQ Management | 15672 | ✅ Running |
| Elasticsearch | 9200 | ✅ Running |
| Prometheus | 9090 | ✅ Running |
| Grafana | 3002 | ✅ Running |

### Port Conflict: Docusaurus vs Mercure

**Issue**: Both services want port 3000

**Solutions**:

#### Solution 1: Run Docusaurus on Alternate Port (Recommended)

```bash
# Start Docusaurus on port 3001
cd /var/www/deschide_news_app/docs-site
pnpm start -- --port 3001
```

**Update package.json**:
```json
{
  "scripts": {
    "start": "docusaurus start --port 3001"
  }
}
```

**Result**:
- Docusaurus: http://localhost:3001
- Mercure: http://localhost:3000 (unchanged)

#### Solution 2: Move Mercure to Alternate Port

**Update Backend .env**:
```bash
# Change from port 3000 to 3003
MERCURE_URL=http://localhost:3003/.well-known/mercure
```

**Restart Mercure** on new port

**Result**:
- Docusaurus: http://localhost:3000
- Mercure: http://localhost:3003

#### Solution 3: Nginx Reverse Proxy

**Both services stay on their ports**, accessed via nginx:

```nginx
# Docusaurus
server {
    listen 80;
    server_name docs.deschide.local;
    location / {
        proxy_pass http://localhost:3001;
    }
}

# Mercure
server {
    listen 80;
    server_name mercure.deschide.local;
    location / {
        proxy_pass http://localhost:3000;
    }
}
```

**Access URLs**:
- Docusaurus: http://docs.deschide.local
- Mercure: http://mercure.deschide.local

### Recommended Configuration

**Final Port Allocation**:

| Service | Port | Access URL |
|---------|------|------------|
| **Docusaurus (Dev)** | **3001** | http://localhost:3001 |
| **Docusaurus (Nginx)** | **80** | http://docs.deschide.local |
| Mercure | 3000 | http://localhost:3000 |
| Backend | 8081 | http://127.0.0.1:8081 |
| Frontend | 3005 | http://localhost:3005 |
| CDN | 8082 | http://127.0.0.1:8082 |

---

## Timeline and Effort Estimate

### Total Estimated Time: ~6-8 hours

### Phase Breakdown

| Phase | Tasks | Time | Priority | Assignee |
|-------|-------|------|----------|----------|
| **Phase 1** | Installation & Setup | 30 min | High | Developer |
| | - Create Docusaurus site | 5 min | | |
| | - Clean default content | 5 min | | |
| | - Configure docusaurus.config.ts | 10 min | | |
| | - Configure sidebars.ts | 10 min | | |
| **Phase 2** | Getting Started Docs | 30 min | High | Developer |
| | - Migrate Quick Start | 10 min | | |
| | - Migrate Installation | 10 min | | |
| | - Create Architecture Overview | 10 min | | |
| **Phase 3** | Backend Documentation | 1-2 hours | High | Backend Dev |
| | - Migrate architecture docs | 30 min | | |
| | - Create API documentation | 30 min | | |
| | - Create guides | 30 min | | |
| **Phase 4** | Frontend Documentation | 1-2 hours | High | Frontend Dev |
| | - Migrate setup docs | 20 min | | |
| | - Migrate routing docs | 20 min | | |
| | - Migrate feature docs | 40 min | | |
| **Phase 5** | Features & Infrastructure | 1 hour | Medium | Developer |
| | - Migrate features docs | 30 min | | |
| | - Migrate infrastructure | 30 min | | |
| **Phase 6** | Development & Deployment | 30 min | Medium | Developer |
| | - Create Git workflow guide | 10 min | | |
| | - Create deployment guide | 10 min | | |
| | - Create troubleshooting | 10 min | | |
| **Phase 7** | Planning & Reference | 1 hour | Low | Tech Lead |
| | - Organize planning docs | 30 min | | |
| | - Create API reference | 20 min | | |
| | - Create CLI reference | 10 min | | |
| **Phase 8** | Customization | 30 min | Low | Developer |
| | - Custom CSS styling | 15 min | | |
| | - Landing page creation | 15 min | | |
| **Phase 9** | Testing & Deployment | 30 min | High | DevOps |
| | - Local testing | 10 min | | |
| | - Build production | 10 min | | |
| | - Deploy to staging | 10 min | | |
| **Total** | | **6-8 hours** | | |

### Parallel Work Opportunities

**Can Be Done Simultaneously**:
- Backend docs (Phase 3) + Frontend docs (Phase 4)
- Features (Phase 5) + Development guides (Phase 6)
- Planning docs (Phase 7) + Customization (Phase 8)

**Reduces Total Time**: From 6-8 hours to 3-4 hours with 2 people

### Incremental Deployment Strategy

**Week 1**:
- ✅ Install Docusaurus
- ✅ Migrate Getting Started
- ✅ Deploy to staging

**Week 2**:
- ✅ Migrate Backend docs
- ✅ Migrate Frontend docs
- ✅ Update deployment

**Week 3**:
- ✅ Migrate Features & Infrastructure
- ✅ Add Development guides
- ✅ Full testing

**Week 4**:
- ✅ Complete Planning & Reference
- ✅ Customize theme
- ✅ Production deployment

---

## Next Steps

### Immediate Actions (This Week)

1. **Decision Making**:
   - [ ] Review this plan and approve approach
   - [ ] Decide on deployment target (GitHub Pages / Vercel / Self-hosted)
   - [ ] Resolve port allocation (Docusaurus vs Mercure)
   - [ ] Assign team members to phases

2. **Installation** (30 minutes):
   ```bash
   # Install Docusaurus
   cd /var/www/deschide_news_app
   npx create-docusaurus@latest docs-site classic --typescript
   cd docs-site
   pnpm install

   # Clean default content
   rm -rf docs/tutorial-basics/ docs/tutorial-extras/
   rm docs/intro.md
   rm -rf blog/2019-* blog/2021-*

   # Configure port to avoid conflict
   # Edit package.json scripts section
   ```

3. **Initial Configuration** (30 minutes):
   - [ ] Update `docusaurus.config.ts` with project details
   - [ ] Configure `sidebars.ts` with proposed structure
   - [ ] Test local server: `pnpm start`

4. **First Content Migration** (1 hour):
   - [ ] Create `docs/index.md` (Documentation home)
   - [ ] Create `docs/getting-started/` directory
   - [ ] Migrate Quick Start from README.md
   - [ ] Verify rendering and navigation

### Short-term Goals (Next 2 Weeks)

1. **Complete High-Priority Documentation**:
   - [ ] Getting Started section
   - [ ] Backend documentation
   - [ ] Frontend documentation
   - [ ] Deploy to staging environment

2. **Quality Assurance**:
   - [ ] Review all migrated content
   - [ ] Fix broken links
   - [ ] Optimize images
   - [ ] Test all code examples

3. **Team Onboarding**:
   - [ ] Share Docusaurus editing guide
   - [ ] Establish documentation update workflow
   - [ ] Create contribution guidelines

### Long-term Goals (Next 1-2 Months)

1. **Complete Migration**:
   - [ ] All documentation migrated
   - [ ] Documentation gaps filled
   - [ ] Search functionality configured (Algolia)
   - [ ] Multi-language support added (Romanian)

2. **Production Deployment**:
   - [ ] Deploy to production URL
   - [ ] Configure custom domain (docs.deschide.md)
   - [ ] Set up automatic deployments
   - [ ] Monitor analytics

3. **Continuous Improvement**:
   - [ ] Regular content updates
   - [ ] User feedback collection
   - [ ] Documentation versioning
   - [ ] Video tutorials (optional)

---

## Questions to Resolve

Before proceeding, please decide on:

1. **Deployment Strategy**:
   - [ ] GitHub Pages
   - [ ] Vercel
   - [ ] Netlify
   - [ ] Self-hosted (Nginx)

2. **Port Configuration**:
   - [ ] Run Docusaurus on port 3001
   - [ ] Move Mercure to different port
   - [ ] Use nginx reverse proxy

3. **Migration Priority**:
   - [ ] All at once (6-8 hours)
   - [ ] Incremental (1-2 weeks)
   - [ ] Minimal viable docs first

4. **Team Assignment**:
   - [ ] Who will handle backend docs?
   - [ ] Who will handle frontend docs?
   - [ ] Who will deploy and maintain?

5. **Additional Features**:
   - [ ] Multi-language docs (Romanian + English)?
   - [ ] Blog for announcements?
   - [ ] API playground/explorer?
   - [ ] Video tutorials?

---

## Appendix: Quick Reference Commands

### Development

```bash
# Start development server
cd /var/www/deschide_news_app/docs-site
pnpm start                    # Port 3000 (default)
pnpm start -- --port 3001    # Port 3001 (avoid Mercure conflict)

# Build for production
pnpm build

# Serve production build locally
pnpm serve

# Clear cache
pnpm clear

# Generate static files
pnpm write-translations
```

### Content Management

```bash
# Create new doc
# Manually create file: docs/section/new-doc.md

# Create new blog post
# Manually create file: blog/YYYY-MM-DD-post-title.md

# Create version snapshot
npm run docusaurus docs:version 1.0

# Update sidebar
# Edit: sidebars.ts
```

### Deployment

```bash
# Deploy to GitHub Pages
GIT_USER=radusoltan pnpm deploy

# Build and deploy manually
pnpm build
# Upload build/ directory to hosting
```

### Troubleshooting

```bash
# Clear Docusaurus cache
rm -rf .docusaurus

# Clear node_modules and reinstall
rm -rf node_modules pnpm-lock.yaml
pnpm install

# Check for broken links
pnpm build
# Review build output for warnings

# Test production build locally
pnpm build && pnpm serve
```

---

## Support and Resources

### Docusaurus Documentation
- **Official Docs**: https://docusaurus.io/docs
- **Configuration**: https://docusaurus.io/docs/configuration
- **Markdown Features**: https://docusaurus.io/docs/markdown-features
- **Deployment**: https://docusaurus.io/docs/deployment

### Community Resources
- **GitHub**: https://github.com/facebook/docusaurus
- **Discord**: https://discord.gg/docusaurus
- **Stack Overflow**: Tag `docusaurus`

### Project-Specific
- **Source Repository**: https://github.com/radusoltan/deschide_news_app
- **Backend API**: http://127.0.0.1:8081/api
- **Frontend**: http://localhost:3005

---

**Document Version**: 1.0
**Last Updated**: 2025-11-14
**Prepared By**: Claude Code (Docusaurus Expert Agent)
**Status**: Ready for Review and Approval
