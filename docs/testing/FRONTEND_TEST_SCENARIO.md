# General Application Manual Test Scenario (Frontend)

## Scope
This scenario covers the public-facing section of the Deschide News App (`apps/frontend`). It assumes the "Demo Data Plan" has been executed.

**URL**: `http://localhost:3005` (or staging URL)

## 1. Homepage & Navigation

| ID | Test Case | Steps | Expected Result |
|----|-----------|-------|-----------------|
| FE-01 | **Homepage Load** | Open root URL. | Page loads without errors. "Featured" section is visible. Articles list is populated. |
| FE-02 | **Language Switching** | 1. Observe current language (e.g., RO).<br>2. Click language switcher (EN).<br>3. Click language switcher (RU). | 1. Content is in RO.<br>2. UI and Content switch to English.<br>3. UI and Content switch to Russian. URL changes to `/en/...` then `/ru/...`. |
| FE-03 | **Main Menu Navigation** | Click on each top-level category (e.g., "Politics", "Social"). | Navigates to correct Category Page. URL matches category slug. |
| FE-04 | **Sticky Header** | Scroll down the homepage. | Header remains visible or behaves as designed (e.g., hides on scroll down, shows on scroll up). |
| FE-05 | **Footer Links** | Scroll to footer. Click "Contact", "Terms". | Navigates to respective static pages. |

## 2. Article Consumption

| ID | Test Case | Steps | Expected Result |
|----|-----------|-------|-----------------|
| FE-06 | **Open Article** | Click on any article title from Homepage. | Opens Article Detail page. Title, Lead, Content, Author, and Date are visible. |
| FE-07 | **Image Gallery** | Open article with multiple images. Click on an image. | Lightbox/Gallery opens. Can navigate between images. |
| FE-08 | **Related Articles** | Scroll to bottom of article. | "Related Articles" section is present and populated. Links work. |
| FE-09 | **Author Page** | Click on Author's name. | Opens Author Profile page showing list of their articles. |
| FE-10 | **Share Functionality** | Click Share button (Facebook/Twitter). | Opens social media share dialog with correct URL and Title. |

## 3. Search Functionality

| ID | Test Case | Steps | Expected Result |
|----|-----------|-------|-----------------|
| FE-11 | **Search Query** | 1. Click Search Icon.<br>2. Type "Romania" (or relevant keyword).<br>3. Press Enter. | Search Results page opens. List of relevant articles is displayed. |
| FE-12 | **Empty Results** | Search for nonsense string "xyz123abc". | Message "No results found" is displayed. |

## 4. Live Text

| ID | Test Case | Steps | Expected Result |
|----|-----------|-------|-----------------|
| FE-13 | **Live Text Banner** | Ensure a Live Text is "LIVE" in backend.<br>Go to Homepage. | "Breaking News" / Live Text banner is visible. |
| FE-14 | **Live Feed Page** | Click on Live Text banner. | Opens Live Text detail page. Timeline of posts is visible. |
| FE-15 | **Real-time Update** | 1. Keep page open.<br>2. (Backend) Add new post.<br>3. Observer Frontend. | New post appears automatically without page refresh (Mercure/SSE). |

## 5. Technical / Responsive

| ID | Test Case | Steps | Expected Result |
|----|-----------|-------|-----------------|
| FE-16 | **Mobile Layout** | Resize browser to <768px (or use DevTools Mobile View). | Burger menu appears. Layout stacks vertically. No horizontal scroll due to overflow. |
| FE-17 | **404 Page** | Navigate to `/non-existent-page`. | Custom 404 Error page is displayed. Links to Homepage. |

## 6. Troubleshooting & Debugging

If any of the above tests fail, please perform the following checks to identify the root cause:

| Check | Action | What to look for |
|-------|--------|------------------|
| **Browser Console** | Open DevTools (F12) -> Console tab. | Red error messages, network failures (404, 500), JavaScript exceptions, or missing resources. |
| **Network Tab** | Open DevTools (F12) -> Network tab. | Failed requests (red). API calls returning 500 errors. Slow loading resources. |
| **Backend Logs** | terminal: `symfony server:log` or `docker compose logs` | PHP Fatal Errors, Exception stack traces, Database connection issues. |
| **Frontend Logs** | terminal: Check the terminal running `pnpm dev` | Next.js server-side errors, rendering exceptions, API connection failures. |
