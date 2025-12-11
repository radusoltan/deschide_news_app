# Short Link Redirect Route

## Overview

This route handles short URL redirects in the format `/s/{code}`.

## Location

- **Route**: `/s/[code]/route.ts`
- **URL Pattern**: `/s/{code}` (e.g., `/s/abc123`)
- **Method**: GET

## How It Works

1. User clicks a short link: `https://deschide.md/s/abc123`
2. Next.js frontend receives the request at `/s/[code]`
3. Frontend calls backend: `GET http://127.0.0.1:8081/s/abc123`
4. Backend returns 301 redirect with `Location` header
5. Frontend issues Next.js `redirect()` to the `Location` URL
6. Backend asynchronously tracks analytics (click count, user agent, IP, referrer)

## Performance

- **Target**: < 100ms redirect time
- **Optimization**: Backend handles analytics asynchronously via Symfony Messenger
- **No Caching**: Dynamic route ensures accurate click tracking

## Route Configuration

```typescript
export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';
```

- `dynamic = 'force-dynamic'` - Always server-side rendered (no caching)
- `runtime = 'nodejs'` - Use Node.js runtime (not Edge) for full fetch compatibility

## Backend Integration

### Backend Endpoint

**Controller**: `App\Controller\ShortLinkRedirectController`
**URL**: `GET /s/{code}`

### Response Flow

**Success (301 Redirect)**:
```http
HTTP/1.1 301 Moved Permanently
Location: https://deschide.md/ro/article/some-article-slug
Cache-Control: private, no-store
```

**Not Found (404)**:
```http
HTTP/1.1 404 Not Found
Content-Type: text/html; charset=UTF-8
```

### Analytics

The backend dispatches a `ShortLinkClickMessage` via Symfony Messenger with:
- `shortLinkId` - The short link database ID
- `ipAddress` - Client IP address
- `userAgent` - Client user agent string
- `referrer` - HTTP Referer header (where user came from)

This is processed asynchronously by `ShortLinkClickHandler` to ensure fast redirects.

## API Resource

Short links are also available via API Platform:

### Endpoints

- `GET /api/short_links` - List all short links (paginated)
- `GET /api/short_links/{id}` - Get single short link
- `GET /api/short_links/{id}/stats` - Get detailed statistics
- `POST /api/short_links` - Create new short link (admin only)
- `DELETE /api/short_links/{id}` - Delete short link (admin only)

### Filtering

```bash
# Find short link by code
curl "http://127.0.0.1:8081/api/short_links?code=abc123"

# Search by title
curl "http://127.0.0.1:8081/api/short_links?title=some%20article"

# Filter by article
curl "http://127.0.0.1:8081/api/short_links?article=5"
```

## Code Validation

Short codes must match the pattern: `^[a-zA-Z0-9]{6,10}$`

- **Length**: 6-10 characters
- **Allowed**: Letters (a-z, A-Z) and numbers (0-9)
- **Not Allowed**: Special characters, spaces, unicode

Invalid codes return `404 Not Found` immediately without querying the backend.

## Error Handling

| Scenario | HTTP Status | Response |
|----------|-------------|----------|
| Invalid code format | 404 | "Invalid short link code" |
| Code not found in DB | 404 | "Short link not found" |
| Backend unreachable | 503 | "Service temporarily unavailable" |
| Backend error | 500 | "Internal server error" |
| Valid redirect | 307/308 | Redirect to `originalUrl` |

## Testing

### Manual Testing

```bash
# Test with backend running
curl -I http://localhost:3005/s/testcode

# Expected (if code exists):
HTTP/1.1 307 Temporary Redirect
Location: https://deschide.md/ro/article/some-article-slug

# Expected (if code doesn't exist):
HTTP/1.1 404 Not Found
```

### Creating Test Short Links

Use the admin panel or API:

```bash
# Via API (requires authentication)
curl -X POST http://127.0.0.1:8081/api/short_links \
  -H "Content-Type: application/ld+json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "code": "abc123",
    "originalUrl": "https://deschide.md/ro/article/test-article",
    "title": "Test Article"
  }'
```

## Deployment Considerations

### Environment Variables

Ensure `NEXT_PUBLIC_API_URL` is set correctly:

- **Development**: `http://127.0.0.1:8081`
- **Staging**: `https://api-staging.deschide.md`
- **Production**: `https://api.deschide.md`

### DNS Configuration

Short links work at the root domain level:
- ✅ `https://deschide.md/s/abc123`
- ❌ `https://deschide.md/ro/s/abc123` (localized paths not used)

### CDN/Cache Considerations

- **Do NOT cache** `/s/*` routes at CDN level
- Set `Cache-Control: private, no-store` headers
- This ensures accurate analytics tracking

### Performance Monitoring

Monitor redirect latency:
```bash
# Measure redirect time
time curl -I http://localhost:3005/s/abc123
```

Target: < 100ms total time.

## Security

### Rate Limiting

Consider adding rate limiting to prevent abuse:
- **Backend**: Already has rate limiting configured
- **CDN**: Configure rate limits for `/s/*` paths (e.g., 100 req/min per IP)

### Validation

- Code format validated before backend call
- SQL injection protected (parameterized queries in Doctrine)
- XSS protected (no user input reflected in response)

### Analytics Privacy

- IP addresses are logged for analytics
- User agents are stored
- Referrer URLs are tracked
- Comply with GDPR/privacy regulations

## Known Issues

### Backend Controller Naming Conflict

**Issue**: `ShortLinkRedirectController::redirect()` conflicts with `AbstractController::redirect()`

**Error**:
```
Declaration of ShortLinkRedirectController::redirect(...) must be compatible with AbstractController::redirect(...)
```

**Fix**: Rename controller method to `handleRedirect()` or `redirectToUrl()`:

```php
#[Route('/s/{code}', name: 'short_link_redirect', methods: ['GET'])]
public function handleRedirect(string $code, Request $request): Response
{
    // ... existing code
}
```

## Future Enhancements

1. **QR Code Generation**: Generate QR codes for short links
2. **Preview Endpoint**: `/s/{code}/preview` to show link details before redirecting
3. **Expiration**: Add expiry dates for temporary short links
4. **Password Protection**: Require password for sensitive links
5. **Custom Aliases**: Allow custom short codes (e.g., `/s/breaking-news`)
6. **A/B Testing**: Multiple destination URLs with split traffic
7. **Geographic Routing**: Redirect based on user location
8. **Time-based Routing**: Different URLs based on time/date

## Related Files

- **Backend Controller**: `apps/backend/src/Controller/ShortLinkRedirectController.php`
- **Backend Entity**: `apps/backend/src/Entity/ShortLink.php`
- **Backend Message**: `apps/backend/src/Message/ShortLinkClickMessage.php`
- **Backend Handler**: `apps/backend/src/MessageHandler/ShortLinkClickHandler.php`
- **Admin Panel**: `apps/frontend/app/[locale]/admin/short-links/page.tsx`

## Documentation

- **Planning**: `/var/www/deschide_news_app/docs/LINK_SHORTENER_PLAN.md`
- **Integration**: See main `CLAUDE.md` for full system architecture
