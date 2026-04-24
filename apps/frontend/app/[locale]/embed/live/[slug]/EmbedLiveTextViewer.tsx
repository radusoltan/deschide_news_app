'use client';

import { useState, useEffect } from 'react';
import { useMercureSubscription } from '@/lib/hooks/useMercureSubscription';
import { createSafeHtml } from '@/lib/sanitize';

interface LiveTextPost {
  id: number;
  content: string;
  contentHtml: string;
  isKeyPoint: boolean;
  publishedAt: string;
  author: {
    id: number;
    name: string;
  };
}

interface LiveTextEmbed {
  id: number;
  title: string;
  slug: string;
  description?: string;
  status: string;
  startTime?: string;
  endTime?: string;
  locale: string;
  category?: {
    id: number;
    name: string;
  };
  author: {
    id: number;
    name: string;
  };
  posts: LiveTextPost[];
  pagination: {
    limit: number;
    offset: number;
    hasMore: boolean;
  };
  embedInfo: {
    version: string;
    sourceUrl: string;
    mercureTopic: string;
  };
  sportMatch?: {
    id: number;
    sportType: string;
    homeTeam: string;
    awayTeam: string;
    homeScore: number;
    awayScore: number;
    status: string;
    currentMinute?: number;
  };
}

interface Props {
  liveText: LiveTextEmbed;
  theme: 'light' | 'dark';
}

/**
 * EmbedLiveTextViewer Component
 *
 * Minimal viewer for embedded LiveText with real-time updates
 */
