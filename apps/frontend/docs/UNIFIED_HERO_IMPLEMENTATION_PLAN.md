# Unified Hero Section Implementation Plan

**Document Version**: 1.0
**Date**: December 17, 2025
**Status**: Planning Phase
**Priority**: High

---

## Executive Summary

This document outlines a comprehensive implementation plan for merging the current separate Hero Article and Special Articles (Breaking/Alert/Flash) sections into a unified, dynamic hero component system. The goal is to reduce vertical space consumption while creating a more dramatic, engaging above-the-fold experience that adapts based on news urgency levels.

---

## 1. Current State Analysis

### 1.1 Existing Components

| Component | Location | Purpose |
|-----------|----------|---------|
| `HeroArticle.tsx` | `/components/home/HeroArticle.tsx` | Full-bleed hero with important article |
| `SpecialArticlesSection.tsx` | `/components/special/SpecialArticlesSection.tsx` | Container for special articles |
| `SpecialArticleBanner.tsx` | `/components/special/SpecialArticleBanner.tsx` | Individual special article banners |

### 1.2 Current Homepage Structure

```
+------------------------------------------+
|           HeroArticle (60-70vh)          |
|   - Full-bleed image                     |
|   - Title, lead, CTA                     |
+------------------------------------------+
|      SpecialArticlesSection (100px+)     |
|   - Breaking News Banner                 |
|   - Alert Banner                         |
|   - Flash Banner                         |
+------------------------------------------+
|         Rest of Homepage...              |
+------------------------------------------+
```

### 1.3 Problems with Current Approach

1. **Vertical Space Waste**: Two separate sections consume ~80vh on desktop
2. **Disjointed Experience**: Breaking news feels disconnected from hero
3. **Missed Opportunity**: Breaking news should BE the hero when available
4. **Inconsistent Hierarchy**: Most urgent news may appear below less urgent
5. **Mobile Inefficiency**: Stacking creates excessive scrolling

---

## 2. Proposed Solution

### 2.1 Unified Hero Concept

Create a single `UnifiedHero` component that dynamically renders different templates based on available content and urgency:

```
Priority Decision Logic:
1. BREAKING news exists? -> BreakingNewsHero (highest visual impact)
2. ALERT news exists?    -> AlertHero (urgent but less dramatic)
3. FLASH news exists?    -> FlashHero (quick updates style)
4. Default              -> StandardHero (current important article)
```

### 2.2 New Homepage Structure

```
+------------------------------------------+
|        UnifiedHero (50-70vh)             |
|   Dynamically renders one of:            |
|   - BreakingNewsHero                     |
|   - AlertHero                            |
|   - FlashHero                            |
|   - StandardHero                         |
|   + Sidebar with secondary stories       |
+------------------------------------------+
|         Rest of Homepage...              |
+------------------------------------------+
```

---

## 3. Component Architecture

### 3.1 File Structure

```
components/
  hero/
    UnifiedHero.tsx              # Main orchestrator component
    templates/
      BreakingNewsHero.tsx       # Breaking news template
      AlertHero.tsx              # Alert news template
      FlashHero.tsx              # Flash news template
      StandardHero.tsx           # Standard important article
    partials/
      HeroImage.tsx              # Shared image component
      HeroContent.tsx            # Shared content wrapper
      HeroMeta.tsx               # Shared meta information
      HeroBadge.tsx              # Dynamic urgency badge
      HeroSecondaryStories.tsx   # Sidebar stories panel
      HeroTicker.tsx             # Optional scrolling ticker
    animations/
      BreakingPulse.tsx          # Breaking news pulse animation
      AlertGlow.tsx              # Alert glow effect
      FlashStrobe.tsx            # Flash quick animation
    index.ts                     # Barrel exports
    types.ts                     # TypeScript interfaces
    constants.ts                 # Configuration constants
```

### 3.2 Component Interfaces

