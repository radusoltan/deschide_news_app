# 🎨 Plan de Adaptare Site Public conform Brandbook Deschide

**Data:** 2025-12-09
**Document sursă:** DESCHIDE_BRANDBOOK.pdf
**Scop:** Alinierea completă a site-ului public la identitatea vizuală oficială Deschide

---

## 📋 Sumar Executiv

### Status Actual vs. Brandbook

| Aspect | Status Actual | Conform Brandbook | Gap |
|--------|---------------|-------------------|-----|
| **Culori Principale** | ❌ Necunoscute | Oxford Blue (#112240) + Tomato (#F05E45) | 🔴 MAJOR |
| **Proporții Culori** | ❌ Random | 40% Blue, 40-50% Orange, 10% Mindaro | 🔴 MAJOR |
| **Fonturi Titluri** | ❓ Nedefinite | League Spartan Bold (CAPS ONLY) | 🔴 MAJOR |
| **Fonturi Body** | ❓ Nedefinite | Poppins Regular/Medium | 🔴 MAJOR |
| **Logo** | ⚠️ Existent | Min 20px, variante specifice | 🟡 MEDIU |
| **Accente Culoare** | ❌ Lipsa | Mindaro (#D4FB8C) max 10% | 🟡 MEDIU |

---

## 🎨 PARTEA 1: SISTEM DE CULORI

### 1.1 Paleta de Culori Oficială

**Implementare în Tailwind CSS (`tailwind.config.ts`):**

```typescript
// apps/frontend/tailwind.config.ts
export default {
  theme: {
    extend: {
      colors: {
        // Brand Colors - Deschide Official
        'deschide': {
          // Oxford Blue - Primary Dark
          'oxford-blue': {
            DEFAULT: '#112240',
            50: '#e8eaf0',
            100: '#c1c7d8',
            200: '#96a0be',
            300: '#6b7aa4',
            400: '#4b5e90',
            500: '#112240', // Base
            600: '#0e1d37',
            700: '#0b172c',
            800: '#081121',
            900: '#040913',
          },

          // Tomato - Primary Orange
          'tomato': {
            DEFAULT: '#F05E45',
            50: '#fef2f1',
            100: '#fde0dc',
            200: '#fbbfb7',
            300: '#f99d92',
            400: '#f77c6d',
            500: '#F05E45', // Base
            600: '#ec3720',
            700: '#c72610',
            800: '#981d0c',
            900: '#6a1408',
          },

          // Red CMYK - Accent Red
          'red-cmyk': {
            DEFAULT: '#E92628',
            50: '#fef0f0',
            100: '#fcd8d8',
            200: '#f9b1b2',
            300: '#f58a8b',
            400: '#f26365',
            500: '#E92628', // Base
            600: '#d40f11',
            700: '#a70c0e',
            800: '#7a090a',
            900: '#4d0607',
          },

          // Mindaro - Accent Green (max 10%)
          'mindaro': {
            DEFAULT: '#D4FB8C',
            50: '#f9fee9',
            100: '#f0fcc8',
            200: '#e7faa7',
            300: '#ddf886',
            400: '#D4FB8C', // Base
            500: '#c3f661',
            600: '#b2f136',
            700: '#9cd91f',
            800: '#78a818',
            900: '#547711',
          },
        },

        // Semantic Colors
        'brand-primary': '#112240',    // Oxford Blue
        'brand-secondary': '#F05E45',  // Tomato
        'brand-accent': '#D4FB8C',     // Mindaro (limited use)
        'brand-danger': '#E92628',     // Red CMYK
      },
    },
  },
}
```

### 1.2 Reguli de Utilizare Culori

**CSS Variables (`apps/frontend/app/globals.css`):**

```css
@layer base {
  :root {
    /* Brand Colors */
    --color-oxford-blue: 17 34 64;      /* #112240 */
    --color-tomato: 240 94 69;          /* #F05E45 */
    --color-red-cmyk: 233 38 40;        /* #E92628 */
    --color-mindaro: 212 251 140;       /* #D4FB8C */

    /* Usage Proportions (for reference) */
    /* Oxford Blue: 40% - Main backgrounds, headers */
    /* Tomato: 40-50% - Primary CTAs, highlights */
    /* Red CMYK: 10-30% - Secondary accents, borders */
    /* Mindaro: 5-10% - ACCENT ONLY, never full text */

    /* Text Colors - CRITICAL RULES */
    --text-on-light: var(--color-oxford-blue);  /* Blue text on light bg */
    --text-on-dark: 255 255 255;                /* White text on dark bg */
    /* NEVER: Red text (#E92628) - NOT ALLOWED per brandbook */
    /* NEVER: Full Mindaro text - only accents */
  }
}

/* Utility Classes */
.text-brand-primary {
  color: rgb(var(--color-oxford-blue));
}

.text-brand-secondary {
  color: rgb(var(--color-tomato));
}

.text-brand-accent {
  color: rgb(var(--color-mindaro));
  /* WARNING: Use sparingly, max 10% of design */
}

.bg-brand-primary {
  background-color: rgb(var(--color-oxford-blue));
}

.bg-brand-secondary {
  background-color: rgb(var(--color-tomato));
}

/* Gradients - Per Brandbook Social Media Guidelines */
.gradient-oxford-tomato {
  background: linear-gradient(135deg,
    rgb(var(--color-oxford-blue)) 0%,
    rgb(var(--color-tomato)) 100%
  );
}

.gradient-tomato-red {
  background: linear-gradient(135deg,
    rgb(var(--color-tomato)) 0%,
    rgb(var(--color-red-cmyk)) 100%
  );
}

/* Drop Shadow for Text on Photos - Per Brandbook Section 1.3 */
.text-on-photo {
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
}
```

---

## 📝 PARTEA 2: SISTEM TIPOGRAFIC

### 2.1 Instalare Fonturi

**Instalare via Google Fonts:**

```typescript
// apps/frontend/app/layout.tsx
import { League_Spartan, Poppins } from 'next/font/google'

// League Spartan Bold - DOAR pentru titluri (MAJUSCULE)
const leagueSpartan = League_Spartan({
  weight: ['700'], // Bold only
  subsets: ['latin', 'latin-ext'],
  variable: '--font-league-spartan',
  display: 'swap',
})

// Poppins - Pentru conținut
const poppins = Poppins({
  weight: ['400', '500', '600'], // Regular, Medium, SemiBold
  subsets: ['latin', 'latin-ext'],
  variable: '--font-poppins',
  display: 'swap',
})

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html
      lang="ro"
      className={`${leagueSpartan.variable} ${poppins.variable}`}
    >
      <body className="font-poppins">{children}</body>
    </html>
  )
}
```

### 2.2 Configurare Tailwind pentru Fonturi

```typescript
// apps/frontend/tailwind.config.ts
export default {
  theme: {
    extend: {
      fontFamily: {
        // League Spartan - Titluri DOAR MAJUSCULE
        'heading': ['var(--font-league-spartan)', 'sans-serif'],

        // Poppins - Body text
        'body': ['var(--font-poppins)', 'sans-serif'],
        'sans': ['var(--font-poppins)', 'sans-serif'], // Default
      },

      // Typography Scale - Optimized for Deschide
      fontSize: {
        // League Spartan sizes (headings)
        'h1': ['2.5rem', { lineHeight: '1.2', fontWeight: '700', letterSpacing: '0.02em' }],    // 40px
        'h2': ['2rem', { lineHeight: '1.25', fontWeight: '700', letterSpacing: '0.015em' }],    // 32px
        'h3': ['1.5rem', { lineHeight: '1.3', fontWeight: '700', letterSpacing: '0.01em' }],    // 24px
        'h4': ['1.25rem', { lineHeight: '1.4', fontWeight: '700', letterSpacing: '0.01em' }],   // 20px

        // Poppins sizes (body)
        'body-lg': ['1.125rem', { lineHeight: '1.7', fontWeight: '400' }],  // 18px
        'body': ['1rem', { lineHeight: '1.6', fontWeight: '400' }],         // 16px
        'body-sm': ['0.875rem', { lineHeight: '1.5', fontWeight: '400' }],  // 14px

        // Accent text (Poppins Medium)
        'accent': ['1rem', { lineHeight: '1.5', fontWeight: '500' }],
        'accent-lg': ['1.125rem', { lineHeight: '1.6', fontWeight: '500' }],
      },
    },
  },
}
```

### 2.3 Componente Tipografice

**Fișier: `apps/frontend/components/typography/Heading.tsx`**

```typescript
import { cn } from '@/lib/utils'

interface HeadingProps {
  children: React.ReactNode
  level: 1 | 2 | 3 | 4
  className?: string
}

export function Heading({ children, level, className }: HeadingProps) {
  const Tag = `h${level}` as const

  // IMPORTANT: League Spartan DOAR în MAJUSCULE per brandbook
  const uppercaseChildren = typeof children === 'string'
    ? children.toUpperCase()
    : children

  const baseClasses = 'font-heading uppercase tracking-wide'

  const sizeClasses = {
    1: 'text-h1 text-brand-primary',
    2: 'text-h2 text-brand-primary',
    3: 'text-h3 text-brand-primary',
    4: 'text-h4 text-brand-primary',
  }

  return (
    <Tag className={cn(baseClasses, sizeClasses[level], className)}>
      {uppercaseChildren}
    </Tag>
  )
}
```

**Fișier: `apps/frontend/components/typography/Text.tsx`**

```typescript
import { cn } from '@/lib/utils'

interface TextProps {
  children: React.ReactNode
  variant?: 'body' | 'body-lg' | 'body-sm' | 'accent' | 'accent-lg'
  className?: string
  asChild?: boolean
}

export function Text({
  children,
  variant = 'body',
  className
}: TextProps) {
  const baseClasses = 'font-body'

  const variantClasses = {
    'body': 'text-body',
    'body-lg': 'text-body-lg',
    'body-sm': 'text-body-sm',
    'accent': 'text-accent font-medium',     // Poppins Medium
    'accent-lg': 'text-accent-lg font-medium',
  }

  return (
    <p className={cn(baseClasses, variantClasses[variant], className)}>
      {children}
    </p>
  )
}
```

---

## 🎯 PARTEA 3: LOGO & BRANDING

### 3.1 Componenta Logo

**Fișier: `apps/frontend/components/brand/Logo.tsx`**

```typescript
import Image from 'next/image'
import { cn } from '@/lib/utils'

interface LogoProps {
  variant?: 'blue' | 'red' | 'white'  // Per brandbook section 2.1
  size?: 'sm' | 'md' | 'lg'
  className?: string
}

export function Logo({
  variant = 'blue',
  size = 'md',
  className
}: LogoProps) {
  // Minimum size: 20px per brandbook
  const sizes = {
    sm: { width: 20, height: 20 },   // Minimum
    md: { width: 40, height: 40 },
    lg: { width: 60, height: 60 },
  }

  // Logo variants per brandbook:
  // - Blue/Red: ONLY on white background
  // - White: on colored backgrounds or photos
  const logoSrc = {
    blue: '/logo-oxford-blue.svg',
    red: '/logo-red.svg',
    white: '/logo-white.svg',
  }

  return (
    <div className={cn('relative', className)} style={sizes[size]}>
      <Image
        src={logoSrc[variant]}
        alt="Deschide.md Logo"
        fill
        className="object-contain"
        priority
      />
    </div>
  )
}

// Usage example with proper spacing (per brandbook section 2.0)
export function LogoWithSpacing({ variant = 'blue' }: { variant?: 'blue' | 'red' | 'white' }) {
  return (
    <div className="inline-flex">
      {/* Clear space = logo size per brandbook */}
      <div className="p-[20px]">
        <Logo variant={variant} size="md" />
      </div>
    </div>
  )
}
```

### 3.2 Assets Logo (SVG)

**Creați fișierele în `apps/frontend/public/`:**

1. `logo-oxford-blue.svg` - RGB(17, 34, 64) - #112240
2. `logo-red.svg` - RGB(233, 38, 40) - #E92628
3. `logo-white.svg` - RGB(255, 255, 255) - #FFFFFF

**Template SVG (logo-oxford-blue.svg):**

```svg
<svg width="100" height="100" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
  <!-- Logo bazat pe D + E conform brandbook page 7 -->
  <path d="M20 20 ... [path data] ..." fill="#112240"/>
</svg>
```

---

## 🏠 PARTEA 4: COMPONENTE UI MAJORE

### 4.1 Header / Navigation

**Fișier: `apps/frontend/components/layout/Header.tsx`**

```typescript
import { Logo } from '@/components/brand/Logo'
import { Heading } from '@/components/typography/Heading'

export function Header() {
  return (
    <header className="sticky top-0 z-50 bg-deschide-oxford-blue">
      <div className="container mx-auto px-4">
        <div className="flex items-center justify-between h-16">
          {/* Logo with clear space */}
          <div className="flex items-center space-x-4">
            <Logo variant="white" size="md" />
            <Heading level={4} className="text-white">
              Deschide
            </Heading>
          </div>

          {/* Navigation */}
          <nav className="hidden md:flex space-x-8">
            <NavLink href="/politica">Politică</NavLink>
            <NavLink href="/economie">Economie</NavLink>
            <NavLink href="/social">Social</NavLink>
            <NavLink href="/sport">Sport</NavLink>
          </nav>
        </div>
      </div>
    </header>
  )
}

function NavLink({ href, children }: { href: string; children: React.ReactNode }) {
  return (
    <a
      href={href}
      className="font-heading uppercase text-sm text-white hover:text-deschide-mindaro transition-colors"
    >
      {children}
    </a>
  )
}
```

### 4.2 Article Card

**Fișier: `apps/frontend/components/articles/ArticleCard.tsx`**

```typescript
import Image from 'next/image'
import { Heading } from '@/components/typography/Heading'
import { Text } from '@/components/typography/Text'
import { cn } from '@/lib/utils'

interface ArticleCardProps {
  title: string
  excerpt: string
  image: string
  category: string
  date: string
  variant?: 'default' | 'featured' | 'compact'
}

export function ArticleCard({
  title,
  excerpt,
  image,
  category,
  date,
  variant = 'default'
}: ArticleCardProps) {
  const isFeature = variant === 'featured'

  return (
    <article className={cn(
      'group relative overflow-hidden rounded-lg',
      'bg-white border border-gray-200',
      'hover:shadow-xl transition-all duration-300',
      isFeature && 'col-span-2 row-span-2'
    )}>
      {/* Image */}
      <div className={cn(
        'relative overflow-hidden',
        isFeature ? 'h-96' : 'h-48'
      )}>
        <Image
          src={image}
          alt={title}
          fill
          className="object-cover group-hover:scale-105 transition-transform duration-500"
        />

        {/* Gradient overlay per brandbook social media guidelines */}
        <div className="absolute inset-0 gradient-oxford-tomato opacity-0 group-hover:opacity-20 transition-opacity" />

        {/* Category badge - Tomato color per brandbook */}
        <div className="absolute top-4 left-4">
          <span className="px-3 py-1 bg-deschide-tomato text-white text-xs font-heading uppercase rounded">
            {category}
          </span>
        </div>
      </div>

      {/* Content */}
      <div className="p-6">
        {/* Title - League Spartan Bold, uppercase per brandbook */}
        <Heading level={3} className="mb-2 line-clamp-2">
          {title}
        </Heading>

        {/* Excerpt - Poppins Regular */}
        <Text variant="body" className="text-gray-600 line-clamp-3 mb-4">
          {excerpt}
        </Text>

        {/* Meta */}
        <div className="flex items-center justify-between">
          <Text variant="body-sm" className="text-gray-500">
            {date}
          </Text>

          {/* CTA with Mindaro accent (max 10% usage) */}
          <button className="font-body text-sm font-medium text-deschide-oxford-blue hover:text-deschide-tomato transition-colors">
            <span className="mr-1">Citește</span>
            <span className="text-deschide-mindaro">→</span>
          </button>
        </div>
      </div>
    </article>
  )
}
```

### 4.3 Hero Section

**Fișier: `apps/frontend/components/sections/Hero.tsx`**

```typescript
import Image from 'next/image'
import { Heading } from '@/components/typography/Heading'
import { Text } from '@/components/typography/Text'

export function Hero() {
  return (
    <section className="relative h-[600px] overflow-hidden">
      {/* Background Image */}
      <Image
        src="/hero-image.jpg"
        alt="Hero"
        fill
        className="object-cover"
        priority
      />

      {/* Gradient overlay - per brandbook section 4.1 */}
      <div className="absolute inset-0 bg-gradient-to-r from-deschide-oxford-blue/90 via-deschide-oxford-blue/70 to-transparent" />

      {/* Content */}
      <div className="relative container mx-auto px-4 h-full flex items-center">
        <div className="max-w-2xl">
          {/* Breaking badge - Tomato color */}
          <div className="inline-flex items-center space-x-2 mb-4">
            <span className="h-2 w-2 bg-deschide-tomato rounded-full animate-pulse" />
            <Text variant="accent" className="text-white uppercase">
              Breaking News
            </Text>
          </div>

          {/* Title with drop shadow per brandbook section 1.3 */}
          <Heading
            level={1}
            className="text-white text-on-photo mb-4"
          >
            Titlu Principal Știre Breaking
          </Heading>

          {/* Description */}
          <Text
            variant="body-lg"
            className="text-white/90 text-on-photo mb-6"
          >
            Descrierea scurtă a știrii principale cu informații esențiale
            pentru cititori, păstrând tonul jurnalistic specific Deschide.
          </Text>

          {/* CTA Button - Tomato primary per brandbook */}
          <button className="px-8 py-3 bg-deschide-tomato hover:bg-deschide-tomato-600 text-white font-heading uppercase text-sm transition-colors rounded-lg">
            Citește Articolul
          </button>
        </div>
      </div>
    </section>
  )
}
```

### 4.4 Footer

**Fișier: `apps/frontend/components/layout/Footer.tsx`**

```typescript
import { Logo } from '@/components/brand/Logo'
import { Text } from '@/components/typography/Text'

export function Footer() {
  return (
    <footer className="bg-deschide-oxford-blue text-white">
      <div className="container mx-auto px-4 py-12">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
          {/* Brand Column */}
          <div className="col-span-1 md:col-span-2">
            <Logo variant="white" size="lg" className="mb-4" />
            <Text variant="body" className="text-white/80 max-w-md">
              Deschide.md - Portal de știri independent care oferă informații
              verificate și analize de profunzime pentru Moldova.
            </Text>

            {/* Social Links with proper color usage */}
            <div className="flex space-x-4 mt-6">
              <SocialLink href="#" icon="facebook" />
              <SocialLink href="#" icon="telegram" />
              <SocialLink href="#" icon="instagram" />
            </div>
          </div>

          {/* Links Columns */}
          <div>
            <FooterHeading>Categorii</FooterHeading>
            <FooterLink href="/politica">Politică</FooterLink>
            <FooterLink href="/economie">Economie</FooterLink>
            <FooterLink href="/social">Social</FooterLink>
            <FooterLink href="/sport">Sport</FooterLink>
          </div>

          <div>
            <FooterHeading>Legal</FooterHeading>
            <FooterLink href="/despre">Despre Noi</FooterLink>
            <FooterLink href="/contact">Contact</FooterLink>
            <FooterLink href="/termeni">Termeni</FooterLink>
            <FooterLink href="/confidentialitate">Confidențialitate</FooterLink>
          </div>
        </div>

        {/* Copyright */}
        <div className="border-t border-white/10 mt-8 pt-8">
          <Text variant="body-sm" className="text-white/60 text-center">
            © {new Date().getFullYear()} Deschide.md. Toate drepturile rezervate.
          </Text>
        </div>
      </div>
    </footer>
  )
}

function FooterHeading({ children }: { children: React.ReactNode }) {
  return (
    <h3 className="font-heading uppercase text-sm text-deschide-mindaro mb-4">
      {children}
    </h3>
  )
}

function FooterLink({ href, children }: { href: string; children: React.ReactNode }) {
  return (
    <a
      href={href}
      className="block font-body text-sm text-white/80 hover:text-deschide-tomato transition-colors mb-2"
    >
      {children}
    </a>
  )
}

function SocialLink({ href, icon }: { href: string; icon: string }) {
  return (
    <a
      href={href}
      className="w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-deschide-tomato transition-colors"
      aria-label={`Urmărește-ne pe ${icon}`}
    >
      {/* Icon SVG */}
    </a>
  )
}
```

---

## 📱 PARTEA 5: COMPONENTE SOCIALE (Social Media Style)

### 5.1 Breaking News Card (Social Media Inspired)

**Fișier: `apps/frontend/components/social/BreakingCard.tsx`**

```typescript
import Image from 'next/image'
import { Heading } from '@/components/typography/Heading'
import { Logo } from '@/components/brand/Logo'
import { cn } from '@/lib/utils'

interface BreakingCardProps {
  title: string
  image: string
  layout?: 'with-border' | 'no-border'
}

export function BreakingCard({
  title,
  image,
  layout = 'no-border'
}: BreakingCardProps) {
  const hasBorder = layout === 'with-border'

  return (
    <div className={cn(
      'relative aspect-square overflow-hidden',
      hasBorder && 'p-[5%] bg-deschide-oxford-blue' // 5% border per brandbook section 4.0
    )}>
      {/* Inner container */}
      <div className={cn(
        'relative w-full h-full overflow-hidden',
        hasBorder && 'rounded-lg'
      )}>
        {/* Image - occupies 3/5 per brandbook section 4.0 */}
        <div className="absolute top-0 left-0 right-0 h-[60%]">
          <Image
            src={image}
            alt={title}
            fill
            className="object-cover"
          />

          {/* Gradient overlay per brandbook section 4.1 */}
          <div className="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-deschide-oxford-blue/80" />
        </div>

        {/* Content area - occupies 2/5 */}
        <div className="absolute bottom-0 left-0 right-0 h-[40%] bg-deschide-oxford-blue p-6 flex flex-col justify-between">
          {/* Title - League Spartan Bold uppercase, white on dark */}
          <Heading
            level={3}
            className="text-white text-on-photo line-clamp-3"
          >
            {title}
          </Heading>

          {/* Logo positioned per brandbook section 2.2 */}
          <div className="flex justify-end">
            <Logo variant="white" size="sm" />
          </div>
        </div>
      </div>
    </div>
  )
}
```

### 5.2 Opinion Card (Tomato Background)

**Fișier: `apps/frontend/components/social/OpinionCard.tsx`**

```typescript
import Image from 'next/image'
import { Heading } from '@/components/typography/Heading'
import { Text } from '@/components/typography/Text'
import { Logo } from '@/components/brand/Logo'

interface OpinionCardProps {
  title: string
  author: {
    name: string
    photo: string
    title: string
  }
}

export function OpinionCard({ title, author }: OpinionCardProps) {
  return (
    <div className="relative aspect-square bg-deschide-tomato overflow-hidden">
      {/* Decorative background - based on logo shape per brandbook section 4.1 */}
      <div className="absolute inset-0">
        <svg viewBox="0 0 100 100" className="w-full h-full opacity-20">
          <circle cx="70" cy="30" r="40" fill="rgba(233, 38, 40, 0.5)" />
          <circle cx="30" cy="70" r="35" fill="rgba(17, 34, 64, 0.3)" />
        </svg>
      </div>

      {/* Content */}
      <div className="relative h-full p-8 flex flex-col">
        {/* Badge */}
        <Text variant="body-sm" className="text-white uppercase mb-4">
          📝 Opinia Este
        </Text>

        {/* Title */}
        <Heading level={2} className="text-white mb-auto">
          {title}
        </Heading>

        {/* Author */}
        <div className="flex items-center space-x-4">
          <div className="relative w-16 h-16 rounded-full overflow-hidden border-2 border-white">
            <Image
              src={author.photo}
              alt={author.name}
              fill
              className="object-cover"
            />
          </div>
          <div>
            <Text variant="accent" className="text-white">
              {author.name}
            </Text>
            <Text variant="body-sm" className="text-white/80">
              {author.title}
            </Text>
          </div>
        </div>

        {/* Logo - bottom right per brandbook section 2.2 */}
        <div className="absolute bottom-8 right-8">
          <Logo variant="white" size="md" />
        </div>
      </div>
    </div>
  )
}
```

---

## 🎨 PARTEA 6: REGULI STRICTE DE BRAND

### 6.1 Design System Do's and Don'ts

**Fișier: `apps/frontend/docs/BRAND_RULES.md`**

```markdown
# Reguli Stricte de Brand Deschide

## ✅ DO's (Permis)

### Culori
- ✅ Fundal albastru (Oxford Blue) pentru header/footer
- ✅ Fundal portocaliu (Tomato) pentru CTA-uri și accente
- ✅ Text alb pe fundal întunecat
- ✅ Text albastru pe fundal deschis
- ✅ Mindaro DOAR ca accent vizual (max 10%)
- ✅ Drop shadow pe text pe fotografii

### Tipografie
- ✅ League Spartan Bold DOAR în MAJUSCULE pentru titluri
- ✅ Poppins Regular pentru body text
- ✅ Poppins Medium pentru accente în text

### Logo
- ✅ Logo minim 20px (web) / 12mm (print)
- ✅ Logo roșu/albastru DOAR pe fundal alb
- ✅ Logo alb pe fundal colorat sau fotografii
- ✅ Clear space = dimensiunea logo-ului

### Layout
- ✅ Gradiente Oxford Blue → Tomato
- ✅ Fundal decorativ bazat pe forma logo-ului (doar layout fără chenar)

## ❌ DON'Ts (INTERZIS)

### Culori
- ❌ **NICIODATĂ text roșu (#E92628)** - STRICT INTERZIS
- ❌ **NICIODATĂ text complet în Mindaro** - doar accente
- ❌ Fundal roșu monoton (excepție: chenar roșu în social media)
- ❌ Chenar Mindaro sau albastru în social media

### Tipografie
- ❌ League Spartan în minuscule
- ❌ Alte fonturi pentru titluri/body
- ❌ Poppins pentru titluri principale

### Logo
- ❌ Logo mai mic de 20px (web) / 12mm (print)
- ❌ Logo roșu/albastru pe fundal colorat
- ❌ Logo fără clear space adecvat
- ❌ Distorsiune/modificare logo

### Layout
- ❌ Fundal decorativ în layout cu chenar
- ❌ Ignorarea proporțiilor 40%/40%/10%/10%
```

### 6.2 Color Validation Hook

**Fișier: `apps/frontend/lib/hooks/useBrandValidation.ts`**

```typescript
/**
 * Hook pentru validarea utilizării corecte a culorilor conform brandbook
 */
export function useBrandValidation() {
  const validateTextColor = (color: string, background: string) => {
    const rules = {
      // Red text is FORBIDDEN per brandbook section 1.2, 1.3
      redTextForbidden: !color.includes('#E92628') && !color.includes('233, 38, 40'),

      // Mindaro text only as accent, not full paragraphs
      mindaroLimited: !color.includes('#D4FB8C') || isAccentUsage(),

      // Proper contrast
      contrastValid: checkContrast(color, background) >= 4.5,
    }

    return {
      isValid: Object.values(rules).every(Boolean),
      violations: Object.entries(rules)
        .filter(([_, valid]) => !valid)
        .map(([rule]) => rule),
    }
  }

  const validateLogoVariant = (variant: 'blue' | 'red' | 'white', background: string) => {
    // Logo roșu/albastru DOAR pe fundal alb - brandbook section 2.1
    if ((variant === 'blue' || variant === 'red') && background !== '#FFFFFF') {
      return {
        isValid: false,
        message: 'Logo albastru/roșu DOAR pe fundal alb. Folosește varianta albă.',
      }
    }

    // Logo alb pe fundal colorat sau fotografii
    if (variant === 'white' && background === '#FFFFFF') {
      return {
        isValid: false,
        message: 'Logo alb pe fundal alb nu este vizibil. Folosește varianta albastră.',
      }
    }

    return { isValid: true }
  }

  return {
    validateTextColor,
    validateLogoVariant,
  }
}

function isAccentUsage(): boolean {
  // Check if Mindaro is used sparingly (max 10%)
  return true // Implement based on component usage
}

function checkContrast(foreground: string, background: string): number {
  // Implement WCAG contrast calculation
  return 7.0 // Placeholder
}
```

---

## 📊 PARTEA 7: PLAN DE IMPLEMENTARE

### 7.1 Faze de Implementare

| Fază | Componente | Durată | Prioritate |
|------|------------|--------|------------|
| **Faza 1: Fundație** | Culori, Fonturi, Logo | 1-2 zile | 🔴 CRITICĂ |
| **Faza 2: Layout Principal** | Header, Footer, Navigation | 1 zi | 🔴 CRITICĂ |
| **Faza 3: Componente Articole** | ArticleCard, Hero, Listing | 2 zile | 🟡 ÎNALTĂ |
| **Faza 4: Pagini** | Homepage, Article Page, Category | 2-3 zile | 🟡 ÎNALTĂ |
| **Faza 5: Social Inspired** | Breaking Card, Opinion Card | 1 zi | 🟢 MEDIE |
| **Faza 6: Polish** | Animations, Micro-interactions | 1 zi | 🟢 SCĂZUTĂ |
| **Faza 7: Validare** | Brand compliance, Accessibility | 1 zi | 🔴 CRITICĂ |

**TOTAL ESTIMAT:** 9-11 zile lucru

### 7.2 Checklist Implementare

#### Faza 1: Fundație ✅

- [ ] **Culori**
  - [ ] Instalează Tailwind config cu paleta Deschide
  - [ ] Creează CSS variables în globals.css
  - [ ] Validează coduri HEX: #112240, #F05E45, #E92628, #D4FB8C
  - [ ] Implementează utility classes (bg-*, text-*)
  - [ ] Creează gradiente conform brandbook

- [ ] **Fonturi**
  - [ ] Instalează League Spartan Bold via Google Fonts
  - [ ] Instalează Poppins (Regular, Medium) via Google Fonts
  - [ ] Configurează font variables în layout.tsx
  - [ ] Creează componente Heading (uppercase forțat)
  - [ ] Creează componente Text (Poppins)
  - [ ] Testează rendering pe diferite browsere

- [ ] **Logo**
  - [ ] Exportează SVG logo în 3 variante (blue, red, white)
  - [ ] Creează componenta Logo cu variante
  - [ ] Implementează clear space rules
  - [ ] Verifică minimum size (20px web)
  - [ ] Testează pe fundal alb/colorat/foto

#### Faza 2: Layout Principal ✅

- [ ] **Header**
  - [ ] Fundal Oxford Blue (#112240)
  - [ ] Logo alb cu clear space
  - [ ] Navigare cu League Spartan uppercase
  - [ ] Hover state: Mindaro (#D4FB8C)
  - [ ] Responsive mobile menu

- [ ] **Footer**
  - [ ] Fundal Oxford Blue
  - [ ] Secțiuni cu Poppins
  - [ ] Headings cu Mindaro accent
  - [ ] Link-uri hover: Tomato
  - [ ] Social icons cu culori brand

- [ ] **Navigation**
  - [ ] Submeniuri cu fundal Tomato
  - [ ] Text alb pe fundal colorat
  - [ ] Drop shadow pe text overlay fotografii

#### Faza 3: Componente Articole ✅

- [ ] **ArticleCard**
  - [ ] Proporții imagine/text conform brandbook
  - [ ] Gradient overlay la hover
  - [ ] Badge categorie: Tomato background
  - [ ] Titlu: League Spartan Bold uppercase
  - [ ] Excerpt: Poppins Regular
  - [ ] CTA cu Mindaro accent (săgeată)

- [ ] **Hero Section**
  - [ ] Gradient Oxford Blue → transparent
  - [ ] Text cu drop shadow pe fotografie
  - [ ] Breaking badge: Tomato cu pulse animation
  - [ ] CTA button: Tomato background

- [ ] **Article Grid**
  - [ ] Bento grid layout (modern)
  - [ ] Featured articles: span 2x2
  - [ ] Proporții culori: 40% blue, 40% orange

#### Faza 4: Pagini Majore ✅

- [ ] **Homepage**
  - [ ] Hero section (60% viewport)
  - [ ] Breaking news section (Tomato accent)
  - [ ] Article grid (mix sizes)
  - [ ] Categories showcase
  - [ ] Newsletter signup (Mindaro accent)

- [ ] **Article Page**
  - [ ] Hero image cu gradient
  - [ ] Breadcrumbs (Oxford Blue)
  - [ ] Body text: Poppins Regular 16px/1.6
  - [ ] Headings: League Spartan Bold uppercase
  - [ ] Share buttons: Tomato hover
  - [ ] Related articles

- [ ] **Category Page**
  - [ ] Hero banner: category color
  - [ ] Filter/sort UI (Oxford Blue)
  - [ ] Article listing grid
  - [ ] Pagination

#### Faza 5: Social Inspired Components ✅

- [ ] **Breaking Card**
  - [ ] Layout cu/fără chenar (5% margins)
  - [ ] Imagine: 3/5 sau 2/5
  - [ ] Gradient overlay
  - [ ] Logo placement (bottom right)

- [ ] **Opinion Card**
  - [ ] Fundal Tomato
  - [ ] Fundal decorativ (forma logo)
  - [ ] Author foto cu border alb
  - [ ] Logo: colț dreapta jos

- [ ] **Dialog Deschis Card**
  - [ ] Layout specific per brandbook
  - [ ] Logo: colț dreapta jos

#### Faza 6: Polish & Animations ✅

- [ ] **Micro-interactions**
  - [ ] Hover effects pe carduri (scale 1.05)
  - [ ] Button transitions (200ms)
  - [ ] Gradient animations
  - [ ] Scroll animations (fade in)

- [ ] **Loading States**
  - [ ] Skeleton screens (Oxford Blue)
  - [ ] Spinner: Tomato color
  - [ ] Progress bars: Mindaro accent

- [ ] **Error States**
  - [ ] Error messages: Red CMYK (#E92628) background
  - [ ] Success messages: Mindaro background
  - [ ] Warning: Tomato background

#### Faza 7: Validare & QA ✅

- [ ] **Brand Compliance**
  - [ ] Audit toate culorile folosite
  - [ ] Verifică: ZERO text roșu (#E92628)
  - [ ] Verifică: Mindaro max 10%
  - [ ] Verifică: Logo variants corecte
  - [ ] Verifică: Fonturi doar League Spartan + Poppins

- [ ] **Accessibility**
  - [ ] Contrast ratio WCAG AA (4.5:1)
  - [ ] Keyboard navigation
  - [ ] Screen reader support
  - [ ] Alt texts pentru imagini

- [ ] **Performance**
  - [ ] Font loading optimized
  - [ ] Image optimization (WebP)
  - [ ] CSS bundle size
  - [ ] Core Web Vitals

- [ ] **Cross-browser**
  - [ ] Chrome/Edge (Chromium)
  - [ ] Firefox
  - [ ] Safari (desktop + mobile)
  - [ ] Mobile browsers (iOS Safari, Chrome Android)

### 7.3 Tools & Scripts

**Fișier: `scripts/brand-audit.ts`**

```typescript
/**
 * Script pentru audit automat brand compliance
 * Usage: pnpm brand-audit
 */
import fs from 'fs'
import path from 'path'

const FORBIDDEN_PATTERNS = {
  redText: /color:\s*#E92628|text-\[#E92628\]|rgb\(233,\s*38,\s*40\)/gi,
  wrongFonts: /font-family:\s*(?!.*(?:League\s*Spartan|Poppins|var\(--font))[^;]+/gi,
  mindaroOveruse: /text-deschide-mindaro(?!.*\baccent\b)/gi,
}

async function auditFiles(dir: string) {
  const files = await getAllFiles(dir)
  const violations: any[] = []

  for (const file of files) {
    if (!file.endsWith('.tsx') && !file.endsWith('.css')) continue

    const content = fs.readFileSync(file, 'utf-8')

    // Check for red text (FORBIDDEN)
    const redTextMatches = content.match(FORBIDDEN_PATTERNS.redText)
    if (redTextMatches) {
      violations.push({
        file,
        rule: 'RED_TEXT_FORBIDDEN',
        message: 'Text roșu (#E92628) este INTERZIS conform brandbook section 1.2',
        matches: redTextMatches,
      })
    }

    // Check for wrong fonts
    const fontMatches = content.match(FORBIDDEN_PATTERNS.wrongFonts)
    if (fontMatches) {
      violations.push({
        file,
        rule: 'WRONG_FONTS',
        message: 'Doar League Spartan și Poppins sunt permise',
        matches: fontMatches,
      })
    }

    // Check for Mindaro overuse
    const mindaroMatches = content.match(FORBIDDEN_PATTERNS.mindaroOveruse)
    if (mindaroMatches && mindaroMatches.length > 5) {
      violations.push({
        file,
        rule: 'MINDARO_OVERUSE',
        message: 'Mindaro folosit prea des (max 10% în design)',
        count: mindaroMatches.length,
      })
    }
  }

  return violations
}

async function getAllFiles(dir: string): Promise<string[]> {
  const files: string[] = []
  const items = fs.readdirSync(dir)

  for (const item of items) {
    const fullPath = path.join(dir, item)
    const stat = fs.statSync(fullPath)

    if (stat.isDirectory() && !item.startsWith('.') && item !== 'node_modules') {
      files.push(...await getAllFiles(fullPath))
    } else if (stat.isFile()) {
      files.push(fullPath)
    }
  }

  return files
}

// Run audit
auditFiles('./apps/frontend').then(violations => {
  if (violations.length === 0) {
    console.log('✅ Brand compliance: PASSED')
  } else {
    console.log('❌ Brand compliance: FAILED')
    console.log(`Found ${violations.length} violations:`)
    violations.forEach((v, i) => {
      console.log(`\n${i + 1}. ${v.rule}`)
      console.log(`   File: ${v.file}`)
      console.log(`   ${v.message}`)
    })
    process.exit(1)
  }
})
```

**Adaugă în `package.json`:**

```json
{
  "scripts": {
    "brand-audit": "tsx scripts/brand-audit.ts",
    "pre-commit": "pnpm brand-audit && pnpm lint"
  }
}
```

---

## 📚 PARTEA 8: DOCUMENTAȚIE & TRAINING

### 8.1 Ghid pentru Developeri

**Fișier: `apps/frontend/docs/DEVELOPER_BRAND_GUIDE.md`**

```markdown
# Ghid Brand pentru Developeri

## Quick Reference

### Culori - Copy/Paste

```tsx
// Oxford Blue (Primary)
className="bg-deschide-oxford-blue text-white"

// Tomato (Secondary)
className="bg-deschide-tomato text-white"

// Mindaro (Accent - USE SPARINGLY!)
className="text-deschide-mindaro" // Only for small accents

// ❌ NEVER USE
className="text-[#E92628]" // RED TEXT IS FORBIDDEN
```

### Tipografie - Copy/Paste

```tsx
// Titlu (League Spartan Bold, uppercase)
<Heading level={1}>Titlu Automat Uppercase</Heading>

// Body text (Poppins Regular)
<Text variant="body">Conținut articol...</Text>

// Accent text (Poppins Medium)
<Text variant="accent">Text accentuat</Text>
```

### Logo - Copy/Paste

```tsx
// Pe fundal alb
<Logo variant="blue" size="md" />

// Pe fundal colorat sau foto
<Logo variant="white" size="md" />

// ❌ NEVER
<Logo variant="red" /> pe fundal colorat
```

## Common Mistakes ❌

1. **Text roșu** - INTERZIS per brandbook
2. **League Spartan în minuscule** - Doar CAPS
3. **Mindaro overuse** - Max 10%, doar accente
4. **Logo prea mic** - Minimum 20px
5. **Logo roșu pe fundal colorat** - Doar pe alb

## Pre-commit Checklist ✅

- [ ] No red text (#E92628)
- [ ] League Spartan only in UPPERCASE
- [ ] Mindaro used sparingly (<10%)
- [ ] Logo size >= 20px
- [ ] Logo variant correct for background
- [ ] Drop shadow on text over photos
```

### 8.2 Design Tokens Export

**Fișier: `apps/frontend/design-tokens.json`**

```json
{
  "colors": {
    "brand": {
      "oxford-blue": {
        "value": "#112240",
        "type": "color",
        "usage": "Primary backgrounds, headers, text on light",
        "proportion": "40%"
      },
      "tomato": {
        "value": "#F05E45",
        "type": "color",
        "usage": "CTA buttons, highlights, accents",
        "proportion": "40-50%"
      },
      "red-cmyk": {
        "value": "#E92628",
        "type": "color",
        "usage": "Secondary accents, borders, badges",
        "proportion": "10-30%",
        "warning": "NEVER use for text"
      },
      "mindaro": {
        "value": "#D4FB8C",
        "type": "color",
        "usage": "Small accents only (arrows, icons)",
        "proportion": "5-10%",
        "warning": "NEVER use for full text blocks"
      }
    }
  },
  "typography": {
    "heading": {
      "fontFamily": "League Spartan",
      "fontWeight": "700",
      "textTransform": "uppercase",
      "usage": "Titles only, always UPPERCASE"
    },
    "body": {
      "fontFamily": "Poppins",
      "fontWeight": "400",
      "usage": "Body text, paragraphs"
    },
    "accent": {
      "fontFamily": "Poppins",
      "fontWeight": "500",
      "usage": "Emphasis in text, intermediate font"
    }
  },
  "logo": {
    "minSize": {
      "web": "20px",
      "print": "12mm"
    },
    "clearSpace": "Equal to logo size",
    "variants": {
      "blue": "Only on white background",
      "red": "Only on white background",
      "white": "On colored backgrounds or photos"
    }
  }
}
```

---

## 🎯 PARTEA 9: SUCCESS METRICS

### 9.1 KPI-uri Brand Compliance

| Metric | Target | Cum se măsoară |
|--------|--------|----------------|
| **Brand Audit Score** | 100% | Script automat `brand-audit.ts` |
| **Font Compliance** | 100% | Doar League Spartan + Poppins |
| **Color Compliance** | 100% | Doar paleta oficială |
| **Red Text Violations** | 0 | Zero instanțe text #E92628 |
| **Mindaro Usage** | <10% | Audit vizual componente |
| **Logo Size Violations** | 0 | Min 20px verificat |
| **Accessibility (WCAG AA)** | 100% | Lighthouse audit |

### 9.2 Monitoring & Alerts

**Fișier: `.github/workflows/brand-compliance.yml`**

```yaml
name: Brand Compliance Check

on:
  pull_request:
    branches: [main, develop]
  push:
    branches: [main, develop]

jobs:
  brand-audit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Setup Node
        uses: actions/setup-node@v3
        with:
          node-version: '18'

      - name: Install dependencies
        run: pnpm install

      - name: Run Brand Audit
        run: pnpm brand-audit

      - name: Comment PR with violations
        if: failure()
        uses: actions/github-script@v6
        with:
          script: |
            github.rest.issues.createComment({
              issue_number: context.issue.number,
              owner: context.repo.owner,
              repo: context.repo.repo,
              body: '❌ Brand compliance check failed. Please review violations.'
            })
```

---

## 📦 PARTEA 10: ASSETS & RESOURCES

### 10.1 Required Assets

**Descarcă/Creează următoarele assets:**

```
apps/frontend/public/
├── logo/
│   ├── logo-oxford-blue.svg          # Logo albastru (#112240)
│   ├── logo-red.svg                  # Logo roșu (#E92628)
│   ├── logo-white.svg                # Logo alb (#FFFFFF)
│   └── logo-monochrome.svg           # Pentru favicon
├── fonts/
│   ├── LeagueSpartan-Bold.woff2      # Backup local
│   └── Poppins-Regular.woff2         # Backup local
├── gradients/
│   ├── oxford-tomato.svg             # Gradient presets
│   └── tomato-red.svg
└── patterns/
    └── logo-pattern.svg              # Fundal decorativ bazat pe logo
```

### 10.2 Figma / Design Files

**Link-uri utile:**

- Brandbook PDF: `/docs/DESCHIDE_BRANDBOOK.pdf`
- Design tokens: `apps/frontend/design-tokens.json`
- Component library: TBD (va fi creat în Figma)

---

## ✅ CONCLUZIE

### Prioritizare Implementare

**URGENT (Săptămâna 1):**
1. ✅ Instalare culori + Tailwind config
2. ✅ Instalare fonturi (League Spartan + Poppins)
3. ✅ Creeare componente base (Heading, Text, Logo)
4. ✅ Header + Footer cu brand corect

**IMPORTANT (Săptămâna 2):**
5. ✅ Article cards conform brandbook
6. ✅ Hero section + homepage layout
7. ✅ Category pages + article pages

**NICE-TO-HAVE (Săptămâna 3):**
8. ✅ Social media inspired components
9. ✅ Animations + polish
10. ✅ Brand audit automation

### Success Criteria

✅ **GATA** când:
- [ ] Brand audit script returnează 0 violations
- [ ] Lighthouse accessibility >= 95
- [ ] Toate componentele folosesc paleta oficială
- [ ] Zero text roșu (#E92628) în aplicație
- [ ] Logo-ul respectă min size (20px) și clear space
- [ ] Fonturi: doar League Spartan Bold (caps) + Poppins
- [ ] Mindaro usage < 10% în fiecare pagină

---

**Plan elaborat de:** workflow-orchestrator agent
**Data:** 2025-12-09
**Status:** ✅ GATA PENTRU IMPLEMENTARE
**Next step:** Începe cu Faza 1 (Fundație - Culori & Fonturi)
