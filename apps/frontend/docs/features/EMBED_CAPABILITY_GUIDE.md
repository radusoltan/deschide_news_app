# Embed Capability Guide

This guide explains how to embed Deschide LiveText on external websites using iframe, JavaScript SDK, or API integration.

## Overview

The embed system provides three ways to integrate LiveText:

1. **Iframe Embed** - Simple HTML iframe (no JavaScript required)
2. **JavaScript SDK** - Easy-to-use SDK with auto-embedding
3. **Embed API** - RESTful API for custom integrations

**Features**:
- ✅ Real-time updates via Mercure
- ✅ Light/Dark theme support
- ✅ Fully responsive design
- ✅ Sport match score display
- ✅ No tracking/analytics (privacy-friendly)
- ✅ CORS-enabled for all domains
- ✅ Minimal footprint (optimized for performance)

---

## Method 1: Iframe Embed

The simplest way to embed LiveText. Just copy and paste the iframe code.

### Basic Usage

```html
<iframe
  src="https://deschide.local/ro/embed/live/breaking-news?theme=light"
  width="100%"
  height="600"
  frameborder="0"
  allowfullscreen
></iframe>
```

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `theme` | string | `light` | Theme: `light` or `dark` |

### Getting Iframe Code via API

```bash
curl "https://api.deschide.local/api/embed/code/123?locale=ro&theme=light&width=100%&height=600px"
```

**Response**:
```json
{
  "liveTextId": 123,
  "slug": "breaking-news",
  "embedUrl": "https://deschide.local/ro/embed/live/breaking-news?theme=light",
  "iframeCode": "<iframe src=\"https://deschide.local/ro/embed/live/breaking-news?theme=light\" width=\"100%\" height=\"600px\" frameborder=\"0\" allowfullscreen></iframe>",
  "javascriptCode": "...",
  "options": {
    "locale": "ro",
    "width": "100%",
    "height": "600px",
    "theme": "light"
  }
}
```

---

## Method 2: JavaScript SDK

The easiest way with automatic embedding and additional features.

### Installation

Include the SDK script in your HTML:

```html
<script src="https://deschide.local/embed.js"></script>
```

### Basic Usage

**Method A: Programmatic Embedding**

```html
<!-- Container -->
<div id="deschide-livetext-123"></div>

<!-- SDK Script -->
<script src="https://deschide.local/embed.js"></script>

<!-- Embed LiveText -->
<script>
  DeschideLiveText.embed({
    containerId: "deschide-livetext-123",
    liveTextId: 123,
    locale: "ro",
    theme: "light",
    width: "100%",
    height: "600px"
  });
</script>
```

**Method B: Auto-Embedding with Data Attributes**

```html
<!-- SDK Script (loads first) -->
<script src="https://deschide.local/embed.js"></script>

<!-- Container with data attributes (auto-embeds on page load) -->
<div
  data-deschide-livetext="123"
  data-locale="ro"
  data-theme="light"
  data-width="100%"
  data-height="600px"
></div>
```

**Method C: Embedding by Slug**

```html
<div id="my-livetext"></div>

<script src="https://deschide.local/embed.js"></script>
<script>
  DeschideLiveText.embed({
    containerId: "my-livetext",
    slug: "breaking-news", // Use slug instead of ID
    locale: "ro",
    theme: "dark"
  });
</script>
```

### SDK Options

```typescript
DeschideLiveText.embed({
  // Required
  containerId: string;        // Container element ID

  // Required (one of these)
  liveTextId?: number;        // LiveText ID
  slug?: string;              // LiveText slug

  // Optional
  locale?: string;            // Locale: 'ro', 'en', 'ru' (default: 'ro')
  theme?: string;             // Theme: 'light', 'dark' (default: 'light')
  width?: string;             // Width CSS value (default: '100%')
  height?: string;            // Height CSS value (default: '600px')
  autoResize?: boolean;       // Auto-resize iframe (default: true)
  onLoad?: Function;          // Callback when loaded
  onError?: Function;         // Callback on error
});
```

### SDK Methods

#### `DeschideLiveText.embed(options)`
Embed a LiveText in a container.