```typescript
// types.ts

export type HeroVariant = 'breaking' | 'alert' | 'flash' | 'standard';

export interface HeroArticle {
  id: number;
  title: string;
  slug: string;
  lead?: string;
  publishedAt?: string;
  badge?: HeroVariant;
  category?: {
    id: number;
    title: string;
    slug: string;
  };
  articleImages?: Array<{
    id: number;
    image: {
      path: string;
      alt?: string;
      width?: number;
      height?: number;
    };
    isFeatured: boolean;
  }>;
}

export interface UnifiedHeroProps {
  /** Primary article for hero display */
  primaryArticle: HeroArticle;
  /** Secondary articles for sidebar (max 3) */
  secondaryArticles?: HeroArticle[];
  /** Override variant (useful for testing) */
  forceVariant?: HeroVariant;
  /** Locale for translations */
  locale: string;
  /** Show ticker with additional headlines */
  showTicker?: boolean;
  /** Additional headlines for ticker */
  tickerHeadlines?: Array<{ title: string; slug: string }>;
}

export interface HeroTemplateProps {
  article: HeroArticle;
  locale: string;
  className?: string;
}
```

### 3.3 Main Orchestrator Component

```tsx
// UnifiedHero.tsx

'use client';

import React from 'react';
import { BreakingNewsHero } from './templates/BreakingNewsHero';
import { AlertHero } from './templates/AlertHero';
import { FlashHero } from './templates/FlashHero';
import { StandardHero } from './templates/StandardHero';
import { HeroSecondaryStories } from './partials/HeroSecondaryStories';
import { HeroTicker } from './partials/HeroTicker';
import { UnifiedHeroProps, HeroVariant } from './types';

// Determine which template to render based on article badge
function getHeroVariant(badge?: string): HeroVariant {
  if (badge === 'breaking') return 'breaking';
  if (badge === 'alert') return 'alert';
  if (badge === 'flash') return 'flash';
  return 'standard';
}

// Template component mapping
const HERO_TEMPLATES = {
  breaking: BreakingNewsHero,
  alert: AlertHero,
  flash: FlashHero,
  standard: StandardHero,
} as const;

export const UnifiedHero: React.FC<UnifiedHeroProps> = ({
  primaryArticle,
  secondaryArticles = [],
  forceVariant,
  locale,
  showTicker = false,
  tickerHeadlines = [],
}) => {
  const variant = forceVariant || getHeroVariant(primaryArticle.badge);
  const HeroTemplate = HERO_TEMPLATES[variant];

  return (
    <section className="relative" aria-label="Featured news">
      {/* Optional Breaking News Ticker */}
      {showTicker && tickerHeadlines.length > 0 && (
        <HeroTicker headlines={tickerHeadlines} locale={locale} />
      )}

      {/* Main Hero Grid Layout */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-0 lg:gap-0">
        {/* Primary Hero - 8 columns on desktop */}
        <div className="lg:col-span-8">
          <HeroTemplate article={primaryArticle} locale={locale} />
        </div>

        {/* Secondary Stories Sidebar - 4 columns on desktop */}
        {secondaryArticles.length > 0 && (
          <div className="lg:col-span-4 hidden lg:block">
            <HeroSecondaryStories
              articles={secondaryArticles}
              locale={locale}
              variant={variant}
            />
          </div>
        )}
      </div>

      {/* Mobile Secondary Stories (below hero) */}
      {secondaryArticles.length > 0 && (
        <div className="lg:hidden mt-4">
          <HeroSecondaryStories
            articles={secondaryArticles}
            locale={locale}
            variant={variant}
            layout="horizontal"
          />
        </div>
      )}
    </section>
  );
};
```

---

## 4. Visual Design Specifications

### 4.1 Breaking News Hero

**Design Philosophy**: Maximum drama and urgency. Full-bleed red gradient, pulsing elements, bold typography.

```
Visual Characteristics:
- Background: Dark red gradient (#7f1d1d -> #450a0a) with image overlay
- Badge: Pulsing "BREAKING" pill with animated dot
- Typography: Largest size, white text with strong shadows
- Accent: Red border or glow effect
- Animation: Subtle pulse on badge, gentle image zoom on hover
```

