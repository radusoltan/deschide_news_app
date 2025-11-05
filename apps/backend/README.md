# Deschide News - Frontend

Modern multilingual news platform built with **Next.js 16** and **React 19**.

## 📋 Overview

- **Framework**: Next.js 16 (App Router)
- **React Version**: 19.2
- **Language**: TypeScript 5.x
- **Bundler**: Turbopack (Next.js 16 default)
- **Styling**: Tailwind CSS 4
- **State Management**: React Query + React Context
- **API Integration**: Fetch API + Custom API Client
- **Real-time**: Mercure SSE (Server-Sent Events)
- **Testing**: Jest + Playwright

## 🚀 Quick Start

### Prerequisites

- Node.js 20+ (LTS)
- pnpm 9+ (recommended) or npm 10+
- Backend API running (http://127.0.0.1:8081)

### Installation

```bash
# 1. Clone repository
git clone git@github.com:radusoltan/deschide_news_app_frontend.git
cd deschide_news_app_frontend

# 2. Install dependencies
pnpm install
# or
npm install

# 3. Configure environment
cp .env.example .env.local
# Edit .env.local with your configuration

# 4. Run development server
pnpm dev
# or
npm run dev
```

### Access Application

- **Development Server**: http://localhost:3005
- **Public Homepage**: http://localhost:3005/ro (Romanian)
- **Admin Panel**: http://localhost:3005/ro/admin
- **Login**: http://localhost:3005/ro/login

## 🔧 Configuration

### Environment Variables

```bash
PORT=3005
NEXT_PUBLIC_API_URL=http://127.0.0.1:8081
NEXT_PUBLIC_CDN_URL=http://127.0.0.1:8082
NEXT_PUBLIC_MERCURE_URL=http://localhost:3000/.well-known/mercure
NEXT_PUBLIC_DEFAULT_LOCALE=ro
NEXT_PUBLIC_AVAILABLE_LOCALES=ro,en,ru
```

## 📄 Documentation

- `/docs/api_integration.md` - API client architecture
- `/docs/i18n_config.md` - Internationalization
- `/docs/auth_ui.md` - Authentication flow
- `/docs/routes_public.md` - Public pages
- `/docs/routes_admin.md` - Admin CMS

---

**Version**: 1.0
**Status**: Production-ready
