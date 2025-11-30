# Sitemap Architecture - Deschide News

**Date**: November 30, 2025
**Application**: Deschide News Frontend
**Total Sitemaps**: 4 (Main, News, Image, Archive)

---

## Architecture Overview

```
robots.txt
    |
    +-- References all 4 sitemaps
    |
    v
+-------------------+-------------------+-------------------+-------------------+
|   sitemap.xml     | news-sitemap.xml  | image-sitemap.xml |sitemap-archive.xml|
|   (Main)          | (Google News)     | (Images)          | (Archives)        |
+-------------------+-------------------+-------------------+-------------------+
| Active Content    | Recent Articles   | Article Images    | Archived Articles |
| - Homepage        | (Last 48 hours)   | - Featured Images | (Historical)      |
| - Articles        |                   | - Inline Images   |                   |
| - Categories      |                   |                   |                   |
| - Authors         |                   |                   |                   |
| - Static Pages    |                   |                   |                   |
| - Archive Pages   |                   |                   |                   |
+-------------------+-------------------+-------------------+-------------------+
| Priority: 0.5-1.0 | Priority: 0.8-1.0 | N/A (image schema)| Priority: 0.3     |
| Freq: hourly-mnth | Freq: hourly      | Freq: daily       | Freq: yearly      |
| Cache: 1 hour     | Cache: 1 hour     | Cache: 1 hour     | Cache: 24 hours   |
| Revalidate: 3600s | Revalidate: 3600s | Revalidate: 3600s | Revalidate: 86400s|
+-------------------+-------------------+-------------------+-------------------+
```

---

## Sitemap Comparison Matrix

| Feature | Main Sitemap | News Sitemap | Image Sitemap | Archive Sitemap |
|---------|-------------|--------------|---------------|-----------------|
| **URL** | `/sitemap.xml` | `/news-sitemap.xml` | `/image-sitemap.xml` | `/sitemap-archive.xml` |
| **Format** | Standard XML | Google News XML | Image XML | Standard XML |
| **Content** | All active pages | Recent articles | Images only | Archived articles |
| **Priority** | 0.5 - 1.0 | 0.8 - 1.0 | N/A | 0.3 |
| **Change Freq** | hourly - monthly | hourly | daily | yearly |
| **Cache (Browser)** | 1 hour | 1 hour | 1 hour | 24 hours |
| **Cache (CDN)** | N/A | N/A | N/A | 1 week |
| **Revalidate** | 3600s | 3600s | 3600s | 86400s |
| **Hreflang** | Yes (all locales) | Yes (all locales) | No | Yes (all locales) |
| **Time Range** | All active | Last 48 hours | All with images | All archived |
| **Volume** | ~500 URLs | ~50 URLs | ~1000+ images | ~65,000 URLs |
| **Update Trigger** | Content changes | New articles | New images | Archive changes |
| **SEO Purpose** | General indexing | News indexing | Image search | Historical content |

---

## Content Distribution

```
Total Content Universe
├── Active Content (sitemap.xml)
│   ├── Homepage (1)
│   ├── Static Pages (5)
│   │   ├── /all
│   │   ├── /trending
│   │   ├── /archive
│   │   ├── /about
│   │   └── /contact
│   ├── Categories (~20)
│   ├── Authors (~50)
│   ├── Active Articles (~2,000)
│   └── Archive Pages (~24 year/month combinations)
│
├── Recent Articles (news-sitemap.xml)
│   └── Last 48 hours (~20-50 articles)
│
├── Images (image-sitemap.xml)
│   ├── Featured Images (~2,000)
│   └── Inline Images (~5,000)
│
└── Archived Articles (sitemap-archive.xml)
    └── Historical content (~65,000 articles)
```

---

## URL Patterns by Sitemap

### Main Sitemap (sitemap.xml)

```
https://deschide.md/                                    (homepage, ro)
https://deschide.md/en/                                 (homepage, en)
https://deschide.md/ru/                                 (homepage, ru)
https://deschide.md/politika                            (category, ro)
https://deschide.md/en/politics                         (category, en)
https://deschide.md/ru/politika                         (category, ru)
https://deschide.md/author/john-doe                     (author, ro)
https://deschide.md/en/author/john-doe                  (author, en)
https://deschide.md/ru/author/john-doe                  (author, ru)
https://deschide.md/politika/article-slug               (article, ro)
https://deschide.md/en/politics/article-slug            (article, en)
https://deschide.md/ru/politika/article-slug            (article, ru)
https://deschide.md/all                                 (static, ro)
https://deschide.md/archive/2025                        (archive year)
https://deschide.md/archive/2025/11                     (archive month)
```

