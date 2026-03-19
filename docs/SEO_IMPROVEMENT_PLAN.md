# Plan de Imbunatatiri SEO - deschide.md (Next.js)

**Data:** 12 Decembrie 2025
**Bazat pe:** Raport Bing Webmaster + Microsoft Clarity + Audit Cod Frontend

---

## Executive Summary

Aplicatia Next.js are o **fundatie SEO solida** cu:
- Metadata dinamica pentru toate paginile
- Structured Data (JSON-LD) pentru articole
- Sitemap-uri multiple (main, news, image, archive)
- robots.txt configurat corect
- Suport multilingv (ro, en, ru)

**Problema principala din raportul Bing:** 68.4% redirecturi 301 - probabil din site-ul vechi, nu din aceasta aplicatie.

**Probleme UX din Clarity:**
- Dead Clicks PC: 10.97%
- Quickback PC: 6.58%
- Script Errors Mobile: 2.04%

---

## Prioritate 1: CRITICA (Implementare Imediata)

### 1.1 Configurare Environment Variables

**Status:** Incomplet
**Fisier:** `.env.local` si `.env.production`

```bash
# Social Media (NECONFIGURAT)
NEXT_PUBLIC_FACEBOOK_URL=https://www.facebook.com/deschide.md
NEXT_PUBLIC_TWITTER_URL=https://twitter.com/deschidenews
NEXT_PUBLIC_TELEGRAM_URL=https://t.me/deschidenews
NEXT_PUBLIC_YOUTUBE_URL=https://www.youtube.com/@deschidenews
NEXT_PUBLIC_INSTAGRAM_URL=https://www.instagram.com/deschidenews

# Verificare Search Engines (NECONFIGURAT)
NEXT_PUBLIC_GOOGLE_SITE_VERIFICATION=xxx
NEXT_PUBLIC_YANDEX_VERIFICATION=xxx
NEXT_PUBLIC_BING_VERIFICATION=xxx

# Contact (PARTIAL)
NEXT_PUBLIC_ORG_PHONE=+373 XX XXX XXX
NEXT_PUBLIC_ORG_ADDRESS=str. XXX, Chisinau, Moldova
```

**Actiune:** Completeaza toate valorile inainte de lansare.

---

### 1.2 Fix Dead Clicks pe PC (10.97%)

**Problema:** Utilizatorii PC dau click pe elemente care par interactive dar nu sunt.

**Cauze probabile:**
1. Text cu culori de link dar fara `<a>` tag
2. Imagini/carduri care arata clickable dar nu sunt
3. Butoane/iconite fara functionalitate
4. Hover states pe elemente non-interactive

**Actiuni:**

```tsx
// 1. Asigura-te ca toate cardurile de articole sunt clickable
// BAD - doar titlul e link
<div className="card">
  <img src="..." />
  <Link href="/article"><h3>Title</h3></Link>
  <p>Lead text...</p>  {/* Nu e clickable! */}
</div>

// GOOD - tot cardul e link
<Link href="/article" className="card group">
  <img src="..." className="group-hover:scale-105" />
  <h3>Title</h3>
  <p>Lead text...</p>
</Link>
```

**Fisiere de verificat:**
- `components/ArticleCard.tsx`
- `components/article/ArticleCard.tsx`
- `components/CategorySection.tsx`
- `components/public/TrendingArticles.tsx`

---

### 1.3 Fix Script Errors Mobile (2.04%)

**Actiuni:**

1. **Adauga Error Boundary global:**

```tsx
// app/[locale]/layout.tsx
import { ErrorBoundary } from '@/components/ErrorBoundary';

export default function Layout({ children }) {
  return (
    <ErrorBoundary fallback={<ErrorFallback />}>
      {children}
    </ErrorBoundary>
  );
}
```

2. **Verifica console errors in development:**
```bash
cd apps/frontend
pnpm dev
# Deschide http://localhost:3005 pe Chrome DevTools
# Filtreaza Console pentru errors
```

3. **Adauga client-side error logging:**

```tsx
// lib/error-tracking.ts
export function logClientError(error: Error, context?: string) {
  // In development
  console.error(`[${context}]`, error);

  // In production - trimite la Clarity sau Sentry
  if (typeof window !== 'undefined' && window.clarity) {
    window.clarity('event', 'js_error', {
      message: error.message,
      stack: error.stack,
      context
    });
  }
}
```

---

### 1.4 Canonical URLs pe Toate Paginile

**Status:** Partial implementat (doar articole)

**Actiune:** Adauga canonical URL in metadata pentru:

```tsx
// app/[locale]/(public)/category/[slug]/page.tsx
export async function generateMetadata({ params }): Promise<Metadata> {
  const canonicalUrl = `${SITE_URL}/${params.locale}/category/${params.slug}`;

  return {
    alternates: {
      canonical: canonicalUrl,
      languages: {
        'ro': `${SITE_URL}/ro/category/${params.slug}`,
        'en': `${SITE_URL}/en/category/${params.slug}`,
        'ru': `${SITE_URL}/ru/category/${params.slug}`,
        'x-default': `${SITE_URL}/ro/category/${params.slug}`,
      },
    },
  };
}
```

