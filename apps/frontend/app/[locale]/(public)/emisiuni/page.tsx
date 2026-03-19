import { Metadata } from 'next';
import Link from 'next/link';
import Image from 'next/image';
import { notFound } from 'next/navigation';
import { fetchAllVideos, fetchVideoShows } from '@/lib/api/video-shows';
import { YouTubeVideo, VideoShow } from '@/lib/types/video';
import { cn } from '@/lib/utils/cn';

// ============================================================================
// Types
// ============================================================================

interface EmisiuniPageProps {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{ page?: string; show?: string }>;
}

// ============================================================================
// Translations
// ============================================================================

const translations: Record<string, {
  title: string;
  description: string;
  allShows: string;
  noVideos: string;
  watchNow: string;
  views: string;
  page: string;
  of: string;
  prev: string;
  next: string;
  filterByShow: string;
}> = {
  ro: {
    title: 'Emisiuni Video',
    description: 'Urmărește toate emisiunile video realizate de redacția Deschide.md',
    allShows: 'Toate emisiunile',
    noVideos: 'Nu există videoclipuri disponibile momentan.',
    watchNow: 'Vizionează',
    views: 'vizualizări',
    page: 'Pagina',
    of: 'din',
    prev: 'Anterior',
    next: 'Următorul',
    filterByShow: 'Filtrează după emisiune',
  },
  en: {
    title: 'Video Shows',
    description: 'Watch all video shows produced by Deschide.md editorial team',
    allShows: 'All Shows',
    noVideos: 'No videos available at the moment.',
    watchNow: 'Watch Now',
    views: 'views',
    page: 'Page',
    of: 'of',
    prev: 'Previous',
    next: 'Next',
    filterByShow: 'Filter by show',
  },
  ru: {
    title: 'Видео передачи',
    description: 'Смотрите все видео передачи редакции Deschide.md',
    allShows: 'Все передачи',
    noVideos: 'На данный момент видео недоступны.',
    watchNow: 'Смотреть',
    views: 'просмотров',
    page: 'Страница',
    of: 'из',
    prev: 'Назад',
    next: 'Вперёд',
    filterByShow: 'Фильтр по передаче',
  },
};

// ============================================================================
// Metadata
// ============================================================================

export async function generateMetadata({ params }: EmisiuniPageProps): Promise<Metadata> {
  const { locale } = await params;
  const t = translations[locale] || translations.ro;

  return {
    title: t.title,
    description: t.description,
    openGraph: {
      title: t.title,
      description: t.description,
      type: 'website',
    },
  };
}

// ============================================================================
// Helper Components
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
}

