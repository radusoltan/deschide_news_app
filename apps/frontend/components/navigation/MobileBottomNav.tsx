'use client';

/**
 * MobileBottomNav Component
 * Premium fixed bottom navigation for mobile devices
 *
 * Features:
 * - Fixed position at bottom with safe area support (iPhone notch)
 * - 5 navigation items: Home, Trending, Search, Categories, Menu
 * - Active state highlighting with brand colors
 * - Hide on scroll down, show on scroll up
 * - Touch-friendly 44px tap targets
 * - Smooth animations and premium micro-interactions
 * - Backdrop blur effect for premium feel
 */

import { useState, useEffect, useCallback } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import {
  Home,
  TrendingUp,
  Search,
  Grid3x3,
  Menu
} from 'lucide-react';
import type { Locale } from '@/lib/types';

interface MobileBottomNavProps {
  locale: Locale;
  currentPath?: string;
  onSearchClick?: () => void;
  onCategoriesClick?: () => void;
  onMenuClick?: () => void;
}

interface NavItem {
  id: string;
  icon: typeof Home;
  label: string;
  href?: string;
  onClick?: () => void;
}

export default function MobileBottomNav({
  locale,
  currentPath,
  onSearchClick,
  onCategoriesClick,
  onMenuClick,
}: MobileBottomNavProps) {
  const pathname = usePathname();
  const [isVisible, setIsVisible] = useState(true);
  const [lastScrollY, setLastScrollY] = useState(0);
  const [isMounted] = useState(true);

  // Navigation items configuration
  const navItems: NavItem[] = [
    {
      id: 'home',
      icon: Home,
      label: locale === 'ro' ? 'Acasă' : locale === 'ru' ? 'Домой' : 'Home',
      href: `/${locale}`,
    },
    {
      id: 'trending',
      icon: TrendingUp,
      label: 'Trending',
      href: `/${locale}/trending`,
    },
    {
      id: 'search',
      icon: Search,
      label: locale === 'ro' ? 'Caută' : locale === 'ru' ? 'Поиск' : 'Search',
      onClick: onSearchClick,
    },
    {
      id: 'categories',
      icon: Grid3x3,
      label: locale === 'ro' ? 'Categorii' : locale === 'ru' ? 'Категории' : 'Categories',
      onClick: onCategoriesClick,
    },
    {
      id: 'menu',
      icon: Menu,
      label: locale === 'ro' ? 'Meniu' : locale === 'ru' ? 'Меню' : 'Menu',
      onClick: onMenuClick,
    },
  ];

  // Check if nav item is active
  const isActive = useCallback(
    (item: NavItem) => {
      if (!item.href) return false;
      const activePath = currentPath || pathname;
      if (item.id === 'home') {
        // Home is active only on exact homepage
        return activePath === `/${locale}` || activePath === `/${locale}/`;
      }
      return activePath?.startsWith(item.href);
    },
    [currentPath, pathname, locale]
  );

  // Handle scroll behavior - hide on scroll down, show on scroll up
  useEffect(() => {
    let ticking = false;

    const handleScroll = () => {
      if (!ticking) {
        window.requestAnimationFrame(() => {
          const currentScrollY = window.scrollY;

          // Don't hide if we're at the top (first 100px)
          if (currentScrollY < 100) {
            setIsVisible(true);
          } else {
            // Hide when scrolling down, show when scrolling up
            if (currentScrollY > lastScrollY) {
              setIsVisible(false);
            } else {
              setIsVisible(true);
            }
          }

          setLastScrollY(currentScrollY);
          ticking = false;
        });

        ticking = true;
      }
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, [lastScrollY]);

  // Render nav item
  const renderNavItem = (item: NavItem) => {
    const Icon = item.icon;
    const active = isActive(item);

    const commonClasses = `flex flex-col items-center justify-center gap-1 min-h-[44px] min-w-[44px] px-2 transition-all duration-200 ease-out touch-target ${active ? 'text-[var(--color-accent)]' : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] active:scale-95'}`;

    const content = (
      <>
        <Icon
          className={`transition-all duration-200 ${
            active ? 'w-6 h-6 scale-110' : 'w-6 h-6'
          }`}
          strokeWidth={active ? 2.5 : 2}
        />
        <span
          className={`text-[10px] font-medium leading-none transition-all duration-200 ${
            active ? 'font-semibold' : ''
          }`}
        >
          {item.label}
        </span>
        {/* Active indicator dot */}
        {active && (
          <div className="absolute bottom-0 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-[var(--color-accent)] animate-scale-in" />
        )}
      </>
    );

    if (item.href) {
      return (
        <Link
          key={item.id}
          href={item.href}
          className={`relative ${commonClasses}`}
          aria-current={active ? 'page' : undefined}
        >
          {content}
        </Link>
      );
    }

    return (
      <button
        key={item.id}
        onClick={item.onClick}
        className={`relative ${commonClasses}`}
        aria-label={item.label}
        type="button"
      >
        {content}
      </button>
    );
  };

  return (
    <>
      {/* Spacer to prevent content from being hidden behind fixed nav */}
      <div className="h-16 md:hidden" aria-hidden="true" />

      {/* Mobile Bottom Navigation - Only visible on mobile < 768px */}
      <nav
        className={`fixed bottom-0 left-0 right-0 z-40 md:hidden transition-transform duration-300 ease-out ${isVisible ? 'translate-y-0' : 'translate-y-full'} ${isMounted ? 'animate-slide-up' : 'opacity-0'}`}
        style={{
          paddingBottom: 'env(safe-area-inset-bottom)',
        }}
        aria-label="Mobile navigation"
      >
        {/* Backdrop blur container */}
        <div className="relative">
          {/* Border top with subtle shadow */}
          <div className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[var(--color-border)] to-transparent" />

          {/* Navigation content */}
          <div className="bg-[var(--color-surface-elevated)]/95 backdrop-blur-md border-t border-[var(--color-border)] shadow-[0_-2px_16px_rgba(17,34,64,0.08)]">
            {/* Safe area padding */}
            <div className="h-16 flex items-center justify-around px-2">
              {navItems.map(renderNavItem)}
            </div>
          </div>

          {/* Bottom glow effect on active */}
          <div
            className="absolute inset-x-0 bottom-0 h-1 bg-gradient-to-r from-transparent via-[var(--color-accent)]/20 to-transparent pointer-events-none opacity-0 transition-opacity duration-300"
            style={{
              opacity: navItems.some(isActive) ? 0.6 : 0,
            }}
          />
        </div>
      </nav>

      {/* Add required animations to globals.css if not present */}
      <style jsx>{`
        @keyframes slide-up-mobile {
          from {
            opacity: 0;
            transform: translateY(100%);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }

        .animate-slide-up {
          animation: slide-up-mobile 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes scale-in-mobile {
          from {
            transform: scale(0);
            opacity: 0;
          }
          to {
            transform: scale(1);
            opacity: 1;
          }
        }

        .animate-scale-in {
          animation: scale-in-mobile 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        /* Ensure touch targets meet accessibility standards */
        .touch-target {
          -webkit-tap-highlight-color: transparent;
          user-select: none;
          -webkit-user-select: none;
        }

        /* Prevent content shift when nav hides/shows */
        @media (prefers-reduced-motion: reduce) {
          .animate-slide-up {
            animation: none;
            opacity: 1;
            transform: translateY(0);
          }

          nav {
            transition: none !important;
          }
        }
      `}</style>
    </>
  );
}
