/**
 * Tags Page - Display all popular tags
 * Route: /[locale]/tags
 */

import { Metadata } from 'next';
import { fetchPopularTags } from '@/lib/api/tags';
import { TagCloud } from '@/components/tags';

export async function generateMetadata({
  params,
}: {
  params: { locale: string };
}): Promise<Metadata> {
  const titles = {
    ro: 'Tag-uri Populare',
    en: 'Popular Tags',
    ru: 'Популярные теги',
  };

  const descriptions = {
    ro: 'Explorează toate tag-urile și subiectele articolelor noastre',
    en: 'Explore all tags and topics from our articles',
    ru: 'Изучите все теги и темы из наших статей',
  };

  const title = titles[params.locale as keyof typeof titles] || titles.ro;
  const description =
    descriptions[params.locale as keyof typeof descriptions] || descriptions.ro;

  return {
    title,
    description,
    openGraph: {
      title,
      description,
      type: 'website',
    },
  };
}

export default async function TagsPage({
  params,
}: {
  params: { locale: string };
}) {
  const { locale } = params;

  // Fetch popular tags
  let tags: any[] = [];
  try {
    const response = await fetchPopularTags(locale, 100);
    tags = response['hydra:member'] || [];
  } catch (error) {
    console.error('Failed to fetch tags:', error);
  }

  const headings = {
    ro: 'Tag-uri Populare',
    en: 'Popular Tags',
    ru: 'Популярные теги',
  };

  const descriptions = {
    ro: 'Explorează articolele după subiecte și cuvinte cheie',
    en: 'Explore articles by topics and keywords',
    ru: 'Изучайте статьи по темам и ключевым словам',
  };

  const emptyMessages = {
    ro: 'Nu există tag-uri disponibile momentan.',
    en: 'No tags available at the moment.',
    ru: 'Теги в настоящее время недоступны.',
  };

  return (
    <div className="container mx-auto px-4 py-8 max-w-6xl">
      {/* Page Header */}
      <div className="mb-8 text-center">
        <h1 className="text-4xl font-bold mb-3 text-gray-900 dark:text-gray-100">
          {headings[locale as keyof typeof headings] || headings.ro}
        </h1>
        <p className="text-lg text-gray-600 dark:text-gray-400">
          {descriptions[locale as keyof typeof descriptions] || descriptions.ro}
        </p>
      </div>

      {/* Tag Cloud */}
      {tags.length > 0 ? (
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8">
          <TagCloud tags={tags} locale={locale as 'ro' | 'en' | 'ru'} />
        </div>
      ) : (
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8 text-center text-gray-500 dark:text-gray-400">
          {emptyMessages[locale as keyof typeof emptyMessages] ||
            emptyMessages.ro}
        </div>
      )}

      {/* Statistics */}
      {tags.length > 0 && (
        <div className="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
          {locale === 'ro' && `${tags.length} tag-uri disponibile`}
          {locale === 'en' && `${tags.length} tags available`}
          {locale === 'ru' && `${tags.length} тегов доступно`}
        </div>
      )}
    </div>
  );
}
