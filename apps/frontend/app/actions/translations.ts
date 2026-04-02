'use server';

import { triggerTranslation } from '@/lib/dal';

export interface TranslateActionResult {
  success: boolean;
  message: string;
  error?: string;
}

export async function translateEntityAction(
  entityType: 'article' | 'category' | 'author',
  entityId: number
): Promise<TranslateActionResult> {
  try {
    const result = await triggerTranslation(entityType, entityId);

    return {
      success: true,
      message: result.message || 'Translation queued successfully',
    };
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Translation request failed';

    return {
      success: false,
      message,
      error: message,
    };
  }
}
