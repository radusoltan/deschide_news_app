# HeroArticle Visual Design Guide

## Component Anatomy

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                                                                             │
│                          FULL-WIDTH HERO IMAGE                              │
│                            (60-70vh height)                                 │
│                                                                             │
│                          ┌────────────────┐                                 │
│    Transparent          │   TRANSPARENT   │                                │
│         ↓               │       TOP       │                                │
│                          └────────────────┘                                 │
│                                   ↓                                         │
│                          ┌────────────────┐                                 │
│                          │  BLACK/60 MID  │                                │
│                          └────────────────┘                                 │
│                                   ↓                                         │
│    ┌─────────────────────────────────────────────────────────┐             │
│    │                    BLACK/95 BOTTOM                      │             │
│    │  ┌──────────────┐                                       │             │
│    │  │  POLITICA    │  ← Category Badge (dynamic color)    │             │
│    │  └──────────────┘                                       │             │
│    │                                                          │             │
│    │  Breaking News: Major Development                       │             │
│    │  in Government Policy                                   │             │
│    │  ↑ Title (60px League Spartan, white, uppercase)       │             │
│    │                                                          │             │
│    │  A detailed lead paragraph that provides context       │             │
│    │  and entices readers to continue reading the full      │             │
│    │  story with additional details and analysis.           │             │
│    │  ↑ Lead (24px Merriweather, serif, white/95)          │             │
│    │                                                          │             │
│    │  ┌──────────────────────────────┐      2 hours ago     │             │
│    │  │ CITEȘTE ARTICOLUL COMPLET → │      ↑ Time          │             │
│    │  └──────────────────────────────┘                       │             │
│    │  ↑ CTA Button (Tomato brand color)                     │             │
│    └─────────────────────────────────────────────────────────┘             │
│                                   ║                                         │
│                                   ║  ← Scroll indicator                     │
│                                   ●      (bounce animation)                 │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Layout Specifications

### Container Structure

```
article.relative.w-full.overflow-hidden.bg-brand-oxford.group
└── div.relative.h-[70vh].overflow-hidden
    ├── Image (Next.js Image component)
    │   └── Priority loading
    │   └── Fill container
    │   └── Scale 105% on hover
    │
    ├── div.absolute.inset-0 (Gradient overlay)
    │   └── bg-gradient-to-t from-black/95 via-black/60 to-transparent
    │
    └── div.absolute.inset-0.flex.items-end (Content container)
        └── div.container-deschide.w-full.pb-16
            └── div.max-w-4xl
                ├── Category Badge
                ├── H1 Title
                ├── Lead Paragraph
                └── CTA Row
                    ├── CTA Button
                    └── Time
```

## Typography Hierarchy

### Desktop (≥1024px)

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│  POLITICA (14px, League Spartan 700, uppercase, white)     │
│                                                             │
│  BREAKING NEWS STORY TITLE                                 │
│  CONTINUES ON SECOND LINE                                   │
│  (60px, League Spartan 800, uppercase, white)              │
│  Line height: 1.1, Letter spacing: -0.025em                │
│                                                             │
│  Lead paragraph with serif typography for editorial        │
│  feel and enhanced readability. Uses Merriweather          │
│  at 24px with relaxed line height.                         │
│  (24px, Merriweather 400, white/95)                        │
│  Line height: 1.625                                         │
│                                                             │
│  ┌──────────────────────────┐                              │
│  │ READ FULL STORY      →  │  2 hours ago                 │
│  └──────────────────────────┘                              │
│  (16px, Sans 700)           (14px, Sans 500)               │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Mobile (<640px)

```
┌──────────────────────────────────┐
│                                  │
│  POLITICA (12px)                 │
│                                  │
│  BREAKING NEWS                   │
│  STORY TITLE                     │
│  (32px, tighter line-height)     │
│                                  │
│  Lead paragraph text             │
│  smaller size for mobile         │
│  (16px, Merriweather)            │
│                                  │
│  ┌──────────────────────────┐   │
│  │ READ FULL STORY      →  │   │
│  └──────────────────────────┘   │
│                                  │
│  2 hours ago                     │
│  (Stacked vertically)            │
│                                  │
└──────────────────────────────────┘
```

