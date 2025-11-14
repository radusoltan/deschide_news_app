# Public Routes Documentation

## Overview

This document describes all public-facing routes in the Deschide News frontend, accessible to non-authenticated users.

---

## 🌐 Route Structure

### Base URL Pattern

```
/[locale]/[...path]

Examples:
- /ro                           → Romanian homepage
- /en/politics                  → English politics category
- /ru/политика/статья          → Russian article
```

### Route Groups

```
app/[locale]/
├── (public)/               # Public pages group
│   ├── page.tsx           # Homepage
│   ├── [categorySlug]/
│   │   ├── page.tsx       # Category page
│   │   └── [articleSlug]/
│   │       └── page.tsx   # Article detail
│   ├── search/page.tsx
│   ├── trending/page.tsx
│   ├── archive/
│   ├── author/[slug]/page.tsx
│   ├── about/page.tsx
│   └── contact/page.tsx
└── live/[slug]/page.tsx   # LiveText viewer
```

---

## 📄 Homepage

### Route

**Path**: `/[locale]`

**Examples**:
- `/ro`
- `/en`
- `/ru`

### File

`app/[locale]/(public)/page.tsx`

### Features

1. **Important Articles** (Hero Section)
   - 5 featured articles from `ImportantArticlesList`
   - Position 0: Large hero image (1920×1080)
   - Positions 1-4: Smaller cards (800×600)

2. **Latest News**
   - Infinite scroll
   - 20 articles per page
   - Auto-load on scroll

3. **Category Navigation**
   - Horizontal category tabs
   - Quick access to all categories

### Implementation

```typescript
import { getArticles } from '@/lib/api/articles';
import { getImportantArticles } from '@/lib/api/important-articles';

export default async function HomePage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  
  // Fetch important articles (hero section)
  const importantArticles = await getImportantArticles(locale);
  
  // Fetch latest articles
  const latestArticles = await getArticles({
    locale,
    page: 1,
    itemsPerPage: 20,
    status: 'published',
    order: { publishedAt: 'DESC' },
  });
  
  return (
    <div>
      <HeroSection articles={importantArticles} />
      <LatestNews articles={latestArticles.items} locale={locale} />
    </div>
  );
}
```

### Data Fetching

```typescript
// Server Component - data fetched on server
export default async function HomePage({ params }) {
  // SSR - Fast initial load
  const data = await getArticles({ locale });
  
  return <HomePage data={data} />;
}
```

---

## 📂 Category Page

### Route

**Path**: `/[locale]/[categorySlug]`

**Examples**:
- `/ro/politica`
- `/en/politics`
- `/ru/политика`

### File

`app/[locale]/(public)/[categorySlug]/page.tsx`

### Features

1. **Category Hero**
   - Featured article from category
   - Large image + title

2. **Article List**
   - All articles in category
   - Pagination (20 per page)
   - Sorted by publishedAt DESC

3. **Subcategories** (if any)
   - List child categories
   - Quick navigation

### Implementation

```typescript
import { getCategory } from '@/lib/api/categories';
import { getArticles } from '@/lib/api/articles';

export default async function CategoryPage({
  params,
}: {
  params: Promise<{ locale: string; categorySlug: string }>;
}) {
  const { locale, categorySlug } = await params;
  
  // Fetch category by slug
  const category = await getCategory(categorySlug, locale);
  
  if (!category) {
    notFound();
  }
  
  // Fetch articles in this category
  const articles = await getArticles({
    locale,
    category: category.id,
    status: 'published',
    page: 1,
    itemsPerPage: 20,
  });
  
  return (
    <div>
      <CategoryHeader category={category} />
      <ArticleGrid articles={articles.items} />
      <Pagination 
        currentPage={1} 
        totalPages={Math.ceil(articles.total / 20)} 
      />
    </div>
  );
}
```

### Metadata

```typescript
export async function generateMetadata({ params }): Promise<Metadata> {
  const { locale, categorySlug } = await params;
  const category = await getCategory(categorySlug, locale);
  
  return {
    title: `${category.name} - Deschide News`,
    description: category.description,
    alternates: {
      canonical: `https://deschide.md/${locale}/${categorySlug}`,
      languages: {
        ro: `https://deschide.md/ro/${category.slugRo}`,
        en: `https://deschide.md/en/${category.slugEn}`,
        ru: `https://deschide.md/ru/${category.slugRu}`,
      },
    },
  };
}
```

---

## 📰 Article Detail Page

### Route

**Path**: `/[locale]/[categorySlug]/[articleSlug]`

**Examples**:
- `/ro/politica/stiri-de-ultima-ora`
- `/en/politics/breaking-news`
- `/ru/политика/срочные-новости`

### File

`app/[locale]/(public)/[categorySlug]/[articleSlug]/page.tsx`

### Features

1. **Article Header**
   - Title
   - Lead paragraph
   - Author info
   - Published date
   - Reading time
   - Badge (breaking, exclusive, etc.)

2. **Featured Image**
   - Hero image (1600×900)
   - Caption
   - Photo credit

3. **Article Body**
   - Rich text content (HTML)
   - Inline images
   - Pull quotes
   - Social share buttons

4. **Sidebar**
   - Related articles
   - Most popular
   - Advertisement slots

5. **Social Sharing**
   - Facebook, Twitter, WhatsApp, Email
   - Copy link

### Implementation

```typescript
import { getArticleBySlug } from '@/lib/api/articles';
import { ArticleLayout } from '@/components/article/ArticleLayout';