```javascript
DeschideLiveText.embed({
  containerId: "my-container",
  liveTextId: 123,
  locale: "ro",
  theme: "light",
  onLoad: function(iframe) {
    console.log("LiveText loaded!", iframe);
  },
  onError: function(error) {
    console.error("Failed to load:", error);
  }
});
```

#### `DeschideLiveText.getEmbedCode(options)`
Get embed code for a LiveText.

```javascript
DeschideLiveText.getEmbedCode({
  liveTextId: 123,
  locale: "ro",
  theme: "light"
})
.then(function(data) {
  console.log("Iframe code:", data.iframeCode);
  console.log("JS code:", data.javascriptCode);
})
.catch(function(error) {
  console.error("Error:", error);
});
```

#### `DeschideLiveText.list(options)`
Get list of embeddable LiveTexts.

```javascript
DeschideLiveText.list({
  status: "live",  // Optional filter
  limit: 20        // Optional limit
})
.then(function(liveTexts) {
  console.log("Live texts:", liveTexts);
})
.catch(function(error) {
  console.error("Error:", error);
});
```

#### `DeschideLiveText.version`
Get SDK version.

```javascript
console.log("SDK Version:", DeschideLiveText.version); // "1.0.0"
```

---

## Method 3: Embed API

RESTful API for custom integrations.

### Base URL

```
https://api.deschide.local/api/embed
```

### Endpoints

#### 1. Get LiveText by ID

**GET** `/api/embed/live-text/{id}`

Get LiveText data for embedding.

**Query Parameters**:
- `locale` (optional): Locale (ro, en, ru). Default: ro
- `limit` (optional): Number of posts to return. Default: 50
- `offset` (optional): Pagination offset. Default: 0

**Example**:
```bash
curl "https://api.deschide.local/api/embed/live-text/123?locale=ro&limit=50"
```

**Response**:
```json
{
  "id": 123,
  "title": "Breaking News",
  "slug": "breaking-news",
  "description": "Follow live updates...",
  "status": "live",
  "startTime": "2025-11-03T14:00:00+00:00",
  "endTime": null,
  "locale": "ro",
  "category": {
    "id": 5,
    "name": "Politics"
  },
  "author": {
    "id": 1,
    "name": "John Doe"
  },
  "posts": [
    {
      "id": 456,
      "content": "Important update...",
      "contentHtml": "<p>Important update...</p>",
      "isKeyPoint": true,
      "publishedAt": "2025-11-03T14:30:00+00:00",
      "author": {
        "id": 2,
        "name": "Jane Smith"
      }
    }
  ],
  "pagination": {
    "limit": 50,
    "offset": 0,
    "hasMore": false
  },
  "embedInfo": {
    "version": "1.0",
    "sourceUrl": "https://deschide.local/ro/live/breaking-news",
    "mercureTopic": "deschide_news/live_text/123"
  },
  "sportMatch": {
    "id": 10,
    "sportType": "football",
    "homeTeam": "Team A",
    "awayTeam": "Team B",
    "homeScore": 2,
    "awayScore": 1,
    "status": "live",
    "currentMinute": 67
  }
}
```

#### 2. Get LiveText by Slug

**GET** `/api/embed/live-text/slug/{slug}`

Get LiveText data by slug.

**Example**:
```bash
curl "https://api.deschide.local/api/embed/live-text/slug/breaking-news?locale=ro"
```

#### 3. Get Embed Code

**GET** `/api/embed/code/{id}`

Get iframe and JavaScript embed code.

**Query Parameters**:
- `locale` (optional): Locale. Default: ro
- `theme` (optional): Theme (light/dark). Default: light
- `width` (optional): Width. Default: 100%
- `height` (optional): Height. Default: 600px

**Example**:
```bash
curl "https://api.deschide.local/api/embed/code/123?locale=ro&theme=light&width=100%&height=600px"
```

**Response**:
```json
{
  "liveTextId": 123,
  "slug": "breaking-news",
  "embedUrl": "https://deschide.local/ro/embed/live/breaking-news?theme=light",
  "iframeCode": "<iframe src=\"...\" width=\"100%\" height=\"600px\" frameborder=\"0\" allowfullscreen></iframe>",
  "javascriptCode": "<div id=\"deschide-livetext-123\"></div>\n<script src=\"...\"></script>\n<script>...</script>",
  "options": {
    "locale": "ro",
    "width": "100%",
    "height": "600px",
    "theme": "light"
  }
}
```

