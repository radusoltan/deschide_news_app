import { Metadata } from 'next';
import Link from 'next/link';
import Image from 'next/image';
import { notFound } from 'next/navigation';
import { fetchVideosByShow, fetchVideoShowBySlug, fetchVideoShows } from '@/lib/api/video-shows';
import { YouTubeVideo, VideoShow } from '@/lib/types/video';
import { cn } from '@/lib/utils/cn';

// ============================================================================
// Types
// ============================================================================

interface ShowPageProps {
  params: Promise<{ locale: string; slug: string }>;
  searchParams: Promise<{ page?: string }>;
}

// ============================================================================
// Translations
// ============================================================================

const translations: Record<string, {
  backToAll: string;
  noVideos: string;
  watchNow: string;
  views: string;
  page: string;
  of: string;
  prev: string;
  next: string;
  episodes: string;
  subscribe: string;
}> = {
  ro: {
    backToAll: 'Toate emisiunile',
    noVideos: 'Nu există videoclipuri disponibile pentru această emisiune.',
    watchNow: 'Vizionează',
    views: 'vizualizări',
    page: 'Pagina',
    of: 'din',
    prev: 'Anterior',
    next: 'Următorul',
    episodes: 'episoade',
    subscribe: 'Abonează-te pe YouTube',
  },
  en: {
    backToAll: 'All Shows',
    noVideos: 'No videos available for this show.',
    watchNow: 'Watch Now',
    views: 'views',
    page: 'Page',
    of: 'of',
    prev: 'Previous',
    next: 'Next',
    episodes: 'episodes',
    subscribe: 'Subscribe on YouTube',
  },
  ru: {
    backToAll: 'Все передачи',
    noVideos: 'Нет видео для этой передачи.',
    watchNow: 'Смотреть',
    views: 'просмотров',
    page: 'Страница',
    of: 'из',
    prev: 'Назад',
    next: 'Вперёд',
    episodes: 'выпусков',
    subscribe: 'Подписаться на YouTube',
  },
};

// ============================================================================
// Metadata
// ============================================================================

export async function generateMetadata({ params }: ShowPageProps): Promise<Metadata> {
  const { locale, slug } = await params;

  try {
    const show = await fetchVideoShowBySlug(slug, locale);
    if (!show) {
      return { title: 'Show Not Found' };
    }

    return {
      title: show.name,
      description: show.description || `Watch all episodes of ${show.name}`,
      openGraph: {
        title: show.name,
        description: show.description || `Watch all episodes of ${show.name}`,
        type: 'website',
        images: show.thumbnailUrl ? [{ url: show.thumbnailUrl }] : undefined,
      },
    };
  } catch {
    return { title: 'Show Not Found' };
  }
}

// ============================================================================
// Static Params for Build
// ============================================================================

export async function generateStaticParams() {
  try {
    const response = await fetchVideoShows();
    const locales = ['ro', 'en', 'ru'];

    return locales.flatMap((locale) =>
      response.member.map((show) => ({
        locale,
        slug: show.slug,
      }))
    );
  } catch {
    return [];
  }
}

// ============================================================================
// Helper Functions
// ============================================================================

function formatViewCount(count: number): string {
  if (count >= 1000000) {
    return `${(count / 1000000).toFixed(1)}M`;
  }
  if (count >= 1000) {
    return `${(count / 1000).toFixed(1)}K`;
  }
  return count.toString();
}

