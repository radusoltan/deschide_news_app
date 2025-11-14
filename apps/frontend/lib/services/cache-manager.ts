/**
 * Cache Manager
 * In-memory cache with TTL (Time To Live) support
 */

interface CacheEntry<T> {
  data: T;
  timestamp: number;
  ttl: number;
}

interface CacheStats {
  hits: number;
  misses: number;
  sets: number;
  deletes: number;
  size: number;
}

/**
 * In-memory cache with TTL support
 */
export class CacheManager<T = unknown> {
  private cache: Map<string, CacheEntry<T>> = new Map();
  private stats: CacheStats = {
    hits: 0,
    misses: 0,
    sets: 0,
    deletes: 0,
    size: 0,
  };
  private cleanupInterval: NodeJS.Timeout | null = null;

  constructor(
    private defaultTTL: number = 300000, // 5 minutes default
    private maxSize: number = 1000,
    private enableAutoCleanup: boolean = true
  ) {
    if (enableAutoCleanup) {
      this.startAutoCleanup();
    }
  }

  /**
   * Get value from cache
   * Returns null if not found or expired
   */
  get(key: string): T | null {
    const entry = this.cache.get(key);

    if (!entry) {
      this.stats.misses++;
      return null;
    }

    // Check if expired
    if (this.isExpired(entry)) {
      this.delete(key);
      this.stats.misses++;
      return null;
    }

    this.stats.hits++;
    return entry.data;
  }

  /**
   * Set value in cache with optional TTL
   */
  set(key: string, value: T, ttl?: number): void {
    // Check size limit
    if (this.cache.size >= this.maxSize && !this.cache.has(key)) {
      // Remove oldest entry
      this.evictOldest();
    }

    const entry: CacheEntry<T> = {
      data: value,
      timestamp: Date.now(),
      ttl: ttl || this.defaultTTL,
    };

    this.cache.set(key, entry);
    this.stats.sets++;
    this.updateSize();
  }

  /**
   * Delete value from cache
   */
  delete(key: string): boolean {
    const deleted = this.cache.delete(key);
    if (deleted) {
      this.stats.deletes++;
      this.updateSize();
    }
    return deleted;
  }

  /**
   * Check if key exists and is not expired
   */
  has(key: string): boolean {
    const entry = this.cache.get(key);
    if (!entry) {
      return false;
    }

    if (this.isExpired(entry)) {
      this.delete(key);
      return false;
    }

    return true;
  }

  /**
   * Clear all cache entries
   */
  clear(): void {
    this.cache.clear();
    this.stats.size = 0;
  }

  /**
   * Get cache statistics
   */
  getStats(): CacheStats {
    return { ...this.stats };
  }

  /**
   * Get hit rate percentage
   */
  getHitRate(): number {
    const total = this.stats.hits + this.stats.misses;
    if (total === 0) {
      return 0;
    }
    return (this.stats.hits / total) * 100;
  }

  /**
   * Clean up expired entries
   */
  cleanup(): number {
    let removed = 0;
    const now = Date.now();

    for (const [key, entry] of Array.from(this.cache.entries())) {
      if (now - entry.timestamp > entry.ttl) {
        this.cache.delete(key);
        removed++;
      }
    }

    if (removed > 0) {
      this.updateSize();
    }

    return removed;
  }

  /**
   * Get all keys in cache
   */
  keys(): string[] {
    return Array.from(this.cache.keys());
  }

  /**
   * Get cache size
   */
  size(): number {
    return this.cache.size;
  }

  /**
   * Start automatic cleanup interval
   */
  private startAutoCleanup(): void {
    // Run cleanup every minute
    this.cleanupInterval = setInterval(() => {
      const removed = this.cleanup();
      if (removed > 0 && process.env.NODE_ENV === 'development') {
        console.log(`[Cache] Cleaned up ${removed} expired entries`);
      }
    }, 60000);
  }

  /**
   * Stop automatic cleanup
   */
  stopAutoCleanup(): void {
    if (this.cleanupInterval) {
      clearInterval(this.cleanupInterval);
      this.cleanupInterval = null;
    }
  }

  /**
   * Check if entry is expired
   */
  private isExpired(entry: CacheEntry<T>): boolean {
    return Date.now() - entry.timestamp > entry.ttl;
  }

  /**
   * Evict oldest entry (LRU-like)
   */
  private evictOldest(): void {
    let oldestKey: string | null = null;
    let oldestTime = Infinity;

    for (const [key, entry] of Array.from(this.cache.entries())) {
      if (entry.timestamp < oldestTime) {
        oldestTime = entry.timestamp;
        oldestKey = key;
      }
    }

    if (oldestKey) {
      this.delete(oldestKey);
    }
  }

  /**
   * Update size statistic
   */
  private updateSize(): void {
    this.stats.size = this.cache.size;
  }

  /**
   * Destroy cache and cleanup
   */
  destroy(): void {
    this.stopAutoCleanup();
    this.clear();
  }
}

/**
 * Create cache key from parameters
 */
export function createCacheKey(...parts: (string | number | undefined)[]): string {
  return parts
    .filter((part) => part !== undefined && part !== null)
    .map((part) => String(part))
    .join(':');
}

/**
 * Global cache instances
 */
export const slugCache = new CacheManager<unknown>(300000, 500); // 5 min TTL, 500 entries
export const articleCache = new CacheManager<unknown>(300000, 200); // 5 min TTL, 200 entries
export const categoryCache = new CacheManager<unknown>(600000, 100); // 10 min TTL, 100 entries

/**
 * Reset all caches (useful for testing)
 */
export function resetAllCaches(): void {
  slugCache.clear();
  articleCache.clear();
  categoryCache.clear();
}

/**
 * Get combined cache statistics
 */
export function getAllCacheStats() {
  return {
    slug: {
      stats: slugCache.getStats(),
      hitRate: slugCache.getHitRate(),
    },
    article: {
      stats: articleCache.getStats(),
      hitRate: articleCache.getHitRate(),
    },
    category: {
      stats: categoryCache.getStats(),
      hitRate: categoryCache.getHitRate(),
    },
  };
}
