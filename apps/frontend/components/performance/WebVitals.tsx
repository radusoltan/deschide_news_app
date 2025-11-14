/**
 * Web Vitals Component
 *
 * Client-side component that monitors and reports Core Web Vitals
 * Should be included in root layout
 */

'use client';

import { useReportWebVitals } from 'next/web-vitals';
import { sendToAnalytics, getMetricRating } from '@/lib/performance/core-web-vitals';
import type { WebVitalMetric } from '@/lib/performance/core-web-vitals';

export default function WebVitals() {
  useReportWebVitals((metric) => {
    // Add rating to metric
    const metricWithRating: WebVitalMetric = {
      ...metric,
      rating: getMetricRating(metric.name, metric.value),
      navigationType: 'navigate',
    };

    // Send to analytics
    sendToAnalytics(metricWithRating);
  });

  return null;
}
