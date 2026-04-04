/**
 * Topics Listing Page - Display all topics as a tree
 * Route: /[locale]/topics
 */

import { Metadata } from 'next';
import Link from 'next/link';
import type { TopicTreeNode } from '@/lib/types/topic';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

interface TopicsPageProps {
  params: Promise<{ locale: string }>;
}

export async function generateMetadata({
  params,
}: TopicsPageProps): Promise<Metadata> {
  const { locale } = await params;

  const titles: Record<string, string> = {
    ro: 'Subiecte',
    en: 'Topics',
    ru: 'Темы',
  };

  const descriptions: Record<string, string> = {
    ro: 'Explorează articolele pe subiecte tematice',
    en: 'Explore articles by thematic topics',
    ru: 'Изучите статьи по тематическим темам',
  };

  const title = titles[locale] || titles.ro;
  const description = descriptions[locale] || descriptions.ro;

  return {
    title: `${title} — Deschide.md`,
    description,
    openGraph: { title, description, type: 'website' },
  };
}

async function fetchTopicsTree(locale: string): Promise<TopicTreeNode[]> {
  try {
    const res = await fetch(`${API_BASE_URL}/api/topics/tree`, {
      headers: { 'Accept-Language': locale },
      next: { revalidate: 600 },
    });
    if (res.ok) return res.json();
  } catch {
    // silent
  }
  return [];
}

export default async function TopicsPage({ params }: TopicsPageProps) {
  const { locale } = await params;
  const tree = await fetchTopicsTree(locale);

  const headings: Record<string, string> = {
    ro: 'Subiecte',
    en: 'Topics',
    ru: 'Темы',
  };

  const descriptions: Record<string, string> = {
    ro: 'Explorează articolele organizate pe subiecte tematice',
    en: 'Explore articles organized by thematic topics',
    ru: 'Изучите статьи, организованные по тематическим темам',
  };

  return (
    <div className="container mx-auto px-4 py-8 max-w-5xl">
      <div className="mb-8 text-center">
        <h1 className="text-4xl font-bold mb-3 text-primary dark:text-gray-100">
          {headings[locale] || headings.ro}
        </h1>
        <p className="text-lg text-gray-600 dark:text-gray-400">
          {descriptions[locale] || descriptions.ro}
        </p>
      </div>

      {tree.length > 0 ? (
        <div className="bg-surface dark:bg-surface-dark rounded-lg shadow-md p-6 space-y-2">
          {tree.map((node) => (
            <TopicTreeItem key={node.id} node={node} locale={locale} depth={0} />
          ))}
        </div>
      ) : (
        <div className="bg-surface dark:bg-surface-dark rounded-lg shadow-md p-8 text-center text-secondary dark:text-gray-400">
          {locale === 'ru' ? 'Темы в настоящее время недоступны.' : locale === 'en' ? 'No topics available.' : 'Nu exista subiecte disponibile.'}
        </div>
      )}
    </div>
  );
}

function TopicTreeItem({
  node,
  locale,
  depth,
}: {
  node: TopicTreeNode;
  locale: string;
  depth: number;
}) {
  const hasChildren = node.children && node.children.length > 0;

  return (
    <div>
      <Link
        href={`/${locale}/topics/${node.slug}`}
        className={`block px-4 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors ${
          depth === 0 ? 'font-semibold text-base' : 'text-sm'
        } text-primary dark:text-gray-200`}
        style={{ paddingLeft: `${depth * 20 + 16}px` }}
      >
        {node.title}
      </Link>

      {hasChildren &&
        node.children!.map((child) => (
          <TopicTreeItem key={child.id} node={child} locale={locale} depth={depth + 1} />
        ))}
    </div>
  );
}
