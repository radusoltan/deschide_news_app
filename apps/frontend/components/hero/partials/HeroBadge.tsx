'use client';

/**
 * HeroBadge Component
 * Dynamic urgency badge with variant-specific styling and animations
 */

import React from 'react';
import { HeroBadgeProps, BADGE_TRANSLATIONS, HeroVariant } from '../types';

/**
 * Lightning bolt icon for Flash news
 */
const LightningIcon: React.FC<{ className?: string }> = ({ className }) => (
  <svg
    className={className}
    width="14"
    height="14"
    viewBox="0 0 24 24"
    fill="currentColor"
  >
    <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
  </svg>
);

/**
 * Alert triangle icon for Alert news
 */
const AlertIcon: React.FC<{ className?: string }> = ({ className }) => (
  <svg
    className={className}
    width="14"
    height="14"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="2"
    strokeLinecap="round"
    strokeLinejoin="round"
  >
    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
    <line x1="12" y1="9" x2="12" y2="13" />
    <line x1="12" y1="17" x2="12.01" y2="17" />
  </svg>
);

/**
 * Star icon for Featured/Standard news
 */
const StarIcon: React.FC<{ className?: string }> = ({ className }) => (
  <svg
    className={className}
    width="14"
    height="14"
    viewBox="0 0 24 24"
    fill="currentColor"
  >
    <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" />
  </svg>
);

/**
 * Get badge styles based on variant
 */
function getBadgeClass(variant: HeroVariant): string {
  switch (variant) {
    case 'breaking':
      return 'breaking-hero-badge';
    case 'alert':
      return 'alert-hero-badge';
    case 'flash':
      return 'flash-hero-badge';
    default:
      return 'standard-hero-badge';
  }
}

/**
 * Get icon for variant
 */
function getIcon(variant: HeroVariant, size: 'sm' | 'md' | 'lg'): React.ReactNode {
  const iconClass = size === 'lg' ? 'w-4 h-4' : size === 'md' ? 'w-3.5 h-3.5' : 'w-3 h-3';

  switch (variant) {
    case 'breaking':
      // Breaking uses the pulsing dot defined in CSS
      return null;
    case 'alert':
      return <AlertIcon className={iconClass} />;
    case 'flash':
      return <LightningIcon className={iconClass} />;
    default:
      return <StarIcon className={iconClass} />;
  }
}

/**
 * Get size classes
 */
function getSizeClasses(size: 'sm' | 'md' | 'lg'): string {
  switch (size) {
    case 'lg':
      return 'text-sm px-6 py-3';
    case 'sm':
      return 'text-xs px-3 py-1.5';
    default:
      return 'text-xs px-4 py-2';
  }
}

export const HeroBadge: React.FC<HeroBadgeProps> = ({
  variant,
  locale,
  size = 'md',
  animated = true,
}) => {
  const badgeText = BADGE_TRANSLATIONS[variant]?.[locale] || BADGE_TRANSLATIONS[variant]?.en || variant.toUpperCase();
  const baseClass = getBadgeClass(variant);
  const sizeClass = getSizeClasses(size);
  const icon = getIcon(variant, size);

  return (
    <span
      className={`${baseClass} ${sizeClass} ${!animated ? 'animation-none' : ''}`}
      role="status"
      aria-live="polite"
    >
      {icon}
      <span className="font-bold tracking-wider">{badgeText}</span>
    </span>
  );
};

export default HeroBadge;