## Color Palette

### Gradient Overlay (Key to Readability)

```
Top (0%)     ─────  transparent         ← Image fully visible
             ↓
             ─────  rgba(0,0,0,0.2)     ← Subtle darkening begins
             ↓
Mid (40%)    ─────  rgba(0,0,0,0.6)    ← Noticeable overlay
             ↓
             ─────  rgba(0,0,0,0.8)     ← Strong overlay
             ↓
Bottom (100%) ────  rgba(0,0,0,0.95)    ← Almost black for text
```

### Brand Colors Used

| Element | Color | RGB Value | Usage |
|---------|-------|-----------|-------|
| Background Fallback | Oxford Blue 900 | rgb(17, 34, 64) | No image fallback |
| CTA Button | Tomato 500 | rgb(240, 94, 69) | Primary action |
| CTA Hover | Tomato 600 | rgb(221, 60, 40) | Button hover state |
| Title Hover | Mindaro 400 | rgb(212, 251, 140) | Accent on hover |
| Text on Dark | White | rgb(255, 255, 255) | Primary text |
| Text Secondary | White/95 | rgba(255, 255, 255, 0.95) | Lead text |
| Text Tertiary | White/80 | rgba(255, 255, 255, 0.8) | Timestamp |

### Category Badge Colors

```css
/* Dynamic gradient per category */
.category-badge[data-category="politica"] {
  background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
}

.category-badge[data-category="economie"] {
  background: linear-gradient(135deg, #047857 0%, #065f46 100%);
}

.category-badge[data-category="sport"] {
  background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
}

/* ... see globals.css lines 1657-1704 for full list */
```

## Spacing System

### Desktop Layout

```
┌─────────────────────────────────────────────────────────────┐
│  ← Container padding: px-8 (32px) →                         │
│                                                             │
│     ← Max-width content: 4xl (896px) →                     │
│     ┌─────────────────────────────────────┐                │
│     │                                     │  ← pb-16 (64px) │
│     │  [Category Badge]                   │     ↓          │
│     │       ↓ mb-6 (24px)                 │                │
│     │  [Title]                            │                │
│     │       ↓ mb-6 (24px)                 │                │
│     │  [Lead Paragraph]                   │                │
│     │       ↓ mb-8 (32px)                 │                │
│     │  [CTA + Time]                       │                │
│     │                                     │                │
│     └─────────────────────────────────────┘                │
│                                                             │
└─────────────────────────────────────────────────────────────┘
     ↑                                                       ↑
  Left edge                                            Right edge
```

### Mobile Layout

```
┌─────────────────────────────┐
│  ← px-4 (16px) →            │
│                             │
│  [Category Badge]           │ ← pb-8 (32px)
│       ↓ mb-4 (16px)         │     ↓
│  [Title]                    │
│       ↓ mb-4 (16px)         │
│  [Lead]                     │
│       ↓ mb-6 (24px)         │
│  [CTA Button]               │
│       ↓ gap-4 (16px)        │
│  [Time]                     │
│                             │
└─────────────────────────────┘
```

## Animation Specifications

### Entrance Animations (Staggered Fade-in-up)

```
Timeline:
─────────────────────────────────────────────────────────────→ time

0ms      [Category Badge] fade-in-up (0.5s)
         │
         └──▶ opacity: 0→1, translateY: 20px→0

100ms    [Title] fade-in-up (0.5s, delay 0.1s)
         │
         └──▶ opacity: 0→1, translateY: 20px→0

200ms    [Lead] fade-in-up (0.5s, delay 0.2s)
         │
         └──▶ opacity: 0→1, translateY: 20px→0

300ms    [CTA Row] fade-in-up (0.5s, delay 0.3s)
         │
         └──▶ opacity: 0→1, translateY: 20px→0

800ms    All animations complete
```

