'use client';

import React from 'react';
import { SpecialArticleBanner, SpecialArticle, BadgeType } from './SpecialArticleBanner';
import { cn } from '@/lib/utils/cn';

interface SpecialArticlesSectionProps {
  articles: SpecialArticle[];
  locale: string;
  className?: string;
  /** Show only one article per badge type */
  uniqueBadges?: boolean;
  /** Maximum number of articles to show */
  maxArticles?: number;
}

// Sort order for badge types (most urgent first)
const BADGE_PRIORITY: Record<BadgeType, number> = {
  breaking: 1,
  alert: 2,
  flash: 3,
};

/**
 * SpecialArticlesSection - Clean ticker-style container for urgent news
 *
 * Design inspired by BBC, Guardian, Reuters:
 * - Compact, horizontal layout
 * - Sorted by urgency (BREAKING > ALERT > FLASH)
 * - Minimal visual noise
 * - Quick scanning experience
 */
export const SpecialArticlesSection: React.FC<SpecialArticlesSectionProps> = ({
  articles,
  locale,
  className,
  uniqueBadges = true,
  maxArticles = 3,
}) => {
  // Filter out articles without valid badges and sort by priority
  let sortedArticles = articles
    .filter(article => article.badge && BADGE_PRIORITY[article.badge])
    .sort((a, b) => {
      // First sort by badge priority
      const priorityDiff = BADGE_PRIORITY[a.badge] - BADGE_PRIORITY[b.badge];
      if (priorityDiff !== 0) return priorityDiff;

      // Then sort by publishedAt (most recent first)
      if (a.publishedAt && b.publishedAt) {
        return new Date(b.publishedAt).getTime() - new Date(a.publishedAt).getTime();
      }
      return 0;
    });

  // If uniqueBadges is true, keep only one article per badge type
  if (uniqueBadges) {
    const seenBadges = new Set<BadgeType>();
    sortedArticles = sortedArticles.filter(article => {
      if (seenBadges.has(article.badge)) {
        return false;
      }
      seenBadges.add(article.badge);
      return true;
    });
  }

  // Limit the number of articles
  sortedArticles = sortedArticles.slice(0, maxArticles);

  // Don't render if no special articles
  if (sortedArticles.length === 0) {
    return null;
  }

  return (
    <section
      className={cn(
        'xl:container mx-auto px-4',
        className
      )}
      aria-label="Urgent news"
    >
      {/* Clean card container */}
      <div className="bg-surface-sunken rounded-lg p-3 space-y-2">
        {sortedArticles.map((article, index) => (
          <div
            key={article.id}
            className="animate-fade-in"
            style={{
              animationDelay: `${index * 50}ms`,
              animationFillMode: 'backwards',
            }}
          >
            <SpecialArticleBanner
              article={article}
              locale={locale}
            />
          </div>
        ))}
      </div>
    </section>
  );
};

export default SpecialArticlesSection;
