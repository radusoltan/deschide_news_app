import type { ReactNode } from 'react';
import { headers } from 'next/headers';
import ClientLayoutWrapper from './components/ClientLayoutWrapper';
import { fetchCategories } from '@/lib/api/categories';
import { fetchPublicMenuItems } from '@/lib/api/public-menu';
import { resolveLocaleContextData } from '@/lib/server/resolve-locale-context-data';
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
  const localeAsLocale = locale as Locale;

  // Read pathname injected by proxy.ts so the resolver can prime
  // LocaleContext for the right route type before any rendering.
  const requestHeaders = await headers();
  const pathname = requestHeaders.get('x-pathname') ?? `/${locale}`;

  // Fetch categories, menu items, and resolve locale context in parallel.
  // `fetchCategories` is shared with the resolver (and the category page) —
  // React 19 fetch memoization dedupes the underlying HTTP call.
  const [categoriesResult, headerMenuItems, footerMenuItems, initialLocaleData] = await Promise.all([
    fetchCategories(localeAsLocale).catch((error) => {
      console.error('Failed to fetch categories:', error);
      return { member: [] };
    }),
    fetchPublicMenuItems('main', locale),
    fetchPublicMenuItems('footer', locale),
    resolveLocaleContextData(pathname, localeAsLocale),
  ]);

  return (
    <ClientLayoutWrapper
      locale={locale}
      categories={categoriesResult.member}
      headerMenuItems={headerMenuItems}
      footerMenuItems={footerMenuItems}
      initialLocaleData={initialLocaleData}
    >
      {children}
    </ClientLayoutWrapper>
  );
}
