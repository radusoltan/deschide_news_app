/**
 * LoadingSpinner Component
 *
 * Premium loading spinner using Deschide brand colors (Tomato).
 * Provides smooth, performant loading indication with accessibility support.
 *
 * Features:
 * - Brand-aligned Tomato color (#F05E45)
 * - Multiple size variants (sm, md, lg, xl)
 * - GPU-accelerated animation (transform only)
 * - Accessible with ARIA labels
 * - Respects prefers-reduced-motion
 *
 * Usage:
 * ```tsx
 * <LoadingSpinner />
 * <LoadingSpinner size="lg" />
 * <LoadingSpinner size="sm" text="Loading articles..." />
 * ```
 */

import React from 'react';

export interface LoadingSpinnerProps {
  /** Size variant of the spinner */
  size?: 'sm' | 'md' | 'lg' | 'xl';
  /** Optional loading text to display */
  text?: string;
  /** Custom className for additional styling */
  className?: string;
  /** Color variant - defaults to brand tomato */
  variant?: 'tomato' | 'oxford' | 'red';
}

const sizeClasses = {
  sm: 'w-4 h-4 border-2',
  md: 'w-8 h-8 border-2',
  lg: 'w-12 h-12 border-3',
  xl: 'w-16 h-16 border-4',
};

const colorClasses = {
  tomato: 'border-brand-tomato-200 border-t-brand-tomato-500',
  oxford: 'border-brand-oxford-200 border-t-brand-oxford-900',
  red: 'border-brand-red-200 border-t-brand-red-600',
};

const textSizeClasses = {
  sm: 'text-sm',
  md: 'text-base',
  lg: 'text-lg',
  xl: 'text-xl',
};

export function LoadingSpinner({
  size = 'md',
  text,
  className = '',
  variant = 'tomato',
}: LoadingSpinnerProps) {
  return (
    <div
      className={`flex flex-col items-center justify-center gap-3 ${className}`}
      role="status"
      aria-live="polite"
    >
      {/* Spinner Circle */}
      <div
        className={`
          ${sizeClasses[size]}
          ${colorClasses[variant]}
          rounded-full
          animate-spin-smooth
          will-animate
        `}
        aria-hidden="true"
      />

      {/* Loading Text */}
      {text && (
        <p className={`${textSizeClasses[size]} text-gray-600 font-medium`}>
          {text}
        </p>
      )}

      {/* Screen reader text */}
      <span className="sr-only">
        {text || 'Loading content, please wait...'}
      </span>
    </div>
  );
}

/**
 * LoadingOverlay Component
 *
 * Full-screen or container overlay with loading spinner.
 * Use for blocking interactions during async operations.
 *
 * Usage:
 * ```tsx
 * <LoadingOverlay />
 * <LoadingOverlay text="Uploading image..." />
 * <LoadingOverlay variant="oxford" size="lg" />
 * ```
 */

export interface LoadingOverlayProps extends LoadingSpinnerProps {
  /** Background opacity (0-100) */
  opacity?: number;
  /** Full screen overlay vs container relative */
  fullScreen?: boolean;
}

export function LoadingOverlay({
  size = 'lg',
  text,
  variant = 'tomato',
  opacity = 80,
  fullScreen = false,
  className = '',
}: LoadingOverlayProps) {
  const opacityClass = `bg-surface/${opacity}`;
  const positionClass = fullScreen ? 'fixed' : 'absolute';

  return (
    <div
      className={`
        ${positionClass}
        inset-0
        ${opacityClass}
        backdrop-blur-sm
        z-50
        flex
        items-center
        justify-center
        animate-fade-in
        ${className}
      `}
      role="status"
      aria-live="assertive"
    >
      <div className="bg-surface rounded-2xl shadow-2xl p-8 animate-scale-in">
        <LoadingSpinner size={size} text={text} variant={variant} />
      </div>
    </div>
  );
}

/**
 * InlineLoader Component
 *
 * Small inline loader for buttons and compact spaces.
 *
 * Usage:
 * ```tsx
 * <button disabled>
 *   <InlineLoader className="mr-2" />
 *   Saving...
 * </button>
 * ```
 */

export interface InlineLoaderProps {
  className?: string;
  variant?: 'tomato' | 'oxford' | 'red' | 'white';
}

export function InlineLoader({
  className = '',
  variant = 'tomato'
}: InlineLoaderProps) {
  const variantColors = {
    tomato: 'border-brand-tomato-200 border-t-brand-tomato-500',
    oxford: 'border-brand-oxford-200 border-t-brand-oxford-900',
    red: 'border-brand-red-200 border-t-brand-red-600',
    white: 'border-white/30 border-t-white',
  };

  return (
    <div
      className={`
        inline-block
        w-4
        h-4
        border-2
        ${variantColors[variant]}
        rounded-full
        animate-spin-smooth
        will-animate
        ${className}
      `}
      role="status"
      aria-label="Loading"
    />
  );
}

/**
 * PulseLoader Component
 *
 * Three-dot pulse animation for subtle loading indication.
 *
 * Usage:
 * ```tsx
 * <PulseLoader />
 * <PulseLoader variant="oxford" />
 * ```
 */

export interface PulseLoaderProps {
  variant?: 'tomato' | 'oxford' | 'red';
  className?: string;
}

export function PulseLoader({
  variant = 'tomato',
  className = ''
}: PulseLoaderProps) {
  const dotColors = {
    tomato: 'bg-brand-tomato-500',
    oxford: 'bg-brand-oxford-900',
    red: 'bg-brand-red-600',
  };

  return (
    <div
      className={`flex items-center gap-1.5 ${className}`}
      role="status"
      aria-label="Loading"
    >
      <div className={`w-2 h-2 rounded-full ${dotColors[variant]} animate-brand-pulse`} />
      <div className={`w-2 h-2 rounded-full ${dotColors[variant]} animate-brand-pulse stagger-1`} />
      <div className={`w-2 h-2 rounded-full ${dotColors[variant]} animate-brand-pulse stagger-2`} />
    </div>
  );
}

export default LoadingSpinner;
