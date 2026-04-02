/**
 * Global Schema.org Structured Data
 *
 * Organization, WebSite, and other global schemas
 * These should be included on every page or in the root layout
 */

const SITE_NAME = process.env.NEXT_PUBLIC_APP_NAME || 'Deschide News';
const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL ?? '';

/**
 * NewsMediaOrganization Schema
 * Enhanced organization schema for news publishers
 */
export interface NewsMediaOrganizationSchema {
  '@context': 'https://schema.org';
  '@type': 'NewsMediaOrganization';
  name: string;
  url: string;
  logo: {
    '@type': 'ImageObject';
    url: string;
    width?: number;
    height?: number;
  };
  description?: string;
  sameAs?: string[]; // Social media profiles
  address?: {
    '@type': 'PostalAddress';
    addressCountry: string;
    addressLocality?: string;
  };
  foundingDate?: string;
  contactPoint?: {
    '@type': 'ContactPoint';
    contactType: string;
    email?: string;
  };
}

export function generateNewsMediaOrganizationSchema(
  locale: 'ro' | 'en' | 'ru'
): NewsMediaOrganizationSchema {
  const descriptions = {
    ro: 'Portal de știri multilingv - Română, English, Русский',
    en: 'Multilingual news portal - Romanian, English, Russian',
    ru: 'Многоязычный новостной портал - Румынский, Английский, Русский',
  };

  return {
    '@context': 'https://schema.org',
    '@type': 'NewsMediaOrganization',
    name: SITE_NAME,
    url: SITE_URL,
    logo: {
      '@type': 'ImageObject',
      url: `${SITE_URL}/logo.png`,
      width: 512,
      height: 512,
    },
    description: descriptions[locale],
    sameAs: [
      'https://facebook.com/deschide',
      'https://twitter.com/deschide',
      'https://youtube.com/@deschide',
      'https://instagram.com/deschide',
    ],
    address: {
      '@type': 'PostalAddress',
      addressCountry: 'MD', // Moldova
    },
    // foundingDate: '2025-01-01',
    contactPoint: {
      '@type': 'ContactPoint',
      contactType: 'customer service',
      // email: 'contact@deschide.md',
    },
  };
}

/**
 * WebSite Schema with Search Action
 * Enables site search in Google
 */
export interface WebSiteSchema {
  '@context': 'https://schema.org';
  '@type': 'WebSite';
  name: string;
  url: string;
  description?: string;
  inLanguage: string[];
  publisher: {
    '@type': 'Organization';
    name: string;
  };
  potentialAction?: {
    '@type': 'SearchAction';
    target: {
      '@type': 'EntryPoint';
      urlTemplate: string;
    };
    'query-input': string;
  };
}

export function generateWebSiteSchema(
  locale: 'ro' | 'en' | 'ru'
): WebSiteSchema {
  const descriptions = {
    ro: 'Portal de știri și informații în limba română, engleză și rusă',
    en: 'News and information portal in Romanian, English and Russian',
    ru: 'Новостной и информационный портал на румынском, английском и русском языках',
  };

  const localePrefix = locale === 'ro' ? '' : `${locale}/`;

  return {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: SITE_NAME,
    url: `${SITE_URL}/${localePrefix}`,
    description: descriptions[locale],
    inLanguage: ['ro-RO', 'en-US', 'ru-RU'],
    publisher: {
      '@type': 'Organization',
      name: SITE_NAME,
    },
    potentialAction: {
      '@type': 'SearchAction',
      target: {
        '@type': 'EntryPoint',
        urlTemplate: `${SITE_URL}/${localePrefix}search?q={search_term_string}`,
      },
      'query-input': 'required name=search_term_string',
    },
  };
}

/**
 * ItemList Schema for article listings
 * Use on homepage, category pages, etc.
 */
export interface ItemListSchema {
  '@context': 'https://schema.org';
  '@type': 'ItemList';
  itemListElement: Array<{
    '@type': 'ListItem';
    position: number;
    url: string;
    name?: string;
  }>;
}

export function generateItemListSchema(
  items: Array<{ url: string; title: string }>,
  basePosition = 1
): ItemListSchema {
  return {
    '@context': 'https://schema.org',
    '@type': 'ItemList',
    itemListElement: items.map((item, index) => ({
      '@type': 'ListItem',
      position: basePosition + index,
      url: item.url,
      name: item.title,
    })),
  };
}

/**
 * CollectionPage Schema
 * For category pages and article listings
 */
export interface CollectionPageSchema {
  '@context': 'https://schema.org';
  '@type': 'CollectionPage';
  '@id': string;
  url: string;
  name: string;
  description?: string;
  isPartOf: {
    '@type': 'WebSite';
    '@id': string;
  };
  inLanguage: string;
}

export function generateCollectionPageSchema(
  categoryTitle: string,
  categorySlug: string,
  locale: 'ro' | 'en' | 'ru',
  description?: string
): CollectionPageSchema {
  const localePrefix = locale === 'ro' ? '' : `${locale}/`;
  const categoryUrl = `${SITE_URL}/${localePrefix}${categorySlug}`;

  return {
    '@context': 'https://schema.org',
    '@type': 'CollectionPage',
    '@id': categoryUrl,
    url: categoryUrl,
    name: categoryTitle,
    description,
    isPartOf: {
      '@type': 'WebSite',
      '@id': SITE_URL,
    },
    inLanguage: locale === 'ro' ? 'ro-RO' : locale === 'en' ? 'en-US' : 'ru-RU',
  };
}

/**
 * Generate all global schemas for root layout
 */
export function generateGlobalSchemas(
  locale: 'ro' | 'en' | 'ru'
): Array<NewsMediaOrganizationSchema | WebSiteSchema> {
  return [
    generateNewsMediaOrganizationSchema(locale),
    generateWebSiteSchema(locale),
  ];
}

// renderStructuredData is exported from structured-data.ts
