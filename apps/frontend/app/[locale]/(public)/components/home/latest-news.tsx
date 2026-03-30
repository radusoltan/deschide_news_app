import Link from "next/link";
import Image from "next/image";
import { fetchLatestArticles } from "@/lib/api/articles";
import { getFeaturedImage, buildImageUrl, getThumbnailByProfile } from "@/lib/api/important-articles";
import { Article } from "@/lib/types/article";
import { buildArticleUrl, buildLocalizedUrl } from "@/lib/utils/url-builder";
import { ViewCountBadge } from "@/components/public/ViewCountBadge";
import type { Locale } from "@/lib/types";

interface LatestNewsProps {
  locale: string;
}

const sectionLabels: Record<string, { latest: string; popular: string; viewAll: string }> = {
  ro: { latest: 'Ultimele știri', popular: 'Cele mai citite', viewAll: 'Vezi toate' },
  en: { latest: 'Latest news', popular: 'Most popular', viewAll: 'View all' },
  ru: { latest: 'Последние новости', popular: 'Самое популярное', viewAll: 'Смотреть все' },
};

const LatestNews = async ({ locale }: LatestNewsProps) => {
  let articles: Article[] = [];

  try {
    const response = await fetchLatestArticles(locale, 10);
    articles = response.member || [];
  } catch (error) {
    console.error('Failed to fetch latest articles:', error);
    return null;
  }

  if (articles.length === 0) {
    return null;
  }

  const featuredArticle = articles[0];
  const featuredImage = getFeaturedImage(featuredArticle.articleImages);
  // Use article_hero for featured (1600×600, aspect 8:3) — highest resolution thumbnail
  const featuredThumbnail = featuredImage ? getThumbnailByProfile(featuredImage, 'article_hero') : null;
  const gridArticles = articles.slice(1, 7);
  const labels = sectionLabels[locale] || sectionLabels.ro;

  const getCategoryTitle = (category: unknown): string => {
    if (typeof category === 'object' && category !== null && 'title' in category) {
      return (category as { title: string }).title;
    }
    return '';
  };

  const getCategorySlug = (category: unknown): string => {
    if (typeof category === 'object' && category !== null && 'slug' in category) {
      return (category as { slug: string }).slug;
    }
    return '';
  };

  return (
    <section className="bg-[var(--color-surface-elevated)] py-6">
      <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
        <div className="flex flex-row flex-wrap">
          {/* Sidebar - Most Popular */}
          <div className="flex-shrink max-w-full w-full lg:w-1/3 lg:pr-8 lg:pb-8 order-first">
            <div className="w-full bg-[var(--color-surface-elevated)]">
              <div className="mb-6">
                <div className="p-4 bg-[var(--color-surface-dark)]">
                  <h2 className="text-lg font-sans text-white uppercase">{labels.popular}</h2>
                </div>
                <ul>
                  {articles.slice(0, 10).map((article, index) => (
                    <li key={article.id} className="border-b border-[var(--color-border)] hover:bg-[var(--color-surface-sunken)] transition-colors">
                      <Link
                        className="flex items-start gap-3 px-4 py-3"
                        href={buildArticleUrl(article, locale as Locale)}
                      >
                        <span className="flex-shrink-0 text-2xl font-sans text-[var(--color-accent)]/30 leading-none mt-0.5">
                          {(index + 1).toString().padStart(2, '0')}
                        </span>
                        <span className="text-sm font-serif font-medium text-[var(--color-text-primary)] leading-snug line-clamp-2 hover:text-[var(--color-accent)] transition-colors">
                          {article.title}
                        </span>
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          </div>

          {/* Main Content */}
          <div className="flex-shrink max-w-full w-full lg:w-2/3 overflow-hidden">
            {/* Section Title */}
            <div className="flex items-center justify-between mb-5">
              <h2 className="text-[var(--color-text-primary)] text-2xl font-sans uppercase flex items-center">
                <span className="inline-block w-1 h-6 bg-[var(--color-accent)] mr-3 rounded-full" />
                {labels.latest}
              </h2>
              <Link
                href={buildLocalizedUrl('/all', locale as Locale)}
                className="inline-flex items-center gap-1 text-sm font-serif font-medium text-[var(--color-accent)] hover:text-[var(--color-accent)] transition-colors group"
              >
                {labels.viewAll}
                <svg className="w-4 h-4 transform group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                </svg>
              </Link>
            </div>

            {/* Featured Article */}
            <div className="w-full pb-5">
              <article className="group relative overflow-hidden rounded-sm">
                <Link href={buildArticleUrl(featuredArticle, locale as Locale)} className="block">
                  <div className="relative aspect-[8/3] max-h-[28rem] overflow-hidden">
                    {featuredThumbnail ? (
                      <Image
                        className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                        src={buildImageUrl(featuredThumbnail.path)}
                        alt={featuredImage?.alt || featuredArticle.title}
                        width={featuredThumbnail.width}
                        height={featuredThumbnail.height}
                        priority
                        placeholder="blur"
                        blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTkyMCIgaGVpZ2h0PSIxMDgwIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9IiNmM2Y0ZjYiLz48L3N2Zz4="
                      />
                    ) : featuredImage ? (
                      <Image
                        className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                        src={buildImageUrl(featuredImage.path)}
                        alt={featuredImage.alt || featuredArticle.title}
                        width={featuredImage.width || 1920}
                        height={featuredImage.height || 1080}
                        priority
                        placeholder="blur"
                        blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTkyMCIgaGVpZ2h0PSIxMDgwIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9IiNmM2Y0ZjYiLz48L3N2Zz4="
                      />
                    ) : (
                      <div className="w-full h-96 bg-gradient-to-br from-[var(--color-surface-dark)] via-[var(--color-surface-elevated-dark)] to-[var(--color-surface-dark)] flex items-center justify-center">
                        <svg className="w-16 h-16 text-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                        </svg>
                      </div>
                    )}
                  </div>
                  <div className="absolute px-5 pt-8 pb-5 bottom-0 w-full bg-gradient-to-t from-black/90 via-black/50 to-transparent">
                    <h2 className="text-2xl lg:text-3xl font-sans text-white text-on-photo-strong mb-2 leading-tight line-clamp-3 group-hover:text-[var(--color-accent)] transition-colors duration-300">
                      {featuredArticle.title}
                    </h2>
                    {featuredArticle.lead && (
                      <p className="text-white/80 hidden sm:block text-sm font-serif line-clamp-2">
                        {featuredArticle.lead}
                      </p>
                    )}
                    {featuredArticle.category && (
                      <div className="pt-2 flex items-center text-white/60 text-sm">
                        <span className="inline-block w-0.5 h-3 bg-[var(--color-accent)] mr-2" />
                        {getCategoryTitle(featuredArticle.category)}
                      </div>
                    )}
                  </div>
                </Link>
              </article>
            </div>

            {/* Grid of Articles */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-x-4 gap-y-6">
              {gridArticles.map((article) => {
                const image = getFeaturedImage(article.articleImages);
                const thumbnail = image ? getThumbnailByProfile(image, 'article_card') : null;

                return (
                  <article
                    key={article.id}
                    className="group flex flex-col h-full"
                  >
                    {/* Image */}
                    <Link href={buildArticleUrl(article, locale as Locale)} className="block relative aspect-video overflow-hidden bg-[var(--color-surface-sunken)] mb-3 rounded-sm">
                      {thumbnail ? (
                        <Image
                          className="object-cover w-full h-full transition-transform duration-500 group-hover:scale-105"
                          src={buildImageUrl(thumbnail.path)}
                          alt={image?.alt || article.title}
                          fill
                          sizes="(max-width: 640px) 100vw, 33vw"
                          loading="lazy"
                          placeholder="blur"
                          blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwMCIgaGVpZ2h0PSI5MDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2YzZjRmNiIvPjwvc3ZnPg=="
                        />
                      ) : image ? (
                        <Image
                          className="object-cover w-full h-full transition-transform duration-500 group-hover:scale-105"
                          src={buildImageUrl(image.path)}
                          alt={image.alt || article.title}
                          fill
                          sizes="(max-width: 640px) 100vw, 33vw"
                          loading="lazy"
                          placeholder="blur"
                          blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwMCIgaGVpZ2h0PSI5MDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2YzZjRmNiIvPjwvc3ZnPg=="
                        />
                      ) : (
                        <div className="absolute inset-0 bg-[var(--color-surface-sunken)] flex items-center justify-center">
                          <span className="text-[var(--color-text-tertiary)] text-xs">No image</span>
                        </div>
                      )}
                    </Link>

                    {/* Content */}
                    <div className="flex flex-col flex-grow">
                      <h3 className="text-lg font-sans text-[var(--color-text-primary)] leading-snug mb-2 tracking-tight">
                        <Link
                          href={buildArticleUrl(article, locale as Locale)}
                          className="hover:text-[var(--color-accent)] transition-colors duration-200 block"
                        >
                          {article.title}
                        </Link>
                      </h3>

                      <p className="hidden md:block text-[var(--color-text-secondary)] text-sm leading-relaxed mb-3 line-clamp-2 flex-grow font-serif">
                        {article.lead || '\u00A0'}
                      </p>

                      <div className="mt-auto pt-2">
                        <div className="flex items-center justify-between">
                          {article.category && getCategorySlug(article.category) && (
                            <Link
                              className="inline-flex items-center text-xs font-medium text-[var(--color-text-secondary)] hover:text-[var(--color-accent)] transition-colors uppercase tracking-wide font-serif"
                              href={buildLocalizedUrl(`/${getCategorySlug(article.category)}`, locale as Locale)}
                            >
                              <span className="w-0.5 h-3 bg-[var(--color-accent)] mr-2" />
                              {getCategoryTitle(article.category)}
                            </Link>
                          )}
                          {article.viewCount > 0 && (
                            <ViewCountBadge views={article.viewCount} />
                          )}
                        </div>
                      </div>
                    </div>
                  </article>
                );
              })}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};

export default LatestNews;
