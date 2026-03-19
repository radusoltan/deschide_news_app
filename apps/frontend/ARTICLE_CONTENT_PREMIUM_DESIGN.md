# Article Content Premium Design System

## Overview

The article content display system has been enhanced with premium typography, sophisticated styling, and professional micro-interactions to create a best-in-class reading experience. This document outlines all available features and how to use them.

## Philosophy: "Content is King"

Every design decision prioritizes readability, professionalism, and visual hierarchy:

- **Premium Typography**: 19-20px body text with 1.75-1.8 line-height for optimal readability
- **Generous Spacing**: Ample whitespace between elements to reduce cognitive load
- **Visual Hierarchy**: Clear distinction between headings, body text, and supporting elements
- **Brand Integration**: Subtle use of Deschide brand colors (Tomato #F05E45, Oxford Blue #112240)
- **Responsive Excellence**: Beautiful on all devices from mobile to desktop

---

## Typography Scale

### Headings

```html
<h2>Main Section Heading</h2>
<!-- Desktop: 40px / Mobile: 32px, League Spartan Bold, uppercase -->

<h3>Subsection Heading</h3>
<!-- Desktop: 32px / Mobile: 24px, League Spartan Bold, uppercase -->

<h4>Minor Heading</h4>
<!-- Desktop: 24px / Mobile: 20px, League Spartan Bold, uppercase -->
```

### Body Text

```html
<p>Regular paragraph text with optimal readability...</p>
<!-- Font: 19-20px, Line-height: 1.75-1.8, Merriweather (serif) -->
```

### Special Paragraphs

#### Drop Cap (Automatic)
The **first paragraph** of every article automatically gets a drop cap on the first letter:

```html
<p>Moldova se află într-un moment crucial...</p>
<!-- First letter "M" will be styled as a large drop cap -->
```

**Result**: Large 4.5em tomato-colored uppercase letter floated left.

#### Intro Paragraph (Manual Class)
For introductory paragraphs that need emphasis:

```html
<p class="intro">Acest articol analizează impactul...</p>
<!-- Larger font size (22px), enhanced readability -->
```

---

## Lists

### Unordered Lists
Custom bullet points using brand tomato color:

```html
<ul>
  <li>Transparența procesului electoral</li>
  <li>Participarea activă a observatorilor</li>
  <li>Educarea electoratului</li>
</ul>
```

**Features**:
- Circular tomato-colored bullets (6px)
- Generous spacing (0.75rem between items)
- 19-20px text size matching body
- Proper indentation with relative positioning

### Ordered Lists
Custom numbered markers in tomato color:

```html
<ol>
  <li>Etapa de pregătire</li>
  <li>Implementarea reformelor</li>
  <li>Monitorizarea rezultatelor</li>
</ol>
```

**Features**:
- Bold tomato-colored numbers
- Auto-incrementing counter
- Same spacing and sizing as unordered lists

### Nested Lists
Full support for nested lists with smaller bullets:

```html
<ul>
  <li>Prim nivel
    <ul>
      <li>Nivel secundar (bullet mai mic)</li>
    </ul>
  </li>
</ul>
```

---

## Blockquotes & Citations

### Standard Blockquote

```html
<blockquote>
  Democrația nu este un proces finit, ci o călătorie continuă către perfecționare.
</blockquote>
```

**Features**:
- 4px left border in tomato color
- Gradient background (gray-50 to white)
- Larger font size (22-24px)
- Italic serif font
- Rounded corners with subtle shadow
- Generous padding

### Blockquote with Citation

```html
<blockquote>
  Transparența este cheia unei democrații funcționale.
  <cite>Dr. Ion Popescu, Expert în Guvernanță</cite>
</blockquote>
```

**Features**:
- Citation appears right-aligned below quote
- Smaller font, non-italic
- Oxford Blue color
- Automatic "—" prefix

### Pull Quote (Special Emphasis)

For quotes that deserve extra attention:

```html
<blockquote class="pullquote">
  Această reformă va schimba fundamental peisajul politic moldovenesc.
</blockquote>
```

**Features**:
- Centered, larger text (28-32px)
- Decorative giant quotation mark
- Tomato gradient background
- Maximum width for readability
- Bold semi-italic styling

---

## Images & Figures

### Standard Article Image

```html
<figure>
  <img src="/path/to/image.jpg" alt="Description" class="article-image" />
  <figcaption>Sediul Comisiei Electorale Centrale din Chișinău</figcaption>
</figure>
```

**Features**:
- Rounded corners (1rem)
- Premium shadow (Oxford Blue tint)
- Hover effect (lift + enhanced shadow)
- Responsive sizing
- Centered caption with italic styling

### Wide Image (Breakout Effect)

For images that need more visual impact:

```html
<figure>
  <img src="/path/to/image.jpg" alt="Description" class="article-image wide" />
  <figcaption>Panoramic view of Parliament</figcaption>
</figure>
```

**Features**:
- Breaks out of content container
- Mobile: +4rem width (-2rem margins)
- Desktop: +8rem width (-4rem margins)
- Same premium styling as standard

### Full-Width Image (Edge-to-Edge)

For hero-style images:

```html
<img src="/path/to/hero.jpg" alt="Description" class="article-image full" />
```

**Features**:
- Full viewport width
- No rounded corners
- Dramatic visual impact
- Best for landscapes or architectural shots

---

## Video Embeds

### Standard YouTube/Vimeo Embed

```html
<div class="video-embed">
  <iframe
    src="https://www.youtube.com/embed/VIDEO_ID"
    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
    allowfullscreen
  ></iframe>
</div>
```

**Features**:
- Responsive 16:9 aspect ratio
- Rounded corners with shadow
- Dark Oxford Blue background
- Maintains aspect ratio on all devices
- 2.5rem top/bottom margins

### Wide Video Embed

For cinematic presentations:

```html
<div class="video-embed wide">
  <iframe src="https://www.youtube.com/embed/VIDEO_ID" allowfullscreen></iframe>
</div>
```

**Features**:
- Same breakout effect as wide images
- Perfect for documentary-style content

---

## Tables

### Standard Data Table

```html
<table>
  <thead>
    <tr>
      <th>Etapa</th>
      <th>Perioadă</th>
      <th>Responsabil</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>Pregătire</td>
      <td>Ian-Mar 2024</td>
      <td>CEC</td>
    </tr>
    <tr>
      <td>Implementare</td>
      <td>Apr-Iun 2024</td>
      <td>Parlament</td>
    </tr>
  </tbody>
</table>
```

**Features**:
- Gradient header background
- Bold uppercase headers
- Zebra striping (even rows gray-50)
- Hover effect on rows
- Rounded corners with shadow
- Responsive horizontal scroll on mobile
- Sticky header on desktop
- Optimized padding and typography

---

## Special Content Boxes

### Info Box (Note)

```html
<div class="note">
  Important information that readers should know about this topic.
</div>
```

**Features**:
- Oxford Blue left border
- Gradient blue background
- Information emoji (ℹ️) prefix
- Slightly smaller font (0.95rem)

### Warning Box

```html
<div class="warning">
  Critical information that requires attention!
</div>
```

**Features**:
- Red left border
- Gradient red background
- Warning emoji (⚠️) prefix

### Success Box

```html
<div class="success">
  Positive outcome or successful completion notice.
</div>
```

**Features**:
- Green left border
- Gradient green background
- Checkmark (✓) prefix

---

## Text Utilities

### Highlighted Text

```html
<span class="highlight">important text to emphasize</span>
```

**Features**:
- Mindaro (yellow-green) gradient background
- Subtle padding and rounded corners
- Medium font weight

### Links

Standard links automatically get brand styling:

```html
<a href="/related-article">Read more about electoral reforms</a>
```

**Features**:
- Tomato color (#F05E45)
- Underline with tomato decoration
- 3px underline offset
- Hover: darker tomato + thicker underline
- Smooth transitions

---

## Advanced Features

### Reading Progress Indicator

For long-form articles, add a reading progress bar:

```html
<div class="article-reading-progress" id="reading-progress"></div>

<script>
window.addEventListener('scroll', () => {
  const winScroll = document.documentElement.scrollTop;
  const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
  const scrolled = (winScroll / height);
  document.getElementById('reading-progress').style.transform = `scaleX(${scrolled})`;
});
</script>
```

**Features**:
- Fixed top position
- Tomato-to-red gradient
- Smooth animation
- 4px height
- Highest z-index (9999)

---

## Responsive Behavior

### Mobile Optimizations (< 768px)

- Tables: Smaller font (0.875rem), reduced padding
- Wide images/videos: +4rem breakout
- Drop cap: Still prominent but proportionally sized
- All spacing scaled appropriately

### Desktop Enhancements (>= 1024px)

- Sticky table headers
- Larger wide breakouts (+8rem)
- Enhanced hover effects
- Optimal line length (max-width constraints)

---

## Print Optimization

Content is automatically optimized for printing:

- Font size: 12pt
- Color: Black text
- Links: Underlined with URL printed after
- Videos: Hidden (replaced with message)
- Images: Page-break aware
- Headings: Prevent orphan lines

---

## Accessibility

All enhancements maintain WCAG 2.1 AA compliance:

- **Color Contrast**: All text meets 4.5:1 minimum
- **Font Sizes**: Minimum 17px (mobile) / 19px (desktop)
- **Focus States**: Visible focus indicators on interactive elements
- **Reduced Motion**: Respects `prefers-reduced-motion` media query
- **Semantic HTML**: Proper heading hierarchy, figure/figcaption usage
- **Alt Text**: All images require descriptive alt attributes

---

## Performance Optimizations

- **Will-change**: Applied only to animating elements
- **Transform**: Used for animations (GPU-accelerated)
- **Lazy Loading**: Recommended for images (add `loading="lazy"`)
- **Font Subsetting**: Only necessary characters loaded
- **CSS Containment**: Where applicable for render optimization

---

## Examples from Test Article

Visit the test article to see all features in action:

**URL**: `http://localhost:3005/ro/politika/reformele-electorale-din-moldova-drumul-spre-o-democratie-consolidata`

**Features demonstrated**:
1. Drop cap on first paragraph
2. Multiple heading levels (H2, H3)
3. Unordered and ordered lists
4. Blockquotes with citations
5. Data table with timeline
6. Inline images with captions
7. YouTube video embed
8. All spacing and typography in action

---

## Best Practices for Content Creators

### DO

✓ Use semantic HTML (proper heading hierarchy)
✓ Provide descriptive alt text for all images
✓ Use figcaption for image context
✓ Add citations to blockquotes when quoting sources
✓ Use tables for tabular data (not layout)
✓ Keep paragraphs to 3-5 sentences for readability
✓ Use lists to break up complex information

### DON'T

✗ Skip heading levels (H2 → H4 without H3)
✗ Use excessive emphasis (bold/italic)
✗ Create overly long paragraphs (>7 sentences)
✗ Forget alt text on images
✗ Use tables for layout purposes
✗ Overuse special content boxes (note/warning/success)
✗ Use inline styles (breaks design system)

---

## Technical Implementation

### Component Location
`/var/www/deschide_news_app/apps/frontend/components/article/ArticleBody.tsx`

### Global Styles
`/var/www/deschide_news_app/apps/frontend/app/globals.css`
- Lines 774-1145: Article content premium enhancements

### Typography Variables
Defined in layout.tsx via Google Fonts:
- **Heading**: League Spartan (700, 800 weights, uppercase)
- **Body**: Merriweather (400, 700 weights, serif)

### Brand Colors (RGB)
- **Oxford Blue**: `rgb(17, 34, 64)` - Primary dark
- **Tomato**: `rgb(240, 94, 69)` - Primary accent
- **Red**: `rgb(233, 38, 40)` - Secondary accent
- **Mindaro**: `rgb(212, 251, 140)` - Highlight (max 10% usage)

---

## Browser Support

Tested and optimized for:
- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Mobile Safari (iOS 14+)
- Chrome Mobile (Android 10+)

---

## Future Enhancements

Planned improvements:
- [ ] Interactive image zoom/lightbox
- [ ] Social media embed support (Twitter, Instagram)
- [ ] Audio player styling
- [ ] Table of contents auto-generation for H2/H3
- [ ] Reading time estimation
- [ ] Font size adjustment controls
- [ ] Dark mode support
- [ ] Related articles sidebar

---

## Support & Questions

For questions about article content styling:
- Technical: Reference this document
- Design decisions: See `/context/design_principles_and_features.md`
- Brand guidelines: See Deschide Brandbook

---

**Last Updated**: 2025-12-13
**Version**: 1.0.0
**Maintained by**: Frontend Team
