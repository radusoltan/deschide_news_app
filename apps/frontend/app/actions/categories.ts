'use server';

import { revalidatePath } from 'next/cache';
import { createCategory, updateCategory } from '@/lib/dal';

// ============================================================================
// Types
// ============================================================================

export interface CategoryFormState {
  message?: string;
  errors?: {
    title?: string[];
    slug?: string[];
    status?: string[];
    _form?: string[];
  };
}

// ============================================================================
// Server Actions
// ============================================================================

/**
 * Create new category
 */
export async function createCategoryAction(
  locale: string,
  formData: FormData
): Promise<CategoryFormState> {
  const title = formData.get('title') as string;
  const slug = formData.get('slug') as string;
  const status = formData.get('status') as string;
  const onFrontPage = formData.get('onFrontPage') === 'on';

  // Validate required fields
  const errors: CategoryFormState['errors'] = {};

  if (!title || title.trim().length === 0) {
    errors.title = ['Title is required'];
  }

  if (!slug || slug.trim().length === 0) {
    errors.slug = ['Slug is required'];
  }

  if (Object.keys(errors).length > 0) {
    return { errors };
  }

  try {
    await createCategory(
      {
        title: title.trim(),
        slug: slug.trim(),
        status: status || 'active',
        onFrontPage,
      },
      locale
    );

    revalidatePath(`/[locale]/admin/categories`, 'page');

    return { message: 'Category created successfully' };
  } catch (error) {
    console.error('Failed to create category:', error);
    return {
      errors: {
        _form: [error instanceof Error ? error.message : 'Failed to create category'],
      },
    };
  }
}

/**
 * Update existing category
 */
export async function updateCategoryAction(
  id: number,
  locale: string,
  formData: FormData
): Promise<CategoryFormState> {
  const title = formData.get('title') as string;
  const slug = formData.get('slug') as string;
  const status = formData.get('status') as string;
  const onFrontPage = formData.get('onFrontPage') === 'on';

  // Validate required fields
  const errors: CategoryFormState['errors'] = {};

  if (!title || title.trim().length === 0) {
    errors.title = ['Title is required'];
  }

  if (!slug || slug.trim().length === 0) {
    errors.slug = ['Slug is required'];
  }

  if (Object.keys(errors).length > 0) {
    return { errors };
  }

  try {
    await updateCategory(
      id,
      {
        title: title.trim(),
        slug: slug.trim(),
        status: status || 'active',
        onFrontPage,
      },
      locale
    );

    revalidatePath(`/[locale]/admin/categories`, 'page');
    revalidatePath(`/[locale]/admin/categories/[id]`, 'page');

    return { message: 'Category updated successfully' };
  } catch (error) {
    console.error('Failed to update category:', error);
    return {
      errors: {
        _form: [error instanceof Error ? error.message : 'Failed to update category'],
      },
    };
  }
}
