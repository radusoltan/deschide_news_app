# Deschide Brand Design System

This directory contains brand identity components following the official **DESCHIDE_BRANDBOOK.pdf** specifications.

## Brand Colors

### Primary Colors (40% usage each)

#### Oxford Blue
- **Primary**: `#112240` (900 shade)
- **Usage**: Headers, navigation, primary CTAs, text
- **Tailwind**: `bg-brand-oxford`, `text-brand-oxford`
- **CSS Variable**: `rgb(var(--brand-oxford-900))`

#### Tomato
- **Primary**: `#F05E45` (500 shade)
- **Usage**: Links, hover states, secondary CTAs, accents
- **Tailwind**: `bg-brand-tomato`, `text-brand-tomato`
- **CSS Variable**: `rgb(var(--brand-tomato-500))`

### Accent Colors (10-30% usage)

#### Red CMYK
- **Primary**: `#E92628` (600 shade)
- **Usage**: Breaking news badges, urgent alerts, NEVER for text
- **Tailwind**: `bg-brand-red`, `text-brand-red`
- **CSS Variable**: `rgb(var(--brand-red-600))`

#### Mindaro (Lime Green)
- **Primary**: `#D4FB8C` (400 shade)
- **Usage**: Max 10%, sparingly for highlights, NEVER for text
- **Tailwind**: `bg-brand-mindaro`
- **CSS Variable**: `rgb(var(--brand-mindaro-400))`

## Typography

### Fonts

#### League Spartan Bold
- **Usage**: All headings, titles, logo
- **Weight**: 700 (Bold only)
- **CRITICAL**: MUST be UPPERCASE
- **Variable**: `--font-heading`
- **Tailwind**: `font-heading`

#### Poppins
- **Usage**: Body text, UI elements, labels
- **Weights**: 400 (Regular), 500 (Medium), 600 (SemiBold)
- **Variable**: `--font-body`
- **Tailwind**: `font-body`

### Typography Scale

| Element | Desktop | Mobile | Class |
|---------|---------|--------|-------|
| Hero Title | 48px | 32px | `text-hero-title(-mobile)` |
| H1 | 40px | 28px | `text-h1(-mobile)` |
| H2 | 32px | 24px | `text-h2(-mobile)` |
| H3 | 24px | 20px | `text-h3(-mobile)` |
| H4 | 20px | 18px | `text-h4(-mobile)` |
| Body Large | 19px | - | `text-body-lg` |
| Body | 17px | - | `text-body` |
| Body Small | 15px | - | `text-body-sm` |
| Accent Large | 16px | - | `text-accent-lg` |
| Accent | 14px | - | `text-accent` |

## Components

### Typography Components

#### Heading Component
```tsx
import { Heading, HeroHeading } from '@/components/typography';

// Standard heading
<Heading level={1} variant="oxford">
  Breaking News
</Heading>

// Hero heading (extra large)
<HeroHeading variant="tomato" align="center">
  Latest Updates
</HeroHeading>
```

**Props:**
- `level`: 1-4 (heading level)
- `variant`: 'oxford' | 'tomato' | 'default'
- `align`: 'left' | 'center' | 'right'
- `responsive`: boolean (auto-adjust size on mobile)

#### Text Component
```tsx
import { Text, ArticleBody, Meta, Quote } from '@/components/typography';

// Body text
<Text variant="body">Article content here...</Text>

// Accent text (meta info)
<Meta color="tomato">Published 2 hours ago</Meta>

// Article body (with prose styling)
<ArticleBody>
  <p>Long-form article content...</p>
</ArticleBody>

// Pull quote
<Quote author="John Doe">
  This is an important quote from the article.
</Quote>
```

**Props:**
- `variant`: 'body' | 'body-lg' | 'body-sm' | 'accent' | 'accent-lg'
- `color`: 'oxford' | 'tomato' | 'gray' | 'white'
- `weight`: 'normal' | 'medium' | 'semibold'
- `align`: 'left' | 'center' | 'right' | 'justify'

### Brand Components

