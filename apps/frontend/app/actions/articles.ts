'use server';

import { revalidatePath, revalidateTag } from 'next/cache';
import { createArticle, updateArticle, deleteArticle } from '@/lib/dal';

// ============================================================================
// Types
// ============================================================================

export interface ArticleFormState {
  message?: string;
  articleId?: number;
  errors?: {
    title?: string[];
    slug?: string[];
    content?: string[];
    excerpt?: string[];
    status?: string[];
    _form?: string[];
  };
}

export interface DeleteArticleState {
  message?: string;
  success?: boolean;
  errors?: {
    _form?: string[];
  };
}

function safelyRevalidateArticles() {
  if (typeof revalidateTag === 'function') {
    revalidateTag('articles', 'max');
  }
}

// ============================================================================
// Server Actions
// ============================================================================

/**
 * Create new article
 * Server Action for creating articles from client forms
 */
export async function createArticleAction(
  locale: string,
  formData: FormData
): Promise<ArticleFormState> {
  // Extract form data
  const title = formData.get('title') as string;
  const slug = formData.get('slug') as string;
  const lead = formData.get('lead') as string;
  const content = formData.get('content') as string;
  const excerpt = formData.get('excerpt') as string;
  const status = formData.get('status') as string;
  const category = formData.get('category') as string;
  const authorsJson = formData.get('authors') as string;
  const publishAt = formData.get('publishAt') as string;
  const badge = formData.get('badge') as string;
  const isFeatured = formData.get('isFeatured') as string;

  // Validate required fields
  const errors: ArticleFormState['errors'] = {};

  if (!title || title.trim().length === 0) {
    errors.title = ['Title is required'];
  }

  if (!slug || slug.trim().length === 0) {
    errors.slug = ['Slug is required'];
  }

  if (!content || content.trim().length === 0) {
    errors.content = ['Content is required'];
  }

  // Parse and validate authors
  let authors: string[] = [];
  if (authorsJson) {
    try {
      authors = JSON.parse(authorsJson);
      if (!Array.isArray(authors) || authors.length === 0) {
        errors._form = ['At least one author is required'];
      } else if (authors.length > 5) {
        errors._form = ['Maximum 5 authors allowed'];
      } else if (authors.some(a => !a || a.trim() === '')) {
        errors._form = ['All author fields must be filled'];
      }
    } catch {
      errors._form = ['Invalid authors data'];
    }
  } else {
    errors._form = ['At least one author is required'];
  }

  if (Object.keys(errors).length > 0) {
    return { errors };
  }

  // Create article via DAL
  try {
    const articleData: any = {
      title: title.trim(),
      slug: slug.trim(),
      lead: lead?.trim() || undefined,
      content: content.trim(),
      excerpt: excerpt?.trim() || undefined,
      status: status || 'new',
      badge: badge && badge.trim() !== '' ? badge.trim() : null,
      isFeatured: isFeatured === '1',
    };

    // Add category IRI if selected
    if (category && category.trim() !== '') {
      articleData.category = `/api/categories/${category.trim()}`;
    }

    // Add authors (array of IRIs)
    articleData.authors = authors;

    // Add publishAt only when scheduling (status=submitted)
    if (status === 'submitted' && publishAt && publishAt.trim() !== '') {
      articleData.publishAt = publishAt.trim();
    }

    const newArticle = await createArticle(articleData, locale);

    // Revalidate admin list and public homepage
    revalidatePath(`/[locale]/admin/articles`, 'page');
    safelyRevalidateArticles();
    revalidatePath('/ro', 'page');
    revalidatePath('/en', 'page');
    revalidatePath('/ru', 'page');

    return {
      message: 'Article created successfully',
      articleId: newArticle.id
    };
  } catch (error) {
    console.error('Failed to create article:', error);
    return {
      errors: {
        _form: [error instanceof Error ? error.message : 'Failed to create article'],
      },
    };
  }
}

