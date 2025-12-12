# Logo Implementation Plan

**Date:** 2025-12-11
**Objective:** Update the public site branding to use the `deschide_logo.svg` icon alongside the "DESCHIDE" text, ensuring compliance with the Brandbook.

## Overview
The current text-only logo will be updated to a composite logo: **Icon (SVG) + Text**. The design must align with the "League Spartan" typography and existing color system (Oxford Blue, Tomato, White).

## Technical Changes

### 1. Asset Management
-   **Source:** `deschide_logo.svg` (Root)
-   **Target:** `apps/frontend/public/assets/brand/deschide_logo.svg`
-   The SVG paths will be extracted for inline usage in React to support `currentColor`.

### 2. Component Update (`Logo.tsx`)
-   **Structure:** Convert to a `flex` container (`flex items-center gap-x-2`).
-   **Icon:**
    -   Inline SVG using paths from `deschide_logo.svg`.
    -   `fill="currentColor"` to inherit text color.
    -   Height: ~40px (match standard text height) or scaled via props.
-   **Text:**
    -   Retain "DESCHIDE" text.
    -   Font: `font-heading` (League Spartan).
    -   Weight: `font-bold`.
    -   Tracking: `tracking-tight`.

### 3. Verification
-   **Visual Check:** Ensure alignment and color inheritance in Header (White), Footer (White), and Mobile Menu (Blue).
-   **Responsiveness:** Ensure icon scales correctly with the `size` prop.