**Color Palette:**
```css
--breaking-primary: #dc2626;      /* Red-600 */
--breaking-dark: #7f1d1d;         /* Red-900 */
--breaking-darker: #450a0a;       /* Red-950 */
--breaking-accent: #fca5a5;       /* Red-300 */
--breaking-pulse: rgba(239, 68, 68, 0.4);
```

**Layout Specifications:**
```
Desktop (>=1024px):
- Height: 60vh minimum, 70vh maximum
- Image: Full coverage with gradient overlay
- Content: Bottom-left aligned, max-width 800px
- Badge: Top-left corner, 16px from edges
- Title: 48-56px, font-weight 800
- Lead: 22-24px, font-weight 400

Mobile (<1024px):
- Height: 50vh minimum
- Title: 32-36px
- Lead: 18px
- Badge: Smaller, centered
```

**Micro-interactions:**
1. Badge pulsing animation (2s interval)
2. Image slow zoom on hover (transform: scale(1.03))
3. Title underline animation on hover
4. CTA button glow effect

### 4.2 Alert Hero

**Design Philosophy**: Authoritative and urgent, but not alarming. Navy/Oxford blue theme with amber accents.

```
Visual Characteristics:
- Background: Oxford blue gradient with subtle texture
- Badge: Amber "ALERT" pill with solid styling
- Typography: Large but slightly smaller than Breaking
- Accent: Amber/Orange highlights
- Animation: Subtle glow effect, no pulse
```

**Color Palette:**
```css
--alert-primary: #1e3a5f;         /* Oxford Blue */
--alert-dark: #112240;            /* Oxford Dark */
--alert-accent: #f59e0b;          /* Amber-500 */
--alert-accent-light: #fcd34d;    /* Amber-300 */
--alert-glow: rgba(245, 158, 11, 0.2);
```

**Layout Specifications:**
```
Desktop:
- Height: 55vh minimum, 65vh maximum
- Title: 44-52px, font-weight 700
- Lead: 20-22px

Mobile:
- Height: 45vh minimum
- Title: 28-32px
- Lead: 17px
```

**Micro-interactions:**
1. Badge subtle glow on mount
2. Image gentle parallax on scroll
3. Amber underline on title hover

### 4.3 Flash Hero

**Design Philosophy**: Quick, energetic, modern. Teal/Cyan theme suggesting speed and timeliness.

```
Visual Characteristics:
- Background: Dark with teal gradient accent
- Badge: Teal "FLASH" pill with bolt icon
- Typography: Medium-large, dynamic styling
- Accent: Electric teal highlights
- Animation: Quick slide-in, flash effect on badge
```

**Color Palette:**
```css
--flash-primary: #0d9488;         /* Teal-600 */
--flash-dark: #134e4a;            /* Teal-900 */
--flash-accent: #5eead4;          /* Teal-300 */
--flash-light: #99f6e4;           /* Teal-200 */
--flash-glow: rgba(94, 234, 212, 0.3);
```

**Layout Specifications:**
```
Desktop:
- Height: 50vh minimum, 60vh maximum
- Title: 40-48px, font-weight 700
- Lead: 18-20px

Mobile:
- Height: 40vh minimum
- Title: 26-30px
- Lead: 16px
```

**Micro-interactions:**
1. Badge flash animation on mount (quick strobe)
2. Lightning bolt icon animation
3. Quick fade-in for content elements

### 4.4 Standard Hero

**Design Philosophy**: Premium editorial feel. Current design refined with the Deschide brand colors.

```
Visual Characteristics:
- Background: Oxford blue base with image overlay
- Badge: Category-colored badge
- Typography: Premium editorial style
- Accent: Tomato brand color accents
- Animation: Smooth, elegant transitions
```

