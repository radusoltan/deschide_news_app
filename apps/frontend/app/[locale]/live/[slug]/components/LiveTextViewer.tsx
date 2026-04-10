'use client';

import { useState, useEffect, useRef } from 'react';
import { LiveTextTimeline } from './LiveTextTimeline';
import { ReactionButtons } from './ReactionButtons';
import type { LiveText, LiveTextPost, LiveTextSportMatch } from '@/lib/types/livetext';
import type { MatchStatistics } from '@/lib/types/sport';
import { createSafeHtml } from '@/lib/sanitize';

// LiveTextSportMatch extended with optional statistics (present in API responses)
type SportMatchWithStats = LiveTextSportMatch & {
  statistics?: MatchStatistics;
};

interface LiveTextViewerProps {
  liveText: LiveText;
  keyPoints: LiveTextPost[];
  locale: string;
}

// ─────────────────────────────────────────────────────────────
// Sport icon helpers
// ─────────────────────────────────────────────────────────────

const SportIcon = ({ type, className = '' }: { type: string; className?: string }) => {
  const icons: Record<string, string> = {
    football: 'M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 2a8 8 0 110 16A8 8 0 0112 4zm-1 3v2H9v2h2v2H9v2h2v2h2v-2h2v-2h-2v-2h2V9h-2V7h-2z',
    basketball: 'M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zM4.07 13H7.1a14.46 14.46 0 00.605 3.41A8.008 8.008 0 014.07 13zm0-2a8.008 8.008 0 013.63-3.41A14.46 14.46 0 007.1 11H4.07zM13 4.07V7.1a14.46 14.46 0 00-3.41.605A8.008 8.008 0 0113 4.07zm-2 15.86a8.008 8.008 0 01-3.41-3.63A14.46 14.46 0 0011 16.9v3.03zM11 15H9.12a12.44 12.44 0 010-6H11V15zm2 4.93V16.9c.41.03.82.07 1.22.12.5.07.98.16 1.44.28a8.008 8.008 0 01-2.66 2.63zM13 15v-6h1.88a12.44 12.44 0 010 6H13zm2.9-8.41A14.46 14.46 0 0016.9 11h3.03a8.008 8.008 0 00-4.03-4.41zM15 11H13V9.12c1.03.05 2.03.21 2.97.43.08.31.14.62.19.94.1.56.16 1.14.18 1.51H15zm0 2h.19a12.44 12.44 0 01-.19 1.51 12.1 12.1 0 01-.97.43A14.26 14.26 0 0113 14.88V13h2zm2.9 3.41A8.008 8.008 0 0019.93 13H16.9a14.46 14.46 0 01-.605 3.41zM16.9 11h3.03a8.008 8.008 0 00-3.63-4.41A14.46 14.46 0 0116.9 11z',
    tennis: 'M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 2a8 8 0 11-.001 16.001A8 8 0 0112 4zm-3.5 2.5a6 6 0 000 11 6 6 0 000-11zm7 0a6 6 0 000 11 6 6 0 000-11z',
    default: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15v-4H7l5-8v4h4l-5 8z',
  };
  return (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor">
      <path d={icons[type] || icons.default} />
    </svg>
  );
};

const MatchStatusDot = ({ status }: { status: string }) => {
  if (status === 'live') {
    return (
      <span className="relative flex h-2.5 w-2.5">
        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75" />
        <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500" />
      </span>
    );
  }
  if (status === 'half_time') {
    return <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-400" />;
  }
  if (status === 'finished') {
    return <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-gray-400" />;
  }
  return <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-blue-400" />;
};

// ─────────────────────────────────────────────────────────────
// Scoreboard Component
// ─────────────────────────────────────────────────────────────

interface ScoreboardProps {
  sportMatch: NonNullable<LiveText['sportMatch']>;
  locale: string;
}

