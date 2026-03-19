# MobileBottomNav - Quick Start Guide

Get the mobile bottom navigation up and running in 5 minutes.

## Step 1: Import the Component

```tsx
import MobileBottomNav from '@/components/navigation/MobileBottomNav';
```

## Step 2: Add to Your Layout

### Option A: Simple (No Callbacks)

```tsx
// app/[locale]/layout.tsx
export default function Layout({ children, params }) {
  return (
    <div className="min-h-screen">
      {children}
      <MobileBottomNav locale={params.locale} />
    </div>
  );
}
```

### Option B: With Full Functionality

```tsx
// app/[locale]/_components/MobileNavWrapper.tsx
'use client';

import { useState } from 'react';
import MobileBottomNav from '@/components/navigation/MobileBottomNav';

export default function MobileNavWrapper({ locale }) {
  const [isSearchOpen, setIsSearchOpen] = useState(false);
  const [isCategoriesOpen, setIsCategoriesOpen] = useState(false);
  const [isMenuOpen, setIsMenuOpen] = useState(false);

  return (
    <>
      <MobileBottomNav
        locale={locale}
        onSearchClick={() => setIsSearchOpen(true)}
        onCategoriesClick={() => setIsCategoriesOpen(true)}
        onMenuClick={() => setIsMenuOpen(true)}
      />

      {/* Add your modals/sheets here */}
      {isSearchOpen && (
        <SearchModal onClose={() => setIsSearchOpen(false)} />
      )}
    </>
  );
}
```

## Step 3: Test It

```bash
# Start the dev server
pnpm dev

# Open in browser
http://localhost:3005/ro

# Switch to mobile view
# Chrome DevTools → Toggle Device Toolbar (Cmd+Shift+M)
# Select iPhone or Android device
```

## What You Should See

```
┌─────────────────────────────────────────┐
│                                         │
│         [Your Page Content]             │
│                                         │
├─────────────────────────────────────────┤
│ [🏠]  [📈]  [🔍]  [▦]  [☰]             │ ← Navigation
│ Acasă Trending Caută Categorii Meniu   │
└─────────────────────────────────────────┘
```

## Behavior Checklist

- ✅ Navigation appears at bottom on mobile
- ✅ Navigation hidden on desktop (>= 768px)
- ✅ Home link navigates to homepage
- ✅ Trending link navigates to trending page
- ✅ Current page is highlighted in tomato color
- ✅ Navigation hides when scrolling down
- ✅ Navigation shows when scrolling up
- ✅ Smooth animations

## Common Customizations

### Change Active Color

```tsx
// In MobileBottomNav.tsx (line ~90)
// Replace: text-brand-tomato-500
// With: text-brand-oxford-900 (or any other brand color)
```

### Add New Item

```tsx
// In MobileBottomNav.tsx, add to navItems array:
{
  id: 'saved',
  icon: Bookmark,
  label: locale === 'ro' ? 'Salvate' : 'Saved',
  href: `/${locale}/saved`,
}
```

### Remove Item

```tsx
// In MobileBottomNav.tsx, remove from navItems array
// Example: Remove trending
const navItems: NavItem[] = [
  { id: 'home', ... },
  // { id: 'trending', ... },  ← Comment out or delete
  { id: 'search', ... },
  ...
];
```

## Troubleshooting

### Not Appearing?
Check viewport width < 768px in DevTools.

### No Scroll Hide/Show?
Ensure page content is taller than viewport.

### Callbacks Not Working?
Verify you're passing function props correctly.

### Safe Area Issues on iPhone?
Add to your HTML head:
```html
<meta name="viewport" content="viewport-fit=cover">
```

## Next Steps

1. Read `MobileBottomNav.md` for complete documentation
2. Check `MOBILE_BOTTOM_NAV_VISUAL_GUIDE.md` for design specs
3. Review `MobileBottomNavExample.tsx` for advanced patterns
4. Test on real devices (iOS + Android)

## Need Help?

- 📖 Full Docs: `MobileBottomNav.md`
- 🎨 Design Guide: `MOBILE_BOTTOM_NAV_VISUAL_GUIDE.md`
- 💻 Examples: `MobileBottomNavExample.tsx`
- 📋 Overview: `README_MOBILE_BOTTOM_NAV.md`

---

**That's it! You're done.** 🎉

The component is production-ready and follows all best practices for performance, accessibility, and premium design.
