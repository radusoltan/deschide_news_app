# Internationalization (i18n) Configuration

## Overview

Deschide News frontend implements multilingual support using **Next.js App Router** with dynamic locale routing and server-side locale detection.

---

## 🌍 Supported Locales

| Code | Language | Direction | Status |
|------|----------|-----------|--------|
| **ro** | Romanian | LTR | ✅ Default |
| **en** | English | LTR | ✅ Active |
| **ru** | Russian | LTR | ✅ Active |

**Default Locale**: Romanian (`ro`)  
**Fallback**: Always falls back to Romanian

---

## 📁 URL Structure

### Dynamic Locale Routing

```
/[locale]/                    # Root with locale segment
├── (public)/                 # Public pages group
│   ├── page.tsx             # Homepage
│   ├── [categorySlug]/
│   │   ├── page.tsx         # Category page
│   │   └── [articleSlug]/
│   │       └── page.tsx     # Article page
│   ├── search/page.tsx
│   ├── trending/page.tsx
│   └── ...
├── admin/                    # Admin CMS
│   ├── page.tsx
│   ├── articles/
│   └── ...
├── live/[slug]/page.tsx     # LiveText viewer
└── login/page.tsx           # Login page
```

### Example URLs

```
Romanian (default):
- https://deschide.md/ro
- https://deschide.md/ro/politica
- https://deschide.md/ro/politica/stiri-de-ultima-ora

English:
- https://deschide.md/en
- https://deschide.md/en/politics
- https://deschide.md/en/politics/breaking-news

Russian:
- https://deschide.md/ru
- https://deschide.md/ru/политика
- https://deschide.md/ru/политика/срочные-новости
```

---

## 🔧 Implementation

### Locale Parameter

All pages receive `locale` parameter from URL:

```typescript
// app/[locale]/page.tsx

export default async function HomePage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  
  // Fetch content in specified locale
  const articles = await getArticles({ locale });
  
  return <HomePage articles={articles} locale={locale} />;
}
```

### Locale Detection Flow

```
1. User visits: /ro/politica
                  ↓
2. Next.js extracts: { locale: 'ro' }
                  ↓
3. Page component receives locale parameter
                  ↓
4. API calls include: Accept-Language: ro header
                  ↓
5. Backend returns Romanian translations
```

---

## 🌐 API Integration

### Sending Locale to Backend

**API Client** (`lib/api-client.ts`):

```typescript
export async function apiRequest<T>(
  endpoint: string,
  options: ApiRequestOptions = {}
): Promise<T> {
  const { locale = 'ro', ...fetchOptions } = options;
  
  const headers = new Headers(fetchOptions.headers);
  headers.set('Accept-Language', locale);  // ← Backend reads this
  headers.set('Accept', 'application/ld+json');
  
  const response = await fetch(`${API_URL}${endpoint}`, {
    ...fetchOptions,
    headers
  });
  
  return response.json();
}
```

**Usage in Components**:

```typescript
// Automatic locale from params
const articles = await getArticles({ locale });

// Custom locale
const englishArticles = await getArticles({ locale: 'en' });
```

---

## 🔄 Language Switching

### Language Switcher Component

**File**: `app/components/LanguageSwitcher.tsx`

```typescript
'use client';

import { useParams, usePathname, useRouter } from 'next/navigation';

const LOCALES = [
  { code: 'ro', name: 'RO', flag: '🇷🇴' },
  { code: 'en', name: 'EN', flag: '🇬🇧' },
  { code: 'ru', name: 'RU', flag: '🇷🇺' },
];

export function LanguageSwitcher() {
  const router = useRouter();
  const pathname = usePathname();
  const params = useParams();
  const currentLocale = params.locale as string;
  
  const switchLocale = (newLocale: string) => {
    // Replace current locale in path with new locale
    const newPath = pathname.replace(`/${currentLocale}`, `/${newLocale}`);
    router.push(newPath);
  };
  
  return (
    <div className="flex gap-2">
      {LOCALES.map(({ code, name, flag }) => (
        <button
          key={code}
          onClick={() => switchLocale(code)}
          className={currentLocale === code ? 'active' : ''}
          aria-label={`Switch to ${name}`}
        >
          {flag} {name}
        </button>
      ))}
    </div>
  );
}
```