**Color Palette:**
```css
/* Uses existing Deschide brand colors */
--standard-primary: rgb(var(--brand-oxford-900));
--standard-accent: rgb(var(--brand-tomato-500));
--standard-light: rgb(var(--brand-mindaro-400));
```

---

## 5. Animation Specifications

### 5.1 Breaking News Animations

```css
/* Breaking Badge Pulse */
@keyframes breaking-pulse {
  0%, 100% {
    opacity: 1;
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
  }
  50% {
    opacity: 0.9;
    box-shadow: 0 0 20px 10px rgba(239, 68, 68, 0);
  }
}

/* Breaking Dot Animation */
@keyframes breaking-dot {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.5); opacity: 0.7; }
}

/* Breaking Background Shift */
@keyframes breaking-bg-shift {
  0% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
  100% { background-position: 0% 50%; }
}
```

### 5.2 Alert Animations

```css
/* Alert Glow Effect */
@keyframes alert-glow {
  0% { box-shadow: 0 0 5px rgba(245, 158, 11, 0.3); }
  100% { box-shadow: 0 0 20px rgba(245, 158, 11, 0.1); }
}

/* Alert Badge Entrance */
@keyframes alert-badge-enter {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
```

### 5.3 Flash Animations

```css
/* Flash Strobe Effect */
@keyframes flash-strobe {
  0%, 100% { opacity: 1; }
  10% { opacity: 0.8; }
  20% { opacity: 1; }
  30% { opacity: 0.9; }
}

/* Flash Bolt Animation */
@keyframes flash-bolt {
  0% { transform: translateY(-5px) scale(0.9); opacity: 0; }
  50% { transform: translateY(0) scale(1.1); opacity: 1; }
  100% { transform: translateY(0) scale(1); opacity: 1; }
}

/* Flash Quick Slide */
@keyframes flash-slide-in {
  from {
    opacity: 0;
    transform: translateX(-30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}
```

### 5.4 Shared Animations

```css
/* Content Fade In Up */
@keyframes hero-content-enter {
  from {
    opacity: 0;
    transform: translateY(40px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Image Ken Burns Effect */
@keyframes hero-ken-burns {
  from { transform: scale(1); }
  to { transform: scale(1.05); }
}

/* Staggered Animation Delays */
.hero-stagger-1 { animation-delay: 0.1s; }
.hero-stagger-2 { animation-delay: 0.2s; }
.hero-stagger-3 { animation-delay: 0.3s; }
.hero-stagger-4 { animation-delay: 0.4s; }
```

---

## 6. Responsive Design Strategy

### 6.1 Breakpoint Definitions

```typescript
const BREAKPOINTS = {
  mobile: '< 640px',      // Single column, compact
  tablet: '640px - 1023px', // Single column, expanded
  desktop: '>= 1024px',    // Split layout (8+4 columns)
  wide: '>= 1280px',       // Full Bento grid
};
```

### 6.2 Mobile Layout (< 640px)

```
+------------------------------------------+
|            Hero Image (50vh)             |
|  +------------------------------------+  |
|  |  [BADGE]                           |  |
|  |                                    |  |
|  |  Title (32px)                      |  |
|  |  Lead (truncated to 2 lines)       |  |
|  |  [CTA Button]                      |  |
|  +------------------------------------+  |
+------------------------------------------+
|     Secondary Stories (horizontal)       |
|  [Story 1] [Story 2] [Story 3]           |
+------------------------------------------+
```

### 6.3 Tablet Layout (640px - 1023px)

```
+------------------------------------------+
|            Hero Image (55vh)             |
|  +------------------------------------+  |
|  |  [BADGE]                           |  |
|  |                                    |  |
|  |  Title (36px)                      |  |
|  |  Lead (3 lines)                    |  |
|  |  Meta info  |  [CTA Button]        |  |
|  +------------------------------------+  |
+------------------------------------------+
|        Secondary Stories (2 cols)        |
|  +----------------+ +----------------+   |
|  |   Story 1      | |   Story 2      |   |
|  +----------------+ +----------------+   |
|  |           Story 3                 |   |
|  +-----------------------------------+   |
+------------------------------------------+
```

