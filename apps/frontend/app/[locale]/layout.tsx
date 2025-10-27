import type { Metadata } from 'next';
import { Inter } from 'next/font/google';
import ServerIntlProvider from '@/app/components/ServerIntlProvider';
import '../globals.css';

const inter = Inter({
  subsets: ['latin'],
  display: 'swap',
  variable: '--font-inter',
});

export const metadata: Metadata = {
  title: {
    default: 'Deschide News',
    template: '%s | Deschide News',
  },
  description: 'Portal de știri multilingv - Română, English, Русский',
  keywords: ['știri', 'news', 'новости', 'romania', 'articole'],
};

// Load messages for the locale
async function loadMessages(locale: string) {
  const messages = await import(`@/messages/${locale}.json`);
  return messages.default;
}

export default async function LocaleLayout({
  children,
  params,
}: {
  children: React.ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const messages = await loadMessages(locale);

  return (
    <html lang={locale} className={inter.variable}>
      <body className="antialiased">
        <ServerIntlProvider messages={messages} locale={locale}>
          {children}
        </ServerIntlProvider>
      </body>
    </html>
  );
}

// Generate static params for all locales
export function generateStaticParams() {
  return [
    { locale: 'ro' },
    { locale: 'en' },
    { locale: 'ru' }
  ];
}
