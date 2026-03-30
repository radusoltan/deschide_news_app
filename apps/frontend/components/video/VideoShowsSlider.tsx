'use client';

import React, { useState, useCallback, useEffect } from 'react';
import { Swiper, SwiperSlide } from 'swiper/react';
import { Navigation, Autoplay, Pagination } from 'swiper/modules';
import type { Swiper as SwiperType } from 'swiper';
import Image from 'next/image';
import Link from 'next/link';
import { cn } from '@/lib/utils/cn';
import { YouTubeVideo, VideoShow } from '@/lib/types/video';

import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';

// ============================================================================
// Types
// ============================================================================

interface VideoShowsSliderProps {
  videos: YouTubeVideo[];
  videoShows?: VideoShow[];
  title?: string;
  locale: string;
  className?: string;
}

// ============================================================================
// Translations
// ============================================================================

const translations: Record<string, {
  title: string;
  watchNow: string;
  allEmissions: string;
  views: string;
  duration: string;
  newEpisode: string;
}> = {
  ro: {
    title: 'Emisiuni Video',
    watchNow: 'Vizionează',
    allEmissions: 'Toate emisiunile',
    views: 'vizualizări',
    duration: 'Durată',
    newEpisode: 'Episod nou',
  },
  en: {
    title: 'Video Shows',
    watchNow: 'Watch Now',
    allEmissions: 'All Shows',
    views: 'views',
    duration: 'Duration',
    newEpisode: 'New Episode',
  },
  ru: {
    title: 'Видео передачи',
    watchNow: 'Смотреть',
    allEmissions: 'Все передачи',
    views: 'просмотров',
    duration: 'Длительность',
    newEpisode: 'Новый выпуск',
  },
};

// ============================================================================
// Helper Functions
// ============================================================================

function formatViewCount(count: number, locale: string): string {
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
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

  if (diffDays === 0) {
    return locale === 'ro' ? 'Azi' : locale === 'ru' ? 'Сегодня' : 'Today';
  }
  if (diffDays === 1) {
    return locale === 'ro' ? 'Ieri' : locale === 'ru' ? 'Вчера' : 'Yesterday';
  }
  if (diffDays < 7) {
    return locale === 'ro' ? `${diffDays} zile în urmă` :
           locale === 'ru' ? `${diffDays} дней назад` :
           `${diffDays} days ago`;
  }
  if (diffDays < 30) {
    const weeks = Math.floor(diffDays / 7);
    return locale === 'ro' ? `${weeks} săpt. în urmă` :
           locale === 'ru' ? `${weeks} нед. назад` :
           `${weeks} week${weeks > 1 ? 's' : ''} ago`;
  }

  return date.toLocaleDateString(locale === 'ro' ? 'ro-RO' : locale === 'ru' ? 'ru-RU' : 'en-US', {
    day: 'numeric',
    month: 'short',
  });
}

function isNewVideo(dateString: string | undefined): boolean {
  if (!dateString) return false;
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
  return diffDays <= 3;
}

// ============================================================================
// Video Card Component
// ============================================================================

interface VideoCardProps {
  video: YouTubeVideo;
  locale: string;
  onPlay: (video: YouTubeVideo) => void;
  featured?: boolean;
}