function formatPublishedDate(dateString: string | undefined, locale: string): string {
  if (!dateString) return '';

  const date = new Date(dateString);
  return date.toLocaleDateString(locale === 'ro' ? 'ro-RO' : locale === 'ru' ? 'ru-RU' : 'en-US', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

// ============================================================================
// Video Card Component
// ============================================================================

interface VideoCardProps {
  video: YouTubeVideo;
  locale: string;
  showColor?: string;
}

function VideoCard({ video, locale, showColor }: VideoCardProps) {
  const t = translations[locale] || translations.ro;

  return (
    <article className={cn(
      'group relative overflow-hidden rounded-xl',
      'bg-slate-900/60 backdrop-blur-sm',
      'border border-slate-700/50',
      'transition-all duration-500',
      'hover:shadow-xl',
      'hover:-translate-y-1'
    )}
    style={{
      '--hover-color': showColor || 'var(--color-breaking)',
    } as React.CSSProperties}
    >
      {/* Thumbnail */}
      <div className="relative aspect-video overflow-hidden">
        <Image
          src={video.thumbnailUrl || video.thumbnailMedium || '/placeholder-video.jpg'}
          alt={video.title}
          fill
          className="object-cover transition-transform duration-700 group-hover:scale-105"
          sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
        />

        {/* Gradient Overlay */}
        <div className="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/30 to-transparent opacity-70 group-hover:opacity-50 transition-opacity" />

        {/* Play Button Overlay */}
        <Link
          href={video.youtubeUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="absolute inset-0 flex items-center justify-center"
          aria-label={`${t.watchNow}: ${video.title}`}
        >
          <div
            className={cn(
              'w-16 h-16 rounded-full',
              'flex items-center justify-center',
              'backdrop-blur-sm',
              'border-2 border-white/20',
              'shadow-xl',
              'transition-all duration-300',
              'group-hover:scale-110'
            )}
            style={{
              backgroundColor: showColor ? `${showColor}e6` : 'var(--color-breaking)e6',
              boxShadow: `0 10px 40px ${showColor || 'var(--color-breaking)'}4d`,
            }}
          >
            <svg className="w-7 h-7 text-white ml-1" fill="currentColor" viewBox="0 0 24 24">
              <path d="M8 5v14l11-7z" />
            </svg>
          </div>
        </Link>

        {/* Duration Badge */}
        {video.durationFormatted && (
          <div className="absolute bottom-3 right-3 px-2 py-0.5 bg-black/80 rounded text-xs font-mono font-semibold text-white">
            {video.durationFormatted}
          </div>
        )}

        {/* Featured Badge */}
        {video.isFeatured && (
          <div
            className="absolute top-3 left-3 px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider text-white"
            style={{ backgroundColor: showColor || 'var(--color-breaking)' }}
          >
            Featured
          </div>
        )}
      </div>

      {/* Content */}
      <div className="p-5">
        <h2 className="font-heading font-bold text-white text-lg leading-tight line-clamp-2 mb-3 group-hover:text-slate-100 transition-colors">
          <Link href={video.youtubeUrl} target="_blank" rel="noopener noreferrer">
            {video.title}
          </Link>
        </h2>

        {/* Meta */}
        <div className="flex items-center justify-between text-xs text-slate-500">
          <div className="flex items-center gap-3">
            <span className="flex items-center gap-1">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              {formatViewCount(video.viewCount)} {t.views}
            </span>
          </div>
          <span>{formatPublishedDate(video.publishedAt, locale)}</span>
        </div>
      </div>
    </article>
  );
}

// ============================================================================
// Pagination Component
// ============================================================================

interface PaginationProps {
  currentPage: number;
  totalPages: number;
  locale: string;
  baseUrl: string;
  accentColor?: string;
}

function Pagination({ currentPage, totalPages, locale, baseUrl, accentColor }: PaginationProps) {
  const t = translations[locale] || translations.ro;

  if (totalPages <= 1) return null;

  const pages: (number | 'ellipsis')[] = [];
  pages.push(1);
  if (currentPage > 3) pages.push('ellipsis');
  for (let i = Math.max(2, currentPage - 1); i <= Math.min(totalPages - 1, currentPage + 1); i++) {
    if (!pages.includes(i)) pages.push(i);
  }
  if (currentPage < totalPages - 2) pages.push('ellipsis');
  if (totalPages > 1 && !pages.includes(totalPages)) pages.push(totalPages);

  return (
    <nav className="flex items-center justify-center gap-2 mt-12" aria-label="Pagination">
      <Link
        href={currentPage > 1 ? `${baseUrl}?page=${currentPage - 1}` : '#'}
        className={cn(
          'px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200',
          currentPage > 1
            ? 'bg-slate-800 text-white hover:opacity-80'
            : 'bg-slate-800/50 text-slate-600 cursor-not-allowed'
        )}
        style={currentPage > 1 ? { backgroundColor: accentColor } : undefined}
        aria-disabled={currentPage <= 1}
      >
        {t.prev}
      </Link>

      <div className="flex items-center gap-1">
        {pages.map((page, index) =>
          page === 'ellipsis' ? (
            <span key={`ellipsis-${index}`} className="px-2 text-slate-500">...</span>
          ) : (
            <Link
              key={page}
              href={`${baseUrl}?page=${page}`}
              className={cn(
                'w-10 h-10 rounded-lg flex items-center justify-center text-sm font-medium transition-all duration-200',
                page === currentPage
                  ? 'text-white'
                  : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
              )}
              style={page === currentPage ? { backgroundColor: accentColor || 'var(--color-breaking)' } : undefined}
            >
              {page}
            </Link>
          )
        )}
      </div>

      <Link
        href={currentPage < totalPages ? `${baseUrl}?page=${currentPage + 1}` : '#'}
        className={cn(
          'px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200',
          currentPage < totalPages
            ? 'bg-slate-800 text-white hover:opacity-80'
            : 'bg-slate-800/50 text-slate-600 cursor-not-allowed'
        )}
        style={currentPage < totalPages ? { backgroundColor: accentColor } : undefined}
        aria-disabled={currentPage >= totalPages}
      >
        {t.next}
      </Link>
    </nav>
  );
}

// ============================================================================
// Main Page Component
// ============================================================================

export default async function ShowPage({ params, searchParams }: ShowPageProps) {
  const { locale, slug } = await params;
  const { page: pageParam } = await searchParams;
  const t = translations[locale] || translations.ro;

  const currentPage = pageParam ? parseInt(pageParam, 10) : 1;
  const itemsPerPage = 12;

  let show = null;
  let videosResponse = null;
  let fetchError: unknown = null;

  try {
    // Fetch show and videos in parallel
    [show, videosResponse] = await Promise.all([
      fetchVideoShowBySlug(slug, locale),
      fetchVideosByShow(slug, currentPage, itemsPerPage, locale),
    ]);
  } catch (error) {
    fetchError = error;
  }

  if (fetchError || !videosResponse) {
    console.error('Error fetching show:', fetchError);
    notFound();
  }

  if (!show) {
    notFound();
  }

  const videos = videosResponse.member;
  const totalItems = videosResponse.totalItems;
  const totalPages = Math.ceil(totalItems / itemsPerPage);

  return (
    <div className="min-h-screen bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950">
      {/* Hero Header */}
      <section className="relative py-14 sm:py-20 border-b border-slate-800 overflow-hidden">
        {/* Background Gradient with Show Color */}
        <div
          className="absolute inset-0 opacity-20"
          style={{
            backgroundImage: `
              radial-gradient(circle at 30% 30%, ${show.color || 'var(--color-breaking)'} 0%, transparent 50%),
              radial-gradient(circle at 70% 70%, ${show.color || '#7f1d1d'} 0%, transparent 50%)
            `,
          }}
        />

        {/* Background Image if available */}
        {show.thumbnailUrl && (
          <div className="absolute inset-0 opacity-10">
            <Image
              src={show.thumbnailUrl}
              alt=""
              fill
              className="object-cover blur-2xl"
            />
          </div>
        )}

        <div className="relative xl:container mx-auto px-4">
          {/* Back Link */}
          <Link
            href={`/${locale}/emisiuni`}
            className="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white mb-8 transition-colors"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            {t.backToAll}
          </Link>

          <div className="flex flex-col md:flex-row gap-8 items-start">
            {/* Show Thumbnail */}
            {show.thumbnailUrl && (
              <div className="flex-shrink-0 w-40 h-40 md:w-48 md:h-48 relative rounded-2xl overflow-hidden border-2 border-slate-700/50 shadow-2xl">
                <Image
                  src={show.thumbnailUrl}
                  alt={show.name}
                  fill
                  className="object-cover"
                />
              </div>
            )}

            {/* Show Info */}
            <div className="flex-1">
              <div
                className="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-4"
                style={{
                  backgroundColor: `${show.color || 'var(--color-breaking)'}20`,
                  color: show.color || 'var(--color-breaking)',
                  border: `1px solid ${show.color || 'var(--color-breaking)'}40`,
                }}
              >
                Video Show
              </div>

              <h1 className="text-3xl sm:text-4xl lg:text-5xl font-heading font-bold text-white mb-4">
                {show.name}
              </h1>

              {show.description && (
                <p className="text-lg text-slate-300 max-w-2xl mb-6">
                  {show.description}
                </p>
              )}

              <div className="flex flex-wrap items-center gap-4">
                <span className="text-slate-400">
                  <strong className="text-white">{show.videosCount}</strong> {t.episodes}
                </span>

                {show.youtubeChannelId && (
                  <a
                    href={`https://www.youtube.com/channel/${show.youtubeChannelId}?sub_confirmation=1`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={cn(
                      'inline-flex items-center gap-2',
                      'px-4 py-2 rounded-full',
                      'text-sm font-medium text-white',
                      'transition-all duration-200 hover:opacity-80'
                    )}
                    style={{ backgroundColor: show.color || 'var(--color-breaking)' }}
                  >
                    <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0C.488 3.45.029 5.804 0 12c.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0C23.512 20.55 23.971 18.196 24 12c-.029-6.185-.484-8.549-4.385-8.816zM9 16V8l8 4-8 4z"/>
                    </svg>
                    {t.subscribe}
                  </a>
                )}
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Videos Grid */}
      <section className="py-10 sm:py-14">
        <div className="xl:container mx-auto px-4">
          {videos.length > 0 ? (
            <>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                {videos.map((video) => (
                  <VideoCard
                    key={video.id}
                    video={video}
                    locale={locale}
                    showColor={show.color || undefined}
                  />
                ))}
              </div>

              <Pagination
                currentPage={currentPage}
                totalPages={totalPages}
                locale={locale}
                baseUrl={`/${locale}/emisiuni/${slug}`}
                accentColor={show.color || undefined}
              />

              <p className="text-center text-sm text-slate-500 mt-6">
                {t.page} {currentPage} {t.of} {totalPages} ({totalItems} {t.episodes})
              </p>
            </>
          ) : (
            <div className="text-center py-20">
              <div className="w-20 h-20 mx-auto mb-6 rounded-full bg-slate-800/50 flex items-center justify-center">
                <svg className="w-10 h-10 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
              </div>
              <p className="text-xl text-slate-400">{t.noVideos}</p>
            </div>
          )}
        </div>
      </section>
    </div>
  );
}
