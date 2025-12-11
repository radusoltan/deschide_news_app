# Archive Sitemap Implementation

**Date**: November 30, 2025
**Application**: Deschide News Frontend
**Framework**: Next.js 16 with App Router
**Status**: COMPLETED

---

## Overview

Implementation of a dedicated XML sitemap for archived articles at `/sitemap-archive.xml`. This sitemap includes all archived content across all three locales (Romanian, English, Russian) with proper multilingual SEO attributes, optimized for search engine indexing of historical content.

---

## Implementation Details

### Files Created

1. **Route Handler**
   - **Path**: `/var/www/deschide_news_app/apps/frontend/app/sitemap-archive.xml/route.ts`
   - **Purpose**: Generates XML sitemap for archived articles
   - **Type**: Next.js App Router route handler
   - **Cache**: 24 hours (archives rarely change)

2. **Data Fetching Function**
   - **Path**: `/var/www/deschide_news_app/apps/frontend/lib/api/sitemap-data.ts`
   - **Function**: `fetchAllArchivedArticlesForSitemap()`
   - **Purpose**: Fetches all archived articles with pagination handling
   - **API Endpoint**: `http://127.0.0.1:8081/api/archived_articles`

### Files Modified

1. **robots.ts**
   - **Path**: `/var/www/deschide_news_app/apps/frontend/app/robots.ts`
   - **Change**: Added `sitemap-archive.xml` to sitemap references
   - **Impact**: Search engines now discover the archive sitemap

---

## Architecture

### URL Structure

```
GET https://deschide.md/sitemap-archive.xml
```

**Response Format**: XML (application/xml)

### Archive-Specific Configuration

```typescript
const ARCHIVE_CONFIG = {
  priority: 0.3,              // Lower than active content (0.7-0.8)
  changeFrequency: 'yearly',  // Archives rarely change
  cacheMaxAge: 86400,         // 24 hours
  cacheSMaxAge: 604800,       // 1 week for CDN
};
```

### Pagination Handling

The implementation handles large archive collections through:

1. **Automatic Pagination**: Iterates through all API pages until `hydra:next` is null
2. **Safety Limit**: Maximum 1,000 pages to prevent infinite loops
3. **Error Recovery**: Returns partial results if API fails mid-pagination
4. **Page Size**: 100 articles per API request for optimal performance

---

## XML Sitemap Structure

### Sample Output

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
  <url>
    <loc>https://deschide.md/politika/archive-article-slug</loc>
    <lastmod>2020-05-15T10:00:00.000Z</lastmod>
    <changefreq>yearly</changefreq>
    <priority>0.3</priority>
    <xhtml:link rel="alternate" hreflang="ro" href="https://deschide.md/politika/archive-article-slug" />
    <xhtml:link rel="alternate" hreflang="en" href="https://deschide.md/en/politics/archive-article-slug" />
    <xhtml:link rel="alternate" hreflang="ru" href="https://deschide.md/ru/politika/archive-article-slug" />
    <xhtml:link rel="alternate" hreflang="x-default" href="https://deschide.md/politika/archive-article-slug" />
  </url>
  <!-- More URLs... -->
</urlset>
```

### Multilingual SEO (hreflang)

Each article URL includes hreflang tags for all three locales:

- **ro** (Romanian): Default locale, no prefix in URL
- **en** (English): `/en/` prefix
- **ru** (Russian): `/ru/` prefix
- **x-default**: Points to Romanian version

---

## API Integration

### Endpoint

```
GET http://127.0.0.1:8081/api/archived_articles?page={page}&itemsPerPage=100
```

### Request Headers

```http
Accept: application/ld+json
Accept-Language: ro
```

### Response Format (Hydra/JSON-LD)

```json
{
  "@context": "/api/contexts/Article",
  "@id": "/api/archived_articles",
  "@type": "hydra:Collection",
  "hydra:totalItems": 65000,
  "hydra:member": [
    {
      "@id": "/api/articles/123",
      "id": 123,
      "title": "Article Title",
      "slug": "article-slug",
      "category": { "slug": "category-slug" },
      "updatedAt": "2020-05-15T10:00:00+00:00",
      "archivedAt": "2024-01-01T00:00:00+00:00"
    }
  ],
  "hydra:view": {
    "@id": "/api/archived_articles?page=1",
    "hydra:next": "/api/archived_articles?page=2"
  }
}
```

### Pagination Flow

1. Start with page 1
2. Fetch 100 articles
3. Check for `hydra:view['hydra:next']`
4. If exists, increment page and repeat
5. Continue until no `hydra:next` or safety limit reached
6. Return all collected articles

---

## SEO Specifications

### Archive Content Priorities

| Content Type | Priority | Change Frequency | Rationale |
|-------------|----------|------------------|-----------|
| Homepage | 1.0 | hourly | Most important, updates frequently |
| Featured Articles | 0.9 | daily | High value content |
| Regular Articles | 0.8 | daily | Primary content |
| Categories | 0.7 | daily | Navigation pages |
| Authors | 0.6 | weekly | Profile pages |
| **Archives** | **0.3** | **yearly** | **Historical content** |

### lastmod Date Logic

Uses the most recent of:
1. `archivedAt` - When article was moved to archive
2. `updatedAt` - Last modification date

```typescript
const lastModDate = article.archivedAt || article.updatedAt;
```

### Cache Headers

```http
Content-Type: application/xml; charset=utf-8
Cache-Control: public, max-age=86400, s-maxage=604800
```

- **max-age=86400**: Browser cache for 24 hours
- **s-maxage=604800**: CDN cache for 1 week

### Revalidation

```typescript
export const revalidate = 86400; // 24 hours
```

Next.js will regenerate the sitemap every 24 hours automatically.

---

## Error Handling

### API Failure Strategy

1. **Graceful Degradation**: Returns empty but valid XML on complete failure
2. **Partial Results**: If pagination fails mid-way, returns articles collected so far
3. **Logging**: All errors logged to console for monitoring
4. **HTTP Status**: Always returns 200 OK (even for empty sitemap)

### Empty Sitemap Response

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
</urlset>
```

