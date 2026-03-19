# Video Shows Slider - Visual Design Guide

## Component Hierarchy

```
┌─────────────────────────────────────────────────────────────────┐
│                     VIDEO SHOWS SLIDER SECTION                  │
│                                                                 │
│  ┌────────────────────────────────────────────────────────┐   │
│  │  HEADER                                                 │   │
│  │  ┌──────┐  ┌─────────────────────┐    ┌──────────┐    │   │
│  │  │ ICON │  │ Title: 48-60px      │    │ VIEW ALL │    │   │
│  │  │      │  │ Subtitle: 14-16px   │    │  BUTTON  │    │   │
│  │  └──────┘  └─────────────────────┘    └──────────┘    │   │
│  └────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ┌────────────────────────────────────────────────────────┐   │
│  │  FILTER BUTTONS (Optional)                              │   │
│  │  [Toate] [Emisiunea 1] [Emisiunea 2] [Emisiunea 3]     │   │
│  └────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ┌────────────────────────────────────────────────────────┐   │
│  │                    VIDEO CAROUSEL                       │   │
│  │                                                          │   │
│  │  ◄   ┌────┐  ┌────┐  ┌────┐  ┌────┐  ┌────┐     ►    │   │
│  │      │ V1 │  │ V2 │  │ V3 │  │ V4 │  │ V5 │          │   │
│  │      └────┘  └────┘  └────┘  └────┘  └────┘          │   │
│  │                                                          │   │
│  └────────────────────────────────────────────────────────┘   │
│                                                                 │
│                         ┌────┐  ┌────┐                         │
│                         │ ◄  │  │  ► │                         │
│                         └────┘  └────┘                         │
│                      Mobile Navigation                          │
└─────────────────────────────────────────────────────────────────┘
```

## Video Card Anatomy

```
┌───────────────────────────────────────────┐
│ ┌──────────┐                  ┌────────┐ │ ← Badge layer
│ │ NEW ●    │                  │ SHOW   │ │
│ └──────────┘                  └────────┘ │
│                                           │
│         ┌─────────────────┐              │
│         │                 │              │ ← Video thumbnail
│         │    ▶ PLAY       │              │   with overlays
│         │                 │              │
│         └─────────────────┘              │
│                                 ┌──────┐ │ ← Duration badge
│                                 │ 5:30 │ │
├───────────────────────────────────────────┤
│                                           │
│  Video Title Here                         │ ← Title (16-24px)
│  Maximum Two Lines                        │
│                                           │
│  👁 125K    ❤ 2.1K         2 days ago   │ ← Meta info
│                                           │
└───────────────────────────────────────────┘
```

## Color Palette

### Primary Colors
```
Background Dark:   #0F172A (slate-950)
Background Mid:    #1E293B (slate-900)
Brand Tomato:      #F05E45 (brand-tomato-500)
Brand Tomato Dark: #E93C28 (brand-tomato-600)
```

### Accent Colors
```
Text Primary:      #FFFFFF (white)
Text Secondary:    #94A3B8 (slate-400)
Text Tertiary:     #64748B (slate-500)
Border Default:    rgba(51, 65, 85, 0.3) (slate-700/30)
Border Hover:      rgba(240, 94, 69, 0.5) (brand-tomato-500/50)
```

### Gradient Examples
```css
/* Card Background */
background: linear-gradient(135deg,
  rgba(15, 23, 42, 0.95) 0%,
  rgba(2, 6, 23, 0.95) 100%
);

/* Play Button */
background: linear-gradient(135deg,
  #F05E45 0%,
  #E93C28 100%
);

/* Ambient Orb (Top Left) */
background: radial-gradient(circle,
  rgba(240, 94, 69, 0.1) 0%,
  transparent 70%
);
```

## Typography Scale

```
Section Title:    48px / 56px / 60px (responsive)
                  font-weight: 800 (extrabold)
                  line-height: 1.2

Card Title:       16px / 18px / 20px (base)
                  20px / 24px (featured)
                  font-weight: 700 (bold)
                  line-height: 1.4

Subtitle:         14px / 16px
                  font-weight: 500 (medium)

Meta Info:        12px
                  font-weight: 500 (medium)

Badge Text:       11px
                  font-weight: 700 (bold)
                  text-transform: uppercase
                  letter-spacing: 0.1em
```

## Spacing System

```
Section Padding:
  Mobile:    py-16 (64px)
  Tablet:    py-20 (80px)
  Desktop:   py-24 (96px)

Card Padding:
  Mobile:    p-5 (20px)
  Desktop:   p-6 (24px)

Card Spacing:
  Mobile:    16px gap
  Tablet:    20px gap
  Desktop:   24-28px gap

Component Gaps:
  Header elements:    gap-5 (20px)
  Meta elements:      gap-4 (16px)
  Filter buttons:     gap-2.5 (10px)
```

## Shadow System

