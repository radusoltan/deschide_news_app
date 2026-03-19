# HeroArticle Component Documentation

## Overview

The `HeroArticle` component is a premium, full-width hero section designed for the Deschide News homepage. It embodies the sophistication and editorial excellence of major international news outlets like The New York Times, The Guardian, and RePublica.

## Visual Design Philosophy

### Inspiration
- **The New York Times**: Bold typography, dramatic imagery, editorial hierarchy
- **The Guardian**: Clean layouts, strong visual storytelling, sophisticated color use
- **RePublica**: Investigative journalism aesthetic, premium feel, high contrast

### Key Design Elements

1. **Full-Bleed Dramatic Hero**
   - Image takes 60-70vh on desktop (50vh on mobile)
   - Smooth scale animation on hover (2-second duration)
   - Priority loading for optimal LCP (Largest Contentful Paint)

2. **Gradient Overlay System**
   - Transparent top → Dark bottom gradient
   - Ensures text readability over any image
   - From `black/95` at bottom to transparent at top
   - Mid-layer at `black/60` for smooth transition

3. **Typography Hierarchy**
   - **Hero Title**: 60px (desktop) / 32px (mobile)
     - Font: League Spartan (brand heading font)
     - Weight: 800 (extra bold)
     - Line height: 1.1 (tight for impact)
     - Uppercase via font-heading class
   - **Lead Text**: 24px (desktop) / 16px (mobile)
     - Font: Merriweather (brand serif font)
     - Weight: 400 (regular)
     - Line height: relaxed (1.625)
     - Editorial readability

4. **Brand Color Integration**
   - Oxford Blue: Background fallback
   - Tomato: CTA buttons and hover states
   - Mindaro: Hover accent on title
   - Category badges: Dynamic colors based on category

## Component API

### Props Interface

```typescript
interface HeroArticleProps {
  article: {
    id: number;
    title: string;
    slug: string;
    lead?: string;
    publishedAt?: string;
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
  };
  locale: string; // 'ro', 'en', or 'ru'
}
```

### Required Props
- `article`: Article object with title, slug, and optional lead/images
- `locale`: Current language locale for URL generation and text localization

### Optional Article Properties
- `lead`: Subtitle/excerpt text (recommended for hero articles)
- `publishedAt`: Publication date (displays relative time: "2h ago")
- `category`: Category object for badge display
- `articleImages`: Array of images (component uses featured or first image)

## Usage Examples

### Basic Usage (Homepage)

```tsx
import HeroArticle from '@/components/home/HeroArticle';
import { fetchImportantArticles } from '@/lib/api/important-articles';

export default async function HomePage({ params }: { params: { locale: string } }) {
  const importantArticles = await fetchImportantArticles(params.locale);
  const heroArticle = importantArticles.member[0]?.article;

  if (!heroArticle) {
    return <div>No hero article available</div>;
  }

  return (
    <main>
      <HeroArticle article={heroArticle} locale={params.locale} />
      {/* Rest of homepage content */}
    </main>
  );
}
```

### With Multiple Important Articles

```tsx
import HeroArticle from '@/components/home/HeroArticle';
import { fetchImportantArticles } from '@/lib/api/important-articles';

export default async function HomePage({ params }: { params: { locale: string } }) {
  const importantArticles = await fetchImportantArticles(params.locale);

  // First article is hero, rest are featured cards
  const [heroItem, ...featuredItems] = importantArticles.member;

  return (
    <main>
      {/* Full-width hero */}
      <HeroArticle article={heroItem.article} locale={params.locale} />

      {/* Featured articles grid below hero */}
      <section className="container-deschide py-12">
        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
          {featuredItems.map((item) => (
            <ArticleCard key={item.id} article={item.article} locale={params.locale} />
          ))}
        </div>
      </section>
    </main>
  );
}
```

### Conditional Hero (with Fallback)

