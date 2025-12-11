# Visual Verification Checklist

**Date**: 2025-12-11
**Frontend URL**: http://localhost:3005

## Quick Visual Inspection Guide

Use this checklist to visually verify the design alignment implementation.

## Homepage (http://localhost:3005/ro)

### Header Section
- [ ] **Background Color**: Oxford Blue (#112240) - dark navy blue
- [ ] **Logo**: White variant visible
- [ ] **Menu Links**:
  - White text in uppercase
  - Hover effect shows Mindaro (#D4FB8C) - light green tint
  - Font: League Spartan Bold

### Hero Section (Important Articles)
- [ ] **Main Article Title**:
  - League Spartan Bold
  - ALL UPPERCASE
  - White text with visible text shadow
  - Hover shows Mindaro tint
- [ ] **Category Badge**:
  - Tomato background (#F05E45) - coral/orange-red
  - White text
  - UPPERCASE
  - Rounded corners with shadow
- [ ] **Text on Image**: Clearly readable with shadow effect
- [ ] **Grid Cards** (4 smaller articles):
  - Same badge and title styling
  - Text shadows visible

### Trending Section (if present)
- [ ] **Section Title**: "TRENDING NOW" in League Spartan uppercase
- [ ] **#1 Badge**: Tomato background with white text
- [ ] **Card Titles**: League Spartan uppercase
- [ ] **Hover Effect**: Slight lift animation + border color change to Tomato

## Category Page (http://localhost:3005/ro/politica)

### Category Hero Article
- [ ] **Title**: League Spartan uppercase with strong text shadow
- [ ] **Category Badge**: Tomato background, white text
- [ ] **Excerpt Text**: Poppins font with text shadow
- [ ] **Gradient Overlay**: Dark gradient at bottom for readability

### Category Navigation
- [ ] **Category Links**:
  - UPPERCASE text
  - Active category has Tomato background
  - Inactive categories have Oxford Blue text
  - Hover shows light gray background + Tomato text

### Article Cards
- [ ] **Category Badge**: Small Tomato badge above title
- [ ] **Title**: League Spartan uppercase
- [ ] **Hover State**: Title color changes to Tomato

## Typography Verification

### Headings (Inspect with DevTools)
```
font-family: League Spartan, system-ui, arial, sans-serif
font-weight: 700
text-transform: uppercase
```

### Body Text (Inspect with DevTools)
```
font-family: Poppins, system-ui, arial, sans-serif
font-weight: 400 or 500
```

## Color Palette Verification

### Primary Colors
- **Oxford Blue**: `#112240` or `rgb(17, 34, 64)` - Header, text
- **Tomato**: `#F05E45` or `rgb(240, 94, 69)` - Badges, accents
- **Mindaro**: `#D4FB8C` or `rgb(212, 251, 140)` - Hover effects only

### Text Shadows (on images)
Inspect element with text over image - should show multiple box-shadows:
```css
text-shadow:
  0 1px 3px rgba(0, 0, 0, 0.8),
  0 2px 8px rgba(0, 0, 0, 0.5),
  0 4px 16px rgba(17, 34, 64, 0.4);
```

## Micro-interactions

### Hover Effects to Test
1. **Card Hover**:
   - Slight upward movement (translateY)
   - Shadow increases
   - Border color may change to Tomato

2. **Link Hover**:
   - Text color changes to Tomato
   - Smooth transition (300ms)

3. **Image Hover**:
   - Subtle zoom effect (scale 1.05)
   - Smooth transition (500-700ms)

4. **Menu Hover**:
   - Background changes to slightly lighter Oxford Blue
   - Text tint changes to Mindaro

## Browser DevTools Inspection

### Check CSS Variables (Console)
```javascript
// Open browser console and run:
getComputedStyle(document.documentElement).getPropertyValue('--brand-oxford-900')
// Should return: 17 34 64

getComputedStyle(document.documentElement).getPropertyValue('--brand-tomato-500')
// Should return: 240 94 69

getComputedStyle(document.documentElement).getPropertyValue('--brand-mindaro-400')
// Should return: 212 251 140
```

### Check Font Loading
```javascript
// Open browser console and run:
document.fonts.check('700 16px "League Spartan"')
// Should return: true

document.fonts.check('400 16px "Poppins"')
// Should return: true
```

## Accessibility Check

### Text Contrast
- [ ] White text on Oxford Blue: High contrast ✓
- [ ] White text on Tomato: High contrast ✓
- [ ] Oxford Blue text on white: High contrast ✓
- [ ] NO Mindaro for long text (only accents) ✓
- [ ] NO Red CMYK on white background ✓

### Text Shadows
- [ ] Text over images is clearly readable
- [ ] Shadow provides sufficient contrast
- [ ] Shadow doesn't obscure text

## Mobile Responsiveness

Test at these breakpoints:
- **Mobile**: 375px width
- **Tablet**: 768px width
- **Desktop**: 1280px width

### Mobile Checks
- [ ] Header collapses to mobile menu
- [ ] Typography scales down appropriately
- [ ] Category badges remain visible
- [ ] Text shadows still readable
- [ ] Touch targets are adequate (min 44px)

## Screenshots Recommended

Take screenshots of:
1. Homepage hero section (desktop)
2. Category navigation (desktop)
3. Article card hover state
4. Mobile menu expanded
5. Category badge close-up
6. Text on image example

## Common Issues to Look For

### ❌ Issues to Report
- Generic blue colors anywhere (should be Oxford Blue)
- Generic red colors (should be Tomato)
- Lowercase headings (should be UPPERCASE)
- No text shadow on text over images
- No hover effects
- Wrong fonts (not League Spartan or Poppins)

### ✅ Expected Behavior
- All headings in League Spartan Bold UPPERCASE
- All body text in Poppins
- Consistent Tomato badges
- Mindaro appears only on hover
- Text shadows on all image overlays
- Smooth transitions and animations

## Browser Testing

Test in:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (if on Mac)
- [ ] Edge (latest)

## Performance Check

### Core Web Vitals (Chrome DevTools)
- **LCP** (Largest Contentful Paint): < 2.5s
- **INP** (Interaction to Next Paint): < 200ms
- **CLS** (Cumulative Layout Shift): < 0.1

### Font Loading
- [ ] No FOUT (Flash of Unstyled Text)
- [ ] No layout shift from font swap
- [ ] Fallback fonts load smoothly

---

## Quick Test URLs

### Romanian (default)
- Homepage: http://localhost:3005/ro
- Politica: http://localhost:3005/ro/politica
- Economie: http://localhost:3005/ro/economie

### English
- Homepage: http://localhost:3005/en
- Politics: http://localhost:3005/en/politics

### Russian
- Homepage: http://localhost:3005/ru

---

**Note**: This checklist should be completed by viewing the actual rendered pages in a browser. The implementation is code-complete, but visual verification ensures the design renders correctly across devices and browsers.
