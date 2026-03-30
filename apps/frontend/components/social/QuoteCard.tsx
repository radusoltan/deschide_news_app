'use client';

import React from 'react';
import { Logo } from '@/components/brand';
import { cn } from '@/lib/utils/cn';

interface QuoteCardProps {
  quote: string;
  author: string;
  authorTitle?: string;
  className?: string;
}

/**
 * QuoteCard - Social Media Quote/Interview Card
 *
 * DESIGN SPECIFICATIONS:
 * - Oxford Blue background (#112240) for premium feel
 * - Large decorative quote marks in Mindaro (subtle, max 10% usage per brandbook)
 * - Quote text: Poppins, white, medium size for readability
 * - Attribution: Poppins Medium with author name and optional title
 * - Logo: white variant, bottom right corner
 * - Aspect ratio: square (1:1) for social media
 *
 * @example
 * <QuoteCard
 *   quote="Moldova's future depends on transparency and accountability in government."
 *   author="Maria Ionescu"
 *   authorTitle="Former Minister of Justice"
 * />
 */
export const QuoteCard: React.FC<QuoteCardProps> = ({
  quote,
  author,
  authorTitle,
  className = '',
}) => {
  return (
    <div
      className={cn(
        'aspect-square relative',
        'bg-gradient-to-br from-brand-oxford-900 to-brand-oxford-800',
        'rounded-lg shadow-2xl overflow-hidden',
        'transition-transform duration-300 hover:scale-[1.02]',
        className
      )}
      role="article"
      aria-label={`Quote by ${author}`}
    >
      {/* Decorative background pattern */}
      <div className="absolute inset-0 overflow-hidden opacity-5">
        {/* Subtle grid pattern */}
        <div
          className="absolute inset-0"
          style={{
            backgroundImage: `linear-gradient(rgba(212, 251, 140, 0.1) 1px, transparent 1px),
                            linear-gradient(90deg, rgba(212, 251, 140, 0.1) 1px, transparent 1px)`,
            backgroundSize: '40px 40px',
          }}
        />
      </div>

      {/* Content container */}
      <div className="relative z-10 h-full flex flex-col justify-between p-6 md:p-8 lg:p-10">
        {/* Opening quote mark - decorative */}
        <div className="flex items-start">
          <svg
            viewBox="0 0 60 45"
            className="w-12 h-12 md:w-16 md:h-16 lg:w-20 lg:h-20 text-brand-mindaro-400 opacity-30"
            fill="currentColor"
            aria-hidden="true"
          >
            <path d="M6.8 27.2c-2.267 0-4.067-.734-5.4-2.2C.133 23.533-.4 21.667-.4 19.4c0-6.933 1.533-12.533 4.6-16.8C7.267-1.667 11.2-4 16-4v8c-2.4 0-4.4.933-6 2.8-1.6 1.867-2.533 4.133-2.8 6.8h6.6v13.6H6.8zm27.2 0c-2.267 0-4.067-.734-5.4-2.2-1.267-1.467-1.8-3.333-1.8-5.6 0-6.933 1.533-12.533 4.6-16.8C34.467-1.667 38.4-4 43.2-4v8c-2.4 0-4.4.933-6 2.8-1.6 1.867-2.533 4.133-2.8 6.8h6.6v13.6H34z" />
          </svg>
        </div>

        {/* Quote text - centered vertically */}
        <div className="flex-1 flex items-center">
          <blockquote
            className={cn(
              'font-body text-white',
              'text-lg md:text-xl lg:text-2xl',
              'leading-relaxed',
              'italic',
              'line-clamp-6'
            )}
          >
            {quote}
          </blockquote>
        </div>

        {/* Attribution and logo */}
        <div className="flex items-end justify-between gap-4 pt-4">
          {/* Author attribution */}
          <div className="flex flex-col flex-1">
            {/* Decorative line */}
            <div className="w-12 h-0.5 bg-brand-mindaro-400 mb-3 opacity-60" />

            <cite className="not-italic">
              <p className="font-body font-semibold text-white text-base md:text-lg leading-tight">
                {author}
              </p>
              {authorTitle && (
                <p className="font-body font-medium text-white text-sm md:text-base opacity-75 leading-tight mt-1">
                  {authorTitle}
                </p>
              )}
            </cite>
          </div>

          {/* Logo - bottom right */}
          <div className="flex-shrink-0">
            <Logo variant="white" size="sm" />
          </div>
        </div>

        {/* Closing quote mark - bottom left corner, subtle */}
        <div className="absolute bottom-4 right-4 md:bottom-6 md:right-6 lg:bottom-8 lg:right-8">
          <svg
            viewBox="0 0 60 45"
            className="w-8 h-8 md:w-12 md:h-12 text-brand-mindaro-400 opacity-20 rotate-180"
            fill="currentColor"
            aria-hidden="true"
          >
            <path d="M6.8 27.2c-2.267 0-4.067-.734-5.4-2.2C.133 23.533-.4 21.667-.4 19.4c0-6.933 1.533-12.533 4.6-16.8C7.267-1.667 11.2-4 16-4v8c-2.4 0-4.4.933-6 2.8-1.6 1.867-2.533 4.133-2.8 6.8h6.6v13.6H6.8zm27.2 0c-2.267 0-4.067-.734-5.4-2.2-1.267-1.467-1.8-3.333-1.8-5.6 0-6.933 1.533-12.533 4.6-16.8C34.467-1.667 38.4-4 43.2-4v8c-2.4 0-4.4.933-6 2.8-1.6 1.867-2.533 4.133-2.8 6.8h6.6v13.6H34z" />
          </svg>
        </div>
      </div>
    </div>
  );
};

export default QuoteCard;
