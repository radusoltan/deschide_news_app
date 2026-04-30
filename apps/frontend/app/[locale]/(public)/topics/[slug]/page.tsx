/**
 * Topic Detail Page - Display articles for a specific topic
 * Route: /[locale]/topics/[slug]
 */

import { Metadata } from 'next';
import { notFound } from 'next/navigation';
import Link from 'next/link';
import ArticleCard from '@/components/ArticleCard';
import LocaleContextSetter from '@/app/components/LocaleContextSetter';
import { fetchTopicBySlug } from '@/lib/api/topics';
import type { Article } from '@/lib/types/article';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';
const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL ?? '';

interface TopicPageProps {
  params: Promise<{ locale: string; slug: string }>;
  searchParams?: Promise<{ page?: string }>;
}

interface PathItem {
  id: number;
  title: string;
  slug: string;
  lvl: number;
}

async function fetchTopicPath(topicId: number, locale: string): Promise<PathItem[]> {
  try {
    const res = await fetch(`${API_BASE_URL}/api/topics/${topicId}/path`, {
      headers: { 'Accept-Language': locale },
      next: { revalidate: 600 },
    });
    if (res.ok) return res.json();
  } catch {
    // silent
  }
  return [];
}

async function fetchTopicArticles(
  topicId: number,
  locale: string,
  page: number
): Promise<{ articles: Article[]; total: number }> {
  try {
    const res = await fetch(
      `${API_BASE_URL}/api/topics/${topicId}/articles?page=${page}&itemsPerPage=20`,
      {
        headers: { 'Accept-Language': locale },
        next: { revalidate: 300 },
      }
    );
    if (res.ok) {
      const data = await res.json();
      return {
        articles: data['hydra:member'] || [],
        total: data['hydra:totalItems'] || 0,
      };
    }
  } catch {
    // silent
  }
  return { articles: [], total: 0 };
}

export async function generateMetadata({ params }: TopicPageProps): Promise<Metadata> {
  const { locale, slug } = await params;
  const topic = await fetchTopicBySlug(slug, locale);

  if (!topic) {
    return { title: 'Topic Not Found' };
  }

  const title = topic.title;
  const description = topic.description || `Articles about ${topic.title}`;

  const slugRo = topic.translatedSlugs?.ro || slug;
  const slugEn = topic.translatedSlugs?.en || slug;
  const slugRu = topic.translatedSlugs?.ru || slug;

  return {
    title: `${title} — Deschide.md`,
    description,
    openGraph: { title, description, type: 'website' },
    alternates: {
      canonical: `${SITE_URL}/${locale}/topics/${slug}`,
      languages: {
        'ro-MD': `${SITE_URL}/ro/topics/${slugRo}`,
        ro: `${SITE_URL}/ro/topics/${slugRo}`,
        en: `${SITE_URL}/en/topics/${slugEn}`,
        ru: `${SITE_URL}/ru/topics/${slugRu}`,
        'x-default': `${SITE_URL}/ro/topics/${slugRo}`,
      },
    },
  };
}

export default async function TopicDetailPage({ params, searchParams }: TopicPageProps) {
  const { locale, slug } = await params;
  const sp = searchParams ? await searchParams : {};
  const page = sp.page ? parseInt(sp.page, 10) : 1;

  const topic = await fetchTopicBySlug(slug, locale);
  if (!topic) notFound();

  const [path, { articles, total }] = await Promise.all([
    fetchTopicPath(topic.id, locale),
    fetchTopicArticles(topic.id, locale, page),
  ]);

  const totalPages = Math.ceil(total / 20);

  const labels: Record<string, { articles: string; noArticles: string; prev: string; next: string; pageOf: string }> = {
    ro: { articles: 'articole', noArticles: 'Niciun articol in acest subiect.', prev: 'Anterior', next: 'Urmator', pageOf: 'din' },
    en: { articles: 'articles', noArticles: 'No articles in this topic.', prev: 'Previous', next: 'Next', pageOf: 'of' },
    ru: { articles: 'статей', noArticles: 'Нет статей по этой теме.', prev: 'Назад', next: 'Далее', pageOf: 'из' },
  };
  const l = labels[locale] || labels.ro;

  return (
    <div className="container mx-auto px-4 py-8 max-w-6xl">
      {/* Feeds per-topic translated slugs into the shared LanguageSwitcher
          for client-side navigation between topics (layout-level resolver
          only runs on hard nav). */}
      <LocaleContextSetter
        context="topic"
        translatedSlugs={topic.translatedSlugs}
      />

      {/* Breadcrumb */}
      <nav className="mb-6 text-sm text-secondary dark:text-gray-400">
        <ol className="flex flex-wrap items-center gap-1">
          <li>
            <Link href={`/${locale}`} className="hover:text-primary dark:hover:text-gray-200">
              Home
            </Link>
          </li>
          <li className="mx-1">/</li>
          <li>
            <Link href={`/${locale}/topics`} className="hover:text-primary dark:hover:text-gray-200">
              {locale === 'ru' ? 'Темы' : locale === 'en' ? 'Topics' : 'Subiecte'}
            </Link>
          </li>
          {path.map((item) => (
            <li key={item.id} className="flex items-center gap-1">
              <span className="mx-1">/</span>
              <Link
                href={`/${locale}/topics/${item.slug}`}
                className={`hover:text-primary dark:hover:text-gray-200 ${
                  item.slug === slug ? 'font-semibold text-primary dark:text-gray-100' : ''
                }`}
              >
                {item.title}
              </Link>
            </li>
          ))}
        </ol>
      </nav>

      {/* Topic header */}
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-primary dark:text-gray-100 mb-2">
          {topic.title}
        </h1>
        {topic.description && (
          <p className="text-lg text-secondary dark:text-gray-400">{topic.description}</p>
        )}
        <p className="mt-2 text-sm text-secondary dark:text-gray-500">
          {total} {l.articles}
        </p>
      </div>

      {/* Articles grid */}
      {articles.length > 0 ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {articles.map((article: Article) => (
            <ArticleCard key={article.id} article={article} locale={locale} />
          ))}
        </div>
      ) : (
        <div className="bg-surface dark:bg-surface-dark rounded-lg shadow-md p-8 text-center text-secondary dark:text-gray-400">
          {l.noArticles}
        </div>
      )}

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="mt-8 flex items-center justify-center gap-4">
          {page > 1 && (
            <Link
              href={`/${locale}/topics/${slug}?page=${page - 1}`}
              className="px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 text-primary dark:text-gray-200"
            >
              {l.prev}
            </Link>
          )}
          <span className="text-sm text-secondary dark:text-gray-400">
            {page} {l.pageOf} {totalPages}
          </span>
          {page < totalPages && (
            <Link
              href={`/${locale}/topics/${slug}?page=${page + 1}`}
              className="px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 text-primary dark:text-gray-200"
            >
              {l.next}
            </Link>
          )}
        </div>
      )}

      {/* JSON-LD Structured Data */}
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{
          __html: JSON.stringify({
            '@context': 'https://schema.org',
            '@type': 'CollectionPage',
            name: topic.title,
            description: topic.description || `Articles about ${topic.title}`,
            url: `${SITE_URL}/${locale}/topics/${slug}`,
            numberOfItems: total,
          }),
        }}
      />
    </div>
  );
}