**Pagini de actualizat:**
- `category/[slug]/page.tsx`
- `author/[slug]/page.tsx`
- `archive/[year]/page.tsx`
- `archive/[year]/[month]/page.tsx`
- `tags/[slug]/page.tsx`
- `search/page.tsx`

---

## Prioritate 2: INALTA (Saptamana 1-2)

### 2.1 Breadcrumbs pe Toate Paginile

**Status:** Doar pe articole

**Actiune:** Creeaza component reutilizabil:

```tsx
// components/seo/Breadcrumbs.tsx
import { generateBreadcrumbSchema } from '@/lib/seo/structured-data';

interface BreadcrumbItem {
  name: string;
  url?: string;
}

export function Breadcrumbs({ items, locale }: { items: BreadcrumbItem[], locale: string }) {
  const schema = {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: items.map((item, index) => ({
      '@type': 'ListItem',
      position: index + 1,
      name: item.name,
      item: item.url,
    })),
  };

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(schema) }}
      />
      <nav aria-label="Breadcrumb" className="text-sm text-gray-500 mb-4">
        {items.map((item, index) => (
          <span key={index}>
            {item.url ? (
              <Link href={item.url} className="hover:text-brand-tomato-500">
                {item.name}
              </Link>
            ) : (
              <span>{item.name}</span>
            )}
            {index < items.length - 1 && <span className="mx-2">/</span>}
          </span>
        ))}
      </nav>
    </>
  );
}
```

**Implementare pe pagini:**
- Category: `Home > Categorie > Subcategorie`
- Author: `Home > Autori > Nume Autor`
- Archive: `Home > Arhiva > 2024 > Decembrie`
- Tags: `Home > Tag-uri > Tag Name`

---

### 2.2 Reduce Quickback Rate (6.58% PC)

**Problema:** Utilizatorii se intorc rapid = continutul nu corespunde asteptarilor.

**Actiuni:**

1. **Imbunatateste Above-the-fold:**
```tsx
// Pe pagina de articol, asigura-te ca lead-ul e vizibil imediat
<article>
  <h1 className="text-3xl font-bold">{article.title}</h1>
  <p className="text-xl text-gray-600 mt-4">{article.lead}</p>
  {/* Featured image DUPA lead, nu inainte */}
  <Image ... priority />
</article>
```

2. **Meta description acurata:**
```tsx
// Asigura-te ca meta description = lead-ul articolului
description: article.lead?.substring(0, 160) || generateDescription(article),
```

3. **Incarcarea rapida a continutului:**
- Foloseste `priority` pentru imagini above-the-fold
- Skeleton loading pentru continut
- Preload fonts critice

---

### 2.3 Dynamic OG Images

**Status:** Nu exista

**Actiune:** Implementeaza generare dinamica OG image:

```tsx
// app/api/og/route.tsx
import { ImageResponse } from 'next/og';

export async function GET(request: Request) {
  const { searchParams } = new URL(request.url);
  const title = searchParams.get('title') || 'Deschide News';
  const category = searchParams.get('category') || '';

  return new ImageResponse(
    (
      <div
        style={{
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          width: '1200px',
          height: '630px',
          backgroundColor: '#1a1a2e',
          color: 'white',
        }}
      >
        <div style={{ fontSize: 24, color: '#ef4444' }}>{category}</div>
        <div style={{ fontSize: 60, fontWeight: 'bold', textAlign: 'center', padding: '0 40px' }}>
          {title}
        </div>
        <div style={{ fontSize: 28, marginTop: 20, color: '#d4e157' }}>
          deschide.md
        </div>
      </div>
    ),
    { width: 1200, height: 630 }
  );
}
```

---

### 2.4 Optimizare Core Web Vitals (Mobile)

**Target:** LCP < 2.5s, INP < 200ms, CLS < 0.1

**Actiuni:**

1. **Preload Critical Resources:**
```tsx
// app/[locale]/layout.tsx
<link rel="preconnect" href="https://api.deschide.md" />
<link rel="preconnect" href="https://cdn.deschide.md" />
<link rel="dns-prefetch" href="https://www.googletagmanager.com" />
```

2. **Optimizeaza LCP (Largest Contentful Paint):**
```tsx
// Pentru hero image
<Image
  src={heroImage}
  alt="..."
  priority // Forteaza preload
  fetchPriority="high"
  sizes="100vw"
/>
```

3. **Reduce CLS (Cumulative Layout Shift):**
```tsx
// Adauga aspect-ratio la toate imaginile
<div className="aspect-video relative">
  <Image fill src="..." alt="..." />
</div>
```

4. **Lazy load non-critical:**
```tsx
// Pentru componente sub fold
const TrendingArticles = dynamic(
  () => import('@/components/TrendingArticles'),
  { loading: () => <TrendingSkeleton /> }
);
```

---

## Prioritate 3: MEDIE (Luna 1)

### 3.1 Schema Markup Extended

**Adauga pentru:**

