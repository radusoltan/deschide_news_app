/**
 * Test Page for New Card System
 * Showcases all 6 card types with sample data
 */

import {
  HeroCard,
  FeatureCard,
  CompactCard,
  TextOnlyCard,
  LiveCard,
  MultiSourceCard,
  HeroCardSkeleton,
  FeatureCardSkeleton,
  CompactCardSkeleton,
  TextOnlyCardSkeleton,
  LiveCardSkeleton,
  MultiSourceCardSkeleton,
} from '@/components/cards';

import type { Article } from '@/lib/types/article';

// Sample data
const sampleArticle: Article = {
  '@id': '/api/articles/1',
  '@type': 'Article',
  id: 1,
  title: 'Breaking: Major Political Development in Moldova',
  slug: 'breaking-major-political-development',
  lead: 'This is a sample lead paragraph that would appear in cards to provide context about the story.',
  content: 'Full article content would go here.',
  category: {
    '@id': '/api/categories/1',
    '@type': 'Category',
    id: 1,
    title: 'Politica',
    slug: 'politica',
  },
  authors: ['/api/authors/1'],
  articleImages: [
    {
      '@id': '/api/article_images/1',
      '@type': 'ArticleImage',
      id: 1,
      article: '/api/articles/1',
      image: {
        '@id': '/api/images/1',
        '@type': 'Image',
        id: 1,
        filename: 'sample-image.jpg',
        path: 'images/sample-image.jpg',
        alt: 'Sample news image',
        width: 1920,
        height: 1080,
        mimeType: 'image/jpeg',
        size: 250000,
        originalFilename: 'sample-image.jpg',
        caption: null,
        description: null,
        contentUrl: null,
        uploadedAt: '2026-03-25T10:00:00Z',
        updatedAt: '2026-03-25T10:00:00Z',
        aspectRatio: 16/9,
        formattedSize: '250 KB',
      },
      position: 0,
      isFeatured: true,
    },
  ],
  status: 'published',
  badge: 'breaking',
  viewCount: 1543,
  createdAt: '2026-03-25T10:00:00Z',
  updatedAt: '2026-03-25T10:00:00Z',
  publishedAt: '2026-03-25T10:00:00Z',
  locale: 'ro',
};

const sampleSources = [
  { label: 'Deschide.md', locale: 'ro', href: '/ro/politica/article-1' },
  { label: 'Deschide News EN', locale: 'en', href: '/en/politics/article-1' },
  { label: 'Deschide News RU', locale: 'ru', href: '/ru/politics/article-1' },
];

