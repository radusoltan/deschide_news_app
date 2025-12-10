# Deschide Brand System - Quick Reference Card

## Import Components

```tsx
// Typography
import { Heading, HeroHeading } from '@/components/typography';
import { Text, Meta, Quote, ArticleBody } from '@/components/typography';

// Brand
import { Logo, LogoWithSpacing, LogoIcon, LogoWithTagline } from '@/components/brand';
```

## Colors (Tailwind Classes)

| Color | Background | Text | Border | Usage |
|-------|------------|------|--------|-------|
| **Oxford Blue** | `bg-brand-oxford` | `text-brand-oxford` | `border-brand-oxford` | 40% - Primary |
| **Tomato** | `bg-brand-tomato` | `text-brand-tomato` | `border-brand-tomato` | 40-50% - Secondary |
| **Red** | `bg-brand-red` | `text-brand-red` | `border-brand-red` | 10-30% - Accent |
| **Mindaro** | `bg-brand-mindaro` | ❌ Never | ❌ Never | Max 10% - Backgrounds only |

## Typography

### Headings (League Spartan Bold, UPPERCASE)

```tsx
<HeroHeading variant="oxford">Breaking News</HeroHeading>
<Heading level={1} variant="oxford">Article Title</Heading>
<Heading level={2} variant="tomato">Section Title</Heading>
<Heading level={3}>Subsection</Heading>
<Heading level={4} align="center">Small Heading</Heading>
```

### Text (Poppins)

```tsx
<Text variant="body-lg">Introduction paragraph...</Text>
<Text variant="body">Main content...</Text>
<Text variant="body-sm">Caption or small text...</Text>
<Meta color="tomato">PUBLISHED 2 HOURS AGO</Meta>
<Quote author="John Doe">Important quote...</Quote>
```

## Logo

```tsx
// Standard
<Logo variant="blue" size="md" />
<Logo variant="red" size="lg" href="/" />
<Logo variant="white" size="md" /> // On dark bg

// Special
<LogoWithSpacing variant="blue" size="md" />
<LogoIcon variant="blue" size="sm" />
<LogoWithTagline variant="blue" size="md" tagline="Știri din Moldova" />
```

## Utility Classes

### Text on Photos
```tsx
<h1 className="text-on-photo">Readable Text Over Image</h1>
<h1 className="text-on-photo-strong">Extra Strong Shadow</h1>
```

### Gradients
```tsx
<div className="gradient-oxford-tomato">Blue to Red</div>
<div className="gradient-tomato-red">Red to Darker Red</div>
```

### Accent Bars
```tsx
<div className="accent-bar-oxford p-6">Oxford Left Border</div>
<div className="accent-bar-tomato p-6">Tomato Left Border</div>
<div className="accent-bar-red p-6">Red Left Border</div>
```

### Buttons
```tsx
<button className="btn-brand-primary">Primary</button>
<button className="btn-brand-secondary">Secondary</button>
<button className="btn-brand-accent">Accent</button>
<button className="btn-brand-outline">Outline</button>
```

## Font Utilities

```tsx
<h1 className="font-heading">UPPERCASE HEADING</h1>
<p className="font-body">Body text content</p>
<p className="text-crisp">Crisp anti-aliasing</p>
```

## Container

```tsx
<div className="container-deschide">
  {/* Max-width container with proper padding */}
</div>
```

## Color Hex Codes

| Color | Hex | Usage |
|-------|-----|-------|
| Oxford Blue | `#112240` | Primary brand |
| Tomato | `#F05E45` | Secondary brand |
| Red CMYK | `#E92628` | Accent only |
| Mindaro | `#D4FB8C` | Highlights only |

## Typography Scale

| Size | Desktop | Mobile | Class |
|------|---------|--------|-------|
| Hero | 48px | 32px | `text-hero-title(-mobile)` |
| H1 | 40px | 28px | `text-h1(-mobile)` |
| H2 | 32px | 24px | `text-h2(-mobile)` |
| H3 | 24px | 20px | `text-h3(-mobile)` |
| H4 | 20px | 18px | `text-h4(-mobile)` |
| Body Lg | 19px | - | `text-body-lg` |
| Body | 17px | - | `text-body` |
| Body Sm | 15px | - | `text-body-sm` |

## DO's and DON'Ts

### ✅ DO
- Use Oxford Blue for 40% of design
- Use Tomato for 40-50% of design
- Use UPPERCASE for League Spartan headings
- Use `.text-on-photo` for text over images
- Maintain minimum 20px logo size
- Use Poppins for all body text

### ❌ DON'T
- Use Red or Mindaro for text (poor contrast)
- Make logo smaller than 20px
- Use lowercase with League Spartan
- Exceed 10% Mindaro usage
- Modify logo letter spacing
- Mix fonts within same text block

## Common Patterns

### Article Header
```tsx
<div className="relative">
  <img src="..." alt="..." />
  <div className="absolute bottom-0 left-0 p-6">
    <Meta color="tomato">POLITICĂ</Meta>
    <Heading level={1} className="text-on-photo text-white mt-2">
      Article Title Here
    </Heading>
  </div>
</div>
```

### Category Badge
```tsx
<span className="bg-brand-tomato text-white px-3 py-1 rounded-full text-accent font-medium uppercase">
  Politică
</span>
```

### Card with Accent
```tsx
<article className="accent-bar-oxford bg-white p-6 rounded-lg">
  <Meta color="oxford">ECONOMIE</Meta>
  <Heading level={3} className="mt-2">Card Title</Heading>
  <Text variant="body-sm" className="mt-2">Card content...</Text>
</article>
```

---

**Full Documentation**: `/var/www/deschide_news_app/apps/frontend/components/brand/README.md`
