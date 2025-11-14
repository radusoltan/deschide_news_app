# Advanced Analytics Guide

Complete guide for using advanced analytics features in the Deschide News LiveText system.

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Backend Implementation](#backend-implementation)
- [Frontend Implementation](#frontend-implementation)
- [API Reference](#api-reference)
- [Analytics Tracking](#analytics-tracking)
- [A/B Testing](#ab-testing)
- [Best Practices](#best-practices)
- [GDPR Compliance](#gdpr-compliance)
- [Troubleshooting](#troubleshooting)

---

## Overview

The advanced analytics system provides:
- **Engagement Tracking**: Track user interactions with LiveText posts (views, reads, clicks, shares)
- **Heatmap Analysis**: Visualize which posts get the most engagement
- **Funnel Analysis**: Understand user journey from viewing to sharing
- **A/B Testing**: Test different LiveText configurations scientifically
- **Statistical Significance**: Mathematical confidence in test results

### Architecture

```
Frontend (React)
    ↓ Track engagement
Analytics API (/api/analytics)
    ↓ Store events
PostgreSQL (live_text_post_engagements)
    ↓ Query & analyze
Services (LiveTextAdvancedAnalyticsService)
    ↓ Generate insights
Admin Dashboard (Heatmaps, Funnels, A/B Tests)
```

---

## Features

### 1. Engagement Tracking

Track 5 types of user engagement:

| Type | Description | When to Track |
|------|-------------|---------------|
| **view** | User saw the post | Post enters viewport (50% visible for 1s) |
| **read** | User read the post | User spent 10+ seconds, scrolled 50%+ |
| **click** | User clicked something | Click on link, button, image |
| **reaction** | User reacted | Like, emoji reaction |
| **share** | User shared | Social media share |

### 2. Heatmap Analysis

Visualize post engagement intensity:
- **Unique engagements**: Count of unique users per post
- **Total engagements**: All engagement events per post
- **Average time spent**: How long users read each post
- **Average scroll depth**: How far users scrolled (0-100%)
- **Intensity**: Normalized engagement score (0-100)

### 3. Funnel Analysis

Understand conversion through stages:

```
Views (100%)
    ↓ -X% drop-off
Reads (Y%)
    ↓ -X% drop-off
Clicks (Y%)
    ↓ -X% drop-off
Reactions (Y%)
    ↓ -X% drop-off
Shares (Y%)
```

### 4. A/B Testing

Test LiveText variations:
- **Variant types**: template, layout, theme, content, design, timing
- **Target metrics**: views, time_spent, engagement_rate, click_rate, share_rate
- **Statistical significance**: Z-test for proportions, p-values, confidence levels
- **Winner determination**: Automatic based on significance level (default 95%)

---

## Backend Implementation

### Database Schema

#### Table: `live_text_post_engagements`

```sql
CREATE TABLE live_text_post_engagements (
    id SERIAL PRIMARY KEY,
    post_id INT NOT NULL REFERENCES live_text_posts(id),
    user_id INT REFERENCES "user"(id),
    session_id VARCHAR(255) NOT NULL,
    engagement_type VARCHAR(50) NOT NULL,
    time_spent INT,
    scroll_depth INT,
    clicked_element VARCHAR(255),
    metadata JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP NOT NULL
);

-- Indexes for performance
CREATE INDEX idx_post_engagement_post ON live_text_post_engagements(post_id);
CREATE INDEX idx_post_engagement_session ON live_text_post_engagements(session_id);
CREATE INDEX idx_post_engagement_created ON live_text_post_engagements(created_at);
```

#### Table: `live_text_ab_tests`

```sql
CREATE TABLE live_text_ab_tests (
    id SERIAL PRIMARY KEY,
    created_by_id INT NOT NULL REFERENCES "user"(id),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    hypothesis TEXT,
    status VARCHAR(20) NOT NULL,
    variant_type VARCHAR(50) NOT NULL,
    control_variant JSON NOT NULL,
    test_variants JSON NOT NULL,
    traffic_allocation INT NOT NULL,
    target_metric VARCHAR(100) NOT NULL,
    min_sample_size INT,
    significance_level NUMERIC(3, 2),
    start_date TIMESTAMP,
    end_date TIMESTAMP,
    results JSON,
    winner_variant VARCHAR(100),
    confidence_level NUMERIC(5, 2),
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

-- Junction table for A/B tests and LiveTexts
CREATE TABLE live_text_ab_test_live_texts (
    live_text_ab_test_id INT NOT NULL REFERENCES live_text_ab_tests(id) ON DELETE CASCADE,
    live_text_id INT NOT NULL REFERENCES live_texts(id) ON DELETE CASCADE,
    PRIMARY KEY(live_text_ab_test_id, live_text_id)
);
```

### Services

#### LiveTextAdvancedAnalyticsService

Location: `src/Service/LiveTextAdvancedAnalyticsService.php`

**Key Methods**:

```php
// Track engagement
public function trackEngagement(
    LiveTextPost $post,
    string $engagementType,
    ?User $user = null,
    ?int $timeSpent = null,
    ?int $scrollDepth = null,
    ?string $clickedElement = null,
    ?array $metadata = null
): LiveTextPostEngagement;

// Get heatmap data
public function getHeatmapData(LiveText $liveText): array;

// Get funnel data
public function getEngagementFunnel(LiveText $liveText): array;

// Get top engaged posts
public function getTopEngagedPosts(LiveText $liveText, int $limit = 10): array;

// Get summary
public function getEngagementSummary(LiveText $liveText): array;

// Simplified tracking methods
public function trackView(LiveTextPost $post, ?User $user = null): void;
public function trackRead(LiveTextPost $post, int $timeSpent, int $scrollDepth, ?User $user = null): void;
public function trackClick(LiveTextPost $post, string $clickedElement, ?User $user = null): void;
public function trackShare(LiveTextPost $post, string $platform, ?User $user = null): void;

// GDPR compliance
public function clearOldEngagements(\DateTimeInterface $before): int;
```

#### LiveTextAbTestService

Location: `src/Service/LiveTextAbTestService.php`

**Key Methods**:

```php
// Create test
public function createTest(
    string $name,
    string $variantType,
    array $controlVariant,
    array $testVariants,
    string $targetMetric,
    User $createdBy,
    ?string $description = null,
    ?string $hypothesis = null,
    int $trafficAllocation = 100,
    ?int $minSampleSize = null,
    ?string $significanceLevel = '0.05'
): LiveTextAbTest;

// Manage test lifecycle
public function startTest(LiveTextAbTest $test): void;
public function pauseTest(LiveTextAbTest $test): void;
public function completeTest(LiveTextAbTest $test, ?string $winnerVariant = null): void;

// Variant assignment
public function assignVariant(LiveTextAbTest $test, ?string $sessionId = null): string;
public function getVariantConfig(LiveTextAbTest $test, string $variantKey): array;

// Results calculation
public function calculateResults(LiveTextAbTest $test): array;

// LiveText management
public function addLiveTextToTest(LiveTextAbTest $test, LiveText $liveText): void;
public function removeLiveTextFromTest(LiveTextAbTest $test, LiveText $liveText): void;
```

### Controllers

#### AdvancedAnalyticsController

Location: `src/Controller/AdvancedAnalyticsController.php`

**Endpoints**:

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| POST | `/api/analytics/track` | Track engagement event | Public |
| GET | `/api/analytics/live-text/{id}/heatmap` | Get heatmap data | ROLE_EDITOR |
| GET | `/api/analytics/live-text/{id}/funnel` | Get funnel data | ROLE_EDITOR |
| GET | `/api/analytics/live-text/{id}/top-posts` | Get top posts | ROLE_EDITOR |
| GET | `/api/analytics/live-text/{id}/summary` | Get summary | ROLE_EDITOR |
| POST | `/api/analytics/view` | Track view | Public |
| POST | `/api/analytics/read` | Track read | Public |
| POST | `/api/analytics/click` | Track click | Public |
| POST | `/api/analytics/share` | Track share | Public |
| DELETE | `/api/analytics/clear-old` | Clear old data | ROLE_ADMIN |

#### AbTestController

Location: `src/Controller/AbTestController.php`

**Endpoints**:

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| POST | `/api/ab-tests` | Create test | ROLE_EDITOR |
| GET | `/api/ab-tests` | List tests | ROLE_EDITOR |
| GET | `/api/ab-tests/{id}` | Get test details | ROLE_EDITOR |
| PUT | `/api/ab-tests/{id}/start` | Start test | ROLE_EDITOR |
| PUT | `/api/ab-tests/{id}/pause` | Pause test | ROLE_EDITOR |
| PUT | `/api/ab-tests/{id}/complete` | Complete test | ROLE_EDITOR |
| GET | `/api/ab-tests/{id}/variant` | Get assigned variant | Public |
| POST | `/api/ab-tests/{id}/calculate` | Calculate results | ROLE_EDITOR |
| POST | `/api/ab-tests/{id}/live-texts` | Add LiveText to test | ROLE_EDITOR |
| DELETE | `/api/ab-tests/{id}/live-texts/{ltId}` | Remove LiveText | ROLE_EDITOR |
| GET | `/api/ab-tests/live-text/{ltId}` | Get tests by LiveText | ROLE_EDITOR |

---

## Frontend Implementation

### Types

Location: `lib/types/analytics.ts`

Key types defined:
- `EngagementType`
- `PostEngagement`
- `HeatmapData`
- `FunnelData`
- `EngagementSummary`
- `AbTest`, `AbTestSummary`
- `AbTestResults`
- `VariantConfig`

### API Functions

Location: `lib/api/analytics.ts`

**Engagement Tracking**:
```typescript
import { trackEngagement, trackView, trackRead, trackClick, trackShare } from '@/lib/api/analytics';

// Generic tracking
await trackEngagement({
  post_id: 123,
  engagement_type: 'read',
  time_spent: 45,
  scroll_depth: 80
});

// Simplified methods
await trackView(postId);
await trackRead(postId, timeSpent, scrollDepth);
await trackClick(postId, 'button-share');
await trackShare(postId, 'twitter');
```

**Analytics Data**:
```typescript
import { getHeatmapData, getFunnelData, getEngagementSummary } from '@/lib/api/analytics';

// Get heatmap
const heatmap = await getHeatmapData(liveTextId, authToken);

// Get funnel
const funnel = await getFunnelData(liveTextId, authToken);

// Get summary
const summary = await getEngagementSummary(liveTextId, authToken);
```

**A/B Testing**:
```typescript
import { createAbTest, startAbTest, getAssignedVariant, calculateAbTestResults } from '@/lib/api/analytics';

// Create test
const { test } = await createAbTest({
  name: 'Hero Layout Test',
  variant_type: 'layout',
  control_variant: {
    key: 'control',
    name: 'Current Layout',
    config: { layout: 'vertical' }
  },
  test_variants: {
    variant_a: {
      key: 'variant_a',
      name: 'Horizontal Layout',
      config: { layout: 'horizontal' }
    }
  },
  target_metric: 'engagement_rate'
}, authToken);

// Start test
await startAbTest(test.id, authToken);

// Get assigned variant (public)
const { variant, config } = await getAssignedVariant(testId);

// Calculate results
const { results } = await calculateAbTestResults(testId, authToken);
```

### React Hooks

Location: `lib/hooks/useAnalytics.ts`

**Auto-tracking with hooks**:

```tsx
import { usePostTracking, usePostClickTracking, usePostShareTracking } from '@/lib/hooks/useAnalytics';

function LiveTextPost({ post }: { post: Post }) {
  // Automatically track view and read time
  const postRef = usePostTracking(post.id, true);

  // Track clicks
  const trackClick = usePostClickTracking(post.id, true);

  // Track shares
  const trackShare = usePostShareTracking(post.id);

  return (
    <div ref={postRef as any} data-post-id={post.id}>
      <div dangerouslySetInnerHTML={{ __html: post.contentHtml }} />

      <button onClick={() => trackClick('share-button')}>
        Share
      </button>

      <SocialShareButtons
        onShare={(platform) => trackShare(platform)}
      />
    </div>
  );
}
```

**A/B Test Hook**:

```tsx
import { useAbTestVariant } from '@/lib/hooks/useAnalytics';

function LiveTextViewer({ liveText }: { liveText: LiveText }) {
  const { variant, config, loading } = useAbTestVariant(liveText.abTestId);

  if (loading) return <Loading />;

  // Render based on variant
  if (variant === 'variant_a' && config?.layout === 'horizontal') {
    return <HorizontalLayout liveText={liveText} />;
  }

  return <VerticalLayout liveText={liveText} />;
}
```

### Utilities

Location: `lib/utils/analyticsTracker.ts`

**Manual tracking**:

```typescript
import { track, trackPostView, trackPostRead } from '@/lib/utils/analyticsTracker';

// Generic track
await track({
  post_id: 123,
  engagement_type: 'click',
  clicked_element: 'image-gallery'
});

// Specific methods
await trackPostView(postId);
await trackPostRead(postId, 30, 75);
```

**Advanced trackers**:

```typescript
import { PostVisibilityTracker, PostReadTimeTracker } from '@/lib/utils/analyticsTracker';

// Visibility tracker
const visibilityTracker = new PostVisibilityTracker((postId) => {
  console.log(`Post ${postId} viewed`);
});
visibilityTracker.observe(element);

// Read time tracker
const readTracker = new PostReadTimeTracker((postId, time, depth) => {
  console.log(`Post ${postId} read for ${time}s, scrolled ${depth}%`);
});
readTracker.startTracking(postId, element);
```

---

## API Reference

### Analytics API

#### Track Engagement

```http
POST /api/analytics/track
Content-Type: application/json

{
  "post_id": 123,
  "engagement_type": "read",
  "time_spent": 45,
  "scroll_depth": 80,
  "clicked_element": null,
  "metadata": {}
}
```

Response:
```json
{
  "success": true,
  "engagement_id": 456
}
```

#### Get Heatmap Data

```http
GET /api/analytics/live-text/123/heatmap
Authorization: Bearer {token}
```

Response:
```json
{
  "data": [
    {
      "post_id": 789,
      "unique_engagements": 45,
      "total_engagements": 102,
      "avg_time_spent": 32.5,
      "avg_scroll_depth": 68.3,
      "intensity": 85.2
    }
  ],
  "max_engagements": 120,
  "total_posts": 25
}
```

#### Get Funnel Data

```http
GET /api/analytics/live-text/123/funnel
Authorization: Bearer {token}
```

Response:
```json
{
  "funnel": {
    "view": {
      "unique_users": 1000,
      "total_events": 1000,
      "conversion_rate": 100.0
    },
    "read": {
      "unique_users": 450,
      "total_events": 450,
      "conversion_rate": 45.0
    },
    "click": {
      "unique_users": 200,
      "total_events": 250,
      "conversion_rate": 20.0
    },
    "reaction": {
      "unique_users": 80,
      "total_events": 90,
      "conversion_rate": 8.0
    },
    "share": {
      "unique_users": 30,
      "total_events": 35,
      "conversion_rate": 3.0
    }
  },
  "total_views": 1000,
  "drop_off_rate": {
    "view_to_read": 55.0,
    "read_to_click": 55.6,
    "click_to_reaction": 60.0,
    "reaction_to_share": 62.5
  }
}
```

### A/B Testing API

#### Create A/B Test

```http
POST /api/ab-tests
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Hero Layout Test",
  "variant_type": "layout",
  "control_variant": {
    "key": "control",
    "name": "Current Layout",
    "config": { "layout": "vertical" }
  },
  "test_variants": {
    "variant_a": {
      "key": "variant_a",
      "name": "Horizontal Layout",
      "config": { "layout": "horizontal" }
    }
  },
  "target_metric": "engagement_rate",
  "traffic_allocation": 100,
  "significance_level": "0.05"
}
```

Response:
```json
{
  "success": true,
  "test": {
    "id": 1,
    "name": "Hero Layout Test",
    "status": "draft",
    "variant_type": "layout",
    "target_metric": "engagement_rate"
  }
}
```

#### Get Assigned Variant

```http
GET /api/ab-tests/1/variant
```

Response:
```json
{
  "variant": "variant_a",
  "config": {
    "layout": "horizontal"
  },
  "test_id": 1,
  "test_name": "Hero Layout Test"
}
```

---

## Analytics Tracking

### Automatic Tracking

Use React hooks for automatic tracking:

```tsx
import { usePostTracking } from '@/lib/hooks/useAnalytics';

function Post({ post }) {
  const postRef = usePostTracking(post.id);

  return (
    <article ref={postRef as any} data-post-id={post.id}>
      {/* Post content */}
    </article>
  );
}
```

This automatically tracks:
- **View**: When post enters viewport (50% visible)
- **Read**: When user spends 10+ seconds and scrolls 50%+

### Manual Tracking

Track specific events:

```tsx
import { trackPostClick, trackPostShare } from '@/lib/utils/analyticsTracker';

function Post({ post }) {
  return (
    <article>
      <a
        href={post.link}
        onClick={() => trackPostClick(post.id, 'external-link')}
      >
        Read more
      </a>

      <ShareButton
        onShare={(platform) => trackPostShare(post.id, platform)}
      />
    </article>
  );
}
```

### Performance Optimization

Use batching for high-traffic sites:

```typescript
import { analyticsBatcher } from '@/lib/utils/analyticsTracker';

// Events are automatically batched and flushed every 5 seconds
// or when batch size reaches 10 events
```

---

## A/B Testing

### Creating an A/B Test

1. **Define hypothesis**:
   - "Horizontal layout will increase engagement by 20%"

2. **Create test**:
```typescript
const test = await createAbTest({
  name: 'Layout Test',
  hypothesis: 'Horizontal layout increases engagement',
  variant_type: 'layout',
  control_variant: {
    key: 'control',
    name: 'Vertical Layout',
    config: { layout: 'vertical' }
  },
  test_variants: {
    variant_a: {
      key: 'variant_a',
      name: 'Horizontal Layout',
      config: { layout: 'horizontal' }
    }
  },
  target_metric: 'engagement_rate',
  min_sample_size: 1000,
  significance_level: '0.05' // 95% confidence
}, authToken);
```

3. **Add LiveTexts to test**:
```typescript
await addLiveTextToTest(test.id, liveTextId, authToken);
```

4. **Start test**:
```typescript
await startAbTest(test.id, authToken);
```

5. **Implement variants in frontend**:
```tsx
function LiveTextViewer({ liveText }) {
  const { variant, config } = useAbTestVariant(liveText.abTestId);

  if (variant === 'variant_a') {
    return <HorizontalLayout config={config} />;
  }

  return <VerticalLayout config={config} />;
}
```

6. **Monitor and calculate results**:
```typescript
const { results } = await calculateAbTestResults(test.id, authToken);

if (results.winner === 'variant_a' && results.comparisons.variant_a.is_significant) {
  console.log(`Variant A won with ${results.comparisons.variant_a.improvement}% improvement`);
  console.log(`Confidence: ${results.comparisons.variant_a.confidence_level}%`);
}
```

7. **Complete test**:
```typescript
await completeAbTest(test.id, results.winner, authToken);
```

### Variant Assignment

Variants are assigned using:
- **Consistent hashing**: Same session always gets same variant
- **Traffic allocation**: Control % of users in test (default 100%)
- **Even distribution**: Variants evenly distributed

### Statistical Significance

Uses **Z-test for proportions**:

```
Z = (p2 - p1) / SE
SE = sqrt(p * (1 - p) * (1/n1 + 1/n2))
p = (p1*n1 + p2*n2) / (n1 + n2)

p-value = 2 * P(Z > |z|)
```

Where:
- `p1` = control conversion rate
- `p2` = test conversion rate
- `n1` = control sample size
- `n2` = test sample size

Significance level (alpha):
- `0.05` = 95% confidence (recommended)
- `0.01` = 99% confidence (more conservative)
- `0.10` = 90% confidence (less strict)

---

## Best Practices

### Analytics Tracking

1. **Don't over-track**:
   - Only track meaningful interactions
   - Use sampling for high-traffic sites
   - Batch events for performance

2. **Privacy-first**:
   - Use session-based tracking (anonymous)
   - Don't track PII without consent
   - Clear old data regularly (GDPR)

3. **Performance**:
   - Use Intersection Observer API (built-in)
   - Debounce scroll events
   - Send tracking async (non-blocking)

4. **Data quality**:
   - Validate engagement types
   - Set reasonable thresholds (10s read time)
   - Filter bot traffic

### A/B Testing

1. **Test one thing at a time**:
   - ❌ Change layout + colors + content
   - ✅ Change only layout

2. **Run tests long enough**:
   - Minimum: 1 week
   - Recommended: 2-4 weeks
   - Capture weekly cycles

3. **Sample size matters**:
   - Minimum: 100 conversions per variant
   - Recommended: 1000+ per variant
   - Use sample size calculator

4. **Don't peek early**:
   - Let test run to completion
   - Multiple peeks increase false positives
   - Use sequential testing if needed

5. **Document everything**:
   - Hypothesis
   - Expected lift
   - Actual results
   - Learnings

---

## GDPR Compliance

### Data Retention

Clear old engagement data:

```bash
# Clear data older than 90 days
symfony console app:analytics:clear-old --days=90

# Or via API
curl -X DELETE "http://api.example.com/api/analytics/clear-old?days=90" \
  -H "Authorization: Bearer {token}"
```

### Anonymous Tracking

By default, tracking uses:
- **Session ID**: Anonymous, not tied to user account
- **IP address**: Can be hashed or omitted
- **User agent**: For bot detection

### User Consent

Implement consent banner:

```tsx
import { useState, useEffect } from 'react';

function ConsentBanner() {
  const [consent, setConsent] = useState(false);

  useEffect(() => {
    const saved = localStorage.getItem('analytics-consent');
    setConsent(saved === 'true');
  }, []);

  const handleAccept = () => {
    localStorage.setItem('analytics-consent', 'true');
    setConsent(true);
  };

  if (consent) return null;

  return (
    <div className="consent-banner">
      We use cookies for analytics.
      <button onClick={handleAccept}>Accept</button>
    </div>
  );
}

// Then in tracking:
const canTrack = localStorage.getItem('analytics-consent') === 'true';
const postRef = usePostTracking(post.id, canTrack);
```

### Data Access Requests

Provide endpoint for users to request their data:

```php
// In custom controller
public function exportUserData(User $user): JsonResponse
{
    $engagements = $this->entityManager
        ->getRepository(LiveTextPostEngagement::class)
        ->findBy(['user' => $user]);

    return $this->json([
        'user_id' => $user->getId(),
        'engagements' => $engagements,
    ]);
}
```

---

## Troubleshooting

### Tracking Not Working

**Issue**: Events not being tracked

**Solutions**:
1. Check browser console for errors
2. Verify API endpoint is accessible: `curl http://api.example.com/api/analytics/track`
3. Check CORS configuration in `nelmio_cors.yaml`
4. Verify `NEXT_PUBLIC_ANALYTICS_ENABLED` is not set to `false`
5. Check browser privacy/ad blocking extensions

### Heatmap Shows No Data

**Issue**: Heatmap is empty

**Solutions**:
1. Verify tracking is working (see above)
2. Check database: `SELECT COUNT(*) FROM live_text_post_engagements`
3. Wait for sufficient data (at least 10 engagements)
4. Check LiveText ID is correct
5. Verify user has ROLE_EDITOR permission

### A/B Test Not Assigning Variants

**Issue**: All users get control variant

**Solutions**:
1. Check test status: Must be `running`
2. Verify test start date: Must be in the past
3. Check traffic allocation: Must be > 0
4. Verify session cookies are enabled
5. Check browser console for API errors

### Statistical Significance Never Reached

**Issue**: Test runs forever without winner

**Solutions**:
1. Increase traffic: More users = faster results
2. Reduce minimum sample size (if set too high)
3. Test bigger changes (easier to detect)
4. Run test longer (at least 2 weeks)
5. Consider effect may be too small to detect

### Performance Issues

**Issue**: Tracking slows down page

**Solutions**:
1. Enable batching (automatic by default)
2. Reduce tracking frequency
3. Use sampling for high-traffic posts
4. Optimize database indexes
5. Add caching layer (Redis)

### Database Growing Too Large

**Issue**: `live_text_post_engagements` table too big

**Solutions**:
1. Set up automatic cleanup cron:
   ```bash
   # /etc/cron.daily/analytics-cleanup
   symfony console app:analytics:clear-old --days=90
   ```

2. Partition table by date:
   ```sql
   CREATE TABLE live_text_post_engagements_2024_01
   PARTITION OF live_text_post_engagements
   FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');
   ```

3. Archive old data to cold storage

---

## Example: Complete Implementation

### Backend Setup

1. **Entities and repositories** ✅ (Already created)
2. **Services** ✅ (Already created)
3. **Controllers** ✅ (Already created)
4. **Run migration**:
```bash
symfony console doctrine:migrations:migrate
```

### Frontend Setup

1. **Install dependencies** (if needed):
```bash
# No additional dependencies required
```

2. **Add tracking to LiveText viewer**:

```tsx
// app/[locale]/live/[slug]/LiveTextViewer.tsx
'use client';

import { usePostTracking, usePostShareTracking } from '@/lib/hooks/useAnalytics';
import { SocialShareButtons } from '@/components/social/SocialShareButtons';

export function LiveTextViewer({ liveText }) {
  return (
    <div>
      <h1>{liveText.title}</h1>

      {liveText.posts.map((post) => (
        <LiveTextPost key={post.id} post={post} />
      ))}
    </div>
  );
}

function LiveTextPost({ post }) {
  const postRef = usePostTracking(post.id);
  const trackShare = usePostShareTracking(post.id);

  return (
    <article ref={postRef as any} data-post-id={post.id}>
      <div dangerouslySetInnerHTML={{ __html: post.contentHtml }} />

      <SocialShareButtons
        url={`https://example.com/live/${post.liveText.slug}`}
        title={post.liveText.title}
        onShare={(platform) => trackShare(platform)}
      />
    </article>
  );
}
```

3. **Add admin analytics dashboard** (example):

```tsx
// app/[locale]/admin/analytics/[id]/page.tsx
import { getHeatmapData, getFunnelData, getEngagementSummary } from '@/lib/api/analytics';

export default async function AnalyticsPage({ params }) {
  const { id } = params;
  const [heatmap, funnel, summary] = await Promise.all([
    getHeatmapData(id),
    getFunnelData(id),
    getEngagementSummary(id),
  ]);

  return (
    <div>
      <h1>Analytics Dashboard</h1>

      <section>
        <h2>Summary</h2>
        <div>Total Engagements: {summary.summary.total_engagements}</div>
        <div>Unique Users: {summary.summary.total_unique_users}</div>
        <div>Avg Time Spent: {summary.summary.avg_time_spent}s</div>
        <div>Engagement Rate: {summary.summary.engagement_rate}</div>
      </section>

      <section>
        <h2>Engagement Funnel</h2>
        <FunnelChart data={funnel} />
      </section>

      <section>
        <h2>Post Heatmap</h2>
        <HeatmapChart data={heatmap} />
      </section>
    </div>
  );
}
```

---

## Summary

**Advanced Analytics provides**:
- ✅ Engagement tracking (5 types)
- ✅ Heatmap analysis
- ✅ Funnel analysis
- ✅ A/B testing with statistical significance
- ✅ GDPR compliance tools
- ✅ Performance optimizations
- ✅ React hooks for easy integration
- ✅ Comprehensive API

**Files Created**:
- Backend: 5 files (entities, services, controllers, repositories)
- Frontend: 4 files (types, API, hooks, utils)
- Database: 2 tables, 1 junction table
- API: 20 endpoints

**Ready for production**: Yes ✅

---

**Last Updated**: 2025-11-03
**Version**: 1.0
**Author**: Claude Code
