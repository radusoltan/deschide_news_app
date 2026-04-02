/**
 * SEO Configuration
 * Central configuration for SEO-related settings
 */

export const SEO_CONFIG = {
  // Site information
  siteName: process.env.NEXT_PUBLIC_APP_NAME || 'Deschide News',
  siteUrl: process.env.NEXT_PUBLIC_SITE_URL ?? '',

  // Default metadata
  defaultTitle: 'Deschide News - Știri de ultimă oră',
  defaultDescription: 'Cele mai recente știri din România și din lume. Politică, economie, sport, cultură și multe altele.',

  // Social media
  twitter: {
    site: '@deschide',
    creator: '@deschide',
  },

  facebook: {
    appId: process.env.NEXT_PUBLIC_FACEBOOK_APP_ID || '',
  },

  // Images
  defaultOgImage: '/og-default.jpg',
  logoUrl: '/logo.png',

  // Organization info
  organization: {
    name: 'Deschide News',
    legalName: 'Deschide Media SRL',
    url: process.env.NEXT_PUBLIC_SITE_URL ?? '',
    logo: '/logo.png',
    foundingDate: '2024',
    address: {
      streetAddress: '',
      addressLocality: 'Chișinău',
      addressRegion: '',
      postalCode: '',
      addressCountry: 'MD',
    },
    contactPoint: {
      telephone: '',
      contactType: 'customer service',
      email: 'contact@deschide.md',
    },
  },

  // Locale configuration
  locales: {
    ro: {
      locale: 'ro_RO',
      language: 'ro-RO',
      name: 'Română',
    },
    en: {
      locale: 'en_US',
      language: 'en-US',
      name: 'English',
    },
    ru: {
      locale: 'ru_RU',
      language: 'ru-RU',
      name: 'Русский',
    },
  },

  // Robots configuration
  robots: {
    index: true,
    follow: true,
    googleBot: {
      index: true,
      follow: true,
      'max-video-preview': -1,
      'max-image-preview': 'large',
      'max-snippet': -1,
    },
  },

  // Verification codes
  verification: {
    google: process.env.NEXT_PUBLIC_GOOGLE_SITE_VERIFICATION || '',
    yandex: process.env.NEXT_PUBLIC_YANDEX_VERIFICATION || '',
    bing: process.env.NEXT_PUBLIC_BING_VERIFICATION || '',
  },

  // JSON-LD defaults
  jsonLd: {
    enabled: true,
    includeOrganization: true,
    includeBreadcrumbs: true,
    includeWebPage: true,
  },
};

export type SeoConfig = typeof SEO_CONFIG;
