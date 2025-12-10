# Animation Cheatsheet - Copy & Paste

Quick copy-paste snippets for common animation patterns.

## Loading States

### Replace any loading UI
```tsx
// Old
<div>Loading...</div>

// New ✨
<LoadingSpinner size="lg" text="Loading articles..." />
```

### Article Grid Loading
```tsx
// Old
{loading && <div>Loading...</div>}

// New ✨
{loading && <SkeletonCard variant="article" count={6} />}
```

### Button Loading
```tsx
// Old
<button disabled={loading}>
  {loading ? 'Loading...' : 'Save'}
</button>

// New ✨
<button disabled={loading} className="btn-brand-primary">
  {loading && <InlineLoader variant="white" className="mr-2" />}
  {loading ? 'Saving...' : 'Save'}
</button>
```

## Card Enhancements

### Basic Card → Premium Card
```tsx
// Old
<div className="bg-white rounded-lg shadow-sm">

// New ✨ (with hover lift)
<div className="bg-white rounded-lg shadow-sm hover-lift">
```

### Card with Image → Image Zoom
```tsx
// Old
<div>
  <img src={image} alt={title} />
</div>

// New ✨
<div className="hover-image-zoom">
  <img src={image} alt={title} />
</div>
```

### Full Premium Card
```tsx
<article className="bg-white rounded-lg overflow-hidden shadow-sm hover-lift">
  <div className="hover-image-zoom">
    <img src={article.image} alt={article.title} className="w-full h-48 object-cover" />
  </div>
  <div className="p-4">
    {article.category && (
      <span className="inline-block px-3 py-1 bg-brand-tomato-100 text-brand-tomato-600 text-sm font-semibold rounded">
        {article.category}
      </span>
    )}
    <h3 className="mt-2 text-xl font-bold text-brand-oxford-900 underline-animate">
      <a href={article.url}>{article.title}</a>
    </h3>
    <p className="mt-2 text-gray-600">{article.excerpt}</p>
  </div>
</article>
```

## Hero Sections

### Hero with Gradient Overlay
```tsx
<div className="relative overflow-hidden">
  {/* Image with zoom */}
  <div className="hover-image-zoom">
    <img src={hero.image} alt={hero.title} className="w-full h-96 object-cover" />
  </div>

  {/* Gradient overlay for text readability */}
  <div className="absolute inset-0 card-overlay-gradient" />

  {/* Content */}
  <div className="absolute bottom-0 left-0 right-0 p-8">
    <div className="animate-fade-in-up">
      {hero.isBreaking && (
        <div className="breaking-news-badge mb-4">
          Breaking News
        </div>
      )}
      <h1 className="text-4xl font-bold text-white text-on-photo-strong">
        {hero.title}
      </h1>
    </div>
    <p className="text-white text-on-photo mt-4 animate-fade-in-up stagger-1">
      {hero.excerpt}
    </p>
  </div>
</div>
```

## Lists with Animation

### Static List → Staggered Animation
```tsx
// Old
{articles.map(article => (
  <ArticleCard key={article.id} article={article} />
))}

// New ✨
{articles.map((article, i) => (
  <div key={article.id} className={`animate-fade-in-up stagger-${Math.min(i + 1, 6)}`}>
    <ArticleCard article={article} />
  </div>
))}
```

### Grid Layout
```tsx
<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
  {articles.map((article, i) => (
    <div key={article.id} className={`animate-fade-in-up stagger-${Math.min(i + 1, 6)}`}>
      <ArticleCard article={article} />
    </div>
  ))}
</div>
```

## Breaking News

### Breaking News Badge
```tsx
{article.isBreaking && (
  <div className="breaking-news-badge">
    Breaking News
  </div>
)}
```

### Breaking News Card
```tsx
<article className="bg-white rounded-lg shadow-sm hover-lift">
  {article.isBreaking && (
    <div className="breaking-news-badge mb-3">
      Breaking
    </div>
  )}
  <h3>{article.title}</h3>
</article>
```

## Navigation Links

### Standard Link → Animated Underline
```tsx
// Old
<a href={url} className="text-brand-oxford-900 font-bold">
  {title}
</a>

// New ✨
<a href={url} className="text-brand-oxford-900 font-bold underline-animate">
  {title}
</a>
```

## Buttons & Forms

### Button with Focus Ring
```tsx
<button className="btn-brand-primary focus-brand">
  Submit
</button>
```

### Input with Focus Ring
```tsx
<input
  type="text"
  className="border-2 border-gray-300 rounded-lg px-4 py-2 focus-brand"
  placeholder="Search..."
/>
```

## Full Page Examples

### Article List Page
```tsx
'use client';
import { useState, useEffect } from 'react';
import { SkeletonCard } from '@/components/ui';

export default function ArticlesPage() {
  const [loading, setLoading] = useState(true);
  const [articles, setArticles] = useState([]);

  useEffect(() => {
    fetch('/api/articles')
      .then(res => res.json())
      .then(data => {
        setArticles(data);
        setLoading(false);
      });
  }, []);

  if (loading) {
    return (
      <div className="container-deschide py-12">
        <h1 className="text-4xl font-bold text-brand-oxford-900 mb-8">Latest Articles</h1>
        <SkeletonGrid variant="article" count={6} columns={{ sm: 1, md: 2, lg: 3 }} />
      </div>
    );
  }

  return (
    <div className="container-deschide py-12">
      <h1 className="text-4xl font-bold text-brand-oxford-900 mb-8 animate-fade-in-up">
        Latest Articles
      </h1>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {articles.map((article, i) => (
          <div key={article.id} className={`animate-fade-in-up stagger-${Math.min(i + 1, 6)}`}>
            <ArticleCard article={article} />
          </div>
        ))}
      </div>
    </div>
  );
}
```

