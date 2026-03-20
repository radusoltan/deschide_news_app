'use server';

import { revalidatePath } from 'next/cache';
import { createUser, updateUser, deleteUser } from '@/lib/dal';

// ============================================================================
// Types
// ============================================================================

export interface UserFormState {
  message?: string;
  errors?: {
    username?: string[];
    email?: string[];
    plainPassword?: string[];
    confirmPassword?: string[];
    roles?: string[];
    _form?: string[];
  };
}

export interface DeleteUserState {
  success?: boolean;
  message?: string;
  error?: string;
}

// ============================================================================
// Server Actions
// ============================================================================

export async function createUserAction(
  formData: FormData
): Promise<UserFormState> {
  const username = formData.get('username') as string;
  const email = formData.get('email') as string;
  const plainPassword = formData.get('plainPassword') as string;
  const confirmPassword = formData.get('confirmPassword') as string;
  const firstName = formData.get('firstName') as string;
  const lastName = formData.get('lastName') as string;
  const isActive = formData.get('isActive') === 'on' || formData.get('isActive') === 'true';

  // Collect roles from checkboxes
  const roles: string[] = [];
  if (formData.get('role_admin') === 'on') roles.push('ROLE_ADMIN');
  if (formData.get('role_editor') === 'on') roles.push('ROLE_EDITOR');
  if (roles.length === 0) roles.push('ROLE_USER');

  // Validate
  const errors: UserFormState['errors'] = {};

  if (!username || username.trim().length === 0) {
    errors.username = ['Username is required'];
  }

  if (!email || email.trim().length === 0) {
    errors.email = ['Email is required'];
  }

  if (!plainPassword || plainPassword.length < 8) {
    errors.plainPassword = ['Password must be at least 8 characters'];
  }

  if (plainPassword !== confirmPassword) {
    errors.confirmPassword = ['Passwords do not match'];
  }

  if (Object.keys(errors).length > 0) {
    return { errors };
  }

  try {
    await createUser({
      username: username.trim(),
      email: email.trim(),
      plainPassword,
      roles,
      firstName: firstName?.trim() || undefined,
      lastName: lastName?.trim() || undefined,
      isActive,
    });

    revalidatePath('/[locale]/admin/users', 'page');

    return { message: 'User created successfully' };
  } catch (error) {
    console.error('Failed to create user:', error);
    return {
      errors: {
        _form: [error instanceof Error ? error.message : 'Failed to create user'],
      },
    };
  }
}

export async function updateUserAction(
  id: number,
  formData: FormData
): Promise<UserFormState> {
  const username = formData.get('username') as string;
  const email = formData.get('email') as string;
  const plainPassword = formData.get('plainPassword') as string;
  const confirmPassword = formData.get('confirmPassword') as string;
  const firstName = formData.get('firstName') as string;
  const lastName = formData.get('lastName') as string;
  const isActive = formData.get('isActive') === 'on' || formData.get('isActive') === 'true';

  // Collect roles
  const roles: string[] = [];
  if (formData.get('role_admin') === 'on') roles.push('ROLE_ADMIN');
  if (formData.get('role_editor') === 'on') roles.push('ROLE_EDITOR');
  if (roles.length === 0) roles.push('ROLE_USER');

  // Validate
  const errors: UserFormState['errors'] = {};

  if (!username || username.trim().length === 0) {
    errors.username = ['Username is required'];
  }

  if (!email || email.trim().length === 0) {
    errors.email = ['Email is required'];
  }

  // Only validate password if provided
  if (plainPassword && plainPassword.length < 8) {
    errors.plainPassword = ['Password must be at least 8 characters'];
  }

  if (plainPassword && plainPassword !== confirmPassword) {
    errors.confirmPassword = ['Passwords do not match'];
  }

  if (Object.keys(errors).length > 0) {
    return { errors };
  }

  try {
    await updateUser(id, {
      username: username.trim(),
      email: email.trim(),
      plainPassword: plainPassword || undefined,
      roles,
      firstName: firstName?.trim() || undefined,
      lastName: lastName?.trim() || undefined,
      isActive,
    });

    revalidatePath('/[locale]/admin/users', 'page');
    revalidatePath('/[locale]/admin/users/[id]', 'page');

    return { message: 'User updated successfully' };
  } catch (error) {
    console.error('Failed to update user:', error);
    return {
      errors: {
        _form: [error instanceof Error ? error.message : 'Failed to update user'],
      },
    };
  }
}

export async function deleteUserAction(
  id: number
): Promise<DeleteUserState> {
  try {
    await deleteUser(id);

    revalidatePath('/[locale]/admin/users', 'page');

    return {
      success: true,
      message: 'User deleted successfully',
    };
  } catch (error) {
    console.error('Failed to delete user:', error);
    return {
      success: false,
      error: error instanceof Error ? error.message : 'Failed to delete user',
    };
  }
}