```css
/* Card Default */
box-shadow: none;

/* Card Hover */
box-shadow: 0 20px 60px -15px rgba(240, 94, 69, 0.3);

/* Play Button */
box-shadow: 0 0 40px rgba(240, 94, 69, 0.4);

/* Play Button Hover */
box-shadow: 0 0 60px rgba(240, 94, 69, 0.6);

/* Navigation Button */
box-shadow: 0 0 0 1px rgba(51, 65, 85, 0.5),
            0 20px 25px -5px rgba(0, 0, 0, 0.3);

/* Modal Container */
box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.8);
```

## Animation Timing

```
Fast Interactions:   200-300ms
Standard:            500ms
Smooth:              700ms
Slow/Dramatic:       1000ms

Easing Functions:
  - ease-out:  Most hover states
  - ease-in-out: Modal entrance
  - linear:    Ping animations
```

## Interaction States

### Video Card States

#### Default
```
- Border: slate-700/30
- Transform: none
- Shadow: none
- Play button: scale(1)
```

#### Hover
```
- Border: brand-tomato-500/50
- Transform: translateY(-8px)
- Shadow: 0 20px 60px -15px rgba(240,94,69,0.3)
- Play button: scale(1.25)
- Duration: 700ms ease-out
```

#### Active/Click
```
- Scale: 0.98 (brief feedback)
- Duration: 150ms
```

### Play Button States

#### Default
```
- Size: 80px × 80px (mobile) / 96px × 96px (desktop)
- Background: Gradient tomato
- Shadow: 0 0 40px rgba(240,94,69,0.4)
- Rings: Hidden (opacity: 0)
```

#### Hover
```
- Scale: 1.25
- Shadow: 0 0 60px rgba(240,94,69,0.6)
- Outer ring: Visible, scale(1.5), opacity: 100%
- Ping ring: Visible, animating
- Inner glow: Visible, opacity: 100%
- Duration: 500ms ease-out
```

### Navigation Button States

#### Default
```
- Size: 56px × 56px (desktop) / 48px × 48px (mobile)
- Background: Gradient slate
- Border: slate-700/50
- Icon color: white/80
```

#### Hover
```
- Background: Gradient brand-tomato
- Border: brand-tomato-500/50
- Icon color: white
- Scale: 1.1
- Icon translate: ±0.5px
- Shadow: brand-tomato-500/30
```

#### Disabled
```
- Opacity: 0.3
- Cursor: not-allowed
- No hover effects
```

## Modal Design

### Layout
```
┌─────────────────────────────────────────┐
│  ╔═══════════════════════════════════╗  │
│  ║                                   ║  │
│  ║      YouTube Video Player         ║  │
│  ║          (16:9 ratio)             ║  │
│  ║                                   ║  │
│  ╚═══════════════════════════════════╝  │
│                                         │
│         Video Title Here                │
│                                         │
│  [Show Name]  👁 125K views  ⏱ 5:30   │
│                                         │
└─────────────────────────────────────────┘
                    ┌───┐
                    │ × │ Close
                    └───┘
```

### Effects
```
Backdrop:
  - Color: black/98
  - Blur: backdrop-blur-2xl
  - Ambient glow: 600px red orb, 120px blur

Video Container:
  - Border-radius: 24px
  - Border: white/10
  - Shadow: 0 40px 100px -20px black/80

Entrance Animation:
  - Scale: 0.95 → 1.0
  - TranslateY: 8px → 0
  - Opacity: 0 → 1
  - Duration: 700ms ease-out
```

## Responsive Breakpoints

```
┌────────────┬─────────┬────────┬─────────┬─────────┐
│ Device     │ Width   │ Slides │ Spacing │ Padding │
├────────────┼─────────┼────────┼─────────┼─────────┤
│ Mobile     │ < 480px │ 1.0    │ 16px    │ 16px    │
│ Mobile L   │ 480px+  │ 1.3    │ 16px    │ 16px    │
│ Tablet S   │ 640px+  │ 2.0    │ 20px    │ 24px    │
│ Tablet     │ 768px+  │ 2.5    │ 24px    │ 24px    │
│ Desktop S  │ 1024px+ │ 3.0    │ 24px    │ 32px    │
│ Desktop    │ 1280px+ │ 4.0    │ 28px    │ 32px    │
└────────────┴─────────┴────────┴─────────┴─────────┘
```

## Background Effects

### Ambient Orbs
```
Top-Left Orb:
  - Size: 500px × 500px
  - Color: brand-tomato-500/10
  - Blur: 120px
  - Opacity: 0.4

Bottom-Right Orb:
  - Size: 600px × 600px
  - Color: brand-oxford-600/10
  - Blur: 140px
  - Opacity: 0.3
```