### 6.4 Desktop Layout (>= 1024px)

```
+------------------------------------------+
|  Hero (8 cols)        | Secondary (4)    |
|  +------------------+ | +-------------+  |
|  | [BADGE]          | | |  Story 1    |  |
|  |                  | | | [thumbnail] |  |
|  | Title (48px)     | | | Title       |  |
|  |                  | | +-------------+  |
|  | Lead paragraph   | | +-------------+  |
|  |                  | | |  Story 2    |  |
|  | Meta | [CTA]     | | | [thumbnail] |  |
|  +------------------+ | +-------------+  |
|        (60vh)         | +-------------+  |
|                       | |  Story 3    |  |
|                       | +-------------+  |
+------------------------------------------+
```

---

## 7. Secondary Stories Sidebar

### 7.1 Design Specifications

The secondary stories sidebar displays 2-3 related or also-important articles alongside the main hero.

```typescript
interface SecondaryStoriesProps {
  articles: HeroArticle[];
  locale: string;
  variant: HeroVariant;
  layout?: 'vertical' | 'horizontal';
}
```

### 7.2 Visual Design

**Desktop (Vertical Layout):**
```
+---------------------------+
| Background matches hero   |
| variant (darker shade)    |
+---------------------------+
|  Story Card 1             |
|  +---------------------+  |
|  | Thumbnail (16:9)    |  |
|  +---------------------+  |
|  | [Category Badge]    |  |
|  | Title (2-3 lines)   |  |
|  | Time ago            |  |
|  +---------------------+  |
|                           |
|  Story Card 2             |
|  +---------------------+  |
|  | ...                 |  |
|  +---------------------+  |
|                           |
|  Story Card 3             |
|  +---------------------+  |
|  | ...                 |  |
|  +---------------------+  |
+---------------------------+
```

**Mobile (Horizontal Scroll):**
```
+----------------------------------------------------------+
| < [Card 1] [Card 2] [Card 3] >                           |
+----------------------------------------------------------+
```

### 7.3 Styling by Hero Variant

| Variant | Background | Border | Text |
|---------|------------|--------|------|
| Breaking | `bg-red-950` | `border-l-red-500` | White |
| Alert | `bg-slate-900` | `border-l-amber-500` | White |
| Flash | `bg-teal-950` | `border-l-teal-400` | White |
| Standard | `bg-slate-800` | `border-l-tomato-500` | White |

---

## 8. Implementation Steps

### Phase 1: Component Structure (Week 1)

- [ ] Create new `components/hero/` directory structure
- [ ] Define TypeScript interfaces in `types.ts`
- [ ] Create constants and configuration in `constants.ts`
- [ ] Build shared partials:
  - [ ] `HeroImage.tsx` - Optimized image component
  - [ ] `HeroBadge.tsx` - Dynamic badge component
  - [ ] `HeroContent.tsx` - Content wrapper
  - [ ] `HeroMeta.tsx` - Meta information display

### Phase 2: Hero Templates (Week 1-2)

- [ ] Build `StandardHero.tsx` (refactor existing HeroArticle)
- [ ] Build `BreakingNewsHero.tsx` with red theme
- [ ] Build `AlertHero.tsx` with oxford/amber theme
- [ ] Build `FlashHero.tsx` with teal theme
- [ ] Create barrel exports in `index.ts`

### Phase 3: Secondary Stories (Week 2)

- [ ] Build `HeroSecondaryStories.tsx` component
- [ ] Implement vertical layout for desktop
- [ ] Implement horizontal scroll for mobile
- [ ] Style adaptation based on hero variant

### Phase 4: Animations (Week 2)

- [ ] Add animation keyframes to `globals.css`
- [ ] Implement `BreakingPulse.tsx`
- [ ] Implement `AlertGlow.tsx`
- [ ] Implement `FlashStrobe.tsx`
- [ ] Add reduced-motion support

