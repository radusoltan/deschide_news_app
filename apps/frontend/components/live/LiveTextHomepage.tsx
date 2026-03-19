import { getLiveTexts } from '@/lib/api/livetext';
import LiveTextHomepageSection from './LiveTextHomepageSection';

interface LiveTextHomepageProps {
  locale: string;
  className?: string;
}

/**
 * Server component wrapper for LiveText homepage section
 * Fetches only live LiveTexts and renders the client component
 */
export async function LiveTextHomepage({ locale, className }: LiveTextHomepageProps) {
  let liveTexts: any[] = [];

  try {
    const { items } = await getLiveTexts(
      {
        status: 'live',
        orderBy: 'startTime',
        orderDirection: 'DESC',
        itemsPerPage: 6, // Limit to 6 for homepage
      },
      {
        locale,
        revalidate: 30, // Revalidate every 30 seconds for live content
      }
    );
    liveTexts = items;
  } catch (error) {
    console.error('Failed to fetch live LiveTexts:', error);
    return null;
  }

  // Don't render section if no live texts
  if (liveTexts.length === 0) {
    return null;
  }

  return (
    <LiveTextHomepageSection
      liveTexts={liveTexts}
      locale={locale}
      className={className}
    />
  );
}

export default LiveTextHomepage;
