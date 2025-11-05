'use client';

import { useState, useEffect } from 'react';
import { PostEditorForm } from './PostEditorForm';
import { PostsList } from './PostsList';
import { PostPreview } from './PostPreview';
import { useMercureSubscription } from '@/lib/hooks';
import type { LiveText, LiveTextPost, PostCreatedEvent, PostUpdatedEvent, PostDeletedEvent } from '@/lib/types/livetext';

interface PostsEditorClientProps {
  liveText: LiveText;
  locale: string;
}

export function PostsEditorClient({ liveText: initialLiveText, locale }: PostsEditorClientProps) {
  const [liveText, setLiveText] = useState(initialLiveText);
  const [posts, setPosts] = useState<LiveTextPost[]>(initialLiveText.posts || []);
  const [editingPost, setEditingPost] = useState<LiveTextPost | null>(null);
  const [previewContent, setPreviewContent] = useState('');
  const [previewIsKeyPoint, setPreviewIsKeyPoint] = useState(false);

  // Subscribe to Mercure updates
  const { latestEvent, status } = useMercureSubscription(liveText.id, {
    autoReconnect: true,
    debug: process.env.NODE_ENV === 'development',
  });

  // Handle Mercure events
  useEffect(() => {
    if (!latestEvent) return;

    switch (latestEvent.type) {
      case 'post.created':
        handlePostCreated(latestEvent as PostCreatedEvent);
        break;
      case 'post.updated':
        handlePostUpdated(latestEvent as PostUpdatedEvent);
        break;
      case 'post.deleted':
        handlePostDeleted(latestEvent as PostDeletedEvent);
        break;
    }
  }, [latestEvent]);

  const handlePostCreated = (event: PostCreatedEvent) => {
    setPosts((prev) => {
      // Check if post already exists
      if (prev.some((p) => p.id === event.post.id)) {
        return prev;
      }
      // Add new post at the beginning
      return [event.post, ...prev];
    });
  };

  const handlePostUpdated = (event: PostUpdatedEvent) => {
    setPosts((prev) =>
      prev.map((post) =>
        post.id === event.post.id
          ? { ...post, ...event.post }
          : post
      )
    );
  };

  const handlePostDeleted = (event: PostDeletedEvent) => {
    setPosts((prev) => prev.filter((post) => post.id !== event.postId));
  };

  const handlePostSuccess = () => {
    // Reset editing state
    setEditingPost(null);
    setPreviewContent('');
    setPreviewIsKeyPoint(false);

    // Refresh posts list from API
    refreshPosts();
  };

  const handleEdit = (post: LiveTextPost) => {
    setEditingPost(post);
    setPreviewContent(post.contentHtml);
    setPreviewIsKeyPoint(post.isKeyPoint);
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handleDelete = (postId: number) => {
    setPosts((prev) => prev.filter((p) => p.id !== postId));
  };

  const handleCancel = () => {
    setEditingPost(null);
    setPreviewContent('');
    setPreviewIsKeyPoint(false);
  };

  const refreshPosts = async () => {
    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';
      const response = await fetch(`${apiUrl}/api/live_texts/${liveText.id}`, {
        headers: {
          'Accept': 'application/ld+json',
          'Accept-Language': locale,
        },
        credentials: 'include',
        cache: 'no-store',
      });

      if (response.ok) {
        const data = await response.json();
        setPosts(data.posts || []);
      }
    } catch (error) {
      console.error('Error refreshing posts:', error);
    }
  };

  const texts = {
    ro: {
      title: 'Editare Postări',
      subtitle: 'Gestionați postările pentru',
      connection: 'Conexiune',
      connecting: 'Conectare...',
      connected: 'Conectat',
      disconnected: 'Deconectat',
      error: 'Eroare',
    },
    en: {
      title: 'Manage Posts',
      subtitle: 'Manage posts for',
      connection: 'Connection',
      connecting: 'Connecting...',
      connected: 'Connected',
      disconnected: 'Disconnected',
      error: 'Error',
    },
    ru: {
      title: 'Управление постами',
      subtitle: 'Управление постами для',
      connection: 'Подключение',
      connecting: 'Подключение...',
      connected: 'Подключено',
      disconnected: 'Отключено',
      error: 'Ошибка',
    },
  };

  const t = texts[locale as keyof typeof texts] || texts.ro;

  const connectionStatusText = {
    connecting: t.connecting,
    connected: t.connected,
    disconnected: t.disconnected,
    error: t.error,
  };

  return (
    <div>
      {/* Header */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
              {t.title}
            </h1>
            <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
              {t.subtitle}: <span className="font-medium">{liveText.title}</span>
            </p>
          </div>

          {/* Connection Status Indicator */}
          <div className="flex items-center gap-2">
            <div
              className={`w-2 h-2 rounded-full ${
                status === 'connected'
                  ? 'bg-green-500'
                  : status === 'connecting'
                    ? 'bg-yellow-500 animate-pulse'
                    : 'bg-red-500'
              }`}
            />
            <span className="text-xs text-gray-600 dark:text-gray-400">
              {connectionStatusText[status]}
            </span>
          </div>
        </div>
      </div>

      {/* Split View: Editor + Preview (Desktop) / Stacked (Mobile) */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {/* Editor Form */}
        <div>
          <PostEditorForm
            liveTextId={liveText.id}
            locale={locale}
            editingPost={editingPost}
            onSuccess={handlePostSuccess}
            onCancel={handleCancel}
            onContentChange={setPreviewContent}
            onKeyPointChange={setPreviewIsKeyPoint}
          />
        </div>

        {/* Preview */}
        <div className="lg:sticky lg:top-4 lg:self-start">
          <PostPreview
            content={previewContent}
            isKeyPoint={previewIsKeyPoint}
            locale={locale}
          />
        </div>
      </div>

      {/* Posts List */}
      <div className="mt-8">
        <PostsList
          posts={posts}
          locale={locale}
          onEdit={handleEdit}
          onDelete={handleDelete}
          onRefresh={refreshPosts}
        />
      </div>
    </div>
  );
}
