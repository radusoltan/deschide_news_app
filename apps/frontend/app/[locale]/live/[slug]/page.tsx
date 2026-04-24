import { notFound } from 'next/navigation';
import { getLiveTextBySlug, getLiveTextKeyPoints } from '@/lib/api/livetext';
import { LiveTextViewer } from './components/LiveTextViewer';
import type { LiveText, LiveTextPost } from '@/lib/types/livetext';

interface LiveTextPageProps {
  params: Promise<{
    locale: string;
    slug: string;
  }>;
}

export default async function LiveTextPage({ params }: LiveTextPageProps) {
  const { locale, slug } = await params;

  // Fetch LiveText data
  let liveText: LiveText | null = null;
  try {
    liveText = await getLiveTextBySlug(slug, { locale, cache: 'no-store' });
  } catch (err) {
    console.error('Failed to fetch live text:', err);
    notFound();
  }

  if (!liveText) {
    notFound();
  }

  // Fetch key points
  let keyPoints: LiveTextPost[] = [];
  try {
    keyPoints = await getLiveTextKeyPoints(liveText.id, { locale, cache: 'no-store' });
  } catch (err) {
    console.error('Failed to fetch key points:', err);
    // Continue without key points
  }

  return <LiveTextViewer liveText={liveText} keyPoints={keyPoints} locale={locale} />;
}

export async function generateMetadata({ params }: LiveTextPageProps) {
  const { locale, slug } = await params;

  try {
    const liveText = await getLiveTextBySlug(slug, { locale, cache: 'no-store' });

    if (!liveText) {
      return {
        title: 'Live Text Not Found',
      };
    }

    return {
      title: liveText.title,
      description: liveText.description || `Live coverage: ${liveText.title}`,
    };
  } catch (err) {
    return {
      title: 'Live Text',
    };
  }
}
