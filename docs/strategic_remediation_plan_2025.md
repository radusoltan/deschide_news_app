# Strategic Remediation Plan: "Premium Media" Upgrade

## Executive Summary
This remediation plan addresses the key findings from the **Design & UX Audit 2025**. It transforms the Deschide News App from a functional "template" layout into a **distinctive, authoritative media platform**.
Our strategy focuses on three pillars:
1.  **Visual Authority**: Introducing a "Hero" hierarchy and authoritative typography.
2.  **Tactile Quality**: Enhancing micro-interactions and mobile touch-feel.
3.  **Brand Immersion**: Deepening the use of the Oxford/Tomato palette.

## User Review Required
> [!IMPORTANT]
> **Strategic Shift**: We are moving away from a flat grid to a **Curated Layout**. This requires a new `HeroArticle` component for the homepage lead story.
> **Typography Overhaul**: We will import and apply **Serif fonts** (Merriweather/Playfair) for article content to establish editorial credibility.

## Strategic Remediation Roadmap
> [!IMPORTANT]
> **Typography Change**: I am proposing adding a Serif font (e.g., `Merriweather` or `Playfair Display`) via Google Fonts for article body text. This fundamentally changes the reading experience from "Tech" to "Editorial".
> **Layout Change**: The homepage grid will be altered to feature one large "Hero" item at the top.

## Proposed Changes

### Frontend
#### [MODIFY] [tailwind.config.ts](file:///var/www/deschide_news_app/apps/frontend/tailwind.config.ts)
- Add `font-serif` family (Merriweather/Playfair).
- Adjust `colors` if necessary to add a darker body text shade (`brand-ink` or similar).

#### [MODIFY] [layout.tsx](file:///var/www/deschide_news_app/apps/frontend/app/[locale]/layout.tsx)
- Import the new Serif font from `next/font/google`.

#### [NEW] [HeroArticle.tsx](file:///var/www/deschide_news_app/apps/frontend/components/HeroArticle.tsx)
- Create a new component for the primary featured article.
- Features: Full width or 2/3 width, larger typography, gradient overlay text-on-photo style.

#### [MODIFY] [page.tsx](file:///var/www/deschide_news_app/apps/frontend/app/[locale]/page.tsx)
- Update the homepage layout to use `HeroArticle` for the first item and `ArticleCard` for the rest in a grid.

#### [MODIFY] [globals.css](file:///var/www/deschide_news_app/apps/frontend/app/globals.css)
- Refine `.article-content` styles to use the new Serif font and increased line-height.
- Add utility classes for mobile touch targets (min-height 44px).

### Mobile Polish (New)
#### [MODIFY] [Header.tsx](file:///var/www/deschide_news_app/apps/frontend/components/layout/Header.tsx)
- Implement "sticky minimal" behavior on scroll for mobile.
- Ensure hamburger menu animation is smooth (backdrop-filter).

## Verification Plan

### Automated Tests
- None (Visual changes are hard to unit test).

### Manual Verification
1.  **Homepage Hero**:
    - Visit `http://localhost:3005`.
    - Verify the first article is displayed as a large "Hero" component with distinct styling.
    - Verify responsiveness (stacking correctly on mobile).
2.  **Typography Check**:
    - Visit an Article page.
    - Inspect the body text. Confirm it is rendering with the new Serif font.
    - Verify readability (contrast and line-height).
3.  **Visual Comparison**:
    - Capture new screenshots effectively replacing the ones taken during the audit usage.