```tsx
import HeroArticle from '@/components/home/HeroArticle';
import { fetchImportantArticles } from '@/lib/api/important-articles';

export default async function HomePage({ params }: { params: { locale: string } }) {
  const importantArticles = await fetchImportantArticles(params.locale);
  const hasHero = importantArticles.member.length > 0;

  return (
    <main>
      {hasHero ? (
        <HeroArticle
          article={importantArticles.member[0].article}
          locale={params.locale}
        />
      ) : (
        // Fallback hero section
        <section className="bg-brand-oxford text-white py-24">
          <div className="container-deschide text-center">
            <h1 className="font-heading text-5xl mb-4">DESCHIDE</h1>
            <p className="font-serif text-xl">Breaking News & Analysis</p>
          </div>
        </section>
      )}
    </main>
  );
}
```

## Image Optimization

### Thumbnail Profile Usage

The component uses the `hero_big` thumbnail profile for optimal quality:
- **Dimensions**: 1920x1080 (16:9 aspect ratio)
- **Format**: WebP (generated by backend)
- **Fallback**: Original image if thumbnail not available

### Backend Thumbnail Generation

Ensure articles have the `hero_big` thumbnail generated:

```bash
# Generate thumbnails for all images
symfony console app:import:generate-thumbnails

# Check thumbnail profiles
symfony console app:thumbnails:list
```

### Next.js Image Component

The component uses Next.js Image with optimal settings:
```tsx
<Image
  src={buildImageUrl(imageToUse.path)}
  alt={featuredImage?.alt || article.title}
  fill                          // Responsive fill container
  priority                      // Priority loading (LCP optimization)
  sizes="100vw"                 // Full viewport width
  quality={90}                  // High quality for hero
  className="object-cover ..."  // Cover + hover scale animation
/>
```

## Animations & Micro-interactions

### Entrance Animations
- **Staggered fade-in-up**: Category badge → Title → Lead → CTA
- **Delay timing**: 0.1s, 0.2s, 0.3s between elements
- **Duration**: 0.5s ease-out

### Hover Effects
1. **Image Scale**: 2-second smooth scale to 105%
2. **Title Color**: Transition to Mindaro accent color
3. **CTA Button**: Translate right + shadow increase + color darken
4. **Arrow Icon**: Additional translate right animation

### Scroll Indicator
- Desktop only (hidden on mobile/tablet)
- Bouncing animation with pulse effect
- Positioned at bottom center
- Subtle white ring with dot inside

## Responsive Breakpoints

### Mobile (< 640px)
- Hero height: 50vh
- Title: 32px (text-3xl)
- Lead: 16px (text-base)
- Vertical stacking of CTA and time
- Simplified spacing

### Tablet (640px - 1023px)
- Hero height: 60vh
- Title: 40-48px (text-4xl/5xl)
- Lead: 18-20px (text-lg/xl)
- Horizontal CTA/time row

### Desktop (≥ 1024px)
- Hero height: 70vh
- Title: 60px (text-6xl)
- Lead: 24px (text-2xl)
- Scroll indicator visible
- Generous spacing (pb-16)

## Accessibility Features

1. **Semantic HTML**
   - `<article>` wrapper
   - `<h1>` for title
   - `<time>` with datetime attribute
   - Proper heading hierarchy

2. **Focus States**
   - Custom focus ring (focus-brand-oxford)
   - Keyboard navigation support
   - Skip to content compatibility

3. **Alt Text**
   - Image alt from backend or article title fallback
   - Descriptive link labels
   - Icon-only buttons have aria-labels

4. **Color Contrast**
   - Text shadow for readability on images
   - White text on dark gradient overlay
   - WCAG AA compliant contrast ratios

5. **Reduced Motion**
   - Respects `prefers-reduced-motion` CSS media query
   - Animations reduced to 0.01ms duration
   - See `globals.css` line 912-921

## Performance Optimization

### Core Web Vitals Targets

- **LCP (Largest Contentful Paint)**: < 2.5s
  - Priority image loading
  - Optimized hero_big thumbnails (WebP)
  - Next.js Image optimization

- **CLS (Cumulative Layout Shift)**: < 0.1
  - Fixed aspect ratio container
  - No layout shift on image load
  - Stable gradient overlay

- **INP (Interaction to Next Paint)**: < 200ms
  - Optimized hover animations (will-change: transform)
  - GPU-accelerated transforms
  - Debounced scroll events (if parallax added)

