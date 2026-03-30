'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { cn } from '@/lib/utils/cn';

// Types
interface LiveTextCategory {
  id: number;
  title: string;
  slug: string;
}

interface SportMatch {
  id: number;
  sportType: string;
  homeTeam: string;
  awayTeam: string;
  homeScore: number;
  awayScore: number;
  status: string;
  competition?: string;
}

interface LiveTextItem {
  id: number;
  title: string;
  slug: string;
  description: string | null;
  status: string;
  startTime: string | null;
  category: LiveTextCategory | null;
  sportMatch?: SportMatch | null;
}

interface LiveTextHomepageSectionProps {
  liveTexts: LiveTextItem[];
  locale: string;
  className?: string;
}

// Translations
const translations: Record<string, { live: string; watching: string; follow: string; vs: string; score: string }> = {
  ro: { live: 'LIVE', watching: 'urmăresc', follow: 'Urmărește', vs: 'vs', score: 'Scor' },
  en: { live: 'LIVE', watching: 'watching', follow: 'Follow', vs: 'vs', score: 'Score' },
  ru: { live: 'LIVE', watching: 'смотрят', follow: 'Следить', vs: 'против', score: 'Счёт' },
};

// Sport type icons
const SportIcon: React.FC<{ type: string; className?: string }> = ({ type, className }) => {
  const icons: Record<string, React.ReactElement> = {
    football: (
      <svg className={className} viewBox="0 0 24 24" fill="currentColor">
        <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" strokeWidth="1.5" />
        <path d="M12 2v4M12 18v4M2 12h4M18 12h4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
      </svg>
    ),
    handball: (
      <svg className={className} viewBox="0 0 24 24" fill="currentColor">
        <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" strokeWidth="1.5" />
        <path d="M12 3c0 5-3 9-3 9s3 4 3 9M12 3c0 5 3 9 3 9s-3 4-3 9" stroke="currentColor" strokeWidth="1.5" fill="none" />
      </svg>
    ),
    basketball: (
      <svg className={className} viewBox="0 0 24 24" fill="currentColor">
        <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" strokeWidth="1.5" />
        <path d="M12 3v18M3 12h18M4.5 7.5c3 1.5 6 1.5 9 0M4.5 16.5c3-1.5 6-1.5 9 0" stroke="currentColor" strokeWidth="1.5" fill="none" />
      </svg>
    ),
    tennis: (
      <svg className={className} viewBox="0 0 24 24" fill="currentColor">
        <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" strokeWidth="1.5" />
        <path d="M3.5 8.5c5 1 7-3 17 3M3.5 15.5c10-6 12-2 17-3" stroke="currentColor" strokeWidth="1.5" fill="none" />
      </svg>
    ),
    default: (
      <svg className={className} viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke="currentColor" strokeWidth="1.5" fill="none" strokeLinecap="round" strokeLinejoin="round" />
      </svg>
    ),
  };
  return icons[type] || icons.default;
};

// Pulsing live indicator
const LiveIndicator: React.FC<{ size?: 'sm' | 'md' }> = ({ size = 'md' }) => (
  <span className={cn(
    'relative inline-flex items-center gap-1.5 font-mono font-bold tracking-wider',
    size === 'sm' ? 'text-[10px]' : 'text-xs'
  )}>
    <span className="relative flex h-2 w-2">
      <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75" />
      <span className="relative inline-flex rounded-full h-2 w-2 bg-red-600" />
    </span>
    <span className="text-red-500">LIVE</span>
  </span>
);