export default function TestCardsPage() {
  return (
    <div className="min-h-screen bg-[var(--color-surface)] dark:bg-[var(--color-surface-dark)] p-8">
      <div className="max-w-7xl mx-auto">
        <h1 className="text-4xl font-bold mb-8 text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)]">
          Card System Test Page
        </h1>

        {/* Hero Section */}
        <section className="mb-12">
          <h2 className="text-2xl font-semibold mb-4 text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)]">
            Hero Card
          </h2>
          <div className="grid grid-cols-12 gap-6 @container">
            <HeroCard article={sampleArticle} locale="ro" priority className="" />
            <div className="col-span-4 space-y-4">
              <FeatureCard article={sampleArticle} locale="ro" />
            </div>
          </div>
        </section>

        {/* Feature Cards Grid */}
        <section className="mb-12">
          <h2 className="text-2xl font-semibold mb-4 text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)]">
            Feature Cards
          </h2>
          <div className="grid grid-cols-12 gap-6 @container">
            <FeatureCard article={sampleArticle} locale="ro" />
            <FeatureCard
              article={{
                ...sampleArticle,
                title: 'Economic Growth Reaches Record Levels',
                category: {
                  '@id': '/api/categories/2',
                  '@type': 'Category',
                  id: 2,
                  title: 'Economie',
                  slug: 'economie',
                },
                badge: null,
              }}
              locale="ro"
            />
            <FeatureCard
              article={{
                ...sampleArticle,
                title: 'Cultural Festival Draws Thousands',
                category: {
                  '@id': '/api/categories/3',
                  '@type': 'Category',
                  id: 3,
                  title: 'Cultura',
                  slug: 'cultura',
                },
                badge: null,
              }}
              locale="ro"
            />
          </div>
        </section>

        {/* Compact Cards */}
        <section className="mb-12">
          <h2 className="text-2xl font-semibold mb-4 text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)]">
            Compact Cards (Most Read Style)
          </h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {[1, 2, 3, 4, 5].map((rank) => (
              <CompactCard
                key={rank}
                article={{
                  ...sampleArticle,
                  title: `Most Read Article #${rank}`,
                }}
                locale="ro"
                rank={rank}
              />
            ))}
          </div>
        </section>

        {/* Text-only Cards */}
        <section className="mb-12">
          <h2 className="text-2xl font-semibold mb-4 text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)]">
            Text-only Cards
          </h2>
          <div className="grid grid-cols-12 gap-6 @container">
            <TextOnlyCard
              article={{
                ...sampleArticle,
                title: 'Breaking News: Emergency Session Called',
                badge: 'breaking',
              }}
              locale="ro"
            />
            <TextOnlyCard
              article={{
                ...sampleArticle,
                title: 'Opinion: The Future of Democracy',
                category: {
                  '@id': '/api/categories/4',
                  '@type': 'Category',
                  id: 4,
                  title: 'Opinii',
                  slug: 'opinii',
                },
                badge: null,
              }}
              locale="ro"
              authorName="John Doe"
              authorAvatar="/placeholder-avatar.jpg"
            />
            <TextOnlyCard
              article={{
                ...sampleArticle,
                title: 'Quick Update: Parliament Votes',
                badge: 'flash',
              }}
              locale="ro"
            />
          </div>
        </section>

        {/* Live Card */}
        <section className="mb-12">
          <h2 className="text-2xl font-semibold mb-4 text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)]">
            Live Card
          </h2>
          <div className="grid grid-cols-12 gap-6 @container">
            <LiveCard
              title="LIVE: Election Results Coming In"
              updateCount={23}
              latestUpdate="2026-03-25T12:30:00Z"
              href="/live/election-results"
              locale="ro"
            />
          </div>
        </section>

        {/* Multi-source Card */}
        <section className="mb-12">
          <h2 className="text-2xl font-semibold mb-4 text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)]">
            Multi-source Card
          </h2>
          <div className="grid grid-cols-12 gap-6 @container">
            <MultiSourceCard
              headline="International Crisis Affects Region"
              sources={sampleSources}
              imageUrl="/api/images/placeholder.jpg"
            />
            <div className="col-span-8 space-y-4">
              <p className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)]">
                Multi-source card shows the same story available in multiple languages,
                unique to multilingual news platforms.
              </p>
            </div>
          </div>
        </section>

        {/* Skeletons */}
        <section className="mb-12">
          <h2 className="text-2xl font-semibold mb-4 text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)]">
            Skeleton States
          </h2>
          <div className="grid grid-cols-12 gap-6 @container mb-8">
            <HeroCardSkeleton />
            <div className="col-span-4 space-y-4">
              <FeatureCardSkeleton />
            </div>
          </div>

          <div className="grid grid-cols-12 gap-6 @container mb-8">
            <FeatureCardSkeleton />
            <FeatureCardSkeleton />
            <FeatureCardSkeleton />
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            <CompactCardSkeleton />
            <CompactCardSkeleton />
            <CompactCardSkeleton />
          </div>

          <div className="grid grid-cols-12 gap-6 @container mb-8">
            <TextOnlyCardSkeleton />
            <TextOnlyCardSkeleton />
            <TextOnlyCardSkeleton />
          </div>

          <div className="grid grid-cols-12 gap-6 @container mb-8">
            <LiveCardSkeleton />
          </div>

          <div className="grid grid-cols-12 gap-6 @container">
            <MultiSourceCardSkeleton />
            <div className="col-span-8"></div>
          </div>
        </section>
      </div>
    </div>
  );
}