/**
 * Update existing article
 * Server Action for updating articles from client forms
 */
export async function updateArticleAction(
  id: number,
  locale: string,
  formData: FormData
): Promise<ArticleFormState> {
  // Extract form data
  const title = formData.get('title') as string;
  const slug = formData.get('slug') as string;
  const lead = formData.get('lead') as string;
  const content = formData.get('content') as string;
  const excerpt = formData.get('excerpt') as string;
  const status = formData.get('status') as string;
  const category = formData.get('category') as string;
  const authorsJson = formData.get('authors') as string;
  const publishAt = formData.get('publishAt') as string;
  const badge = formData.get('badge') as string;
  const isFeatured = formData.get('isFeatured') as string;

  // Validate required fields
  const errors: ArticleFormState['errors'] = {};

  if (!title || title.trim().length === 0) {
    errors.title = ['Title is required'];
  }

  if (!slug || slug.trim().length === 0) {
    errors.slug = ['Slug is required'];
  }

  if (!content || content.trim().length === 0) {
    errors.content = ['Content is required'];
  }

  // Parse and validate authors
  let authors: string[] = [];
  if (authorsJson) {
    try {
      authors = JSON.parse(authorsJson);
      if (!Array.isArray(authors) || authors.length === 0) {
        errors._form = ['At least one author is required'];
      } else if (authors.length > 5) {
        errors._form = ['Maximum 5 authors allowed'];
      } else if (authors.some(a => !a || a.trim() === '')) {
        errors._form = ['All author fields must be filled'];
      }
    } catch {
      errors._form = ['Invalid authors data'];
    }
  } else {
    errors._form = ['At least one author is required'];
  }

  if (Object.keys(errors).length > 0) {
    return { errors };
  }

  // Update article via DAL
  try {
    const articleData: any = {
      title: title.trim(),
      slug: slug.trim(),
      lead: lead?.trim() || undefined,
      content: content.trim(),
      excerpt: excerpt?.trim() || undefined,
      status: status || 'new',
      badge: badge && badge.trim() !== '' ? badge.trim() : null,
      isFeatured: isFeatured === '1',
    };

    // Add category IRI if selected
    if (category && category.trim() !== '') {
      articleData.category = `/api/categories/${category.trim()}`;
    }

    // Add authors (array of IRIs)
    articleData.authors = authors;

    // Add publishAt only when scheduling (status=submitted)
    if (status === 'submitted' && publishAt && publishAt.trim() !== '') {
      articleData.publishAt = publishAt.trim();
    }

    await updateArticle(id, articleData, locale);

    // Revalidate admin pages and public homepage
    revalidatePath(`/[locale]/admin/articles`, 'page');
    revalidatePath(`/[locale]/admin/articles/[id]`, 'page');
    safelyRevalidateArticles();
    revalidatePath('/ro', 'page');
    revalidatePath('/en', 'page');
    revalidatePath('/ru', 'page');

    return { message: 'Article updated successfully' };
  } catch (error) {
    console.error('Failed to update article:', error);
    return {
      errors: {
        _form: [error instanceof Error ? error.message : 'Failed to update article'],
      },
    };
  }
}

/**
 * Delete article
 * Server Action for deleting articles
 */
export async function deleteArticleAction(
  id: number,
  locale: string
): Promise<DeleteArticleState> {
  try {
    await deleteArticle(id, locale);

    // Revalidate admin list and public homepage
    revalidatePath(`/[locale]/admin/articles`, 'page');
    safelyRevalidateArticles();
    revalidatePath('/ro', 'page');
    revalidatePath('/en', 'page');
    revalidatePath('/ru', 'page');

    return {
      message: 'Article deleted successfully',
      success: true
    };
  } catch (error) {
    console.error('Failed to delete article:', error);
    return {
      success: false,
      errors: {
        _form: [error instanceof Error ? error.message : 'Failed to delete article'],
      },
    };
  }
}
