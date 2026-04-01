import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import ServerIntlProvider from '@/app/components/ServerIntlProvider';
import { generateHomepageMetadata, generateGlobalSchemas, renderStructuredData } from '@/lib/seo';
import WebVitals from '@/components/performance/WebVitals';
import ServiceWorkerRegistrar from '@/components/pwa/ServiceWorkerRegistrar';
import { golosText, notoSerif } from '../fonts';
import '../globals.css';

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
    <html lang={locale} className={`${golosText.variable} ${notoSerif.variable}`} suppressHydrationWarning>
      <head>
        {/* Prevent flash of wrong theme */}
        <script
          dangerouslySetInnerHTML={{
            __html: `(function(){try{var t=localStorage.getItem('theme-preference');if(t==='dark'){document.documentElement.setAttribute('data-theme','dark')}else if(t==='light'){document.documentElement.setAttribute('data-theme','light')}else if(window.matchMedia('(prefers-color-scheme:dark)').matches){document.documentElement.setAttribute('data-theme','dark')}}catch(e){}})()`,
          }}
        />

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

        {/* Preconnect to Google Fonts */}
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
      <body className="antialiased font-sans">
        <WebVitals />
        <ServiceWorkerRegistrar />
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
