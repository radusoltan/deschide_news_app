import { Metadata } from 'next';
import ArchiveBrowser from '@/components/archive/ArchiveBrowser';
import type { Locale } from '@/lib/types';

interface ArchivePageProps {
  params: Promise<{
    locale: string;
  }>;
}

/**
 * Archive Page
 *
 * Displays archived articles with a vintage newspaper aesthetic.
 * Features year and category filters, pagination, and proper SEO metadata.
 * Uses noindex to prevent search engines from indexing old archived content.
 */
export default async function ArchivePage({ params }: ArchivePageProps) {
  const { locale } = await params;

  // Pre-fetch initial stats for SSR
  let initialStats = null;
  try {
    const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? '';
    const response = await fetch(`${apiUrl}/api/archive/stats?locale=${locale}`, {
      headers: {
        Accept: 'application/json',
        'Accept-Language': locale,
      },
      next: { revalidate: 3600 }, // Revalidate every hour
    });

    if (response.ok) {
      initialStats = await response.json();
    }
  } catch (error) {
    console.error('Error fetching initial archive stats:', error);
  }

  return <ArchiveBrowser locale={locale as Locale} initialStats={initialStats} />;
}

/**
 * Generate metadata for SEO
 * Uses noindex to prevent old archived content from appearing in search results
 * while keeping follow to maintain link equity
 */
export async function generateMetadata({
  params,
}: ArchivePageProps): Promise<Metadata> {
  const { locale } = await params;

  const meta = {
    ro: {
      title: 'Arhiva de Știri | Deschide News',
      description:
        'Explorați arhiva completă a articolelor publicate pe Deschide News. Căutați în trecut prin anii de jurnalism de calitate.',
    },
    en: {
      title: 'News Archive | Deschide News',
      description:
        'Explore the complete archive of articles published on Deschide News. Search through years of quality journalism.',
    },
    ru: {
      title: 'Архив новостей | Deschide News',
      description:
        'Изучите полный архив статей, опубликованных на Deschide News. Поиск по годам качественной журналистики.',
    },
  };

  const { title, description } =
    meta[locale as keyof typeof meta] || meta.ro;

  return {
    title,
    description,
    robots: {
      index: false, // Prevent indexing of old archived content
      follow: true, // But follow links for link equity
      googleBot: {
        index: false,
        follow: true,
      },
    },
    alternates: {
      canonical: `/${locale}/archive`,
      languages: {
        'ro-MD': '/ro/archive',
        ro: '/ro/archive',
        en: '/en/archive',
        ru: '/ru/archive',
        'x-default': '/ro/archive',
      },
    },
    openGraph: {
      title,
      description,
      locale: locale === 'ro' ? 'ro_RO' : locale === 'en' ? 'en_US' : 'ru_RU',
      type: 'website',
      siteName: 'Deschide News',
    },
  };
}

/**
 * Revalidate every hour for ISR
 */
export const revalidate = 3600;
