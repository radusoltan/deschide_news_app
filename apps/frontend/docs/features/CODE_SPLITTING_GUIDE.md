# Code Splitting & Performance Optimization Guide

This guide explains the code splitting and performance optimization strategies implemented in the LiveText feature.

## Overview

Code splitting is automatically handled by Next.js 16, but we've implemented additional optimizations:

1. **Dynamic Imports** for heavy components
2. **Route-based splitting** (automatic with App Router)
3. **Component-level splitting** for analytics charts
4. **Lazy loading** for images and media
5. **Virtual scrolling** for large lists

---

## Dynamic Imports

### When to Use

Use dynamic imports for:
- Components that are not immediately visible
- Heavy dependencies (charts, markdown editors, etc.)
- Admin-only components
- Modal/Dialog content

### Example Usage

```typescript
// Instead of:
import { HeavyChart } from './HeavyChart';

// Use:
import dynamic from 'next/dynamic';

const HeavyChart = dynamic(() => import('./HeavyChart'), {
  loading: () => <div>Loading chart...</div>,
  ssr: false // Disable SSR if not needed
});
```

### Real-World Examples

#### Analytics Dashboard Charts

```typescript
// app/[locale]/admin/live-texts/[id]/analytics/components/Charts.tsx
import dynamic from 'next/dynamic';

// Split chart libraries into separate chunks
export const ViewsOverTimeChart = dynamic(
  () => import('./ViewsOverTimeChart'),
  { ssr: false }
);

export const PostEngagementTable = dynamic(
  () => import('./PostEngagementTable'),
  { ssr: false }
);

export const ViewersByPlatformChart = dynamic(
  () => import('./ViewersByPlatformChart'),
  { ssr: false }
);
```

#### Modal Components

```typescript
// Only load modal when it's opened
const NotificationSettingsModal = dynamic(
  () => import('./NotificationSettingsModal'),
  { ssr: false }
);

function Toolbar() {
  const [showSettings, setShowSettings] = useState(false);

  return (
    <>
      <button onClick={() => setShowSettings(true)}>Settings</button>
      {showSettings && <NotificationSettingsModal />}
    </>
  );
}
```

---

## Route-Based Code Splitting

Next.js App Router automatically splits code by route. Each page is its own chunk.

### Directory Structure

```
app/
├── [locale]/
│   ├── live/
│   │   └── [slug]/
│   │       └── page.tsx          # Chunk: live-slug
│   └── admin/
│       └── live-texts/
│           ├── page.tsx           # Chunk: admin-live-texts
│           └── [id]/
│               └── analytics/
│                   └── page.tsx   # Chunk: admin-analytics
```

### Benefits

- Users only download code for routes they visit
- Parallel loading of route chunks
- Automatic prefetching on link hover

---

## Component-Level Optimization

### 1. Virtual Scrolling

For large lists (100+ items), use `VirtualScroll` component:

```typescript
import { VirtualScroll } from '@/lib/components/VirtualScroll';

<VirtualScroll
  items={posts}
  itemCount={posts.length}
  itemHeight={200}
  renderItem={(post) => <PostCard post={post} />}
  onLoadMore={loadMore}
/>
```

**Benefits**:
- Only renders visible items + buffer
- Dramatically reduces DOM nodes
- Smooth scrolling with 1000+ items

### 2. Lazy Loading Images

Use `LazyImage` component for all images:

```typescript
import { LazyImage } from '@/lib/components/LazyImage';

<LazyImage
  src="/images/large-image.jpg"
  alt="Description"
  className="w-full h-64 object-cover"
  threshold={0.1}
  rootMargin="50px"
/>
```

**Benefits**:
- Images load only when entering viewport
- Reduces initial page load
- Blur-up placeholder effect
- Loading skeletons

### 3. React.memo for Static Components

Prevent unnecessary re-renders:

