/**
 * Robots.txt Configuration
 *
 * Controls search engine crawling behavior
 * - Allows all search engines by default
 * - Blocks admin and API routes
 * - References sitemaps for better indexing
 */

import { MetadataRoute } from 'next';

export default function robots(): MetadataRoute.Robots {
  const baseUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://deschide.md';

  return {
    rules: [
      {
        userAgent: '*',
        allow: '/',
        disallow: [
          '/api/',
          '/admin/',
          '/_next/',
          '/preview/',
          '/*.json$', // Block JSON endpoints from indexing
          '/*?*utm_*', // Block URLs with tracking parameters
        ],
      },
      // Slow down aggressive crawlers
      {
        userAgent: ['AhrefsBot', 'SemrushBot', 'MJ12bot', 'DotBot'],
        crawlDelay: 10,
        disallow: ['/api/', '/admin/'],
      },
      // Block specific bad bots
      {
        userAgent: [
          'GPTBot', // OpenAI
          'ChatGPT-User',
          'CCBot', // Common Crawl
          'Google-Extended', // Google AI training
          'anthropic-ai', // Anthropic
          'Claude-Web', // Claude
          'Omgilibot', // Omgili
        ],
        disallow: '/',
      },
    ],
    sitemap: [
      `${baseUrl}/sitemap.xml`,
      `${baseUrl}/news-sitemap.xml`,
      `${baseUrl}/image-sitemap.xml`,
      `${baseUrl}/sitemap-archive.xml`,
    ],
    host: baseUrl,
  };
}