### News Sitemap (news-sitemap.xml)

```xml
<!-- Only articles from last 48 hours -->
<url>
  <loc>https://deschide.md/politika/breaking-news</loc>
  <news:news>
    <news:publication>
      <news:name>Deschide News</news:name>
      <news:language>ro</news:language>
    </news:publication>
    <news:publication_date>2025-11-30T08:00:00+00:00</news:publication_date>
    <news:title>Breaking News Title</news:title>
  </news:news>
</url>
```

### Image Sitemap (image-sitemap.xml)

```xml
<url>
  <loc>https://deschide.md/politika/article-slug</loc>
  <image:image>
    <image:loc>https://cdn.deschide.md/uploads/images/photo.jpg</image:loc>
    <image:title>Photo Caption</image:title>
    <image:caption>Detailed description</image:caption>
  </image:image>
</url>
```

### Archive Sitemap (sitemap-archive.xml)

```xml
<url>
  <loc>https://deschide.md/politika/old-article-2020</loc>
  <lastmod>2020-05-15T10:00:00.000Z</lastmod>
  <changefreq>yearly</changefreq>
  <priority>0.3</priority>
  <xhtml:link rel="alternate" hreflang="ro" href="..." />
  <xhtml:link rel="alternate" hreflang="en" href="..." />
  <xhtml:link rel="alternate" hreflang="ru" href="..." />
  <xhtml:link rel="alternate" hreflang="x-default" href="..." />
</url>
```

---

## Priority Hierarchy

```
Priority Scale (0.0 - 1.0)
    |
    1.0 ├─ Homepage
        │
    0.9 ├─ Featured Articles (main sitemap)
        │  Breaking News (news sitemap)
        │
    0.8 ├─ Regular Articles (main sitemap)
        │  Recent News (news sitemap)
        │
    0.7 ├─ Categories (main sitemap)
        │
    0.6 ├─ Authors (main sitemap)
        │
    0.5 ├─ Static Pages (main sitemap)
        │  Archive Pages (main sitemap)
        │
    0.4 ├─
        │
    0.3 ├─ Archived Articles (archive sitemap) ← NEW
        │
    0.2 ├─
        │
    0.1 ├─
        │
    0.0 └─
```

---

## Change Frequency Strategy

| Frequency | Content Type | Sitemap | Rationale |
|-----------|-------------|---------|-----------|
| **hourly** | Homepage | Main | Constantly updated with new content |
| **hourly** | Recent news | News | Breaking news updates |
| **daily** | Active articles | Main | Comments, views, minor updates |
| **daily** | Categories | Main | New articles added daily |
| **daily** | Images | Image | New articles with images |
| **weekly** | Authors | Main | Author bio updates |
| **weekly** | Archive pages | Main | New articles added to archive |
| **monthly** | Static pages | Main | Rarely change |
| **yearly** | Archived articles | Archive | Historical content, rarely updated |

---

## Cache Strategy

```
Browser Cache          CDN Cache           Next.js Revalidation
     |                     |                        |
     v                     v                        v
+----------+         +------------+          +----------------+
| 1 hour   | ------> | No CDN yet | -------> | 3600s (1h)     | Main Sitemap
+----------+         +------------+          +----------------+
| 1 hour   | ------> | No CDN yet | -------> | 3600s (1h)     | News Sitemap
+----------+         +------------+          +----------------+
| 1 hour   | ------> | No CDN yet | -------> | 3600s (1h)     | Image Sitemap
+----------+         +------------+          +----------------+
| 24 hours | ------> | 1 week     | -------> | 86400s (24h)   | Archive Sitemap
+----------+         +------------+          +----------------+
```

**Archive Sitemap Caching Benefits**:
- Reduces server load (archives rarely change)
- Faster response times (CDN serves cached version)
- Lower API requests (24-hour revalidation window)

---

