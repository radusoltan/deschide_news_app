'use client';

import { useRouter } from 'next/navigation';
import { useArticleUpdates } from '@/lib/hooks/useArticleUpdates';

/**
 * Invisible client component that subscribes to Mercure SSE.
 * When a published article event arrives, triggers router.refresh()
 * which seamlessly re-renders all server components with fresh data.
 */
export default function LiveRefresh() {
  const router = useRouter();

  useArticleUpdates((event) => {
    if (event.status === 'published') {
      router.refresh();
    }
  });

  return null;
}
