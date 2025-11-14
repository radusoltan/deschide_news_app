# Social Media Integration Guide

This guide explains the social media integration features for LiveText, including auto-posting, Open Graph metadata, and social sharing buttons.

## Overview

The social media integration provides:
- **Auto-Posting** - Automatic posts to Twitter, Facebook, Telegram when LiveText goes live or key points are published
- **Open Graph Metadata** - Optimized metadata for social sharing (Facebook, LinkedIn, etc.)
- **Twitter Cards** - Optimized Twitter card metadata
- **Social Share Buttons** - Pre-built sharing buttons for multiple platforms
- **Sharing URLs** - Helper functions for generating platform-specific sharing URLs

---

## Backend Implementation

### Service

#### `SocialMediaService`
**Location**: `src/Service/SocialMediaService.php`

Handles automatic posting to social media platforms.

**Features**:
- Auto-post when LiveText goes live
- Auto-post when key point posts are created
- Support for Twitter, Facebook, Telegram
- Configurable via environment variables
- Error handling and logging

**Methods**:
- `postLiveTextStarted(LiveText $liveText)` - Post when LiveText starts
- `postImportantUpdate(LiveTextPost $post)` - Post key points
- `getSharingMetadata(LiveText $liveText)` - Get sharing metadata for frontend

**Supported Platforms**:
1. **Twitter** - Via Twitter API v2
2. **Facebook** - Via Facebook Graph API
3. **Telegram** - Via Telegram Bot API

### Configuration

**Environment Variables** (`.env` or `.env.local`):

```bash
# Frontend URL for generating links
FRONTEND_URL=http://localhost:3005

# Twitter API (OAuth 2.0)
TWITTER_API_KEY=your_api_key
TWITTER_API_SECRET=your_api_secret
TWITTER_ACCESS_TOKEN=your_access_token
TWITTER_ACCESS_SECRET=your_access_secret

# Facebook Page
FACEBOOK_PAGE_ID=your_page_id
FACEBOOK_ACCESS_TOKEN=your_page_access_token

# Telegram Bot
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_CHANNEL_ID=@your_channel_id
```

**Services Configuration** (`config/services.yaml`):

```yaml
services:
    App\Service\SocialMediaService:
        arguments:
            $frontendUrl: '%env(FRONTEND_URL)%'
            $twitterApiKey: '%env(TWITTER_API_KEY)%'
            $twitterApiSecret: '%env(TWITTER_API_SECRET)%'
            $twitterAccessToken: '%env(TWITTER_ACCESS_TOKEN)%'
            $twitterAccessSecret: '%env(TWITTER_ACCESS_SECRET)%'
            $facebookPageId: '%env(FACEBOOK_PAGE_ID)%'
            $facebookAccessToken: '%env(FACEBOOK_ACCESS_TOKEN)%'
            $telegramBotToken: '%env(TELEGRAM_BOT_TOKEN)%'
            $telegramChannelId: '%env(TELEGRAM_CHANNEL_ID)%'
```

### Auto-Posting Triggers

#### 1. LiveText Goes Live

**Trigger**: `LiveTextProcessor` - When status changes from any status to `LIVE`
**File**: `src/State/LiveTextProcessor.php:96-98`

```php
// Auto-post to social media when going LIVE
if ($data->getStatus() === LiveTextStatus::LIVE && $oldStatus !== LiveTextStatus::LIVE) {
    $this->socialMediaService->postLiveTextStarted($existingEntity);
}
```

**Message Format**:
- Twitter: `🔴 LIVE: {title} - {url}`
- Facebook: Same format with link preview
- Telegram: Same with HTML formatting

#### 2. Key Point Post Created

**Trigger**: `LiveTextPostProcessor` - When new post with `isKeyPoint=true` is created
**File**: `src/State/LiveTextPostProcessor.php:119-122`

```php
// Auto-post to social media if this is a key point
if ($data->isKeyPoint()) {
    $this->socialMediaService->postImportantUpdate($data);
}
```

