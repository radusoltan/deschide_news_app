'use client';

import { useEffect } from 'react';
import type { ReactNode } from 'react';
import Header from './Header';
import Footer from './Footer';
import MobileBottomNav from '@/components/navigation/MobileBottomNav';
import { PublishedLocalesProvider } from '@/lib/contexts/PublishedLocalesContext';
import type { Category } from '@/lib/types/article';
import type { MenuItem } from '@/lib/types/menu';
import type { Locale } from '@/lib/types';

interface ClientLayoutWrapperProps {
  children: ReactNode;
  locale: string;
  categories?: Category[];
  headerMenuItems?: MenuItem[];
  footerMenuItems?: MenuItem[];
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
  headerMenuItems = [],
  footerMenuItems = [],
}: ClientLayoutWrapperProps) {
  // Initialize dark mode on mount
  useEffect(() => {
    const stored = localStorage.getItem('theme-preference') as 'light' | 'dark' | 'system' || 'system';

    if (stored === 'dark') {
      document.documentElement.setAttribute('data-theme', 'dark');
    } else if (stored === 'light') {
      document.documentElement.setAttribute('data-theme', 'light');
    } else {
      // System preference
      document.documentElement.removeAttribute('data-theme');
      if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    }
  }, []);

  return (
    <PublishedLocalesProvider>
      <div className="min-h-screen flex flex-col bg-[var(--color-surface)] dark:bg-[var(--color-surface-dark)]">
        {/* Skip to content link for accessibility */}
        <a
          href="#main-content"
          className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 px-4 py-2 bg-[var(--color-accent)] text-white rounded-md font-sans text-[var(--font-size-sm)] font-medium focus:outline-none focus:ring-2 focus:ring-[var(--color-focus)] focus:ring-offset-2 focus:ring-offset-[var(--color-surface)] dark:focus:ring-offset-[var(--color-surface-dark)]"
        >
          Skip to content
        </a>

        <Header locale={locale} categories={categories} menuItems={headerMenuItems} />

        <main
          id="main-content"
          className="flex-1 pt-16 sm:pt-20 pb-16 md:pb-0"
          role="main"
        >
          {children}
        </main>

        <Footer locale={locale} menuItems={footerMenuItems} />

        {/* Mobile Bottom Navigation - Only on mobile */}
        <MobileBottomNav
          locale={locale as Locale}
          // TODO: Add proper handlers when implementing search/categories functionality
          onSearchClick={() => {
            // Implement search modal/page
            console.log('Search clicked');
          }}
          onCategoriesClick={() => {
            // Implement categories modal/page
            console.log('Categories clicked');
          }}
        />
      </div>
    </PublishedLocalesProvider>
  );
}