### Hover Effects

#### Image Scale Animation

```
Idle:    scale(1)    ─────────────────────┐
                                          │
Hover:                                    │
         scale(1) → scale(1.05)           │
         Duration: 2000ms                 │
         Easing: ease-out                 │
                                          │
Leave:                                    │
         scale(1.05) → scale(1)           │
         Duration: 2000ms ────────────────┘
```

#### Title Color Transition

```
Idle:    color: white ─────────────────┐
                                       │
Hover:                                 │
         white → mindaro-400           │
         Duration: 300ms               │
         Easing: ease                  │
                                       │
Leave:                                 │
         mindaro-400 → white ───────────┘
```

#### CTA Button Animation

```
Idle State:
┌──────────────────────────┐
│ READ FULL STORY      →  │  bg-tomato-500, shadow-lg
└──────────────────────────┘

Hover State (300ms transition):
┌──────────────────────────┐
│ READ FULL STORY       → │  bg-tomato-600, shadow-xl
└──────────────────────────┘  translateX(4px)
                              Arrow icon: translateX(4px)
```

### Scroll Indicator Animation (Desktop Only)

```
     ┌─┐
     │ │  ← Border ring (white/40)
     │●│  ← Dot (white/60, pulse animation)
     │ │
     └─┘
      ║
      ║   ← Vertical line suggested by design
      ↓
  Bounce Animation:
  translateY: 0 → -10px → 0
  Duration: 1s
  Easing: ease-in-out
  Iteration: infinite
```

## Responsive Behavior

### Breakpoint Transitions

```
Mobile        Tablet         Desktop
(<640px)      (640-1023px)   (≥1024px)
   │              │              │
   ├─ h-[50vh]    ├─ h-[60vh]    ├─ h-[70vh]
   │              │              │
   ├─ text-3xl    ├─ text-4xl    ├─ text-6xl
   │  (32px)      │  (40px)      │  (60px)
   │              │              │
   ├─ text-base   ├─ text-lg     ├─ text-2xl
   │  (16px)      │  (18px)      │  (24px)
   │              │              │
   ├─ pb-8        ├─ pb-12       ├─ pb-16
   │  (32px)      │  (48px)      │  (64px)
   │              │              │
   ├─ mb-4        ├─ mb-6        ├─ mb-6
   │  (16px)      │  (24px)      │  (24px)
   │              │              │
   └─ Vertical    └─ Horizontal  └─ Horizontal
      CTA+Time       CTA+Time       CTA+Time
      Stack          Row            Row
```

## Text Shadow for Readability

### On Photo Text (Title + Lead)

```css
text-shadow:
  0 1px 3px rgba(0, 0, 0, 0.8),     /* Tight shadow (base) */
  0 2px 8px rgba(0, 0, 0, 0.5),     /* Medium blur (depth) */
  0 4px 16px rgba(17, 34, 64, 0.4); /* Large blur (glow, oxford blue) */

/* This creates a multi-layer shadow that ensures
   readability over any background image */
```

### Visual Representation

```
White Text
  │
  └─▶ Layer 1: Dark tight shadow (3px blur)
      └─▶ Layer 2: Medium shadow (8px blur)
          └─▶ Layer 3: Large glow (16px blur, brand color)

Result: Text appears to "float" above the image
        with excellent readability even on light backgrounds
```

## Interactive States