**Message Format**:
- Twitter: `⚡ {livetext_title}: {post_content} - {url}`
- Content is truncated to fit platform limits
- URL always included

### Platform-Specific Details

#### Twitter Integration

**API**: Twitter API v2
**Endpoint**: `POST https://api.twitter.com/2/tweets`
**Authentication**: Bearer Token (OAuth 2.0)
**Character Limit**: 280 characters (including URL)

**Setup Steps**:
1. Create Twitter Developer Account
2. Create an App
3. Generate API Keys and Access Tokens
4. Add credentials to `.env.local`

#### Facebook Integration

**API**: Facebook Graph API
**Endpoint**: `POST https://graph.facebook.com/{page_id}/feed`
**Authentication**: Page Access Token
**Character Limit**: 500 characters (soft limit)

**Setup Steps**:
1. Create Facebook Page
2. Create Facebook App
3. Get Page Access Token (with `pages_manage_posts` permission)
4. Add credentials to `.env.local`

#### Telegram Integration

**API**: Telegram Bot API
**Endpoint**: `POST https://api.telegram.org/bot{token}/sendMessage`
**Authentication**: Bot Token
**No character limit**

**Setup Steps**:
1. Create Telegram Bot via @BotFather
2. Get Bot Token
3. Add bot as admin to your channel
4. Get channel ID (e.g., `@my_channel`)
5. Add credentials to `.env.local`

---

## Frontend Implementation

### Utilities

#### `socialMetadata.ts`
**Location**: `lib/utils/socialMetadata.ts`

Utility functions for social media metadata.

**Functions**:
- `generateLiveTextMetadata(liveText, locale)` - Generate Open Graph metadata for LiveText
- `generateArticleMetadata(article, locale)` - Generate Open Graph metadata for Article
- `generateOpenGraphTags(metadata)` - Generate OG meta tags for Next.js
- `generateTwitterCardTags(metadata)` - Generate Twitter Card meta tags
- `getSharingUrl(platform, url, title, description)` - Get platform-specific sharing URL
- `copyToClipboard(url)` - Copy URL to clipboard

**Supported Platforms**:
- Facebook
- Twitter
- LinkedIn
- WhatsApp
- Telegram

**Example Usage**:

```typescript
import {
  generateLiveTextMetadata,
  generateOpenGraphTags,
  generateTwitterCardTags,
  getSharingUrl
} from '@/lib/utils/socialMetadata';

// Generate metadata
const metadata = generateLiveTextMetadata(liveText, 'ro');

// For Next.js metadata
export async function generateMetadata({ params }) {
  const liveText = await getLiveText(params.slug);
  const metadata = generateLiveTextMetadata(liveText, params.locale);

  return {
    title: metadata.title,
    description: metadata.description,
    openGraph: generateOpenGraphTags(metadata),
    twitter: generateTwitterCardTags(metadata),
  };
}

// Get sharing URL
const facebookUrl = getSharingUrl('facebook', 'https://example.com/live/my-event');
const twitterUrl = getSharingUrl('twitter', 'https://example.com/live/my-event', 'Check this out!');
```

### Components

#### **SocialShareButtons**
**Location**: `components/social/SocialShareButtons.tsx`

Pre-built social sharing buttons component.

**Features**:
- Share to Facebook, Twitter, LinkedIn, WhatsApp, Telegram
- Copy link to clipboard
- Customizable size (small, medium, large)
- Two variants (icons, buttons)
- Success feedback for copy action

**Props**:
```typescript
interface SocialShareButtonsProps {
  url: string;              // URL to share
  title?: string;           // Optional title
  description?: string;     // Optional description
  showLabels?: boolean;     // Show text labels (default: false)
  size?: 'small' | 'medium' | 'large';  // Button size (default: 'medium')
  variant?: 'icons' | 'buttons';        // Style variant (default: 'icons')
}
```

**Usage Examples**:

