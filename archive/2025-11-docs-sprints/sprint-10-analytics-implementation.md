# Sprint 10: Analytics & Metrics Implementation

**Date**: 2025-11-03
**Phase**: Phase 5 - Analytics & Polish
**Sprint**: Sprint 10 - Analytics & Metrics
**Status**: ✅ Backend Complete | ⏳ Frontend In Progress

## Overview

Sprint 10 focuses on implementing comprehensive analytics and metrics tracking for LiveText feature. This includes view tracking, real-time viewer counts, engagement metrics, and detailed analytics dashboards.

## Implementation Summary

### Backend Implementation (✅ Complete)

#### 1. Database Schema

**New Table**: `live_text_views`

```sql
CREATE TABLE live_text_views (
    id SERIAL PRIMARY KEY,
    live_text_id INT NOT NULL,
    user_id INT NULL,
    session_id VARCHAR(255) NOT NULL,
    time_spent INT DEFAULT 0,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    viewed_at TIMESTAMP NOT NULL,
    last_activity_at TIMESTAMP NOT NULL,

    FOREIGN KEY (live_text_id) REFERENCES live_texts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES "user"(id) ON DELETE SET NULL
);

CREATE INDEX idx_live_text_view_live_text ON live_text_views(live_text_id);
CREATE INDEX idx_live_text_view_session ON live_text_views(session_id);
CREATE INDEX idx_live_text_view_ip ON live_text_views(ip_address);
CREATE INDEX idx_live_text_view_viewed_at ON live_text_views(viewed_at);
```

#### 2. New Entities

**File**: `src/Entity/LiveTextView.php`
- Tracks individual view sessions
- Records time spent, IP address, user agent
- Supports both authenticated and anonymous users
- Automatic timestamp management

#### 3. Repository with Advanced Queries

**File**: `src/Repository/LiveTextViewRepository.php`

**Methods**:
- `getTotalViewsCount()` - Total views for a LiveText
- `getUniqueViewersCount()` - Count unique viewers by IP
- `getAverageTimeSpent()` - Average time spent viewing
- `getPeakConcurrentViewers()` - Maximum concurrent viewers in any 5-minute window
- `getViewsOverTime()` - Views grouped by hour for charts
- `getActiveSessions()` - Active sessions in last 5 minutes
- `getViewersByPlatform()` - Viewer count by device type (Mobile/Desktop/Tablet)
- `cleanupOldSessions()` - Cleanup sessions older than 24 hours

#### 4. Analytics Service

**File**: `src/Service/LiveTextAnalyticsService.php`

**Key Features**:
- Track views with session management
- Update time spent incrementally
- Real-time viewer count with database fallback
- Comprehensive analytics with caching (60s TTL)
- Post engagement metrics (reactions per post)
- Platform detection from user agent

**Methods**:
- `trackView()` - Track initial view or heartbeat
- `updateTimeSpent()` - Update session duration
- `getActiveViewerCount()` - Current active viewers
- `getAnalytics()` - Comprehensive analytics (cached)
- `clearCache()` - Invalidate analytics cache

#### 5. API Endpoints

**Analytics Endpoint**: `GET /api/live_texts/{id}/analytics`
- Query param: `?detailed=true` for full analytics
- Returns: LiveTextAnalyticsDto

**Viewer Count**: `GET /api/live_texts/{id}/viewers`
- Returns: Current viewer count

**Track View**: `POST /api/live_texts/{id}/track_view`
- Body: `{"sessionId": "...", "timeSpent": 30}`
- Returns: Success status and current viewer count

#### 6. Security Configuration

**Public Access** (no authentication required):
- `/api/live_texts/.+/track_view` - Track views
- `/api/live_texts/.+/viewers` - Get viewer count
- `/api/live_texts/generate_session` - Generate session ID

### Frontend Implementation (✅ Complete - Core)

#### 1. TypeScript Types

**File**: `lib/types/livetext.ts`

