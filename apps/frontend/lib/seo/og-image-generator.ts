/**
 * Open Graph Image Generator
 *
 * Utilities for generating dynamic OG images
 * Can be used with @vercel/og or similar services
 */

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL ?? '';

/**
 * OG Image Configuration
 */
export interface OGImageConfig {
  title: string;
  category?: string;
  author?: string;
  publishedAt?: string;
  imageUrl?: string;
  locale?: 'ro' | 'en' | 'ru';
}

/**
 * Generate OG image URL (for future implementation with dynamic image generation)
 *
 * This can be implemented with:
 * 1. @vercel/og - Generate images on-the-fly
 * 2. Cloudinary - Transform existing images
 * 3. Custom API endpoint - Generate images server-side
 */
export function generateDynamicOGImageUrl(config: OGImageConfig): string {
  const {
    title,
    category,
    author,
    publishedAt,
    imageUrl,
    locale = 'ro',
  } = config;

  // For now, return a placeholder
  // In production, this would call an API endpoint that generates the image

  const params = new URLSearchParams({
    title: title.substring(0, 100), // Limit to 100 chars
    ...(category && { category }),
    ...(author && { author }),
    ...(publishedAt && { date: new Date(publishedAt).toLocaleDateString(locale) }),
    ...(imageUrl && { bg: imageUrl }),
    locale,
  });

  return `${SITE_URL}/api/og?${params.toString()}`;
}

/**
 * OG Image dimensions
 * Facebook/Twitter recommended: 1200x630
 * LinkedIn: 1200x627
 * Instagram: 1080x1080 (square)
 */
export const OG_IMAGE_DIMENSIONS = {
  facebook: { width: 1200, height: 630 },
  twitter: { width: 1200, height: 675 }, // Slightly taller for Twitter
  linkedin: { width: 1200, height: 627 },
  instagram: { width: 1080, height: 1080 },
  default: { width: 1200, height: 630 },
} as const;

/**
 * Generate OG image dimensions based on platform
 */
export function getOGImageDimensions(platform: keyof typeof OG_IMAGE_DIMENSIONS = 'default') {
  return OG_IMAGE_DIMENSIONS[platform];
}

/**
 * Example implementation of a dynamic OG image generator
 * This would be placed in app/api/og/route.tsx
 *
 * ```tsx
 * import { ImageResponse } from 'next/og';
 *
 * export async function GET(request: Request) {
 *   const { searchParams } = new URL(request.url);
 *   const title = searchParams.get('title');
 *   const category = searchParams.get('category');
 *   const locale = searchParams.get('locale') || 'ro';
 *
 *   return new ImageResponse(
 *     (
 *       <div
 *         style={{
 *           display: 'flex',
 *           fontSize: 60,
 *           color: 'white',
 *           background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
 *           width: '100%',
 *           height: '100%',
 *           padding: '50px 80px',
 *           flexDirection: 'column',
 *           justifyContent: 'space-between',
 *         }}
 *       >
 *         <div style={{ display: 'flex', flexDirection: 'column' }}>
 *           {category && (
 *             <div style={{ fontSize: 30, opacity: 0.8, marginBottom: 20 }}>
 *               {category}
 *             </div>
 *           )}
 *           <div style={{ fontSize: 70, fontWeight: 'bold', lineHeight: 1.2 }}>
 *             {title}
 *           </div>
 *         </div>
 *         <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
 *           <div style={{ fontSize: 40, fontWeight: 'bold' }}>
 *             Deschide News
 *           </div>
 *           <div style={{ fontSize: 30, opacity: 0.8 }}>
 *             {locale === 'ro' && 'Știri din Moldova'}
 *             {locale === 'en' && 'News from Moldova'}
 *             {locale === 'ru' && 'Новости из Молдовы'}
 *           </div>
 *         </div>
 *       </div>
 *     ),
 *     {
 *       width: 1200,
 *       height: 630,
 *     }
 *   );
 * }
 * ```
 */

/**
 * Validate OG image URL
 */
export function isValidOGImageUrl(url: string): boolean {
  if (!url) return false;

  try {
    const parsed = new URL(url);
    const validExtensions = ['.jpg', '.jpeg', '.png', '.webp', '.gif'];
    const hasValidExtension = validExtensions.some((ext) =>
      parsed.pathname.toLowerCase().endsWith(ext)
    );

    return parsed.protocol === 'http:' || parsed.protocol === 'https:' && hasValidExtension;
  } catch {
    return false;
  }
}

/**
 * Get optimal image size for OG
 * Ensures image meets minimum requirements for social media
 */
export function getOptimalOGImageSize(
  originalWidth: number,
  originalHeight: number,
  targetWidth: number = 1200,
  targetHeight: number = 630
): { width: number; height: number } {
  const aspectRatio = originalWidth / originalHeight;
  const targetAspectRatio = targetWidth / targetHeight;

  if (aspectRatio > targetAspectRatio) {
    // Image is wider than target
    return {
      width: targetWidth,
      height: Math.round(targetWidth / aspectRatio),
    };
  } else {
    // Image is taller than target
    return {
      width: Math.round(targetHeight * aspectRatio),
      height: targetHeight,
    };
  }
}

/**
 * Generate image srcset for responsive OG images
 */
export function generateOGImageSrcSet(baseUrl: string, sizes: number[] = [600, 800, 1200]): string {
  return sizes.map((size) => `${baseUrl}?w=${size} ${size}w`).join(', ');
}
