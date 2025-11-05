'use client';

/**
 * Category Navigation Component
 * Displays category menu for site navigation
 * - Horizontal menu on desktop
 * - Dropdown menu on mobile
 * - Highlights current category
 */

import { useState } from 'react';
import Link from 'next/link';
import type { Locale } from '@/lib/types';
import type { Category } from '@/lib/types/article';

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
  const [isOpen, setIsOpen] = useState(false);

  if (!categories || categories.length === 0) {
    return null;
  }

  const toggleDropdown = () => {
    setIsOpen(!isOpen);
  };

  return (
    <nav className={`${className}`} aria-label="Category Navigation">
      {/* Mobile Dropdown */}
      <div className="md:hidden">
        <button
          onClick={toggleDropdown}
          className="w-full flex items-center justify-between px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors"
          aria-expanded={isOpen}
          aria-label="Toggle category menu"
        >
          <span>
            {currentCategorySlug
              ? categories.find((cat) => cat.slug === currentCategorySlug)?.title || 'Categories'
              : 'Categories'}
          </span>
          <svg
            className={`w-5 h-5 transition-transform ${isOpen ? 'rotate-180' : ''}`}
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

        {isOpen && (
          <div className="mt-2 bg-white border border-gray-300 rounded-lg shadow-lg overflow-hidden">
            {categories.map((category) => {
              const isActive = category.slug === currentCategorySlug;
              return (
                <Link
                  key={category.id}
                  href={`/${locale}/${category.slug}`}
                  className={`block px-4 py-3 hover:bg-gray-100 transition-colors ${
                    isActive
                      ? 'bg-red-50 text-red-600 font-semibold border-l-4 border-red-600'
                      : 'text-gray-700'
                  }`}
                  onClick={() => setIsOpen(false)}
                >
                  {category.title}
                </Link>
              );
            })}
          </div>
        )}
      </div>

      {/* Desktop Horizontal Menu */}
      <div className="hidden md:block">
        <ul className="flex flex-wrap items-center gap-1">
          {categories.map((category) => {
            const isActive = category.slug === currentCategorySlug;
            return (
              <li key={category.id}>
                <Link
                  href={`/${locale}/${category.slug}`}
                  className={`inline-block px-4 py-2 rounded-lg font-medium transition-colors ${
                    isActive
                      ? 'bg-red-600 text-white'
                      : 'text-gray-700 hover:bg-gray-100 hover:text-red-600'
                  }`}
                >
                  {category.title}
                </Link>
              </li>
            );
          })}
        </ul>
      </div>
    </nav>
  );
}