1. **VideoObject** (pentru embed YouTube):
```tsx
const videoSchema = {
  '@context': 'https://schema.org',
  '@type': 'VideoObject',
  name: video.title,
  description: video.description,
  thumbnailUrl: video.thumbnail,
  uploadDate: video.uploadDate,
  contentUrl: video.url,
  embedUrl: `https://www.youtube.com/embed/${video.id}`,
};
```

2. **FAQPage** (pentru articole cu intrebari):
```tsx
// Deja implementat in lib/seo/structured-data.ts
// Trebuie folosit pe paginile relevante
```

### 3.2 Internal Linking Optimization

**Problema:** Prea putine link-uri interne.

**Actiuni:**

1. **Related Articles component:**
```tsx
// components/article/RelatedArticles.tsx
export function RelatedArticles({ articleId, categoryId }) {
  // Fetch 4-6 articole din aceeasi categorie
  return (
    <section>
      <h3>Citeste si:</h3>
      <div className="grid grid-cols-2 gap-4">
        {relatedArticles.map(article => (
          <ArticleCard key={article.id} article={article} />
        ))}
      </div>
    </section>
  );
}
```

2. **Tag-uri clickable in articole:**
```tsx
// Asigura-te ca tag-urile duc la pagina de tag
<Link href={`/${locale}/tags/${tag.slug}`}>
  {tag.name}
</Link>
```

3. **Autor clickable:**
```tsx
<Link href={`/${locale}/author/${author.slug}`}>
  {author.fullName}
</Link>
```

---

### 3.3 Verificare Search Engines

**Actiuni:**

1. **Adauga site in Google Search Console:**
   - Verifica cu meta tag sau DNS TXT record
   - Submit sitemap-ul

2. **Adauga site in Bing Webmaster Tools:**
   - Deja ai cont (API key functional)
   - Submit sitemap: `https://deschide.md/sitemap.xml`

3. **Adauga in Yandex Webmaster:**
   - Important pentru audienta rusa
   - https://webmaster.yandex.com/

---

## Prioritate 4: NICE-TO-HAVE (Q1 2026)

### 4.1 Telegram Instant View

**De ce:** 50% trafic vine din social media, Telegram popular in Moldova.

**Actiune:** Creeaza template Telegram IV:
```
# Template pentru articles
~version: "2.1"

# Title
title: //article//h1
# Body
body: //article//*[contains(@class, 'article-content')]
# Image
image_url: //article//img/@src
# Author
author: //span[contains(@class, 'author-name')]
# Date
published_date: //time/@datetime
```

### 4.2 PWA Optimization

**manifest.json:**
```json
{
  "name": "Deschide News",
  "short_name": "Deschide",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#1a1a2e",
  "theme_color": "#ef4444",
  "icons": [...]
}
```

### 4.3 Performance Budget

**Stabileste limite:**
```js
// next.config.mjs
experimental: {
  webVitalsAttribution: ['CLS', 'LCP', 'INP'],
}
```

**Targets:**
- JS Bundle: < 200KB (gzipped)
- CSS: < 50KB
- LCP: < 2.5s
- TTI: < 3.5s

---

## Checklist Pre-Lansare

### SEO Technical
- [ ] Toate env variables completate
- [ ] robots.txt testat (`/robots.txt`)
- [ ] Sitemap functional (`/sitemap.xml`)
- [ ] Canonical URLs pe toate paginile
- [ ] hreflang tags corecte
- [ ] Structured data valid (test cu Google Rich Results)

### Performance
- [ ] Lighthouse Mobile >= 90
- [ ] LCP < 2.5s
- [ ] CLS < 0.1
- [ ] INP < 200ms

### UX
- [ ] Zero dead clicks (test manual pe PC)
- [ ] Zero script errors (test pe mobil)
- [ ] Toate cardurile clickable
- [ ] Touch targets >= 44px

### Content
- [ ] Meta descriptions unice pe toate paginile
- [ ] OG images configurate
- [ ] Alt text pe toate imaginile

### Monitoring
- [ ] Google Search Console conectat
- [ ] Bing Webmaster Tools conectat
- [ ] Microsoft Clarity activ
- [ ] Google Analytics 4 activ

---

## Timeline Recomandat

| Saptamana | Task-uri | Responsabil |
|-----------|----------|-------------|
| W1 | Env variables, Canonical URLs, Error boundary | Dev |
| W2 | Fix dead clicks, Breadcrumbs toate paginile | Dev |
| W3 | Dynamic OG, Core Web Vitals | Dev |
| W4 | Testing, Search Console setup | Dev + QA |
| W5 | Launch + Monitoring | Team |

---

## Scripturi de Testare

```bash
# Test sitemap
curl https://deschide.md/sitemap.xml

# Test robots
curl https://deschide.md/robots.txt

# Test structured data
npx schema-dts-validator https://deschide.md/ro/politic/article-slug

# Lighthouse audit
npx lighthouse https://deschide.md --view

# Test mobile
npx lighthouse https://deschide.md --preset=desktop --view
npx lighthouse https://deschide.md --preset=mobile --view
```

---

**Document creat:** 12 Decembrie 2025
**Urmatoarea revizie:** Dupa implementare Prioritate 1