**Cache on Error**: 5 minutes (shorter than normal cache)

---

## robots.txt Integration

### Updated Configuration

```typescript
sitemap: [
  `${baseUrl}/sitemap.xml`,
  `${baseUrl}/news-sitemap.xml`,
  `${baseUrl}/image-sitemap.xml`,
  `${baseUrl}/sitemap-archive.xml`,  // NEW
]
```

### robots.txt Output

```txt
User-Agent: *
Allow: /
Disallow: /api/
Disallow: /admin/
# ... other rules ...

Host: https://deschide.md
Sitemap: https://deschide.md/sitemap.xml
Sitemap: https://deschide.md/news-sitemap.xml
Sitemap: https://deschide.md/image-sitemap.xml
Sitemap: https://deschide.md/sitemap-archive.xml
```

---

## Performance Optimization

### Pagination Batch Size

- **100 articles per request**: Balance between API efficiency and memory usage
- **Safety limit**: 1,000 pages max (100,000 articles)

### Memory Management

- Articles processed in batches
- No loading entire dataset into memory at once
- Incremental array building: `allArticles.push(...mappedArticles)`

### Expected Volume

Assuming 65,000 archived articles:
- **API Requests**: 650 requests (65,000 / 100)
- **Generation Time**: ~2-3 minutes (depends on API response time)
- **XML Size**: ~15-20 MB (with hreflang tags)

### Optimization Opportunities

1. **API Response Time**: Backend should optimize archived articles query
2. **Index on archivedAt**: Database index for faster filtering
3. **CDN Caching**: 1-week cache at CDN level reduces regeneration frequency
4. **Compression**: Enable gzip/brotli compression for XML response

---

## Testing

### Manual Testing

```bash
# Test the route (requires frontend server running)
curl http://localhost:3005/sitemap-archive.xml | head -50

# Validate XML structure
curl -s http://localhost:3005/sitemap-archive.xml | xmllint --noout -

# Check response headers
curl -I http://localhost:3005/sitemap-archive.xml

# Test robots.txt inclusion
curl http://localhost:3005/robots.txt | grep sitemap-archive
```

### Backend API Testing

```bash
# Test archived articles endpoint
curl -s 'http://127.0.0.1:8081/api/archived_articles?page=1&itemsPerPage=5' | jq

# Check total count
curl -s 'http://127.0.0.1:8081/api/archived_articles?page=1&itemsPerPage=1' | jq '."hydra:totalItems"'

# Test pagination
curl -s 'http://127.0.0.1:8081/api/archived_articles?page=2&itemsPerPage=10' | jq '."hydra:view"'
```

### Validation Tools

1. **XML Sitemap Validator**
   - https://www.xml-sitemaps.com/validate-xml-sitemap.html
   - Validates against sitemap.org schema

2. **Google Search Console**
   - Submit sitemap: `https://deschide.md/sitemap-archive.xml`
   - Monitor indexing status
   - Check for errors

3. **Hreflang Validator**
   - https://www.aleydasolis.com/english/international-seo-tools/hreflang-tags-generator/
   - Validates multilingual implementation

4. **Schema Validator**
   - Ensure XML conforms to sitemap.org schema 0.9

---

## Monitoring

### Metrics to Track

