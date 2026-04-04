import TopicsPageClient from './TopicsPageClient';

interface TopicsPageProps {
  params: Promise<{
    locale: string;
  }>;
}

export default async function TopicsPage({ params }: TopicsPageProps) {
  const { locale } = await params;

  return (
    <div className="p-4">
      <TopicsPageClient locale={locale} />
    </div>
  );
}