const VideoCard: React.FC<VideoCardProps> = ({ video, locale, onPlay, featured = false }) => {
  const t = translations[locale] || translations.en;
  const isNew = isNewVideo(video.publishedAt);

  return (
    <div
      className={cn(
        'group relative overflow-hidden rounded-xl',
        'bg-gradient-to-br from-slate-900/95 to-slate-950/95',
        'backdrop-blur-md',
        'border border-slate-700/30',
        'transition-all duration-700 ease-out',
        'hover:border-[var(--color-accent)]/50',
        'hover:shadow-[0_20px_60px_-15px_rgba(240,94,69,0.3)]',
        'hover:-translate-y-2',
        'cursor-pointer',
        featured && 'col-span-2 row-span-2'
      )}
      onClick={() => onPlay(video)}
    >
      {/* Thumbnail Container */}
      <div className="relative aspect-video overflow-hidden">
        {/* Thumbnail Image */}
        <Image
          src={video.thumbnailUrl || video.thumbnailMedium || '/placeholder-video.jpg'}
          alt={video.title}
          fill
          className={cn(
            'object-cover transition-all duration-1000 ease-out',
            'group-hover:scale-110 group-hover:brightness-110'
          )}
          sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
          priority={featured}
        />

        {/* Sophisticated Gradient Overlay */}
        <div className="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/50 via-40% to-transparent opacity-90 group-hover:opacity-70 transition-opacity duration-500" />

        {/* Radial Gradient for Depth */}
        <div className="absolute inset-0 bg-radial-gradient from-transparent via-transparent to-slate-950/40 opacity-60" />

        {/* Cinematic Vignette */}
        <div className="absolute inset-0 shadow-[inset_0_0_100px_rgba(0,0,0,0.5)] pointer-events-none" />

        {/* Film Grain Texture */}
        <div
          className="absolute inset-0 opacity-[0.025] pointer-events-none mix-blend-overlay"
          style={{
            backgroundImage: `url("data:image/svg+xml,%3Csvg viewBox='0 0 400 400' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E")`,
          }}
        />

        {/* Play Button with Premium Animation */}
        <div
          className={cn(
            'absolute inset-0 flex items-center justify-center',
            'transition-all duration-500'
          )}
        >
          <div className={cn(
            'relative flex items-center justify-center',
            'w-20 h-20 sm:w-24 sm:h-24',
            'rounded-full',
            'bg-gradient-to-br from-[var(--color-accent)] to-[var(--color-accent)]',
            'shadow-[0_0_40px_rgba(240,94,69,0.4)]',
            'transition-all duration-500 ease-out',
            'group-hover:scale-125 group-hover:shadow-[0_0_60px_rgba(240,94,69,0.6)]',
            'before:absolute before:inset-0 before:rounded-full',
            'before:bg-gradient-to-br before:from-white/20 before:to-transparent',
            'before:opacity-0 before:group-hover:opacity-100 before:transition-opacity before:duration-500'
          )}>
            {/* Play Icon */}
            <svg
              className="w-8 h-8 sm:w-10 sm:h-10 text-white ml-1 drop-shadow-lg relative z-10"
              fill="currentColor"
              viewBox="0 0 24 24"
            >
              <path d="M8 5v14l11-7z" />
            </svg>

            {/* Animated Rings */}
            <div className="absolute inset-0 rounded-full border-2 border-white/40 opacity-0 group-hover:opacity-100 group-hover:scale-150 transition-all duration-700 ease-out" />
            <div className="absolute inset-0 rounded-full border border-white/30 animate-ping opacity-0 group-hover:opacity-60" style={{ animationDuration: '2s' }} />

            {/* Inner Glow */}
            <div className="absolute inset-2 rounded-full bg-white/10 blur-md opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
          </div>
        </div>

        {/* Duration Badge - Premium Style */}
        {video.durationFormatted && (
          <div className={cn(
            'absolute bottom-3 right-3',
            'px-3 py-1.5',
            'bg-black/90 backdrop-blur-md',
            'rounded-md font-mono text-xs font-bold text-white',
            'border border-white/10',
            'shadow-lg',
            'transition-all duration-300',
            'group-hover:bg-[var(--color-accent)]/90 group-hover:border-[var(--color-accent)]/30'
          )}>
            {video.durationFormatted}
          </div>
        )}

        {/* New Episode Badge - Animated */}
        {isNew && (
          <div className={cn(
            'absolute top-3 left-3',
            'px-3 py-1.5',
            'bg-gradient-to-r from-[var(--color-accent)] to-[var(--color-accent)]',
            'rounded-md shadow-lg shadow-[var(--color-accent)]/50',
            'text-[11px] font-bold uppercase tracking-widest text-white',
            'border border-[var(--color-accent)]/30',
            'animate-pulse'
          )}>
            <div className="flex items-center gap-1.5">
              <span className="w-1.5 h-1.5 bg-white rounded-full animate-ping" />
              {t.newEpisode}
            </div>
          </div>
        )}

        {/* Video Show Label - Enhanced */}
        {video.videoShow && !isNew && (
          <div className={cn(
            'absolute top-3 left-3',
            'px-3 py-1.5',
            'bg-slate-950/80 backdrop-blur-md rounded-md',
            'text-[11px] font-bold uppercase tracking-widest',
            'border border-slate-600/40',
            'shadow-lg',
            'transition-all duration-300',
            'group-hover:border-slate-500/60'
          )}
          style={{
            color: video.videoShow.color || '#fff',
            textShadow: `0 0 10px ${video.videoShow.color}40`
          }}
          >
            {video.videoShow.name}
          </div>
        )}
      </div>

      {/* Content - Enhanced Typography */}
      <div className="p-5 sm:p-6 space-y-3">
        {/* Title with Better Hierarchy */}
        <h3 className={cn(
          'font-sans font-bold text-white leading-snug',
          'line-clamp-2',
          'transition-all duration-300',
          'group-hover:text-[var(--color-accent)]/80',
          featured ? 'text-xl sm:text-2xl' : 'text-base sm:text-lg'
        )}>
          {video.title}
        </h3>

        {/* Meta Info - Refined Design */}
        <div className="flex items-center justify-between text-xs font-medium">
          <div className="flex items-center gap-4">
            {/* Views with Icon */}
            <span className="flex items-center gap-1.5 text-slate-400 transition-colors group-hover:text-slate-300">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              {formatViewCount(video.viewCount, locale)}
            </span>

            {/* Likes with Heart Icon */}
            {video.likeCount > 0 && (
              <span className="flex items-center gap-1.5 text-slate-400 transition-colors group-hover:text-red-400">
                <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                </svg>
                {formatViewCount(video.likeCount, locale)}
              </span>
            )}
          </div>

          {/* Published Date - Accent Color */}
          <span className="text-slate-500 transition-colors group-hover:text-[var(--color-accent)]">
            {formatPublishedDate(video.publishedAt, locale)}
          </span>
        </div>
      </div>

      {/* Hover Shine Effect */}
      <div className="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none">
        <div className="absolute inset-0 bg-gradient-to-tr from-transparent via-white/5 to-transparent translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-1000 ease-out" />
      </div>
    </div>
  );
};

