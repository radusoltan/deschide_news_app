'use client';

import { useState, useEffect } from 'react';

interface LiveTextTimelineProps {
  liveTextId: number;
  keyPoints: any[];
  locale: string;
  onJumpToPost?: (postId: number) => void;
}

export function LiveTextTimeline({ liveTextId, keyPoints, locale, onJumpToPost }: LiveTextTimelineProps) {
  const texts = {
    ro: {
      title: 'Momente Cheie',
      empty: 'Nu există momente cheie încă',
      now: 'Acum',
    },
    en: {
      title: 'Key Points',
      empty: 'No key points yet',
      now: 'Now',
    },
    ru: {
      title: 'Ключевые моменты',
      empty: 'Ключевых моментов пока нет',
      now: 'Сейчас',
    },
  };

  const t = texts[locale as keyof typeof texts] || texts.ro;

  const formatTime = (dateString: string) => {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat(locale, {
      hour: '2-digit',
      minute: '2-digit',
    }).format(date);
  };

  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat(locale, {
      day: '2-digit',
      month: 'short',
    }).format(date);
  };

  const stripHtml = (html: string) => {
    if (typeof document === 'undefined') return html;
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
  };

  if (keyPoints.length === 0) {
    return (
      <div className="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          {t.title}
        </h3>
        <div className="text-center py-8">
          <svg
            className="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
          <p className="text-sm text-gray-500 dark:text-gray-400">{t.empty}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
      <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-6">
        {t.title}
      </h3>

      {/* Timeline */}
      <div className="relative">
        {/* Vertical line */}
        <div className="absolute left-3 top-0 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-700" />

        {/* Timeline items */}
        <div className="space-y-4">
          {keyPoints.map((post, index) => (
            <div
              key={post.id}
              className="relative pl-8 cursor-pointer group hover:bg-gray-50 dark:hover:bg-gray-700/50 -ml-2 p-2 rounded-lg transition-colors"
              onClick={() => onJumpToPost && onJumpToPost(post.id)}
            >
              {/* Dot */}
              <div className="absolute left-2 top-3 w-3 h-3 rounded-full bg-red-600 ring-4 ring-white dark:ring-gray-800 group-hover:ring-gray-50 dark:group-hover:ring-gray-700/50 transition-all" />

              {/* Content */}
              <div>
                {/* Time */}
                <div className="flex items-center gap-2 mb-1">
                  <span className="text-xs font-medium text-red-600 dark:text-red-400">
                    {formatTime(post.publishedAt)}
                  </span>
                  <span className="text-xs text-gray-400 dark:text-gray-500">
                    {formatDate(post.publishedAt)}
                  </span>
                </div>

                {/* Preview text */}
                <p className="text-sm text-gray-700 dark:text-gray-300 line-clamp-2">
                  {stripHtml(post.contentHtml || post.content)}
                </p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
