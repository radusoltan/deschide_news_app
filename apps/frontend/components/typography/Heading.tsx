import React from 'react';
import { cn } from '@/lib/utils/cn';

interface HeadingProps {
  level: 1 | 2 | 3 | 4;
  children: React.ReactNode;
  className?: string;
  variant?: 'oxford' | 'tomato' | 'default';
  align?: 'left' | 'center' | 'right';
  responsive?: boolean; // Auto-adjust size on mobile
}

/**
 * Heading Component - Deschide Brand Typography
 *
 * CRITICAL BRAND RULES:
 * - Uses League Spartan Bold font (from brandbook)
 * - MUST be UPPERCASE (enforced automatically)
 * - Color options: Oxford Blue (primary), Tomato (secondary), or default
 * - Responsive sizing by default (larger on desktop, smaller on mobile)
 *
 * @example
 * <Heading level={1} variant="oxford">Breaking News</Heading>
 * <Heading level={2} variant="tomato" align="center">Latest Updates</Heading>
 */
export const Heading: React.FC<HeadingProps> = ({
  level,
  children,
  className = '',
  variant = 'default',
  align = 'left',
  responsive = true,
}) => {
  const Tag = `h${level}` as keyof JSX.IntrinsicElements;

  // Base styles - League Spartan MUST be uppercase
  const baseStyles = 'font-heading text-crisp'; // font-heading includes uppercase transform

  // Color variants following brandbook
  const colorVariants = {
    oxford: 'text-brand-oxford',
    tomato: 'text-brand-tomato',
    default: 'text-gray-900 dark:text-white',
  };

  // Typography scale with responsive sizing
  const sizeStyles = {
    1: responsive
      ? 'text-h1-mobile md:text-h1' // 28px mobile → 40px desktop
      : 'text-h1',
    2: responsive
      ? 'text-h2-mobile md:text-h2' // 24px mobile → 32px desktop
      : 'text-h2',
    3: responsive
      ? 'text-h3-mobile md:text-h3' // 20px mobile → 24px desktop
      : 'text-h3',
    4: responsive
      ? 'text-h4-mobile md:text-h4' // 18px mobile → 20px desktop
      : 'text-h4',
  };

  // Text alignment
  const alignStyles = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
  };

  return (
    <Tag
      className={cn(
        baseStyles,
        colorVariants[variant],
        sizeStyles[level],
        alignStyles[align],
        className
      )}
    >
      {children}
    </Tag>
  );
};

/**
 * Hero Heading - Special variant for hero sections
 * Extra large sizing for maximum impact
 */
export const HeroHeading: React.FC<Omit<HeadingProps, 'level'>> = ({
  children,
  className = '',
  variant = 'oxford',
  align = 'left',
  responsive = true,
}) => {
  const baseStyles = 'font-heading text-crisp';

  const colorVariants = {
    oxford: 'text-brand-oxford',
    tomato: 'text-brand-tomato',
    default: 'text-gray-900 dark:text-white',
  };

  const sizeStyles = responsive
    ? 'text-hero-title-mobile md:text-hero-title' // 32px mobile → 48px desktop
    : 'text-hero-title';

  const alignStyles = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
  };

  return (
    <h1
      className={cn(
        baseStyles,
        colorVariants[variant],
        sizeStyles,
        alignStyles[align],
        className
      )}
    >
      {children}
    </h1>
  );
};

export default Heading;
