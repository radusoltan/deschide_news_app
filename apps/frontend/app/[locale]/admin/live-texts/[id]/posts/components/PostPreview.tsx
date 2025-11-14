'use client';

interface PostPreviewProps {
  content: string;
  isKeyPoint: boolean;
  locale: string;
}

export function PostPreview({ content, isKeyPoint, locale }: PostPreviewProps) {
  const texts = {
    ro: {
      preview: 'Preview',
      keyPoint: 'Moment important',
      empty: 'Începeți să scrieți pentru a vedea preview-ul...',
      now: 'Acum',
    },
    en: {
      preview: 'Preview',
      keyPoint: 'Key Point',
      empty: 'Start typing to see preview...',
      now: 'Now',
    },
    ru: {
      preview: 'Предпросмотр',
      keyPoint: 'Ключевой момент',
      empty: 'Начните печатать, чтобы увидеть предварительный просмотр...',
      now: 'Сейчас',
    },
  };

  const t = texts[locale as keyof typeof texts] || texts.ro;

  // Strip HTML to check if empty
  const stripHtml = (html: string) => {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
  };

  const isEmpty = !stripHtml(content).trim();

  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
      {/* Header */}
      <div className="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 px-4 py-3">
        <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
          {t.preview}
        </h3>
      </div>

      {/* Preview Content */}
      <div className="p-6">
        {isEmpty ? (
          <div className="text-center py-12">
            <svg
              className="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
              />
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
              />
            </svg>
            <p className="text-gray-400 dark:text-gray-500">{t.empty}</p>
          </div>
        ) : (
          <div
            className={`rounded-lg p-4 border-l-4 ${
              isKeyPoint
                ? 'border-red-600 bg-red-50 dark:bg-red-900/10'
                : 'border-gray-300 dark:border-gray-700'
            }`}
          >
            {/* Post Header */}
            <div className="flex items-center justify-between mb-3">
              <div className="flex items-center gap-3">
                {/* Key Point Badge */}
                {isKeyPoint && (
                  <span className="px-2 py-1 text-xs font-bold rounded bg-red-600 text-white">
                    {t.keyPoint}
                  </span>
                )}

                {/* Mock Author */}
                <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                    />
                  </svg>
                  <span>You</span>
                </div>

                {/* Mock Timestamp */}
                <div className="text-sm text-gray-500 dark:text-gray-400">
                  {t.now}
                </div>
              </div>
            </div>

            {/* Post Content */}
            <div
              className="prose dark:prose-invert max-w-none"
              dangerouslySetInnerHTML={{ __html: content }}
            />
          </div>
        )}
      </div>
    </div>
  );
}
