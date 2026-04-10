/**
 * Breadcrumb Component
 * Displays navigation trail: Home > Category > Article
 * Includes structured data (BreadcrumbList) for SEO
 */

import Link from 'next/link';
import type { Locale } from '@/lib/types';
import type { Article } from '@/lib/types/article';
import { buildLocalizedUrl, buildCategoryUrl } from '@/lib/utils/url-builder';

interface BreadcrumbItem {
  label: string;
  href?: string;
}

interface BreadcrumbProps {
  items: BreadcrumbItem[];
  locale: Locale;
  className?: string;
}

export default function Breadcrumb({ items, locale, className = '' }: BreadcrumbProps) {
  if (!items || items.length === 0) {
    return null;
  }

  return (
    <nav aria-label="Breadcrumb" className={`text-sm ${className}`}>
      <ol className="flex flex-wrap items-center gap-2">
        {items.map((item, index) => {
          const isLast = index === items.length - 1;

          return (
            <li key={index} className="flex items-center gap-2">
              {!isLast && item.href ? (
                <>
                  <Link
                    href={item.href}
                    className="text-gray-600 dark:text-secondary-dark hover:text-red-600 transition-colors"
                  >
                    {item.label}
                  </Link>
                  <svg
                    className="w-4 h-4 text-gray-400 dark:text-tertiary-dark"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M9 5l7 7-7 7"
                    />
                  </svg>
                </>
              ) : (
                <span className={isLast ? 'text-primary dark:text-primary-dark font-medium' : 'text-gray-600 dark:text-secondary-dark'}>
                  {item.label}
                </span>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}

/**
 * Helper function to build breadcrumb items for article pages
 */
export function buildArticleBreadcrumbs(
  article: Article,
  locale: Locale
): BreadcrumbItem[] {
  const items: BreadcrumbItem[] = [
    {
      label: locale === 'ro' ? 'Acasă' : locale === 'en' ? 'Home' : 'Главная',
      href: buildLocalizedUrl('/', locale),
    },
  ];

  if (article.category) {
    items.push({
      label: article.category.title,
      href: buildCategoryUrl(article.category, locale),
    });
  }

  items.push({
    label: article.title,
  });

  return items;
}