export default async function ArticlePage({
  params,
}: {
  params: Promise<{ locale: string; categorySlug: string; articleSlug: string }>;
}) {
  const { locale, articleSlug } = await params;
  
  // Fetch article by slug
  const article = await getArticleBySlug(articleSlug, locale);
  
  if (!article || article.status !== 'published') {
    notFound();
  }
  
  // Increment view count (server action)
  await incrementArticleViews(article.id);
  
  return (
    <ArticleLayout article={article} locale={locale}>
      <ArticleHeader article={article} />
      <ArticleBody content={article.content} />
      <ArticleSidebar articleId={article.id} />
    </ArticleLayout>
  );
}
```

### SEO Metadata

```typescript
export async function generateMetadata({ params }): Promise<Metadata> {
  const { locale, articleSlug } = await params;
  const article = await getArticleBySlug(articleSlug, locale);
  
  const featuredImage = article.articleImages.find(ai => ai.isFeatured);
  const imageUrl = featuredImage 
    ? `${CDN_URL}/uploads/${featuredImage.image.path}`
    : null;
  
  return {
    title: article.metaTitle || article.title,
    description: article.metaDescription || article.lead,
    authors: [{ name: article.author.name }],
    openGraph: {
      title: article.title,
      description: article.lead,
      type: 'article',
      publishedTime: article.publishedAt,
      authors: [article.author.name],
      images: imageUrl ? [{ url: imageUrl }] : [],
    },
    twitter: {
      card: 'summary_large_image',
      title: article.title,
      description: article.lead,
      images: imageUrl ? [imageUrl] : [],
    },
  };
}
```

### Structured Data (JSON-LD)

```typescript
export function ArticleStructuredData({ article }: { article: Article }) {
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'NewsArticle',
    headline: article.title,
    description: article.lead,
    image: article.articleImages[0]?.image.path,
    datePublished: article.publishedAt,
    dateModified: article.updatedAt,
    author: {
      '@type': 'Person',
      name: article.author.name,
    },
    publisher: {
      '@type': 'Organization',
      name: 'Deschide News',
      logo: {
        '@type': 'ImageObject',
        url: 'https://deschide.md/logo.png',
      },
    },
  };
  
  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
    />
  );
}
```

---

## 🔍 Search Page

### Route

**Path**: `/[locale]/search`

**Examples**:
- `/ro/search?q=politica`
- `/en/search?q=politics`

### File

`app/[locale]/(public)/search/page.tsx`

### Features

1. **Search Input**
   - Real-time suggestions (debounced)
   - Search history (localStorage)

2. **Filters**
   - Category filter
   - Date range
   - Sort by relevance/date

3. **Results**
   - Article cards
   - Highlighted search terms
   - Pagination

### Implementation

```typescript
import { searchArticles } from '@/lib/api/articles';

