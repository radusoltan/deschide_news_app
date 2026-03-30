import type { ReactNode } from 'react';
import ClientLayoutWrapper from './components/ClientLayoutWrapper';
import { fetchCategories } from '@/lib/api/categories';
import { fetchPublicMenuItems } from '@/lib/api/public-menu';
import type { Locale } from '@/lib/types';
import './tailnews.css';

export default async function PublicLayout({
  children,
  params,
}: {
  children: ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;

  // Fetch categories and menu items in parallel
  const [categoriesResult, headerMenuItems, footerMenuItems] = await Promise.all([
    fetchCategories(locale as Locale).catch((error) => {
      console.error('Failed to fetch categories:', error);
      return { member: [] };
    }),
    fetchPublicMenuItems('main', locale),
    fetchPublicMenuItems('footer', locale),
  ]);

  return (
    <ClientLayoutWrapper
      locale={locale}
      categories={categoriesResult.member}
      headerMenuItems={headerMenuItems}
      footerMenuItems={footerMenuItems}
    >
      {children}
    </ClientLayoutWrapper>
  );
}
