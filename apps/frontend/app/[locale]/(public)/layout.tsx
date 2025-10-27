import type { ReactNode } from 'react';
import Header from './components/Header';
import Footer from './components/Footer';
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
    <div className="min-h-screen flex flex-col bg-gray-50">
      <Header locale={locale} />
      <main className="flex-1 pt-9 sm:pt-10">
        {children}
      </main>
      <Footer locale={locale} />
    </div>
  );
}