### Article Card Component
```tsx
function ArticleCard({ article }) {
  return (
    <article className="bg-white rounded-lg overflow-hidden shadow-sm hover-lift">
      {/* Image */}
      <div className="hover-image-zoom">
        <img
          src={article.imageUrl}
          alt={article.title}
          className="w-full h-48 object-cover"
        />
      </div>

      {/* Content */}
      <div className="p-4">
        {/* Category */}
        {article.category && (
          <span className="inline-block px-3 py-1 bg-brand-tomato-100 text-brand-tomato-600 text-sm font-semibold rounded">
            {article.category.name}
          </span>
        )}

        {/* Breaking Badge */}
        {article.isBreaking && (
          <div className="breaking-news-badge mt-2 mb-3 inline-flex">
            Breaking
          </div>
        )}

        {/* Title */}
        <h3 className="mt-2 text-xl font-bold text-brand-oxford-900 underline-animate">
          <a href={`/articles/${article.slug}`}>
            {article.title}
          </a>
        </h3>

        {/* Excerpt */}
        <p className="mt-2 text-gray-600 line-clamp-3">
          {article.excerpt}
        </p>

        {/* Meta */}
        <div className="mt-4 flex items-center gap-3 text-sm text-gray-500">
          <div className="flex items-center gap-2">
            {article.author.avatar && (
              <img
                src={article.author.avatar}
                alt={article.author.name}
                className="w-6 h-6 rounded-full"
              />
            )}
            <span>{article.author.name}</span>
          </div>
          <span>•</span>
          <time dateTime={article.publishedAt}>
            {formatDate(article.publishedAt)}
          </time>
        </div>
      </div>
    </article>
  );
}
```

### Homepage Hero
```tsx
function HomePage({ heroArticle, latestArticles }) {
  return (
    <>
      {/* Hero Section */}
      <section className="relative overflow-hidden mb-12 animate-fade-in">
        <div className="hover-image-zoom">
          <img
            src={heroArticle.imageUrl}
            alt={heroArticle.title}
            className="w-full h-[500px] object-cover"
          />
        </div>

        <div className="absolute inset-0 card-overlay-gradient" />

        <div className="absolute bottom-0 left-0 right-0">
          <div className="container-deschide pb-12">
            <div className="animate-fade-in-up">
              {heroArticle.isBreaking && (
                <div className="breaking-news-badge mb-4">
                  Breaking News
                </div>
              )}
              <h1 className="text-5xl font-bold text-white text-on-photo-strong mb-4">
                {heroArticle.title}
              </h1>
            </div>
            <p className="text-xl text-white text-on-photo max-w-3xl animate-fade-in-up stagger-1">
              {heroArticle.excerpt}
            </p>
          </div>
        </div>
      </section>

      {/* Latest Articles */}
      <section className="container-deschide mb-12">
        <h2 className="text-3xl font-bold text-brand-oxford-900 mb-6 animate-fade-in-up">
          Latest Articles
        </h2>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {latestArticles.map((article, i) => (
            <div key={article.id} className={`animate-fade-in-up stagger-${Math.min(i + 1, 6)}`}>
              <ArticleCard article={article} />
            </div>
          ))}
        </div>
      </section>
    </>
  );
}
```

## Common Class Combinations

### Premium Card
```
bg-white rounded-lg shadow-sm hover-lift overflow-hidden
```

### Card Image Container
```
hover-image-zoom
```

### Card Title Link
```
text-xl font-bold text-brand-oxford-900 underline-animate
```

### Breaking Badge
```
breaking-news-badge
```

### Hero Overlay
```
absolute inset-0 card-overlay-gradient
```

### Hero Title
```
text-white text-on-photo-strong
```

### Section Title
```
text-3xl font-bold text-brand-oxford-900 mb-6 animate-fade-in-up
```

### Button Primary
```
btn-brand-primary focus-brand
```

### Grid Container
```
grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6
```

### Staggered Item
```
animate-fade-in-up stagger-1
```

## Pro Tips

1. **Always use `overflow-hidden`** on cards with `hover-image-zoom`
2. **Limit stagger to 6 items** for best UX
3. **Add `will-animate`** to frequently animated elements
4. **Use `focus-brand`** on all interactive elements
5. **Combine `hover-lift` + `hover-image-zoom`** for premium feel
6. **Use `text-on-photo-strong`** for hero titles
7. **Always test with reduced motion**

## Import Cheatsheet

```tsx
// Top of file
import {
  LoadingSpinner,
  LoadingOverlay,
  InlineLoader,
  PulseLoader,
  SkeletonCard,
  SkeletonGrid
} from '@/components/ui';
```

## Quick Test

Visit `/[locale]/demo-animations` to see all animations in action!
