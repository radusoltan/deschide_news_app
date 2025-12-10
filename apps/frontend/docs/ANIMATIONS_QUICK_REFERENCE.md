# Premium Animations Quick Reference

Fast lookup guide for Deschide brand animations and UI components.

## Quick Imports

```tsx
// All UI components
import {
  LoadingSpinner,
  LoadingOverlay,
  InlineLoader,
  PulseLoader,
  SkeletonCard,
  SkeletonGrid
} from '@/components/ui';
```

## Loading Spinners (Tomato)

| Component | Usage | Props |
|-----------|-------|-------|
| `<LoadingSpinner />` | Standard spinner | `size`, `variant`, `text` |
| `<LoadingOverlay />` | Full-screen overlay | + `opacity`, `fullScreen` |
| `<InlineLoader />` | Button/inline | `variant`, `className` |
| `<PulseLoader />` | Three-dot pulse | `variant`, `className` |

**Sizes**: `sm`, `md` (default), `lg`, `xl`
**Variants**: `tomato` (default), `oxford`, `red`, `white`

```tsx
<LoadingSpinner size="lg" text="Loading..." />
<InlineLoader variant="white" />
<PulseLoader variant="tomato" />
```

## Skeleton States (Oxford Blue)

| Component | Usage | Best For |
|-----------|-------|----------|
| `<SkeletonCard variant="article" />` | Vertical card | Standard articles |
| `<SkeletonCard variant="hero" />` | Large featured | Hero sections |
| `<SkeletonCard variant="compact" />` | Small list | Sidebar lists |
| `<SkeletonCard variant="horizontal" />` | Wide layout | Category pages |
| `<SkeletonCard variant="list" />` | Minimal text | Text lists |

```tsx
// Multiple with stagger
<SkeletonCard variant="article" count={6} />

// Grid layout
<SkeletonGrid variant="article" count={6} columns={{ sm: 1, md: 2, lg: 3 }} />
```

## Animation Classes

### Entrance Animations

```tsx
className="animate-fade-in"         // 0.3s fade in
className="animate-fade-in-up"      // 0.5s from below
className="animate-fade-in-down"    // 0.5s from above
className="animate-slide-in-right"  // 0.3s from right
className="animate-slide-in-left"   // 0.3s from left
className="animate-scale-in"        // 0.3s scale up
```

### Brand Animations

```tsx
className="animate-brand-pulse"     // 2s infinite pulse (breaking news)
className="animate-shimmer"         // 2s infinite shimmer
```

### Stagger Delays

```tsx
// Add sequential delays to list items
className="animate-fade-in-up stagger-1"  // +0.1s
className="animate-fade-in-up stagger-2"  // +0.2s
className="animate-fade-in-up stagger-3"  // +0.3s
// ... up to stagger-6
```

## Hover Effects

```tsx
className="hover-lift"          // Lift 4px + Oxford shadow
className="hover-lift-sm"       // Lift 2px + smaller shadow
className="hover-scale"         // Scale to 1.02
className="hover-scale-sm"      // Scale to 1.01
className="hover-image-zoom"    // Container for image zoom
className="opacity-transition"  // Fade to 0.8
```

## Focus States

```tsx
className="focus-brand"         // Tomato ring (3px)
className="focus-brand-oxford"  // Oxford Blue ring (3px)
```

## Skeletons

```tsx
className="skeleton-brand"       // Oxford Blue gradient (light)
className="skeleton-brand-dark"  // Oxford Blue gradient (dark)
```

## Special Effects

```tsx
// Breaking news badge with pulse
className="breaking-news-badge"

// Animated underline (Tomato)
className="underline-animate"

// Card overlays
className="card-overlay-gradient"         // Oxford Blue
className="card-overlay-gradient-tomato"  // Tomato

// Transitions
className="transition-smooth"   // 0.3s cubic-bezier
className="transition-fast"     // 0.2s cubic-bezier

// Performance
className="will-animate"        // GPU acceleration hint
```

## Common Patterns

### Loading State

```tsx
{loading ? (
  <SkeletonCard variant="article" count={6} />
) : (
  <ArticleGrid articles={articles} />
)}
```

### Button Loading

```tsx
<button disabled={saving} className="btn-brand-primary">
  {saving && <InlineLoader variant="white" className="mr-2" />}
  {saving ? 'Saving...' : 'Save'}
</button>
```

### Staggered List

```tsx
{articles.map((article, i) => (
  <div key={article.id} className={`animate-fade-in-up stagger-${Math.min(i + 1, 6)}`}>
    <ArticleCard article={article} />
  </div>
))}
```

### Premium Card

```tsx
<article className="bg-white rounded-lg shadow-sm hover-lift">
  <div className="hover-image-zoom">
    <img src={image} alt={title} />
  </div>
  <div className="p-4">
    <h3 className="underline-animate">
      <a href={url}>{title}</a>
    </h3>
  </div>
</article>
```

### Hero with Overlay

```tsx
<div className="relative">
  <div className="hover-image-zoom">
    <img src={hero} alt="Hero" />
  </div>
  <div className="absolute inset-0 card-overlay-gradient" />
  <div className="absolute bottom-0 p-8">
    {isBreaking && <div className="breaking-news-badge">Breaking</div>}
    <h1 className="text-white text-on-photo-strong">{title}</h1>
  </div>
</div>
```

## Brand Colors Reference

```tsx
// Oxford Blue (Primary)
className="text-brand-oxford"      // #112240
className="bg-brand-oxford"

// Tomato (Secondary)
className="text-brand-tomato"      // #F05E45
className="bg-brand-tomato"

// Red (Accent - NEVER for text)
className="bg-brand-red"           // #E92628

// Gradients
className="gradient-oxford-tomato"
className="gradient-tomato-red"
className="gradient-oxford-soft"
className="gradient-tomato-soft"
```

## Performance Tips

1. Use `transform` and `opacity` only for animations
2. Add `will-animate` for frequently animated elements
3. Keep animation duration short (200-500ms)
4. Use stagger delays for lists (max 6 items)
5. Animations auto-disable for `prefers-reduced-motion`

## Accessibility

- All loaders have `role="status"` and `aria-live`
- Screen reader text via `sr-only` class
- Keyboard focus with brand focus rings
- Reduced motion support built-in

## Demo Page

Visit `/[locale]/demo-animations` to see all components and animations in action.

## File Locations

- **Animations**: `/var/www/deschide_news_app/apps/frontend/app/globals.css`
- **Tailwind Config**: `/var/www/deschide_news_app/apps/frontend/tailwind.config.ts`
- **Components**: `/var/www/deschide_news_app/apps/frontend/components/ui/`
- **Full Docs**: `/var/www/deschide_news_app/apps/frontend/components/ui/README.md`