### Phase 5: Main Orchestrator (Week 3)

- [ ] Build `UnifiedHero.tsx` component
- [ ] Implement variant detection logic
- [ ] Handle responsive layouts
- [ ] Add error boundaries

### Phase 6: Integration (Week 3)

- [ ] Update homepage (`page.tsx`) to use `UnifiedHero`
- [ ] Modify data fetching to combine important + special articles
- [ ] Update API calls in `lib/api/`
- [ ] Remove old `HeroArticle` and `SpecialArticlesSection` from homepage

### Phase 7: Testing & Polish (Week 4)

- [ ] Unit tests for variant selection logic
- [ ] Visual regression tests
- [ ] Performance testing (LCP, CLS)
- [ ] Accessibility audit
- [ ] Cross-browser testing
- [ ] Mobile device testing

---

## 9. Data Integration

### 9.1 Updated Homepage Data Flow

```typescript
// app/[locale]/(public)/page.tsx

export default async function HomePage({ params }: PageProps) {
  const { locale } = await params;

  // Fetch all potential hero articles
  const [importantResponse, specialArticles] = await Promise.all([
    fetchImportantArticles(locale),
    fetchAllSpecialArticles(locale, 3),
  ]);

  // Determine primary hero article
  let primaryHero: HeroArticle;
  let secondaryStories: HeroArticle[] = [];

  // Priority: Breaking > Alert > Flash > Important
  const breakingArticle = specialArticles.find(a => a.badge === 'breaking');
  const alertArticle = specialArticles.find(a => a.badge === 'alert');
  const flashArticle = specialArticles.find(a => a.badge === 'flash');
  const importantArticle = importantResponse.member?.[0]?.article;

  if (breakingArticle) {
    primaryHero = breakingArticle;
    secondaryStories = [
      alertArticle,
      flashArticle,
      importantArticle,
    ].filter(Boolean).slice(0, 3);
  } else if (alertArticle) {
    primaryHero = alertArticle;
    secondaryStories = [
      flashArticle,
      importantArticle,
      importantResponse.member?.[1]?.article,
    ].filter(Boolean).slice(0, 3);
  } else if (flashArticle) {
    primaryHero = flashArticle;
    secondaryStories = [
      importantArticle,
      importantResponse.member?.[1]?.article,
      importantResponse.member?.[2]?.article,
    ].filter(Boolean).slice(0, 3);
  } else {
    primaryHero = importantArticle;
    secondaryStories = importantResponse.member
      ?.slice(1, 4)
      .map(item => item.article)
      .filter(Boolean) || [];
  }

  return (
    <>
      <UnifiedHero
        primaryArticle={primaryHero}
        secondaryArticles={secondaryStories}
        locale={locale}
      />
      {/* Rest of homepage sections */}
    </>
  );
}
```

### 9.2 API Optimization

Consider creating a new backend endpoint for optimized hero data:

```
GET /api/hero-articles?locale=ro

Response:
{
  "primary": { ... article with badge or important ... },
  "secondary": [ ... up to 3 articles ... ],
  "ticker": [ ... headlines for optional ticker ... ]
}
```

---

## 10. Performance Considerations

### 10.1 Core Web Vitals Targets

| Metric | Target | Strategy |
|--------|--------|----------|
| LCP | < 2.5s | Priority image loading, preload |
| CLS | < 0.1 | Fixed aspect ratios, font display swap |
| INP | < 200ms | Minimal JS, efficient animations |

### 10.2 Image Optimization

```typescript
// Hero image loading strategy
<Image
  src={heroImageUrl}
  alt={article.title}
  fill
  priority // Critical for LCP
  sizes="100vw" // Full viewport width
  quality={85}
  className="object-cover"
  // Use hero_big thumbnail profile (1920x1080)
/>
```

### 10.3 Animation Performance

