# Premium UI Components - Animations & Interactions

Brand-aligned UI components with premium animations and micro-interactions for the Deschide News platform.

## Overview

This directory contains reusable UI components that implement the Deschide brand design system with sophisticated animations and interactions. All components are:

- **Brand-aligned**: Using Oxford Blue, Tomato, and Red brand colors
- **Performance-optimized**: GPU-accelerated animations (transform/opacity only)
- **Accessible**: ARIA labels, screen reader support, reduced motion support
- **Type-safe**: Full TypeScript support with exported interfaces
- **Responsive**: Mobile-first design

## Components

### LoadingSpinner

Premium loading spinners using Deschide brand colors.

#### Variants

```tsx
import { LoadingSpinner, LoadingOverlay, InlineLoader, PulseLoader } from '@/components/ui';

// Standard spinner (default: Tomato)
<LoadingSpinner />

// Sizes: sm, md (default), lg, xl
<LoadingSpinner size="lg" />

// Color variants: tomato (default), oxford, red
<LoadingSpinner variant="oxford" />

// With text
<LoadingSpinner size="lg" text="Loading articles..." />

// Full-screen overlay
<LoadingOverlay text="Uploading image..." />
<LoadingOverlay fullScreen opacity={80} />

// Inline loader (for buttons)
<button disabled>
  <InlineLoader variant="white" className="mr-2" />
  Saving...
</button>

// Pulse loader (three dots)
<PulseLoader variant="tomato" />
```

#### Props

**LoadingSpinner**:
- `size?: 'sm' | 'md' | 'lg' | 'xl'` - Size variant (default: 'md')
- `text?: string` - Optional loading text
- `variant?: 'tomato' | 'oxford' | 'red'` - Color variant (default: 'tomato')
- `className?: string` - Additional CSS classes

**LoadingOverlay**:
- Extends LoadingSpinner props
- `opacity?: number` - Background opacity 0-100 (default: 80)
- `fullScreen?: boolean` - Fixed full-screen overlay (default: false)

**InlineLoader**:
- `variant?: 'tomato' | 'oxford' | 'red' | 'white'` - Color variant
- `className?: string` - Additional CSS classes

**PulseLoader**:
- `variant?: 'tomato' | 'oxford' | 'red'` - Color variant
- `className?: string` - Additional CSS classes

---

### SkeletonCard

Premium skeleton loading states for article cards with Oxford Blue gradient.

#### Variants

```tsx
import { SkeletonCard, SkeletonGrid } from '@/components/ui';

// Article card (standard vertical)
<SkeletonCard variant="article" />

// Hero card (large featured)
<SkeletonCard variant="hero" />

// Compact card (small list items)
<SkeletonCard variant="compact" />

// Horizontal card (wide layout)
<SkeletonCard variant="horizontal" />

// List item (minimal, text-focused)
<SkeletonCard variant="list" />

// Multiple cards with staggered animation
<SkeletonCard variant="article" count={3} />

// Without image
<SkeletonCard variant="article" showImage={false} />

// Without category badge
<SkeletonCard variant="article" showCategory={false} />

// Responsive grid
<SkeletonGrid
  variant="article"
  count={6}
  columns={{ sm: 1, md: 2, lg: 3 }}
/>
```

#### Individual Skeleton Components

```tsx
import {
  ArticleCardSkeleton,
  HeroCardSkeleton,
  CompactCardSkeleton,
  HorizontalCardSkeleton,
  ListItemSkeleton
} from '@/components/ui';

// Use individual components for more control
<ArticleCardSkeleton showImage={true} showCategory={true} />
<HeroCardSkeleton />
<CompactCardSkeleton showImage={false} />
<HorizontalCardSkeleton showCategory={true} />
<ListItemSkeleton />
```

#### Props

