# Live Text Feature - Sprint 4: Frontend Public Viewer

**Status:** ✅ Completed
**Date:** November 3, 2025
**Sprint:** Phase 2 - Real-Time Updates, Sprint 4

## Overview

Sprint 4 implements the public-facing Live Text viewer with real-time updates via Mercure. Users can browse active Live Text events and view individual events with automatic updates as new posts are published.

## Implementation Summary

### 1. TypeScript Type Definitions

**File:** `/var/www/deschide_news_app/deschide_frontend/lib/types/livetext.ts`

Created comprehensive TypeScript interfaces matching the backend API:

- `LiveTextStatus` - Type for status enum ('draft' | 'live' | 'paused' | 'ended')
- `LiveText` - Full LiveText entity interface
- `LiveTextPost` - Post entity interface
- `LiveTextAuthor` - Author interface
- `LiveTextCategory` - Category interface
- `LiveTextCollaborator` - Collaborator interface
- `LiveTextListItem` - Simplified interface for list views
- **Mercure Event Types:**
  - `MercureEventBase` - Base interface for all events
  - `PostCreatedEvent` - New post published
  - `PostUpdatedEvent` - Post content updated
  - `PostDeletedEvent` - Post removed
  - `StatusChangedEvent` - LiveText status changed
  - `ViewersCountEvent` - Viewer count updated (for future use)
  - `MercureEvent` - Union type of all events
- **API Response Types:**
  - `LiveTextApiResponse` - Hydra/JSON-LD API response format
  - `LiveTextCollectionResponse` - Collection response with pagination

### 2. Mercure Subscription Hook

**File:** `/var/www/deschide_news_app/deschide_frontend/lib/hooks/useMercureSubscription.ts`

Custom React hook for subscribing to Mercure real-time events:

**Features:**
- EventSource-based SSE (Server-Sent Events) connection
- Automatic reconnection with configurable attempts (default: 5)
- Connection status tracking (connecting, connected, disconnected, error)
- Event parsing and type-safe event handling
- Graceful cleanup on unmount
- Debug mode for development
- Manual reconnection trigger

**Usage:**
```typescript
const { latestEvent, status, error, reconnect } = useMercureSubscription(
  liveTextId,
  {
    autoReconnect: true,
    maxReconnectAttempts: 5,
    reconnectDelay: 3000,
    debug: process.env.NODE_ENV === 'development',
  }
);
```

**Configuration:**
- `NEXT_PUBLIC_MERCURE_URL` - Mercure hub URL (already configured in `.env.local`)
- `NEXT_PUBLIC_API_URL` - Backend API URL for topic construction

### 3. API Service Functions

**File:** `/var/www/deschide_news_app/deschide_frontend/lib/api/livetext.ts`

Client-side API service for fetching LiveText data:

**Functions:**
- `getLiveTexts(filters, options)` - Fetch all LiveTexts with filtering
- `getLiveTextBySlug(slug, options)` - Fetch single LiveText by slug
- `getLiveTextById(id, options)` - Fetch single LiveText by ID
- `getActiveLiveTexts(options)` - Helper to get active (live/paused) LiveTexts
- `getMercureTopicUrl(liveTextId)` - Build Mercure topic URL

**Filtering Options:**
- `status` - Filter by status (single or array)
- `category` - Filter by category ID
- `locale` - Language filter
- `page` - Pagination page number
- `itemsPerPage` - Items per page
- `orderBy` - Sort field (startTime, endTime, createdAt, title)
- `orderDirection` - Sort direction (ASC, DESC)

**Fetch Options:**
- `locale` - Request language (sets Accept-Language header)
- `cache` - Next.js cache strategy
- `revalidate` - ISR revalidation interval

**Example:**
```typescript
const { items, totalItems } = await getLiveTexts({
  status: ['live', 'paused'],
  orderBy: 'startTime',
  orderDirection: 'DESC'
}, { locale: 'ro' });
```

### 4. LiveText List Page

**File:** `/var/www/deschide_news_app/deschide_frontend/app/[locale]/(public)/live/page.tsx`

Public page listing all Live Text events with filtering.

**Features:**
- Server-side rendered for SEO
- Status filtering (All Active, Live, Paused, Ended)
- Card-based grid layout (responsive: 1 col mobile, 2 col tablet, 3 col desktop)
- Status badges with color coding:
  - **LIVE** - Red background, animated pulse
  - **PAUSED** - Yellow background
  - **ENDED** - Gray background
- Displays metadata: category, author, start time, end time
- No caching (`revalidate: 0`, `dynamic: 'force-dynamic'`) for real-time freshness
- Multilanguage support (ro, en, ru)
- SEO metadata with Open Graph tags

**URL Patterns:**
- `/ro/live` - All active events (Romanian)
- `/ro/live?status=live` - Only live events
- `/ro/live?status=paused` - Only paused events
- `/ro/live?status=ended` - Ended events

**Translations:**
- Romanian (ro) - Primary language
- English (en)
- Russian (ru)

### 5. LiveText Viewer Page

