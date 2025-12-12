/**
 * Tag Detail Page - Display articles for a specific tag
 * Route: /[locale]/tags/[slug]
 */

import { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { fetchArticlesByTag, fetchTags, fetchRelatedTags } from '@/lib/api';
import ArticleCard from '@/components/ArticleCard';
import { TagList } from '@/components/tags';
import type { Locale } from '@/lib/types';

interface TagPageProps {
  params: {
    locale: string;
    slug: string;
  };
  searchParams?: {
    page?: string;
  };
}

/**
 * Generate metadata for SEO
 */
export async function generateMetadata({
  params,
}: TagPageProps): Promise<Metadata> {
  const { locale, slug } = params;

  try {
    // Fetch tag by slug
    const tagsResponse = await fetchTags(locale, { slug, itemsPerPage: 1 });
    const tag = tagsResponse['hydra:member']?.[0];

    if (!tag) {
      return {
        title: 'Tag Not Found',
      };
    }

    const title = `#${tag.name}`;
    const description =
      tag.description ||
      `Articles tagged with ${tag.name} - ${tag.usageCount} articles`;

    return {
      title,
      description,
      keywords: [tag.name, ...(tag.description?.split(',') || [])],
      openGraph: {
        title,
        description,
        type: 'website',
      },
      alternates: {
        canonical: `/${locale}/tags/${slug}`,
      },
    };
  } catch (error) {
    return {
      title: 'Tag',
    };
  }
}

/**
 * Tag detail page component
 */
export default async function TagPage({
  params,
  searchParams,
}: TagPageProps) {
  const { locale: localeParam, slug } = params;
  const locale = localeParam as Locale;
  const page = parseInt(searchParams?.page || '1', 10);

  // Fetch tag information
  let tag = null;
  try {
    const tagsResponse = await fetchTags(locale, { slug, itemsPerPage: 1 });
    tag = tagsResponse['hydra:member']?.[0];
  } catch (error) {
    console.error('Failed to fetch tag:', error);
  }

  if (!tag) {
    notFound();
  }

  // Fetch articles with this tag
  let articles: any[] = [];
  let totalItems = 0;
  try {
    const articlesResponse = await fetchArticlesByTag(slug, locale, 20, page);
    articles = articlesResponse['hydra:member'] || [];
    totalItems = articlesResponse['hydra:totalItems'] || 0;
  } catch (error) {
    console.error('Failed to fetch articles:', error);
  }

  // Fetch related tags
  let relatedTags: any[] = [];
  try {
    const relatedResponse = await fetchRelatedTags(tag.id, locale, 10);
    relatedTags = relatedResponse['hydra:member'] || [];
  } catch (error) {
    console.error('Failed to fetch related tags:', error);
  }

  const headings = {
    ro: 'Articole cu tag-ul',
    en: 'Articles tagged with',
    ru: 'Статьи с тегом',
  };

  const relatedHeadings = {
    ro: 'Tag-uri Similare',
    en: 'Related Tags',
    ru: 'Похожие теги',
  };

  const emptyMessages = {
    ro: 'Nu există articole cu acest tag.',
    en: 'No articles with this tag.',
    ru: 'Нет статей с этим тегом.',
  };

  return (
    <div className="container mx-auto px-4 py-8 max-w-7xl">
      {/* Page Header */}
      <div className="mb-8">
        <h1 className="text-4xl font-bold mb-3 text-gray-900 dark:text-gray-100">
          {headings[locale as keyof typeof headings] || headings.ro}{' '}
          <span className="text-blue-600 dark:text-blue-400">#{tag.name}</span>
        </h1>

        {tag.description && (
          <p className="text-lg text-gray-600 dark:text-gray-400 mb-4">
            {tag.description}
          </p>
        )}

        <div className="text-sm text-gray-500 dark:text-gray-400">
          {locale === 'ro' && `${totalItems} articole`}
          {locale === 'en' && `${totalItems} articles`}
          {locale === 'ru' && `${totalItems} статей`}
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Main Content - Articles */}
        <div className="lg:col-span-2">
          {articles.length > 0 ? (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {articles.map((article) => (
                <ArticleCard
                  key={article.id}
                  article={article}
                  locale={locale}
                />
              ))}
            </div>
          ) : (
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8 text-center text-gray-500 dark:text-gray-400">
              {emptyMessages[locale as keyof typeof emptyMessages] ||
                emptyMessages.ro}
            </div>
          )}

          {/* Pagination would go here if needed */}
        </div>

        {/* Sidebar - Related Tags */}
        <div className="lg:col-span-1">
          {relatedTags.length > 0 && (
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 sticky top-4">
              <h2 className="text-xl font-bold mb-4 text-gray-900 dark:text-gray-100">
                {relatedHeadings[locale as keyof typeof relatedHeadings] ||
                  relatedHeadings.ro}
              </h2>
              <TagList
                tags={relatedTags}
                locale={locale}
                variant="outline"
                size="sm"
              />
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