### Preserving Context on Language Switch

**For Article Pages**:

Problem: Article slug changes per language
- Romanian: `/ro/politica/stiri-noi`
- English: `/en/politics/new-news`

**Solution**: Fetch translated slug from API

```typescript
async function getTranslatedArticlePath(
  articleId: number,
  targetLocale: string
): Promise<string> {
  // Fetch article in target locale
  const article = await getArticle(articleId, targetLocale);
  
  // Return path with translated slugs
  return `/${targetLocale}/${article.category.slug}/${article.slug}`;
}

// In LanguageSwitcher
const switchLocale = async (newLocale: string) => {
  if (articleId) {
    const newPath = await getTranslatedArticlePath(articleId, newLocale);
    router.push(newPath);
  } else {
    // Simple locale replacement
    const newPath = pathname.replace(`/${currentLocale}`, `/${newLocale}`);
    router.push(newPath);
  }
};
```

---

## 📝 Static Content Translation

### Translation Files (Future Enhancement)

**Directory Structure** (planned):
```
locales/
├── ro/
│   ├── common.json
│   └── homepage.json
├── en/
│   ├── common.json
│   └── homepage.json
└── ru/
    ├── common.json
    └── homepage.json
```

**Example**: `locales/ro/common.json`

```json
{
  "nav": {
    "home": "Acasă",
    "categories": "Categorii",
    "search": "Căutare",
    "login": "Autentificare"
  },
  "footer": {
    "about": "Despre noi",
    "contact": "Contact",
    "privacy": "Politica de confidențialitate"
  },
  "search": {
    "placeholder": "Caută articole...",
    "noResults": "Nu s-au găsit rezultate",
    "loading": "Se încarcă..."
  }
}
```

**Usage** (with next-intl or similar):

```typescript
import { useTranslations } from 'next-intl';

export function Navigation() {
  const t = useTranslations('nav');
  
  return (
    <nav>
      <a href="/">{t('home')}</a>
      <a href="/categories">{t('categories')}</a>
      <a href="/search">{t('search')}</a>
    </nav>
  );
}
```

---

## 🗂️ Metadata & SEO

### Dynamic Metadata per Locale

**File**: `app/[locale]/page.tsx`

```typescript
import type { Metadata } from 'next';

const METADATA_BY_LOCALE = {
  ro: {
    title: 'Deschide News - Știri de ultimă oră din Moldova',
    description: 'Cele mai recente știri din Moldova și lume',
  },
  en: {
    title: 'Deschide News - Latest News from Moldova',
    description: 'Breaking news from Moldova and around the world',
  },
  ru: {
    title: 'Deschide News - Последние новости из Молдовы',
    description: 'Последние новости из Молдовы и мира',
  },
};

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string }>;
}): Promise<Metadata> {
  const { locale } = await params;
  const metadata = METADATA_BY_LOCALE[locale] || METADATA_BY_LOCALE.ro;
  
  return {
    title: metadata.title,
    description: metadata.description,
    alternates: {
      canonical: `https://deschide.md/${locale}`,
      languages: {
        'ro': 'https://deschide.md/ro',
        'en': 'https://deschide.md/en',
        'ru': 'https://deschide.md/ru',
      },
    },
    openGraph: {
      locale: locale,
      siteName: 'Deschide News',
    },
  };
}
```

---

## 🔍 Search Engine Optimization

### hreflang Tags

**Automatic Generation**:

```html
<!-- In <head> -->
<link rel="canonical" href="https://deschide.md/ro/politica/articol" />
<link rel="alternate" hreflang="ro" href="https://deschide.md/ro/politica/articol" />
<link rel="alternate" hreflang="en" href="https://deschide.md/en/politics/article" />
<link rel="alternate" hreflang="ru" href="https://deschide.md/ru/политика/статья" />
<link rel="alternate" hreflang="x-default" href="https://deschide.md/ro/politica/articol" />
```

### Language Detection & Redirect

**Middleware** (optional):

```typescript
// middleware.ts

