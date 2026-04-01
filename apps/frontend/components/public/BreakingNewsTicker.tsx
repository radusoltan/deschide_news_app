'use client';

import { useState, useEffect, useCallback } from 'react';
import Link from 'next/link';
import { useIntl } from 'react-intl';
import type { Article } from '@/lib/types/article';
import { buildArticleUrl } from '@/lib/utils/url-builder';
import type { Locale } from '@/lib/types';

interface BreakingNewsTickerProps {
  locale: string;
  articles?: Article[];
}

const DISMISS_KEY = 'breaking-news-dismissed';

function getInitialDismissed(): boolean {
  if (typeof window === 'undefined') return true;
  const dismissed = localStorage.getItem(DISMISS_KEY);
  const dismissedAt = dismissed ? parseInt(dismissed, 10) : 0;
  return Date.now() - dismissedAt < 30 * 60 * 1000;
}

export function BreakingNewsTicker({ locale, articles = [] }: BreakingNewsTickerProps) {
  const intl = useIntl();
  const [isDismissed, setIsDismissed] = useState(getInitialDismissed);
  const [currentIndex, setCurrentIndex] = useState(0);

  // Auto-rotate articles
  useEffect(() => {
    if (articles.length <= 1) return;
    const interval = setInterval(() => {
      setCurrentIndex((prev) => (prev + 1) % articles.length);
    }, 5000);
    return () => clearInterval(interval);
  }, [articles.length]);

  const handleDismiss = useCallback(() => {
    setIsDismissed(true);
    localStorage.setItem(DISMISS_KEY, Date.now().toString());
  }, []);

  if (isDismissed || articles.length === 0) {
    return null;
  }

  const currentArticle = articles[currentIndex];
  const articleUrl = buildArticleUrl(currentArticle, locale as Locale);

  return (
    <div className="bg-brand-red-600 text-white relative overflow-hidden">
      <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
        <div className="flex items-center gap-3 py-2">
          {/* Pulsing indicator */}
          <div className="flex-shrink-0 flex items-center gap-2">
            <span className="relative flex h-2.5 w-2.5">
              <span className="animate-brand-pulse absolute inline-flex h-full w-full rounded-full bg-surface opacity-75" />
              <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-surface" />
            </span>
            <span className="font-heading text-xs tracking-wider hidden sm:inline">
              {intl.formatMessage({ id: 'articles.breakingNews', defaultMessage: 'Breaking news' })}
            </span>
          </div>

          {/* Divider */}
          <div className="w-px h-4 bg-surface/30 flex-shrink-0" />

          {/* Scrolling text */}
          <div className="flex-1 min-w-0 overflow-hidden">
            <Link
              href={articleUrl}
              className="block truncate text-sm font-body hover:text-brand-mindaro-400 transition-colors"
            >
              {currentArticle.title}
            </Link>
          </div>

          {/* Close button */}
          <button
            onClick={handleDismiss}
            className="flex-shrink-0 p-1 hover:bg-surface/20 rounded transition-colors"
            aria-label={intl.formatMessage({ id: 'common.close', defaultMessage: 'Close' })}
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  );
}

export default BreakingNewsTicker;
