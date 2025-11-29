# Plan de Dezvoltare Frontend - Deschide News App

**Data creare:** 2025-10-27
**Ultima actualizare:** 2025-10-27
**Versiune:** 2.1

**NOTE:** Acest plan acoperă doar dezvoltarea frontend-ului. Backend are plan separat (development-plan.md).

---

## 📚 Documentații de Referință

### Official Next.js Documentation
- [Internationalization](https://nextjs.org/docs/app/guides/internationalization) - Routing și locale management
- [Data Fetching](https://nextjs.org/docs/app/getting-started/fetching-data) - Server Components, Suspense, caching
- [Images](https://nextjs.org/docs/app/getting-started/images) - Image optimization
- [Multi-Zones](https://nextjs.org/docs/app/guides/multi-zones) - Multiple apps patterns
- [Authentication](https://nextjs.org/docs/app/guides/authentication) - Auth patterns, sessions, middleware

### Internationalization Tutorial
- [React-Intl with Next.js](https://i18nexus.com/tutorials/nextjs/react-intl) - Implementare cu next-i18n-router

### Tailwind CSS
- [Upgrade Guide (v3 → v4)](https://tailwindcss.com/docs/upgrade-guide) - Migrare template TailNews

### Design Resources
- **Admin Template:** [Flowbite Admin Dashboard](/layouts/flowbite-admin-dashboard/) - Hugo/Tailwind v4 pentru admin
- **Public Template:** [TailNews](/layouts/tailnews/) - News template **Tailwind v3** (necesită migrare la v4)

---

## Stack Tehnologic Frontend

**Core:**
- Next.js 16 (App Router)
- React 19.2
- TypeScript
- **Tailwind CSS 4** (cu migrare din v3 pentru template-uri)
- Turbopack (bundler default)

**Styling Strategy:**
- **Zona Publică**: Stiluri separate adaptate din TailNews (Tailwind v3 → v4)
- **Zona Admin**: Stiluri separate cu Flowbite (Tailwind v4)
- Configurații CSS separate pentru izolare completă între zone

**Internationalization:**
- **next-i18n-router** - routing pentru locale (ro, en, ru)
- **react-intl** - message formatting, number/date localization

**State Management & Data Fetching:**
- **Server Components** (preferred) - pentru data fetching
- **React Query (TanStack Query)** - client-side state, caching, mutations
- **Zustand** - lightweight global state (UI, auth)

**Forms & Validation:**
- React Hook Form
- Zod (schema validation)

**UI Components:**
- **Flowbite Components** - pentru admin panel
- **Tailwind CSS 4** - utility classes
- **Heroicons** - icon library
- Custom components based on TailNews template

**Authentication:**
- JWT tokens (access + refresh)
- Middleware-based route protection
- Iron Session (optional, for stateless sessions)

**Real-time:**
- Mercure SSE client

**Image Handling:**
- Next.js Image component
- Remote patterns pentru backend images

**Development:**
- Port: 3005 (pnpm dev)
- API Backend: http://127.0.0.1:8081

---

## 📋 Starea Actuală a Proiectului

### ✅ Environment Setup (COMPLETED)

**Frontend:**
- ✅ Next.js 16 instalat în `/var/www/deschide_news_app/deschide_frontend`
- ✅ Rulează pe port 3005
- ✅ React 19.2.0, Tailwind CSS 4 configurate
- ✅ `.env.local` creat cu toate variabilele
- ✅ `ecosystem.config.js` pentru PM2 creat
- ✅ `next.config.mjs` configurat pentru port 3005 și Turbopack
- ✅ Zero conflicte de porturi

**Installed Packages:**
- ✅ next 16.0.0
- ✅ react 19.2.0
- ✅ react-dom 19.2.0
- ✅ tailwindcss 4
- ✅ eslint 9

**Configuration Files:**
- ✅ `.env.local` - environment variables
- ✅ `next.config.mjs` - Next.js config
- ✅ `ecosystem.config.js` - PM2 config
- ✅ `package.json` - dependencies

**Available Design Templates:**
- ✅ `/layouts/flowbite-admin-dashboard/` - Admin panel template (Hugo + Flowbite + Tailwind)
- ✅ `/layouts/tailnews/` - Public news template (HTML + Tailwind)
- ✅ `/layouts/auth/` - Authentication pages

### 📂 Structura Actuală

```
deschide_frontend/
├── app/
│   ├── favicon.ico
│   ├── globals.css      # Minimal global styles only
│   ├── layout.js        # Root layout (needs conversion to TypeScript)
│   └── page.js          # Home page (needs conversion to TypeScript)
├── public/
│   ├── next.svg
│   └── vercel.svg
├── .env.local           # ✅ Created
├── .next/               # Build output
├── node_modules/        # ✅ Installed
├── ecosystem.config.js  # ✅ PM2 config
├── next.config.mjs      # ✅ Configured
├── package.json         # ✅ Dependencies
└── pnpm-lock.yaml       # ✅ Lock file
```

### 📂 Structura Țintă (după Sprint 0)

```
deschide_frontend/
├── app/
│   ├── [locale]/
│   │   ├── (public)/
│   │   │   └── layout.tsx    # Imports app/styles/public.css
│   │   └── admin/
│   │       └── layout.tsx    # Imports app/styles/admin.css
│   ├── styles/              # ← NEW: Separate CSS per zone
│   │   ├── public.css       # TailNews adapted (v3 → v4)
│   │   └── admin.css        # Flowbite styles (v4)
│   ├── globals.css          # Minimal global only
│   └── ...
└── ...
```

### ⚙️ Ce Necesită Configurare

1. **TypeScript Setup** - convertire de la JavaScript la TypeScript
2. **Internationalization** - instalare next-i18n-router + react-intl
3. **API Integration Layer** - fetch wrapper pentru backend (Server Components preferred)
4. **State Management** - instalare Zustand și React Query
5. **Form Handling** - instalare React Hook Form și Zod
6. **UI Components** - instalare Flowbite React components
7. **Authentication Flow** - JWT storage, middleware protection
8. **Adapt Templates** - conversie template-uri HTML → React Components

---

## 🎯 Plan de Dezvoltare - 6 Sprints

### Sprint 0: Foundation & Infrastructure (5-7 zile) ⏳ NEXT

**Obiectiv:** Setup complet TypeScript, i18n (next-i18n-router + react-intl), și structură de bază

#### Preconditions:
- ✅ Frontend rulează pe port 3005
- ✅ Backend API disponibil pe port 8081
- ✅ Environment variables configurate
- ✅ Design templates disponibile în `/layouts/`

#### Tasks:

**1. TypeScript Migration**

```bash
cd /var/www/deschide_news_app/deschide_frontend

# Install TypeScript dependencies
pnpm add -D typescript @types/react @types/node

# Rename files
mv app/layout.js app/layout.tsx
mv app/page.js app/page.tsx
```

Create `tsconfig.json`:
```json
{
  "compilerOptions": {
    "target": "ES2017",
    "lib": ["dom", "dom.iterable", "esnext"],
    "allowJs": true,
    "skipLibCheck": true,
    "strict": true,
    "noEmit": true,
    "esModuleInterop": true,
    "module": "esnext",
    "moduleResolution": "bundler",
    "resolveJsonModule": true,
    "isolatedModules": true,
    "jsx": "preserve",
    "incremental": true,
    "plugins": [{ "name": "next" }],
    "paths": {
      "@/*": ["./*"]
    }
  },
  "include": ["next-env.d.ts", "**/*.ts", "**/*.tsx", ".next/types/**/*.ts"],
  "exclude": ["node_modules"]
}
```

**2. Install Core Dependencies**

```bash
# Internationalization (conform documentației)
pnpm add next-i18n-router react-intl

# API & State Management
pnpm add @tanstack/react-query zustand

# Forms & Validation
pnpm add react-hook-form zod @hookform/resolvers

# UI Components (Flowbite)
pnpm add flowbite flowbite-react

# Icons
pnpm add @heroicons/react

# Date handling
pnpm add date-fns

# Utility
pnpm add clsx tailwind-merge

# Dev dependencies
pnpm add -D @types/react-intl
```

**3. Configure Internationalization (next-i18n-router + react-intl)**

Conform [tutorial React-Intl](https://i18nexus.com/tutorials/nextjs/react-intl):

**Step 1:** Create `i18nConfig.js` (root):
```javascript
const i18nConfig = {
  locales: ['ro', 'en', 'ru'],
  defaultLocale: 'ro'
};

module.exports = i18nConfig;
```

**Step 2:** Create `middleware.ts`:
```typescript
import { i18nRouter } from 'next-i18n-router';
import i18nConfig from './i18nConfig';

export function middleware(request: Request) {
  return i18nRouter(request, i18nConfig);
}

// Applies this middleware only to files in the app directory
export const config = {
  matcher: '/((?!api|static|.*\\..*|_next).*)'
};
```

**Step 3:** Update `next.config.mjs`:
```javascript
/** @type {import('next').NextConfig} */
const nextConfig = {
  images: {
    remotePatterns: [
      {
        protocol: 'http',
        hostname: 'api.deschide.local',
        port: '',
        pathname: '/media/**',
      },
      {
        protocol: 'http',
        hostname: '127.0.0.1',
        port: '8081',
        pathname: '/media/**',
      },
    ],
  },
  turbopack: {},
};

export default nextConfig;
```

**Step 4:** Create helper for Server Components `app/intl.ts`:
```typescript
import { createIntl, createIntlCache, IntlShape } from 'react-intl';

// Load messages
async function loadMessages(locale: string) {
  const messages = await import(`@/messages/${locale}.json`);
  return messages.default;
}

// Cache for intl instances
const cache = createIntlCache();

// Helper function for Server Components
export async function getIntl(locale: string): Promise<IntlShape> {
  const messages = await loadMessages(locale);

  return createIntl(
    {
      locale,
      messages,
    },
    cache
  );
}
```

**Step 5:** Create Client Provider `app/components/ServerIntlProvider.tsx`:
```typescript
'use client';

import { ReactNode } from 'react';
import { IntlProvider } from 'react-intl';

type ServerIntlProviderProps = {
  messages: Record<string, string>;
  locale: string;
  children: ReactNode;
};

export default function ServerIntlProvider({
  messages,
  locale,
  children,
}: ServerIntlProviderProps) {
  return (
    <IntlProvider messages={messages} locale={locale}>
      {children}
    </IntlProvider>
  );
}
```

**4. Create Directory Structure**

```bash
mkdir -p app/\[locale\]/{(public),admin}
mkdir -p app/styles  # ← Separate CSS files for public/admin
mkdir -p components/{ui,layout,forms,flowbite}
mkdir -p lib/{api,utils,hooks,types,store}
mkdir -p messages
mkdir -p public/images
```

Final structure:
```
app/
├── [locale]/                # Internationalized routes
│   ├── layout.tsx          # Locale-specific layout
│   ├── page.tsx            # Home page
│   ├── (public)/           # Public routes group
│   │   ├── layout.tsx      # Public layout (TailNews + public.css)
│   │   ├── article/
│   │   ├── category/
│   │   ├── author/
│   │   ├── search/
│   │   └── about/
│   └── admin/              # Admin routes (protected)
│       ├── layout.tsx      # Admin layout (Flowbite + admin.css)
│       ├── dashboard/
│       ├── articles/
│       ├── categories/
│       ├── authors/
│       └── users/
├── styles/                  # ← Separate CSS for zones
│   ├── public.css          # Public zone styles (TailNews adapted v3→v4)
│   └── admin.css           # Admin zone styles (Flowbite v4)
├── components/
│   ├── ServerIntlProvider.tsx  # Client provider for react-intl
│   └── ...
├── intl.ts                 # Server Components intl helper
├── globals.css             # Minimal global styles only
└── providers.tsx           # React Query provider

components/
├── ui/                     # Reusable UI components
│   ├── Button.tsx
│   ├── Input.tsx
│   ├── Card.tsx
│   └── ...
├── layout/                 # Layout components (adapted from TailNews)
│   ├── Header.tsx
│   ├── Footer.tsx
│   ├── Sidebar.tsx         # Admin sidebar (from Flowbite)
│   └── LanguageSwitcher.tsx
├── forms/                  # Form components
│   ├── LoginForm.tsx
│   └── ArticleForm.tsx
└── flowbite/              # Flowbite component adaptations
    ├── AdminNav.tsx
    ├── AdminSidebar.tsx
    └── ...

lib/
├── api/                    # API functions (for Server Components & Client)
│   ├── articles.ts
│   ├── categories.ts
│   ├── auth.ts
│   └── users.ts
├── hooks/                  # Custom hooks (Client Components)
│   ├── useAuth.ts
│   └── useArticles.ts
├── store/                  # Zustand stores
│   ├── authStore.ts
│   └── uiStore.ts
├── types/                  # TypeScript types
│   ├── api.ts
│   ├── article.ts
│   └── user.ts
└── utils/                  # Utility functions
    ├── cn.ts
    ├── date.ts
    └── fetch.ts

messages/
├── ro.json                 # Romanian translations
├── en.json                 # English translations
└── ru.json                 # Russian translations
```

**5. Setup React Query Provider**

`app/providers.tsx`:
```typescript
'use client';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ReactNode, useState } from 'react';

export function Providers({ children }: { children: ReactNode }) {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 60 * 1000, // 1 minute
            refetchOnWindowFocus: false,
          },
        },
      })
  );

  return (
    <QueryClientProvider client={queryClient}>
      {children}
    </QueryClientProvider>
  );
}
```

**6. Update Root Layout**

`app/[locale]/layout.tsx`:
```typescript
import ServerIntlProvider from '@/components/ServerIntlProvider';
import { Providers } from '../providers';
import '../globals.css';

// Load messages for the locale
async function loadMessages(locale: string) {
  const messages = await import(`@/messages/${locale}.json`);
  return messages.default;
}

export default async function LocaleLayout({
  children,
  params: { locale }
}: {
  children: React.ReactNode;
  params: { locale: string };
}) {
  const messages = await loadMessages(locale);

  return (
    <html lang={locale}>
      <body>
        <ServerIntlProvider messages={messages} locale={locale}>
          <Providers>
            {children}
          </Providers>
        </ServerIntlProvider>
      </body>
    </html>
  );
}

// Generate static params for all locales
export function generateStaticParams() {
  return [
    { locale: 'ro' },
    { locale: 'en' },
    { locale: 'ru' }
  ];
}
```

**7. Create Translation Files**

`messages/ro.json`:
```json
{
  "common": {
    "home": "Acasă",
    "about": "Despre",
    "contact": "Contact",
    "search": "Caută",
    "loading": "Se încarcă...",
    "error": "A apărut o eroare",
    "login": "Autentificare",
    "logout": "Deconectare",
    "language": "Limba",
    "menu": "Meniu"
  },
  "articles": {
    "title": "Articole",
    "latestArticles": "Ultimele articole",
    "readMore": "Citește mai mult",
    "publishedOn": "Publicat pe",
    "author": "Autor",
    "category": "Categorie",
    "views": "vizualizări",
    "relatedArticles": "Articole similare",
    "gallery": "Galerie foto"
  },
  "auth": {
    "username": "Nume utilizator",
    "password": "Parolă",
    "login": "Autentificare",
    "loginTitle": "Autentificare",
    "loginSubtitle": "Introduceți datele de autentificare",
    "loginError": "Nume utilizator sau parolă incorectă",
    "rememberMe": "Ține-mă minte",
    "forgotPassword": "Ai uitat parola?"
  }
}
```

`messages/en.json`:
```json
{
  "common": {
    "home": "Home",
    "about": "About",
    "contact": "Contact",
    "search": "Search",
    "loading": "Loading...",
    "error": "An error occurred",
    "login": "Login",
    "logout": "Logout",
    "language": "Language",
    "menu": "Menu"
  },
  "articles": {
    "title": "Articles",
    "latestArticles": "Latest articles",
    "readMore": "Read more",
    "publishedOn": "Published on",
    "author": "Author",
    "category": "Category",
    "views": "views",
    "relatedArticles": "Related articles",
    "gallery": "Photo gallery"
  },
  "auth": {
    "username": "Username",
    "password": "Password",
    "login": "Login",
    "loginTitle": "Sign In",
    "loginSubtitle": "Enter your credentials",
    "loginError": "Invalid username or password",
    "rememberMe": "Remember me",
    "forgotPassword": "Forgot password?"
  }
}
```

`messages/ru.json`:
```json
{
  "common": {
    "home": "Главная",
    "about": "О нас",
    "contact": "Контакты",
    "search": "Поиск",
    "loading": "Загрузка...",
    "error": "Произошла ошибка",
    "login": "Войти",
    "logout": "Выйти",
    "language": "Язык",
    "menu": "Меню"
  },
  "articles": {
    "title": "Статьи",
    "latestArticles": "Последние статьи",
    "readMore": "Читать далее",
    "publishedOn": "Опубликовано",
    "author": "Автор",
    "category": "Категория",
    "views": "просмотров",
    "relatedArticles": "Похожие статьи",
    "gallery": "Фотогалерея"
  },
  "auth": {
    "username": "Имя пользователя",
    "password": "Пароль",
    "login": "Войти",
    "loginTitle": "Вход в систему",
    "loginSubtitle": "Введите данные для входа",
    "loginError": "Неверное имя пользователя или пароль",
    "rememberMe": "Запомнить меня",
    "forgotPassword": "Забыли пароль?"
  }
}
```

**8. Create Utility Functions**

`lib/utils/cn.ts`:
```typescript
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}
```

`lib/utils/fetch.ts`:
```typescript
// Server-side fetch wrapper with error handling
export async function fetchAPI<T>(
  endpoint: string,
  options?: RequestInit
): Promise<T> {
  const baseURL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';
  const url = `${baseURL}${endpoint}`;

  try {
    const response = await fetch(url, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        ...options?.headers,
      },
    });

    if (!response.ok) {
      throw new Error(`API error: ${response.status}`);
    }

    return response.json();
  } catch (error) {
    console.error('Fetch error:', error);
    throw error;
  }
}
```

**9. Configure Tailwind CSS 4 with Separate Styles**

**IMPORTANT:** Vom avea stiluri complet separate pentru zona publică și admin.

**Step 1:** Update `tailwind.config.js` (root):
```javascript
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './pages/**/*.{js,ts,jsx,tsx,mdx}',
    './components/**/*.{js,ts,jsx,tsx,mdx}',
    './app/**/*.{js,ts,jsx,tsx,mdx}',
    './node_modules/flowbite-react/**/*.{js,jsx,ts,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: '#93c5fd',
          400: '#60a5fa',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
          800: '#1e40af',
          900: '#1e3a8a',
        },
      },
    },
  },
  plugins: [
    require('flowbite/plugin')
  ],
};
```

**Step 2:** Create separate CSS files for public and admin zones:

`app/styles/public.css` (pentru zona publică):
```css
/* Tailwind CSS 4 imports */
@import "tailwindcss";

/* TailNews adapted styles - migrated from v3 to v4 */
/* Note: TailNews uses Tailwind v3, we'll adapt to v4 syntax */

/* Custom public zone styles */
.article-card {
  /* Adapted from TailNews template */
}

.news-grid {
  /* Custom grid for news layout */
}

/* Tailwind v3 → v4 compatibility adjustments */
/* Example: shadow-sm → shadow-xs (if needed) */
```

`app/styles/admin.css` (pentru zona admin):
```css
/* Tailwind CSS 4 imports */
@import "tailwindcss";

/* Flowbite component styles */
/* Admin-specific styles only */

.admin-table {
  /* Custom admin table styling */
}

.admin-sidebar {
  /* Flowbite sidebar customization */
}
```

**Step 3:** Update `app/globals.css` (minimal global styles):
```css
/* Global styles only - no zone-specific styles here */
@import "tailwindcss";

/* CSS Variables for both zones */
:root {
  --primary-color: #3b82f6;
  --secondary-color: #1e40af;
}

/* Minimal global resets */
* {
  box-sizing: border-box;
  padding: 0;
  margin: 0;
}
```

**Step 4:** Import zone-specific CSS in layouts:

Update `app/[locale]/(public)/layout.tsx`:
```typescript
import { Header } from '@/components/layout/Header';
import { Footer } from '@/components/layout/Footer';
import '@/app/styles/public.css'; // ← Public zone styles only

export default function PublicLayout({ children }) {
  return (
    <>
      <Header />
      <main className="min-h-screen bg-gray-50 dark:bg-gray-900">
        {children}
      </main>
      <Footer />
    </>
  );
}
```

Update `app/[locale]/admin/layout.tsx`:
```typescript
import { Header } from '@/components/layout/Header';
import { AdminSidebar } from '@/components/layout/AdminSidebar';
import '@/app/styles/admin.css'; // ← Admin zone styles only

export default function AdminLayout({ children }) {
  return (
    <>
      <Header />
      <AdminSidebar />
      <main className="p-4 ml-64 mt-16 min-h-screen bg-gray-50 dark:bg-gray-900">
        {children}
      </main>
    </>
  );
}
```

**Step 5:** Tailwind v3 → v4 Migration Notes for TailNews:

Conform [Upgrade Guide](https://tailwindcss.com/docs/upgrade-guide), principale schimbări:

```css
/* OLD (Tailwind v3) → NEW (Tailwind v4) */

/* Import syntax */
@tailwind base;        → @import "tailwindcss";
@tailwind components;  → (merged into single import)
@tailwind utilities;   → (merged into single import)

/* Utility class renames */
shadow-sm     → shadow-xs
ring (3px)    → ring-3
outline-none  → outline-hidden
rounded-sm    → rounded-xs

/* Opacity modifiers (already supported in v3, ensure compatibility) */
bg-opacity-50  → bg-black/50
text-opacity-75 → text-black/75

/* CSS variables in arbitrary values */
bg-[--brand-color]  → bg-(--brand-color)

/* Default value changes to be aware of */
/* Border color: gray-200 → currentColor */
/* Ring width: 3px → 1px */
/* Ring color: blue-500 → currentColor */
/* Placeholder color: gray-400 → currentColor at 50% opacity */
```

**Automated Migration Tool:**
```bash
# Run automated upgrade tool (optional)
npx @tailwindcss/upgrade

# Manual review recommended for TailNews template adaptation
```

**Deliverables:**
- ✅ TypeScript configurat complet
- ✅ next-i18n-router + react-intl instalat și configurat
- ✅ Middleware pentru locale routing
- ✅ ServerIntlProvider pentru Client Components
- ✅ getIntl() helper pentru Server Components
- ✅ Structură de directoare creată (cu `app/styles/`)
- ✅ React Query provider configurat
- ✅ Translation files create pentru 3 limbi (ro, en, ru)
- ✅ Utility functions create
- ✅ **Tailwind CSS 4 configurat cu stiluri separate:**
  - ✅ `app/styles/public.css` - Zona publică (TailNews v3→v4)
  - ✅ `app/styles/admin.css` - Zona admin (Flowbite v4)
  - ✅ `app/globals.css` - Minimal global styles
- ✅ Flowbite plugin configurat pentru zona admin
- ✅ generateStaticParams pentru toate locale-urile
- ✅ Tailwind v3→v4 migration notes documentate

**Testing:**
```bash
cd /var/www/deschide_news_app/deschide_frontend

# Start dev server
pnpm dev

# Verify TypeScript compilation
pnpm build

# Test routing cu locale
# http://localhost:3005      → redirect to /ro (default)
# http://localhost:3005/ro
# http://localhost:3005/en
# http://localhost:3005/ru

# Test intl în Server Component
# Verify messages are loaded correctly
# Test language switching
```

---

### Sprint 1: Authentication & Base Layout (7-10 zile)

**Obiectiv:** Sistem complet de autentificare și layout-uri de bază (public + admin)

**Note:**
- Backend trebuie să aibă endpoints `/api/login` și `/api/token/refresh` funcționale
- Vom adapta template-urile din `/layouts/flowbite-admin-dashboard/` și `/layouts/tailnews/`

#### Tasks:

**1. Create TypeScript Types**

`lib/types/user.ts`:
```typescript
export interface User {
  id: number;
  username: string;
  email: string;
  firstName: string;
  lastName: string;
  roles: string[]; // ROLE_EDITOR, ROLE_ADMIN
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface LoginCredentials {
  username: string;
  password: string;
}

export interface LoginResponse {
  token: string;
  refresh_token: string;
}

export interface AuthUser {
  user: User;
  accessToken: string;
  refreshToken: string;
}
```

**2. Create Auth Store (Zustand)**

`lib/store/authStore.ts`:
```typescript
import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import { User } from '@/lib/types/user';

interface AuthState {
  user: User | null;
  accessToken: string | null;
  refreshToken: string | null;
  isAuthenticated: boolean;
  setAuth: (user: User, accessToken: string, refreshToken: string) => void;
  clearAuth: () => void;
  updateUser: (user: Partial<User>) => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      accessToken: null,
      refreshToken: null,
      isAuthenticated: false,
      setAuth: (user, accessToken, refreshToken) =>
        set({
          user,
          accessToken,
          refreshToken,
          isAuthenticated: true
        }),
      clearAuth: () =>
        set({
          user: null,
          accessToken: null,
          refreshToken: null,
          isAuthenticated: false
        }),
      updateUser: (userData) =>
        set((state) => ({
          user: state.user ? { ...state.user, ...userData } : null,
        })),
    }),
    {
      name: 'auth-storage',
      storage: createJSONStorage(() => localStorage),
    }
  )
);
```

**3. Create Auth API Functions**

`lib/api/auth.ts`:
```typescript
import { fetchAPI } from '@/lib/utils/fetch';
import { LoginCredentials, LoginResponse, User } from '@/lib/types/user';

export const authApi = {
  // Login - returns tokens
  login: async (credentials: LoginCredentials): Promise<LoginResponse> => {
    return fetchAPI<LoginResponse>('/api/login', {
      method: 'POST',
      body: JSON.stringify(credentials),
    });
  },

  // Refresh token
  refreshToken: async (refreshToken: string): Promise<LoginResponse> => {
    return fetchAPI<LoginResponse>('/api/token/refresh', {
      method: 'POST',
      body: JSON.stringify({ refresh_token: refreshToken }),
    });
  },

  // Get current user info
  getCurrentUser: async (token: string): Promise<User> => {
    return fetchAPI<User>('/api/me', {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    });
  },

  // Logout (optional, if backend has logout endpoint)
  logout: async (token: string): Promise<void> => {
    try {
      await fetchAPI('/api/logout', {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });
    } catch (error) {
      // Silent fail - clear local auth anyway
      console.error('Logout error:', error);
    }
  },
};
```

**4. Create Protected Route Middleware**

Conform [Next.js Auth documentation](https://nextjs.org/docs/app/guides/authentication):

Update `middleware.ts`:
```typescript
import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';
import { i18nRouter } from 'next-i18n-router';
import i18nConfig from './i18nConfig';

export function middleware(request: NextRequest) {
  // First handle i18n routing
  const i18nResponse = i18nRouter(request, i18nConfig);

  // Check if route is protected (admin routes)
  const pathname = request.nextUrl.pathname;
  const isAdminRoute = pathname.includes('/admin');

  if (isAdminRoute) {
    // Check for auth token in cookies or header
    const token = request.cookies.get('access_token')?.value;

    if (!token) {
      // Redirect to login page, preserving locale
      const locale = pathname.split('/')[1]; // Extract locale from path
      const loginUrl = new URL(`/${locale}/login`, request.url);
      loginUrl.searchParams.set('redirect', pathname);
      return NextResponse.redirect(loginUrl);
    }

    // TODO: Optional - verify token validity (requires backend call)
    // For now, optimistic check - trust token exists
  }

  return i18nResponse;
}

export const config = {
  matcher: '/((?!api|static|.*\\..*|_next).*)'
};
```

**5. Create useAuth Hook**

`lib/hooks/useAuth.ts`:
```typescript
'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useRouter, usePathname } from 'next/navigation';
import { useAuthStore } from '@/lib/store/authStore';
import { authApi } from '@/lib/api/auth';
import { LoginCredentials } from '@/lib/types/user';

export function useAuth() {
  const router = useRouter();
  const pathname = usePathname();
  const queryClient = useQueryClient();
  const locale = pathname.split('/')[1]; // Extract locale from pathname

  const {
    user,
    accessToken,
    refreshToken,
    isAuthenticated,
    setAuth,
    clearAuth
  } = useAuthStore();

  // Login mutation
  const loginMutation = useMutation({
    mutationFn: authApi.login,
    onSuccess: async (data) => {
      // Get user info with the token
      try {
        const userInfo = await authApi.getCurrentUser(data.token);
        setAuth(userInfo, data.token, data.refresh_token);

        // Store tokens in cookies for middleware
        document.cookie = `access_token=${data.token}; path=/; max-age=604800`; // 7 days
        document.cookie = `refresh_token=${data.refresh_token}; path=/; max-age=2592000`; // 30 days

        // Redirect to admin dashboard
        router.push(`/${locale}/admin/dashboard`);
      } catch (error) {
        console.error('Failed to get user info:', error);
        clearAuth();
      }
    },
  });

  // Current user query (refetch on mount if authenticated)
  const { data: currentUser, isLoading: isLoadingUser } = useQuery({
    queryKey: ['currentUser'],
    queryFn: () => authApi.getCurrentUser(accessToken!),
    enabled: isAuthenticated && !!accessToken,
    retry: 1,
    staleTime: 5 * 60 * 1000, // 5 minutes
  });

  // Logout function
  const logout = async () => {
    if (accessToken) {
      await authApi.logout(accessToken);
    }

    clearAuth();

    // Clear cookies
    document.cookie = 'access_token=; path=/; max-age=0';
    document.cookie = 'refresh_token=; path=/; max-age=0';

    // Clear React Query cache
    queryClient.clear();

    router.push(`/${locale}/login`);
  };

  return {
    user: currentUser || user,
    isAuthenticated,
    isLoading: loginMutation.isPending || isLoadingUser,
    login: loginMutation.mutate,
    logout,
    error: loginMutation.error,
  };
}
```

**6. Create Login Form**

`components/forms/LoginForm.tsx`:
```typescript
'use client';

import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useAuth } from '@/lib/hooks/useAuth';
import { useIntl } from 'react-intl';

const loginSchema = z.object({
  username: z.string().min(3, 'Username must be at least 3 characters'),
  password: z.string().min(6, 'Password must be at least 6 characters'),
});

type LoginFormData = z.infer<typeof loginSchema>;

export function LoginForm() {
  const { formatMessage } = useIntl();
  const { login, isLoading, error } = useAuth();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
  });

  const onSubmit = (data: LoginFormData) => {
    login(data);
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
      <div>
        <label
          htmlFor="username"
          className="block mb-2 text-sm font-medium text-gray-900 dark:text-white"
        >
          {formatMessage({ id: 'auth.username' })}
        </label>
        <input
          id="username"
          type="text"
          {...register('username')}
          className="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5"
          placeholder="admin"
        />
        {errors.username && (
          <p className="mt-2 text-sm text-red-600">{errors.username.message}</p>
        )}
      </div>

      <div>
        <label
          htmlFor="password"
          className="block mb-2 text-sm font-medium text-gray-900 dark:text-white"
        >
          {formatMessage({ id: 'auth.password' })}
        </label>
        <input
          id="password"
          type="password"
          {...register('password')}
          className="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5"
          placeholder="••••••••"
        />
        {errors.password && (
          <p className="mt-2 text-sm text-red-600">{errors.password.message}</p>
        )}
      </div>

      <div className="flex items-center justify-between">
        <div className="flex items-start">
          <div className="flex items-center h-5">
            <input
              id="remember"
              type="checkbox"
              className="w-4 h-4 border border-gray-300 rounded bg-gray-50 focus:ring-3 focus:ring-primary-300"
            />
          </div>
          <div className="ml-3 text-sm">
            <label htmlFor="remember" className="text-gray-500 dark:text-gray-300">
              {formatMessage({ id: 'auth.rememberMe' })}
            </label>
          </div>
        </div>
        <a href="#" className="text-sm font-medium text-primary-600 hover:underline">
          {formatMessage({ id: 'auth.forgotPassword' })}
        </a>
      </div>

      {error && (
        <div className="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50">
          {formatMessage({ id: 'auth.loginError' })}
        </div>
      )}

      <button
        type="submit"
        disabled={isLoading}
        className="w-full text-white bg-primary-600 hover:bg-primary-700 focus:ring-4 focus:outline-none focus:ring-primary-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center disabled:opacity-50 disabled:cursor-not-allowed"
      >
        {isLoading
          ? formatMessage({ id: 'common.loading' })
          : formatMessage({ id: 'auth.login' })
        }
      </button>
    </form>
  );
}
```

**7. Create Login Page**

`app/[locale]/(public)/login/page.tsx`:
```typescript
import { LoginForm } from '@/components/forms/LoginForm';
import { getIntl } from '@/app/intl';

export default async function LoginPage({
  params: { locale }
}: {
  params: { locale: string };
}) {
  const intl = await getIntl(locale);

  return (
    <section className="bg-gray-50 dark:bg-gray-900">
      <div className="flex flex-col items-center justify-center px-6 py-8 mx-auto md:h-screen lg:py-0">
        <a href="#" className="flex items-center mb-6 text-2xl font-semibold text-gray-900 dark:text-white">
          <img className="w-8 h-8 mr-2" src="/images/logo.svg" alt="logo" />
          Deschide News
        </a>
        <div className="w-full bg-white rounded-lg shadow dark:border md:mt-0 sm:max-w-md xl:p-0 dark:bg-gray-800 dark:border-gray-700">
          <div className="p-6 space-y-4 md:space-y-6 sm:p-8">
            <h1 className="text-xl font-bold leading-tight tracking-tight text-gray-900 md:text-2xl dark:text-white">
              {intl.formatMessage({ id: 'auth.loginTitle' })}
            </h1>
            <LoginForm />
          </div>
        </div>
      </div>
    </section>
  );
}
```

**8. Adapt TailNews Header Component**

`components/layout/Header.tsx`:
```typescript
'use client';

import Link from 'next/link';
import { useIntl } from 'react-intl';
import { useAuth } from '@/lib/hooks/useAuth';
import { usePathname } from 'next/navigation';
import { LanguageSwitcher } from './LanguageSwitcher';

export function Header() {
  const { formatMessage } = useIntl();
  const { isAuthenticated, user, logout } = useAuth();
  const pathname = usePathname();
  const locale = pathname.split('/')[1];

  return (
    <header className="sticky top-0 z-50 bg-white border-b border-gray-200 dark:bg-gray-800 dark:border-gray-700">
      <div className="container mx-auto px-4">
        <div className="flex items-center justify-between h-16">
          {/* Logo */}
          <Link href={`/${locale}`} className="flex items-center">
            <span className="text-2xl font-bold text-gray-900 dark:text-white">
              Deschide News
            </span>
          </Link>

          {/* Navigation */}
          <nav className="hidden md:flex items-center space-x-8">
            <Link
              href={`/${locale}`}
              className="text-gray-700 hover:text-primary-600 dark:text-gray-300"
            >
              {formatMessage({ id: 'common.home' })}
            </Link>
            <Link
              href={`/${locale}/about`}
              className="text-gray-700 hover:text-primary-600 dark:text-gray-300"
            >
              {formatMessage({ id: 'common.about' })}
            </Link>
            <Link
              href={`/${locale}/contact`}
              className="text-gray-700 hover:text-primary-600 dark:text-gray-300"
            >
              {formatMessage({ id: 'common.contact' })}
            </Link>
          </nav>

          {/* Right side */}
          <div className="flex items-center space-x-4">
            <LanguageSwitcher />

            {isAuthenticated ? (
              <>
                <span className="text-sm text-gray-600 dark:text-gray-400">
                  {user?.firstName} {user?.lastName}
                </span>
                <Link
                  href={`/${locale}/admin/dashboard`}
                  className="text-sm text-primary-600 hover:text-primary-700"
                >
                  Admin
                </Link>
                <button
                  onClick={logout}
                  className="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400"
                >
                  {formatMessage({ id: 'common.logout' })}
                </button>
              </>
            ) : (
              <Link
                href={`/${locale}/login`}
                className="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700"
              >
                {formatMessage({ id: 'common.login' })}
              </Link>
            )}
          </div>
        </div>
      </div>
    </header>
  );
}
```

**9. Create Language Switcher**

`components/layout/LanguageSwitcher.tsx`:
```typescript
'use client';

import { usePathname, useRouter } from 'next/navigation';
import { useState } from 'react';

const languages = [
  { code: 'ro', name: 'Română', flag: '🇷🇴' },
  { code: 'en', name: 'English', flag: '🇬🇧' },
  { code: 'ru', name: 'Русский', flag: '🇷🇺' },
];

export function LanguageSwitcher() {
  const pathname = usePathname();
  const router = useRouter();
  const currentLocale = pathname.split('/')[1];
  const [isOpen, setIsOpen] = useState(false);

  const switchLanguage = (newLocale: string) => {
    // Replace locale in pathname
    const newPathname = pathname.replace(`/${currentLocale}`, `/${newLocale}`);

    // Set cookie for persistence
    document.cookie = `NEXT_LOCALE=${newLocale}; path=/; max-age=31536000`; // 1 year

    router.push(newPathname);
    setIsOpen(false);
  };

  const currentLang = languages.find(lang => lang.code === currentLocale) || languages[0];

  return (
    <div className="relative">
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center space-x-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
      >
        <span>{currentLang.flag}</span>
        <span>{currentLang.code.toUpperCase()}</span>
      </button>

      {isOpen && (
        <div className="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
          <ul className="py-2">
            {languages.map((lang) => (
              <li key={lang.code}>
                <button
                  onClick={() => switchLanguage(lang.code)}
                  className={`flex items-center w-full px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 ${
                    lang.code === currentLocale ? 'text-primary-600 font-medium' : 'text-gray-700 dark:text-gray-300'
                  }`}
                >
                  <span className="mr-3">{lang.flag}</span>
                  <span>{lang.name}</span>
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
```

**10. Create Footer Component**

`components/layout/Footer.tsx`:
```typescript
'use client';

import { useIntl } from 'react-intl';
import Link from 'next/link';
import { usePathname } from 'next/navigation';

export function Footer() {
  const { formatMessage } = useIntl();
  const pathname = usePathname();
  const locale = pathname.split('/')[1];

  return (
    <footer className="bg-gray-900 text-white">
      <div className="container mx-auto px-4 py-12">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
          {/* Brand */}
          <div>
            <h3 className="text-xl font-bold mb-4">Deschide News</h3>
            <p className="text-gray-400 text-sm">
              {formatMessage({ id: 'footer.tagline' })}
            </p>
          </div>

          {/* Links */}
          <div>
            <h4 className="font-semibold mb-4">{formatMessage({ id: 'footer.quickLinks' })}</h4>
            <ul className="space-y-2 text-sm">
              <li>
                <Link href={`/${locale}`} className="text-gray-400 hover:text-white">
                  {formatMessage({ id: 'common.home' })}
                </Link>
              </li>
              <li>
                <Link href={`/${locale}/about`} className="text-gray-400 hover:text-white">
                  {formatMessage({ id: 'common.about' })}
                </Link>
              </li>
              <li>
                <Link href={`/${locale}/contact`} className="text-gray-400 hover:text-white">
                  {formatMessage({ id: 'common.contact' })}
                </Link>
              </li>
            </ul>
          </div>

          {/* Categories */}
          <div>
            <h4 className="font-semibold mb-4">{formatMessage({ id: 'footer.categories' })}</h4>
            <ul className="space-y-2 text-sm">
              <li>
                <Link href={`/${locale}/category/politica`} className="text-gray-400 hover:text-white">
                  Politică
                </Link>
              </li>
              <li>
                <Link href={`/${locale}/category/economie`} className="text-gray-400 hover:text-white">
                  Economie
                </Link>
              </li>
            </ul>
          </div>

          {/* Social */}
          <div>
            <h4 className="font-semibold mb-4">{formatMessage({ id: 'footer.followUs' })}</h4>
            <div className="flex space-x-4">
              {/* Add social icons */}
            </div>
          </div>
        </div>

        <div className="border-t border-gray-800 mt-8 pt-8 text-center text-sm text-gray-400">
          <p>© {new Date().getFullYear()} Deschide News. All rights reserved.</p>
        </div>
      </div>
    </footer>
  );
}
```

**11. Create Public Layout**

`app/[locale]/(public)/layout.tsx`:
```typescript
import { Header } from '@/components/layout/Header';
import { Footer } from '@/components/layout/Footer';

export default function PublicLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <>
      <Header />
      <main className="min-h-screen bg-gray-50 dark:bg-gray-900">
        {children}
      </main>
      <Footer />
    </>
  );
}
```

**12. Create Admin Sidebar (adapted from Flowbite template)**

`components/layout/AdminSidebar.tsx`:
```typescript
'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useIntl } from 'react-intl';
import {
  HomeIcon,
  DocumentTextIcon,
  FolderIcon,
  UserGroupIcon,
  Cog6ToothIcon,
} from '@heroicons/react/24/outline';

export function AdminSidebar() {
  const { formatMessage } = useIntl();
  const pathname = usePathname();
  const locale = pathname.split('/')[1];

  const navigation = [
    {
      name: 'Dashboard',
      href: `/${locale}/admin/dashboard`,
      icon: HomeIcon,
    },
    {
      name: 'Articles',
      href: `/${locale}/admin/articles`,
      icon: DocumentTextIcon,
    },
    {
      name: 'Categories',
      href: `/${locale}/admin/categories`,
      icon: FolderIcon,
    },
    {
      name: 'Users',
      href: `/${locale}/admin/users`,
      icon: UserGroupIcon,
    },
    {
      name: 'Settings',
      href: `/${locale}/admin/settings`,
      icon: Cog6ToothIcon,
    },
  ];

  return (
    <aside className="fixed top-0 left-0 z-40 w-64 h-screen pt-16 bg-white border-r border-gray-200 dark:bg-gray-800 dark:border-gray-700">
      <div className="h-full px-3 pb-4 overflow-y-auto">
        <ul className="space-y-2 font-medium">
          {navigation.map((item) => {
            const isActive = pathname === item.href;
            const Icon = item.icon;

            return (
              <li key={item.name}>
                <Link
                  href={item.href}
                  className={`flex items-center p-2 rounded-lg group ${
                    isActive
                      ? 'bg-primary-100 text-primary-600 dark:bg-primary-900 dark:text-primary-400'
                      : 'text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700'
                  }`}
                >
                  <Icon className="w-5 h-5" />
                  <span className="ml-3">{item.name}</span>
                </Link>
              </li>
            );
          })}
        </ul>
      </div>
    </aside>
  );
}
```

**13. Create Admin Layout**

`app/[locale]/admin/layout.tsx`:
```typescript
import { Header } from '@/components/layout/Header';
import { AdminSidebar } from '@/components/layout/AdminSidebar';

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <>
      <Header />
      <AdminSidebar />
      <main className="p-4 ml-64 mt-16 min-h-screen bg-gray-50 dark:bg-gray-900">
        {children}
      </main>
    </>
  );
}
```

**14. Create Dashboard Page (placeholder)**

`app/[locale]/admin/dashboard/page.tsx`:
```typescript
import { getIntl } from '@/app/intl';

export default async function DashboardPage({
  params: { locale }
}: {
  params: { locale: string };
}) {
  const intl = await getIntl(locale);

  return (
    <div>
      <h1 className="text-3xl font-bold text-gray-900 dark:text-white mb-8">
        {intl.formatMessage({ id: 'admin.dashboard' })}
      </h1>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        {/* Stats cards */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div className="text-sm font-medium text-gray-600 dark:text-gray-400">
            Total Articles
          </div>
          <div className="text-3xl font-bold text-gray-900 dark:text-white mt-2">
            0
          </div>
        </div>

        {/* More stats cards... */}
      </div>
    </div>
  );
}
```

**Deliverables:**
- ✅ TypeScript types pentru User și Auth
- ✅ Zustand auth store cu persist (localStorage)
- ✅ Auth API functions (login, refresh, getCurrentUser, logout)
- ✅ Middleware pentru protected routes (admin)
- ✅ useAuth hook cu React Query
- ✅ LoginForm cu validation (React Hook Form + Zod)
- ✅ Login page styling (adapted from auth templates)
- ✅ Header component cu auth state și language switcher
- ✅ Footer component
- ✅ LanguageSwitcher cu dropdown și cookie persistence
- ✅ AdminSidebar adapted din Flowbite template
- ✅ Public layout (Header + Footer)
- ✅ Admin layout (Header + Sidebar)
- ✅ Dashboard placeholder page
- ✅ Cookie-based token storage pentru middleware
- ✅ Translation files actualizate (auth, admin, footer)

**Testing:**
```bash
# Test authentication flow
1. Navigate to http://localhost:3005/ro/admin/dashboard
2. Should redirect to /ro/login (middleware protection)
3. Enter credentials (username: admin, password: from backend)
4. Verify redirect to /ro/admin/dashboard after successful login
5. Verify Header shows user name and logout button
6. Test admin sidebar navigation
7. Test logout functionality
8. Test language switching (cookies should persist selection)

# Test all locales
http://localhost:3005/ro/login
http://localhost:3005/en/login
http://localhost:3005/ru/login

# Test middleware protection
# Try accessing admin routes without login
# Verify redirect to login with ?redirect parameter
```

---

### Sprint 2: Articles Public Pages (10-14 zile)

**Obiectiv:** Pagini publice pentru vizualizare articole - adapted din TailNews template

**Note:**
- Vom adapta design-ul din `/layouts/tailnews/` template (Tailwind v3 → v4)
- Toate stilurile publice vor fi în `app/styles/public.css`
- Components vor folosi doar clase Tailwind v4

#### Tasks Principale:

1. **Create Article Types** - TypeScript interfaces
2. **Server Components Data Fetching** - folosind fetch API (conform Next.js docs)
3. **ArticleCard Component** - adapted din TailNews
4. **Home Page** - layout și grid adapted din TailNews index.html
5. **Article Detail Page** - adapted din TailNews single.html
6. **Category Page** - adapted din TailNews category.html
7. **Author Page** - adapted din TailNews author.html
8. **Search Page** - adapted din TailNews search.html
9. **Image Optimization** - Next.js Image cu remote patterns
10. **Related Articles Component**
11. **Pagination Component**
12. **SEO Metadata** - generateMetadata() pentru toate paginile

**Pattern Data Fetching (Server Components):**
```typescript
// Server Component - direct fetch
export default async function ArticlePage({ params }: { params: { slug: string } }) {
  const article = await fetch(`${API_URL}/api/articles/${params.slug}`, {
    next: { revalidate: 60 } // ISR - revalidate every 60 seconds
  }).then(res => res.json());

  return (
    <article>
      <h1>{article.title}</h1>
      {/* ... */}
    </article>
  );
}
```

---

### Sprint 3: Admin Panel - Articles CRUD (14-21 zile)

**Obiectiv:** Panel admin complet pentru managementul articolelor - adapted din Flowbite Admin Dashboard

**Note:**
- Toate stilurile admin vor fi în `app/styles/admin.css`
- Flowbite components cu Tailwind v4
- Layout-uri adaptate din `/layouts/flowbite-admin-dashboard/`

**Tasks Principale:**
1. Articles listing page (table din Flowbite)
2. Article create form (rich text editor)
3. Article edit form
4. Image upload component
5. Category și Author selection
6. Translation management (tabs pentru ro/en/ru)
7. Status workflow (draft → published)
8. Preview functionality
9. Bulk actions

**Rich Text Editor Options:**
- TipTap (recommended)
- Lexical
- Quill

---

### Sprint 4: Admin Panel - Categories & Users (7-10 zile)

**Obiectiv:** CRUD pentru categorii, autori și users

---

### Sprint 5: Search & Real-time Features (7-10 zile)

**Obiectiv:** Elasticsearch search și Mercure integration

---

### Sprint 6: Polish & Optimization (7-10 zile)

**Obiectiv:** SEO, performance, testing

---

## 📊 Timeline Estimat

| Sprint | Durata | Focus | Status |
|--------|--------|-------|--------|
| Sprint 0 | 5-7 zile | Foundation (TypeScript, i18n, structure) | ⏳ Next |
| Sprint 1 | 7-10 zile | Authentication & Layouts | ⬜ Pending |
| Sprint 2 | 10-14 zile | Articles Public Pages | ⬜ Pending |
| Sprint 3 | 14-21 zile | Admin - Articles CRUD | ⬜ Pending |
| Sprint 4 | 7-10 zile | Admin - Categories & Users | ⬜ Pending |
| Sprint 5 | 7-10 zile | Search & Real-time | ⬜ Pending |
| Sprint 6 | 7-10 zile | Polish & Optimization | ⬜ Pending |

**Total estimat:** 57-82 zile (~3-4 luni)

---

## 📝 Notes Importante

### Design Templates Usage & Styling Strategy

**TailNews Template** (`/layouts/tailnews/`) - **Tailwind v3**:
- `index.html` → Home page grid layout
- `single.html` → Article detail page
- `category.html` → Category listing
- `author.html` → Author page
- `search.html` → Search results
- **Strategie:** HTML + Tailwind v3 → React Server Components + Tailwind v4
- **Stiluri:** Toate în `app/styles/public.css` (DOAR zona publică)
- **Migrare:** Folosim [Tailwind Upgrade Guide](https://tailwindcss.com/docs/upgrade-guide)

**Flowbite Admin** (`/layouts/flowbite-admin-dashboard/`) - **Tailwind v4**:
- Sidebar navigation
- Tables cu sorting/filtering
- Forms cu validation styling
- Modal components
- Dashboard widgets
- **Stiluri:** Toate în `app/styles/admin.css` (DOAR zona admin)
- **Plugin:** Flowbite plugin configurat pentru zona admin

**Izolare CSS:**
```
app/
├── styles/
│   ├── public.css   ← Folosit DOAR în app/[locale]/(public)/layout.tsx
│   └── admin.css    ← Folosit DOAR în app/[locale]/admin/layout.tsx
├── globals.css      ← Minimal global styles (variabile, resets)
```

**IMPORTANT:** Fiecare zonă are stilurile sale separate pentru a evita conflictele și pentru a permite customizări independente.

### Data Fetching Strategy

Conform [Next.js Data Fetching docs](https://nextjs.org/docs/app/getting-started/fetching-data):

**Server Components (preferred):**
```typescript
// Direct fetch în Server Component
async function getData() {
  const res = await fetch('https://api.example.com/...', {
    next: { revalidate: 3600 } // Cache for 1 hour
  });
  return res.json();
}

export default async function Page() {
  const data = await getData();
  return <main>{/* ... */}</main>;
}
```

**Client Components (când e necesar):**
```typescript
'use client';
import { useQuery } from '@tanstack/react-query';

export function ClientComponent() {
  const { data } = useQuery({
    queryKey: ['articles'],
    queryFn: () => fetch('/api/articles').then(res => res.json())
  });

  return <div>{/* ... */}</div>;
}
```

### Authentication Pattern

Conform [Next.js Authentication docs](https://nextjs.org/docs/app/guides/authentication):

- **Middleware**: Optimistic checks (redirect fără backend call)
- **Server Components**: Session verification via Data Access Layer
- **Client Components**: useAuth hook pentru UI state

### Environment Variables

Verifică `.env.local`:
```env
NODE_ENV=development
PORT=3005
NEXT_PUBLIC_API_URL=http://127.0.0.1:8081
NEXT_PUBLIC_MERCURE_URL=http://localhost:3000/.well-known/mercure
NEXT_PUBLIC_APP_NAME="Deschide News"
NEXT_PUBLIC_DEFAULT_LOCALE=ro
```

### Code Quality

```bash
# Install ESLint + Prettier
pnpm add -D prettier eslint-config-prettier

# Create .prettierrc
{
  "semi": true,
  "trailingComma": "es5",
  "singleQuote": true,
  "printWidth": 100,
  "tabWidth": 2
}

# Run linting
pnpm lint
```

---

---

## 🎨 Tailwind CSS 4 - Migration Strategy

### Context

- **Frontend folosește:** Tailwind CSS 4
- **TailNews template folosește:** Tailwind CSS 3
- **Flowbite Admin Dashboard:** Compatible cu Tailwind CSS 4

### Key Changes (v3 → v4)

Conform [Tailwind CSS Upgrade Guide](https://tailwindcss.com/docs/upgrade-guide):

#### 1. Import Syntax Change
```css
/* OLD (v3) */
@tailwind base;
@tailwind components;
@tailwind utilities;

/* NEW (v4) - Single import */
@import "tailwindcss";
```

#### 2. Utility Class Renames
| Old (v3) | New (v4) | Usage |
|----------|----------|-------|
| `shadow-sm` | `shadow-xs` | Small shadows |
| `ring` (3px) | `ring-3` | Ring width |
| `outline-none` | `outline-hidden` | Hide outline |
| `rounded-sm` | `rounded-xs` | Small border radius |

#### 3. Default Value Changes
- **Border color:** `gray-200` → `currentColor`
- **Ring width:** `3px` → `1px`
- **Ring color:** `blue-500` → `currentColor`
- **Placeholder:** `gray-400` → `currentColor` at 50% opacity

#### 4. CSS Variables Syntax
```html
<!-- OLD (v3) -->
<div class="bg-[--brand-color]"></div>

<!-- NEW (v4) -->
<div class="bg-(--brand-color)"></div>
```

#### 5. Opacity Modifiers (Already Compatible)
```html
<!-- OLD syntax (still works, but deprecated) -->
<div class="bg-black bg-opacity-50"></div>

<!-- NEW syntax (preferred in v4) -->
<div class="bg-black/50"></div>
```

### Migration Workflow

**Step 1:** Copiază stilurile din TailNews template
```bash
# Exemple de fișiere din /layouts/tailnews/
# - style.css
# - assets/css/...
```

**Step 2:** Adaptează în `app/styles/public.css`
```css
/* app/styles/public.css */
@import "tailwindcss";

/* Adapted from TailNews - converted v3 → v4 */

/* Example: Article card from TailNews */
.article-card {
  /* OLD: @apply shadow-sm rounded-sm border-gray-200 */
  @apply shadow-xs rounded-xs border-current/20;
}

/* Custom components adapted from TailNews */
.news-hero { /* ... */ }
.category-badge { /* ... */ }
```

**Step 3:** Update component classes
```typescript
// OLD (TailNews HTML with v3 classes)
<div className="shadow-sm rounded-sm ring outline-none">

// NEW (React component with v4 classes)
<div className="shadow-xs rounded-xs ring-3 outline-hidden">
```

**Step 4:** Test visual consistency
```bash
# Compare with original TailNews template
# Verify:
# - Shadows look correct (shadow-xs vs shadow-sm)
# - Borders use currentColor correctly
# - Ring effects are visible (ring-3)
# - Placeholders have correct opacity
```

### Automated Migration

Tailwind oferă un tool automat de migrare:
```bash
cd /var/www/deschide_news_app/deschide_frontend

# Run automated upgrade
npx @tailwindcss/upgrade

# Review changes manually
# Not all changes can be automated - manual review required
```

**IMPORTANT:** Manual review este necesară pentru template-uri complexe.

### Browser Support

Tailwind CSS 4 necesită browsere moderne:
- Safari 16.4+
- Chrome 111+
- Firefox 128+

**Note:** Nu există suport pentru browsere mai vechi.

### Configuration Changes

#### Removed Options in v4:
- ❌ `corePlugins` - nu mai este suportat
- ❌ `safelist` - înlocuit cu `@source inline()`
- ❌ `separator` - eliminat

#### Custom Utilities:
```css
/* OLD (v3) */
@layer utilities {
  .custom-utility {
    /* styles */
  }
}

/* NEW (v4) */
@utility custom-utility {
  /* styles - better variant support */
}
```

### Testing Checklist

După migrare, verifică:

- [ ] Toate paginile publice se afișează corect
- [ ] Shadows sunt vizibile și corecte (xs în loc de sm)
- [ ] Borders folosesc culori corecte (currentColor)
- [ ] Ring effects funcționează (ring-3)
- [ ] Placeholder text are opacitate corectă
- [ ] Dark mode funcționează (dacă e implementat)
- [ ] Responsive design este intact
- [ ] Hover states funcționează
- [ ] Focus states sunt vizibile
- [ ] Print styles (dacă există) funcționează

### Resources

- [Tailwind CSS 4 Upgrade Guide](https://tailwindcss.com/docs/upgrade-guide)
- [Tailwind CSS 4 Documentation](https://tailwindcss.com/docs)
- [Breaking Changes Reference](https://tailwindcss.com/docs/upgrade-guide#breaking-changes)

---

---

## 📝 Changelog

### Version 2.1 (2025-10-27)

**Rectificări importante:**

1. **✅ Tailwind CSS 4 confirmat** - Frontend folosește Tailwind CSS 4
2. **✅ Stiluri separate per zonă:**
   - `app/styles/public.css` - Zona publică (TailNews adapted)
   - `app/styles/admin.css` - Zona admin (Flowbite)
   - `app/globals.css` - Minimal global styles
3. **✅ Strategie izolare CSS:**
   - Public layout importă doar `public.css`
   - Admin layout importă doar `admin.css`
   - Zero conflict între zone
4. **✅ TailNews migration plan:**
   - Template folosește Tailwind v3
   - Planificare migrare v3 → v4
   - Ghid complet de migrare adăugat
   - Checklist de testing post-migrare
5. **✅ Flowbite pentru admin:**
   - Setări Flowbite strict în zona admin
   - Plugin configurat în `tailwind.config.js`
   - Stiluri izolate în `admin.css`

**Documente de referință adăugate:**
- [Tailwind CSS Upgrade Guide](https://tailwindcss.com/docs/upgrade-guide)
- Tabel complet cu utility class renames (v3 → v4)
- Browser support requirements (Safari 16.4+, Chrome 111+, Firefox 128+)
- Automated migration tool: `npx @tailwindcss/upgrade`

**Structură actualizată:**
- Directory structure include `app/styles/`
- Layout-uri actualizate cu import CSS specific
- Sprint 0 deliverables actualizate

---

**Document Version:** 2.1
**Last Updated:** 2025-10-27
**Status:** ✅ Ready for Sprint 0 implementation

**Based on:**
- Next.js 16 Official Documentation
- next-i18n-router + react-intl pattern
- Flowbite Admin Dashboard template (Tailwind v4)
- TailNews template (Tailwind v3 → v4 migration)
- Tailwind CSS 4 Upgrade Guide
- Server Components best practices

**Key Features:**
- ✅ Separate CSS files per zone (public/admin)
- ✅ Tailwind v3 → v4 migration strategy
- ✅ Flowbite isolated to admin zone
- ✅ Complete documentation references
- ✅ Testing checklists included
