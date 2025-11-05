'use client';

import { useState, useEffect, useRef } from 'react';
import { LiveTextTimeline } from './LiveTextTimeline';
import { ReactionButtons } from './ReactionButtons';
import type { LiveText, LiveTextPost } from '@/lib/types/livetext';

interface LiveTextViewerProps {
  liveText: LiveText;
  keyPoints: any[];
  locale: string;
}

export function LiveTextViewer({ liveText: initialLiveText, keyPoints: initialKeyPoints, locale }: LiveTextViewerProps) {
  const [showKeyPointsOnly, setShowKeyPointsOnly] = useState(false);
  const [posts, setPosts] = useState<LiveTextPost[]>(initialLiveText.posts || []);
  const [keyPoints, setKeyPoints] = useState(initialKeyPoints);
  const postRefs = useRef<Map<number, HTMLDivElement>>(new Map());

  // Extract template configuration
  const template = initialLiveText.template;
  const colors = template?.config?.colors || {
    primary: '#ef4444',
    secondary: '#dc2626',
    accent: '#b91c1c',
    background: '#fef2f2',
    text: '#7f1d1d',
  };

  const texts = {
    ro: {
      allPosts: 'Toate Postările',
      keyPointsOnly: 'Doar Momente Cheie',
      live: 'LIVE',
      ended: 'ÎNCHEIAT',
      paused: 'PAUZĂ',
      keyPoint: 'Moment Cheie',
      author: 'Autor',
      noPosts: 'Nu există postări încă',
      noKeyPoints: 'Nu există momente cheie',
    },
    en: {
      allPosts: 'All Posts',
      keyPointsOnly: 'Key Points Only',
      live: 'LIVE',
      ended: 'ENDED',
      paused: 'PAUSED',
      keyPoint: 'Key Point',
      author: 'Author',
      noPosts: 'No posts yet',
      noKeyPoints: 'No key points yet',
    },
    ru: {
      allPosts: 'Все посты',
      keyPointsOnly: 'Только ключевые моменты',
      live: 'В ЭФИРЕ',
      ended: 'ЗАВЕРШЕНО',
      paused: 'ПАУЗА',
      keyPoint: 'Ключевой момент',
      author: 'Автор',
      noPosts: 'Постов пока нет',
      noKeyPoints: 'Ключевых моментов нет',
    },
  };

  const t = texts[locale as keyof typeof texts] || texts.ro;

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

  const getStatusBadge = () => {
    const statusText = {
      live: t.live,
      paused: t.paused,
      ended: t.ended,
      draft: 'DRAFT',
    };

    const text = statusText[initialLiveText.status] || 'DRAFT';

    // Use template colors for live status
    if (initialLiveText.status === 'live') {
      return (
        <span
          className="px-3 py-1 text-xs font-bold rounded text-white animate-pulse"
          style={{ backgroundColor: colors.primary }}
        >
          {text}
        </span>
      );
    }

    // Default colors for other statuses
    const defaultColors = {
      paused: 'bg-yellow-600 text-white',
      ended: 'bg-gray-600 text-white',
      draft: 'bg-gray-400 text-white',
    };

    const color = defaultColors[initialLiveText.status as keyof typeof defaultColors] || 'bg-gray-400 text-white';

    return (
      <span className={`px-3 py-1 text-xs font-bold rounded ${color}`}>
        {text}
      </span>
    );
  };

  const filteredPosts = showKeyPointsOnly
    ? posts.filter(post => post.isKeyPoint)
    : posts;

  const handleJumpToPost = (postId: number) => {
    const element = postRefs.current.get(postId);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth', block: 'center' });
      // Add highlight with template color
      element.style.boxShadow = `0 0 0 2px ${colors.primary}`;
      setTimeout(() => {
        element.style.boxShadow = '';
      }, 2000);
    }
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex items-center gap-3 mb-4">
          {getStatusBadge()}
          {initialLiveText.category && (
            <span className="text-sm text-gray-500 dark:text-gray-400">
              {initialLiveText.category.title}
            </span>
          )}
        </div>

        <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-3">
          {initialLiveText.title}
        </h1>

        {initialLiveText.description && (
          <p className="text-lg text-gray-600 dark:text-gray-300">
            {initialLiveText.description}
          </p>
        )}
      </div>

      {/* Toggle View */}
      <div className="mb-6 flex justify-center">
        <div className="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-800 p-1">
          <button
            onClick={() => setShowKeyPointsOnly(false)}
            className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
              !showKeyPointsOnly
                ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
            }`}
          >
            {t.allPosts}
          </button>
          <button
            onClick={() => setShowKeyPointsOnly(true)}
            className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
              showKeyPointsOnly
                ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
            }`}
          >
            {t.keyPointsOnly}
          </button>
        </div>
      </div>

      {/* Main Content - Split View on Desktop */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Posts - 2/3 width on desktop */}
        <div className="lg:col-span-2">
          {filteredPosts.length === 0 ? (
            <div className="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-12 text-center">
              <svg
                className="w-16 h-16 mx-auto text-gray-400 dark:text-gray-600 mb-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
              </svg>
              <p className="text-gray-600 dark:text-gray-400">
                {showKeyPointsOnly ? t.noKeyPoints : t.noPosts}
              </p>
            </div>
          ) : (
            <div className="space-y-4">
              {filteredPosts.map((post) => (
                <div
                  key={post.id}
                  ref={(el) => {
                    if (el) postRefs.current.set(post.id, el);
                  }}
                  className="bg-white dark:bg-gray-800 rounded-lg border-l-4 transition-all p-6 shadow-sm hover:shadow-md"
                  style={{
                    borderLeftColor: post.isKeyPoint ? colors.primary : undefined,
                    backgroundColor: post.isKeyPoint
                      ? `${colors.background}`
                      : undefined,
                  }}
                >
                  {/* Header */}
                  <div className="flex items-center justify-between mb-3">
                    <div className="flex items-center gap-3">
                      {post.isKeyPoint && (
                        <span
                          className="px-2 py-1 text-xs font-bold rounded text-white"
                          style={{ backgroundColor: colors.primary }}
                        >
                          {t.keyPoint}
                        </span>
                      )}
                      <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                          />
                        </svg>
                        <span>{post.author?.username || t.author}</span>
                      </div>
                    </div>
                    <span className="text-sm text-gray-500 dark:text-gray-400">
                      {formatTimestamp(post.publishedAt)}
                    </span>
                  </div>

                  {/* Content */}
                  <div
                    className="prose dark:prose-invert max-w-none mb-4"
                    dangerouslySetInnerHTML={{ __html: post.contentHtml || post.content }}
                  />

                  {/* Reactions */}
                  <ReactionButtons
                    postId={post.id}
                    locale={locale}
                  />
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Timeline Sidebar - 1/3 width on desktop, sticky */}
        <div className="lg:col-span-1">
          <div className="lg:sticky lg:top-4">
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
  );
}