#### Logo Component
```tsx
import { Logo, LogoWithSpacing, LogoIcon, LogoWithTagline } from '@/components/brand';

// Standard logo
<Logo variant="blue" size="md" />

// Logo with link
<Logo variant="white" size="lg" href="/" />

// Logo with required spacing
<LogoWithSpacing variant="red" size="md" />

// Compact icon (for mobile nav)
<LogoIcon variant="blue" size="sm" />

// Logo with tagline
<LogoWithTagline
  variant="blue"
  size="md"
  tagline="Știri din Moldova"
/>
```

**Props:**
- `variant`: 'blue' | 'red' | 'white'
- `size`: 'sm' (20px) | 'md' (40px) | 'lg' (60px) | 'xl' (80px)
- `href`: string (optional link)
- `withSpacing`: boolean (add clear space)

**Brand Rules:**
- Minimum size: 20px height
- Clear space: Equal to height of letter 'S'
- Never distort or modify letter spacing

## Utility Classes

### Brand Colors

```css
/* Text colors */
.text-brand-oxford
.text-brand-tomato
.text-brand-red

/* Background colors */
.bg-brand-oxford
.bg-brand-tomato
.bg-brand-red
.bg-brand-mindaro

/* Gradients */
.gradient-oxford-tomato
.gradient-tomato-red
.gradient-oxford-soft
.gradient-tomato-soft
```

### Typography

```css
/* Font families */
.font-heading  /* League Spartan Bold, UPPERCASE */
.font-body     /* Poppins */

/* Text on photos (with shadows) */
.text-on-photo
.text-on-photo-strong

/* Anti-aliasing */
.text-crisp
```

### Accents

```css
/* Accent bars (left border) */
.accent-bar-oxford
.accent-bar-tomato
.accent-bar-red
```

### Buttons

```css
/* Brand button styles */
.btn-brand-primary    /* Oxford blue */
.btn-brand-secondary  /* Tomato */
.btn-brand-accent     /* Red */
.btn-brand-outline    /* Outline style */
```

## Best Practices

### DO:
- ✅ Use Oxford Blue for 40% of design elements
- ✅ Use Tomato for 40-50% of design elements
- ✅ Always use UPPERCASE for League Spartan headings
- ✅ Maintain minimum 20px logo size
- ✅ Use `.text-on-photo` for text over images
- ✅ Use Poppins for all body text
- ✅ Follow responsive typography scale

### DON'T:
- ❌ Use Red CMYK or Mindaro for text (accessibility)
- ❌ Make logo smaller than 20px
- ❌ Use lowercase with League Spartan headings
- ❌ Mix font families within same text block
- ❌ Exceed 10% usage for Mindaro accent
- ❌ Modify logo letter spacing or proportions

## CSS Custom Properties

All brand colors are available as CSS variables in RGB format for opacity support:

```css
/* Oxford Blue */
--brand-oxford-900: 17 34 64;
/* Usage: rgb(var(--brand-oxford-900) / 0.8) */

/* Tomato */
--brand-tomato-500: 240 94 69;

/* Red */
--brand-red-600: 233 38 40;

/* Mindaro */
--brand-mindaro-400: 212 251 140;
```

## Accessibility

- **Contrast Ratios**: All text colors meet WCAG AA standards
- **Text on Photos**: Always use `.text-on-photo` class for readability
- **Never for Text**: Red CMYK and Mindaro (poor contrast)
- **Focus States**: All interactive elements have visible focus indicators

## Migration Guide

If migrating from old color scheme:

```tsx
// OLD (deprecated)
<h1 className="text-primary-700">Title</h1>

// NEW (brand compliant)
<Heading level={1} variant="oxford">Title</Heading>
// OR
<h1 className="font-heading text-brand-oxford">TITLE</h1>
```

## Resources

- **Brandbook**: `/var/www/deschide_news_app/docs/DESCHIDE_BRANDBOOK.pdf`
- **Design System**: `/var/www/deschide_news_app/context/design_principles_and_features.md`
- **Tailwind Config**: `/var/www/deschide_news_app/apps/frontend/tailwind.config.ts`
- **Global CSS**: `/var/www/deschide_news_app/apps/frontend/app/globals.css`