**SkeletonCard**:
- `variant?: 'article' | 'hero' | 'compact' | 'list' | 'horizontal'` - Layout variant
- `count?: number` - Number of skeleton cards (default: 1)
- `showImage?: boolean` - Show image skeleton (default: true)
- `showCategory?: boolean` - Show category badge skeleton (default: true)
- `className?: string` - Additional CSS classes

**SkeletonGrid**:
- Extends SkeletonCard props
- `columns?: { sm?: number; md?: number; lg?: number }` - Grid columns per breakpoint

---

## Animation Utilities (globals.css)

All animations are available as utility classes in `globals.css`:

### Fade Animations

```tsx
className="animate-fade-in"        // Fade in (0.3s)
className="animate-fade-in-up"     // Fade in from below (0.5s)
className="animate-fade-in-down"   // Fade in from above (0.5s)
```

### Slide Animations

```tsx
className="animate-slide-in-right" // Slide in from right (0.3s)
className="animate-slide-in-left"  // Slide in from left (0.3s)
```

### Scale Animations

```tsx
className="animate-scale-in"       // Scale up fade in (0.3s)
```

### Brand Animations

```tsx
className="animate-brand-pulse"    // Pulse for breaking news (2s infinite)
className="animate-shimmer"        // Shimmer effect (2s infinite)
```

### Stagger Delays

Use for sequential animations:

```tsx
className="animate-fade-in-up stagger-1"  // 0.1s delay
className="animate-fade-in-up stagger-2"  // 0.2s delay
className="animate-fade-in-up stagger-3"  // 0.3s delay
// ... up to stagger-6
```

### Hover Effects

```tsx
// Lift with brand shadow
className="hover-lift"       // Lift 4px with Oxford Blue shadow
className="hover-lift-sm"    // Lift 2px (smaller)

// Scale
className="hover-scale"      // Scale to 1.02
className="hover-scale-sm"   // Scale to 1.01

// Image zoom
className="hover-image-zoom" // Container for image
// Image inside scales to 1.05 on hover
```

### Focus States

```tsx
className="focus-brand"        // Tomato focus ring (3px)
className="focus-brand-oxford" // Oxford Blue focus ring (3px)
```

### Loading Skeletons

```tsx
className="skeleton-brand"      // Oxford Blue gradient (light)
className="skeleton-brand-dark" // Oxford Blue gradient (dark)
```

### Transitions

```tsx
className="transition-smooth" // 0.3s cubic-bezier
className="transition-fast"   // 0.2s cubic-bezier
className="opacity-transition" // Opacity fade to 0.8 on hover
```

### Premium Card Overlays

```tsx
className="card-overlay-gradient"        // Oxford Blue gradient overlay
className="card-overlay-gradient-tomato" // Tomato gradient overlay
```

### Breaking News Badge

```tsx
className="breaking-news-badge"
// Includes pulsing dot animation
```

### Text Effects

```tsx
className="underline-animate"
// Animated underline on hover (Tomato color)
```

### Performance Optimization

```tsx
className="will-animate"
// Adds will-change: transform, opacity for smoother animations
```

---

## Usage Examples

### Loading State Pattern

```tsx
'use client';

import { useState, useEffect } from 'react';
import { LoadingSpinner, SkeletonCard } from '@/components/ui';

function ArticleList() {
  const [loading, setLoading] = useState(true);
  const [articles, setArticles] = useState([]);

  useEffect(() => {
    fetchArticles().then((data) => {
      setArticles(data);
      setLoading(false);
    });
  }, []);

  if (loading) {
    return <SkeletonCard variant="article" count={6} />;
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      {articles.map((article) => (
        <ArticleCard key={article.id} article={article} />
      ))}
    </div>
  );
}
```

### Staggered Animation

```tsx
function ArticleGrid({ articles }) {
  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
      {articles.map((article, index) => (
        <div
          key={article.id}
          className={`animate-fade-in-up stagger-${Math.min(index + 1, 6)}`}
        >
          <ArticleCard article={article} />
        </div>
      ))}
    </div>
  );
}
```