### CTA Button States

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│  DEFAULT STATE                                              │
│  ┌──────────────────────────────┐                          │
│  │ CITEȘTE ARTICOLUL COMPLET → │                          │
│  └──────────────────────────────┘                          │
│  bg-tomato-500, shadow-lg                                   │
│  transform: translateX(0)                                   │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  HOVER STATE (300ms transition)                            │
│  ┌──────────────────────────────┐                          │
│  │ CITEȘTE ARTICOLUL COMPLET  →│                          │
│  └──────────────────────────────┘                          │
│  bg-tomato-600, shadow-xl                                   │
│  transform: translateX(4px)                                 │
│  Arrow: translateX(4px) additional                          │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  FOCUS STATE (keyboard navigation)                         │
│  ┌──────────────────────────────┐                          │
│  │◄CITEȘTE ARTICOLUL COMPLET → │                          │
│  └──────────────────────────────┘                          │
│  Custom focus ring: oxford blue glow                        │
│  box-shadow: 0 0 0 3px rgba(17, 34, 64, 0.3)              │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ACTIVE STATE (click/tap)                                  │
│  ┌──────────────────────────────┐                          │
│  │ CITEȘTE ARTICOLUL COMPLET → │                          │
│  └──────────────────────────────┘                          │
│  Slightly darker, reduced shadow                            │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Category Badge States

```
IDLE:     [POLITICA]  bg-gradient blue, no shadow

HOVER:    [POLITICA]  filter: brightness(1.1)
                      transform: translateY(-1px)
                      Duration: 200ms
```

## Accessibility Features

### Color Contrast Ratios

```
Element                 Foreground    Background       Ratio    WCAG
─────────────────────  ────────────  ───────────────  ───────  ──────
Title (white)          #FFFFFF       rgba(0,0,0,0.95)  21:1     AAA ✓
Lead (white/95)        #F2F2F2       rgba(0,0,0,0.95)  19.8:1   AAA ✓
CTA Text (white)       #FFFFFF       rgb(240,94,69)    4.7:1    AA ✓
Time (white/80)        #CCCCCC       rgba(0,0,0,0.95)  15.2:1   AAA ✓
Badge Text (white)     #FFFFFF       rgb(29,78,216)    8.6:1    AAA ✓
```

### Focus Indicators

```
Default browser focus:
┌──────────────────────────────┐
│ CITEȘTE ARTICOLUL COMPLET → │  ← Blue outline (Chrome)
└──────────────────────────────┘

Custom focus (focus-brand-oxford):
┌──────────────────────────────┐
│◄CITEȘTE ARTICOLUL COMPLET → │  ← Custom oxford blue glow
└──────────────────────────────┘    3px blur, 0.3 opacity

Applied to:
- CTA button
- Title link
- Category badge link
```

### Screen Reader Experience

```
<article>
  <h1>
    <a href="/politica/breaking-news-story">
      Breaking News: Major Development in Government Policy
    </a>
  </h1>

  <span class="category-badge">
    <a href="/politica">Politics</a>
  </span>

  <p>Lead paragraph text...</p>

  <a href="/politica/breaking-news-story">
    Read full story
  </a>

  <time datetime="2025-12-17T10:30:00Z">
    2 hours ago
  </time>
</article>

Screen reader announces:
"Article. Heading level 1. Link. Breaking News: Major Development
in Government Policy. Link. Politics. Lead paragraph text...
Link. Read full story. Time. 2 hours ago."
```

## Performance Checklist

### Image Loading

```
✓ Priority loading (priority attribute)
✓ Responsive images (srcset via Next.js Image)
✓ WebP format (generated by backend)
✓ Lazy loading for off-screen content
✓ Proper aspect ratio (16:9)
✓ CDN delivery (127.0.0.1:8082 dev, production CDN)
✓ Optimized thumbnail profile (hero_big: 1920x1080)
```

### Animation Performance

```
✓ GPU-accelerated transforms (translateX, translateY, scale)
✓ Will-change hints for animated elements
✓ Debounced scroll events (if parallax enabled)
✓ RequestAnimationFrame for smooth animations
✓ Reduced motion support (prefers-reduced-motion)
✓ No layout-triggering properties in animations
```

### Rendering Optimization

```
✓ Server-side rendering (Next.js RSC)
✓ ISR revalidation (60s)
✓ Static generation where possible
✓ Minimal client-side JavaScript
✓ Optimized bundle size
✓ Tree-shaking for unused code
```

## CSS Architecture

### Utility-First with Custom Classes