import { NextRequest, NextResponse } from 'next/server';

const SUPPORTED_LOCALES = ['ro', 'en', 'ru'];
const DEFAULT_LOCALE = 'ro';

export function middleware(request: NextRequest) {
  const pathname = request.nextUrl.pathname;
  
  // Check if path starts with locale
  const pathnameHasLocale = SUPPORTED_LOCALES.some(
    (locale) => pathname.startsWith(`/${locale}/`) || pathname === `/${locale}`
  );
  
  if (!pathnameHasLocale) {
    // Detect locale from Accept-Language header
    const acceptLanguage = request.headers.get('accept-language') || '';
    const browserLocale = acceptLanguage.split(',')[0].split('-')[0];
    
    const locale = SUPPORTED_LOCALES.includes(browserLocale)
      ? browserLocale
      : DEFAULT_LOCALE;
    
    // Redirect to localized path
    return NextResponse.redirect(
      new URL(`/${locale}${pathname}`, request.url)
    );
  }
  
  return NextResponse.next();
}

export const config = {
  matcher: ['/((?!api|_next/static|_next/image|favicon.ico).*)'],
};
```

---

## 📊 Locale-specific Features

### Date & Time Formatting

```typescript
import { format } from 'date-fns';
import { ro, enUS, ru } from 'date-fns/locale';

const LOCALE_MAP = {
  ro: ro,
  en: enUS,
  ru: ru,
};

export function formatDate(date: Date, locale: string) {
  return format(date, 'PPP', { locale: LOCALE_MAP[locale] });
}

// Usage
formatDate(new Date(), 'ro');  // "5 noiembrie 2025"
formatDate(new Date(), 'en');  // "November 5, 2025"
formatDate(new Date(), 'ru');  // "5 ноября 2025 г."
```

### Number Formatting

```typescript
export function formatNumber(num: number, locale: string) {
  return new Intl.NumberFormat(locale).format(num);
}

formatNumber(1234567, 'ro');  // "1.234.567"
formatNumber(1234567, 'en');  // "1,234,567"
formatNumber(1234567, 'ru');  // "1 234 567"
```

---

## 🚀 Performance Optimization

### Static Generation per Locale

```typescript
// app/[locale]/page.tsx

export async function generateStaticParams() {
  return [
    { locale: 'ro' },
    { locale: 'en' },
    { locale: 'ru' },
  ];
}
```

### Locale-specific Caching

```typescript
// Cache key includes locale
const cacheKey = `articles_${locale}_page_${page}`;
```

---

## 🧪 Testing

### Locale Switching Tests

```typescript
// __tests__/e2e/locale-switching.spec.ts

describe('Locale Switching', () => {
  it('should switch language and preserve context', async () => {
    await page.goto('/ro/politica');
    
    // Click English flag
    await page.click('[aria-label="Switch to EN"]');
    
    // Should redirect to English version
    expect(page.url()).toContain('/en/politics');
  });
  
  it('should load translated content', async () => {
    await page.goto('/en/politics/some-article');
    
    const title = await page.textContent('h1');
    expect(title).toBe('English Article Title');
    
    // Switch to Russian
    await page.click('[aria-label="Switch to RU"]');
    
    const russianTitle = await page.textContent('h1');
    expect(russianTitle).toBe('Русский заголовок');
  });
});
```

---

## 📝 Best Practices

1. **Always pass locale to API calls**: Don't rely on server-side detection
2. **Use locale parameter from URL**: Single source of truth
3. **Preserve user choice**: Store selected locale in cookie (optional)
4. **Test all locales**: Ensure features work in ro, en, ru
5. **Handle missing translations**: Always provide fallback
6. **SEO**: Include hreflang tags for all versions

---

**Last Updated**: November 5, 2025  
**Version**: 1.0  
**Next.js Version**: 16