```typescript
import { memo } from 'react';

export const PostCard = memo(function PostCard({ post }) {
  return <div>{post.title}</div>;
}, (prevProps, nextProps) => {
  // Custom comparison
  return prevProps.post.id === nextProps.post.id;
});
```

---

## Bundle Size Optimization

### Analyze Bundle

```bash
# Build with analysis
ANALYZE=true pnpm build

# Open analyzer
open .next/analyze/client.html
```

### Common Optimizations

#### 1. Tree Shaking

Only import what you need:

```typescript
// ❌ Bad: Imports entire library
import _ from 'lodash';

// ✅ Good: Imports only needed function
import debounce from 'lodash/debounce';
```

#### 2. Remove Unused Dependencies

```bash
# Find unused dependencies
npx depcheck

# Remove them
pnpm remove unused-package
```

#### 3. Use Next.js Image Component

```typescript
import Image from 'next/image';

<Image
  src="/image.jpg"
  alt="Description"
  width={800}
  height={600}
  loading="lazy"
/>
```

---

## Performance Monitoring

### 1. Lighthouse

```bash
# Run Lighthouse audit
npx lighthouse http://localhost:3005/ro/live/some-slug --view
```

**Target Scores**:
- Performance: 90+
- Accessibility: 95+
- Best Practices: 95+
- SEO: 100

### 2. Web Vitals

Monitor Core Web Vitals:

```typescript
// app/layout.tsx
import { Analytics } from '@vercel/analytics/react';
import { SpeedInsights } from '@vercel/speed-insights/next';

export default function RootLayout({ children }) {
  return (
    <html>
      <body>
        {children}
        <Analytics />
        <SpeedInsights />
      </body>
    </html>
  );
}
```

### 3. Bundle Analyzer

Check bundle sizes regularly:

```bash
pnpm build
ls -lh .next/static/chunks/
```

**Target Sizes**:
- Initial JS: < 200 KB
- First Load JS: < 300 KB
- Route chunks: < 100 KB each

---

## Best Practices

### ✅ Do

- Use dynamic imports for heavy components
- Implement virtual scrolling for large lists
- Lazy load images and media
- Split routes logically
- Monitor bundle sizes
- Use React.memo for expensive components
- Implement code splitting boundaries at route level

### ❌ Don't

- Import entire libraries when you only need specific functions
- Load all components upfront
- Render all list items at once
- Use inline styles extensively (impacts serialization)
- Import heavy dependencies in shared components
- Forget to check bundle size after adding dependencies

---

## Next.js 16 Specific Optimizations

### Turbopack (Development)

Already enabled by default in Next.js 16:

```json
// package.json
{
  "scripts": {
    "dev": "next dev --turbo"
  }
}
```

### Server Components

Use Server Components by default:

```typescript
// app/page.tsx (Server Component by default)
export default async function Page() {
  const data = await fetchData();
  return <ClientComponent data={data} />;
}

// components/ClientComponent.tsx
'use client';  // Only mark as client when needed

export function ClientComponent({ data }) {
  const [state, setState] = useState(data);
  return <div>{state}</div>;
}
```

---

## Optimization Checklist

- [ ] Dynamic imports for admin panels
- [ ] Virtual scrolling for post lists
- [ ] Lazy loading for all images
- [ ] Code splitting for chart libraries
- [ ] Tree-shaking unused imports
- [ ] React.memo for expensive renders
- [ ] Server Components where possible
- [ ] Bundle size monitoring
- [ ] Lighthouse score > 90
- [ ] Core Web Vitals passing

---

## Resources

- [Next.js Code Splitting](https://nextjs.org/docs/app/building-your-application/optimizing/lazy-loading)
- [React.lazy](https://react.dev/reference/react/lazy)
- [Web Vitals](https://web.dev/vitals/)
- [Bundle Analyzer](https://www.npmjs.com/package/@next/bundle-analyzer)

---

**Last Updated**: 2025-11-03
**Status**: Production Ready ✅