function VideoCard({ video, locale }: VideoCardProps) {
  const t = translations[locale] || translations.ro;

  return (
    <article className={cn(
      'group relative overflow-hidden rounded-xl',
      'bg-slate-900/60 backdrop-blur-sm',
      'border border-slate-700/50',
      'transition-all duration-500',
      'hover:border-red-500/40 hover:shadow-xl hover:shadow-red-500/5',
      'hover:-translate-y-1'
    )}>
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
          <div className={cn(
            'w-16 h-16 rounded-full',
            'flex items-center justify-center',
            'bg-red-600/90 backdrop-blur-sm',
            'border-2 border-white/20',
            'shadow-xl shadow-red-500/30',
            'transition-all duration-300',
            'group-hover:scale-110 group-hover:bg-red-500'
          )}>
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

        {/* Show Badge */}
        {video.videoShow && (
          <div
            className="absolute top-3 left-3 px-2 py-1 bg-slate-900/80 backdrop-blur-sm rounded text-[10px] font-semibold uppercase tracking-wider border border-slate-600/50"
            style={{ color: video.videoShow.color || '#fff' }}
          >
            {video.videoShow.name}
          </div>
        )}
      </div>

      {/* Content */}
      <div className="p-5">
        <h2 className="font-heading font-bold text-white text-lg leading-tight line-clamp-2 mb-3 group-hover:text-red-100 transition-colors">
          <Link href={video.youtubeUrl} target="_blank" rel="noopener noreferrer">
            {video.title}
          </Link>
        </h2>

        {video.description && (
          <p className="text-sm text-slate-400 line-clamp-2 mb-4">
            {video.description}
          </p>
        )}

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
}

function Pagination({ currentPage, totalPages, locale, baseUrl }: PaginationProps) {
  const t = translations[locale] || translations.ro;

  if (totalPages <= 1) return null;

  const pages: (number | 'ellipsis')[] = [];

  // Always show first page
  pages.push(1);

  // Show ellipsis after first page if needed
  if (currentPage > 3) {
    pages.push('ellipsis');
  }

  // Show pages around current
  for (let i = Math.max(2, currentPage - 1); i <= Math.min(totalPages - 1, currentPage + 1); i++) {
    if (!pages.includes(i)) {
      pages.push(i);
    }
  }

  // Show ellipsis before last page if needed
  if (currentPage < totalPages - 2) {
    pages.push('ellipsis');
  }

  // Always show last page if more than 1 page
  if (totalPages > 1 && !pages.includes(totalPages)) {
    pages.push(totalPages);
  }

  return (
    <nav className="flex items-center justify-center gap-2 mt-12" aria-label="Pagination">
      {/* Previous */}
      <Link
        href={currentPage > 1 ? `${baseUrl}?page=${currentPage - 1}` : '#'}
        className={cn(
          'px-4 py-2 rounded-lg text-sm font-medium',
          'transition-all duration-200',
          currentPage > 1
            ? 'bg-slate-800 text-white hover:bg-red-600'
            : 'bg-slate-800/50 text-slate-600 cursor-not-allowed'
        )}
        aria-disabled={currentPage <= 1}
      >
        {t.prev}
      </Link>

      {/* Page Numbers */}
      <div className="flex items-center gap-1">
        {pages.map((page, index) =>
          page === 'ellipsis' ? (
            <span key={`ellipsis-${index}`} className="px-2 text-slate-500">
              ...
            </span>
          ) : (
            <Link
              key={page}
              href={`${baseUrl}?page=${page}`}
              className={cn(
                'w-10 h-10 rounded-lg flex items-center justify-center text-sm font-medium',
                'transition-all duration-200',
                page === currentPage
                  ? 'bg-red-600 text-white'
                  : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
              )}
            >
              {page}
            </Link>
          )
        )}
      </div>

      {/* Next */}
      <Link
        href={currentPage < totalPages ? `${baseUrl}?page=${currentPage + 1}` : '#'}
        className={cn(
          'px-4 py-2 rounded-lg text-sm font-medium',
          'transition-all duration-200',
          currentPage < totalPages
            ? 'bg-slate-800 text-white hover:bg-red-600'
            : 'bg-slate-800/50 text-slate-600 cursor-not-allowed'
        )}
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

export default async function EmisiuniPage({ params, searchParams }: EmisiuniPageProps) {
  const { locale } = await params;
  const { page: pageParam, show: showParam } = await searchParams;
  const t = translations[locale] || translations.ro;

  const currentPage = pageParam ? parseInt(pageParam, 10) : 1;
  const itemsPerPage = 12;

  try {
    // Fetch videos and shows in parallel
    const [videosResponse, showsResponse] = await Promise.all([
      fetchAllVideos(currentPage, itemsPerPage, locale),
      fetchVideoShows(locale),
    ]);

    const videos = videosResponse.member;
    const shows = showsResponse.member;
    const totalItems = videosResponse.totalItems;
    const totalPages = Math.ceil(totalItems / itemsPerPage);

    return (
      <div className="min-h-screen bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950">
        {/* Header Section */}
        <section className="relative py-12 sm:py-16 border-b border-slate-800">
          {/* Background Pattern */}
          <div
            className="absolute inset-0 opacity-[0.02]"
            style={{
              backgroundImage: `
                radial-gradient(circle at 20% 30%, #dc2626 0%, transparent 40%),
                radial-gradient(circle at 80% 70%, #7f1d1d 0%, transparent 40%)
              `,
            }}
          />

          <div className="relative xl:container mx-auto px-4">
            <div className="flex items-center gap-4 mb-4">
              <div className="w-14 h-14 flex items-center justify-center rounded-xl bg-red-600/20 border border-red-500/30">
                <svg className="w-7 h-7 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0C.488 3.45.029 5.804 0 12c.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0C23.512 20.55 23.971 18.196 24 12c-.029-6.185-.484-8.549-4.385-8.816zM9 16V8l8 4-8 4z"/>
                </svg>
              </div>
              <div>
                <h1 className="text-3xl sm:text-4xl font-heading font-bold text-white">
                  {t.title}
                </h1>
                <p className="text-slate-400 mt-1">
                  {t.description}
                </p>
              </div>
            </div>

            {/* Shows Filter */}
            {shows.length > 0 && (
              <div className="mt-8">
                <p className="text-sm text-slate-500 mb-3">{t.filterByShow}</p>
                <div className="flex flex-wrap gap-2">
                  <Link
                    href={`/${locale}/emisiuni`}
                    className={cn(
                      'px-4 py-2 rounded-full text-sm font-medium',
                      'transition-all duration-200',
                      !showParam
                        ? 'bg-red-600 text-white'
                        : 'bg-slate-800 text-slate-300 hover:bg-slate-700 border border-slate-700'
                    )}
                  >
                    {t.allShows}
                  </Link>
                  {shows.map((show) => (
                    <Link
                      key={show.id}
                      href={`/${locale}/emisiuni/${show.slug}`}
                      className={cn(
                        'px-4 py-2 rounded-full text-sm font-medium',
                        'bg-slate-800 text-slate-300 hover:bg-slate-700',
                        'border border-slate-700 hover:border-slate-600',
                        'transition-all duration-200'
                      )}
                    >
                      {show.name}
                      <span className="ml-1.5 text-slate-500">({show.videosCount})</span>
                    </Link>
                  ))}
                </div>
              </div>
            )}
          </div>
        </section>

        {/* Videos Grid */}
        <section className="py-10 sm:py-14">
          <div className="xl:container mx-auto px-4">
            {videos.length > 0 ? (
              <>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                  {videos.map((video) => (
                    <VideoCard key={video.id} video={video} locale={locale} />
                  ))}
                </div>

                {/* Pagination */}
                <Pagination
                  currentPage={currentPage}
                  totalPages={totalPages}
                  locale={locale}
                  baseUrl={`/${locale}/emisiuni`}
                />

                {/* Page Info */}
                <p className="text-center text-sm text-slate-500 mt-6">
                  {t.page} {currentPage} {t.of} {totalPages} ({totalItems} videos)
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
  } catch (error) {
    console.error('Error fetching videos:', error);
    notFound();
  }
}