#### 4. List Embeddable LiveTexts

**GET** `/api/embed/list`

Get list of embeddable LiveTexts.

**Query Parameters**:
- `status` (optional): Filter by status (live, ended, etc.)
- `limit` (optional): Limit results. Default: 20

**Example**:
```bash
curl "https://api.deschide.local/api/embed/list?status=live&limit=20"
```

**Response**:
```json
{
  "liveTexts": [
    {
      "id": 123,
      "title": "Breaking News",
      "slug": "breaking-news",
      "status": "live",
      "startTime": "2025-11-03T14:00:00+00:00",
      "embedUrl": "https://deschide.local/ro/embed/live/breaking-news"
    }
  ],
  "count": 1
}
```

---

## Features

### Real-Time Updates

The embed automatically receives real-time updates via Mercure SSE (Server-Sent Events). No configuration needed - it just works!

**Supported Events**:
- New posts
- Updated posts
- Deleted posts
- Status changes (live → ended)
- Sport match score updates

### Sport Match Display

If the LiveText has an associated sport match, a scoreboard is automatically displayed showing:
- Team names
- Current score
- Match status
- Current minute (for live matches)

### Theme Support

Two themes available:
- **Light theme** (default) - White background, dark text
- **Dark theme** - Dark background, light text

Set via URL parameter: `?theme=dark`

### Responsive Design

The embed is fully responsive and adapts to:
- Desktop (1200px+)
- Tablet (768px - 1199px)
- Mobile (< 768px)

### Privacy-Friendly

The embed page:
- ❌ No tracking scripts
- ❌ No third-party analytics
- ❌ No cookies
- ✅ Minimal footprint
- ✅ Fast loading

---

## Advanced Usage

### Custom Styling

The embed uses CSS custom properties for theming. External sites can override styles:

```html
<style>
  iframe {
    border: 2px solid #3b82f6;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  }
</style>

<iframe src="..." width="100%" height="600"></iframe>
```

### Multiple Embeds on Same Page

You can embed multiple LiveTexts on the same page:

```html
<script src="https://deschide.local/embed.js"></script>

<!-- First LiveText -->
<div
  data-deschide-livetext="123"
  data-locale="ro"
></div>

<!-- Second LiveText -->
<div
  data-deschide-livetext="456"
  data-locale="en"
  data-theme="dark"
></div>
```

### Lazy Loading

For better performance, use lazy loading for iframes:

```html
<iframe
  src="https://deschide.local/ro/embed/live/breaking-news"
  width="100%"
  height="600"
  loading="lazy"
  frameborder="0"
></iframe>
```

### Access Control (Optional)

If you want to restrict embedding to specific domains, configure CORS in backend:

```yaml
# config/packages/nelmio_cors.yaml
nelmio_cors:
    paths:
        '^/api/embed':
            allow_origin: ['https://trusted-site.com', 'https://another-site.com']
```

---

## WordPress Plugin (Optional)

Create a WordPress shortcode for easy embedding:

```php
<?php
// Add to functions.php

function deschide_livetext_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => '',
        'slug' => '',
        'locale' => 'ro',
        'theme' => 'light',
        'width' => '100%',
        'height' => '600px',
    ], $atts);

    if (!$atts['id'] && !$atts['slug']) {
        return '<p>Error: LiveText ID or slug required</p>';
    }

    $id = 'deschide-livetext-' . ($atts['id'] ?: $atts['slug']);

    return sprintf(
        '<div id="%s"></div>
        <script src="https://deschide.local/embed.js"></script>
        <script>
            DeschideLiveText.embed({
                containerId: "%s",
                %s,
                locale: "%s",
                theme: "%s",
                width: "%s",
                height: "%s"
            });
        </script>',
        $id,
        $id,
        $atts['id'] ? 'liveTextId: ' . $atts['id'] : 'slug: "' . $atts['slug'] . '"',
        $atts['locale'],
        $atts['theme'],
        $atts['width'],
        $atts['height']
    );
}

add_shortcode('deschide_livetext', 'deschide_livetext_shortcode');
?>
```

