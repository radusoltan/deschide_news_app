/**
 * URL Builder Utility Tests
 */

import { buildArticleUrl, buildCategoryUrl, buildImageUrl } from '@/lib/utils/url-builder';
import { mockArticle, mockArticles } from '@/__tests__/__mocks__/articles';
import { mockCategory } from '@/__tests__/__mocks__/categories';

describe('URL Builder Utilities', () => {
  describe('buildArticleUrl', () => {
    it('should build article URL for default locale (ro)', () => {
      const url = buildArticleUrl(mockArticle, 'ro');
      expect(url).toBe('/politics/test-article-title');
    });

    it('should build article URL for English locale', () => {
      const url = buildArticleUrl(mockArticle, 'en');
      expect(url).toBe('/en/politics/test-article-title');
    });

    it('should build article URL for Russian locale', () => {
      const url = buildArticleUrl(mockArticle, 'ru');
      expect(url).toBe('/ru/politics/test-article-title');
    });

    it('should handle article with string category', () => {
      const articleWithStringCategory = {
        ...mockArticle,
        category: 'politics',
      };
      const url = buildArticleUrl(articleWithStringCategory, 'ro');
      expect(url).toBe('/politics/test-article-title');
    });

    it('should handle missing category gracefully', () => {
      const articleWithoutCategory = {
        ...mockArticle,
        category: undefined,
      };
      const url = buildArticleUrl(articleWithoutCategory as any, 'ro');
      expect(url).toBe('/uncategorized/test-article-title');
    });
  });

  describe('buildCategoryUrl', () => {
    it('should build category URL for default locale (ro)', () => {
      const url = buildCategoryUrl(mockCategory, 'ro');
      expect(url).toBe('/politics');
    });

    it('should build category URL for English locale', () => {
      const url = buildCategoryUrl(mockCategory, 'en');
      expect(url).toBe('/en/politics');
    });

    it('should build category URL for Russian locale', () => {
      const url = buildCategoryUrl(mockCategory, 'ru');
      expect(url).toBe('/ru/politics');
    });

    it('should handle string category', () => {
      const url = buildCategoryUrl('politics', 'ro');
      expect(url).toBe('/politics');
    });

    it('should handle missing category', () => {
      const url = buildCategoryUrl(undefined as any, 'ro');
      expect(url).toBe('/uncategorized');
    });
  });

  // Note: buildImageUrl is in lib/api/important-articles.ts, not in url-builder.ts
});
