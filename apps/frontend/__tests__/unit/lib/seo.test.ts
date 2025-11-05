/**
 * SEO Utilities Tests
 */

import { getMetricRating, formatMetricValue } from '@/lib/performance/core-web-vitals';
import { truncateForSocial } from '@/lib/seo/social-media-meta';

describe('SEO Utilities', () => {
  describe('getMetricRating', () => {
    it('should rate LCP as good when < 2500ms', () => {
      expect(getMetricRating('LCP', 2000)).toBe('good');
    });

    it('should rate LCP as needs-improvement when between 2500-4000ms', () => {
      expect(getMetricRating('LCP', 3000)).toBe('needs-improvement');
    });

    it('should rate LCP as poor when > 4000ms', () => {
      expect(getMetricRating('LCP', 5000)).toBe('poor');
    });

    it('should rate FID as good when < 100ms', () => {
      expect(getMetricRating('FID', 50)).toBe('good');
    });

    it('should rate CLS as good when < 0.1', () => {
      expect(getMetricRating('CLS', 0.05)).toBe('good');
    });

    it('should handle unknown metrics', () => {
      expect(getMetricRating('UNKNOWN', 1000)).toBe('good');
    });
  });

  describe('formatMetricValue', () => {
    it('should format CLS as decimal', () => {
      expect(formatMetricValue('CLS', 0.123456)).toBe('0.123');
    });

    it('should format LCP as milliseconds', () => {
      expect(formatMetricValue('LCP', 2567.89)).toBe('2568ms');
    });

    it('should format FID as milliseconds', () => {
      expect(formatMetricValue('FID', 89.3)).toBe('89ms');
    });
  });

  describe('truncateForSocial', () => {
    it('should not truncate short text', () => {
      const text = 'Short text';
      expect(truncateForSocial(text, 'facebook')).toBe(text);
    });

    it('should truncate long text for Facebook (300 chars)', () => {
      const text = 'a'.repeat(350);
      const result = truncateForSocial(text, 'facebook');
      expect(result.length).toBe(300);
      expect(result.endsWith('...')).toBe(true);
    });

    it('should truncate long text for Twitter (200 chars)', () => {
      const text = 'a'.repeat(250);
      const result = truncateForSocial(text, 'twitter');
      expect(result.length).toBe(200);
      expect(result.endsWith('...')).toBe(true);
    });

    it('should handle empty text', () => {
      expect(truncateForSocial('', 'facebook')).toBe('');
    });
  });
});
