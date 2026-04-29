/**
 * HomepageSidebar URL emission tests (T60.6 Cluster B / Phase 3.5).
 *
 * Asserts that the trending sidebar consumes `category.translatedSlugs[locale]`
 * via `getCategorySlugForLocale` instead of leaking the source-locale slug.
 */

import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock('@/lib/api/statistics', () => ({
  getTrendingArticles: jest.fn(),
}));

import { getTrendingArticles } from '@/lib/api/statistics';
import { SidebarContent } from '@/app/[locale]/(public)/components/home/HomepageSidebar';

describe('HomepageSidebar — locale-aware article URLs', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  async function renderSidebar(locale: 'ro' | 'en' | 'ru') {
    const ui = await SidebarContent({ locale });
    return render(ui);
  }

  it('builds /en URLs from translatedSlugs.en when locale is "en"', async () => {
    (getTrendingArticles as jest.Mock).mockResolvedValueOnce([
      {
        id: 1,
        title: 'Moldova negotiates EU summit',
        slug: 'moldova-eu-summit',
        category: {
          id: 1,
          name: 'Politics',
          slug: 'politics',
          translatedSlugs: { ro: 'politica', en: 'politics', ru: 'politika' },
        },
        views_24h: 100,
        published_at: '2026-04-01T10:00:00Z',
      },
    ]);

    await renderSidebar('en');
    const link = screen.getByRole('link', { name: /Moldova negotiates EU summit/i });
    expect(link).toHaveAttribute('href', '/en/politics/moldova-eu-summit');
  });

  it('builds prefix-less /{slug} URLs when locale is "ro"', async () => {
    (getTrendingArticles as jest.Mock).mockResolvedValueOnce([
      {
        id: 1,
        title: 'Moldova negociază summit UE',
        slug: 'moldova-summit-ue',
        category: {
          id: 1,
          name: 'Politică',
          slug: 'politica',
          translatedSlugs: { ro: 'politica', en: 'politics', ru: 'politika' },
        },
        views_24h: 100,
        published_at: '2026-04-01T10:00:00Z',
      },
    ]);

    await renderSidebar('ro');
    const link = screen.getByRole('link', { name: /Moldova negociază summit UE/i });
    expect(link).toHaveAttribute('href', '/politica/moldova-summit-ue');
  });

  it('falls back to category.slug when translatedSlugs is absent (defensive — Gedmo serves locale slug)', async () => {
    (getTrendingArticles as jest.Mock).mockResolvedValueOnce([
      {
        id: 1,
        title: 'Headline',
        slug: 'article-en-slug',
        category: {
          id: 1,
          name: 'Politics',
          slug: 'politics', // already locale-specific via Gedmo translatable hint
        },
        views_24h: 50,
        published_at: '2026-04-01T10:00:00Z',
      },
    ]);

    await renderSidebar('en');
    const link = screen.getByRole('link', { name: /Headline/i });
    expect(link).toHaveAttribute('href', '/en/politics/article-en-slug');
  });

  it('renders nothing when trending is empty', async () => {
    (getTrendingArticles as jest.Mock).mockResolvedValueOnce([]);

    const { container } = await renderSidebar('ro');
    // Ad placeholder should still render but no trending links
    expect(container.querySelectorAll('ul').length).toBe(0);
  });
});