- Use `transform` and `opacity` only (GPU-accelerated)
- Add `will-change` sparingly for animated elements
- Support `prefers-reduced-motion` for accessibility
- Keep animation durations under 500ms for responsiveness

### 10.4 JavaScript Budget

- Hero component should add < 15KB gzipped JS
- Use CSS animations over JS where possible
- Lazy load secondary story images

---

## 11. Accessibility Requirements

### 11.1 ARIA Labels

```tsx
<section aria-label="Featured breaking news" role="region">
  <div role="article" aria-labelledby="hero-title">
    <span className="sr-only">Breaking News Alert</span>
    <h1 id="hero-title">{article.title}</h1>
  </div>
</section>
```

### 11.2 Focus Management

- Ensure all interactive elements are focusable
- Logical tab order through hero content
- Visible focus indicators matching variant colors
- Skip link to bypass hero section

### 11.3 Color Contrast

| Element | Contrast Ratio | Compliance |
|---------|---------------|------------|
| Title on image | 7:1 minimum | WCAG AAA |
| Badge text | 4.5:1 minimum | WCAG AA |
| Meta text | 4.5:1 minimum | WCAG AA |

### 11.4 Reduced Motion Support

```css
@media (prefers-reduced-motion: reduce) {
  .hero-animation,
  .breaking-pulse,
  .alert-glow,
  .flash-strobe {
    animation: none;
    transition: none;
  }
}
```

---

## 12. Testing Strategy

### 12.1 Unit Tests

```typescript
// __tests__/hero/UnifiedHero.test.tsx

describe('UnifiedHero', () => {
  describe('variant selection', () => {
    it('renders BreakingNewsHero when article has breaking badge', () => {
      // ...
    });

    it('renders AlertHero when article has alert badge', () => {
      // ...
    });

    it('renders FlashHero when article has flash badge', () => {
      // ...
    });

    it('renders StandardHero when article has no badge', () => {
      // ...
    });

    it('respects forceVariant prop override', () => {
      // ...
    });
  });

  describe('responsive behavior', () => {
    it('shows secondary stories in sidebar on desktop', () => {
      // ...
    });

    it('shows secondary stories below hero on mobile', () => {
      // ...
    });
  });
});
```

### 12.2 Visual Regression Tests

Using Playwright for screenshot comparisons:

```typescript
// e2e/hero-visual.spec.ts

test.describe('Hero Visual Tests', () => {
  test('breaking news hero matches snapshot', async ({ page }) => {
    // Mock breaking news data
    await page.goto('/?mockHero=breaking');
    await expect(page.locator('[data-testid="unified-hero"]')).toHaveScreenshot();
  });

  // Similar tests for alert, flash, standard variants
});
```

### 12.3 Performance Tests

```typescript
// e2e/hero-performance.spec.ts

test('hero LCP is under 2.5 seconds', async ({ page }) => {
  await page.goto('/');

  const lcp = await page.evaluate(() => {
    return new Promise(resolve => {
      new PerformanceObserver(list => {
        const entries = list.getEntries();
        resolve(entries[entries.length - 1].startTime);
      }).observe({ type: 'largest-contentful-paint', buffered: true });
    });
  });

  expect(lcp).toBeLessThan(2500);
});
```

---

## 13. Migration Plan

### 13.1 Backward Compatibility

During migration, support both old and new implementations:

```typescript
// feature flag approach
const USE_UNIFIED_HERO = process.env.NEXT_PUBLIC_UNIFIED_HERO === 'true';

export default function HomePage() {
  if (USE_UNIFIED_HERO) {
    return <UnifiedHero {...props} />;
  }

  // Legacy implementation
  return (
    <>
      <HeroArticle {...} />
      <SpecialArticlesSection {...} />
    </>
  );
}
```

### 13.2 Rollout Strategy

1. **Development**: Full new implementation in dev environment
2. **Staging**: A/B test with metrics collection
3. **Production**: Gradual rollout (10% -> 50% -> 100%)
4. **Cleanup**: Remove legacy components after full rollout