```tsx
import { SocialShareButtons } from '@/components/social/SocialShareButtons';

// Basic usage (icons only)
<SocialShareButtons
  url="https://deschide.local/ro/live/breaking-news"
  title="Breaking News Live"
/>

// With labels and custom size
<SocialShareButtons
  url="https://deschide.local/ro/live/breaking-news"
  title="Breaking News Live"
  description="Follow the latest updates"
  showLabels={true}
  size="large"
  variant="buttons"
/>
```

**Visual Styles**:
- **Icons Variant**: Circular colored icons with hover animation
- **Buttons Variant**: Full buttons with icon + label
- **Color Coding**: Each platform has its brand color
  - Facebook: Blue (#1877F2)
  - Twitter: Sky Blue (#1DA1F2)
  - LinkedIn: Dark Blue (#0A66C2)
  - WhatsApp: Green (#25D366)
  - Telegram: Blue (#0088CC)
  - Copy Link: Gray (green when copied)

---

## Open Graph Metadata

Open Graph metadata ensures optimal appearance when sharing on social media.

### Required Meta Tags

```html
<meta property="og:title" content="Breaking News Live - Deschide News" />
<meta property="og:description" content="Follow live updates on..." />
<meta property="og:image" content="https://cdn.example.com/image.jpg" />
<meta property="og:url" content="https://deschide.local/ro/live/breaking-news" />
<meta property="og:type" content="article" />
<meta property="og:site_name" content="Deschide News" />
<meta property="og:locale" content="ro_RO" />
```

### Article-Specific Tags

```html
<meta property="article:published_time" content="2025-11-03T14:00:00Z" />
<meta property="article:modified_time" content="2025-11-03T16:30:00Z" />
<meta property="article:author" content="John Doe" />
<meta property="article:section" content="Breaking News" />
<meta property="article:tag" content="politics" />
```

### Twitter Card Tags

```html
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="Breaking News Live" />
<meta name="twitter:description" content="Follow live updates..." />
<meta name="twitter:image" content="https://cdn.example.com/image.jpg" />
<meta name="twitter:site" content="@deschide_news" />
<meta name="twitter:creator" content="@deschide_news" />
```

### Image Requirements

**Open Graph Image**:
- Recommended size: 1200x630px
- Aspect ratio: 1.91:1
- Format: JPG or PNG
- Max size: 8MB

**Twitter Card Image**:
- Recommended size: 1200x675px (16:9) or 1200x1200px (1:1)
- Format: JPG, PNG, WEBP, GIF
- Max size: 5MB

---

## Usage in Next.js Pages

### LiveText Page with Metadata

```tsx
// app/[locale]/live/[slug]/page.tsx
import { generateLiveTextMetadata, generateOpenGraphTags, generateTwitterCardTags } from '@/lib/utils/socialMetadata';
import { SocialShareButtons } from '@/components/social/SocialShareButtons';

export async function generateMetadata({ params }) {
  const liveText = await getLiveText(params.slug);
  const metadata = generateLiveTextMetadata(liveText, params.locale);

  return {
    title: metadata.title,
    description: metadata.description,
    openGraph: generateOpenGraphTags(metadata),
    twitter: generateTwitterCardTags(metadata),
  };
}

export default function LiveTextPage({ params }) {
  const liveText = await getLiveText(params.slug);
  const shareUrl = `${process.env.NEXT_PUBLIC_BASE_URL}/${params.locale}/live/${liveText.slug}`;

  return (
    <div>
      <h1>{liveText.title}</h1>
      <p>{liveText.description}</p>

      {/* Social Share Buttons */}
      <SocialShareButtons
        url={shareUrl}
        title={liveText.title}
        description={liveText.description}
      />

      {/* LiveText Content */}
      <LiveTextPostsList posts={liveText.posts} />
    </div>
  );
}
```

---

## Testing

### Backend Testing

**Test auto-posting** (requires valid credentials in `.env.local`):

```bash
# 1. Update LiveText status to LIVE
curl -X PUT http://127.0.0.1:8081/api/live_texts/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"status": "live"}'

# Check logs for social media posts
tail -f var/log/dev.log | grep "SocialMedia"

# 2. Create a key point post
curl -X POST http://127.0.0.1:8081/api/live_text_posts \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "liveText": "/api/live_texts/1",
    "author": "/api/users/1",
    "content": "BREAKING: Important update!",
    "isKeyPoint": true
  }'

# Check social media platforms for new posts
```

### Frontend Testing

**Test sharing buttons**:
```typescript
// Test each platform
const testUrl = 'https://deschide.local/ro/live/test-event';
console.log(getSharingUrl('facebook', testUrl));
console.log(getSharingUrl('twitter', testUrl, 'Test Event'));
console.log(getSharingUrl('linkedin', testUrl));
console.log(getSharingUrl('whatsapp', testUrl, 'Test Event'));
console.log(getSharingUrl('telegram', testUrl, 'Test Event'));
```

**Test Open Graph metadata** (use Facebook Debugger):
```
https://developers.facebook.com/tools/debug/
```

**Test Twitter Cards** (use Twitter Card Validator):
```
https://cards-dev.twitter.com/validator
```

---

## Best Practices

### 1. **Auto-Posting Guidelines**
- Only post key points to avoid spam
- Keep messages concise (respect platform limits)
- Always include URL for context
- Use emojis for visual appeal: 🔴 (LIVE), ⚡ (Key Point)

### 2. **Open Graph Images**
- Use high-quality images (1200x630px recommended)
- Include text overlay for context
- Ensure images load quickly (optimize file size)
- Provide fallback default image

### 3. **Error Handling**
- Social media posting failures should not block request
- Log errors for monitoring
- Implement retry logic for transient errors
- Consider message queue for reliability (RabbitMQ/Messenger)

### 4. **Rate Limiting**
- Be aware of platform rate limits:
  - Twitter: 300 tweets per 3-hour window
  - Facebook: varies by app
  - Telegram: 30 messages per second
- Implement rate limiting/throttling if needed

### 5. **Content Formatting**
- Strip HTML tags from content
- Truncate long content appropriately
- Preserve URLs in truncated content
- Handle special characters (emojis, unicode)

---

## Configuration Checklist

- [ ] Add social media credentials to `.env.local`
- [ ] Configure `SocialMediaService` in `services.yaml`
- [ ] Test Twitter API connection
- [ ] Test Facebook API connection
- [ ] Test Telegram Bot connection
- [ ] Set up Open Graph default image
- [ ] Update Twitter handle in `socialMetadata.ts`
- [ ] Test metadata with Facebook Debugger
- [ ] Test Twitter Cards
- [ ] Verify auto-posting on staging

---

## Troubleshooting

### Twitter API Errors

**401 Unauthorized**:
- Check API keys and access tokens
- Verify OAuth 2.0 Bearer Token is valid
- Ensure app has write permissions

**403 Forbidden**:
- Check if account/app is suspended
- Verify rate limits not exceeded

### Facebook API Errors

**190 - Access Token Invalid**:
- Regenerate Page Access Token
- Verify token has `pages_manage_posts` permission

**200 - Permissions Error**:
- Ensure app has necessary permissions
- Check if page access token (not user token)

### Telegram Bot Errors

**401 Unauthorized**:
- Verify Bot Token is correct
- Check bot is not deleted

**403 Forbidden - Bot was blocked**:
- Ensure bot is admin in channel
- Verify channel ID format (`@channel_name` or numeric ID)

---

## Future Enhancements

- [ ] Instagram integration (via Facebook Graph API)
- [ ] LinkedIn Company Page integration
- [ ] Message queue for reliable posting (RabbitMQ)
- [ ] Retry logic for failed posts
- [ ] Post scheduling/delay
- [ ] Analytics tracking (clicks, impressions)
- [ ] A/B testing for post content
- [ ] Multi-account support
- [ ] Content templates per platform

---

**Last Updated**: 2025-11-03
**Status**: Production Ready ✅