const matchStatusLabels: Record<string, Record<string, string>> = {
  ro: {
    live: 'LIVE',
    half_time: 'PAUZA',
    finished: 'FINAL',
    not_started: 'Necomencat',
    postponed: 'AMÂNAT',
    cancelled: 'ANULAT',
  },
  en: {
    live: 'LIVE',
    half_time: 'HT',
    finished: 'FT',
    not_started: 'Not started',
    postponed: 'POSTPONED',
    cancelled: 'CANCELLED',
  },
  ru: {
    live: 'В ЭФИРЕ',
    half_time: 'ПЕРЕРЫВ',
    finished: 'ФИНАЛ',
    not_started: 'Не начался',
    postponed: 'ПЕРЕНЕСЕН',
    cancelled: 'ОТМЕНЕН',
  },
};

function Scoreboard({ sportMatch, locale }: ScoreboardProps) {
  const statusLabel =
    (matchStatusLabels[locale] || matchStatusLabels.ro)[sportMatch.status] || sportMatch.status;

  const isLive = sportMatch.status === 'live';
  const isHalfTime = sportMatch.status === 'half_time';
  const isFinished = sportMatch.status === 'finished';

  return (
    <div className="relative overflow-hidden rounded-2xl">
      {/* Layered dark gradient background — sports feel */}
      <div
        className="absolute inset-0"
        style={{
          background:
            'linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #0f172a 100%)',
        }}
      />
      {/* Subtle radial glow */}
      <div
        className="absolute inset-0 opacity-40"
        style={{
          background:
            'radial-gradient(ellipse 60% 50% at 50% 50%, rgba(239,68,68,0.18) 0%, transparent 70%)',
        }}
      />

      {/* Content */}
      <div className="relative z-10 px-4 py-5 sm:px-8 sm:py-7">
        {/* Competition + status row */}
        <div className="flex items-center justify-center gap-3 mb-5">
          <SportIcon
            type={sportMatch.sportType}
            className="w-4 h-4 text-slate-400 shrink-0"
          />
          {sportMatch.competition && (
            <span className="text-xs font-semibold uppercase tracking-widest text-slate-400">
              {sportMatch.competition}
            </span>
          )}
          {sportMatch.venue && (
            <>
              <span className="text-slate-600 text-xs">·</span>
              <span className="text-xs text-slate-500 hidden sm:inline">{sportMatch.venue}</span>
            </>
          )}
        </div>

        {/* Teams + Score */}
        <div className="grid grid-cols-3 items-center gap-2 sm:gap-6">
          {/* Home team */}
          <div className="flex flex-col items-center sm:items-end gap-1.5 text-center sm:text-right">
            {sportMatch.homeTeamLogo ? (
              <img
                src={sportMatch.homeTeamLogo}
                alt={sportMatch.homeTeam}
                className="w-10 h-10 sm:w-14 sm:h-14 object-contain drop-shadow"
              />
            ) : (
              <div className="w-10 h-10 sm:w-14 sm:h-14 rounded-full bg-blue-600/20 ring-2 ring-blue-500/40 flex items-center justify-center">
                <span className="text-blue-300 text-lg sm:text-xl font-black leading-none">
                  {sportMatch.homeTeam.charAt(0)}
                </span>
              </div>
            )}
            <span className="text-white font-bold text-sm sm:text-base leading-tight max-w-[100px] sm:max-w-none">
              {sportMatch.homeTeam}
            </span>
          </div>

          {/* Score block */}
          <div className="flex flex-col items-center gap-2">
            <div className="flex items-center gap-2 sm:gap-3">
              <span className="text-4xl sm:text-6xl font-black text-white tabular-nums leading-none tracking-tight">
                {sportMatch.homeScore}
              </span>
              <span className="text-2xl sm:text-4xl font-light text-slate-500 leading-none">:</span>
              <span className="text-4xl sm:text-6xl font-black text-white tabular-nums leading-none tracking-tight">
                {sportMatch.awayScore}
              </span>
            </div>

            {/* Match status */}
            <div className="flex items-center gap-1.5">
              <MatchStatusDot status={sportMatch.status} />
              <span
                className={`text-xs font-bold uppercase tracking-wider ${
                  isLive
                    ? 'text-red-400'
                    : isHalfTime
                    ? 'text-amber-400'
                    : isFinished
                    ? 'text-slate-400'
                    : 'text-blue-400'
                }`}
              >
                {statusLabel}
              </span>
              {isLive && sportMatch.currentMinute && (
                <span className="text-xs text-slate-400 font-medium">
                  {sportMatch.currentMinute}&apos;
                </span>
              )}
            </div>

            {sportMatch.currentPeriod && (
              <span className="text-xs text-slate-500 uppercase tracking-wide">
                {sportMatch.currentPeriod}
              </span>
            )}
          </div>

          {/* Away team */}
          <div className="flex flex-col items-center sm:items-start gap-1.5 text-center sm:text-left">
            {sportMatch.awayTeamLogo ? (
              <img
                src={sportMatch.awayTeamLogo}
                alt={sportMatch.awayTeam}
                className="w-10 h-10 sm:w-14 sm:h-14 object-contain drop-shadow"
              />
            ) : (
              <div className="w-10 h-10 sm:w-14 sm:h-14 rounded-full bg-red-600/20 ring-2 ring-red-500/40 flex items-center justify-center">
                <span className="text-red-300 text-lg sm:text-xl font-black leading-none">
                  {sportMatch.awayTeam.charAt(0)}
                </span>
              </div>
            )}
            <span className="text-white font-bold text-sm sm:text-base leading-tight max-w-[100px] sm:max-w-none">
              {sportMatch.awayTeam}
            </span>
          </div>
        </div>
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────
// Statistics Panel
// ─────────────────────────────────────────────────────────────

interface StatRowProps {
  label: string;
  home: number;
  away: number;
  isPercentage?: boolean;
}

function StatRow({ label, home, away, isPercentage }: StatRowProps) {
  const total = home + away || 1;
  const homePct = Math.round((home / total) * 100);
  const awayPct = 100 - homePct;

  return (
    <div className="space-y-1">
      <div className="flex items-center justify-between text-xs font-semibold">
        <span className="text-blue-400 tabular-nums">{isPercentage ? `${home}%` : home}</span>
        <span className="text-slate-400 uppercase tracking-wider text-center px-2">{label}</span>
        <span className="text-red-400 tabular-nums">{isPercentage ? `${away}%` : away}</span>
      </div>
      <div className="flex h-1.5 rounded-full overflow-hidden bg-slate-700">
        <div
          className="bg-blue-500 transition-all duration-700"
          style={{ width: `${isPercentage ? home : homePct}%` }}
        />
        <div
          className="bg-red-500 transition-all duration-700 ml-auto"
          style={{ width: `${isPercentage ? away : awayPct}%` }}
        />
      </div>
    </div>
  );
}

interface StatisticsPanelProps {
  statistics: MatchStatistics;
  locale: string;
}

const statLabels: Record<string, Record<string, string>> = {
  ro: {
    possession: 'Posesie',
    shots: 'Șuturi',
    shotsOnTarget: 'Șuturi pe poartă',
    corners: 'Cornere',
    fouls: 'Faulturi',
    offsides: 'Ofsaid',
    passes: 'Pase',
    passAccuracy: 'Precizie pase',
  },
  en: {
    possession: 'Possession',
    shots: 'Shots',
    shotsOnTarget: 'Shots on target',
    corners: 'Corners',
    fouls: 'Fouls',
    offsides: 'Offsides',
    passes: 'Passes',
    passAccuracy: 'Pass accuracy',
  },
  ru: {
    possession: 'Владение',
    shots: 'Удары',
    shotsOnTarget: 'Удары в створ',
    corners: 'Угловые',
    fouls: 'Фолы',
    offsides: 'Офсайды',
    passes: 'Передачи',
    passAccuracy: 'Точность пасов',
  },
};

const percentageStats = new Set(['possession', 'passAccuracy']);

function StatisticsPanel({ statistics, locale }: StatisticsPanelProps) {
  const labels = statLabels[locale] || statLabels.ro;
  const entries = Object.entries(statistics).filter(
    ([, value]) =>
      value !== null &&
      value !== undefined &&
      typeof value === 'object' &&
      'home' in value &&
      'away' in value
  ) as [string, { home: number; away: number }][];

  if (entries.length === 0) return null;

  return (
    <div className="rounded-xl bg-slate-900 border border-slate-700/60 overflow-hidden">
      <div className="px-4 py-3 border-b border-slate-700/60">
        <h3 className="text-xs font-bold uppercase tracking-widest text-slate-400">
          {locale === 'ru' ? 'Статистика матча' : locale === 'en' ? 'Match Statistics' : 'Statistici meci'}
        </h3>
      </div>
      <div className="px-4 py-4 space-y-4">
        {entries.map(([key, value]) => (
          <StatRow
            key={key}
            label={labels[key] || key}
            home={value.home}
            away={value.away}
            isPercentage={percentageStats.has(key)}
          />
        ))}
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────
// Key Points Sidebar
// ─────────────────────────────────────────────────────────────

const keyPointIcons: Record<string, string> = {
  goal: '⚽',
  penalty_goal: '⚽',
  own_goal: '⚽',
  yellow_card: '🟨',
  red_card: '🟥',
  second_yellow_card: '🟥',
  substitution: '🔄',
  injury: '🚑',
  half_time: '⏸',
  full_time: '🏁',
  var_check: '📺',
  kick_off: '🏃',
  default: '📍',
};

interface KeyPointsSidebarProps {
  keyPoints: LiveTextPost[];
  locale: string;
  colors: { primary: string; secondary: string; accent: string };
  onJumpToPost: (id: number) => void;
}

function KeyPointsSidebar({ keyPoints, locale, colors, onJumpToPost }: KeyPointsSidebarProps) {
  const t = {
    ro: { title: 'Momente Cheie', empty: 'Nu există momente cheie încă' },
    en: { title: 'Key Moments', empty: 'No key moments yet' },
    ru: { title: 'Ключевые моменты', empty: 'Ключевых моментов пока нет' },
  }[locale] || { title: 'Momente Cheie', empty: 'Nu există momente cheie încă' };

  const formatTime = (d: string) =>
    new Intl.DateTimeFormat(locale, { hour: '2-digit', minute: '2-digit' }).format(new Date(d));

  const stripHtml = (html: string) => {
    if (typeof document === 'undefined') return html;
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
  };

  return (
    <div className="rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700/60 bg-surface dark:bg-surface-dark">
      <div className="px-4 py-3 border-b border-slate-200 dark:border-slate-700/60 flex items-center gap-2">
        <span
          className="w-2 h-2 rounded-full inline-block"
          style={{ backgroundColor: colors.primary }}
        />
        <h3 className="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">
          {t.title}
        </h3>
      </div>

      {keyPoints.length === 0 ? (
        <div className="px-4 py-8 text-center">
          <p className="text-sm text-slate-400 dark:text-slate-500">{t.empty}</p>
        </div>
      ) : (
        <div className="relative">
          {/* Vertical connector line */}
          <div className="absolute left-[27px] top-2 bottom-2 w-px bg-slate-200 dark:bg-slate-700/60" />

          <div className="py-2">
            {keyPoints.map((post) => {
              const icon = post.eventType
                ? (keyPointIcons[post.eventType] || keyPointIcons.default)
                : keyPointIcons.default;

              return (
                <button
                  key={post.id}
                  onClick={() => onJumpToPost(post.id)}
                  className="group w-full text-left pl-4 pr-4 py-2.5 flex items-start gap-3 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors"
                >
                  {/* Icon bubble */}
                  <div
                    className="relative z-10 shrink-0 mt-0.5 w-7 h-7 rounded-full flex items-center justify-center text-sm ring-2 ring-white dark:ring-slate-900"
                    style={{ backgroundColor: `${colors.primary}22` }}
                  >
                    <span style={{ filter: 'none' }}>{icon}</span>
                  </div>

                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-1.5 mb-0.5">
                      <span
                        className="text-xs font-bold tabular-nums"
                        style={{ color: colors.primary }}
                      >
                        {formatTime(post.publishedAt)}
                      </span>
                    </div>
                    <p className="text-xs text-slate-600 dark:text-slate-400 line-clamp-2 leading-relaxed group-hover:text-slate-900 dark:group-hover:text-slate-200 transition-colors">
                      {stripHtml(post.contentHtml || post.content)}
                    </p>
                  </div>
                </button>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────
// Post Card
// ─────────────────────────────────────────────────────────────

interface PostCardProps {
  post: LiveTextPost;
  colors: { primary: string; secondary: string; accent: string; background: string };
  isNew?: boolean;
  locale: string;
  keyPointLabel: string;
  authorLabel: string;
  postRef: (el: HTMLDivElement | null) => void;
}

function PostCard({ post, colors, isNew, locale, keyPointLabel, authorLabel, postRef }: PostCardProps) {
  const timeStr = new Intl.DateTimeFormat(locale, {
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(post.publishedAt));

  return (
    <article
      ref={postRef}
      className={`
        group relative flex gap-4 sm:gap-5 px-0
        transition-all duration-500 ease-out
        ${isNew ? 'animate-[fadeSlideIn_0.4s_ease-out]' : ''}
      `}
    >
      {/* Timeline spine */}
      <div className="flex flex-col items-center shrink-0 pt-1">
        {/* Timestamp */}
        <div className="flex flex-col items-center gap-1">
          <span
            className={`text-xs font-bold tabular-nums leading-none ${
              post.isKeyPoint ? '' : 'text-slate-400 dark:text-slate-500'
            }`}
            style={post.isKeyPoint ? { color: colors.primary } : {}}
          >
            {timeStr}
          </span>
        </div>

        {/* Dot */}
        <div className="mt-2 mb-1">
          {post.isKeyPoint ? (
            <div
              className="w-4 h-4 rounded-full ring-4 ring-white dark:ring-slate-900 shadow-md"
              style={{ backgroundColor: colors.primary }}
            />
          ) : (
            <div className="w-2.5 h-2.5 rounded-full bg-slate-300 dark:bg-slate-600 ring-2 ring-white dark:ring-slate-900" />
          )}
        </div>

        {/* Connector line (rendered via border trick — avoids DOM thrash) */}
        <div className="flex-1 w-px bg-slate-200 dark:bg-slate-700/60 min-h-[24px]" />
      </div>

      {/* Card body */}
      <div
        className={`
          flex-1 min-w-0 mb-4 rounded-xl border transition-all duration-200
          group-hover:shadow-md
          ${
            post.isKeyPoint
              ? 'border-l-4 shadow-sm'
              : 'border-slate-200 dark:border-slate-700/60 bg-surface dark:bg-surface-dark/60'
          }
        `}
        style={
          post.isKeyPoint
            ? {
                borderLeftColor: colors.primary,
                borderTopColor: 'var(--color-border)',
                borderRightColor: 'var(--color-border)',
                borderBottomColor: 'var(--color-border)',
                backgroundColor: `${colors.background}`,
              }
            : {}
        }
      >
        {/* Card header */}
        {(post.isKeyPoint || post.author) && (
          <div className="flex items-center justify-between px-4 pt-3 pb-0">
            <div className="flex items-center gap-2">
              {post.isKeyPoint && (
                <span
                  className="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full text-white leading-tight"
                  style={{ backgroundColor: colors.primary }}
                >
                  <svg className="w-2.5 h-2.5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                  </svg>
                  {keyPointLabel}
                </span>
              )}
            </div>
            {post.author && (
              <div className="flex items-center gap-1.5 text-xs text-slate-400 dark:text-slate-500">
                <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>{post.author.username}</span>
              </div>
            )}
          </div>
        )}

        {/* Content */}
        <div
          className="px-4 py-3 prose prose-sm dark:prose-invert max-w-none
            prose-p:leading-relaxed prose-p:my-1
            prose-headings:font-bold prose-headings:text-slate-900 dark:prose-headings:text-white
            prose-strong:text-slate-800 dark:prose-strong:text-slate-200
            prose-a:text-blue-600 dark:prose-a:text-blue-400"
          dangerouslySetInnerHTML={createSafeHtml(post.contentHtml || post.content)}
        />

        {/* Reactions */}
        <div className="px-4 pb-3">
          <ReactionButtons postId={post.id} locale={locale} />
        </div>
      </div>
    </article>
  );
}

// ─────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────

export function LiveTextViewer({ liveText: initialLiveText, keyPoints: initialKeyPoints, locale }: LiveTextViewerProps) {
  const [showKeyPointsOnly, setShowKeyPointsOnly] = useState(false);
  const [posts, setPosts] = useState<LiveTextPost[]>(initialLiveText.posts || []);
  const [keyPoints, setKeyPoints] = useState(initialKeyPoints);
  const [newPostIds, setNewPostIds] = useState<Set<number>>(new Set());
  const postRefs = useRef<Map<number, HTMLDivElement>>(new Map());

  const template = initialLiveText.template;
  const colors = template?.config?.colors || {
    primary: '#ef4444',
    secondary: 'var(--color-breaking)',
    accent: '#b91c1c',
    background: '#fef2f2',
    text: '#7f1d1d',
  };

  const sportMatch = initialLiveText.sportMatch as SportMatchWithStats | null | undefined;
  const hasStats =
    sportMatch?.statistics && Object.keys(sportMatch.statistics).length > 0;

  // ── i18n ──────────────────────────────────────────────────
  const texts = {
    ro: {
      allPosts: 'Toate Postările',
      keyPointsOnly: 'Momente Cheie',
      live: 'LIVE',
      ended: 'ÎNCHEIAT',
      paused: 'PAUZĂ',
      keyPoint: 'Moment Cheie',
      author: 'Autor',
      noPosts: 'Nu există postări încă',
      noKeyPoints: 'Nu există momente cheie',
      liveCommentary: 'Comentariu LIVE',
      postsCount: (n: number) => `${n} ${n === 1 ? 'postare' : 'postări'}`,
    },
    en: {
      allPosts: 'All Posts',
      keyPointsOnly: 'Key Points',
      live: 'LIVE',
      ended: 'ENDED',
      paused: 'PAUSED',
      keyPoint: 'Key Point',
      author: 'Author',
      noPosts: 'No posts yet',
      noKeyPoints: 'No key points yet',
      liveCommentary: 'Live Commentary',
      postsCount: (n: number) => `${n} ${n === 1 ? 'post' : 'posts'}`,
    },
    ru: {
      allPosts: 'Все посты',
      keyPointsOnly: 'Ключевые моменты',
      live: 'В ЭФИРЕ',
      ended: 'ЗАВЕРШЕНО',
      paused: 'ПАУЗА',
      keyPoint: 'Ключевой момент',
      author: 'Автор',
      noPosts: 'Постов пока нет',
      noKeyPoints: 'Ключевых моментов нет',
      liveCommentary: 'Прямой эфир',
      postsCount: (n: number) => `${n} ${n === 1 ? 'пост' : 'постов'}`,
    },
  };

  const t = texts[locale as keyof typeof texts] || texts.ro;

  // ── Timestamp formatter ────────────────────────────────────
  const formatTimestamp = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);

    if (diffMins < 1) return locale === 'ro' ? 'Acum' : locale === 'en' ? 'Now' : 'Сейчас';
    if (diffMins < 60) return `${diffMins}${locale === 'ro' ? ' min' : locale === 'en' ? ' min' : ' мин'}`;
    if (diffMins < 1440) {
      const hours = Math.floor(diffMins / 60);
      return `${hours}${locale === 'ro' ? ' ore' : locale === 'en' ? ' hours' : ' ч'}`;
    }
    return new Intl.DateTimeFormat(locale, {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    }).format(date);
  };

  // ── Status badge ──────────────────────────────────────────
  const getStatusBadge = () => {
    const statusText: Record<string, string> = {
      live: t.live,
      paused: t.paused,
      ended: t.ended,
      draft: 'DRAFT',
    };
    const text = statusText[initialLiveText.status] || 'DRAFT';

    if (initialLiveText.status === 'live') {
      return (
        <span className="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-bold rounded-full text-white"
          style={{ backgroundColor: colors.primary }}>
          <span className="relative flex h-1.5 w-1.5">
            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-surface opacity-75" />
            <span className="relative inline-flex rounded-full h-1.5 w-1.5 bg-surface" />
          </span>
          {text}
        </span>
      );
    }

    const colorMap: Record<string, string> = {
      paused: 'bg-amber-500 text-white',
      ended: 'bg-slate-500 text-white',
      draft: 'bg-slate-400 text-white',
    };
    return (
      <span className={`px-3 py-1 text-xs font-bold rounded-full ${colorMap[initialLiveText.status] || colorMap.draft}`}>
        {text}
      </span>
    );
  };

  // ── Filtered posts ────────────────────────────────────────
  const filteredPosts = showKeyPointsOnly
    ? posts.filter((p) => p.isKeyPoint)
    : posts;

  // ── Jump to post ──────────────────────────────────────────
  const handleJumpToPost = (postId: number) => {
    const element = postRefs.current.get(postId);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth', block: 'center' });
      element.style.outline = `2px solid ${colors.primary}`;
      element.style.outlineOffset = '4px';
      element.style.borderRadius = '12px';
      setTimeout(() => {
        element.style.outline = '';
        element.style.outlineOffset = '';
        element.style.borderRadius = '';
      }, 2200);
    }
  };

  // ── Render ─────────────────────────────────────────────────
  return (
    <>
      {/* Custom keyframe for new-post entrance */}
      <style>{`
        @keyframes fadeSlideIn {
          from { opacity: 0; transform: translateY(-12px); }
          to   { opacity: 1; transform: translateY(0); }
        }
      `}</style>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-6">

        {/* ── Sport Scoreboard ──────────────────────────────── */}
        {sportMatch ? (
          <section aria-label="Scoreboard">
            <Scoreboard sportMatch={sportMatch} locale={locale} />
          </section>
        ) : (
          /* ── Generic live header (non-sport) ──────────────── */
          <header className="space-y-3">
            <div className="flex items-center flex-wrap gap-3">
              {getStatusBadge()}
              {initialLiveText.category && (
                <span className="text-sm text-slate-500 dark:text-slate-400 font-medium">
                  {initialLiveText.category.title}
                </span>
              )}
            </div>
            <h1 className="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-primary-dark leading-tight tracking-tight">
              {initialLiveText.title}
            </h1>
            {initialLiveText.description && (
              <p className="text-base sm:text-lg text-slate-600 dark:text-slate-300 leading-relaxed max-w-3xl">
                {initialLiveText.description}
              </p>
            )}
          </header>
        )}

        {/* ── Sport: title row under scoreboard ────────────── */}
        {sportMatch && (
          <header>
            <div className="flex items-center flex-wrap gap-3 mb-2">
              {getStatusBadge()}
              {initialLiveText.category && (
                <span className="text-sm text-slate-500 dark:text-slate-400 font-medium">
                  {initialLiveText.category.title}
                </span>
              )}
            </div>
            <h1 className="text-xl sm:text-2xl font-bold text-slate-900 dark:text-primary-dark leading-snug">
              {initialLiveText.title}
            </h1>
            {initialLiveText.description && (
              <p className="mt-1 text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                {initialLiveText.description}
              </p>
            )}
          </header>
        )}

        {/* ── Statistics ────────────────────────────────────── */}
        {hasStats && (
          <section aria-label="Match statistics">
            <StatisticsPanel
              statistics={sportMatch!.statistics as MatchStatistics}
              locale={locale}
            />
          </section>
        )}

        {/* ── Filter toggle ─────────────────────────────────── */}
        <div className="flex items-center justify-between gap-4">
          <div className="flex items-center gap-2">
            <h2 className="text-sm font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">
              {t.liveCommentary}
            </h2>
            <span className="text-xs font-semibold text-slate-400 dark:text-slate-500 tabular-nums">
              {t.postsCount(filteredPosts.length)}
            </span>
          </div>

          <div className="inline-flex items-center rounded-full border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-surface-dark/80 p-0.5 gap-0.5">
            <button
              onClick={() => setShowKeyPointsOnly(false)}
              className={`px-3 py-1.5 rounded-full text-xs font-semibold transition-all ${
                !showKeyPointsOnly
                  ? 'bg-surface dark:bg-slate-700 text-slate-900 dark:text-primary-dark shadow-sm'
                  : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
              }`}
            >
              {t.allPosts}
            </button>
            <button
              onClick={() => setShowKeyPointsOnly(true)}
              className={`px-3 py-1.5 rounded-full text-xs font-semibold transition-all ${
                showKeyPointsOnly
                  ? 'bg-surface dark:bg-slate-700 text-slate-900 dark:text-primary-dark shadow-sm'
                  : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
              }`}
            >
              {t.keyPointsOnly}
            </button>
          </div>
        </div>

        {/* ── Main two-column layout ────────────────────────── */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8 items-start">

          {/* Feed — 2/3 */}
          <div className="lg:col-span-2">
            {filteredPosts.length === 0 ? (
              <div className="rounded-xl border border-dashed border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-surface-dark/30 p-12 text-center">
                <div className="w-12 h-12 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                  <svg className="w-6 h-6 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <p className="text-sm font-medium text-slate-500 dark:text-slate-400">
                  {showKeyPointsOnly ? t.noKeyPoints : t.noPosts}
                </p>
              </div>
            ) : (
              <div className="relative">
                {filteredPosts.map((post) => (
                  <PostCard
                    key={post.id}
                    post={post}
                    colors={colors}
                    isNew={newPostIds.has(post.id)}
                    locale={locale}
                    keyPointLabel={t.keyPoint}
                    authorLabel={t.author}
                    postRef={(el) => {
                      if (el) postRefs.current.set(post.id, el);
                    }}
                  />
                ))}
              </div>
            )}
          </div>

          {/* Sidebar — 1/3 */}
          <div className="lg:col-span-1 space-y-4">
            <div className="lg:sticky lg:top-6">
              {/* Key moments using our premium sidebar (replaces LiveTextTimeline on desktop) */}
              <div className="hidden lg:block">
                <KeyPointsSidebar
                  keyPoints={keyPoints}
                  locale={locale}
                  colors={colors}
                  onJumpToPost={handleJumpToPost}
                />
              </div>

              {/* On mobile keep the original LiveTextTimeline */}
              <div className="lg:hidden">
                <LiveTextTimeline
                  liveTextId={initialLiveText.id}
                  keyPoints={keyPoints}
                  locale={locale}
                  onJumpToPost={handleJumpToPost}
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
