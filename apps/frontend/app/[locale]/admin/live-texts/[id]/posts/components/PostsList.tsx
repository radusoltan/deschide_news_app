'use client';

import { useState } from 'react';
import type { LiveTextPost } from '@/lib/types/livetext';

interface PostsListProps {
  posts: LiveTextPost[];
  locale: string;
  onEdit: (post: LiveTextPost) => void;
  onDelete: (postId: number) => void;
  onRefresh: () => void;
}

export function PostsList({ posts, locale, onEdit, onDelete, onRefresh }: PostsListProps) {
  const [deletingId, setDeletingId] = useState<number | null>(null);

  const texts = {
    ro: {
      posts: 'Postări',
      noPosts: 'Nu există postări încă',
      keyPoint: 'Moment important',
      edit: 'Editează',
      delete: 'Șterge',
      confirmDelete: 'Sigur doriți să ștergeți această postare?',
      deleteError: 'Eroare la ștergere postare',
    },
    en: {
      posts: 'Posts',
      noPosts: 'No posts yet',
      keyPoint: 'Key Point',
      edit: 'Edit',
      delete: 'Delete',
      confirmDelete: 'Are you sure you want to delete this post?',
      deleteError: 'Error deleting post',
    },
    ru: {
      posts: 'Посты',
      noPosts: 'Постов пока нет',
      keyPoint: 'Ключевой момент',
      edit: 'Редактировать',
      delete: 'Удалить',
      confirmDelete: 'Вы уверены, что хотите удалить этот пост?',
      deleteError: 'Ошибка при удалении поста',
    },
  };

  const t = texts[locale as keyof typeof texts] || texts.ro;

  const handleDelete = async (postId: number) => {
    if (!confirm(t.confirmDelete)) {
      return;
    }

    setDeletingId(postId);

    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? '';
      const response = await fetch(`${apiUrl}/api/live_text_posts/${postId}`, {
        method: 'DELETE',
        credentials: 'include',
      });

      if (!response.ok) {
        throw new Error('Failed to delete');
      }

      onDelete(postId);
      onRefresh();
    } catch (error) {
      console.error('Error deleting post:', error);
      alert(t.deleteError);
    } finally {
      setDeletingId(null);
    }
  };

  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat(locale, {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(date);
  };

  // Strip HTML tags for preview
  const stripHtml = (html: string) => {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
  };

  if (posts.length === 0) {
    return (
      <div className="bg-surface dark:bg-surface-dark rounded-lg border border-gray-200 dark:border-gray-700 p-8 text-center">
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
        <p className="text-gray-600 dark:text-gray-400">{t.noPosts}</p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <h3 className="text-lg font-semibold text-primary dark:text-primary-dark mb-4">
        {t.posts} ({posts.length})
      </h3>

      <div className="space-y-3">
        {posts.map((post) => (
          <div
            key={post.id}
            className={`bg-surface dark:bg-surface-dark rounded-lg border ${
              post.isKeyPoint
                ? 'border-red-300 dark:border-red-800 bg-red-50 dark:bg-red-900/10'
                : 'border-gray-200 dark:border-gray-700'
            } p-4 hover:shadow-md transition-shadow`}
          >
            {/* Header */}
            <div className="flex items-center justify-between mb-3">
              <div className="flex items-center gap-3">
                {/* Key Point Badge */}
                {post.isKeyPoint && (
                  <span className="px-2 py-1 text-xs font-bold rounded bg-red-600 text-white">
                    {t.keyPoint}
                  </span>
                )}

                {/* Author */}
                <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                    />
                  </svg>
                  <span>{post.author?.username || 'Unknown'}</span>
                </div>

                {/* Timestamp */}
                <div className="text-sm text-secondary dark:text-gray-400">
                  {formatDate(post.publishedAt)}
                </div>
              </div>

              {/* Actions */}
              <div className="flex items-center gap-2">
                {/* Edit Button */}
                <button
                  onClick={() => onEdit(post)}
                  className="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded transition-colors"
                  title={t.edit}
                >
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                    />
                  </svg>
                </button>

                {/* Delete Button */}
                <button
                  onClick={() => handleDelete(post.id)}
                  disabled={deletingId === post.id}
                  className="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors disabled:opacity-50"
                  title={t.delete}
                >
                  {deletingId === post.id ? (
                    <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                      <path
                        className="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                      />
                    </svg>
                  ) : (
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                      />
                    </svg>
                  )}
                </button>
              </div>
            </div>

            {/* Content Preview */}
            <div className="text-primary dark:text-primary-dark line-clamp-3">
              {stripHtml(post.contentHtml)}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
