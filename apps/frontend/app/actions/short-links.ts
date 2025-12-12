/**
 * Server Actions for Short Links
 * Handles short link CRUD operations with authentication
 */

'use server';

import { revalidatePath } from 'next/cache';
import {
  deleteShortLink as deleteShortLinkApi,
  createShortLink as createShortLinkApi,
  type CreateShortLinkData,
  type ShortLink,
} from '@/lib/api/short-links';
import { getSession } from '@/lib/auth/session';

// ============================================================================
// Types
// ============================================================================

export type ActionResult = {
  success: boolean;
  error?: string;
};

export type CreateShortLinkResult = {
  success: boolean;
  error?: string;
  shortLink?: ShortLink;
};

// ============================================================================
// Delete Short Link Action
// ============================================================================

/**
 * Delete a short link
 * Requires authentication
 */
export async function deleteShortLinkAction(
  id: number,
  locale: string
): Promise<ActionResult> {
  try {
    // Get session and token
    const session = await getSession();
    if (!session) {
      return {
        success: false,
        error: 'Nu sunteți autentificat',
      };
    }

    const token = session.tokens.accessToken;

    // Call API to delete
    await deleteShortLinkApi(id, token);

    // Revalidate the short links page
    revalidatePath(`/${locale}/admin/short-links`);

    return { success: true };
  } catch (error) {
    console.error('Failed to delete short link:', error);
    return {
      success: false,
      error:
        error instanceof Error
          ? error.message
          : 'Eroare la ștergerea linkului scurt',
    };
  }
}

// ============================================================================
// Create Short Link Action
// ============================================================================

/**
 * Create a new short link
 * Requires authentication
 */
export async function createShortLinkAction(
  data: CreateShortLinkData,
  locale: string
): Promise<CreateShortLinkResult> {
  try {
    // Validate URL
    if (!data.originalUrl) {
      return {
        success: false,
        error: 'URL-ul este obligatoriu',
      };
    }

    try {
      new URL(data.originalUrl);
    } catch {
      return {
        success: false,
        error: 'URL-ul nu este valid',
      };
    }

    // Get session and token
    const session = await getSession();
    if (!session) {
      return {
        success: false,
        error: 'Nu sunteți autentificat',
      };
    }

    const token = session.tokens.accessToken;

    // Prepare data (remove empty optional fields)
    const dataToSend: CreateShortLinkData = {
      originalUrl: data.originalUrl,
    };

    if (data.code?.trim()) {
      dataToSend.code = data.code.trim();
    }

    if (data.title?.trim()) {
      dataToSend.title = data.title.trim();
    }

    // Call API to create
    const shortLink = await createShortLinkApi(dataToSend, token);

    // Revalidate the short links page
    revalidatePath(`/${locale}/admin/short-links`);

    return { success: true, shortLink };
  } catch (error) {
    console.error('Failed to create short link:', error);
    return {
      success: false,
      error:
        error instanceof Error
          ? error.message
          : 'Eroare la crearea linkului scurt',
    };
  }
}