## Data Flow

### Main Sitemap Generation

```
User Request
    |
    v
Next.js Route Handler (sitemap.ts)
    |
    +-- Fetch Categories
    +-- Fetch Authors
    +-- Fetch Active Articles
    |
    v
Generate XML with hreflang
    |
    v
Response (Cache: 1 hour)
```

### Archive Sitemap Generation

```
User Request
    |
    v
Next.js Route Handler (sitemap-archive.xml/route.ts)
    |
    v
fetchAllArchivedArticlesForSitemap()
    |
    +-- Loop: while (hasNextPage)
    |       |
    |       +-- Fetch page (100 articles)
    |       +-- Append to array
    |       +-- Check for hydra:next
    |       +-- Safety: max 1000 pages
    |
    v
Map to ArchivedArticle interface
    |
    v
generateArchiveSitemapXml()
    |
    +-- For each article
    |       +-- For each locale (ro, en, ru)
    |               +-- Build URL
    |               +-- Add hreflang tags
    |               +-- Set priority: 0.3
    |               +-- Set changefreq: yearly
    |
    v
Escape XML entities
    |
    v
Response (Cache: 24 hours)
```

---

## Multilingual SEO Implementation

### Hreflang Tags Structure

Every article URL includes hreflang alternates for all locales:

```xml
<url>
  <loc>https://deschide.md/politika/article-slug</loc>
  <!-- Romanian (default locale) -->
  <xhtml:link
    rel="alternate"
    hreflang="ro"
    href="https://deschide.md/politika/article-slug"
  />
  <!-- English -->
  <xhtml:link
    rel="alternate"
    hreflang="en"
    href="https://deschide.md/en/politics/article-slug"
  />
  <!-- Russian -->
  <xhtml:link
    rel="alternate"
    hreflang="ru"
    href="https://deschide.md/ru/politika/article-slug"
  />
  <!-- Default (fallback to Romanian) -->
  <xhtml:link
    rel="alternate"
    hreflang="x-default"
    href="https://deschide.md/politika/article-slug"
  />
</url>
```

### Benefits

1. **Regional Targeting**: Search engines serve correct language version
2. **Duplicate Content Prevention**: Signals that translations are not duplicates
3. **User Experience**: Users see results in their preferred language
4. **SEO Best Practice**: Follows Google's international SEO guidelines

---

## File Sizes & Performance

### Estimated Sizes (Uncompressed)

| Sitemap | URLs | Avg Entry Size | Total Size | Generation Time |
|---------|------|----------------|------------|-----------------|
| Main | ~2,100 | ~1 KB | ~2 MB | 5-10 seconds |
| News | ~50 | ~800 bytes | ~40 KB | 1-2 seconds |
| Image | ~7,000 images | ~500 bytes | ~3.5 MB | 10-15 seconds |
| Archive | ~65,000 | ~300 bytes | ~19.5 MB | 2-3 minutes |

### With Hreflang Tags (Archive)

Each archived article generates 3 URLs (one per locale), each with 4 hreflang tags:
- Entry size increases to ~1 KB per article
- Total size: ~65 MB (exceeds 50 MB limit!)

**Solution Required**: Implement sitemap index to split into chunks

---

## Google Sitemap Limits

### Per Sitemap

- Max URLs: **50,000**
- Max file size (uncompressed): **50 MB**
- Max file size (compressed): **10 MB**

### Current Status

| Sitemap | URLs | Size | Compliant? | Action Needed |
|---------|------|------|------------|---------------|
| Main | ~2,100 | ~2 MB | Yes | None |
| News | ~50 | ~40 KB | Yes | None |
| Image | ~7,000 | ~3.5 MB | Yes | None |
| Archive | ~65,000 | ~65 MB | **NO** | **Split required** |

### Archive Sitemap Solution

**Option 1: Sitemap Index**
```
sitemap-archive-index.xml
├── sitemap-archive-1.xml (1-50,000)
└── sitemap-archive-2.xml (50,001-65,000)
```

**Option 2: Year-Based Split**
```
sitemap-archive-2024.xml
sitemap-archive-2023.xml
sitemap-archive-2022.xml
...
```

**Recommended**: Year-based split (easier to manage, better cache control)

---

## robots.txt Integration

