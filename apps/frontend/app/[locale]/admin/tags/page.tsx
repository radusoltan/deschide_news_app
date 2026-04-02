import TagsPageClient from './TagsPageClient';

interface TagsPageProps {
  params: Promise<{
    locale: string;
  }>;
  searchParams: Promise<{
    page?: string;
    search?: string;
  }>;
}

export default async function TagsPage({ params, searchParams }: TagsPageProps) {
  const { locale } = await params;
  const { page: pageParam, search } = await searchParams;
  const currentPage = pageParam ? parseInt(pageParam, 10) : 1;

  return (
    <div className="p-4">
      <TagsPageClient
        locale={locale}
        initialPage={currentPage}
        initialSearch={search || ''}
      />
    </div>
  );
}