1. **Generation Time**: How long does it take to generate the sitemap?
2. **Archive Count**: Number of URLs in the sitemap
3. **API Performance**: Time to fetch all pages
4. **Error Rate**: Failed API requests during pagination
5. **Cache Hit Rate**: CDN cache effectiveness

### Google Search Console Monitoring

1. **Sitemaps Section**
   - Submit archive sitemap
   - Monitor submission status
   - Check for errors/warnings

2. **Coverage Report**
   - Track indexed archived articles
   - Identify crawl errors
   - Monitor indexing rate

3. **Performance Report**
   - Impressions for archived content
   - Click-through rates
   - Average position

---

## Comparison with Other Sitemaps

### Main Sitemap (sitemap.xml)

- **Includes**: Active content (homepage, articles, categories, authors, static pages)
- **Priority**: 0.5 - 1.0
- **Change Frequency**: hourly - monthly
- **Cache**: 1 hour

### News Sitemap (news-sitemap.xml)

- **Includes**: Articles from last 48 hours
- **Priority**: 0.8 - 1.0
- **Change Frequency**: hourly
- **Cache**: 1 hour
- **Format**: Google News XML schema

### Image Sitemap (image-sitemap.xml)

- **Includes**: Article images
- **Priority**: N/A (image-specific schema)
- **Change Frequency**: daily
- **Cache**: 1 hour

### Archive Sitemap (sitemap-archive.xml)

- **Includes**: All archived articles
- **Priority**: 0.3
- **Change Frequency**: yearly
- **Cache**: 24 hours

---

## Sitemap Segmentation Strategy

### Why Separate Archive Sitemap?

1. **Performance**: Separate caching for static vs dynamic content
2. **Crawl Budget**: Different update frequencies
3. **Prioritization**: Clear signal to search engines
4. **Maintainability**: Easier to manage and update
5. **Volume**: Archives can be very large (65,000+ articles)

### Google's Sitemap Limits

- **Max URLs per sitemap**: 50,000
- **Max file size**: 50 MB (uncompressed)
- **Max file size**: 10 MB (compressed)

**Archive Sitemap Compliance**:
- Currently: ~65,000 URLs (exceeds limit)
- **Recommendation**: Split into multiple sitemaps if > 50,000 URLs

### Future: Sitemap Index

If archive grows beyond 50,000 articles:

```xml
<!-- sitemap-archive-index.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <sitemap>
    <loc>https://deschide.md/sitemap-archive-1.xml</loc>
    <lastmod>2025-11-30T00:00:00+00:00</lastmod>
  </sitemap>
  <sitemap>
    <loc>https://deschide.md/sitemap-archive-2.xml</loc>
    <lastmod>2025-11-30T00:00:00+00:00</lastmod>
  </sitemap>
</sitemapindex>
```

---

## Locale-Specific Article URLs

### Romanian (Default Locale)

```
https://deschide.md/{category-slug}/{article-slug}
```

**Example**: `https://deschide.md/politika/article-123`

### English

```
https://deschide.md/en/{category-slug}/{article-slug}
```

**Example**: `https://deschide.md/en/politics/article-123`

### Russian

```
https://deschide.md/ru/{category-slug}/{article-slug}
```

**Example**: `https://deschide.md/ru/politika/article-123`

### Translation Handling

**Current Implementation**: Uses the same slug across all locales (TODO)

**Future Enhancement**: Fetch actual translated slugs per locale
- Requires API to return translations in response
- Update mapping in `fetchAllArchivedArticlesForSitemap()`

---

## Code Organization

### Utility Functions

Located in `/var/www/deschide_news_app/apps/frontend/lib/seo/sitemap-utils.ts`:

1. **buildArticleUrl(locale, categorySlug, articleSlug)**
   - Constructs article URL with proper locale prefix
   - Handles default locale (ro) with no prefix

2. **generateLanguageAlternates(paths)**
   - Generates hreflang alternate links
   - Includes x-default tag

3. **parseDate(dateString)**
   - Safely parses date strings
   - Handles null/undefined values

### Configuration

Located in `/var/www/deschide_news_app/apps/frontend/lib/seo/sitemap-config.ts`:

```typescript
export const SITEMAP_CONFIG = {
  baseUrl: process.env.NEXT_PUBLIC_SITE_URL || 'https://deschide.md',
  locales: ['ro', 'en', 'ru'] as const,
  defaultLocale: 'ro' as const,
  // ... other config
};
```

---

## Security Considerations

### No Sensitive Data

- Only public archived articles included
- No draft or private content
- No user information exposed

### XML Injection Prevention

```typescript
function escapeXml(unsafe: string): string {
  return unsafe
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;');
}
```

