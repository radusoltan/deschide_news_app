import type { ReactNode } from 'react';
import ClientLayoutWrapper from '../(public)/components/ClientLayoutWrapper';
import { fetchCategories } from '@/lib/api/categories';
import type { Locale } from '@/lib/types';
import '../(public)/tailnews.css';

export default async function LiveLayout({
  children,
  params,
}: {
  children: ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;

  // Fetch categories for navigation
  let categories: Awaited<ReturnType<typeof fetchCategories>>['member'] = [];
  try {
    const result = await fetchCategories(locale as Locale);
    categories = result.member;
  } catch (error) {
    console.error('Failed to fetch categories for navigation:', error);
  }

  return (
    <ClientLayoutWrapper locale={locale} categories={categories}>
      {children}
    </ClientLayoutWrapper>
  );
}
