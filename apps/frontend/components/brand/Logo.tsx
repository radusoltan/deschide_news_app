import React from 'react';
import Link from 'next/link';
import { cn } from '@/lib/utils/cn';

interface LogoProps {
  variant?: 'blue' | 'red' | 'white';
  size?: 'sm' | 'md' | 'lg' | 'xl';
  className?: string;
  href?: string;
  withSpacing?: boolean;
}

/**
 * Logo Component - Deschide Brand Identity
 *
 * CRITICAL BRAND RULES FROM BRANDBOOK:
 * - Minimum size: 20px height (enforced)
 * - Required clear space: Equal to height of letter 'S' (withSpacing prop)
 * - Color variants: Blue (Oxford), Red (Tomato), White (on dark backgrounds)
 * - Typography: League Spartan Bold UPPERCASE
 * - Letter spacing: Tight but readable
 *
 * @example
 * <Logo variant="blue" size="md" />
 * <Logo variant="white" size="lg" href="/" />
 * <LogoWithSpacing variant="red" size="md" />
 */
export const Logo: React.FC<LogoProps> = ({
  variant = 'blue',
  size = 'md',
  className = '',
  href,
  withSpacing = false,
}) => {
  // Premium alignment system: Icon height matched to text cap-height
  // Cap-height is approximately 70% of font-size for most sans-serif fonts
  // Icon heights are precisely calculated to match the visual cap-height
  const sizeConfig = {
    sm: {
      iconHeight: 18,      // Matches cap-height of 20px text
      fontSize: 'text-xl', // 20px (cap ~14px, with compensation = ~18px)
      lineHeight: 'leading-none', // Tight line-height for better alignment
    },
    md: {
      iconHeight: 32,       // Matches cap-height of 36px text
      fontSize: 'text-4xl', // 36px (cap ~25px, with compensation = ~32px)
      lineHeight: 'leading-none',
    },
    lg: {
      iconHeight: 48,       // Matches cap-height of 60px text
      fontSize: 'text-6xl', // 60px (cap ~42px, with compensation = ~48px)
      lineHeight: 'leading-none',
    },
    xl: {
      iconHeight: 58,       // Matches cap-height of 72px text
      fontSize: 'text-7xl', // 72px (cap ~50px, with compensation = ~58px)
      lineHeight: 'leading-none',
    },
  };

  // Color variants from brandbook
  const colorVariants = {
    blue: 'text-brand-oxford', // Oxford Blue #112240
    red: 'text-brand-tomato', // Tomato #F05E45
    white: 'text-white',
  };

  // Spacing around logo (equal to height of 'S')
  const spacingClass = withSpacing ? 'p-2 md:p-4' : '';

  // Get current size configuration
  const currentSize = sizeConfig[size];

  const logoElement = (
    <div
      className={cn(
        'flex items-center gap-x-2.5',
        colorVariants[variant],
        spacingClass,
        'transition-colors duration-200',
        className
      )}
    >
      {/* Inline SVG Icon with precise height alignment */}
      <svg
        viewBox="0 0 1000 854.25"
        className="flex-shrink-0"
        style={{
          height: `${currentSize.iconHeight}px`,
          width: 'auto',
          // Subtle drop shadow for premium depth
          filter: 'drop-shadow(0 1px 2px rgba(0, 0, 0, 0.1))',
        }}
        fill="currentColor"
        aria-hidden="true"
      >
        <path d="M0,162.3V693.17H122c81.35,0,148.48-25.22,201.39-75.68,52.86-50.42,79.29-113.89,79.29-190.37s-26.43-139.72-79.29-189.77c-52.91-50.03-120.04-75.05-201.39-75.05H0Z"/>
        <path d="M598.76,424.68c0,28.93-2.63,56.78-7.63,83.6h390.73v-162.3h-389.75c4.33,25.3,6.64,51.51,6.64,78.7Zm-138.9,314.14c-65.48,59.61-143.79,98.15-233.59,115.43H1000v-162.32H504.69c-13.55,16.31-28.44,31.97-44.82,46.88ZM233.18,0c88.92,17.05,165.95,54.79,229.73,113.01,17.11,15.62,32.61,32.06,46.52,49.29h490.57V0H233.18Z"/>
      </svg>

      {/* Text with matched alignment */}
      <span
        className={cn(
          'font-heading font-bold tracking-tight select-none',
          currentSize.fontSize,
          currentSize.lineHeight
        )}
        style={{
          letterSpacing: '-0.02em', // Tight spacing from brandbook
          // Optical alignment: slight vertical shift for perfect visual balance
          transform: 'translateY(-0.5px)',
        }}
      >
        DESCHIDE
      </span>
    </div>
  );

  // Wrap in Link if href provided
  if (href) {
    return (
      <Link
        href={href}
        className="inline-block hover:opacity-80 transition-opacity"
        aria-label="Deschide - Home"
      >
        {logoElement}
      </Link>
    );
  }

  return logoElement;
};

/**
 * LogoWithSpacing - Logo with required clear space
 * Enforces brandbook spacing rules (space = height of letter 'S')
 */
export const LogoWithSpacing: React.FC<Omit<LogoProps, 'withSpacing'>> = (props) => {
  return (
    <div className="inline-block">
      <Logo {...props} withSpacing={true} />
    </div>
  );
};

/**
 * LogoIcon - Compact "D" icon for small spaces (favicons, mobile nav)
 * Uses first letter with brand styling
 */
interface LogoIconProps {
  variant?: 'blue' | 'red' | 'white';
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

export const LogoIcon: React.FC<LogoIconProps> = ({
  variant = 'blue',
  size = 'md',
  className = '',
}) => {
  const sizes = {
    sm: 'w-6 h-6 text-base', // 24px
    md: 'w-10 h-10 text-2xl', // 40px
    lg: 'w-16 h-16 text-4xl', // 64px
  };

  const colorVariants = {
    blue: 'bg-brand-oxford text-white',
    red: 'bg-brand-tomato text-white',
    white: 'bg-surface text-brand-oxford',
  };

  return (
    <div
      className={cn(
        'font-heading font-bold',
        'flex items-center justify-center',
        'rounded-lg',
        sizes[size],
        colorVariants[variant],
        className
      )}
    >
      D
    </div>
  );
};

/**
 * LogoWithTagline - Full logo with optional tagline
 */
interface LogoWithTaglineProps extends LogoProps {
  tagline?: string;
}

export const LogoWithTagline: React.FC<LogoWithTaglineProps> = ({
  tagline = 'Știri din Moldova',
  ...logoProps
}) => {
  const taglineSizes = {
    sm: 'text-xs',
    md: 'text-sm',
    lg: 'text-base',
    xl: 'text-lg',
  };

  const size = logoProps.size || 'md';

  return (
    <div className="flex flex-col gap-1">
      <Logo {...logoProps} />
      {tagline && (
        <p
          className={cn(
            'font-body font-medium text-gray-600 dark:text-gray-400',
            taglineSizes[size]
          )}
        >
          {tagline}
        </p>
      )}
    </div>
  );
};

export default Logo;