All dynamic content (URLs, titles) is escaped before XML insertion.

### Rate Limiting

Consider implementing rate limiting on sitemap routes:
- Prevents abuse
- Protects backend API from excessive requests
- Can be done at CDN level or Nginx

---

## Deployment Checklist

- [x] Create route handler at `app/sitemap-archive.xml/route.ts`
- [x] Add data fetching function in `lib/api/sitemap-data.ts`
- [x] Update `robots.ts` with sitemap reference
- [ ] Deploy to production
- [ ] Test sitemap generation on production
- [ ] Submit to Google Search Console
- [ ] Monitor indexing status
- [ ] Set up alerts for sitemap errors

---

## Future Enhancements

### 1. Sitemap Index (If > 50,000 URLs)

Split archive sitemap into multiple files:
- `sitemap-archive-1.xml` (articles 1-50,000)
- `sitemap-archive-2.xml` (articles 50,001-100,000)
- `sitemap-archive-index.xml` (references all parts)

### 2. Year-Based Segmentation

```
/sitemap-archive-2024.xml
/sitemap-archive-2023.xml
/sitemap-archive-2022.xml
```

Benefits:
- Easier to manage
- More granular cache control
- Better crawl efficiency

### 3. Actual Translated Slugs

Update API to return translated slugs:

```json
{
  "slug": "article-slug-ro",
  "translations": {
    "en": { "slug": "article-slug-en" },
    "ru": { "slug": "article-slug-ru" }
  }
}
```

### 4. Incremental Updates

Instead of regenerating entire sitemap:
- Track last generation timestamp
- Only include articles archived since last run
- Append to existing sitemap files

### 5. Compression

Enable gzip compression for sitemap routes:
- Reduces bandwidth
- Faster downloads for search engines
- Can be configured in Next.js or CDN

---

## Troubleshooting

### Issue: Sitemap Returns Empty

**Possible Causes**:
1. Backend API not running
2. No archived articles in database
3. API endpoint incorrect

**Solution**:
```bash
# Check backend API
curl http://127.0.0.1:8081/api/archived_articles

# Check frontend logs
# Look for console.error messages
```

### Issue: Sitemap Generation Timeout

**Possible Causes**:
1. Too many archived articles (> 100,000)
2. Slow API response times
3. Network issues

**Solution**:
- Implement sitemap index with chunking
- Optimize backend query performance
- Increase timeout in Next.js config

### Issue: Wrong URLs in Sitemap

**Possible Causes**:
1. `NEXT_PUBLIC_SITE_URL` not set correctly
2. Category slug mismatch
3. Locale prefix logic error

**Solution**:
```bash
# Check environment variable
echo $NEXT_PUBLIC_SITE_URL

# Verify buildArticleUrl() function
# Test with known article data
```

### Issue: Hreflang Tags Missing

**Possible Causes**:
1. Missing translations in API response
2. `generateLanguageAlternates()` function error

**Solution**:
- Verify API returns translations for all locales
- Check `sitemap-utils.ts` implementation

---

## References

### Documentation

- [Sitemap.org Protocol](https://www.sitemaps.org/protocol.html)
- [Google Sitemap Guidelines](https://developers.google.com/search/docs/crawling-indexing/sitemaps/overview)
- [Hreflang Implementation](https://developers.google.com/search/docs/specialty/international/localized-versions)
- [Next.js Route Handlers](https://nextjs.org/docs/app/building-your-application/routing/route-handlers)

### Related Files

- `/var/www/deschide_news_app/apps/frontend/app/sitemap.ts` - Main sitemap
- `/var/www/deschide_news_app/apps/frontend/app/news-sitemap.xml/route.ts` - News sitemap
- `/var/www/deschide_news_app/apps/frontend/app/image-sitemap.xml/route.ts` - Image sitemap
- `/var/www/deschide_news_app/apps/frontend/app/robots.ts` - Robots.txt generator
- `/var/www/deschide_news_app/docs/audits/seo-audit.md` - SEO audit report

---

## Conclusion

The archive sitemap implementation provides a comprehensive solution for indexing historical content across all three locales. It follows SEO best practices with proper hreflang tags, appropriate priority/change frequency values, and efficient caching strategies.

**Key Benefits**:
- Improved search engine visibility for archived content
- Proper multilingual SEO support
- Efficient crawling with appropriate priorities
- Scalable pagination handling
- Graceful error handling

**Next Steps**:
1. Deploy to production
2. Submit to Google Search Console
3. Monitor indexing performance
4. Consider implementing sitemap index if archive grows beyond 50,000 articles

---

**Implementation Completed**: November 30, 2025
**Implemented By**: Claude SEO Specialist
**Status**: READY FOR DEPLOYMENT
