# Social Media Card Components

Premium social media style card components for the Deschide brand, designed according to the brandbook specifications (Sections 4.0-4.1).

## Components

### 1. BreakingCard

Breaking news card optimized for social media sharing with two layout variants.

**Features:**
- Two layouts: `with-border` (5% Oxford Blue margin) and `no-border`
- Image occupies 60% (top), content 40% (bottom)
- Gradient overlay for text readability
- League Spartan Bold UPPERCASE title
- Optional category badge
- White logo in bottom right corner
- Square aspect ratio (1:1)
- Hover scale animation (1.02x)

**Usage:**

```tsx
import { BreakingCard } from '@/components/social';

<BreakingCard
  title="Breaking: Major Development in Politics"
  image="/images/breaking-news.jpg"
  layout="with-border"
  category="Politică"
/>
```

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `title` | `string` | required | Article title (displayed in UPPERCASE) |
| `image` | `string` | required | Image URL or path |
| `layout` | `'with-border' \| 'no-border'` | `'with-border'` | Card layout variant |
| `category` | `string` | optional | Category badge text |
| `className` | `string` | optional | Additional CSS classes |

---

### 2. OpinionCard

Opinion/editorial card with decorative Tomato background.

**Features:**
- Tomato gradient background (#F05E45)
- Decorative circular shapes (logo-inspired)
- "Opinie" badge (customizable)
- League Spartan Bold UPPERCASE title
- Circular author photo with white border
- Author name and title
- White logo in bottom right corner
- Square aspect ratio (1:1)
- Hover scale animation (1.02x)

**Usage:**

```tsx
import { OpinionCard } from '@/components/social';

<OpinionCard
  title="Why Moldova Needs Political Reform Now"
  author={{
    name: "Ion Popescu",
    photo: "/images/authors/ion-popescu.jpg",
    title: "Political Analyst"
  }}
  badge="Opinia Este"
/>
```

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `title` | `string` | required | Opinion title (displayed in UPPERCASE) |
| `author.name` | `string` | required | Author full name |
| `author.photo` | `string` | required | Author photo URL (will be circular) |
| `author.title` | `string` | required | Author job title/role |
| `badge` | `string` | `'Opinie'` | Badge text (e.g., "Opinia Este") |
| `className` | `string` | optional | Additional CSS classes |

---

### 3. QuoteCard

Quote/interview card with Oxford Blue background and decorative quote marks.

**Features:**
- Oxford Blue gradient background (#112240)
- Large decorative quote marks in Mindaro (subtle)
- Poppins italic quote text
- Decorative line before attribution
- Author name and optional title
- White logo in bottom right corner
- Subtle background pattern
- Square aspect ratio (1:1)
- Hover scale animation (1.02x)

**Usage:**

```tsx
import { QuoteCard } from '@/components/social';

<QuoteCard
  quote="Moldova's future depends on transparency and accountability in government."
  author="Maria Ionescu"
  authorTitle="Former Minister of Justice"
/>
```

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `quote` | `string` | required | Quote text (displayed in italic) |
| `author` | `string` | required | Person being quoted |
| `authorTitle` | `string` | optional | Author's title/role |
| `className` | `string` | optional | Additional CSS classes |

---

## Design Specifications

### Brand Compliance

All components follow the Deschide brandbook:

- **Typography**: League Spartan Bold (headings, always UPPERCASE), Poppins (body text)
- **Colors**: Oxford Blue (#112240), Tomato (#F05E45), Mindaro (#D4FB8C - max 10% usage)
- **Logo**: White variant, size="sm", bottom right placement
- **Aspect Ratio**: Square (1:1) for optimal social media sharing
- **Shadows**: Drop shadows on text over photos, card shadows for depth

### Accessibility

All components include:
- Proper ARIA labels (`role="article"`, `aria-label`)
- Semantic HTML (`<blockquote>`, `<cite>`, `<h2>`)
- Alt text support for images
- Keyboard navigation support (inherited from hover states)

### Performance

Optimized for:
- Next.js Image component with proper `sizes` attribute
- Lazy loading where appropriate
- CSS transforms for smooth animations (GPU-accelerated)
- Minimal re-renders with proper prop typing

### Responsive Design

All cards are fully responsive:
- Mobile: Smaller text, compact padding
- Tablet: Medium text and padding
- Desktop: Full-size text and generous padding
- Breakpoints: sm (640px), md (768px), lg (1024px)

## Usage Examples

### Grid Layout (3 cards)

```tsx
<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
  <BreakingCard
    title="Breaking: Major Development"
    image="/news/breaking.jpg"
    layout="with-border"
    category="Politică"
  />

  <OpinionCard
    title="Why Reform Matters Now"
    author={{
      name: "Ion Popescu",
      photo: "/authors/ion.jpg",
      title: "Political Analyst"
    }}
  />

  <QuoteCard
    quote="The future belongs to those who prepare for it today."
    author="Maria Ionescu"
    authorTitle="Former Minister"
  />
</div>
```

### Single Card (Featured)

```tsx
<div className="max-w-md mx-auto">
  <BreakingCard
    title="Exclusive Interview With PM"
    image="/news/interview.jpg"
    layout="no-border"
  />
</div>
```

### Custom Styling

```tsx
<OpinionCard
  title="The Economics of Change"
  author={authorData}
  className="shadow-xl hover:shadow-2xl"
/>
```

## Social Media Export

These cards are designed for:
- **Instagram**: 1080x1080px (square)
- **Facebook**: 1200x1200px (square)
- **Twitter**: 1200x1200px (square)
- **LinkedIn**: 1200x1200px (square)

To export as image, use a library like `html-to-image`:

```tsx
import { toPng } from 'html-to-image';

const exportCard = async (ref: HTMLElement) => {
  const dataUrl = await toPng(ref, {
    width: 1200,
    height: 1200,
    pixelRatio: 2,
  });
  // Download or share dataUrl
};
```

## Files

- `/components/social/BreakingCard.tsx` - Breaking news card component
- `/components/social/OpinionCard.tsx` - Opinion/editorial card component
- `/components/social/QuoteCard.tsx` - Quote/interview card component
- `/components/social/index.ts` - Barrel exports
- `/components/social/README.md` - This documentation

## Related Components

- `/components/brand/Logo.tsx` - Logo component (used in all cards)
- `/components/typography/Heading.tsx` - Typography components
- `/components/typography/Text.tsx` - Text components