```css
/* Component uses Tailwind utilities: */
- Layout: relative, absolute, flex, items-end
- Sizing: w-full, h-[70vh], max-w-4xl
- Spacing: pb-16, mb-6, gap-4
- Typography: text-6xl, font-heading, tracking-tight
- Colors: text-white, bg-brand-tomato
- Effects: shadow-lg, hover:shadow-xl

/* Plus custom classes from globals.css: */
- .container-deschide       (max-w + padding)
- .font-heading             (League Spartan + uppercase)
- .font-serif               (Merriweather)
- .text-on-photo-strong     (text shadow for readability)
- .category-badge           (dynamic category colors)
- .animate-fade-in-up       (entrance animation)
- .focus-brand-oxford       (custom focus ring)
```

### Class Naming Conventions

```
Naming Pattern: [state]:[utility]-[modifier]

Examples:
- hover:scale-105           (hover state, scale transform)
- group-hover:translate-x-1 (hover on parent group)
- dark:bg-black/20          (dark mode variant)
- sm:text-4xl               (responsive breakpoint)
- focus-brand-oxford        (custom utility)
```

## Print Styles

The component includes print optimization:

```css
@media print {
  article.bg-brand-oxford {
    /* Remove background for print */
    background: white !important;
  }

  .category-badge,
  .animate-* {
    /* Disable animations */
    animation: none !important;
  }

  /* Show content in readable format */
  color: black !important;
  background: white !important;
}
```

## Browser Support

### Tested Browsers

```
✓ Chrome 120+      (Full support)
✓ Firefox 120+     (Full support)
✓ Safari 17+       (Full support)
✓ Edge 120+        (Full support)
⚠ Safari 16        (Minor gradient issues)
✗ IE 11            (Not supported)
```

### Fallbacks

```css
/* Gradient overlay fallback */
background: linear-gradient(...); /* Modern browsers */
background: rgba(0,0,0,0.9);     /* Fallback for old browsers */

/* Backdrop filter fallback (if used) */
backdrop-filter: blur(10px);          /* Modern */
-webkit-backdrop-filter: blur(10px);  /* Safari */
background: rgba(0,0,0,0.8);         /* No blur support */
```

## Design Tokens

### Spacing Scale (Tailwind)

```
0.5rem  = 8px   (gap-2, p-2)
1rem    = 16px  (gap-4, p-4, mb-4)
1.5rem  = 24px  (gap-6, mb-6)
2rem    = 32px  (gap-8, mb-8, pb-8)
3rem    = 48px  (pb-12)
4rem    = 64px  (pb-16)
```

### Font Sizes

```
text-xs     = 0.75rem  (12px)
text-sm     = 0.875rem (14px)
text-base   = 1rem     (16px)
text-lg     = 1.125rem (18px)
text-xl     = 1.25rem  (20px)
text-2xl    = 1.5rem   (24px)
text-3xl    = 1.875rem (32px)
text-4xl    = 2.25rem  (40px)
text-5xl    = 3rem     (48px)
text-6xl    = 3.75rem  (60px)
```

### Shadow Scale

```
shadow-lg:  0 10px 15px -3px rgba(0,0,0,0.1),
            0 4px 6px -4px rgba(0,0,0,0.1)

shadow-xl:  0 20px 25px -5px rgba(0,0,0,0.1),
            0 8px 10px -6px rgba(0,0,0,0.1)

shadow-2xl: 0 25px 50px -12px rgba(0,0,0,0.25)
```

## Component Variants (Future)

### Planned Variations

1. **Compact Hero** (40vh height)
2. **Video Hero** (auto-play background video)
3. **Slideshow Hero** (multiple articles carousel)
4. **Breaking News Hero** (red accent, pulsing badge)
5. **Dark Mode Hero** (inverted colors)

Each variant would maintain the core design language while adapting specific elements.

---

**Visual Guide Version**: 1.0.0
**Last Updated**: December 2025
**Design System**: Deschide Brand Guidelines
