'use client';

import type { ReactNode } from 'react';
import Header from './Header';
import Footer from './Footer';
import type { Category } from '@/lib/types/article';

interface ClientLayoutWrapperProps {
  children: ReactNode;
  locale: string;
  categories?: Category[];
}

/**
 * Client Layout Wrapper
 * Wraps Header, Footer, and children in a client component boundary
 * This prevents event handler warnings in Next.js 16 when used in server layouts
 */
export default function ClientLayoutWrapper({
  children,
  locale,
  categories = [],
}: ClientLayoutWrapperProps) {
  return (
    <div className="min-h-screen flex flex-col bg-gray-50">
      <Header locale={locale} categories={categories} />
      <main className="flex-1 pt-9 sm:pt-10">{children}</main>
      <Footer locale={locale} />
    </div>
  );
}
