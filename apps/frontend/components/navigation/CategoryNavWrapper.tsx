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
  let categories: Awaited<ReturnType<typeof fetchFrontPageCategories>>['member'] = [];

  try {
    const result = await fetchFrontPageCategories(locale);
    categories = result.member;
  } catch (error) {
    console.error('Error fetching categories:', error);
    return null;
  }

  return (
    <CategoryNav
      categories={categories}
      locale={locale}
      currentCategorySlug={currentCategorySlug}
      className={className}
    />
  );
}
