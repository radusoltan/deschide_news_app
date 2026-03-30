import React from 'react';
import { cn } from '@/lib/utils/cn';

interface TextProps {
  variant?: 'body' | 'body-lg' | 'body-sm' | 'accent' | 'accent-lg';
  children: React.ReactNode;
  className?: string;
  as?: 'p' | 'span' | 'div' | 'label';
  color?: 'oxford' | 'tomato' | 'gray' | 'white';
  align?: 'left' | 'center' | 'right' | 'justify';
  weight?: 'normal' | 'medium' | 'semibold';
}

/**
 * Text Component - Deschide Brand Typography
 *
 * CRITICAL BRAND RULES:
 * - Uses Poppins font (from brandbook)
 * - Body text: Regular weight (400) for content
 * - Accent text: Medium weight (500) for UI elements, labels, meta
 * - Color options: Oxford Blue, Tomato, Gray, or White
 *
 * @example
 * <Text variant="body">Main article content goes here...</Text>
 * <Text variant="accent" color="tomato">Published 2 hours ago</Text>
 * <Text variant="body-lg" weight="medium">Introduction paragraph</Text>
 */
export const Text: React.FC<TextProps> = ({
  variant = 'body',
  children,
  className = '',
  as: Tag = 'p',
  color = 'gray',
  align = 'left',
  weight,
}) => {
  // Base styles - Poppins font
  const baseStyles = 'font-body text-crisp';

  // Typography variants from design system
  const variantStyles = {
    'body-lg': 'text-body-lg', // 19px
    'body': 'text-body', // 17px
    'body-sm': 'text-body-sm', // 15px
    'accent': 'text-accent', // 14px (Medium weight by default)
    'accent-lg': 'text-accent-lg', // 16px (Medium weight by default)
  };

  // Color options
  const colorStyles = {
    oxford: 'text-brand-oxford',
    tomato: 'text-brand-tomato',
    gray: 'text-gray-700 dark:text-gray-300',
    white: 'text-white',
  };

  // Text alignment
  const alignStyles = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
    justify: 'text-justify',
  };

  // Weight override (if needed)
  const weightStyles = weight
    ? {
        normal: 'font-normal',
        medium: 'font-medium',
        semibold: 'font-semibold',
      }[weight]
    : '';

  return (
    <Tag
      className={cn(
        baseStyles,
        variantStyles[variant],
        colorStyles[color],
        alignStyles[align],
        weightStyles,
        className
      )}
    >
      {children}
    </Tag>
  );
};

/**
 * ArticleBody - Specialized component for article content
 * Optimized for long-form reading with proper typography
 */
export const ArticleBody: React.FC<Omit<TextProps, 'variant' | 'as'>> = ({
  children,
  className = '',
  ...props
}) => {
  return (
    <div
      className={cn(
        'font-body text-body leading-relaxed text-gray-800 dark:text-gray-200',
        'prose prose-lg max-w-none',
        'prose-headings:font-heading prose-headings:uppercase',
        'prose-h2:text-h2 prose-h3:text-h3',
        'prose-p:text-body prose-p:leading-relaxed',
        'prose-a:text-brand-tomato prose-a:no-underline hover:prose-a:underline',
        'prose-strong:text-brand-oxford prose-strong:font-semibold',
        className
      )}
      {...props}
    >
      {children}
    </div>
  );
};

/**
 * Meta - Specialized component for metadata (dates, categories, author)
 */
export const Meta: React.FC<Omit<TextProps, 'variant' | 'as'>> = ({
  children,
  className = '',
  color = 'gray',
  ...props
}) => {
  return (
    <Text
      variant="accent"
      as="span"
      color={color}
      className={cn('uppercase tracking-wide', className)}
      {...props}
    >
      {children}
    </Text>
  );
};

/**
 * Quote - Specialized component for pull quotes in articles
 */
interface QuoteProps {
  children: React.ReactNode;
  author?: string;
  className?: string;
}

export const Quote: React.FC<QuoteProps> = ({ children, author, className = '' }) => {
  return (
    <blockquote
      className={cn(
        'border-l-4 border-brand-tomato pl-6 py-4 my-6',
        'bg-brand-oxford-light bg-opacity-30',
        className
      )}
    >
      <Text variant="body-lg" weight="medium" className="italic text-gray-800 dark:text-gray-200">
        {children}
      </Text>
      {author && (
        <Text variant="accent" color="oxford" className="mt-2 not-italic">
          — {author}
        </Text>
      )}
    </blockquote>
  );
};

export default Text;
