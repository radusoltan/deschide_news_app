/**
 * Category Navigation Wrapper (Server Component)
 * Fetches categories and renders CategoryNav client component
 */

import { fetchFrontPageCategories } from '@/lib/api/categories';
import type { Locale } from '@/lib/types';
import CategoryNav from './CategoryNav';

interface CategoryNavWrapperProps {
  locale: Locale;
  currentCategorySlug?: string;
  className?: string;
}

export default async function CategoryNavWrapper({
  locale,
  currentCategorySlug,
  className = '',
}: CategoryNavWrapperProps) {
  try {
    const result = await fetchFrontPageCategories(locale);
    const categories = result.member;

    return (
      <CategoryNav
        categories={categories}
        locale={locale}
        currentCategorySlug={currentCategorySlug}
        className={className}
      />
    );
  } catch (error) {
    console.error('Error fetching categories:', error);
    return null;
  }
}
