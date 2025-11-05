# Frontend Documentation

This directory contains documentation for the Deschide News Next.js frontend application.

## 📋 Document Index

### API Integration & Architecture

| Document | Description | Target Audience |
|----------|-------------|-----------------|
| **api_integration.md** | Complete API client architecture, token management, error handling | Frontend developers, auditor |

### Internationalization

| Document | Description | Target Audience |
|----------|-------------|-----------------|
| **i18n_config.md** | Multilingual routing (ro/en/ru), locale handling, language switching | Frontend developers, auditor |

### Authentication & Security

| Document | Description | Target Audience |
|----------|-------------|-----------------|
| **auth_ui.md** | Login flow, token storage, route protection, session management | Security auditor, frontend developers |

### Application Routes

| Document | Description | Target Audience |
|----------|-------------|-----------------|
| **routes_public.md** | Public pages (homepage, category, article, search, etc.) | Frontend developers, content team |
| **routes_admin.md** | Admin CMS routes (dashboard, article editor, media library, etc.) | Frontend developers, content team |

---

## 🎯 Quick Navigation

### For External Auditor

**Frontend Security Review:**
1. **auth_ui.md** - Authentication flow and token management
2. **api_integration.md** - API security (headers, error handling)
3. **routes_admin.md** - Protected routes and role-based access

**Frontend Architecture:**
1. **api_integration.md** - Backend communication
2. **i18n_config.md** - Internationalization implementation
3. **routes_public.md** - Public application structure
4. **routes_admin.md** - Admin application structure

### For New Frontend Developers

**Onboarding Path:**
1. **routes_public.md** - Understand public application
2. **routes_admin.md** - Understand admin CMS
3. **api_integration.md** - Learn API integration patterns
4. **i18n_config.md** - Understand multilingual routing
5. **auth_ui.md** - Implement authentication

**Common Tasks:**
- Add new public page? → routes_public.md
- Add new admin feature? → routes_admin.md
- Integrate API endpoint? → api_integration.md
- Add translation? → i18n_config.md
- Implement auth? → auth_ui.md

### For Content Team

**Understanding the CMS:**
- routes_admin.md - All admin features and workflows

---

## 📁 Directory Structure Reference

```
deschide_frontend/
├── app/
│   ├── [locale]/              # Localized routes (ro/en/ru)
│   │   ├── (public)/         # Public pages group
│   │   │   ├── page.tsx      # Homepage
│   │   │   ├── [categorySlug]/
│   │   │   │   ├── page.tsx  # Category page
│   │   │   │   └── [articleSlug]/page.tsx  # Article detail
│   │   │   ├── search/       # Search page
│   │   │   ├── trending/     # Trending articles
│   │   │   └── ...
│   │   ├── admin/            # Admin CMS (protected)
│   │   │   ├── page.tsx      # Dashboard
│   │   │   ├── articles/     # Article management
│   │   │   ├── categories/   # Category management
│   │   │   ├── images/       # Media library
│   │   │   └── ...
│   │   ├── live/[slug]/      # LiveText viewer
│   │   └── login/            # Login page
│   ├── layout.tsx            # Root layout
│   ├── sitemap.ts            # Dynamic sitemap
│   └── robots.ts             # Robots.txt
├── components/               # React components
│   ├── admin/               # Admin components
│   ├── article/             # Article display
│   ├── navigation/          # Navigation components
│   └── ui/                  # Reusable UI
├── lib/
│   ├── api/                 # API functions
│   │   ├── articles.ts
│   │   ├── categories.ts
│   │   └── ...
│   ├── api-client.ts        # Core API client
│   ├── hooks/               # Custom hooks
│   ├── types/               # TypeScript types
│   └── utils/               # Utilities
└── public/                  # Static assets
```

---

## 🔗 Related Documentation

### Backend Documentation

**API Specification:**
- Backend: `/var/www/deschide_news_app/deschide_backend/docs/openapi.jsonld`
- Backend: `/var/www/deschide_news_app/deschide_backend/docs/README.md`

**Authentication:**
- Backend: `/var/www/deschide_news_app/deschide_backend/docs/auth_flow.md`
- Frontend: `auth_ui.md` (this repo)

**Entities:**
- Backend: `/var/www/deschide_news_app/deschide_backend/docs/entities.md`

**Multilanguage:**
- Backend: `/var/www/deschide_news_app/deschide_backend/docs/multilanguage_model.md`
- Frontend: `i18n_config.md` (this repo)

### Project Documentation

- `/var/www/deschide_news_app/deschide_backend/docs/BUSINESS_OVERVIEW.md` - Complete project overview
- `/var/www/deschide_news_app/deschide_backend/docs/AUDIT_SCOPE.md` - Audit scope definition
- `/var/www/deschide_news_app/CLAUDE.md` - Main project guide

---

## 📊 Key Technologies

### Framework & Core

