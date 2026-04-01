import { Metadata } from 'next';
import { notFound } from 'next/navigation';
import Link from 'next/link';
import { getLiveTexts } from '@/lib/api';
import type { Locale } from '@/lib/types';
import type { LiveTextStatus } from '@/lib/types/livetext';

interface LivePageProps {
  params: Promise<{
    locale: string;
  }>;
  searchParams: Promise<{
    status?: LiveTextStatus;
  }>;
}

/**
 * LiveText List Page
 *
 * Displays all active LiveText events with real-time status indicators
 */
export default async function LivePage({
  params,
  searchParams,
}: LivePageProps) {
  const { locale } = await params;
  const { status } = await searchParams;

  // Fetch LiveTexts with optional status filter
  let liveTexts;
  try {
    const filters = status ? { status } : { status: ['live', 'paused'] as LiveTextStatus[] };
    const result = await getLiveTexts(filters, {
      locale,
      cache: 'no-store', // Always fresh data for real-time events
    });
    liveTexts = result.items;
  } catch (error) {
    console.error('Error fetching LiveTexts:', error);
    notFound();
  }

  const texts = {
    ro: {
      title: 'Live Text',
      subtitle: 'Urmăriți știrile în timp real',
      all: 'Toate',
      live: 'Live',
      paused: 'Pauzat',
      ended: 'Încheiat',
      noLiveTexts: 'Nu există evenimente live în acest moment.',
      startedAt: 'Început',
      endedAt: 'Încheiat',
      active: 'Activ acum',
      viewLive: 'Vezi Live',
      statusLive: 'LIVE',
      statusPaused: 'PAUZAT',
      statusEnded: 'ÎNCHEIAT',
    },
    en: {
      title: 'Live Text',
      subtitle: 'Follow the news in real-time',
      all: 'All',
      live: 'Live',
      paused: 'Paused',
      ended: 'Ended',
      noLiveTexts: 'No live events at the moment.',
      startedAt: 'Started',
      endedAt: 'Ended',
      active: 'Active now',
      viewLive: 'View Live',
      statusLive: 'LIVE',
      statusPaused: 'PAUSED',
      statusEnded: 'ENDED',
    },
    ru: {
      title: 'Live Текст',
      subtitle: 'Следите за новостями в реальном времени',
      all: 'Все',
      live: 'В эфире',
      paused: 'Приостановлено',
      ended: 'Завершено',
      noLiveTexts: 'В данный момент нет событий в прямом эфире.',
      startedAt: 'Начато',
      endedAt: 'Завершено',
      active: 'Активно сейчас',
      viewLive: 'Смотреть',
      statusLive: 'В ЭФИРЕ',
      statusPaused: 'ПАУЗА',
      statusEnded: 'ЗАВЕРШЕНО',
    },
  };

  const t = texts[locale as keyof typeof texts] || texts.ro;

  // Helper to get status badge classes
  const getStatusBadge = (status: LiveTextStatus) => {
    switch (status) {
      case 'live':
        return {
          label: t.statusLive,
          classes: 'bg-red-600 text-white animate-pulse',
        };
      case 'paused':
        return {
          label: t.statusPaused,
          classes: 'bg-yellow-500 text-white',
        };
      case 'ended':
        return {
          label: t.statusEnded,
          classes: 'bg-surface-sunken0 text-white',
        };
      default:
        return {
          label: status.toUpperCase(),
          classes: 'bg-gray-400 text-white',
        };
    }
  };

  // Helper to format date
  const formatDate = (dateString: string | null) => {
    if (!dateString) return null;
    const date = new Date(dateString);
    return new Intl.DateTimeFormat(locale, {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(date);
  };

  return (
    <div className="container mx-auto px-4 py-8">
      {/* Page Header */}
      <div className="mb-8">
        <h1 className="text-4xl font-bold text-primary dark:text-primary-dark mb-4">
          {t.title}
        </h1>
        <p className="text-gray-600 dark:text-gray-400">{t.subtitle}</p>
      </div>

      {/* Status Filter */}
      <div className="mb-6">
        <div className="flex flex-wrap gap-2">
          <Link
            href={`/${locale}/live`}
            className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
              !status
                ? 'bg-red-600 text-white'
                : 'bg-gray-200 text-primary hover:bg-gray-300 dark:bg-gray-700 dark:text-primary-dark dark:hover:bg-gray-600'
            }`}
          >
            {t.active}
          </Link>
          <Link
            href={`/${locale}/live?status=live`}
            className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
              status === 'live'
                ? 'bg-red-600 text-white'
                : 'bg-gray-200 text-primary hover:bg-gray-300 dark:bg-gray-700 dark:text-primary-dark dark:hover:bg-gray-600'
            }`}
          >
            {t.live}
          </Link>
          <Link
            href={`/${locale}/live?status=paused`}
            className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
              status === 'paused'
                ? 'bg-red-600 text-white'
                : 'bg-gray-200 text-primary hover:bg-gray-300 dark:bg-gray-700 dark:text-primary-dark dark:hover:bg-gray-600'
            }`}
          >
            {t.paused}
          </Link>
          <Link
            href={`/${locale}/live?status=ended`}
            className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
              status === 'ended'
                ? 'bg-red-600 text-white'
                : 'bg-gray-200 text-primary hover:bg-gray-300 dark:bg-gray-700 dark:text-primary-dark dark:hover:bg-gray-600'
            }`}
          >
            {t.ended}
          </Link>
        </div>
      </div>

      {/* LiveText Grid */}
      {liveTexts.length > 0 ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {liveTexts.map((liveText) => {
            const statusBadge = getStatusBadge(liveText.status);

            return (
              <Link
                key={liveText.id}
                href={`/${locale}/live/${liveText.slug}`}
                className="block bg-surface dark:bg-surface-dark rounded-lg shadow-md hover:shadow-xl transition-shadow overflow-hidden border border-gray-200 dark:border-gray-700"
              >
                {/* Status Badge */}
                <div className="p-4 border-b border-gray-200 dark:border-gray-700">
                  <div className="flex items-center justify-between">
                    <span
                      className={`px-3 py-1 rounded-full text-xs font-bold ${statusBadge.classes}`}
                    >
                      {statusBadge.label}
                    </span>
                    {liveText.category && (
                      <span className="text-xs text-secondary dark:text-gray-400">
                        {liveText.category.title}
                      </span>
                    )}
                  </div>
                </div>

                {/* Content */}
                <div className="p-6">
                  <h2 className="text-xl font-bold text-primary dark:text-primary-dark mb-3 line-clamp-2">
                    {liveText.title}
                  </h2>

                  {liveText.description && (
                    <p className="text-gray-600 dark:text-gray-400 mb-4 line-clamp-3">
                      {liveText.description}
                    </p>
                  )}

                  {/* Metadata */}
                  <div className="space-y-2 text-sm text-secondary dark:text-gray-400">
                    {liveText.startTime && (
                      <div className="flex items-center gap-2">
                        <svg
                          className="w-4 h-4"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                          />
                        </svg>
                        <span>
                          {t.startedAt}: {formatDate(liveText.startTime)}
                        </span>
                      </div>
                    )}

                    {liveText.endTime && (
                      <div className="flex items-center gap-2">
                        <svg
                          className="w-4 h-4"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                          />
                        </svg>
                        <span>
                          {t.endedAt}: {formatDate(liveText.endTime)}
                        </span>
                      </div>
                    )}

                    <div className="flex items-center gap-2">
                      <svg
                        className="w-4 h-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                        />
                      </svg>
                      <span>{liveText.author.username}</span>
                    </div>
                  </div>

                  {/* View Button */}
                  <div className="mt-6">
                    <span className="inline-flex items-center gap-2 text-red-600 dark:text-red-400 font-semibold hover:underline">
                      {t.viewLive}
                      <svg
                        className="w-4 h-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M9 5l7 7-7 7"
                        />
                      </svg>
                    </span>
                  </div>
                </div>
              </Link>
            );
          })}
        </div>
      ) : (
        <div className="text-center py-12">
          <p className="text-gray-600 dark:text-gray-400 text-lg">
            {t.noLiveTexts}
          </p>
        </div>
      )}
    </div>
  );
}

