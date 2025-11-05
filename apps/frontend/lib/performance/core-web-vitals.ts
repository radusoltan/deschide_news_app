/**
 * Core Web Vitals Monitoring
 *
 * Monitors and reports Core Web Vitals metrics:
 * - LCP (Largest Contentful Paint): Target < 2.5s
 * - FID (First Input Delay): Target < 100ms
 * - CLS (Cumulative Layout Shift): Target < 0.1
 * - TTFB (Time to First Byte): Target < 600ms
 * - FCP (First Contentful Paint): Target < 1.8s
 */

export interface WebVitalMetric {
  id: string;
  name: 'CLS' | 'FID' | 'FCP' | 'LCP' | 'TTFB' | 'INP';
  value: number;
  rating: 'good' | 'needs-improvement' | 'poor';
  delta: number;
  navigationType: string;
}

/**
 * Get rating for each metric based on Web Vitals thresholds
 */
export function getMetricRating(name: string, value: number): 'good' | 'needs-improvement' | 'poor' {
  const thresholds: Record<string, { good: number; poor: number }> = {
    LCP: { good: 2500, poor: 4000 },
    FID: { good: 100, poor: 300 },
    CLS: { good: 0.1, poor: 0.25 },
    FCP: { good: 1800, poor: 3000 },
    TTFB: { good: 600, poor: 1500 },
    INP: { good: 200, poor: 500 },
  };

  const threshold = thresholds[name];
  if (!threshold) return 'good';

  if (value <= threshold.good) return 'good';
  if (value <= threshold.poor) return 'needs-improvement';
  return 'poor';
}

/**
 * Format metric value for display
 */
export function formatMetricValue(name: string, value: number): string {
  if (name === 'CLS') {
    return value.toFixed(3);
  }
  return `${Math.round(value)}ms`;
}

/**
 * Send Web Vitals to analytics endpoint
 */
export async function sendToAnalytics(metric: WebVitalMetric): Promise<void> {
  // Only send in production
  if (process.env.NODE_ENV !== 'production') {
    console.log('[Web Vitals]', {
      name: metric.name,
      value: formatMetricValue(metric.name, metric.value),
      rating: metric.rating,
    });
    return;
  }

  const body = JSON.stringify({
    ...metric,
    url: window.location.href,
    userAgent: navigator.userAgent,
    timestamp: Date.now(),
  });

  const url = '/api/web-vitals';

  // Use `navigator.sendBeacon()` if available, falling back to `fetch()`
  if (navigator.sendBeacon) {
    navigator.sendBeacon(url, body);
  } else {
    try {
      await fetch(url, {
        body,
        method: 'POST',
        keepalive: true,
        headers: {
          'Content-Type': 'application/json',
        },
      });
    } catch (error) {
      console.error('Failed to send web vitals:', error);
    }
  }
}

/**
 * Get performance recommendations based on metrics
 */
export function getPerformanceRecommendations(metrics: Record<string, number>): string[] {
  const recommendations: string[] = [];

  if (metrics.LCP > 2500) {
    recommendations.push(
      'Optimize images and use priority loading for LCP element',
      'Reduce server response time (TTFB)',
      'Eliminate render-blocking resources'
    );
  }

  if (metrics.FID > 100) {
    recommendations.push(
      'Break up long JavaScript tasks',
      'Defer non-critical JavaScript',
      'Use web workers for heavy computations'
    );
  }

  if (metrics.CLS > 0.1) {
    recommendations.push(
      'Add width and height attributes to images',
      'Reserve space for dynamic content',
      'Avoid inserting content above existing content'
    );
  }

  if (metrics.TTFB > 600) {
    recommendations.push(
      'Optimize server response time',
      'Use CDN for static assets',
      'Implement edge caching'
    );
  }

  return recommendations;
}

/**
 * Track page load performance
 */
export function trackPageLoad(): void {
  if (typeof window === 'undefined') return;

  window.addEventListener('load', () => {
    const perfData = window.performance.timing;
    const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
    const connectTime = perfData.responseEnd - perfData.requestStart;
    const renderTime = perfData.domComplete - perfData.domLoading;

    if (process.env.NODE_ENV === 'development') {
      console.log('[Page Load Performance]', {
        'Total Load Time': `${pageLoadTime}ms`,
        'Connection Time': `${connectTime}ms`,
        'Render Time': `${renderTime}ms`,
      });
    }
  });
}
