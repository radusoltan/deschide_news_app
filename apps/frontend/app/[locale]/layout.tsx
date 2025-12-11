import type { Metadata } from 'next';
import { Inter, League_Spartan, Poppins } from 'next/font/google';
import { notFound } from 'next/navigation';
import ServerIntlProvider from '@/app/components/ServerIntlProvider';
import { generateHomepageMetadata, generateGlobalSchemas, renderStructuredData } from '@/lib/seo';
import WebVitals from '@/components/performance/WebVitals';
import '../globals.css';

// Brand fonts from Deschide brandbook
const leagueSpartan = League_Spartan({
  subsets: ['latin'],
  display: 'swap',
  variable: '--font-heading',
  weight: ['700'], // Bold only, UPPERCASE for titles
  preload: true,
  fallback: ['system-ui', 'arial'],
  adjustFontFallback: true,
});

const poppins = Poppins({
  subsets: ['latin'],
  display: 'swap',
  variable: '--font-body',
  weight: ['400', '500', '600'], // Regular, Medium, SemiBold
  preload: true,
  fallback: ['system-ui', 'arial'],
  adjustFontFallback: true,
});

// Keep Inter as fallback for system UI elements
const inter = Inter({
  subsets: ['latin', 'cyrillic'],
  display: 'swap',
  variable: '--font-inter',
  weight: ['400', '600', '700'],
  preload: false, // Secondary priority
  fallback: ['system-ui', 'arial'],
  adjustFontFallback: true,
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
    <html lang={locale} className={`${leagueSpartan.variable} ${poppins.variable} ${inter.variable}`}>
      <head>
        {/* Favicons - Using SVG favicon for modern browsers */}
        <link rel="icon" href="/favicon.ico" sizes="any" />
        <link rel="manifest" href="/site.webmanifest" />

        {/* Theme color - Deschide Oxford Blue */}
        <meta name="theme-color" content="#112240" />
        <meta name="msapplication-TileColor" content="#112240" />

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
