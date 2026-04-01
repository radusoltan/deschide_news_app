import { Suspense } from 'react';
import { notFound } from 'next/navigation';
import { ArrowLeft } from 'lucide-react';
import Link from 'next/link';
import { getLiveTextById } from '@/lib/api/livetext';
import { LiveTextAnalyticsDashboard } from './components/LiveTextAnalyticsDashboard';
import { LiveTextAnalyticsSkeleton } from './components/LiveTextAnalyticsSkeleton';

interface PageProps {
  params: {
    locale: string;
    id: string;
  };
}

export default async function LiveTextAnalyticsPage({ params }: PageProps) {
  const liveTextId = parseInt(params.id);

  if (isNaN(liveTextId)) {
    return notFound();
  }

  // Fetch LiveText details
  let liveText;
  try {
    liveText = await getLiveTextById(liveTextId, { locale: params.locale });
  } catch (error) {
    return notFound();
  }

  if (!liveText) {
    return notFound();
  }

  return (
    <div className="container mx-auto px-4 py-8">
      {/* Header */}
      <div className="mb-8">
        <Link
          href={`/${params.locale}/admin/live-texts`}
          className="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-primary dark:text-gray-400 dark:hover:text-gray-100 mb-4"
        >
          <ArrowLeft className="h-4 w-4" />
          Back to LiveTexts
        </Link>

        <div className="flex items-start justify-between">
          <div>
            <h1 className="text-3xl font-bold text-primary dark:text-gray-100">
              Analytics
            </h1>
            <p className="mt-2 text-lg text-gray-600 dark:text-gray-400">
              {liveText.title}
            </p>
            <div className="mt-1 flex items-center gap-4 text-sm text-secondary">
              <span>ID: {liveText.id}</span>
              <span>•</span>
              <span>Status: {liveText.status.toUpperCase()}</span>
              {liveText.startTime && (
                <>
                  <span>•</span>
                  <span>
                    Started: {new Date(liveText.startTime).toLocaleDateString()}
                  </span>
                </>
              )}
            </div>
          </div>

          <Link
            href={`/${params.locale}/live/${liveText.slug}`}
            target="_blank"
            className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            View Live
          </Link>
        </div>
      </div>

      {/* Analytics Dashboard */}
      <Suspense fallback={<LiveTextAnalyticsSkeleton />}>
        <LiveTextAnalyticsDashboard liveTextId={liveTextId} />
      </Suspense>
    </div>
  );
}
