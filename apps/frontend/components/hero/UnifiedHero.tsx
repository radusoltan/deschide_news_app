'use client';

/**
 * UnifiedHero Component
 * Main orchestrator that dynamically renders the appropriate hero template
 * based on article badge (breaking > alert > flash > standard)
 *
 * Features:
 * - Dynamic variant selection based on article badge
 * - Responsive grid layout with secondary stories sidebar
 * - Mobile-friendly horizontal scroll for secondary stories
 * - Support for forced variant (testing)
 */

import React from 'react';
import { BreakingNewsHero } from './templates/BreakingNewsHero';
import { AlertHero } from './templates/AlertHero';
import { FlashHero } from './templates/FlashHero';
import { StandardHero } from './templates/StandardHero';
import { HeroSecondaryStories } from './partials/HeroSecondaryStories';
import { UnifiedHeroProps, HeroVariant } from './types';

/**
 * Determine which template to render based on article badge
 */
function getHeroVariant(badge?: string): HeroVariant {
  if (badge === 'breaking') return 'breaking';
  if (badge === 'alert') return 'alert';
  if (badge === 'flash') return 'flash';
  return 'standard';
}

/**
 * Template component mapping
 */
const HERO_TEMPLATES = {
  breaking: BreakingNewsHero,
  alert: AlertHero,
  flash: FlashHero,
  standard: StandardHero,
} as const;

export const UnifiedHero: React.FC<UnifiedHeroProps> = ({
  primaryArticle,
  secondaryArticles = [],
  forceVariant,
  locale,
}) => {
  // Determine variant from badge or forced variant
  const variant = forceVariant || getHeroVariant(primaryArticle.badge);
  const HeroTemplate = HERO_TEMPLATES[variant];

  // Check if we have secondary articles to show
  const hasSecondaryArticles = secondaryArticles.length > 0;

  return (
    <section
      className="relative"
      aria-label="Featured news"
      data-testid="unified-hero"
    >
      {/* Main Hero Grid Layout */}
      <div className={`grid grid-cols-1 ${hasSecondaryArticles ? 'lg:grid-cols-12' : ''} gap-0`}>
        {/* Primary Hero - 8 columns on desktop when sidebar exists, full width otherwise */}
        <div className={hasSecondaryArticles ? 'lg:col-span-8' : ''}>
          <HeroTemplate article={primaryArticle} locale={locale} />
        </div>

        {/* Secondary Stories Sidebar - 4 columns on desktop */}
        {hasSecondaryArticles && (
          <div className="lg:col-span-4 hidden lg:block">
            <HeroSecondaryStories
              articles={secondaryArticles}
              locale={locale}
              variant={variant}
              layout="vertical"
            />
          </div>
        )}
      </div>

      {/* Mobile Secondary Stories (below hero) */}
      {hasSecondaryArticles && (
        <div className="lg:hidden bg-slate-900 py-4">
          <HeroSecondaryStories
            articles={secondaryArticles}
            locale={locale}
            variant={variant}
            layout="horizontal"
          />
        </div>
      )}
    </section>
  );
};

export default UnifiedHero;
