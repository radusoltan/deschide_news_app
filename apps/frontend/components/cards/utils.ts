/**
 * Card Utilities
 * Helper functions for card components
 */

import type { Article, Category } from '@/lib/types/article';

/**
 * Map category slugs to CSS custom property names
 */
export function getSectionColor(categorySlug: string): string {
  const map: Record<string, string> = {
    'politica': 'var(--color-section-politics)',
    'politic': 'var(--color-section-politics)',
    'politics': 'var(--color-section-politics)',
    'economie': 'var(--color-section-economy)',
    'economy': 'var(--color-section-economy)',
    'cultura': 'var(--color-section-culture)',
    'culture': 'var(--color-section-culture)',
    'sport': 'var(--color-section-sport)',
    'sports': 'var(--color-section-sport)',
    'opinii': 'var(--color-section-opinion)',
    'opinion': 'var(--color-section-opinion)',
    'societate': 'var(--color-section-society)',
    'society': 'var(--color-section-society)',
    'tehnologie': 'var(--color-section-tech)',
    'tech': 'var(--color-section-tech)',
    'externe': 'var(--color-section-world)',
    'world': 'var(--color-section-world)',
    'international': 'var(--color-section-world)',
  };
  return map[categorySlug?.toLowerCase()] || 'var(--color-accent)';
}

/**
 * Get category slug from Category object or string
 */
export function getCategorySlugFromArticle(category: Category | string | undefined | null): string {
  if (!category) return '';

  if (typeof category === 'object' && category?.slug) {
    return category.slug;
  }

  if (typeof category === 'string') {
    return category;
  }

  return '';
}

/**
 * Get category title from Category object or string
 */
export function getCategoryTitle(category: Category | string | undefined | null): string {
  if (!category) return '';

  if (typeof category === 'object' && category?.title) {
    return category.title;
  }

  if (typeof category === 'string') {
    return category;
  }

  return '';
}

/**
 * Format publish date/time for display on article cards.
 * Recent articles (< 1h) show relative time, older ones show "31 mar. 07:35".
 */
export function formatRelativeTime(dateString: string, locale: string): string {
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);

  if (diffMins < 1) {
    return locale === 'ru' ? 'только что' : locale === 'en' ? 'just now' : 'acum';
  }
  if (diffMins < 60) {
    return `${diffMins} min`;
  }
  if (diffHours < 1) {
    return `${diffHours}h`;
  }

  const loc = locale === 'ru' ? 'ru-RU' : locale === 'en' ? 'en-US' : 'ro-RO';
  const day = date.getDate();
  const month = date.toLocaleDateString(loc, { month: 'short' }).replace(/\.$/, '');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');

  return `${day} ${month}. ${hours}:${minutes}`;
}

/**
 * Extract first sentence from HTML content for excerpts
 */
export function getFirstSentence(html: string, maxLength: number = 150): string {
  // Strip HTML tags
  const text = html.replace(/<[^>]*>/g, '');

  // Find first sentence
  const match = text.match(/^[^.!?]*[.!?]/);
  if (match) {
    return match[0].trim();
  }

  // Fallback to character limit
  return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
}

/**
 * Get badge variant based on article badge type
 */
export function getBadgeProps(badge: string | null | undefined) {
  if (!badge) return null;

  switch (badge.toLowerCase()) {
    case 'breaking':
      return {
        text: 'BREAKING',
        className: 'bg-[var(--color-breaking)] text-white animate-breaking-pulse'
      };
    case 'alert':
      return {
        text: 'ALERT',
        className: 'bg-[var(--color-breaking)] text-white'
      };
    case 'flash':
      return {
        text: 'FLASH',
        className: 'bg-[var(--color-breaking)] text-white'
      };
    default:
      return null;
  }
}

/**
 * Get localized badge text
 */
export function getLocalizedBadgeText(badge: string | null | undefined, locale: string): string | null {
  if (!badge) return null;

  const translations: Record<string, Record<string, string>> = {
    breaking: {
      ro: 'URGENT',
      en: 'BREAKING',
      ru: 'СРОЧНО'
    },
    alert: {
      ro: 'ALERTĂ',
      en: 'ALERT',
      ru: 'ТРЕВОГА'
    },
    flash: {
      ro: 'FLASH',
      en: 'FLASH',
      ru: 'ВСПЫШКА'
    }
  };

  return translations[badge.toLowerCase()]?.[locale] || badge.toUpperCase();
}