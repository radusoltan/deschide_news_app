import type { Metadata } from 'next';
import { Inter } from 'next/font/google';
import { notFound } from 'next/navigation';
import ServerIntlProvider from '@/app/components/ServerIntlProvider';
import { generateHomepageMetadata, generateGlobalSchemas, renderStructuredData } from '@/lib/seo';
import WebVitals from '@/components/performance/WebVitals';
import '../globals.css';

const inter = Inter({
  subsets: ['latin', 'cyrillic'],
  display: 'swap',
  variable: '--font-inter',
  weight: ['400', '600', '700'], // Only load weights we use
  preload: true, // Preload font for faster initial render
  fallback: ['system-ui', 'arial'], // System font fallback
  adjustFontFallback: true, // Minimize layout shift
});

// Generate metadata dynamically based on locale
export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string }>;
}): Promise<Metadata> {
  const { locale } = await params;

  // Return default metadata for invalid/system paths
  if (locale.startsWith('.') || locale.includes('/')) {
    return {};
  }

  const validLocale = (locale === 'ro' || locale === 'en' || locale === 'ru') ? locale : 'ro';

  return generateHomepageMetadata(validLocale);
}

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
  const validLocale = (locale === 'ro' || locale === 'en' || locale === 'ru') ? locale : 'ro';
  const messages = await loadMessages(validLocale);

  // Generate global structured data schemas
  const globalSchemas = generateGlobalSchemas(validLocale);

  return (
    <html lang={locale} className={inter.variable}>
      <head>
        {/* Preconnect to external domains for performance */}
        <link rel="preconnect" href={process.env.NEXT_PUBLIC_API_URL} crossOrigin="anonymous" />
        <link rel="preconnect" href={process.env.NEXT_PUBLIC_CDN_URL} crossOrigin="anonymous" />
        <link rel="dns-prefetch" href={process.env.NEXT_PUBLIC_API_URL} />
        <link rel="dns-prefetch" href={process.env.NEXT_PUBLIC_CDN_URL} />

        {/* Preconnect to Google Fonts for Inter font */}
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />

        {/* Global Structured Data - Safe: renderStructuredData uses JSON.stringify */}
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{
            __html: renderStructuredData(globalSchemas),
          }}
        />
      </head>
      <body className="antialiased">
        <WebVitals />
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