**New Interfaces**:
- `ViewsOverTimeData` - Hourly view data for charts
- `PostEngagementData` - Post-level engagement metrics
- `ViewersByPlatformData` - Platform breakdown
- `LiveTextAnalytics` - Main analytics response
- `LiveTextViewerCount` - Current viewers
- `TrackViewRequest` - Track view request payload
- `TrackViewResponse` - Track view response

#### 2. API Functions

**File**: `lib/api/livetext-analytics.ts`

**Functions**:
- `getLiveTextAnalytics()` - Fetch full analytics
- `getLiveTextViewerCount()` - Get current viewers
- `trackLiveTextView()` - Track a view session
- `generateSessionId()` - Generate unique session ID

## Analytics Metrics

### Summary Metrics

| Metric | Description | Endpoint |
|--------|-------------|----------|
| **Total Views** | Total number of view sessions | `/analytics` |
| **Unique Viewers** | Distinct viewers by IP address | `/analytics` |
| **Average Time Spent** | Average seconds per session | `/analytics` |
| **Peak Concurrent** | Max viewers at same time (5-min window) | `/analytics` |
| **Current Viewers** | Active viewers right now | `/viewers` |
| **Total Posts** | Number of posts in LiveText | `/analytics` |
| **Total Reactions** | Sum of all reactions | `/analytics` |

### Detailed Analytics (optional)

**Views Over Time**:
```json
{
  "viewsOverTime": [
    {"hour": "2025-11-03 11:00:00", "count": 45},
    {"hour": "2025-11-03 12:00:00", "count": 67}
  ]
}
```

**Post Engagement**:
```json
{
  "postEngagement": [
    {
      "postId": 123,
      "content": "Breaking news update...",
      "reactionsCount": 15,
      "publishedAt": "2025-11-03T11:30:00+00:00"
    }
  ]
}
```

**Viewers by Platform**:
```json
{
  "viewersByPlatform": [
    {"platform": "Mobile", "count": 120},
    {"platform": "Desktop", "count": 85},
    {"platform": "Tablet", "count": 12},
    {"platform": "Other", "count": 3}
  ]
}
```

## Testing

### Backend Tests

**Analytics Endpoint**:
```bash
curl http://127.0.0.1:8081/api/live_texts/1/analytics | jq '.'
curl http://127.0.0.1:8081/api/live_texts/1/analytics?detailed=true | jq '.'
```

**Viewer Count**:
```bash
curl http://127.0.0.1:8081/api/live_texts/1/viewers | jq '.'
```

**Track View**:
```bash
curl -X POST http://127.0.0.1:8081/api/live_texts/1/track_view \
  -H "Content-Type: application/json" \
  -d '{"sessionId": "test-session-123", "timeSpent": 30}' | jq '.'
```

### Test Results

✅ All endpoints working correctly
✅ PostgreSQL compatibility (native SQL for date formatting)
✅ Public access properly configured
✅ Real-time viewer tracking functional
✅ Analytics data accurate and cached

## Usage Examples

### Track a View (Frontend)

```typescript
import { trackLiveTextView, generateSessionId } from '@/lib/api/livetext-analytics';

// On page load
const sessionId = generateSessionId();
localStorage.setItem('livetext_session_id', sessionId);

// Track initial view
await trackLiveTextView(liveTextId, { sessionId });

// Track heartbeat every 30 seconds
setInterval(async () => {
  await trackLiveTextView(liveTextId, {
    sessionId,
    timeSpent: 30 // seconds since last heartbeat
  });
}, 30000);
```

### Display Viewer Count

```typescript
import { getLiveTextViewerCount } from '@/lib/api/livetext-analytics';

// Fetch current viewers
const { currentViewers } = await getLiveTextViewerCount(liveTextId);

// Display: "👁 1,234 watching live"
```

### Show Analytics Dashboard

```typescript
import { getLiveTextAnalytics } from '@/lib/api/livetext-analytics';

// Fetch detailed analytics
const analytics = await getLiveTextAnalytics(liveTextId, true);

console.log(analytics.totalViews);         // 5,432
console.log(analytics.uniqueViewers);      // 3,210
console.log(analytics.averageTimeSpent);   // 342.5 (seconds)
console.log(analytics.peakConcurrentViewers); // 1,234
console.log(analytics.currentViewers);     // 567
console.log(analytics.viewsOverTime);      // Array for charts
console.log(analytics.postEngagement);     // Top 10 posts with reactions
```