- **Next.js 16** - React framework (App Router)
- **React 19.2** - UI library
- **TypeScript 5.x** - Type safety
- **Turbopack** - Fast bundler

### Styling

- **Tailwind CSS 4** - Utility-first CSS
- **CSS Modules** - Component-scoped styles

### State Management

- **React Context** - Global state
- **React Query** - Server state (planned)

### API Integration

- **Fetch API** - HTTP requests
- **Custom API Client** - Token management, auto-refresh

### Real-time

- **Mercure** - Server-sent events (SSE)

### Testing

- **Jest** - Unit tests
- **Playwright** - E2E tests

---

## 🔐 Security Considerations

### Token Storage

**Current Implementation** (Development):
- Tokens stored in memory
- Lost on page refresh
- Not vulnerable to XSS

**Recommended for Production**:
- httpOnly cookies
- Secure flag (HTTPS only)
- SameSite=Strict

See **auth_ui.md** for details.

### Protected Routes

**Server-Side Protection**:
- Layout components check authentication
- Redirect to login if not authenticated
- Role-based access control

**Client-Side Protection**:
- `useRequireAuth` hook
- Automatic redirect
- Token validation

See **routes_admin.md** for implementation.

---

## 🌐 Internationalization

### Supported Locales

- **ro** (Romanian) - Default
- **en** (English)
- **ru** (Russian)

### URL Structure

```
/ro/...          → Romanian
/en/...          → English
/ru/...          → Russian
```

### Implementation

- Dynamic `[locale]` segment in routes
- `Accept-Language` header sent to backend
- Automatic slug translation
- Language switcher preserves context

See **i18n_config.md** for complete details.

---

## 🚀 Performance Optimization

### Static Generation

- Homepage pre-rendered
- Top categories pre-rendered
- Article pages generated on-demand

### Incremental Static Regeneration

```typescript
export const revalidate = 60; // Revalidate every 60 seconds
```

### Image Optimization

- Next.js Image component
- Lazy loading
- WebP format
- Responsive sizes

### Code Splitting

- Route-based automatic splitting
- Dynamic imports for heavy components
- Lazy loading for admin features

---

## 📝 Naming Conventions

### Files

- **Pages**: `page.tsx` (Next.js convention)
- **Layouts**: `layout.tsx`
- **Components**: `PascalCase.tsx`
- **Utilities**: `camelCase.ts`
- **Types**: `kebab-case.ts`

### Routes

- **Public**: `/[locale]/[categorySlug]/[articleSlug]`
- **Admin**: `/[locale]/admin/[feature]/[action]`
- **API**: `/api/[endpoint]`

### Components

- **Page Components**: `HomePage`, `ArticlePage`
- **Layout Components**: `AdminLayout`, `PublicLayout`
- **UI Components**: `Button`, `Card`, `Modal`
- **Feature Components**: `ArticleEditor`, `ImageGallery`

---

## 🔄 Update Guidelines

### When to Update Documentation

**api_integration.md**:
- New API endpoint added
- Authentication flow changes
- Error handling updates

**i18n_config.md**:
- New locale added
- URL structure changes
- Translation system updates

**auth_ui.md**:
- Login flow changes
- Token storage strategy changes
- Route protection updates

**routes_public.md**:
- New public page added
- URL structure changes
- Page features updated

**routes_admin.md**:
- New admin feature added
- Permission changes
- CMS workflow updates

### How to Update

1. Make code changes
2. Update relevant documentation
3. Update "Last Updated" date
4. Commit with descriptive message
5. Notify team

---

## 📦 Development Setup

### Prerequisites

- Node.js 20+ (LTS)
- pnpm 9+ (or npm 10+)
- Backend API running (http://127.0.0.1:8081)

### Quick Start

```bash
# Install dependencies
pnpm install

# Copy environment template
cp .env.example .env.local

# Edit .env.local with your configuration
nano .env.local

# Run development server
pnpm dev
```

**Access**: http://localhost:3005

See main **README.md** in repository root for complete setup.

---

## 🆘 Support

**Questions about frontend:**
- Check relevant documentation in this directory
- See main `README.md` in repository root
- Check backend docs for API details

**Found an error in documentation:**
- Create issue in repository
- Or submit pull request with fix

---

## ⚠️ Important Notes

### For Auditor

1. **Environment**: Documentation based on development setup
2. **Production**: Some features use simplified implementations in dev
3. **Security**: Token storage uses memory in dev, should use httpOnly cookies in production
4. **Dependencies**: Check `package.json` for complete dependency list

### For Developers

1. **Keep Docs Updated**: Update docs when changing features
2. **Follow Conventions**: Use established patterns in codebase
3. **Type Safety**: Always use TypeScript types
4. **Test Changes**: Test in all supported locales (ro/en/ru)

---

**Last Updated**: November 5, 2025
**Maintained By**: Frontend Team
**Version**: 1.0
**Status**: Ready for External Audit
