/**
 * Reserved Slugs Tests
 *
 * Tests for reserved slug validation functionality
 */

import { isReservedSlug, validateSlug, getReservedSlugs, RESERVED_SLUGS } from '../reserved-slugs';

describe('Reserved Slugs', () => {
  describe('RESERVED_SLUGS constant', () => {
    it('should contain all 17 reserved slugs', () => {
      expect(RESERVED_SLUGS).toHaveLength(17);
    });

    it('should include critical system routes', () => {
      const criticalSlugs = ['all', 'search', 'trending', 'archive', 'about', 'contact', 'author'];
      criticalSlugs.forEach((slug) => {
        expect(RESERVED_SLUGS).toContain(slug);
      });
    });

    it('should include admin and API routes', () => {
      expect(RESERVED_SLUGS).toContain('admin');
      expect(RESERVED_SLUGS).toContain('login');
      expect(RESERVED_SLUGS).toContain('api');
    });

    it('should include SEO and feed routes', () => {
      expect(RESERVED_SLUGS).toContain('sitemap');
      expect(RESERVED_SLUGS).toContain('robots');
      expect(RESERVED_SLUGS).toContain('feed');
      expect(RESERVED_SLUGS).toContain('rss');
    });
  });

  describe('isReservedSlug', () => {
    it('should return true for reserved slugs', () => {
      expect(isReservedSlug('all')).toBe(true);
      expect(isReservedSlug('search')).toBe(true);
      expect(isReservedSlug('trending')).toBe(true);
      expect(isReservedSlug('archive')).toBe(true);
      expect(isReservedSlug('about')).toBe(true);
      expect(isReservedSlug('contact')).toBe(true);
      expect(isReservedSlug('author')).toBe(true);
      expect(isReservedSlug('admin')).toBe(true);
      expect(isReservedSlug('api')).toBe(true);
    });

    it('should return false for non-reserved slugs', () => {
      expect(isReservedSlug('politica')).toBe(false);
      expect(isReservedSlug('economie')).toBe(false);
      expect(isReservedSlug('sport')).toBe(false);
      expect(isReservedSlug('cultura')).toBe(false);
      expect(isReservedSlug('tehnologie')).toBe(false);
    });

    it('should be case-sensitive', () => {
      expect(isReservedSlug('All')).toBe(false);
      expect(isReservedSlug('SEARCH')).toBe(false);
      expect(isReservedSlug('Trending')).toBe(false);
    });

    it('should handle empty strings', () => {
      expect(isReservedSlug('')).toBe(false);
    });
  });

  describe('getReservedSlugs', () => {
    it('should return all reserved slugs', () => {
      const slugs = getReservedSlugs();
      expect(slugs).toHaveLength(17);
      expect(slugs).toEqual(RESERVED_SLUGS);
    });

    it('should return a readonly array', () => {
      const slugs = getReservedSlugs();
      expect(Object.isFrozen(slugs)).toBe(false); // TypeScript readonly, not frozen
      expect(slugs).toBe(RESERVED_SLUGS); // Same reference
    });
  });

  describe('validateSlug', () => {
    it('should return error message for reserved slugs', () => {
      const error = validateSlug('all');
      expect(error).not.toBeNull();
      expect(error).toContain('reserved');
      expect(error).toContain('all');
    });

    it('should return null for valid slugs', () => {
      expect(validateSlug('politica')).toBeNull();
      expect(validateSlug('economie')).toBeNull();
      expect(validateSlug('sport')).toBeNull();
    });

    it('should return error for all reserved slugs', () => {
      RESERVED_SLUGS.forEach((slug) => {
        const error = validateSlug(slug);
        expect(error).not.toBeNull();
        expect(error).toContain(slug);
      });
    });

    it('should handle special characters in slug', () => {
      expect(validateSlug('politică-și-economie')).toBeNull();
      expect(validateSlug('știință-tehnologie')).toBeNull();
    });
  });

  describe('Edge cases', () => {
    it('should handle slugs with spaces', () => {
      expect(isReservedSlug('all ')).toBe(false);
      expect(isReservedSlug(' all')).toBe(false);
      expect(isReservedSlug(' all ')).toBe(false);
    });

    it('should handle slugs with special characters', () => {
      expect(isReservedSlug('all-articles')).toBe(false);
      expect(isReservedSlug('search-results')).toBe(false);
    });

    it('should handle very long slugs', () => {
      const longSlug = 'a'.repeat(255);
      expect(isReservedSlug(longSlug)).toBe(false);
    });

    it('should handle numeric slugs', () => {
      expect(isReservedSlug('2024')).toBe(false);
      expect(isReservedSlug('123')).toBe(false);
    });
  });

  describe('Integration scenarios', () => {
    it('should prevent routing conflicts', () => {
      // These should be reserved to prevent conflicts with system pages
      const systemRoutes = [
        'all', // /all - All articles page
        'search', // /search - Search page
        'trending', // /trending - Trending page
        'archive', // /archive - Archive root
        'author', // /author/[slug] - Author pages
      ];

      systemRoutes.forEach((route) => {
        expect(isReservedSlug(route)).toBe(true);
      });
    });

    it('should allow category slugs that are similar but not exact matches', () => {
      expect(isReservedSlug('all-news')).toBe(false);
      expect(isReservedSlug('search-tips')).toBe(false);
      expect(isReservedSlug('trending-topics')).toBe(false);
    });
  });

  describe('Backend compatibility', () => {
    it('should match backend reserved slugs count', () => {
      // Backend has 17 reserved slugs in ReservedSlug.php
      expect(RESERVED_SLUGS).toHaveLength(17);
    });

    it('should include all backend reserved slugs', () => {
      // These must match the backend validation
      const backendSlugs = [
        'all',
        'search',
        'trending',
        'archive',
        'about',
        'contact',
        'author',
        'authors',
        'admin',
        'login',
        'api',
        'sitemap',
        'robots',
        'feed',
        'rss',
        'privacy',
        'terms',
      ];

      backendSlugs.forEach((slug) => {
        expect(RESERVED_SLUGS).toContain(slug);
      });
    });
  });
});
