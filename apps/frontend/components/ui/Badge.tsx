/**
 * Badge Component
 * Reusable badge for categories, tags, and status indicators
 */

import React from 'react';
import Link from 'next/link';

interface BadgeProps {
  children: React.ReactNode;
  variant?: 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | 'info';
  size?: 'sm' | 'md' | 'lg';
  href?: string;
  className?: string;
}

const variantStyles = {
  primary: 'bg-red-600 text-white hover:bg-red-700',
  secondary: 'bg-gray-600 text-white hover:bg-gray-700',
  success: 'bg-green-600 text-white hover:bg-green-700',
  warning: 'bg-yellow-500 text-white hover:bg-yellow-600',
  danger: 'bg-red-500 text-white hover:bg-red-600',
  info: 'bg-blue-500 text-white hover:bg-blue-600',
};

const sizeStyles = {
  sm: 'text-xs px-2 py-1',
  md: 'text-sm px-3 py-1',
  lg: 'text-base px-4 py-2',
};

export default function Badge({
  children,
  variant = 'primary',
  size = 'md',
  href,
  className = '',
}: BadgeProps) {
  const baseStyles = 'inline-flex items-center font-semibold rounded transition-colors';
  const combinedStyles = `${baseStyles} ${variantStyles[variant]} ${sizeStyles[size]} ${className}`;

  if (href) {
    return (
      <Link href={href} className={combinedStyles}>
        {children}
      </Link>
    );
  }

  return <span className={combinedStyles}>{children}</span>;
}