export default async function SearchPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{ q?: string; page?: string }>;
}) {
  const { locale } = await params;
  const { q, page = '1' } = await searchParams;
  
  if (!q) {
    return <SearchEmptyState />;
  }
  
  // Search via Elasticsearch
  const results = await searchArticles({
    locale,
    query: q,
    page: parseInt(page),
    itemsPerPage: 20,
  });
  
  return (
    <div>
      <SearchHeader query={q} totalResults={results.total} />
      <SearchFilters />
      <SearchResults results={results.items} query={q} />
      <Pagination currentPage={parseInt(page)} totalPages={results.totalPages} />
    </div>
  );
}
```

---

## 📈 Trending Page

### Route

**Path**: `/[locale]/trending`

### File

`app/[locale]/(public)/trending/page.tsx`

### Features

- Most viewed articles (last 24h, 7 days, 30 days)
- View count display
- Sortable by time period

### Implementation

```typescript
export default async function TrendingPage({ params, searchParams }) {
  const { locale } = await params;
  const { period = '24h' } = await searchParams;
  
  const trending = await getTrendingArticles({
    locale,
    period,
    limit: 50,
  });
  
  return (
    <div>
      <h1>Trending Articles</h1>
      <PeriodSelector current={period} />
      <TrendingList articles={trending} />
    </div>
  );
}
```

---

## 📅 Archive Page

### Route

**Path**: `/[locale]/archive/[year]/[month]`

**Examples**:
- `/ro/archive/2025/11`
- `/en/archive/2025/november`

### File

`app/[locale]/(public)/archive/[year]/[month]/page.tsx`

### Features

- Articles grouped by publish date
- Month/year navigation
- Calendar widget

---

## 👤 Author Page

### Route

**Path**: `/[locale]/author/[slug]`

**Examples**:
- `/ro/author/ion-popescu`
- `/en/author/john-doe`

### File

`app/[locale]/(public)/author/[slug]/page.tsx`

### Features

1. **Author Bio**
   - Name, photo, bio
   - Social links (Twitter, Facebook)
   - Email

2. **Author Articles**
   - All articles by this author
   - Sorted by date
   - Pagination

### Implementation

```typescript
export default async function AuthorPage({ params }) {
  const { locale, slug } = await params;
  
  const author = await getAuthorBySlug(slug);
  const articles = await getArticles({
    locale,
    author: author.id,
    status: 'published',
  });
  
  return (
    <div>
      <AuthorBio author={author} />
      <AuthorArticles articles={articles.items} />
    </div>
  );
}
```

---

## ℹ️ About Page

### Route

**Path**: `/[locale]/about`

### File

`app/[locale]/(public)/about/page.tsx`

### Content

- Company information
- Mission statement
- Team members
- Contact information

---

## 📧 Contact Page

### Route

**Path**: `/[locale]/contact`

### File

`app/[locale]/(public)/contact/page.tsx`

### Features

- Contact form
- Office address
- Email, phone
- Social media links

---

## 🔴 LiveText Viewer

### Route

**Path**: `/[locale]/live/[slug]`

**Examples**:
- `/ro/live/meci-nationala-moldova`
- `/en/live/moldova-national-match`

### File

`app/[locale]/live/[slug]/page.tsx`

### Features

1. **Live Header**
   - Event title
   - Status badge (LIVE, Ended, Scheduled)
   - Viewer counter

2. **Posts Timeline**
   - Real-time updates (Mercure SSE)
   - Reverse chronological order
   - Pinned posts at top
   - Breaking posts highlighted

3. **Reactions**
   - Like, Love, Wow, Sad, Angry
   - Reaction counts

4. **Sport Match** (if applicable)
   - Score display
   - Match events timeline

### Implementation

```typescript
'use client';

import { useMercureSubscription } from '@/lib/hooks/useMercureSubscription';

export default function LiveTextPage({ params }) {
  const { locale, slug } = params;
  const [liveText, setLiveText] = useState(null);
  const [posts, setPosts] = useState([]);
  
  // Subscribe to real-time updates
  const mercureUpdate = useMercureSubscription(
    `${MERCURE_URL}`,
    [`deschide_news/livetext/${liveText?.id}`]
  );
  
  useEffect(() => {
    if (mercureUpdate?.type === 'post_created') {
      setPosts([mercureUpdate.post, ...posts]);
    }
  }, [mercureUpdate]);
  
  return (
    <div>
      <LiveTextHeader liveText={liveText} />
      <PostsTimeline posts={posts} />
      <ReactionButtons liveTextId={liveText.id} />
    </div>
  );
}
```

---

## 🗺️ Sitemap

### Route

**Path**: `/sitemap.xml`

### File

`app/sitemap.ts`

### Implementation

```typescript
import { MetadataRoute } from 'next';

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const articles = await getAllPublishedArticles();
  const categories = await getAllCategories();
  
  return [
    {
      url: 'https://deschide.md/ro',
      lastModified: new Date(),
      changeFrequency: 'hourly',
      priority: 1,
    },
    ...articles.map(article => ({
      url: `https://deschide.md/ro/${article.category.slug}/${article.slug}`,
      lastModified: article.updatedAt,
      changeFrequency: 'daily',
      priority: 0.8,
    })),
    ...categories.map(category => ({
      url: `https://deschide.md/ro/${category.slug}`,
      lastModified: new Date(),
      changeFrequency: 'daily',
      priority: 0.6,
    })),
  ];
}
```

---

## 🤖 Robots.txt

### Route

**Path**: `/robots.txt`

### File

`app/robots.ts`

### Implementation

```typescript
import { MetadataRoute } from 'next';

export default function robots(): MetadataRoute.Robots {
  return {
    rules: {
      userAgent: '*',
      allow: '/',
      disallow: ['/admin/', '/api/'],
    },
    sitemap: 'https://deschide.md/sitemap.xml',
  };
}
```

---

## 📊 Performance Optimization

### Static Generation

```typescript
// Generate static pages for top categories
export async function generateStaticParams() {
  const categories = await getTopCategories();
  
  return categories.map(category => ({
    categorySlug: category.slug,
  }));
}
```

### Incremental Static Regeneration

```typescript
export const revalidate = 60; // Revalidate every 60 seconds
```

---

**Last Updated**: November 5, 2025  
**Version**: 1.0  
**Total Public Routes**: 12+
