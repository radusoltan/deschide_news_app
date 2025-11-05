import type { ReactNode } from 'react';
import ClientLayoutWrapper from './components/ClientLayoutWrapper';
import './tailnews.css';

export default async function PublicLayout({
  children,
  params,
}: {
  children: ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;

  return (
    <ClientLayoutWrapper locale={locale}>
      {children}
    </ClientLayoutWrapper>
  );
}