// ============================================================================
// Video Modal Component
// ============================================================================

interface VideoModalProps {
  video: YouTubeVideo | null;
  onClose: () => void;
  locale: string;
}

const VideoModal: React.FC<VideoModalProps> = ({ video, onClose, locale }) => {
  const [isLoaded, setIsLoaded] = useState(false);

  useEffect(() => {
    // Prevent body scroll when modal is open
    document.body.style.overflow = 'hidden';
    // Trigger entrance animation
    setTimeout(() => setIsLoaded(true), 10);

    return () => {
      document.body.style.overflow = '';
    };
  }, []);

  // Handle ESC key to close
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', handleEscape);
    return () => window.removeEventListener('keydown', handleEscape);
  }, [onClose]);

  if (!video) return null;

  return (
    <div
      className={cn(
        'fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6',
        'transition-opacity duration-500',
        isLoaded ? 'opacity-100' : 'opacity-0'
      )}
      onClick={onClose}
    >
      {/* Premium Backdrop with Blur */}
      <div className="absolute inset-0 bg-black/98 backdrop-blur-2xl" />

      {/* Ambient Light Effect */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-[var(--color-accent)]/20 rounded-full blur-[120px]" />
      </div>

      {/* Modal Content */}
      <div
        className={cn(
          'relative w-full max-w-6xl',
          'transition-all duration-700 ease-out',
          isLoaded ? 'scale-100 translate-y-0' : 'scale-95 translate-y-8'
        )}
        onClick={(e) => e.stopPropagation()}
      >
        {/* Close Button - Enhanced */}
        <button
          onClick={onClose}
          className={cn(
            'absolute -top-14 right-0 z-10',
            'w-12 h-12 rounded-full',
            'flex items-center justify-center',
            'bg-white/5 hover:bg-[var(--color-accent)]/20',
            'border border-white/10 hover:border-[var(--color-accent)]/50',
            'text-white/70 hover:text-white',
            'backdrop-blur-md',
            'transition-all duration-300',
            'group'
          )}
          aria-label="Close video"
        >
          <svg className="w-6 h-6 transition-transform group-hover:rotate-90 duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

        {/* Video Container with Premium Shadow */}
        <div className={cn(
          'relative aspect-video rounded-2xl overflow-hidden',
          'shadow-[0_40px_100px_-20px_rgba(0,0,0,0.8)]',
          'border border-white/10',
          'bg-black'
        )}>
          <iframe
            src={`${video.embedUrl}?autoplay=1&rel=0&modestbranding=1&color=white`}
            title={video.title}
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowFullScreen
            className="absolute inset-0 w-full h-full"
          />
        </div>

        {/* Video Info - Premium Design */}
        <div className="mt-6 sm:mt-8 text-center space-y-3">
          <h3 className="text-xl sm:text-2xl lg:text-3xl font-sans font-bold text-white leading-tight px-4">
            {video.title}
          </h3>

          <div className="flex items-center justify-center gap-4 text-sm text-slate-400">
            {video.videoShow && (
              <span
                className="px-3 py-1 rounded-full bg-slate-800/50 border border-slate-700/50 font-medium"
                style={{ color: video.videoShow.color || '#fff' }}
              >
                {video.videoShow.name}
              </span>
            )}

            {video.viewCount > 0 && (
              <span className="flex items-center gap-1.5">
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                {formatViewCount(video.viewCount, locale)} {locale === 'ro' ? 'vizualizări' : locale === 'ru' ? 'просмотров' : 'views'}
              </span>
            )}

            {video.durationFormatted && (
              <span className="flex items-center gap-1.5 font-mono">
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {video.durationFormatted}
              </span>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

// ============================================================================
// Main Component
// ============================================================================

export const VideoShowsSlider: React.FC<VideoShowsSliderProps> = ({
  videos,
  videoShows,
  title,
  locale,
  className,
}) => {
  const [activeVideo, setActiveVideo] = useState<YouTubeVideo | null>(null);
  const [swiper, setSwiper] = useState<SwiperType | null>(null);
  const [isBeginning, setIsBeginning] = useState(true);
  const [isEnd, setIsEnd] = useState(false);
  const t = translations[locale] || translations.en;

  const handlePlayVideo = useCallback((video: YouTubeVideo) => {
    setActiveVideo(video);
  }, []);

  const handleCloseModal = useCallback(() => {
    setActiveVideo(null);
  }, []);

  // Don't render if no videos
  if (!videos || videos.length === 0) {
    return null;
  }

  return (
    <>
      <section
        className={cn(
          'relative py-16 sm:py-20 lg:py-24',
          'bg-gradient-to-b from-slate-950 via-slate-900/95 to-slate-950',
          'overflow-hidden',
          className
        )}
        aria-label={title || t.title}
      >
        {/* Premium Background Effects */}
        <div className="absolute inset-0 overflow-hidden">
          {/* Radial Gradient Orbs */}
          <div className="absolute top-1/4 left-1/4 w-[500px] h-[500px] bg-[var(--color-accent)]/10 rounded-full blur-[120px] opacity-40" />
          <div className="absolute bottom-1/4 right-1/4 w-[600px] h-[600px] bg-[var(--color-surface-dark)]/10 rounded-full blur-[140px] opacity-30" />

          {/* Subtle Noise Texture */}
          <div
            className="absolute inset-0 opacity-[0.02] pointer-events-none mix-blend-overlay"
            style={{
              backgroundImage: `url("data:image/svg+xml,%3Csvg viewBox='0 0 300 300' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.7' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E")`,
            }}
          />

          {/* Grid Pattern */}
          <div
            className="absolute inset-0 opacity-[0.02]"
            style={{
              backgroundImage: `linear-gradient(rgba(240,94,69,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(240,94,69,0.1) 1px, transparent 1px)`,
              backgroundSize: '60px 60px',
            }}
          />
        </div>

        <div className="relative xl:container mx-auto px-4 sm:px-6 lg:px-8">
          {/* Premium Section Header */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-6 mb-10 sm:mb-12">
            <div className="flex items-center gap-5">
              {/* Premium Video Icon */}
              <div className={cn(
                'flex-shrink-0 w-14 h-14 sm:w-16 sm:h-16',
                'flex items-center justify-center',
                'rounded-2xl',
                'bg-gradient-to-br from-[var(--color-accent)]/20 to-[var(--color-accent)]/20',
                'border border-[var(--color-accent)]/30',
                'shadow-[0_0_40px_rgba(240,94,69,0.15)]',
                'backdrop-blur-sm'
              )}>
                <svg className="w-7 h-7 sm:w-8 sm:h-8 text-[var(--color-accent)]" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0C.488 3.45.029 5.804 0 12c.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0C23.512 20.55 23.971 18.196 24 12c-.029-6.185-.484-8.549-4.385-8.816zM9 16V8l8 4-8 4z"/>
                </svg>
              </div>

              <div>
                <h2 className="text-3xl sm:text-4xl lg:text-5xl font-sans font-extrabold text-white tracking-tight leading-tight">
                  {title || t.title}
                </h2>
                <p className="text-sm sm:text-base text-slate-400 mt-1.5 font-medium">
                  {videos.length} {locale === 'ro' ? 'videoclipuri' : locale === 'ru' ? 'видео' : 'videos'}
                </p>
              </div>
            </div>

            {/* Premium View All Button */}
            <Link
              href={`/${locale}/emisiuni`}
              className={cn(
                'group inline-flex items-center gap-2.5',
                'px-6 py-3 rounded-full',
                'bg-gradient-to-r from-white/5 to-white/10',
                'hover:from-[var(--color-accent)]/20 hover:to-[var(--color-accent)]/20',
                'border border-white/10 hover:border-[var(--color-accent)]/50',
                'backdrop-blur-sm',
                'text-sm font-semibold text-white/90 hover:text-white',
                'transition-all duration-500',
                'shadow-lg shadow-black/10 hover:shadow-[var(--color-accent)]/20',
                'hover:scale-105'
              )}
            >
              {t.allEmissions}
              <svg className="w-4 h-4 transition-transform group-hover:translate-x-1 duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
              </svg>
            </Link>
          </div>

          {/* Premium Video Shows Filter */}
          {videoShows && videoShows.length > 0 && (
            <div className="flex flex-wrap gap-2.5 mb-8 sm:mb-10">
              <button
                className={cn(
                  'px-5 py-2.5 rounded-full text-sm font-semibold',
                  'bg-gradient-to-r from-[var(--color-accent)] to-[var(--color-accent)]',
                  'text-white shadow-lg shadow-[var(--color-accent)]/30',
                  'border border-[var(--color-accent)]/30',
                  'transition-all duration-300 hover:scale-105'
                )}
              >
                {locale === 'ro' ? 'Toate' : locale === 'ru' ? 'Все' : 'All'}
              </button>
              {videoShows.map((show) => (
                <Link
                  key={show.id}
                  href={`/${locale}/emisiuni/${show.slug}`}
                  className={cn(
                    'px-5 py-2.5 rounded-full text-sm font-semibold',
                    'bg-white/5 hover:bg-white/10',
                    'text-slate-300 hover:text-white',
                    'border border-white/10 hover:border-white/20',
                    'backdrop-blur-sm',
                    'transition-all duration-300 hover:scale-105'
                  )}
                  style={{
                    borderColor: show.color ? `${show.color}40` : undefined,
                  }}
                >
                  {show.name}
                </Link>
              ))}
            </div>
          )}

          {/* Premium Videos Swiper */}
          <div className="relative">
            <Swiper
              modules={[Navigation, Autoplay]}
              spaceBetween={20}
              slidesPerView={1}
              onSwiper={setSwiper}
              onSlideChange={(swiper) => {
                setIsBeginning(swiper.isBeginning);
                setIsEnd(swiper.isEnd);
              }}
              autoplay={{
                delay: 7000,
                disableOnInteraction: true,
                pauseOnMouseEnter: true,
              }}
              breakpoints={{
                480: {
                  slidesPerView: 1.3,
                  spaceBetween: 16,
                },
                640: {
                  slidesPerView: 2,
                  spaceBetween: 20,
                },
                768: {
                  slidesPerView: 2.5,
                  spaceBetween: 24,
                },
                1024: {
                  slidesPerView: 3,
                  spaceBetween: 24,
                },
                1280: {
                  slidesPerView: 4,
                  spaceBetween: 28,
                },
              }}
              className="video-shows-swiper !overflow-visible pb-4"
            >
              {videos.map((video, index) => (
                <SwiperSlide key={video.id}>
                  <VideoCard
                    video={video}
                    locale={locale}
                    onPlay={handlePlayVideo}
                    featured={false}
                  />
                </SwiperSlide>
              ))}
            </Swiper>

            {/* Premium Navigation Arrows - Side positioned */}
            <button
              onClick={() => swiper?.slidePrev()}
              disabled={isBeginning}
              className={cn(
                'absolute left-0 top-1/2 -translate-y-1/2 -translate-x-6 z-10',
                'hidden lg:flex',
                'w-14 h-14 rounded-full',
                'items-center justify-center',
                'bg-gradient-to-br from-slate-800/90 to-slate-900/90',
                'hover:from-[var(--color-accent)]/90 hover:to-[var(--color-accent)]/90',
                'border border-slate-700/50 hover:border-[var(--color-accent)]/50',
                'backdrop-blur-md',
                'text-white/80 hover:text-white',
                'shadow-2xl shadow-black/30 hover:shadow-[var(--color-accent)]/30',
                'transition-all duration-500 ease-out',
                'disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:from-slate-800/90',
                'hover:scale-110 active:scale-95',
                'group'
              )}
              aria-label="Previous videos"
            >
              <svg className="w-6 h-6 transition-transform group-hover:-translate-x-0.5 duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
              </svg>
            </button>

            <button
              onClick={() => swiper?.slideNext()}
              disabled={isEnd}
              className={cn(
                'absolute right-0 top-1/2 -translate-y-1/2 translate-x-6 z-10',
                'hidden lg:flex',
                'w-14 h-14 rounded-full',
                'items-center justify-center',
                'bg-gradient-to-br from-slate-800/90 to-slate-900/90',
                'hover:from-[var(--color-accent)]/90 hover:to-[var(--color-accent)]/90',
                'border border-slate-700/50 hover:border-[var(--color-accent)]/50',
                'backdrop-blur-md',
                'text-white/80 hover:text-white',
                'shadow-2xl shadow-black/30 hover:shadow-[var(--color-accent)]/30',
                'transition-all duration-500 ease-out',
                'disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:from-slate-800/90',
                'hover:scale-110 active:scale-95',
                'group'
              )}
              aria-label="Next videos"
            >
              <svg className="w-6 h-6 transition-transform group-hover:translate-x-0.5 duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>

          {/* Mobile Navigation Buttons */}
          <div className="flex lg:hidden justify-center gap-3 mt-8">
            <button
              onClick={() => swiper?.slidePrev()}
              disabled={isBeginning}
              className={cn(
                'w-12 h-12 rounded-full',
                'flex items-center justify-center',
                'bg-gradient-to-br from-slate-800/90 to-slate-900/90',
                'hover:from-[var(--color-accent)]/90 hover:to-[var(--color-accent)]/90',
                'border border-slate-700/50 hover:border-[var(--color-accent)]/50',
                'text-white/80 hover:text-white',
                'backdrop-blur-sm',
                'transition-all duration-300',
                'disabled:opacity-30 disabled:cursor-not-allowed',
                'shadow-lg'
              )}
              aria-label="Previous videos"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            <button
              onClick={() => swiper?.slideNext()}
              disabled={isEnd}
              className={cn(
                'w-12 h-12 rounded-full',
                'flex items-center justify-center',
                'bg-gradient-to-br from-slate-800/90 to-slate-900/90',
                'hover:from-[var(--color-accent)]/90 hover:to-[var(--color-accent)]/90',
                'border border-slate-700/50 hover:border-[var(--color-accent)]/50',
                'text-white/80 hover:text-white',
                'backdrop-blur-sm',
                'transition-all duration-300',
                'disabled:opacity-30 disabled:cursor-not-allowed',
                'shadow-lg'
              )}
              aria-label="Next videos"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>
        </div>
      </section>

      {/* Video Modal */}
      {activeVideo && (
        <VideoModal
          video={activeVideo}
          onClose={handleCloseModal}
          locale={locale}
        />
      )}
    </>
  );
};

export default VideoShowsSlider;
