'use client';

/**
 * Article Page Error Boundary
 * Catches and displays errors that occur during article rendering
 */

import { useEffect } from 'react';
import { useParams } from 'next/navigation';
import * as Sentry from '@sentry/nextjs';
import { ArticleError } from '@/components/errors';
import type { Locale } from '@/lib/types';

interface ArticleErrorProps {
  error: Error & { digest?: string };
  reset: () => void;
}

export default function ArticlePageError({ error, reset }: ArticleErrorProps) {
  const params = useParams();
  const locale = (params?.locale as Locale) || 'ro';

  useEffect(() => {
    // Log error to console in development
    if (process.env.NODE_ENV === 'development') {
      console.error('Article error:', error);
    }

    // Send to Sentry error tracking (no-op if DSN is not configured)
    Sentry.captureException(error);
  }, [error]);

  return <ArticleError error={error} locale={locale} onRetry={reset} />;
}
