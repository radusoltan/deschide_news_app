/**
 * Dynamic Open Graph Image Generation
 *
 * Generates OG images on-the-fly using @vercel/og
 * Usage: /api/og?title=Article+Title&category=Politics
 */

import { ImageResponse } from 'next/og';
import { NextRequest } from 'next/server';

export const runtime = 'edge';

const SITE_NAME = process.env.NEXT_PUBLIC_APP_NAME || 'Deschide News';

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url);

    // Get parameters
    const title = searchParams.get('title') || 'Deschide News';
    const category = searchParams.get('category');
    const author = searchParams.get('author');
    const date = searchParams.get('date');
    const locale = (searchParams.get('locale') || 'ro') as 'ro' | 'en' | 'ru';

    // Locale-specific labels
    const labels = {
      ro: { by: 'de', on: 'pe' },
      en: { by: 'by', on: 'on' },
      ru: { by: 'от', on: '' },
    };

    const label = labels[locale];

    // Generate image
    return new ImageResponse(
      (
        <div
          style={{
            display: 'flex',
            flexDirection: 'column',
            width: '100%',
            height: '100%',
            background: 'linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #60a5fa 100%)',
            padding: '80px',
            fontFamily: 'system-ui, sans-serif',
          }}
        >
          {/* Content Container */}
          <div
            style={{
              display: 'flex',
              flexDirection: 'column',
              justifyContent: 'space-between',
              height: '100%',
            }}
          >
            {/* Top Section - Category & Title */}
            <div style={{ display: 'flex', flexDirection: 'column' }}>
              {category && (
                <div
                  style={{
                    display: 'flex',
                    fontSize: 32,
                    color: 'rgba(255, 255, 255, 0.9)',
                    marginBottom: 24,
                    textTransform: 'uppercase',
                    letterSpacing: '2px',
                    fontWeight: 600,
                  }}
                >
                  {category}
                </div>
              )}
              <div
                style={{
                  display: 'flex',
                  fontSize: 64,
                  fontWeight: 'bold',
                  color: 'white',
                  lineHeight: 1.2,
                  marginBottom: 32,
                }}
              >
                {title}
              </div>
            </div>

            {/* Bottom Section - Metadata & Branding */}
            <div
              style={{
                display: 'flex',
                flexDirection: 'column',
                gap: 24,
              }}
            >
              {/* Author & Date */}
              {(author || date) && (
                <div
                  style={{
                    display: 'flex',
                    fontSize: 28,
                    color: 'rgba(255, 255, 255, 0.9)',
                    gap: 16,
                  }}
                >
                  {author && (
                    <span>
                      {label.by} {author}
                    </span>
                  )}
                  {author && date && <span>•</span>}
                  {date && (
                    <span>
                      {label.on} {date}
                    </span>
                  )}
                </div>
              )}

              {/* Site Name */}
              <div
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                }}
              >
                <div
                  style={{
                    display: 'flex',
                    fontSize: 48,
                    fontWeight: 'bold',
                    color: 'white',
                  }}
                >
                  {SITE_NAME}
                </div>
                <div
                  style={{
                    display: 'flex',
                    fontSize: 28,
                    color: 'rgba(255, 255, 255, 0.8)',
                  }}
                >
                  {locale === 'ro' && 'Știri din Moldova'}
                  {locale === 'en' && 'News from Moldova'}
                  {locale === 'ru' && 'Новости из Молдовы'}
                </div>
              </div>
            </div>
          </div>
        </div>
      ),
      {
        width: 1200,
        height: 630,
      }
    );
  } catch (error) {
    console.error('Error generating OG image:', error);

    // Return fallback image on error
    return new ImageResponse(
      (
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            width: '100%',
            height: '100%',
            background: '#1e3a8a',
            color: 'white',
            fontSize: 64,
            fontWeight: 'bold',
          }}
        >
          {SITE_NAME}
        </div>
      ),
      {
        width: 1200,
        height: 630,
      }
    );
  }
}
