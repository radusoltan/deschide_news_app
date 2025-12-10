/**
 * Type definitions for Social Media Card Components
 */

/**
 * Base props shared by all social media cards
 */
export interface BaseSocialCardProps {
  /** Additional CSS classes */
  className?: string;
}

/**
 * Breaking News Card Props
 */
export interface BreakingCardProps extends BaseSocialCardProps {
  /** Article title (will be displayed in UPPERCASE) */
  title: string;
  /** Image URL or path */
  image: string;
  /** Card layout variant */
  layout?: 'with-border' | 'no-border';
  /** Optional category badge text */
  category?: string;
}

/**
 * Author information for Opinion Card
 */
export interface OpinionCardAuthor {
  /** Author full name */
  name: string;
  /** Author photo URL (will be displayed in circular frame) */
  photo: string;
  /** Author job title or role */
  title: string;
}

/**
 * Opinion/Editorial Card Props
 */
export interface OpinionCardProps extends BaseSocialCardProps {
  /** Opinion title (will be displayed in UPPERCASE) */
  title: string;
  /** Author information */
  author: OpinionCardAuthor;
  /** Badge text (e.g., "Opinie", "Opinia Este", "Editorial") */
  badge?: string;
}

/**
 * Quote/Interview Card Props
 */
export interface QuoteCardProps extends BaseSocialCardProps {
  /** Quote text (will be displayed in italic) */
  quote: string;
  /** Person being quoted */
  author: string;
  /** Optional author title or role */
  authorTitle?: string;
}

/**
 * Social Media Platform Types
 */
export type SocialMediaPlatform =
  | 'facebook'
  | 'twitter'
  | 'linkedin'
  | 'instagram'
  | 'whatsapp'
  | 'telegram';

/**
 * Export configuration for social media cards
 */
export interface SocialCardExportConfig {
  /** Target platform */
  platform: SocialMediaPlatform;
  /** Export width in pixels */
  width: number;
  /** Export height in pixels */
  height: number;
  /** Pixel ratio for high-DPI displays */
  pixelRatio?: number;
  /** Image format */
  format?: 'png' | 'jpeg' | 'webp';
  /** Quality (0-100) for lossy formats */
  quality?: number;
}

/**
 * Standard export sizes for social media platforms
 */
export const SOCIAL_EXPORT_SIZES: Record<SocialMediaPlatform, { width: number; height: number }> = {
  instagram: { width: 1080, height: 1080 },
  facebook: { width: 1200, height: 1200 },
  twitter: { width: 1200, height: 1200 },
  linkedin: { width: 1200, height: 1200 },
  whatsapp: { width: 1080, height: 1080 },
  telegram: { width: 1080, height: 1080 },
};

/**
 * Default export configuration
 */
export const DEFAULT_EXPORT_CONFIG: Omit<SocialCardExportConfig, 'platform'> = {
  width: 1200,
  height: 1200,
  pixelRatio: 2,
  format: 'png',
  quality: 95,
};