### Texture Overlays
```
Film Grain:
  - Pattern: SVG fractal noise
  - Frequency: 0.9
  - Octaves: 4
  - Opacity: 0.025
  - Blend: overlay

Grid:
  - Size: 60px × 60px
  - Color: brand-tomato-500/10
  - Opacity: 0.02
```

### Card Overlays
```
1. Gradient Bottom (strongest)
   - from-slate-950/90 at bottom
   - via-slate-950/50 at 40%
   - to-transparent at top
   - Opacity: 0.9 → 0.7 on hover

2. Radial Gradient (depth)
   - from-transparent
   - via-transparent
   - to-slate-950/40
   - Opacity: 0.6

3. Vignette (cinematic)
   - inset 0 0 100px rgba(0,0,0,0.5)

4. Film Grain (texture)
   - Opacity: 0.025
   - Blend: overlay
```

## Accessibility Requirements

### Color Contrast
```
Text on Dark BG:     White on slate-950 = 18.5:1 ✓
Tomato on Dark:      #F05E45 on slate-950 = 5.2:1 ✓
Gray Text on Dark:   slate-400 on slate-950 = 8.4:1 ✓
```

### Touch Targets
```
Minimum Size: 48px × 48px ✓
  - Mobile navigation: 48px × 48px
  - Desktop navigation: 56px × 56px
  - Play button: 80px+ × 80px+
```

### Focus States
```
All interactive elements should have:
  - Visible focus ring
  - Proper tab order
  - ARIA labels
  - Keyboard support (Enter, Space, ESC)
```

## Performance Guidelines

### Image Loading
```
- Use Next.js Image component
- Set appropriate sizes attribute
- Use priority for featured videos
- Lazy load remaining videos
```

### Animation Performance
```
- Use transform and opacity only
- Avoid animating width/height
- Use will-change sparingly
- Target 60fps (16.67ms per frame)
```

### Code Splitting
```
- Dynamic import for modal
- Lazy load video player
- Defer non-critical JavaScript
```

## Design Tokens

### Border Radius
```
xs:   4px (0.25rem)
sm:   6px (0.375rem)
md:   8px (0.5rem)
lg:   12px (0.75rem)
xl:   16px (1rem)
2xl:  24px (1.5rem)
full: 9999px
```

### Z-Index Scale
```
base:     0
raised:   10
dropdown: 20
sticky:   30
modal:    100
toast:    110
```

### Opacity Scale
```
0:    0
5:    0.05
10:   0.1
20:   0.2
30:   0.3
40:   0.4
50:   0.5
60:   0.6
70:   0.7
80:   0.8
90:   0.9
95:   0.95
100:  1
```

## Icon Specifications

### Play Icon
```
Size: 32-40px
Stroke: none (filled)
Color: white
Offset: 4px left (ml-1) for visual centering
```

### Eye Icon (Views)
```
Size: 16px
Stroke: 2px
Type: Outline
Color: slate-400 → slate-300 on hover
```

### Heart Icon (Likes)
```
Size: 16px
Stroke: none (filled)
Color: slate-400 → red-400 on hover
```

### Arrow Icons (Navigation)
```
Size: 24px
Stroke: 2.5px
Type: Outline
Color: white/80 → white on hover
Animation: ±2px translate on hover
```

### Close Icon (Modal)
```
Size: 24px
Stroke: 2px
Type: Outline
Color: white/70 → white on hover
Animation: 90deg rotate on hover
```

## Best Practices

### Do's
✓ Use brand colors consistently
✓ Maintain visual hierarchy
✓ Ensure touch targets are 48px+
✓ Test on multiple devices
✓ Validate color contrast
✓ Add loading states
✓ Handle empty states
✓ Provide keyboard navigation
✓ Use semantic HTML
✓ Optimize images

### Don'ts
✗ Don't use generic shadows
✗ Don't skip hover states
✗ Don't ignore mobile layout
✗ Don't animate layout properties
✗ Don't hardcode colors
✗ Don't forget focus states
✗ Don't neglect accessibility
✗ Don't use tiny touch targets
✗ Don't skip error handling
✗ Don't forget loading states

## Quality Checklist

### Visual Quality
- [ ] Premium shadows and glows
- [ ] Smooth animations (60fps)
- [ ] Proper spacing and alignment
- [ ] Consistent border radius
- [ ] High-quality hover states
- [ ] Brand color integration

### User Experience
- [ ] Clear visual feedback
- [ ] Intuitive navigation
- [ ] Fast load times
- [ ] Smooth interactions
- [ ] Responsive design
- [ ] Error handling

### Technical Quality
- [ ] Clean, readable code
- [ ] Proper TypeScript types
- [ ] No console errors
- [ ] Optimized performance
- [ ] Proper cleanup (useEffect)
- [ ] Accessible markup

### Cross-browser
- [ ] Chrome/Edge tested
- [ ] Firefox tested
- [ ] Safari tested
- [ ] Mobile browsers tested
- [ ] Tablet tested
- [ ] Desktop tested
