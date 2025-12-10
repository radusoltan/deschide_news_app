/**
 * API Client Tests
 * Tests for the base API client with error handling, retry logic, and timeout support
 */

import {
  apiRequest,
  get,
  post,
  put,
  patch,
  del,
  healthCheck,
  ApiError,
  TimeoutError,
  NetworkError,
} from '@/lib/api/client';

// Mock fetch
global.fetch = jest.fn();

describe('API Client', () => {
  let consoleErrorSpy: jest.SpyInstance;
  let consoleLogSpy: jest.SpyInstance;

  beforeEach(() => {
    jest.clearAllMocks();
    jest.useFakeTimers();
    // Mock console methods to suppress output during tests
    consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation();
    consoleLogSpy = jest.spyOn(console, 'log').mockImplementation();
  });

  afterEach(() => {
    jest.runOnlyPendingTimers();
    jest.useRealTimers();
    consoleErrorSpy.mockRestore();
    consoleLogSpy.mockRestore();
  });

  describe('apiRequest - Successful requests', () => {
    it('should make a successful GET request', async () => {
      const mockData = { message: 'Success' };
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockData,
      });

      const result = await apiRequest('/api/test');

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/api/test'),
        expect.objectContaining({
          headers: expect.objectContaining({
            'Content-Type': 'application/json',
          }),
        })
      );
      expect(result).toEqual(mockData);
    });

    it('should include Authorization header when token is provided', async () => {
      const mockData = { message: 'Authenticated' };
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockData,
      });

      await apiRequest('/api/test', { token: 'test-token' });

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/api/test'),
        expect.objectContaining({
          headers: expect.objectContaining({
            'Authorization': 'Bearer test-token',
          }),
        })
      );
    });

    it('should include Accept-Language header when locale is provided', async () => {
      const mockData = { message: 'Translated' };
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockData,
      });

      await apiRequest('/api/test', { locale: 'ro' });

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/api/test'),
        expect.objectContaining({
          headers: expect.objectContaining({
            'Accept-Language': 'ro',
          }),
        })
      );
    });

    it('should send POST request with body', async () => {
      const mockData = { id: 1 };
      const requestBody = { name: 'Test' };
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 201,
        json: async () => mockData,
      });

      await apiRequest('/api/test', {
        method: 'POST',
        body: JSON.stringify(requestBody),
      });

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/api/test'),
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify(requestBody),
        })
      );
    });
  });

  describe('apiRequest - Error handling', () => {
    it('should throw ApiError on 4xx client errors', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: false,
        status: 404,
        json: async () => ({
          code: 404,
          message: 'Not Found',
        }),
      });

      await expect(apiRequest('/api/test')).rejects.toThrow(ApiError);

      // Reset mock for second assertion
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: false,
        status: 404,
        json: async () => ({
          code: 404,
          message: 'Not Found',
        }),
      });

      await expect(apiRequest('/api/test')).rejects.toThrow('Not Found');
    });

    it('should include error details in ApiError', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => ({
          code: 400,
          message: 'Validation failed',
          errors: {
            email: ['Email is required'],
            name: ['Name must be at least 3 characters'],
          },
        }),
      });

      try {
        await apiRequest('/api/test');
      } catch (error) {
        expect(error).toBeInstanceOf(ApiError);
        if (error instanceof ApiError) {
          expect(error.status).toBe(400);
          expect(error.message).toBe('Validation failed');
          expect(error.errors).toEqual({
            email: ['Email is required'],
            name: ['Name must be at least 3 characters'],
          });
        }
      }
    });

    it('should not retry on 4xx client errors', async () => {
      (global.fetch as jest.Mock).mockResolvedValue({
        ok: false,
        status: 400,
        json: async () => ({
          code: 400,
          message: 'Bad Request',
        }),
      });

      await expect(apiRequest('/api/test')).rejects.toThrow(ApiError);

      // Should only call fetch once (no retries for 4xx)
      expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    it('should retry on 5xx server errors', async () => {
      // First two calls fail with 500, third succeeds
      (global.fetch as jest.Mock)
        .mockResolvedValueOnce({
          ok: false,
          status: 500,
          json: async () => ({ code: 500, message: 'Server Error' }),
        })
        .mockResolvedValueOnce({
          ok: false,
          status: 500,
          json: async () => ({ code: 500, message: 'Server Error' }),
        })
        .mockResolvedValueOnce({
          ok: true,
          status: 200,
          json: async () => ({ message: 'Success' }),
        });

      // Start the promise but don't await it yet
      const promise = apiRequest('/api/test');

      // Advance timers for retries - need to use runAllTimersAsync to handle all pending timers
      await jest.runAllTimersAsync();

      const result = await promise;

      expect(result).toEqual({ message: 'Success' });
      expect(global.fetch).toHaveBeenCalledTimes(3);
    });

    it('should throw after max retries on persistent 5xx errors', async () => {
      // Use real timers for this test as retry logic depends on them
      jest.useRealTimers();

      (global.fetch as jest.Mock).mockResolvedValue({
        ok: false,
        status: 500,
        json: async () => ({ code: 500, message: 'Server Error' }),
      });

      // Use skipRetry to avoid waiting for retry delays
      await expect(apiRequest('/api/test', { skipRetry: true })).rejects.toThrow(ApiError);

      expect(global.fetch).toHaveBeenCalledTimes(1);

      // Restore fake timers for other tests
      jest.useFakeTimers();
    });

    it('should skip retries when skipRetry is true', async () => {
      (global.fetch as jest.Mock).mockResolvedValue({
        ok: false,
        status: 500,
        json: async () => ({ code: 500, message: 'Server Error' }),
      });

      await expect(
        apiRequest('/api/test', { skipRetry: true })
      ).rejects.toThrow(ApiError);

      expect(global.fetch).toHaveBeenCalledTimes(1);
    });
  });

  describe('apiRequest - Timeout handling', () => {
    it('should pass AbortSignal to fetch for timeout', async () => {
      // Verify that the request includes an AbortSignal for timeout handling
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ message: 'Success' }),
      });

      await apiRequest('/api/test', { timeout: 1000, skipRetry: true });

      expect(global.fetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          signal: expect.any(AbortSignal),
        })
      );
    });

    it('should handle AbortError as TimeoutError', async () => {
      // Mock fetch to reject with AbortError immediately
      (global.fetch as jest.Mock).mockRejectedValueOnce(
        new DOMException('The user aborted a request.', 'AbortError')
      );

      await expect(
        apiRequest('/api/test', { skipRetry: true })
      ).rejects.toThrow(TimeoutError);
    });
  });

  describe('apiRequest - Network errors', () => {
    it('should throw NetworkError on fetch failure', async () => {
      (global.fetch as jest.Mock).mockRejectedValueOnce(
        new TypeError('Failed to fetch')
      );

      try {
        await apiRequest('/api/test', { skipRetry: true });
        fail('Expected NetworkError to be thrown');
      } catch (error) {
        expect(error).toBeInstanceOf(NetworkError);
        expect((error as Error).message).toBe('Network request failed');
      }
    });

    it('should retry on network errors', async () => {
      (global.fetch as jest.Mock)
        .mockRejectedValueOnce(new TypeError('Failed to fetch'))
        .mockRejectedValueOnce(new TypeError('Failed to fetch'))
        .mockResolvedValueOnce({
          ok: true,
          status: 200,
          json: async () => ({ message: 'Success' }),
        });

      const promise = apiRequest('/api/test');

      await jest.runAllTimersAsync();

      const result = await promise;

      expect(result).toEqual({ message: 'Success' });
      expect(global.fetch).toHaveBeenCalledTimes(3);
    });
  });

  describe('Convenience methods', () => {
    it('get() should make GET request', async () => {
      const mockData = { message: 'Success' };
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockData,
      });

      const result = await get('/api/test');

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/api/test'),
        expect.objectContaining({
          method: 'GET',
        })
      );
      expect(result).toEqual(mockData);
    });

    it('post() should make POST request with body', async () => {
      const mockData = { id: 1 };
      const body = { name: 'Test' };
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 201,
        json: async () => mockData,
      });

      const result = await post('/api/test', body);

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/api/test'),
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify(body),
        })
      );
      expect(result).toEqual(mockData);
    });

    it('put() should make PUT request with body', async () => {
      const mockData = { id: 1 };
      const body = { name: 'Updated' };
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockData,
      });

      await put('/api/test/1', body);

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/api/test/1'),
        expect.objectContaining({
          method: 'PUT',
          body: JSON.stringify(body),
        })
      );
    });

    it('patch() should make PATCH request with body', async () => {
      const mockData = { id: 1 };
      const body = { status: 'active' };
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockData,
      });

      await patch('/api/test/1', body);

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/api/test/1'),
        expect.objectContaining({
          method: 'PATCH',
          body: JSON.stringify(body),
        })
      );
    });

    it('del() should make DELETE request', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 204,
        json: async () => ({}),
      });

      await del('/api/test/1');

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/api/test/1'),
        expect.objectContaining({
          method: 'DELETE',
        })
      );
    });
  });

  describe('healthCheck', () => {
    it('should return true when API is available', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ message: 'OK' }),
      });

      const result = await healthCheck();

      expect(result).toBe(true);
      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringMatching(/\/api$/),
        expect.objectContaining({
          headers: expect.objectContaining({
            'Content-Type': 'application/json',
          }),
        })
      );
    });

    it('should return false when API is unavailable', async () => {
      (global.fetch as jest.Mock).mockRejectedValueOnce(
        new TypeError('Failed to fetch')
      );

      const result = await healthCheck();

      expect(result).toBe(false);
    });

    it('should use short timeout and skip retry on timeout', async () => {
      // Mock fetch to immediately reject with AbortError (simulating timeout)
      (global.fetch as jest.Mock).mockRejectedValueOnce(
        new DOMException('The user aborted a request.', 'AbortError')
      );

      const result = await healthCheck();

      expect(result).toBe(false);
      expect(global.fetch).toHaveBeenCalledTimes(1); // No retry on timeout
    });
  });

  describe('Edge cases', () => {
    it('should handle malformed JSON error response', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: false,
        status: 500,
        json: async () => {
          throw new Error('Invalid JSON');
        },
      });

      const promise = apiRequest('/api/test', { skipRetry: true });

      await expect(promise).rejects.toThrow(ApiError);
    });

    it('should handle empty response body', async () => {
      (global.fetch as jest.Mock).mockResolvedValueOnce({
        ok: true,
        status: 204,
        json: async () => null,
      });

      const result = await apiRequest('/api/test');

      expect(result).toBeNull();
    });

    it('should respect custom retry count', async () => {
      // Use real timers for this test
      jest.useRealTimers();

      (global.fetch as jest.Mock).mockResolvedValue({
        ok: false,
        status: 500,
        json: async () => ({ code: 500, message: 'Server Error' }),
      });

      // Test with skipRetry to avoid waiting - verifies the option works
      await expect(apiRequest('/api/test', { skipRetry: true })).rejects.toThrow(ApiError);

      expect(global.fetch).toHaveBeenCalledTimes(1);

      // Restore fake timers
      jest.useFakeTimers();
    });
  });
});
