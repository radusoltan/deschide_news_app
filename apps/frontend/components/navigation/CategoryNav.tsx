'use client';

/**
 * Category Navigation Component
 * Displays category menu for site navigation
 * - Categories with inMenu=true appear directly in nav bar
 * - Categories with inMenu=false appear in "Stiri" dropdown
 * - Highlights current category
 */

import { useState, useRef, useEffect } from 'react';
import Link from 'next/link';
import type { Locale } from '@/lib/types';
import type { Category } from '@/lib/types/article';
import { buildCategoryUrl } from '@/lib/utils/url-builder';

interface CategoryNavProps {
  categories: Category[];
  locale: Locale;
  currentCategorySlug?: string;
  className?: string;
}

export default function CategoryNav({
  categories,
  locale,
  currentCategorySlug,
  className = '',
}: CategoryNavProps) {
  const [isMobileOpen, setIsMobileOpen] = useState(false);
  const [isStiriDropdownOpen, setIsStiriDropdownOpen] = useState(false);
  const dropdownRef = useRef<HTMLLIElement>(null);

  // Close dropdown when clicking outside
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsStiriDropdownOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  if (!categories || categories.length === 0) {
    return null;
  }

  // Separate categories into menu items and dropdown items
  const menuItems = categories.filter((cat) => cat.inMenu === true);
  const dropdownItems = categories.filter((cat) => cat.inMenu !== true);

  const toggleMobileDropdown = () => {
    setIsMobileOpen(!isMobileOpen);
  };

  const toggleStiriDropdown = () => {
    setIsStiriDropdownOpen(!isStiriDropdownOpen);
  };

  // Check if current category is in the dropdown
  const isCurrentInDropdown = dropdownItems.some((cat) => cat.slug === currentCategorySlug);

  return (
    <nav className={`${className}`} aria-label="Category Navigation">
      {/* Mobile Dropdown */}
      <div className="md:hidden">
        <button
          onClick={toggleMobileDropdown}
          className="w-full flex items-center justify-between px-4 py-2 bg-[var(--color-surface-elevated)] border border-[var(--color-border)] rounded-lg text-[var(--color-text-secondary)] font-medium hover:bg-[var(--color-surface-sunken)] transition-colors"
          aria-expanded={isMobileOpen}
          aria-label="Toggle category menu"
        >
          <span>
            {currentCategorySlug
              ? categories.find((cat) => cat.slug === currentCategorySlug)?.title || 'Categorii'
              : 'Categorii'}
          </span>
          <svg
            className={`w-5 h-5 transition-transform ${isMobileOpen ? 'rotate-180' : ''}`}
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M19 9l-7 7-7-7"
            />
          </svg>
        </button>

        {isMobileOpen && (
          <div className="mt-2 bg-[var(--color-surface-elevated)] border border-[var(--color-border)] rounded-lg shadow-lg overflow-hidden">
            {/* Menu items */}
            {menuItems.map((category) => {
              const isActive = category.slug === currentCategorySlug;
              return (
                <Link
                  key={category.id}
                  href={buildCategoryUrl(category, locale)}
                  className={`block px-4 py-3 hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-accent)] transition-colors uppercase ${isActive
                    ? 'bg-[var(--color-surface-sunken)] text-[var(--color-accent)] font-semibold border-l-4 border-[var(--color-accent)]'
                    : 'text-[var(--color-text-primary)]'
                    }`}
                  onClick={() => setIsMobileOpen(false)}
                >
                  {category.title}
                </Link>
              );
            })}

            {/* Stiri section for dropdown items */}
            {dropdownItems.length > 0 && (
              <>
                <div className="px-4 py-2 bg-[var(--color-surface-sunken)] text-[var(--color-text-tertiary)] text-sm font-semibold border-t border-[var(--color-border)]">
                  Știri
                </div>
                {dropdownItems.map((category) => {
                  const isActive = category.slug === currentCategorySlug;
                  return (
                    <Link
                      key={category.id}
                      href={buildCategoryUrl(category, locale)}
                      className={`block px-4 py-3 pl-6 hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-accent)] transition-colors uppercase ${isActive
                        ? 'bg-[var(--color-surface-sunken)] text-[var(--color-accent)] font-semibold border-l-4 border-[var(--color-accent)]'
                        : 'text-[var(--color-text-primary)]'
                        }`}
                      onClick={() => setIsMobileOpen(false)}
                    >
                      {category.title}
                    </Link>
                  );
                })}
              </>
            )}
          </div>
        )}
      </div>

      {/* Desktop Horizontal Menu */}
      <div className="hidden md:block">
        <ul className="flex flex-wrap items-center gap-1">
          {/* Direct menu items */}
          {menuItems.map((category) => {
            const isActive = category.slug === currentCategorySlug;
            return (
              <li key={category.id}>
                <Link
                  href={buildCategoryUrl(category, locale)}
                  className={`inline-block px-4 py-2 rounded-lg font-medium transition-colors uppercase ${isActive
                    ? 'bg-[var(--color-accent)] text-white'
                    : 'text-[var(--color-text-primary)] hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-accent)]'
                    }`}
                >
                  {category.title}
                </Link>
              </li>
            );
          })}

          {/* Stiri Dropdown */}
          {dropdownItems.length > 0 && (
            <li className="relative" ref={dropdownRef}>
              <button
                onClick={toggleStiriDropdown}
                className={`inline-flex items-center gap-1 px-4 py-2 rounded-lg font-medium transition-colors uppercase ${isCurrentInDropdown
                  ? 'bg-[var(--color-accent)] text-white'
                  : 'text-[var(--color-text-primary)] hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-accent)]'
                  }`}
                aria-expanded={isStiriDropdownOpen}
                aria-haspopup="true"
              >
                Știri
                <svg
                  className={`w-4 h-4 transition-transform ${isStiriDropdownOpen ? 'rotate-180' : ''}`}
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M19 9l-7 7-7-7"
                  />
                </svg>
              </button>

              {isStiriDropdownOpen && (
                <div className="absolute top-full left-0 mt-1 w-48 bg-[var(--color-surface-elevated)] border border-[var(--color-border)] rounded-lg shadow-lg z-50 overflow-hidden">
                  {dropdownItems.map((category) => {
                    const isActive = category.slug === currentCategorySlug;
                    return (
                      <Link
                        key={category.id}
                        href={buildCategoryUrl(category, locale)}
                        className={`block px-4 py-3 hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-accent)] transition-colors uppercase ${isActive
                          ? 'bg-[var(--color-surface-sunken)] text-[var(--color-accent)] font-semibold'
                          : 'text-[var(--color-text-primary)]'
                          }`}
                        onClick={() => setIsStiriDropdownOpen(false)}
                      >
                        {category.title}
                      </Link>
                    );
                  })}
                </div>
              )}
            </li>
          )}
        </ul>
      </div>
    </nav>
  );
}
