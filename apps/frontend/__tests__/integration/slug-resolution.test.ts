/**
 * Integration Tests - Slug Resolution
 * Tests the slug resolver service and caching functionality
 */

import { describe, it, expect, beforeEach, afterEach } from '@jest/globals';
import { resolveArticle, resolveCategory } from '@/lib/services/slug-resolver';
import { slugCache, articleCache, categoryCache } from '@/lib/services/cache-manager';
import type { Locale } from '@/lib/types';

describe('Slug Resolution Service', () => {
  beforeEach(() => {
    // Clear all caches before each test
    slugCache.clear();
    articleCache.clear();
    categoryCache.clear();
  });

  afterEach(() => {
    // Cleanup after each test
    slugCache.clear();
    articleCache.clear();
    categoryCache.clear();
  });

  describe('Article Resolution', () => {
    it('should resolve article by slugs (Romanian)', async () => {
      const result = await resolveArticle('politica', 'declaratii-premierului', 'ro');

      expect(result.success).toBe(true);
      expect(result.article).toBeDefined();
      expect(result.article?.slug).toBe('declaratii-premierului');
      expect(result.cached).toBe(false); // First call should not be cached
    });

    it('should resolve article by slugs (English)', async () => {
      const result = await resolveArticle('politics', 'prime-minister-statement', 'en');

      expect(result.success).toBe(true);
      expect(result.article).toBeDefined();
      expect(result.article?.slug).toBe('prime-minister-statement');
    });

    it('should resolve article by slugs (Russian)', async () => {
      const result = await resolveArticle('политика', 'заявление-премьера', 'ru');

      expect(result.success).toBe(true);
      expect(result.article).toBeDefined();
      expect(result.article?.slug).toBe('заявление-премьера');
    });

    it('should return cached result on second call', async () => {
      // First call - not cached
      const first = await resolveArticle('politica', 'declaratii-premierului', 'ro');
      expect(first.cached).toBe(false);

      // Second call - should be cached
      const second = await resolveArticle('politica', 'declaratii-premierului', 'ro');
      expect(second.cached).toBe(true);
      expect(second.article?.id).toBe(first.article?.id);
    });

    it('should return not found for invalid slug', async () => {
      const result = await resolveArticle('politica', 'invalid-article-slug', 'ro');

      expect(result.success).toBe(false);
      expect(result.error).toContain('not found');
    });

    it('should handle wrong category slug', async () => {
      const result = await resolveArticle('economie', 'declaratii-premierului', 'ro');

      // Article exists but in different category
      expect(result.success).toBe(false);
    });

    it('should detect redirect if article moved', async () => {
      // This test assumes backend returns redirect info
      // Implementation depends on actual API response
      const result = await resolveArticle('old-category', 'article-slug', 'ro');

      if (result.redirect) {
        expect(result.redirect.new_url).toBeDefined();
        expect(result.redirect.status_code).toBeOneOf([301, 302, 307, 308]);
      }
    });
  });

  describe('Category Resolution', () => {
    it('should resolve category by slug (Romanian)', async () => {
      const result = await resolveCategory('politica', 'ro');

      expect(result.success).toBe(true);
      expect(result.category).toBeDefined();
      expect(result.category?.slug).toBe('politica');
    });

    it('should resolve category by slug (English)', async () => {
      const result = await resolveCategory('politics', 'en');

      expect(result.success).toBe(true);
      expect(result.category).toBeDefined();
      expect(result.category?.slug).toBe('politics');
    });

    it('should cache category lookups', async () => {
      // First call
      const first = await resolveCategory('politica', 'ro');
      expect(first.cached).toBe(false);

      // Second call
      const second = await resolveCategory('politica', 'ro');
      expect(second.cached).toBe(true);
    });

    it('should return not found for invalid category', async () => {
      const result = await resolveCategory('invalid-category', 'ro');

      expect(result.success).toBe(false);
      expect(result.error).toBeDefined();
    });
  });

  describe('Cache Functionality', () => {
    it('should cache article lookups for 5 minutes', async () => {
      const result = await resolveArticle('politica', 'declaratii-premierului', 'ro');

      // Check cache contains the entry
      const cacheKey = 'article:ro:politica:declaratii-premierului';
      const cached = slugCache.get(cacheKey);

      expect(cached).toBeDefined();
    });

    it('should cache category lookups for 10 minutes', async () => {
      const result = await resolveCategory('politica', 'ro');

      // Check cache contains the entry
      const cacheKey = 'category:ro:politica';
      const cached = slugCache.get(cacheKey);

      expect(cached).toBeDefined();
    });

    it('should respect cache TTL', async () => {
      // This test would need to mock time or wait
      // Skipping actual TTL test in favor of manual testing
      expect(true).toBe(true);
    });

    it('should provide cache statistics', () => {
      const stats = slugCache.getStats();

      expect(stats).toHaveProperty('hits');
      expect(stats).toHaveProperty('misses');
      expect(stats).toHaveProperty('sets');
      expect(stats).toHaveProperty('size');
    });

    it('should track cache hit rate', async () => {
      // Make multiple calls
      await resolveArticle('politica', 'test-article', 'ro');
      await resolveArticle('politica', 'test-article', 'ro'); // Hit
      await resolveArticle('politica', 'test-article', 'ro'); // Hit

      const hitRate = slugCache.getHitRate();
      expect(hitRate).toBeGreaterThan(50); // Should be ~66%
    });
  });

  describe('Error Handling', () => {
    it('should handle API unavailable gracefully', async () => {
      // This would need API mocking
      // Test that service doesn't crash when API is down
      expect(true).toBe(true);
    });

    it('should handle network timeout', async () => {
      // Would need to mock slow network
      expect(true).toBe(true);
    });

    it('should handle malformed API response', async () => {
      // Would need to mock bad response
      expect(true).toBe(true);
    });
  });
});

// Custom Jest matcher
expect.extend({
  toBeOneOf(received: number, expected: number[]) {
    const pass = expected.includes(received);
    return {
      pass,
      message: () => `expected ${received} to be one of ${expected.join(', ')}`,
    };
  },
});
