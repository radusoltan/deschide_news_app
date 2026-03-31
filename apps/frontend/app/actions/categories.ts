'use server';

import { revalidatePath, revalidateTag } from 'next/cache';
import { createCategory, updateCategory, deleteCategory, updateCategoryPositions } from '@/lib/dal';

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

export interface ReorderCategoriesState {
  message?: string;
  success?: boolean;
  errors?: { _form?: string[] };
}

export interface DeleteCategoryState {
  success?: boolean;
  message?: string;
  error?: string;
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
  const inMenu = formData.get('inMenu') === 'on';
  const inFooterMenu = formData.get('inFooterMenu') === 'on';
  const parentId = formData.get('parent') as string;
  const frontPageLayout = formData.get('frontPageLayout') as string | null;

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
    const categoryData: any = {
      title: title.trim(),
      slug: slug.trim(),
      status: status || 'active',
      onFrontPage,
      inMenu,
      inFooterMenu,
      frontPageLayout: onFrontPage && frontPageLayout ? frontPageLayout : null,
    };

    if (parentId && parentId.trim() !== '') {
      categoryData.parent = `/api/categories/${parentId}`;
    } else {
      categoryData.parent = null;
    }

    await createCategory(categoryData, locale);

    revalidatePath(`/[locale]/admin/categories`, 'page');
    revalidateTag('articles', 'max');
    revalidatePath('/ro', 'page');
    revalidatePath('/en', 'page');
    revalidatePath('/ru', 'page');

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
  const inMenu = formData.get('inMenu') === 'on';
  const inFooterMenu = formData.get('inFooterMenu') === 'on';
  const frontPageLayout = formData.get('frontPageLayout') as string | null;

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

  const parentIdUpdate = formData.get('parent') as string;

  try {
    const updateData: any = {
      title: title.trim(),
      slug: slug.trim(),
      status: status || 'active',
      onFrontPage,
      inMenu,
      inFooterMenu,
      frontPageLayout: onFrontPage && frontPageLayout ? frontPageLayout : null,
    };

    if (parentIdUpdate && parentIdUpdate.trim() !== '') {
      updateData.parent = `/api/categories/${parentIdUpdate}`;
    } else {
      updateData.parent = null;
    }

    await updateCategory(id, updateData, locale);

    revalidatePath(`/[locale]/admin/categories`, 'page');
    revalidatePath(`/[locale]/admin/categories/[id]`, 'page');
    // Revalidate homepage when front page settings change
    revalidateTag('articles', 'max');
    revalidatePath('/ro', 'page');
    revalidatePath('/en', 'page');
    revalidatePath('/ru', 'page');

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

/**
 * Delete category
 */
export async function deleteCategoryAction(
  id: number,
  locale: string
): Promise<DeleteCategoryState> {
  try {
    await deleteCategory(id, locale);

    revalidatePath(`/[locale]/admin/categories`, 'page');

    return {
      success: true,
      message: 'Category deleted successfully'
    };
  } catch (error) {
    console.error('Failed to delete category:', error);
    return {
      success: false,
      error: error instanceof Error ? error.message : 'Failed to delete category',
    };
  }
}

/**
 * Reorder front page categories
 */
export async function reorderFrontPageCategoriesAction(
  locale: string,
  positions: Array<{ id: number; frontPagePosition: number }>
): Promise<ReorderCategoriesState> {
  try {
    await updateCategoryPositions(positions, locale);

    revalidateTag('articles', 'max');
    revalidatePath('/ro', 'page');
    revalidatePath('/en', 'page');
    revalidatePath('/ru', 'page');
    revalidatePath(`/[locale]/admin/categories`, 'page');

    return { message: 'Ordinea a fost salvata', success: true };
  } catch (error) {
    console.error('Failed to reorder categories:', error);
    return {
      success: false,
      errors: {
        _form: [error instanceof Error ? error.message : 'Failed to save order'],
      },
    };
  }
}