**Files:**
- `/var/www/deschide_news_app/deschide_frontend/app/[locale]/(public)/live/[slug]/page.tsx` - Server component (data fetching)
- `/var/www/deschide_news_app/deschide_frontend/app/[locale]/(public)/live/[slug]/LiveTextViewer.tsx` - Client component (real-time updates)

**Architecture:**
- **Server Component** (page.tsx):
  - Fetches initial LiveText data by slug
  - Returns 404 if not found
  - Generates SEO metadata
  - Passes data to client component

- **Client Component** (LiveTextViewer.tsx):
  - Manages real-time state with Mercure
  - Handles all event types
  - Displays posts in reverse chronological order
  - Scroll position tracking
  - New posts notification

**Features:**

1. **Real-time Event Handling:**
   - `post.created` - Adds new post to top of list
   - `post.updated` - Updates existing post content
   - `post.deleted` - Removes post from list
   - `status.changed` - Updates LiveText status badge

2. **User Experience:**
   - Connection status indicator (green/yellow/red dot)
   - New posts alert when scrolled away from top
   - "View new posts" floating button
   - Smooth scroll to top
   - Auto-dismiss alert when at top
   - Fade-in animation for new posts
   - Key point highlighting (red border and background)

3. **Post Display:**
   - Author name and avatar icon
   - Timestamp (localized format HH:MM)
   - HTML content rendering
   - Key point badge for important posts
   - Border highlighting for key posts

4. **Status Badge:**
   - **LIVE** - Red with pulse animation
   - **PAUSED** - Yellow
   - **ENDED** - Gray
   - Updates in real-time on status change

5. **Responsive Design:**
   - Container max-width: 4xl (896px)
   - Fixed new posts alert at top center
   - Scrollable posts container (max-height: 800px)
   - Mobile-friendly layout

6. **Multilanguage Support:**
   - All UI text translated (ro, en, ru)
   - Date/time formatting with locale
   - Connection status messages
   - Post metadata

**URL Pattern:**
- `/ro/live/[slug]` - View specific LiveText event

### 6. Styling and Animations

**File:** `/var/www/deschide_news_app/deschide_frontend/tailwind.config.ts`

Added custom animations:
- `fade-in` - Smooth fade and slide animation for new posts
  - Duration: 0.5s
  - Easing: ease-out
  - Transform: translateY(-10px) to 0

**Tailwind Classes:**
- `animate-pulse` - For LIVE badge
- `animate-bounce` - For new posts alert
- `animate-fade-in` - For new posts appearing
- Dark mode support with `dark:` variants

## Technical Details

### Real-time Update Flow

1. **Subscription Initialization:**
   ```
   User opens LiveText page
   → Server fetches initial data
   → Client component mounts
   → useMercureSubscription hook creates EventSource
   → Subscribes to topic: deschide_news/live_text/{id}
   ```

2. **Event Reception:**
   ```
   Backend publishes event to Mercure
   → Mercure hub broadcasts to all subscribers
   → EventSource receives message
   → Hook parses JSON event data
   → Hook updates latestEvent state
   → Component useEffect detects change
   → Component handles event based on type
   → UI updates automatically
   ```

3. **Auto-reconnection:**
   ```
   Connection error detected
   → EventSource closes
   → Hook increments reconnect attempts
   → Wait for reconnectDelay (3s)
   → Create new EventSource
   → Repeat up to maxReconnectAttempts (5)
   ```

### State Management

**LiveTextViewer Component State:**
- `liveText` - Current LiveText entity (for title, status, description)
- `posts` - Array of posts (updated in real-time)
- `newPostsCount` - Number of new posts received while scrolled
- `showNewPostsAlert` - Whether to show "new posts" notification
- `isAtTop` - Whether user is scrolled to top of posts

**State Updates:**
- Server-sent events trigger state updates
- React automatically re-renders components
- Smooth animations applied to new content
- No manual DOM manipulation needed

### Performance Considerations

1. **No Caching on Pages:**
   - List page: `revalidate: 0`, `dynamic: 'force-dynamic'`
   - Viewer page: `revalidate: 0`, `dynamic: 'force-dynamic'`
   - Ensures users always see latest data

2. **EventSource Connection:**
   - Single persistent connection per viewer
   - Automatic cleanup on unmount
   - Minimal data transfer (only updates)

3. **Efficient Rendering:**
   - React key prop on posts (post.id)
   - Only changed posts re-render
   - Animation applied only to new posts (index === 0)

4. **Scroll Optimization:**
   - Scroll handler with state checks
   - Alert dismissed automatically at top
   - Smooth scroll behavior

### Error Handling

1. **API Errors:**
   - Server components: Return 404 on not found
   - Catch and log errors
   - Show user-friendly messages

2. **Mercure Connection Errors:**
   - Display connection status indicator
   - Show reconnection attempts
   - Manual reconnect button
   - Error messages in status text

3. **Missing Data:**
   - Check for null/undefined before rendering
   - Fallback to default locale if translation missing
   - Empty state messages ("No posts yet")

## SEO and Metadata

Both pages include comprehensive SEO metadata:

**List Page:**
- Title and description (per locale)
- Canonical URLs
- Language alternates (ro, en, ru)
- Open Graph tags
- Proper locale codes (ro_RO, en_US, ru_RU)

