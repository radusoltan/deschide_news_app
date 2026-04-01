/**
 * Embed Page for LiveText
 *
 * Optimized page for embedding LiveText in external sites via iframe
 *
 * Features:
 * - Minimal layout (no header/footer)
 * - Theme support (light/dark)
 * - Auto-refresh via Mercure
 * - Responsive design
 * - Optimized for performance
 */

import { Suspense } from 'react';
import { notFound } from 'next/navigation';
import { EmbedLiveTextViewer } from './EmbedLiveTextViewer';
import type { Metadata } from 'next';

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

interface PageProps {
  params: {
    locale: string;
    slug: string;
  };
  searchParams: {
    theme?: 'light' | 'dark';
  };
}

/**
 * Fetch LiveText data for embedding
 */
async function getLiveTextEmbed(slug: string, locale: string) {
  try {
    const response = await fetch(`${API_URL}/api/embed/live-text/slug/${slug}?locale=${locale}`, {
      next: { revalidate: 10 }, // Revalidate every 10 seconds
    });

    if (!response.ok) {
      return null;
    }

    return response.json();
  } catch (error) {
    console.error('Failed to fetch LiveText embed data:', error);
    return null;
  }
}

/**
 * Generate metadata for embed page (minimal)
 */
export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const liveText = await getLiveTextEmbed(params.slug, params.locale);

  if (!liveText) {
    return {
      title: 'LiveText Not Found',
    };
  }

  return {
    title: liveText.title,
    description: liveText.description,
    robots: {
      index: false, // Don't index embed pages
      follow: false,
    },
  };
}

/**
 * Embed Page Component
 */
export default async function EmbedLiveTextPage({ params, searchParams }: PageProps) {
  const liveText = await getLiveTextEmbed(params.slug, params.locale);

  if (!liveText) {
    notFound();
  }

  const theme = searchParams.theme || 'light';

  return (
    <html lang={params.locale} className={theme}>
      <head>
        {/* Minimal head - no tracking, no external scripts */}
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <style>{`
          * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
          }
          body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
          }
          .light {
            --bg-primary: #ffffff;
            --bg-secondary: #f3f4f6;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --accent-color: #3b82f6;
          }
          .dark {
            --bg-primary: #1f2937;
            --bg-secondary: #111827;
            --text-primary: #f9fafb;
            --text-secondary: #9ca3af;
            --border-color: #374151;
            --accent-color: #60a5fa;
          }
          body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
          }
        `}</style>
      </head>
      <body>
        <Suspense fallback={<div style={{ padding: '20px' }}>Loading...</div>}>
          <EmbedLiveTextViewer liveText={liveText} theme={theme} />
        </Suspense>

        {/* Branding footer */}
        <div style={{
          padding: '12px 20px',
          borderTop: '1px solid var(--border-color)',
          backgroundColor: 'var(--bg-secondary)',
          textAlign: 'center',
          fontSize: '12px',
          color: 'var(--text-secondary)'
        }}>
          <a
            href={liveText.embedInfo.sourceUrl}
            target="_blank"
            rel="noopener noreferrer"
            style={{
              color: 'var(--accent-color)',
              textDecoration: 'none'
            }}
          >
            View full coverage on Deschide News →
          </a>
        </div>
      </body>
    </html>
  );
}
