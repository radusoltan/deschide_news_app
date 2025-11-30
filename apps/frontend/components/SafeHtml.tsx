'use client';

import type { ElementType } from 'react';
import { createSafeHtml } from '@/lib/sanitize';

interface SafeHtmlProps {
  html: string;
  className?: string;
  as?: ElementType;
}

export function SafeHtml({ html, className, as: Component = 'div' }: SafeHtmlProps) {
  return (
    <Component
      className={className}
      dangerouslySetInnerHTML={createSafeHtml(html)}
    />
  );
}