### 13.3 Deprecation of Old Components

After successful migration:
- Remove `components/home/HeroArticle.tsx`
- Remove `components/special/SpecialArticlesSection.tsx`
- Remove `components/special/SpecialArticleBanner.tsx`
- Update imports throughout the codebase

---

## 14. Success Metrics

### 14.1 Quantitative Metrics

| Metric | Current | Target | Method |
|--------|---------|--------|--------|
| Above-fold height | ~80vh | ~65vh | Measure in browser |
| LCP | TBD | < 2.5s | Core Web Vitals |
| CLS | TBD | < 0.1 | Core Web Vitals |
| Bounce rate | TBD | -5% | Analytics |
| Time on page | TBD | +10% | Analytics |
| Hero CTR | TBD | +15% | Click tracking |

### 14.2 Qualitative Metrics

- User feedback surveys
- Editorial team satisfaction
- Design review approval
- Accessibility audit pass

---

## 15. Future Enhancements

### 15.1 Phase 2 Features

- **Live Ticker**: Real-time headline updates via Mercure
- **Video Hero**: Support for video background in breaking news
- **Personalization**: Show different secondary stories based on user preferences
- **A/B Testing**: Built-in variant testing framework

### 15.2 Advanced Animations

- Particle effects for breaking news (subtle, performant)
- 3D tilt effect on hover (desktop only)
- Scroll-triggered parallax enhancements

---

## Appendix A: Color Reference Chart

### Breaking News Palette
| Name | Hex | RGB | Usage |
|------|-----|-----|-------|
| Breaking Primary | #dc2626 | 220, 38, 38 | Badges, accents |
| Breaking Dark | #7f1d1d | 127, 29, 29 | Backgrounds |
| Breaking Darkest | #450a0a | 69, 10, 10 | Deep backgrounds |
| Breaking Light | #fca5a5 | 252, 165, 165 | Highlights |

### Alert Palette
| Name | Hex | RGB | Usage |
|------|-----|-----|-------|
| Alert Primary | #1e3a5f | 30, 58, 95 | Main color |
| Alert Dark | #112240 | 17, 34, 64 | Backgrounds |
| Alert Accent | #f59e0b | 245, 158, 11 | Badges, accents |
| Alert Light | #fcd34d | 252, 211, 77 | Highlights |

### Flash Palette
| Name | Hex | RGB | Usage |
|------|-----|-----|-------|
| Flash Primary | #0d9488 | 13, 148, 136 | Main color |
| Flash Dark | #134e4a | 19, 78, 74 | Backgrounds |
| Flash Accent | #5eead4 | 94, 234, 212 | Badges, accents |
| Flash Light | #99f6e4 | 153, 246, 228 | Highlights |

---

## Appendix B: Typography Reference

| Element | Desktop | Mobile | Weight | Line Height |
|---------|---------|--------|--------|-------------|
| Breaking Title | 56px | 36px | 800 | 1.1 |
| Alert Title | 52px | 32px | 700 | 1.15 |
| Flash Title | 48px | 30px | 700 | 1.2 |
| Standard Title | 48px | 32px | 700 | 1.1 |
| Lead Text | 22px | 18px | 400 | 1.5 |
| Badge Text | 12px | 11px | 700 | 1 |
| Meta Text | 14px | 13px | 500 | 1.4 |

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-12-17 | Claude | Initial document |

---

## References

- [Hero Section Design Best Practices 2025](https://detachless.com/blog/hero-section-web-design-ideas)
- [Micro Interactions 2025 Best Practices](https://www.stan.vision/journal/micro-interactions-2025-in-web-design)
- [Motion UI Trends 2025](https://www.betasofttechnology.com/motion-ui-trends-and-micro-interactions/)
- [LogRocket Hero Section Examples](https://blog.logrocket.com/ux-design/hero-section-examples-best-practices)
- Deschide News Design System (`context/design_principles_and_features.md`)
- Deschide Brand Book Guidelines
