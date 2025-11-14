'use client';

import { useState, useEffect } from 'react';
import { RichTextEditor } from './RichTextEditor';
import type { LiveTextPost } from '@/lib/types/livetext';

interface PostEditorFormProps {
  liveTextId: number;
  locale: string;
  editingPost?: LiveTextPost | null;
  onSuccess: () => void;
  onCancel: () => void;
  onContentChange?: (content: string) => void;
  onKeyPointChange?: (isKeyPoint: boolean) => void;
}

export function PostEditorForm({
  liveTextId,
  locale,
  editingPost,
  onSuccess,
  onCancel,
  onContentChange,
  onKeyPointChange
}: PostEditorFormProps) {
  const [content, setContent] = useState('');
  const [isKeyPoint, setIsKeyPoint] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Load editing post data
  useEffect(() => {
    if (editingPost) {
      setContent(editingPost.contentHtml);
      setIsKeyPoint(editingPost.isKeyPoint);
    } else {
      setContent('');
      setIsKeyPoint(false);
    }
  }, [editingPost]);

  // Emit content changes for preview
  useEffect(() => {
    if (onContentChange) {
      onContentChange(content);
    }
  }, [content, onContentChange]);

  // Emit isKeyPoint changes for preview
  useEffect(() => {
    if (onKeyPointChange) {
      onKeyPointChange(isKeyPoint);
    }
  }, [isKeyPoint, onKeyPointChange]);

  const texts = {
    ro: {
      newPost: 'Postare nouă',
      editPost: 'Editare postare',
      content: 'Conținut',
      keyPoint: 'Moment important',
      keyPointDesc: 'Marchează această postare ca moment important',
      publish: 'Publicare',
      update: 'Actualizare',
      cancel: 'Anulare',
      publishing: 'Se publică...',
      updating: 'Se actualizează...',
      contentRequired: 'Conținutul este obligatoriu',
    },
    en: {
      newPost: 'New Post',
      editPost: 'Edit Post',
      content: 'Content',
      keyPoint: 'Key Point',
      keyPointDesc: 'Mark this post as a key point',
      publish: 'Publish',
      update: 'Update',
      cancel: 'Cancel',
      publishing: 'Publishing...',
      updating: 'Updating...',
      contentRequired: 'Content is required',
    },
    ru: {
      newPost: 'Новый пост',
      editPost: 'Редактировать пост',
      content: 'Содержание',
      keyPoint: 'Ключевой момент',
      keyPointDesc: 'Отметить этот пост как ключевой момент',
      publish: 'Опубликовать',
      update: 'Обновить',
      cancel: 'Отмена',
      publishing: 'Публикация...',
      updating: 'Обновление...',
      contentRequired: 'Содержание обязательно',
    },
  };

  const t = texts[locale as keyof typeof texts] || texts.ro;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    // Validation
    const strippedContent = content.replace(/<[^>]*>/g, '').trim();
    if (!strippedContent) {
      setError(t.contentRequired);
      return;
    }

    setIsSubmitting(true);

    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';
      const url = editingPost
        ? `${apiUrl}/api/live_text_posts/${editingPost.id}`
        : `${apiUrl}/api/live_text_posts`;

      const method = editingPost ? 'PUT' : 'POST';

      const payload: any = {
        contentHtml: content,
        content: strippedContent,
        isKeyPoint,
        publishedAt: new Date().toISOString(),
      };

      // For new posts, add liveText reference
      if (!editingPost) {
        payload.liveText = `/api/live_texts/${liveTextId}`;
      }

      const response = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/ld+json',
          'Accept': 'application/ld+json',
          'Accept-Language': locale,
        },
        credentials: 'include',
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.detail || errorData.message || 'Failed to save');
      }

      // Reset form
      setContent('');
      setIsKeyPoint(false);

      // Notify success
      onSuccess();
    } catch (err) {
      console.error('Error saving post:', err);
      setError(err instanceof Error ? err.message : 'Failed to save post');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
      <form onSubmit={handleSubmit}>
        {/* Header */}
        <div className="mb-6">
          <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
            {editingPost ? t.editPost : t.newPost}
          </h2>
        </div>

        {/* Error Message */}
        {error && (
          <div className="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <p className="text-sm text-red-600 dark:text-red-400">
              <strong>Error:</strong> {error}
            </p>
          </div>
        )}

        {/* Content Editor */}
        <div className="mb-6">
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {t.content} <span className="text-red-500">*</span>
          </label>
          <RichTextEditor
            content={content}
            onChange={setContent}
            placeholder="Type your post content here..."
          />
        </div>

        {/* Key Point Toggle */}
        <div className="mb-6">
          <label className="flex items-center gap-3 cursor-pointer">
            <input
              type="checkbox"
              checked={isKeyPoint}
              onChange={(e) => setIsKeyPoint(e.target.checked)}
              className="w-5 h-5 text-red-600 border-gray-300 rounded focus:ring-red-500"
            />
            <div>
              <div className="text-sm font-medium text-gray-700 dark:text-gray-300">
                {t.keyPoint}
              </div>
              <div className="text-xs text-gray-500 dark:text-gray-400">
                {t.keyPointDesc}
              </div>
            </div>
          </label>
        </div>

        {/* Action Buttons */}
        <div className="flex items-center justify-end gap-4">
          <button
            type="button"
            onClick={onCancel}
            disabled={isSubmitting}
            className="px-6 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50"
          >
            {t.cancel}
          </button>
          <button
            type="submit"
            disabled={isSubmitting}
            className="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors disabled:opacity-50 flex items-center gap-2"
          >
            {isSubmitting && (
              <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                <path
                  className="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                />
              </svg>
            )}
            {isSubmitting
              ? (editingPost ? t.updating : t.publishing)
              : (editingPost ? t.update : t.publish)
            }
          </button>
        </div>
      </form>
    </div>
  );
}
