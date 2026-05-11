import Link from 'next/link';
import { notFound } from 'next/navigation';
import moment from 'moment';
import ArticleEditWrapper from './components/ArticleEditWrapper';
import TranslationTabs from './components/TranslationTabs';
import { getArticle, getCategories } from '@/lib/dal';
import { getAuthors } from '@/lib/api/authors';
import type { Article, Category } from '@/lib/types/article';
import type { Tag } from '@/lib/types/tag';

interface EditArticlePageProps {
  params: Promise<{
    locale: string;
    id: string;
  }>;
}

export default async function EditArticlePage({ params }: EditArticlePageProps) {
  const { locale, id } = await params;
  const articleId = parseInt(id, 10);

  if (isNaN(articleId)) {
    notFound();
  }

  // Fetch article data
  let article;
  try {
    article = await getArticle(articleId, locale);
  } catch (error) {
    console.error('Failed to fetch article:', error);
    notFound();
  }

  // Fetch categories for the select dropdown
  let categories: Category[] = [];
  try {
    const data = await getCategories({ locale, itemsPerPage: 100 });
    categories = data.member.filter((cat: Category) => cat.status === 'active');
  } catch (error) {
    console.error('Failed to fetch categories:', error);
    categories = [];
  }

  // Fetch authors for the article form
  let authors: Awaited<ReturnType<typeof getAuthors>> = [];
  try {
    authors = await getAuthors();
  } catch (error) {
    console.error('Failed to fetch authors:', error);
    authors = [];
  }

  // Extract category ID from article.category (can be string IRI or object)
  let categoryId = '';
  if (article.category) {
    if (typeof article.category === 'string') {
      // Extract ID from IRI like "/api/categories/1"
      const match = article.category.match(/\/(\d+)$/);
      categoryId = match ? match[1] : '';
    } else if (typeof article.category === 'object' && article.category !== null && 'id' in article.category) {
      categoryId = (article.category as { id: number }).id.toString();
    }
  }

  // Extract author IRIs from article.authors
  let authorIris: string[] = [];
  if (article.authors && Array.isArray(article.authors)) {
    authorIris = article.authors.map((author: any) => {
      if (typeof author === 'string') {
        return author; // Already an IRI
      } else if (typeof author === 'object' && author !== null && 'id' in author) {
        return `/api/authors/${author.id}`;
      }
      return '';
    }).filter((iri: string) => iri !== '');
  }

  // Extract tags from article (already full Tag objects from eager loading)
  let articleTags: Tag[] = [];
  if (article.tags && Array.isArray(article.tags)) {
    articleTags = article.tags
      .map((tag: any) => {
        if (typeof tag === 'object' && tag !== null && 'id' in tag) {
          return tag;
        }
        return null;
      })
      .filter((t): t is Tag => t !== null);
  }

  // Extract related article IDs
  let relatedArticleIds: number[] = [];
  if (article.relatedArticles && Array.isArray(article.relatedArticles)) {
    relatedArticleIds = article.relatedArticles.map((relatedArticle: any) => {
      if (typeof relatedArticle === 'string') {
        // Extract ID from IRI like "/api/articles/123"
        const match = relatedArticle.match(/\/(\d+)$/);
        return match ? parseInt(match[1], 10) : null;
      } else if (typeof relatedArticle === 'object' && relatedArticle !== null && 'id' in relatedArticle) {
        return relatedArticle.id;
      }
      return null;
    }).filter((id: number | null) => id !== null);
  }

  // Convert publishAt (scheduled date) from ISO to datetime-local format (YYYY-MM-DDTHH:mm)
  let publishAtLocal = '';
  if (article.publishAt) {
    // Use moment to convert to local timezone and format for datetime-local input
    publishAtLocal = moment(article.publishAt).format('YYYY-MM-DDTHH:mm');
  }

  return (
    <div className="p-4">
      {/* Page Header */}
      <div className="mb-4">
        <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 mb-2">
          <Link href={`/${locale}/admin`} className="hover:text-blue-600">
            Dashboard
          </Link>
          <span>/</span>
          <Link href={`/${locale}/admin/articles`} className="hover:text-blue-600">
            Articles
          </Link>
          <span>/</span>
          <span>Edit Article</span>
        </div>
        <h1 className="text-2xl font-semibold text-primary dark:text-primary-dark">
          Edit Article
        </h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Update article details
        </p>
      </div>

      {/* AI Generation Banner */}
      {article.aiGenerated && (
        <div className="mb-4 p-4 rounded-lg border bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800">
          <div className="flex items-center gap-3">
            <span className="text-2xl" role="img" aria-label="AI generated">&#x1F916;</span>
            <div>
              <p className="text-sm font-medium text-blue-800 dark:text-blue-200">
                Articol generat AI
              </p>
              <p className="text-xs text-blue-600 dark:text-blue-400 mt-0.5">
                {article.aiSourceCount != null && `${article.aiSourceCount} surse`}
                {article.aiConfidenceScore != null && ` \u2022 confidence: ${(article.aiConfidenceScore * 100).toFixed(0)}%`}
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Translation Language Tabs */}
      <TranslationTabs
        articleId={articleId}
        activeLocale={locale}
        availableLocales={[]}
      />

      {/* Article Form with Lock Management */}
      <div className="bg-surface dark:bg-surface-dark shadow-md sm:rounded-lg p-6">
        <ArticleEditWrapper
          locale={locale}
          article={{
            id: article.id,
            title: article.title,
            slug: article.slug,
            lead: article.lead || '',
            content: article.content || '',
            status: article.status || 'new',
            category: categoryId,
            authors: authorIris,
            tags: articleTags,
            publishAt: publishAtLocal,
            badge: article.badge || null,
            isFeatured: article.isFeatured || false,
            metaTitle: article.metaTitle || null,
            metaDescription: article.metaDescription || null,
          } as any}
          categories={categories}
          authors={authors as any}
        />
      </div>

    </div>
  );
}
