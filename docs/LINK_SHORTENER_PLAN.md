# Link Shortener Service - Technical Specification

## 1. Overview
This document defines the architecture for the "Bit.ly-like" internal link shortening service. The service supports two primary use cases:
1.  **Automatic Article Short Links**: `deschide.md/s/{webcode}` generated automatically when an article is submitted.
2.  **Custom Short Links**: Manual creation of short links (e.g., `deschide.md/s/campanie2025`) for marketing and social media.

All links include detailed analytics types (Referrer, User Agent, Geo-location).

## 2. Technical Architecture

### 2.1 Database Schema

#### `Article` Entity (Modification)
*   **`webcode`** (`string`, length: 10, unique, nullable)
    *   *Purpose*: Persists the unique code for the article. Used to generate the canonical short link.

#### `ShortLink` Entity (New)
*   **`id`** (`int`, PK)
*   **`code`** (`string`, unique, indexed) - The part after `/s/`.
*   **`originalUrl`** (`string`, length: 500) - The destination URL.
*   **`title`** (`string`, nullable) - For internal dashboard identification.
*   **`clickCount`** (`int`, default: 0) - Denormalized counter for fast sorting/display.
*   **`article`** (`ManyToOne: Article`, nullable) - Link to an article if applicable.
*   **`createdAt`** (`datetime_immutable`)
*   **`createdBy`** (`ManyToOne: User`, nullable)

#### `ShortLinkInteraction` Entity (New)
*   **`id`** (`int`, PK)
*   **`shortLink`** (`ManyToOne: ShortLink`)
*   **`clickedAt`** (`datetime_immutable`)
*   **`ipAddress`** (`string`, nullable) - Anonymized (GDPR compliant).
*   **`userAgent`** (`string`, nullable)
*   **`referrer`** (`string`, nullable)
*   **`countryCode`** (`string`, length: 2, nullable)
*   **`deviceType`** (`string`, length: 20, nullable) - (mobile, desktop, tablet)

### 2.2 API Platform Resources

The following endpoints will be available for the Admin Dashboard:

*   **`GET /api/short_links`**
    *   Filters: `code` (exact), `title` (partial), `article` (exact).
    *   Sort: `createdAt` (DESC), `clickCount` (DESC).
*   **`POST /api/short_links`**
    *   Payload: `{ "originalUrl": "...", "code": "(optional custom alias)", "title": "..." }`
    *   *Validation*: Check if `code` is already taken. If `code` missing -> generate random.
*   **`DELETE /api/short_links/{id}`**
*   **`GET /api/short_links/{id}/stats`**
    *   Returns aggregated stats (clicks per day, top referrers) via a custom Controller or Provider.

### 2.3 Key Services & Logic

#### A. URL Handling & Redirection
*   **Reserved Slugs**: add `'s'` to `ReservedSlug::RESERVED_SLUGS` to prevent CMS conflicts.
*   **Controller**: `ShortLinkRedirectController` listening on `/s/{code}`.
    *   *Cache*: Redirect responses should NOT be cached publicly to ensure stats accuracy (or use a pixel tracker strategy if high-perf needed). For now: `Cache-Control: private, data-no-store`.

#### B. Automatic Generation (Event Subscriber)
*   **Event**: `workflow.article.transition.submit`
*   **Subscriber**: `ArticleWebcodeSubscriber`
*   **Logic**:
    1.  Check if `article.webcode` exists. If yes, skip.
    2.  Generate `webcode` = `ShortCodeGenerator::generate()`.
    3.  Set `article.webcode`.
    4.  Create `ShortLink` entity pointing to the article's public route.

#### C. Analytics Processing
*   **Async**: Redirection must be fast (<50ms). Analytics are dispatched to RabbitMQ.
*   **Message**: `ShortLinkClickMessage(shortLinkId, ip, userAgent, referrer)`.
*   **Handler**: `ShortLinkClickMessageHandler` -> Hydrates `ShortLinkInteraction` and flushes to DB.

## 3. Implementation Checklist

### Phase 1: Planning
- [x] Design Database Schema
- [x] Create Technical Specification

### Phase 2: Backend (Symfony)
- [ ] **Config**: Add 's' to `ReservedSlug`.
- [ ] **Entity**: Modify `Article` (add `webcode`).
- [ ] **Entity**: Create `ShortLink` & `ShortLinkInteraction`.
- [ ] **Service**: Implement `ShortCodeGenerator` (Base62 logic).
- [ ] **Event**: Create `ArticleWebcodeSubscriber`.
- [ ] **Controller**: Create `RedirectController` (`/s/{code}`).
- [ ] **API**: Configure API Platform resources.
- [ ] **Worker**: Set up Async Analytics handler.

### Phase 3: Frontend (Next.js)
- [ ] **Admin**: Create `/admin/short-links` dashboard (List, Create, Delete).
- [ ] **Admin**: Create detailed Stats View (Charts).
- [ ] **Editor**: Add "Short Link" read-only field in Article Editor.

## 4. Workflows

### Creating a Custom Link
1.  Admin navigates to **Marketing > Short Links**.
2.  Clicks "Create New".
3.  Enters Destination URL (e.g., `https://partner.com/promo`).
4.  (Optional) Enters Custom Alias (e.g., `promo24`).
5.  System validates alias availability.
6.  Link `deschide.md/s/promo24` is active immediately.

### Viewing Stats
1.  Admin clicks on a Short Link in the dashboard.
2.  See line chart of "Clicks over last 30 days".
3.  See pie charts for "Top Referrers" and "Device Types".