// Sport match card with score display
const SportMatchCard: React.FC<{
  liveText: LiveTextItem;
  locale: string;
}> = ({ liveText, locale }) => {
  const t = translations[locale] || translations.en;
  const match = liveText.sportMatch!;

  return (
    <Link
      href={`/${locale}/live/${liveText.slug}`}
      className={cn(
        'group relative block overflow-hidden',
        'bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900',
        'border border-slate-700/50 rounded-lg',
        'transition-all duration-300',
        'hover:border-red-500/50 hover:shadow-lg hover:shadow-red-500/10',
        'min-h-[140px]'
      )}
    >
      {/* Animated scan line */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute w-full h-px bg-gradient-to-r from-transparent via-red-500/30 to-transparent animate-live-scan" />
      </div>

      {/* Background pattern */}
      <div className="absolute inset-0 opacity-5">
        <div className="absolute inset-0" style={{
          backgroundImage: `radial-gradient(circle at 2px 2px, white 1px, transparent 0)`,
          backgroundSize: '24px 24px'
        }} />
      </div>

      <div className="relative p-4 h-full flex flex-col">
        {/* Header: Competition + Live badge */}
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-2">
            <SportIcon type={match.sportType} className="w-4 h-4 text-slate-400" />
            <span className="text-[10px] font-medium text-slate-400 uppercase tracking-wider truncate max-w-[120px]">
              {match.competition || match.sportType}
            </span>
          </div>
          <LiveIndicator size="sm" />
        </div>

        {/* Score display */}
        <div className="flex-1 flex items-center justify-center">
          <div className="flex items-center gap-3 sm:gap-4">
            {/* Home team */}
            <div className="text-right min-w-[60px] sm:min-w-[80px]">
              <p className="text-xs sm:text-sm font-semibold text-white truncate">
                {match.homeTeam}
              </p>
            </div>

            {/* Score */}
            <div className="flex items-center gap-1 px-3 py-1.5 bg-black/40 rounded-md border border-slate-600/50">
              <span className="text-xl sm:text-2xl font-mono font-black text-white tabular-nums">
                {match.homeScore}
              </span>
              <span className="text-slate-500 text-sm mx-1">:</span>
              <span className="text-xl sm:text-2xl font-mono font-black text-white tabular-nums">
                {match.awayScore}
              </span>
            </div>

            {/* Away team */}
            <div className="text-left min-w-[60px] sm:min-w-[80px]">
              <p className="text-xs sm:text-sm font-semibold text-white truncate">
                {match.awayTeam}
              </p>
            </div>
          </div>
        </div>

        {/* Footer: Category + CTA */}
        <div className="flex items-center justify-between mt-3 pt-2 border-t border-slate-700/50">
          {match.status && match.status !== 'live' && (
            <span className="text-[10px] text-amber-500 font-medium uppercase">
              {match.status.replace('_', ' ')}
            </span>
          )}
          <span className="ml-auto text-[10px] text-slate-500 group-hover:text-red-400 transition-colors flex items-center gap-1">
            {t.follow}
            <svg className="w-3 h-3 transform group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
            </svg>
          </span>
        </div>
      </div>
    </Link>
  );
};

// Generic live text card (non-sport)
const LiveTextCard: React.FC<{
  liveText: LiveTextItem;
  locale: string;
  featured?: boolean;
}> = ({ liveText, locale, featured = false }) => {
  const t = translations[locale] || translations.en;

  return (
    <Link
      href={`/${locale}/live/${liveText.slug}`}
      className={cn(
        'group relative block overflow-hidden',
        'bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900',
        'border border-slate-700/50 rounded-lg',
        'transition-all duration-300',
        'hover:border-red-500/50 hover:shadow-lg hover:shadow-red-500/10',
        featured ? 'min-h-[160px]' : 'min-h-[120px]'
      )}
    >
      {/* Animated scan line */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute w-full h-px bg-gradient-to-r from-transparent via-red-500/30 to-transparent animate-live-scan" />
      </div>

      {/* Red accent line on left */}
      <div className="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-red-500 via-red-600 to-red-500" />

      <div className="relative p-4 pl-5 h-full flex flex-col">
        {/* Header: Category + Live badge */}
        <div className="flex items-center justify-between mb-2">
          {liveText.category && (
            <span className="text-[10px] font-semibold text-red-400 uppercase tracking-wider">
              {liveText.category.title}
            </span>
          )}
          <LiveIndicator size="sm" />
        </div>

        {/* Title */}
        <h3 className={cn(
          'font-sans font-bold text-white leading-tight',
          'group-hover:text-red-100 transition-colors',
          featured ? 'text-base sm:text-lg line-clamp-3' : 'text-sm line-clamp-2'
        )}>
          {liveText.title}
        </h3>

        {/* Description (only for featured) */}
        {featured && liveText.description && (
          <p className="mt-2 text-xs text-slate-400 line-clamp-2 leading-relaxed">
            {liveText.description}
          </p>
        )}

        {/* Footer */}
        <div className="mt-auto pt-3 flex items-center justify-between">
          {liveText.startTime && (
            <span className="text-[10px] text-slate-500">
              {formatRelativeTime(liveText.startTime, locale)}
            </span>
          )}
          <span className="ml-auto text-[10px] text-slate-500 group-hover:text-red-400 transition-colors flex items-center gap-1">
            {t.follow}
            <svg className="w-3 h-3 transform group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
            </svg>
          </span>
        </div>
      </div>
    </Link>
  );
};

// Format relative time
function formatRelativeTime(dateString: string, locale: string): string {
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  const labels: Record<string, { ago: string; min: string; hour: string; day: string; started: string }> = {
    ro: { ago: 'în urmă', min: 'min', hour: 'h', day: 'zile', started: 'Început acum' },
    en: { ago: 'ago', min: 'min', hour: 'h', day: 'days', started: 'Started' },
    ru: { ago: 'назад', min: 'мин', hour: 'ч', day: 'дней', started: 'Начато' },
  };

  const l = labels[locale] || labels.en;

  if (diffMins < 1) return l.started;
  if (diffMins < 60) return `${l.started} ${diffMins} ${l.min} ${l.ago}`;
  if (diffHours < 24) return `${l.started} ${diffHours} ${l.hour} ${l.ago}`;
  return `${l.started} ${diffDays} ${l.day} ${l.ago}`;
}

/**
 * LiveTextHomepageSection - Premium live broadcast ticker for homepage
 *
 * Design: Dark editorial aesthetic with glowing red "LIVE" indicators,
 * scan line animations, and special sport match score displays.
 */
export const LiveTextHomepageSection: React.FC<LiveTextHomepageSectionProps> = ({
  liveTexts,
  locale,
  className,
}) => {
  // Don't render if no live texts
  if (!liveTexts || liveTexts.length === 0) {
    return null;
  }

  // Separate sport matches from regular live texts
  const sportMatches = liveTexts.filter(lt => lt.sportMatch);
  const regularLiveTexts = liveTexts.filter(lt => !lt.sportMatch);

  // Section title translations
  const sectionTitles: Record<string, string> = {
    ro: 'Transmisiuni Live',
    en: 'Live Coverage',
    ru: 'Прямые трансляции',
  };

  return (
    <section
      className={cn(
        'relative py-6',
        'bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950',
        className
      )}
      aria-label="Live broadcasts"
    >
      {/* Subtle noise texture */}
      <div className="absolute inset-0 opacity-[0.015] pointer-events-none"
        style={{
          backgroundImage: `url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E")`,
        }}
      />

      <div className="relative xl:container mx-auto px-4">
        {/* Section header */}
        <div className="flex items-center gap-3 mb-4">
          <div className="flex items-center gap-2">
            <span className="relative flex h-3 w-3">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75" />
              <span className="relative inline-flex rounded-full h-3 w-3 bg-red-600" />
            </span>
            <h2 className="text-lg sm:text-xl font-sans font-bold text-white tracking-tight">
              {sectionTitles[locale] || sectionTitles.en}
            </h2>
          </div>
          <div className="flex-1 h-px bg-gradient-to-r from-slate-700 via-slate-800 to-transparent" />
          <span className="text-xs text-slate-500 font-mono">
            {liveTexts.length} {locale === 'ro' ? 'active' : locale === 'ru' ? 'активных' : 'active'}
          </span>
        </div>

        {/* Cards grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
          {/* Sport matches first (with score display) */}
          {sportMatches.map((liveText) => (
            <SportMatchCard
              key={liveText.id}
              liveText={liveText}
              locale={locale}
            />
          ))}

          {/* Regular live texts */}
          {regularLiveTexts.map((liveText, index) => (
            <LiveTextCard
              key={liveText.id}
              liveText={liveText}
              locale={locale}
              featured={index === 0 && sportMatches.length === 0}
            />
          ))}
        </div>
      </div>
    </section>
  );
};

export default LiveTextHomepageSection;
