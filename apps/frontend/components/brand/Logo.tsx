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
  // Size mapping (height in px)
  const sizes = {
    sm: 'h-5', // 20px - minimum allowed by brandbook
    md: 'h-10', // 40px - standard size
    lg: 'h-15', // 60px - large
    xl: 'h-20', // 80px - extra large
  };

  // Font size mapping to maintain proportions
  const fontSizes = {
    sm: 'text-xl', // 20px
    md: 'text-4xl', // 36px
    lg: 'text-6xl', // 60px
    xl: 'text-7xl', // 72px
  };

  // Color variants from brandbook
  const colorVariants = {
    blue: 'text-brand-oxford', // Oxford Blue #112240
    red: 'text-brand-tomato', // Tomato #F05E45
    white: 'text-white',
  };

  // Spacing around logo (equal to height of 'S')
  const spacingClass = withSpacing ? 'p-2 md:p-4' : '';

  const logoElement = (
    <div
      className={cn(
        'font-heading font-bold tracking-tight select-none',
        sizes[size],
        fontSizes[size],
        colorVariants[variant],
        spacingClass,
        'transition-colors duration-200',
        className
      )}
      style={{
        letterSpacing: '-0.02em', // Tight spacing from brandbook
      }}
    >
      DESCHIDE
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
    white: 'bg-white text-brand-oxford',
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