export function EmbedLiveTextViewer({ liveText: initialLiveText, theme }: Props) {
  const [liveText, setLiveText] = useState(() => initialLiveText);
  const [posts, setPosts] = useState(() => initialLiveText.posts);

  // Subscribe to Mercure for real-time updates
  const { latestEvent } = useMercureSubscription(liveText.id);

  // Handle Mercure events
  useEffect(() => {
    if (!latestEvent) return;

    const handleEvent = () => {
      switch (latestEvent.type) {
        case 'post.created':
          // Add new post to the top
          setPosts((prev: any) => [latestEvent.post, ...prev]);
          break;

        case 'post.updated':
          // Update existing post
          setPosts((prev: any) =>
            prev.map((p: any) => (p.id === latestEvent.post.id ? { ...p, ...latestEvent.post } : p))
          );
          break;

        case 'post.deleted':
          // Remove deleted post
          setPosts((prev) => prev.filter((p) => p.id !== latestEvent.postId));
          break;

        case 'status.changed':
          // Update LiveText status
          setLiveText((prev) => ({
            ...prev,
            status: latestEvent.status,
          }));
          break;

        case 'sport.score.updated':
          // Update sport match score (sport events use .data property)
          if (liveText.sportMatch) {
            setLiveText((prev) => ({
              ...prev,
              sportMatch: prev.sportMatch ? {
                ...prev.sportMatch,
                homeScore: latestEvent.data.home_score,
                awayScore: latestEvent.data.away_score,
                status: latestEvent.data.status,
                currentMinute: latestEvent.data.current_minute,
              } : undefined,
            }));
          }
          break;
      }
    };

    handleEvent();
  }, [latestEvent, liveText.sportMatch]);

  const getStatusBadge = () => {
    switch (liveText.status) {
      case 'live':
        return (
          <span style={{
            display: 'inline-flex',
            alignItems: 'center',
            gap: '6px',
            padding: '4px 12px',
            backgroundColor: 'var(--color-breaking)',
            color: 'white',
            borderRadius: '9999px',
            fontSize: '12px',
            fontWeight: '600',
            textTransform: 'uppercase',
          }}>
            <span style={{
              width: '6px',
              height: '6px',
              backgroundColor: 'white',
              borderRadius: '50%',
              animation: 'pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
            }}></span>
            LIVE
          </span>
        );
      case 'ended':
        return (
          <span style={{
            padding: '4px 12px',
            backgroundColor: 'var(--bg-secondary)',
            color: 'var(--text-secondary)',
            borderRadius: '9999px',
            fontSize: '12px',
            fontWeight: '600',
            textTransform: 'uppercase',
          }}>
            ENDED
          </span>
        );
      default:
        return null;
    }
  };

  const formatTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleTimeString(liveText.locale, {
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  return (
    <div style={{ padding: '0', minHeight: '400px' }}>
      {/* Header */}
      <div style={{
        padding: '20px',
        borderBottom: '1px solid var(--border-color)',
        backgroundColor: 'var(--bg-secondary)',
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '8px' }}>
          {getStatusBadge()}
          {liveText.category && (
            <span style={{
              padding: '4px 12px',
              backgroundColor: 'var(--accent-color)',
              color: 'white',
              borderRadius: '4px',
              fontSize: '12px',
              fontWeight: '500',
            }}>
              {liveText.category.name}
            </span>
          )}
        </div>
        <h1 style={{
          fontSize: '24px',
          fontWeight: '700',
          marginBottom: '8px',
          lineHeight: '1.3',
        }}>
          {liveText.title}
        </h1>
        {liveText.description && (
          <p style={{
            fontSize: '14px',
            color: 'var(--text-secondary)',
            lineHeight: '1.5',
          }}>
            {liveText.description}
          </p>
        )}
      </div>

      {/* Sport Match Score (if applicable) */}
      {liveText.sportMatch && (
        <div style={{
          padding: '20px',
          backgroundColor: theme === 'dark' ? 'var(--color-surface-sunken-dark)' : 'var(--color-surface-sunken)',
          borderBottom: '1px solid var(--border-color)',
        }}>
          <div style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            gap: '24px',
          }}>
            <div style={{ textAlign: 'center', flex: '1' }}>
              <div style={{ fontSize: '18px', fontWeight: '600' }}>
                {liveText.sportMatch.homeTeam}
              </div>
            </div>
            <div style={{ textAlign: 'center' }}>
              <div style={{ fontSize: '32px', fontWeight: '700' }}>
                {liveText.sportMatch.homeScore} - {liveText.sportMatch.awayScore}
              </div>
              {liveText.sportMatch.status === 'live' && liveText.sportMatch.currentMinute && (
                <div style={{
                  fontSize: '14px',
                  color: 'var(--text-secondary)',
                  marginTop: '4px',
                }}>
                  {liveText.sportMatch.currentMinute}&apos;
                </div>
              )}
            </div>
            <div style={{ textAlign: 'center', flex: '1' }}>
              <div style={{ fontSize: '18px', fontWeight: '600' }}>
                {liveText.sportMatch.awayTeam}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Posts List */}
      <div style={{ padding: '20px' }}>
        {posts.length === 0 ? (
          <div style={{
            textAlign: 'center',
            padding: '40px 20px',
            color: 'var(--text-secondary)',
          }}>
            No updates yet
          </div>
        ) : (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
            {posts.map((post) => (
              <div
                key={post.id}
                style={{
                  padding: '16px',
                  backgroundColor: post.isKeyPoint
                    ? (theme === 'dark' ? 'var(--color-surface-elevated-dark)' : 'var(--color-surface-elevated)')
                    : 'var(--bg-secondary)',
                  borderRadius: '8px',
                  borderLeft: post.isKeyPoint
                    ? '4px solid var(--accent-color)'
                    : 'none',
                }}
              >
                <div style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: '12px',
                  marginBottom: '12px',
                  fontSize: '12px',
                  color: 'var(--text-secondary)',
                }}>
                  <span style={{ fontWeight: '500' }}>{post.author.name}</span>
                  <span>•</span>
                  <span>{formatTime(post.publishedAt)}</span>
                  {post.isKeyPoint && (
                    <>
                      <span>•</span>
                      <span style={{
                        color: 'var(--accent-color)',
                        fontWeight: '600',
                      }}>
                        ⚡ KEY POINT
                      </span>
                    </>
                  )}
                </div>
                <div
                  style={{
                    fontSize: '15px',
                    lineHeight: '1.6',
                  }}
                  dangerouslySetInnerHTML={createSafeHtml(post.contentHtml || post.content)}
                />
              </div>
            ))}
          </div>
        )}
      </div>

      {/* CSS animation */}
      <style>{`
        @keyframes pulse {
          0%, 100% {
            opacity: 1;
          }
          50% {
            opacity: 0.5;
          }
        }
      `}</style>
    </div>
  );
}