/**
 * Generate metadata for SEO
 */
export async function generateMetadata({
  params,
}: LivePageProps): Promise<Metadata> {
  const { locale } = await params;

  const titles = {
    ro: 'Live Text - Urmăriți știrile în timp real',
    en: 'Live Text - Follow the news in real-time',
    ru: 'Live Текст - Следите за новостями в реальном времени',
  };

  const descriptions = {
    ro: 'Urmăriți evenimentele importante în timp real cu Live Text de la Deschide News.',
    en: 'Follow important events in real-time with Live Text from Deschide News.',
    ru: 'Следите за важными событиями в реальном времени с Live Text от Deschide News.',
  };

  const title = titles[locale as keyof typeof titles] || titles.ro;
  const description = descriptions[locale as keyof typeof descriptions] || descriptions.ro;

  return {
    title,
    description,
    alternates: {
      canonical: `/${locale}/live`,
      languages: {
        ro: '/live',
        en: '/en/live',
        ru: '/ru/live',
      },
    },
    openGraph: {
      title,
      description,
      locale: locale === 'ro' ? 'ro_RO' : locale === 'en' ? 'en_US' : 'ru_RU',
      type: 'website',
    },
  };
}

/**
 * Disable caching for real-time data
 */
export const revalidate = 0;
export const dynamic = 'force-dynamic';
