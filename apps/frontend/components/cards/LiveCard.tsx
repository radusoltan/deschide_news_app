/**
 * Live Card Component
 * Live blogs, crisis coverage, real-time updates
 *
 * Grid span: col-span-8 or col-span-12 (full-width during crisis)
 * Pulsing red dot + LIVE text, update count badge
 */

import Link from 'next/link';
import { formatRelativeTime } from './utils';

interface LiveCardProps {
  title: string;
  updateCount?: number;
  latestUpdate?: string;
  href: string;
  className?: string;
  locale?: string;
}

export function LiveCard({
  title,
  updateCount,
  latestUpdate,
  href,
  className = '',
  locale = 'ro'
}: LiveCardProps) {
  const relativeTime = latestUpdate ? formatRelativeTime(latestUpdate, locale) : '';

  // Localized text
  const liveText = locale === 'ru' ? 'ПРЯМОЙ ЭФИР' : locale === 'en' ? 'LIVE' : 'ÎN DIRECT';
  const updatesText = updateCount
    ? locale === 'ru'
      ? `${updateCount} обновлений`
      : locale === 'en'
      ? `${updateCount} updates`
      : `${updateCount} actualizări`
    : '';
  const latestText = locale === 'ru' ? 'Последнее:' : locale === 'en' ? 'Latest:' : 'Ultima:';

  return (
    <article className={`group bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)] rounded-[var(--radius-card)] p-6 hover-lift transition-all duration-300 col-span-full @lg:col-span-8 @xl:col-span-12 ${className}`}>
      <Link href={href} className="block">
        {/* Live indicator and update count */}
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-3">
            {/* Pulsing red dot */}
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 rounded-full bg-red-500 animate-breaking-pulse"></div>
              <span className="text-red-500 font-bold tracking-wide text-sm font-sans">
                {liveText}
              </span>
            </div>

            {/* Update count badge */}
            {updateCount && updateCount > 0 && (
              <span className="px-3 py-1 text-xs font-semibold tracking-wider rounded-full bg-[var(--color-accent)] text-white font-sans">
                {updatesText}
              </span>
            )}
          </div>

          {/* Latest update time */}
          {relativeTime && (
            <time
              dateTime={latestUpdate}
              className="text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)] text-xs font-sans"
            >
              {latestText} {relativeTime}
            </time>
          )}
        </div>

        {/* Title */}
        <h3
          className="text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] leading-[var(--leading-snug)] mb-4 group-hover:text-[var(--color-accent)] dark:group-hover:text-[var(--color-accent)] transition-colors duration-200 font-sans line-clamp-2"
          style={{ fontSize: 'var(--font-size-2xl)' }}
        >
          {title}
        </h3>

        {/* Latest update preview (if provided) */}
        {latestUpdate && (
          <div className="relative">
            {/* Animated scan line */}
            <div className="absolute top-0 left-0 w-full h-px bg-gradient-to-r from-transparent via-red-500 to-transparent animate-live-scan"></div>

            <div className="bg-[var(--color-surface-sunken)] dark:bg-[var(--color-surface-sunken-dark)] rounded-[var(--radius-md)] p-4 mt-2">
              <p
                className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] leading-[var(--leading-relaxed)] line-clamp-3 font-serif"
                style={{ fontSize: 'var(--font-size-base)' }}
              >
                Urmărește actualizările în timp real pentru această știre în curs de dezvoltare...
              </p>
            </div>
          </div>
        )}

        {/* Call to action */}
        <div className="mt-4 flex items-center gap-2 text-[var(--color-accent)] font-medium text-sm font-sans group-hover:gap-3 transition-all duration-200">
          <span>
            {locale === 'ru' ? 'Следить за обновлениями' : locale === 'en' ? 'Follow updates' : 'Urmărește actualizările'}
          </span>
          <svg
            className="w-4 h-4 transition-transform duration-200 group-hover:translate-x-1"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
          >
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
          </svg>
        </div>

        {/* Focus state */}
        <div className="absolute inset-0 rounded-[var(--radius-card)] opacity-0 group-focus-within:opacity-100 ring-3 ring-[var(--color-focus)] dark:ring-[var(--color-focus-dark)] pointer-events-none transition-opacity duration-200" />
      </Link>
    </article>
  );
}