**Usage in WordPress**:
```
[deschide_livetext id="123" locale="ro" theme="light"]
```

or

```
[deschide_livetext slug="breaking-news" locale="en" theme="dark"]
```

---

## Testing

### Test Iframe

```html
<!DOCTYPE html>
<html>
<head>
    <title>Embed Test</title>
</head>
<body>
    <h1>LiveText Embed Test</h1>

    <iframe
        src="http://localhost:3005/ro/embed/live/test-event?theme=light"
        width="100%"
        height="600"
        frameborder="0"
        allowfullscreen
    ></iframe>
</body>
</html>
```

### Test JavaScript SDK

```html
<!DOCTYPE html>
<html>
<head>
    <title>SDK Test</title>
</head>
<body>
    <h1>LiveText SDK Test</h1>

    <div id="livetext-container"></div>

    <script src="http://localhost:3005/embed.js"></script>
    <script>
        DeschideLiveText.embed({
            containerId: "livetext-container",
            liveTextId: 1,
            locale: "ro",
            theme: "light",
            onLoad: function(iframe) {
                console.log("Loaded!", iframe);
            },
            onError: function(error) {
                console.error("Error:", error);
            }
        });
    </script>
</body>
</html>
```

### Test API

```bash
# Get LiveText data
curl http://127.0.0.1:8081/api/embed/live-text/1?locale=ro

# Get embed code
curl http://127.0.0.1:8081/api/embed/code/1?locale=ro&theme=light

# List embeddable LiveTexts
curl http://127.0.0.1:8081/api/embed/list?status=live
```

---

## Best Practices

### 1. **Set Appropriate Height**
Match iframe height to expected content:
- Short updates: 400px
- Medium coverage: 600px
- Full coverage: 800px+

### 2. **Use Slug Instead of ID**
Slugs are more stable and human-readable:
```javascript
// Good
DeschideLiveText.embed({ slug: "breaking-news", ... });

// Less good
DeschideLiveText.embed({ liveTextId: 123, ... });
```

### 3. **Lazy Load Off-Screen Embeds**
For pages with multiple embeds, use lazy loading:
```html
<iframe src="..." loading="lazy"></iframe>
```

### 4. **Handle Errors Gracefully**
```javascript
DeschideLiveText.embed({
    containerId: "my-container",
    liveTextId: 123,
    onError: function(error) {
        document.getElementById("my-container").innerHTML =
            '<p>Failed to load LiveText. <a href="https://deschide.local/live/...">View on Deschide</a></p>';
    }
});
```

### 5. **Test on Different Devices**
Ensure embed works on:
- Desktop browsers (Chrome, Firefox, Safari, Edge)
- Mobile browsers (iOS Safari, Android Chrome)
- Different screen sizes

---

## Troubleshooting

### Iframe Not Loading

**Symptom**: Blank iframe or "Failed to load" error

**Solutions**:
1. Check if LiveText exists and is accessible
2. Verify CORS configuration allows your domain
3. Check browser console for errors
4. Verify embed URL is correct

### CORS Errors

**Symptom**: Console error: "blocked by CORS policy"

**Solution**: Update backend CORS configuration:
```yaml
# config/packages/nelmio_cors.yaml
nelmio_cors:
    paths:
        '^/api/embed':
            allow_origin: ['*']  # or specific domains
```

### SDK Not Loading

**Symptom**: `DeschideLiveText is not defined`

**Solutions**:
1. Ensure SDK script is loaded before usage
2. Check if script URL is correct
3. Verify no JavaScript errors in console

### Real-Time Updates Not Working

**Symptom**: New posts don't appear automatically

**Solutions**:
1. Check Mercure hub is running
2. Verify browser supports EventSource
3. Check for CORS issues with Mercure
4. Test Mercure connection manually

---

## Future Enhancements

- [ ] Auto-resize iframe to content height
- [ ] Customizable CSS themes
- [ ] Post-message API for communication with parent page
- [ ] Analytics events (page views, interactions)
- [ ] A/B testing different embed styles
- [ ] Pre-built plugins for popular CMS (Drupal, Joomla, etc.)
- [ ] React/Vue/Angular components
- [ ] AMP (Accelerated Mobile Pages) support

---

**Last Updated**: 2025-11-03
**Status**: Production Ready ✅