### Loading Strategy

```tsx
// Priority loading for hero
<Image priority />

// ISR revalidation
fetch(url, { next: { revalidate: 60 } })

// CDN image serving
const CDN_URL = process.env.NEXT_PUBLIC_CDN_URL;
```

## Dark Mode Support

The component includes dark mode compatibility:
```css
.dark:bg-black/20  /* Additional overlay in dark mode */
```

To enable dark mode project-wide, add theme provider:
```tsx
// app/layout.tsx
import { ThemeProvider } from 'next-themes';

export default function RootLayout({ children }) {
  return (
    <html suppressHydrationWarning>
      <body>
        <ThemeProvider attribute="class" defaultTheme="light">
          {children}
        </ThemeProvider>
      </body>
    </html>
  );
}
```

## Localization

### Supported Locales
- **Romanian (ro)**: Default locale, no prefix in URL
- **English (en)**: URL prefix `/en/`
- **Russian (ru)**: URL prefix `/ru/`

### Localized Elements

1. **CTA Button Text**
   - ro: "Citește articolul complet"
   - en: "Read full story"
   - ru: "Читать полную статью"

2. **Relative Time Format**
   - ro: "acum 2h", "acum 3 zile"
   - en: "2h ago", "3 days ago"
   - ru: "2ч назад", "3 дней назад"

3. **Date Formatting**
   - Uses browser's Intl.DateTimeFormat
   - Locale-specific month/day names
   - Format: "15 decembrie 2025" (ro)

## Category Badge Colors

Dynamic category colors via `data-category` attribute:

| Category Slug | Color | Gradient |
|--------------|-------|----------|
| politica | Blue | #1d4ed8 → #1e40af |
| economie | Green | #047857 → #065f46 |
| societate | Purple | #7c3aed → #6d28d9 |
| sport | Red | #dc2626 → #b91c1c |
| cultura | Amber | #b45309 → #92400e |
| externe | Cyan | #0891b2 → #0e7490 |
| tech | Teal | #0f766e → #115e59 |
| video | Rose | #be123c → #9f1239 |

Defined in `globals.css` lines 1657-1704.

## Testing Considerations

### Unit Tests

```typescript
// __tests__/unit/components/hero-article.test.tsx
import { render, screen } from '@testing-library/react';
import HeroArticle from '@/components/home/HeroArticle';

describe('HeroArticle', () => {
  const mockArticle = {
    id: 1,
    title: 'Breaking News Story',
    slug: 'breaking-news-story',
    lead: 'This is a breaking news story',
    publishedAt: new Date().toISOString(),
    category: {
      id: 1,
      title: 'Politics',
      slug: 'politica'
    },
    articleImages: [{
      id: 1,
      image: {
        path: 'images/test.jpg',
        alt: 'Test image'
      },
      isFeatured: true
    }]
  };

  it('renders article title', () => {
    render(<HeroArticle article={mockArticle} locale="ro" />);
    expect(screen.getByText('Breaking News Story')).toBeInTheDocument();
  });

  it('displays category badge', () => {
    render(<HeroArticle article={mockArticle} locale="ro" />);
    expect(screen.getByText('Politics')).toBeInTheDocument();
  });

  it('shows CTA button with correct locale text', () => {
    render(<HeroArticle article={mockArticle} locale="ro" />);
    expect(screen.getByText('Citește articolul complet')).toBeInTheDocument();
  });

  it('renders image with priority loading', () => {
    render(<HeroArticle article={mockArticle} locale="ro" />);
    const image = screen.getByAlt('Test image');
    expect(image).toHaveAttribute('priority');
  });
});
```

### E2E Tests (Playwright)