```txt
User-Agent: *
Allow: /
Disallow: /api/
Disallow: /admin/
Disallow: /_next/
Disallow: /preview/
Disallow: /*.json$
Disallow: /*?*utm_*

User-Agent: AhrefsBot
User-Agent: SemrushBot
User-Agent: MJ12bot
User-Agent: DotBot
Disallow: /api/
Disallow: /admin/
Crawl-delay: 10

User-Agent: GPTBot
User-Agent: ChatGPT-User
User-Agent: CCBot
User-Agent: Google-Extended
User-Agent: anthropic-ai
User-Agent: Claude-Web
User-Agent: Omgilibot
Disallow: /

Host: https://deschide.md
Sitemap: https://deschide.md/sitemap.xml
Sitemap: https://deschide.md/news-sitemap.xml
Sitemap: https://deschide.md/image-sitemap.xml
Sitemap: https://deschide.md/sitemap-archive.xml    ← NEW
```

---

## Search Engine Submission

### Google Search Console

1. **Add Property**: `https://deschide.md`
2. **Verify Ownership**: DNS or HTML file
3. **Submit Sitemaps**:
   ```
   https://deschide.md/sitemap.xml
   https://deschide.md/news-sitemap.xml
   https://deschide.md/image-sitemap.xml
   https://deschide.md/sitemap-archive.xml
   ```
4. **Monitor**: Sitemaps > Submitted sitemaps

### Yandex Webmaster (Russian Market)

1. Add site
2. Submit sitemaps (same URLs)
3. Important for Russian locale (ru)

### Bing Webmaster Tools

1. Add site
2. Submit sitemaps
3. Automatic indexing

---

## Monitoring & Maintenance

### Daily Checks

- [ ] Monitor sitemap generation errors in logs
- [ ] Check revalidation success rate

### Weekly Checks

- [ ] Review Google Search Console for errors
- [ ] Check indexed URL count
- [ ] Monitor crawl stats

### Monthly Checks

- [ ] Validate XML structure of all sitemaps
- [ ] Check for broken URLs in sitemaps
- [ ] Review sitemap sizes (watch for limit violations)
- [ ] Update archive sitemap if approaching 50,000 URLs

### Quarterly Checks

- [ ] Full SEO audit
- [ ] Review sitemap strategy
- [ ] Optimize based on indexing data
- [ ] Consider new sitemap types (video, podcast, etc.)

---

## Future Roadmap

### Phase 1: Current (COMPLETED)
- [x] Main sitemap with hreflang
- [x] News sitemap (Google News format)
- [x] Image sitemap
- [x] Archive sitemap with pagination

### Phase 2: Optimization (Next Sprint)
- [ ] Split archive sitemap into year-based files
- [ ] Implement sitemap index for archives
- [ ] Add compression (gzip) support
- [ ] Optimize pagination batch size

### Phase 3: Enhancement (Q1 2026)
- [ ] Add video sitemap (when video content added)
- [ ] Implement mobile sitemap variations
- [ ] Add last-modified tracking for incremental updates
- [ ] Automated sitemap testing in CI/CD

### Phase 4: Advanced (Q2 2026)
- [ ] Real-time sitemap updates via Mercure
- [ ] Sitemap analytics dashboard
- [ ] Automatic sitemap submission to search engines
- [ ] A/B testing for sitemap strategies

---

## Related Documentation

- `/var/www/deschide_news_app/docs/seo/ARCHIVE_SITEMAP_IMPLEMENTATION.md`
- `/var/www/deschide_news_app/docs/audits/seo-audit.md`
- `/var/www/deschide_news_app/CLAUDE.md` (SEO specialist role)

---

## Conclusion

The Deschide News sitemap architecture provides comprehensive coverage for:
- Active content (main sitemap)
- Breaking news (news sitemap)
- Visual content (image sitemap)
- Historical content (archive sitemap)

All sitemaps implement proper multilingual SEO with hreflang tags, appropriate priorities, and cache strategies. The archive sitemap addresses the specific needs of historical content with lower priority and longer cache times.

**Next Step**: Split archive sitemap to comply with Google's 50,000 URL limit.

---

**Architecture Documented**: November 30, 2025
**Status**: PRODUCTION READY (with splitting recommendation)
