/**
 * Articles API Tests
 * Tests for article-related API functions
 */

import {
  fetchArticlesByCategory,
  fetchLatestArticles,
  fetchRelatedArticles,
} from '@/lib/api/articles';
import { mockArticles, mockArticleListResponse } from '@/__tests__/__mocks__/articles';

// Mock fetch
global.fetch = jest.fn();

describe('Articles API', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('fetchArticlesByCategory', () => {
    it('should fetch articles for a specific category', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockArticleListResponse,
      });

      const result = await fetchArticlesByCategory(1);

      expect(global.fetch).toHaveBeenCalledTimes(1);
      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/api/articles'),
        expect.objectContaining({
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
          },
        })
      );

      // Verify URL parameters
      const callUrl = (global.fetch as jest.Mock).mock.calls[0][0];
      expect(callUrl).toContain('categoryId=1');
      expect(callUrl).toContain('status=published');
      expect(callUrl).toContain('itemsPerPage=6');

      expect(result).toEqual(mockArticleListResponse);
    });

    it('should include Accept-Language header when locale is provided', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockArticleListResponse,
      });

      await fetchArticlesByCategory(1, 'ro');

      expect(global.fetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          headers: expect.objectContaining({
            'Accept-Language': 'ro',
          }),
        })
      );
    });

    it('should respect custom itemsPerPage parameter', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockArticleListResponse,
      });

      await fetchArticlesByCategory(1, 'ro', 12);

      const callUrl = (global.fetch as jest.Mock).mock.calls[0][0];
      expect(callUrl).toContain('itemsPerPage=12');
    });

    it('should use default itemsPerPage of 6 when not specified', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockArticleListResponse,
      });

      await fetchArticlesByCategory(1);

      const callUrl = (global.fetch as jest.Mock).mock.calls[0][0];
      expect(callUrl).toContain('itemsPerPage=6');
    });

    it('should handle API errors', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: false,
        status: 404,
        statusText: 'Not Found',
      });

      await expect(fetchArticlesByCategory(999)).rejects.toThrow(
        'Failed to fetch articles: 404 Not Found'
      );
    });

    it('should include revalidate option in Next.js cache config', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockArticleListResponse,
      });

      await fetchArticlesByCategory(1);

      expect(global.fetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          next: expect.objectContaining({
            revalidate: 120,
          }),
        })
      );
    });
  });

  describe('fetchLatestArticles', () => {
    it('should fetch latest published articles', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockArticleListResponse,
      });

      const result = await fetchLatestArticles();

      expect(global.fetch).toHaveBeenCalledTimes(1);

      const callUrl = (global.fetch as jest.Mock).mock.calls[0][0];
      expect(callUrl).toContain('status=published');
      // URL encoding: [ becomes %5B, ] becomes %5D
      expect(callUrl).toContain('order%5BpublishedAt%5D=desc');
      expect(callUrl).toContain('itemsPerPage=10');

      expect(result).toEqual(mockArticleListResponse);
    });

    it('should include Accept-Language header when locale is provided', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockArticleListResponse,
      });

      await fetchLatestArticles('en');

      expect(global.fetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          headers: expect.objectContaining({
            'Accept-Language': 'en',
          }),
        })
      );
    });

    it('should respect custom itemsPerPage parameter', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockArticleListResponse,
      });

      await fetchLatestArticles('ro', 20);

      const callUrl = (global.fetch as jest.Mock).mock.calls[0][0];
      expect(callUrl).toContain('itemsPerPage=20');
    });

    it('should use default itemsPerPage of 10 when not specified', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockArticleListResponse,
      });

      await fetchLatestArticles();

      const callUrl = (global.fetch as jest.Mock).mock.calls[0][0];
      expect(callUrl).toContain('itemsPerPage=10');
    });

    it('should handle API errors', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: false,
        status: 500,
        statusText: 'Internal Server Error',
      });

      await expect(fetchLatestArticles()).rejects.toThrow(
        'Failed to fetch latest articles: 500 Internal Server Error'
      );
    });

    it('should include revalidate option of 60 seconds', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockArticleListResponse,
      });

      await fetchLatestArticles();

      expect(global.fetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          next: expect.objectContaining({
            revalidate: 60,
          }),
        })
      );
    });
  });

  describe('fetchRelatedArticles', () => {
    it('should fetch related articles and exclude current article', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          ...mockArticleListResponse,
          member: mockArticles, // 3 articles
        }),
      });

      const result = await fetchRelatedArticles(1, 1);

      // Should fetch with limit + 1
      const callUrl = (global.fetch as jest.Mock).mock.calls[0][0];
      expect(callUrl).toContain('itemsPerPage=7'); // 6 + 1

      // Should exclude article with id 1
      expect(result).toHaveLength(2);
      expect(result.every((article: any) => article.id !== 1)).toBe(true);
    });

    it('should pass locale to fetchArticlesByCategory', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          ...mockArticleListResponse,
          member: mockArticles,
        }),
      });

      await fetchRelatedArticles(1, 1, 'ru');

      expect(global.fetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          headers: expect.objectContaining({
            'Accept-Language': 'ru',
          }),
        })
      );
    });

    it('should respect custom limit parameter', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          ...mockArticleListResponse,
          member: mockArticles.concat([
            { ...mockArticles[0], id: 4 },
            { ...mockArticles[0], id: 5 },
            { ...mockArticles[0], id: 6 },
          ]),
        }),
      });

      const result = await fetchRelatedArticles(1, 1, 'ro', 3);

      const callUrl = (global.fetch as jest.Mock).mock.calls[0][0];
      expect(callUrl).toContain('itemsPerPage=4'); // 3 + 1

      expect(result).toHaveLength(3);
    });

    it('should use default limit of 6 when not specified', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockArticleListResponse,
      });

      await fetchRelatedArticles(1, 1);

      const callUrl = (global.fetch as jest.Mock).mock.calls[0][0];
      expect(callUrl).toContain('itemsPerPage=7'); // 6 + 1
    });

    it('should return only the requested limit of articles', async () => {
      const manyArticles = Array.from({ length: 10 }, (_, i) => ({
        ...mockArticles[0],
        id: i + 2, // Start from id 2 to exclude current article id 1
      }));

      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          ...mockArticleListResponse,
          member: manyArticles,
        }),
      });

      const result = await fetchRelatedArticles(1, 1, 'ro', 6);

      expect(result).toHaveLength(6);
    });

    it('should return empty array on error', async () => {
      (global.fetch as jest.Mock).mockRejectedValueOnce(
        new Error('Network error')
      );

      const result = await fetchRelatedArticles(1, 1);

      expect(result).toEqual([]);
    });

    it('should handle empty category (no articles)', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          ...mockArticleListResponse,
          member: [],
        }),
      });

      const result = await fetchRelatedArticles(1, 1);

      expect(result).toEqual([]);
    });

    it('should handle case where only current article exists in category', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          ...mockArticleListResponse,
          member: [mockArticles[0]], // Only article with id 1
        }),
      });

      const result = await fetchRelatedArticles(1, 1);

      expect(result).toEqual([]);
    });
  });

  describe('Integration tests', () => {
    it('should handle pagination in API response', async () => {
      const paginatedResponse = {
        ...mockArticleListResponse,
        'hydra:totalItems': 50,
        'hydra:view': {
          '@id': '/api/articles?page=1',
          '@type': 'hydra:PartialCollectionView',
          'hydra:first': '/api/articles?page=1',
          'hydra:last': '/api/articles?page=5',
          'hydra:next': '/api/articles?page=2',
        },
      };

      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => paginatedResponse,
      });

      const result = await fetchArticlesByCategory(1);

      expect(result['hydra:totalItems']).toBe(50);
      expect(result['hydra:view']).toBeDefined();
      expect(result['hydra:view']?.['hydra:next']).toBe('/api/articles?page=2');
    });

    it('should work with Romanian locale', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockArticleListResponse,
      });

      await fetchLatestArticles('ro');

      expect(global.fetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          headers: expect.objectContaining({
            'Accept-Language': 'ro',
          }),
        })
      );
    });

    it('should work with English locale', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockArticleListResponse,
      });

      await fetchLatestArticles('en');

      expect(global.fetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          headers: expect.objectContaining({
            'Accept-Language': 'en',
          }),
        })
      );
    });

    it('should work with Russian locale', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockArticleListResponse,
      });

      await fetchLatestArticles('ru');

      expect(global.fetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          headers: expect.objectContaining({
            'Accept-Language': 'ru',
          }),
        })
      );
    });
  });
});