```typescript
// tests/e2e/homepage-hero.spec.ts
import { test, expect } from '@playwright/test';

test.describe('Homepage Hero Article', () => {
  test('displays hero article on homepage', async ({ page }) => {
    await page.goto('/');

    // Wait for hero to load
    await page.waitForSelector('article.bg-brand-oxford');

    // Check title is visible
    const title = page.locator('h1 a');
    await expect(title).toBeVisible();

    // Check category badge
    const badge = page.locator('.category-badge');
    await expect(badge).toBeVisible();

    // Check CTA button
    const cta = page.locator('text=Citește articolul complet');
    await expect(cta).toBeVisible();
  });

  test('hero image loads with priority', async ({ page }) => {
    await page.goto('/');

    const heroImage = page.locator('article img').first();
    await expect(heroImage).toBeVisible();

    // Check image has loaded
    const src = await heroImage.getAttribute('src');
    expect(src).toContain('/uploads/');
  });

  test('CTA button navigates to article', async ({ page }) => {
    await page.goto('/');

    // Click CTA
    await page.click('text=Citește articolul complet');

    // Should navigate to article page
    await expect(page).toHaveURL(/\/[a-z-]+\/[a-z0-9-]+$/);
  });

  test('hover animations work correctly', async ({ page }) => {
    await page.goto('/');

    const heroSection = page.locator('article.bg-brand-oxford');

    // Hover over hero
    await heroSection.hover();

    // Image should scale up (check transform property)
    const image = page.locator('article img').first();
    const transform = await image.evaluate(el =>
      window.getComputedStyle(el).transform
    );
    expect(transform).toContain('scale');
  });
});
```

## Troubleshooting

### Image Not Displaying

**Problem**: Hero image doesn't appear
**Solutions**:
1. Check CDN_URL environment variable:
   ```bash
   echo $NEXT_PUBLIC_CDN_URL
   # Should be: http://127.0.0.1:8082
   ```
2. Verify image path in article data
3. Check if thumbnail generation completed:
   ```bash
   symfony console app:thumbnails:status
   ```
4. Inspect browser network tab for 404 errors

### Category Badge Wrong Color

**Problem**: Badge shows wrong color or no gradient
**Solutions**:
1. Verify category slug matches defined categories (see Category Badge Colors section)
2. Check `data-category` attribute in browser DevTools
3. Ensure globals.css is imported in layout.tsx
4. Clear Next.js cache: `pnpm clean && pnpm build`

### Title Not Uppercase

**Problem**: Title displays in lowercase/mixed case
**Solutions**:
1. Verify `font-heading` class is applied
2. Check globals.css line 317-322 for `.font-heading` definition
3. Ensure CSS includes `text-transform: uppercase`
4. Clear browser cache

### Animations Not Working

**Problem**: No entrance animations or hover effects
**Solutions**:
1. Check if user has `prefers-reduced-motion` enabled
2. Verify animation classes are in globals.css (lines 480-683)
3. Ensure component has `group` class on wrapper
4. Check browser console for CSS errors

### Layout Shift Issues

**Problem**: Content jumps when hero loads
**Solutions**:
1. Ensure fixed height on hero container (h-[70vh])
2. Use `fill` prop on Image component
3. Add `object-cover` class to Image
4. Check if aspect ratio is maintained

## Future Enhancements

### Planned Features
1. **Video Hero Support**
   - Auto-playing background video with sound control
   - Fallback to poster image
   - Mobile optimization (poster only)

2. **Parallax Scrolling**
   - Subtle parallax effect on scroll
   - GPU-accelerated transforms
   - Disabled on mobile for performance

3. **Reading Progress Bar**
   - Horizontal bar showing article read progress
   - Sticky at top on scroll
   - Animated fill effect

4. **Breaking News Badge**
   - Pulsing "BREAKING" badge overlay
   - Red accent with animation
   - Auto-hide after 24 hours

5. **Social Share Overlay**
   - Quick share buttons on hover
   - Facebook, Twitter, WhatsApp, Telegram
   - Copy link functionality

### Code Improvements
- Extract relative time formatting to shared utility
- Add skeleton loading state for SSR
- Implement image blur placeholder from backend
- Add Storybook stories for component variants

## Related Components

- **ArticleCard**: Standard article card for grids
- **CategoryHeroArticle**: Category-specific hero
- **BreakingNewsBanner**: Alert banner for breaking news
- **FeaturedArticlesGrid**: Grid of featured articles

## Support & Contributing

For issues or feature requests, contact the development team or create a ticket in the project management system.

**Component Version**: 1.0.0
**Last Updated**: December 2025
**Maintainer**: Deschide Frontend Team
