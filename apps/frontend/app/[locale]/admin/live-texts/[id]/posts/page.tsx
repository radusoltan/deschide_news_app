import { notFound } from 'next/navigation';
import { getLiveTextById } from '@/lib/api';
import { PostsEditorClient } from './components/PostsEditorClient';

interface LiveTextPostsPageProps {
  params: Promise<{
    locale: string;
    id: string;
  }>;
}

export default async function LiveTextPostsPage({ params }: LiveTextPostsPageProps) {
  const { locale, id } = await params;

  // Fetch LiveText data
  let liveText: any = null;
  try {
    liveText = await getLiveTextById(parseInt(id, 10), { locale, cache: 'no-store' });
  } catch (err) {
    console.error('Failed to fetch live text:', err);
    notFound();
  }

  if (!liveText) {
    notFound();
  }

  return <PostsEditorClient liveText={liveText} locale={locale} />;
}

export async function generateMetadata({ params }: LiveTextPostsPageProps) {
  const { id } = await params;

  return {
    title: `Manage Posts - Live Text #${id}`,
    description: 'Manage posts for live text event',
  };
}
