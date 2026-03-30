'use client';

import React from 'react';
import Image from 'next/image';
import { Logo } from '@/components/brand';
import { cn } from '@/lib/utils/cn';

interface BreakingCardProps {
  title: string;
  image: string;
  layout?: 'with-border' | 'no-border';
  category?: string;
  className?: string;
}

/**
 * BreakingCard - Social Media Breaking News Card
 *
 * BRANDBOOK SPECIFICATIONS (Section 4.0):
 * - Two layouts: with-border (5% margin in Oxford Blue) and no-border
 * - Image occupies 3/5 (60%) top, content 2/5 (40%) bottom
 * - Gradient overlay from transparent to Oxford Blue for readability
 * - Title: League Spartan Bold, UPPERCASE, white
 * - Logo: white variant, bottom right corner
 * - Drop shadow on text over photos
 * - Aspect ratio: square (1:1) for social media
 *
 * @example
 * <BreakingCard
 *   title="Breaking: Major Development in Politics"
 *   image="/images/breaking-news.jpg"
 *   layout="with-border"
 *   category="Politică"
 * />
 */
export const BreakingCard: React.FC<BreakingCardProps> = ({
  title,
  image,
  layout = 'with-border',
  category,
  className = '',
}) => {
  const cardContent = (
    <div className="relative w-full h-full overflow-hidden">
      {/* Image Section - 60% of height */}
      <div className="absolute top-0 left-0 right-0 h-[60%]">
        <Image
          src={image}
          alt={title}
          fill
          className="object-cover transition-transform duration-500 hover:scale-105"
          sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
          priority
        />

        {/* Gradient overlay for text readability */}
        <div className="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-[var(--color-surface-dark)]" />
      </div>

      {/* Content Section - 40% of height */}
      <div className="absolute bottom-0 left-0 right-0 h-[40%] bg-[var(--color-surface-dark)] flex flex-col justify-between p-6 md:p-8">
        {/* Category badge (if provided) */}
        {category && (
          <div className="flex items-start">
            <span className="inline-block px-3 py-1 bg-[var(--color-accent)] text-white font-sans text-xs md:text-sm font-bold tracking-wide uppercase rounded">
              {category}
            </span>
          </div>
        )}

        {/* Title with drop shadow */}
        <h2
          className={cn(
            'font-sans font-bold uppercase text-white',
            'text-xl md:text-2xl lg:text-3xl',
            'leading-tight tracking-tight',
            'drop-shadow-[0_2px_8px_rgba(0,0,0,0.8)]',
            'line-clamp-3'
          )}
        >
          {title}
        </h2>

        {/* Logo - bottom right corner */}
        <div className="flex justify-end mt-4">
          <Logo variant="white" size="sm" />
        </div>
      </div>
    </div>
  );

  // Render with border (5% margin)
  if (layout === 'with-border') {
    return (
      <div
        className={cn(
          'aspect-square bg-[var(--color-surface-dark)] p-[5%]',
          'rounded-lg shadow-2xl',
          'transition-transform duration-300 hover:scale-[1.02]',
          className
        )}
        role="article"
        aria-label={`Breaking news: ${title}`}
      >
        <div className="relative w-full h-full overflow-hidden rounded-md">
          {cardContent}
        </div>
      </div>
    );
  }

  // Render without border
  return (
    <div
      className={cn(
        'aspect-square relative',
        'rounded-lg shadow-2xl overflow-hidden',
        'transition-transform duration-300 hover:scale-[1.02]',
        className
      )}
      role="article"
      aria-label={`Breaking news: ${title}`}
    >
      {cardContent}
    </div>
  );
};

export default BreakingCard;
