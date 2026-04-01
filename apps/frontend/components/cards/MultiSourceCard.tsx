/**
 * Multi-source Card Component
 * Same story from multiple publishers/languages - unique to multilingual portal
 *
 * Grid span: col-span-4
 * Primary headline with thumbnail, source list below with language indicators
 */

import Link from 'next/link';
import Image from 'next/image';

interface Source {
  label: string;
  locale: string;
  href: string;
}

interface MultiSourceCardProps {
  headline: string;
  sources: Source[];
  imageUrl?: string;
  className?: string;
}

export function MultiSourceCard({
  headline,
  sources,
  imageUrl,
  className = ''
}: MultiSourceCardProps) {
  const primarySource = sources[0];

  // Language flag mapping (using text indicators, not actual flags per design system)
  const getLanguageLabel = (locale: string): string => {
    switch (locale.toLowerCase()) {
      case 'ro':
      case 'romanian':
        return 'RO';
      case 'en':
      case 'english':
        return 'EN';
      case 'ru':
      case 'russian':
        return 'RU';
      default:
        return locale.toUpperCase().substring(0, 2);
    }
  };

  const getLanguageColor = (locale: string): string => {
    switch (locale.toLowerCase()) {
      case 'ro':
      case 'romanian':
        return 'var(--color-section-politics)';
      case 'en':
      case 'english':
        return 'var(--color-section-world)';
      case 'ru':
      case 'russian':
        return 'var(--color-section-society)';
      default:
        return 'var(--color-accent)';
    }
  };

  return (
    <article className={`group bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)] rounded-[var(--radius-card)] overflow-hidden hover-lift-sm transition-all duration-300 col-span-full @lg:col-span-4 ${className}`}>
      {/* Primary source link */}
      {primarySource && (
        <Link href={primarySource.href} className="block">
          {/* Image */}
          {imageUrl && (
            <figure className="relative aspect-video overflow-hidden">
              <Image
                src={imageUrl}
                alt={headline}
                fill
                sizes="(max-width: 740px) 100vw, (max-width: 1024px) 50vw, 33vw"
                className="object-cover transition-transform duration-500 group-hover:scale-105"
                loading="lazy"
              />
              {/* Overlay with multi-source indicator */}
              <div className="absolute top-3 right-3">
                <span className="px-2 py-1 text-xs font-semibold tracking-wider rounded-[var(--radius-sm)] bg-black/70 text-white font-sans">
                  {sources.length} sources
                </span>
              </div>
            </figure>
          )}

          {/* Content */}
          <div className="p-5">
            {/* Headline */}
            <h3
              className="text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] leading-[var(--leading-snug)] mb-4 group-hover:text-[var(--color-accent)] dark:group-hover:text-[var(--color-accent)] transition-colors duration-200 font-sans line-clamp-3"
              style={{ fontSize: 'var(--font-size-xl)' }}
            >
              {headline}
            </h3>
          </div>
        </Link>
      )}

      {/* Source list */}
      <div className="px-5 pb-5">
        <div className="border-t border-[var(--color-border)] dark:border-[var(--color-border-dark)] pt-4">
          <p className="text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)] text-xs font-medium mb-3 font-sans tracking-wide uppercase">
            Available in:
          </p>

          <div className="space-y-2">
            {sources.map((source, index) => (
              <Link
                key={index}
                href={source.href}
                className="flex items-center justify-between py-2 px-3 rounded-[var(--radius-sm)] bg-[var(--color-surface-sunken)] dark:bg-[var(--color-surface-sunken-dark)] hover:bg-[var(--color-border)] dark:hover:bg-[var(--color-border-dark)] transition-colors duration-200 group/source"
              >
                <span className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] text-sm font-medium font-sans group-hover/source:text-[var(--color-text-primary)] dark:group-hover/source:text-[var(--color-text-primary-dark)] transition-colors duration-200 truncate">
                  {source.label}
                </span>

                <div className="flex items-center gap-2 flex-shrink-0">
                  <span
                    className="px-2 py-1 text-xs font-bold rounded-[var(--radius-sm)] text-white font-sans"
                    style={{ backgroundColor: getLanguageColor(source.locale) }}
                  >
                    {getLanguageLabel(source.locale)}
                  </span>
                  <svg
                    className="w-3 h-3 text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)] group-hover/source:text-[var(--color-accent)] dark:group-hover/source:text-[var(--color-accent)] transition-all duration-200 group-hover/source:translate-x-1"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg"
                  >
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                  </svg>
                </div>
              </Link>
            ))}
          </div>
        </div>
      </div>

      {/* Focus state */}
      <div className="absolute inset-0 rounded-[var(--radius-card)] opacity-0 group-focus-within:opacity-100 ring-3 ring-[var(--color-focus)] dark:ring-[var(--color-focus-dark)] pointer-events-none transition-opacity duration-200" />
    </article>
  );
}