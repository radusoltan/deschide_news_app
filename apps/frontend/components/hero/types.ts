/**
 * Unified Hero Component Types
 * Type definitions for the premium hero system
 */

export type HeroVariant = 'breaking' | 'alert' | 'flash' | 'standard';

export interface HeroArticleImage {
  id: number;
  image: {
    path: string;
    alt?: string;
    width?: number;
    height?: number;
  };
  isFeatured: boolean;
  position?: number;
}

export interface HeroCategory {
  id: number;
  title: string;
  slug: string;
}

export interface HeroArticle {
  id: number;
  title: string;
  slug: string;
  lead?: string;
  publishedAt?: string;
  badge?: string;
  category?: HeroCategory;
  articleImages?: HeroArticleImage[];
}

export interface UnifiedHeroProps {
  /** Primary article for hero display */
  primaryArticle: HeroArticle;
  /** Secondary articles for sidebar (max 3) */
  secondaryArticles?: HeroArticle[];
  /** Override variant (useful for testing) */
  forceVariant?: HeroVariant;
  /** Locale for translations */
  locale: string;
}

export interface HeroTemplateProps {
  article: HeroArticle;
  locale: string;
  className?: string;
}

export interface HeroSecondaryStoriesProps {
  articles: HeroArticle[];
  locale: string;
  variant: HeroVariant;
  layout?: 'vertical' | 'horizontal';
}

export interface HeroBadgeProps {
  variant: HeroVariant;
  locale: string;
  size?: 'sm' | 'md' | 'lg';
  animated?: boolean;
}

// Badge translations
export const BADGE_TRANSLATIONS: Record<HeroVariant, Record<string, string>> = {
  breaking: {
    ro: 'BREAKING',
    en: 'BREAKING',
    ru: 'СРОЧНО',
  },
  alert: {
    ro: 'ALERTĂ',
    en: 'ALERT',
    ru: 'ВАЖНО',
  },
  flash: {
    ro: 'FLASH',
    en: 'FLASH',
    ru: 'МОЛНИЯ',
  },
  standard: {
    ro: 'IMPORTANT',
    en: 'FEATURED',
    ru: 'ГЛАВНОЕ',
  },
};

// CTA translations
export const CTA_TRANSLATIONS: Record<string, string> = {
  ro: 'Citește articolul complet',
  en: 'Read full story',
  ru: 'Читать полную статью',
};
