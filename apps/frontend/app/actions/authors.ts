'use server';

import { revalidatePath } from 'next/cache';
import { createAuthor, updateAuthor, deleteAuthor } from '@/lib/dal';

// ============================================================================
// Types
// ============================================================================

export interface AuthorFormState {
  message?: string;
  errors?: {
    firstName?: string[];
    lastName?: string[];
    email?: string[];
    slug?: string[];
    status?: string[];
    _form?: string[];
  };
}

// ============================================================================
// Server Actions
// ============================================================================

/**
 * Create new author
 */
export async function createAuthorAction(
  locale: string,
  formData: FormData
): Promise<AuthorFormState> {
  const firstName = formData.get('firstName') as string;
  const lastName = formData.get('lastName') as string;
  const email = formData.get('email') as string;
  const slug = formData.get('slug') as string;
  const bio = formData.get('bio') as string;
  const status = formData.get('status') as string;
  const isActive = formData.get('isActive') === 'on';
  const twitter = formData.get('twitter') as string;
  const facebook = formData.get('facebook') as string;
  const linkedin = formData.get('linkedin') as string;
  const website = formData.get('website') as string;

  // Validate required fields
  const errors: AuthorFormState['errors'] = {};

  if (!firstName || firstName.trim().length === 0) {
    errors.firstName = ['First name is required'];
  }

  if (!lastName || lastName.trim().length === 0) {
    errors.lastName = ['Last name is required'];
  }

  if (!email || email.trim().length === 0) {
    errors.email = ['Email is required'];
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    errors.email = ['Invalid email format'];
  }

  if (!slug || slug.trim().length === 0) {
    errors.slug = ['Slug is required'];
  }

  if (Object.keys(errors).length > 0) {
    return { errors };
  }

  try {
    await createAuthor(
      {
        firstName: firstName.trim(),
        lastName: lastName.trim(),
        email: email.trim(),
        slug: slug.trim(),
        bio: bio ? bio.trim() : undefined,
        status: status || 'active',
        isActive,
        twitter: twitter || undefined,
        facebook: facebook || undefined,
        linkedin: linkedin || undefined,
        website: website || undefined,
      },
      locale
    );

    revalidatePath(`/[locale]/admin/authors`, 'page');

    return { message: 'Author created successfully' };
  } catch (error) {
    console.error('Failed to create author:', error);
    return {
      errors: {
        _form: [error instanceof Error ? error.message : 'Failed to create author'],
      },
    };
  }
}

/**
 * Update existing author
 */
export async function updateAuthorAction(
  id: number,
  locale: string,
  formData: FormData
): Promise<AuthorFormState> {
  const firstName = formData.get('firstName') as string;
  const lastName = formData.get('lastName') as string;
  const email = formData.get('email') as string;
  const slug = formData.get('slug') as string;
  const bio = formData.get('bio') as string;
  const status = formData.get('status') as string;
  const isActive = formData.get('isActive') === 'on';
  const twitter = formData.get('twitter') as string;
  const facebook = formData.get('facebook') as string;
  const linkedin = formData.get('linkedin') as string;
  const website = formData.get('website') as string;

  // Validate required fields
  const errors: AuthorFormState['errors'] = {};

  if (!firstName || firstName.trim().length === 0) {
    errors.firstName = ['First name is required'];
  }

  if (!lastName || lastName.trim().length === 0) {
    errors.lastName = ['Last name is required'];
  }

  if (!email || email.trim().length === 0) {
    errors.email = ['Email is required'];
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    errors.email = ['Invalid email format'];
  }

  if (!slug || slug.trim().length === 0) {
    errors.slug = ['Slug is required'];
  }

  if (Object.keys(errors).length > 0) {
    return { errors };
  }

  try {
    await updateAuthor(
      id,
      {
        firstName: firstName.trim(),
        lastName: lastName.trim(),
        email: email.trim(),
        slug: slug.trim(),
        bio: bio ? bio.trim() : undefined,
        status: status || 'active',
        isActive,
        twitter: twitter || undefined,
        facebook: facebook || undefined,
        linkedin: linkedin || undefined,
        website: website || undefined,
      },
      locale
    );

    revalidatePath(`/[locale]/admin/authors`, 'page');
    revalidatePath(`/[locale]/admin/authors/[id]`, 'page');

    return { message: 'Author updated successfully' };
  } catch (error) {
    console.error('Failed to update author:', error);
    return {
      errors: {
        _form: [error instanceof Error ? error.message : 'Failed to update author'],
      },
    };
  }
}

/**
 * Delete author
 */
export async function deleteAuthorAction(
  id: number,
  locale: string
): Promise<AuthorFormState> {
  try {
    await deleteAuthor(id, locale);

    revalidatePath(`/[locale]/admin/authors`, 'page');

    return { message: 'Author deleted successfully' };
  } catch (error) {
    console.error('Failed to delete author:', error);
    return {
      errors: {
        _form: [error instanceof Error ? error.message : 'Failed to delete author'],
      },
    };
  }
}
