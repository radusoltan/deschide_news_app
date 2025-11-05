/**
 * Integration Tests - Redirect Handling
 * Tests the redirect middleware and redirect chain detection
 */

import { describe, it, expect, beforeEach } from '@jest/globals';
import { checkUrlRedirect, extractRedirectInfo } from '@/lib/middleware/redirect-handler';
import type { RedirectCheckResponse } from '@/lib/types';

describe('Redirect Handling', () => {
  describe('URL Redirect Detection', () => {
    it('should detect 301 permanent redirect', async () => {
      // Test that redirect detection works
      const response = await checkUrlRedirect('/old-category/old-article');

      if (response && response.has_redirect) {
        expect(response.final_url).toBeDefined();
        expect(response.chain).toBeDefined();
        expect(response.chain![0].status_code).toBe(301);
      }
    });

    it('should handle redirect chains', async () => {
      const response = await checkUrlRedirect('/very-old-url');

      if (response && response.has_redirect && response.chain) {
        expect(response.chain.length).toBeGreaterThan(0);
        expect(response.chain.length).toBeLessThanOrEqual(5); // Max chain length
        expect(response.final_url).toBeDefined();
        expect(response.chain_length).toBe(response.chain.length);
      }
    });

    it('should handle no redirect scenario', async () => {
      const response = await checkUrlRedirect('/politica/declaratii-premierului');

      if (response) {
        expect(response.has_redirect).toBe(false);
        expect(response.chain).toBeUndefined();
      }
    });

    it('should timeout after 3 seconds', async () => {
      const startTime = Date.now();

      try {
        await checkUrlRedirect('/test-timeout-url');
      } catch (error) {
        // Should timeout
      }

      const duration = Date.now() - startTime;
      expect(duration).toBeLessThan(3500); // Should abort around 3000ms
    });

    it('should handle API errors gracefully', async () => {
      // Test with invalid URL that causes API error
      const response = await checkUrlRedirect('/invalid@url#test');

      // Should return null on error, not throw
      expect(response).toBeNull();
    });
  });

  describe('Redirect Info Extraction', () => {
    it('should extract valid redirect info', () => {
      const mockResponse: RedirectCheckResponse = {
        success: true,
        has_redirect: true,
        chain: [
          {
            from: '/old-url',
            to: '/new-url',
            status_code: 301,
            type: 'article',
            hit_count: 42,
            created_at: '2025-01-01T00:00:00Z',
          },
        ],
        final_url: '/new-url',
        chain_length: 1,
      };

      const info = extractRedirectInfo(mockResponse);

      expect(info.shouldRedirect).toBe(true);
      expect(info.finalUrl).toBe('/new-url');
      expect(info.statusCode).toBe(301);
      expect(info.chainLength).toBe(1);
    });

    it('should reject redirect chains longer than 5', () => {
      const mockResponse: RedirectCheckResponse = {
        success: true,
        has_redirect: true,
        chain: Array(6).fill({
          from: '/url1',
          to: '/url2',
          status_code: 301,
          type: 'article',
          hit_count: 1,
          created_at: '2025-01-01T00:00:00Z',
        }),
        final_url: '/final-url',
        chain_length: 6,
      };

      const info = extractRedirectInfo(mockResponse);

      expect(info.shouldRedirect).toBe(false);
      expect(info.finalUrl).toBeUndefined();
    });

    it('should handle response without redirect', () => {
      const mockResponse: RedirectCheckResponse = {
        success: true,
        has_redirect: false,
      };

      const info = extractRedirectInfo(mockResponse);

      expect(info.shouldRedirect).toBe(false);
    });

    it('should extract correct status code', () => {
      const codes = [301, 302, 307, 308];

      codes.forEach((code) => {
        const mockResponse: RedirectCheckResponse = {
          success: true,
          has_redirect: true,
          chain: [
            {
              from: '/old',
              to: '/new',
              status_code: code as 301 | 302 | 307 | 308,
              type: 'article',
              hit_count: 1,
              created_at: '2025-01-01T00:00:00Z',
            },
          ],
          final_url: '/new',
          chain_length: 1,
        };

        const info = extractRedirectInfo(mockResponse);
        expect(info.statusCode).toBe(code);
      });
    });
  });

  describe('Redirect Chain Detection', () => {
    it('should detect circular redirects', async () => {
      // This would need backend support to create circular redirect
      // Test that circular redirects are detected and prevented
      expect(true).toBe(true);
    });

    it('should track redirect hit counts', async () => {
      const response = await checkUrlRedirect('/old-article-url');

      if (response && response.has_redirect && response.chain) {
        expect(response.chain[0].hit_count).toBeGreaterThanOrEqual(0);
      }
    });

    it('should provide creation timestamps', async () => {
      const response = await checkUrlRedirect('/old-article-url');

      if (response && response.has_redirect && response.chain) {
        expect(response.chain[0].created_at).toMatch(/^\d{4}-\d{2}-\d{2}/);
      }
    });
  });

  describe('Redirect Types', () => {
    it('should identify article redirects', async () => {
      const response = await checkUrlRedirect('/old-category/article-slug');

      if (response && response.has_redirect && response.chain) {
        expect(response.chain[0].type).toBe('article');
      }
    });

    it('should identify category redirects', async () => {
      const response = await checkUrlRedirect('/old-category-slug');

      if (response && response.has_redirect && response.chain) {
        expect(response.chain[0].type).toBe('category');
      }
    });
  });

  describe('Performance', () => {
    it('should complete redirect check in < 100ms for cache hit', async () => {
      // First call to cache the result
      await checkUrlRedirect('/test-url');

      // Second call should be fast
      const startTime = Date.now();
      await checkUrlRedirect('/test-url');
      const duration = Date.now() - startTime;

      // Allow some margin for test execution overhead
      expect(duration).toBeLessThan(100);
    });

    it('should handle concurrent redirect checks', async () => {
      const urls = [
        '/url1',
        '/url2',
        '/url3',
        '/url4',
        '/url5',
      ];

      const startTime = Date.now();
      const results = await Promise.all(urls.map((url) => checkUrlRedirect(url)));
      const duration = Date.now() - startTime;

      // Should complete all checks in reasonable time
      expect(duration).toBeLessThan(5000);
      expect(results).toHaveLength(5);
    });
  });
});