**Viewer Page:**
- Dynamic title with status and LiveText title
- Description from LiveText or fallback
- Canonical URL with slug
- Open Graph article metadata
- Published and modified times
- Author information
- Twitter Card support

## Testing

### Backend Integration

Verified backend API endpoints:
```bash
# List all LiveTexts
curl http://127.0.0.1:8081/api/live_texts

# Get specific LiveText with posts
curl http://127.0.0.1:8081/api/live_texts/2
```

**Test Data:**
- 10 LiveTexts created via seed command
- Mix of statuses: live, paused, ended, draft
- 3-5 posts per LiveText
- Translations in all locales (ro, en, ru)

### Frontend Testing

**Development Server:**
```bash
cd /var/www/deschide_news_app/deschide_frontend
pnpm dev
# Server running on http://localhost:3005
```

**TypeScript Compilation:**
- No type errors in types definition
- Proper type inference in components
- All imports resolved correctly

**Manual Testing Checklist:**
- ✅ List page loads and displays LiveTexts
- ✅ Status filtering works
- ✅ Viewer page loads with slug
- ✅ Initial posts display correctly
- ✅ Connection status indicator shows
- ✅ Real-time updates (test with backend POST)
- ✅ Responsive design (mobile/tablet/desktop)
- ✅ Dark mode support
- ✅ Multilanguage switching
- ✅ SEO metadata present

## Known Issues and Limitations

1. **Author Field Empty:**
   - Backend API returns empty array for `author` field in LiveText and LiveTextPost
   - Likely serialization group issue
   - Need to add `live_text:read` group to User entity fields
   - Workaround: Using author username where available

2. **Mercure Bundle Not Installed:**
   - Using manual HttpClient integration instead
   - Works correctly but not using official Symfony bundle
   - Consider installing bundle in future for better integration

3. **Post Ordering:**
   - Backend sends posts ordered by position
   - Frontend displays in reverse chronological (newest first)
   - Might need to adjust backend ordering or add timestamp sorting

## Future Enhancements (Sprint 5+)

Based on roadmap, future improvements:

1. **Viewers Count Feature:**
   - Track and display active viewers
   - Publish `viewers.count` events
   - Update viewer badge in real-time

2. **Post Search/Filter:**
   - Search within LiveText posts
   - Filter by key points only
   - Jump to specific timestamp

3. **Sharing Features:**
   - Share specific post
   - Copy link to post
   - Social media sharing

4. **Notifications:**
   - Browser notifications for new posts
   - Push notifications (PWA)
   - Email alerts for key points

5. **Performance Optimizations:**
   - Virtual scrolling for long post lists
   - Image lazy loading
   - Bundle size optimization

6. **Accessibility:**
   - Screen reader announcements
   - Keyboard navigation
   - ARIA labels and roles
   - Focus management

## Files Created/Modified

### Created Files:
1. `/lib/types/livetext.ts` - TypeScript type definitions
2. `/lib/hooks/useMercureSubscription.ts` - Mercure subscription hook
3. `/lib/hooks/index.ts` - Hook exports
4. `/lib/api/livetext.ts` - LiveText API service
5. `/app/[locale]/(public)/live/page.tsx` - List page
6. `/app/[locale]/(public)/live/[slug]/page.tsx` - Viewer page (server)
7. `/app/[locale]/(public)/live/[slug]/LiveTextViewer.tsx` - Viewer (client)

### Modified Files:
1. `/lib/api/index.ts` - Added livetext export
2. `/tailwind.config.ts` - Added fade-in animation

## Environment Variables

Required in `.env.local` (already configured):
```bash
NEXT_PUBLIC_API_URL=http://127.0.0.1:8081
NEXT_PUBLIC_MERCURE_URL=http://localhost:3000/.well-known/mercure
```

## Deployment Notes

For production deployment:

1. **Mercure Hub:**
   - Ensure Mercure hub is running and accessible
   - Update NEXT_PUBLIC_MERCURE_URL to production URL
   - Configure CORS for frontend domain

2. **API URL:**
   - Update NEXT_PUBLIC_API_URL to production backend
   - Ensure CORS allows frontend domain

3. **Build:**
   ```bash
   cd /var/www/deschide_news_app/deschide_frontend
   pnpm build
   pnpm start
   ```

4. **Caching:**
   - Pages use `revalidate: 0` for real-time data
   - No ISR caching on LiveText pages
   - Consider edge caching with short TTL

5. **Monitoring:**
   - Monitor Mercure connection errors
   - Track event delivery latency
   - Monitor active connections count

## Conclusion

Sprint 4 successfully implements a complete public LiveText viewer with real-time updates. The implementation follows Next.js 16 best practices with server/client component separation, provides excellent UX with smooth animations and notifications, and includes comprehensive SEO support.

The integration with Mercure provides reliable real-time updates without polling, and the custom React hook makes it easy to subscribe to events in any component.

**Next Steps:** Proceed with Sprint 5 (Admin Panel for LiveText Management) to enable editors to create and manage LiveText events through the UI.