### Button with Loading State

```tsx
'use client';

import { useState } from 'react';
import { InlineLoader } from '@/components/ui';

function SaveButton() {
  const [saving, setSaving] = useState(false);

  const handleSave = async () => {
    setSaving(true);
    await saveData();
    setSaving(false);
  };

  return (
    <button
      onClick={handleSave}
      disabled={saving}
      className="btn-brand-primary flex items-center gap-2"
    >
      {saving && <InlineLoader variant="white" />}
      {saving ? 'Saving...' : 'Save'}
    </button>
  );
}
```

### Hero Section with Animations

```tsx
function HeroSection({ article }) {
  return (
    <div className="relative overflow-hidden">
      {/* Image with zoom on hover */}
      <div className="hover-image-zoom">
        <img
          src={article.imageUrl}
          alt={article.title}
          className="w-full h-96 object-cover"
        />
      </div>

      {/* Gradient overlay */}
      <div className="absolute inset-0 card-overlay-gradient" />

      {/* Content with animations */}
      <div className="absolute bottom-0 left-0 right-0 p-8">
        <div className="animate-fade-in-up">
          {article.isBreaking && (
            <div className="breaking-news-badge mb-4">
              Breaking News
            </div>
          )}
          <h1 className="text-4xl font-bold text-white text-on-photo-strong">
            {article.title}
          </h1>
        </div>
        <p className="text-white text-on-photo mt-4 animate-fade-in-up stagger-1">
          {article.excerpt}
        </p>
      </div>
    </div>
  );
}
```

### Card with Hover Effects

```tsx
function ArticleCard({ article }) {
  return (
    <article className="bg-white rounded-lg overflow-hidden shadow-sm hover-lift">
      <div className="hover-image-zoom">
        <img
          src={article.imageUrl}
          alt={article.title}
          className="w-full h-48 object-cover"
        />
      </div>
      <div className="p-4">
        <h3 className="text-xl font-bold text-brand-oxford underline-animate">
          <a href={article.url}>{article.title}</a>
        </h3>
        <p className="mt-2 text-gray-600">{article.excerpt}</p>
      </div>
    </article>
  );
}
```

---

## Accessibility Features

All components support:

1. **ARIA Labels**: Proper `role="status"` and `aria-label` attributes
2. **Screen Readers**: Hidden text for screen reader announcements
3. **Reduced Motion**: Respects `prefers-reduced-motion` setting
4. **Keyboard Navigation**: Focus states with brand colors
5. **Live Regions**: `aria-live` for dynamic content updates

---

## Performance Considerations

1. **GPU Acceleration**: All animations use `transform` and `opacity` only
2. **Will-Change**: Applied to frequently animated elements
3. **Animation Duration**: Kept short (200-500ms) for responsiveness
4. **Reduced Motion**: Animations disabled for users who prefer reduced motion
5. **Lazy Loading**: Skeleton states prevent layout shifts

---

## Design Specifications

### Animation Timings
- **Micro-interactions**: 200-300ms
- **Fade/Slide**: 300-500ms
- **Breaking news pulse**: 2s infinite
- **Skeleton loading**: 1.5s infinite

### Brand Colors
- **Tomato** (#F05E45): Primary loading indicator, focus rings
- **Oxford Blue** (#112240): Skeleton gradients, shadows
- **Red CMYK** (#E92628): Breaking news badges

### Shadows
- **Hover lift**: `0 10px 30px rgba(17, 34, 64, 0.15)`
- **Hover lift small**: `0 6px 20px rgba(17, 34, 64, 0.12)`
- **Focus ring**: `0 0 0 3px rgba(240, 94, 69, 0.4)`

---

## Browser Support

- Chrome/Edge: 90+
- Firefox: 88+
- Safari: 14+
- Mobile browsers: iOS 14+, Android 90+

All modern browsers with CSS Grid, Flexbox, and CSS Animations support.
