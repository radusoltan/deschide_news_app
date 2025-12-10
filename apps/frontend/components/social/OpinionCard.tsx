'use client';

import React from 'react';
import Image from 'next/image';
import { Logo } from '@/components/brand';
import { cn } from '@/lib/utils/cn';

interface OpinionCardProps {
  title: string;
  author: {
    name: string;
    photo: string;
    title: string;
  };
  badge?: string;
  className?: string;
}

/**
 * OpinionCard - Social Media Opinion/Editorial Card
 *
 * BRANDBOOK SPECIFICATIONS (Section 4.1):
 * - Tomato background (#F05E45) for opinion/editorial content
 * - Decorative background using logo shapes (circles/abstract elements)
 * - Badge: "Opinia Este" or similar editorial marker
 * - Title: League Spartan Bold, UPPERCASE, white
 * - Author section with circular photo (white border), name, and title
 * - Logo: white variant, bottom right corner
 * - Aspect ratio: square (1:1) for social media
 *
 * @example
 * <OpinionCard
 *   title="Why Moldova Needs Political Reform Now"
 *   author={{
 *     name: "Ion Popescu",
 *     photo: "/images/authors/ion-popescu.jpg",
 *     title: "Political Analyst"
 *   }}
 *   badge="Opinia Este"
 * />
 */
export const OpinionCard: React.FC<OpinionCardProps> = ({
  title,
  author,
  badge = 'Opinie',
  className = '',
}) => {
  return (
    <div
      className={cn(
        'aspect-square relative',
        'bg-gradient-to-br from-brand-tomato-500 to-brand-tomato-600',
        'rounded-lg shadow-2xl overflow-hidden',
        'transition-transform duration-300 hover:scale-[1.02]',
        className
      )}
      role="article"
      aria-label={`Opinion: ${title} by ${author.name}`}
    >
      {/* Decorative background shapes using logo circles */}
      <div className="absolute inset-0 overflow-hidden opacity-10">
        {/* Large circle - top right */}
        <div className="absolute -top-20 -right-20 w-64 h-64 rounded-full border-8 border-white" />
        {/* Medium circle - bottom left */}
        <div className="absolute -bottom-16 -left-16 w-48 h-48 rounded-full border-8 border-white" />
        {/* Small circle - top left */}
        <div className="absolute top-12 left-12 w-24 h-24 rounded-full bg-white" />
        {/* Abstract shape - center */}
        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-40 h-40 rounded-full border-4 border-white opacity-50" />
      </div>

      {/* Content container with padding */}
      <div className="relative z-10 h-full flex flex-col justify-between p-6 md:p-8">
        {/* Badge */}
        <div className="flex items-start">
          <span
            className={cn(
              'inline-block px-4 py-2',
              'bg-white text-brand-tomato-600',
              'font-heading font-bold uppercase',
              'text-xs md:text-sm tracking-wide',
              'rounded-full shadow-lg'
            )}
          >
            {badge}
          </span>
        </div>

        {/* Title - takes middle space */}
        <div className="flex-1 flex items-center">
          <h2
            className={cn(
              'font-heading font-bold uppercase text-white',
              'text-2xl md:text-3xl lg:text-4xl',
              'leading-tight tracking-tight',
              'drop-shadow-[0_2px_8px_rgba(0,0,0,0.3)]',
              'line-clamp-4'
            )}
          >
            {title}
          </h2>
        </div>

        {/* Author section and logo */}
        <div className="flex items-end justify-between gap-4">
          {/* Author info */}
          <div className="flex items-center gap-3 flex-1">
            {/* Circular author photo with white border */}
            <div className="relative w-14 h-14 md:w-16 md:h-16 flex-shrink-0">
              <div className="absolute inset-0 rounded-full bg-white p-0.5">
                <div className="relative w-full h-full rounded-full overflow-hidden">
                  <Image
                    src={author.photo}
                    alt={author.name}
                    fill
                    className="object-cover"
                    sizes="64px"
                  />
                </div>
              </div>
            </div>

            {/* Author name and title */}
            <div className="flex flex-col">
              <p className="font-body font-semibold text-white text-sm md:text-base leading-tight">
                {author.name}
              </p>
              <p className="font-body font-medium text-white text-xs md:text-sm opacity-90 leading-tight mt-1">
                {author.title}
              </p>
            </div>
          </div>

          {/* Logo - bottom right */}
          <div className="flex-shrink-0">
            <Logo variant="white" size="sm" />
          </div>
        </div>
      </div>
    </div>
  );
};

export default OpinionCard;