## Performance Optimizations

### Caching Strategy

- **Analytics Cache**: 60-second TTL for analytics responses
- **Database Queries**: Optimized with proper indexing
- **Native SQL**: PostgreSQL-specific functions for date formatting

### Database Indexing

All critical columns indexed:
- `live_text_id` (for filtering)
- `session_id` (for tracking)
- `ip_address` (for unique viewers)
- `viewed_at` (for time-based queries)

### Cleanup Job

```bash
# Run daily via cron to cleanup old sessions (24h+)
symfony console app:cleanup-old-livetext-views
```

## Remaining Tasks (Frontend)

### ⏳ Sprint 10 - Frontend Components

1. **Real-Time Viewer Counter Component**
   - Display current viewers in LiveText header
   - Auto-update every 30 seconds
   - Animated counter transitions

2. **Admin Analytics Dashboard**
   - Summary metrics cards
   - Views over time chart (Chart.js or Recharts)
   - Post engagement table
   - Platform breakdown pie chart

3. **User Presence Tracking Hook**
   - `useViewerTracking()` custom React hook
   - Automatic heartbeat every 30 seconds
   - Session management with localStorage
   - Cleanup on unmount

### Example Component Structure

```typescript
// app/[locale]/live/[slug]/components/ViewerCounter.tsx
'use client';

export function ViewerCounter({ liveTextId }: { liveTextId: number }) {
  const [viewerCount, setViewerCount] = useState(0);

  useEffect(() => {
    // Fetch and update every 30s
    const interval = setInterval(async () => {
      const { currentViewers } = await getLiveTextViewerCount(liveTextId);
      setViewerCount(currentViewers);
    }, 30000);

    return () => clearInterval(interval);
  }, [liveTextId]);

  return (
    <div className="flex items-center gap-2">
      <Eye className="h-5 w-5" />
      <span>{viewerCount.toLocaleString()} watching live</span>
    </div>
  );
}
```

## Next Steps

1. ✅ **Backend**: Complete
2. ⏳ **Frontend Components**: Implement viewer counter, analytics dashboard, presence tracking
3. ⏳ **Sprint 11**: Notifications & Polish (sound notifications, visual cues, performance optimization)

## Architecture Decisions

### Why Native SQL for Date Formatting?

Doctrine DQL doesn't support database-specific functions like `TO_CHAR()` in `GROUP BY` clauses. Using native SQL queries ensures PostgreSQL compatibility and optimal performance.

### Why Database Fallback for Viewer Count?

While Redis is ideal for real-time counts, we use database queries as a fallback for simplicity and reliability. In production, implement Redis with Predis or PhpRedis for better performance.

### Why 60-Second Cache TTL?

Balances between real-time accuracy and performance. Analytics don't need to be instant, and caching reduces database load significantly during high traffic.

## Files Created/Modified

### Backend
- ✅ `src/Entity/LiveTextView.php`
- ✅ `src/Repository/LiveTextViewRepository.php`
- ✅ `src/Service/LiveTextAnalyticsService.php`
- ✅ `src/Dto/LiveText/LiveTextAnalyticsDto.php`
- ✅ `src/State/LiveTextAnalyticsProvider.php`
- ✅ `src/Controller/LiveTextViewController.php`
- ✅ `src/Entity/LiveText.php` (added analytics endpoint)
- ✅ `config/packages/security.yaml` (public access config)
- ✅ `migrations/Version20251103115642.php` (migration)

### Frontend
- ✅ `lib/types/livetext.ts` (added analytics types)
- ✅ `lib/api/livetext-analytics.ts` (new file with analytics functions)

## Conclusion

Sprint 10 backend implementation is **complete and tested**. The analytics system provides comprehensive metrics for LiveText viewing behavior, real-time viewer counts, and detailed engagement data. Frontend components are ready for implementation with full API support.

**Status**: ✅ Backend Complete | ⏳ Frontend Components Next
