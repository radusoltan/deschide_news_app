/**
 * Slug generation utilities with Romanian character transliteration
 */

/**
 * Transliteration map for Romanian characters
 * Maps Romanian diacritics to their ASCII equivalents
 */
const ROMANIAN_TRANSLITERATION_MAP: Record<string, string> = {
  // Romanian specific characters
  'Ș': 'S', 'ș': 's',
  'Ţ': 'T', 'ţ': 't',
  'Ț': 'T', 'ț': 't',
  'Ă': 'A', 'ă': 'a',
  'Â': 'A', 'â': 'a',
  'Î': 'I', 'î': 'i',

  // Additional common diacritics (for other languages)
  'À': 'A', 'à': 'a',
  'Á': 'A', 'á': 'a',
  'Ä': 'A', 'ä': 'a',
  'È': 'E', 'è': 'e',
  'É': 'E', 'é': 'e',
  'Ë': 'E', 'ë': 'e',
  'Ì': 'I', 'ì': 'i',
  'Í': 'I', 'í': 'i',
  'Ï': 'I', 'ï': 'i',
  'Ò': 'O', 'ò': 'o',
  'Ó': 'O', 'ó': 'o',
  'Ö': 'O', 'ö': 'o',
  'Ù': 'U', 'ù': 'u',
  'Ú': 'U', 'ú': 'u',
  'Ü': 'U', 'ü': 'u',
  'Ñ': 'N', 'ñ': 'n',
  'Ç': 'C', 'ç': 'c',
};

/**
 * Transliterate Romanian and other special characters to ASCII
 *
 * @param text - Text to transliterate
 * @returns Transliterated text
 *
 * @example
 * transliterate('Știri Locale') // returns 'Stiri Locale'
 * transliterate('Șeful Țării') // returns 'Seful Tarii'
 */
export function transliterate(text: string): string {
  return text
    .split('')
    .map((char) => ROMANIAN_TRANSLITERATION_MAP[char] || char)
    .join('');
}

/**
 * Generate a URL-friendly slug from a string
 *
 * @param text - Text to convert to slug
 * @returns URL-friendly slug
 *
 * @example
 * generateSlug('Știri Locale') // returns 'stiri-locale'
 * generateSlug('Șeful Țării în 2024!') // returns 'seful-tarii-in-2024'
 * generateSlug('Test    Multiple   Spaces') // returns 'test-multiple-spaces'
 */
export function generateSlug(text: string): string {
  if (!text) return '';

  return transliterate(text)
    .toLowerCase()
    // Remove all non-alphanumeric characters except spaces and hyphens
    .replace(/[^\w\s-]/g, '')
    // Replace multiple spaces with single hyphen
    .replace(/\s+/g, '-')
    // Replace multiple hyphens with single hyphen
    .replace(/--+/g, '-')
    // Remove leading/trailing hyphens
    .replace(/^-+|-+$/g, '')
    .trim();
}

/**
 * Validate a slug format
 *
 * @param slug - Slug to validate
 * @returns True if slug is valid
 *
 * @example
 * isValidSlug('stiri-locale') // returns true
 * isValidSlug('Stiri Locale') // returns false (contains spaces)
 * isValidSlug('stiri--locale') // returns false (double hyphen)
 */
export function isValidSlug(slug: string): boolean {
  if (!slug) return false;

  // Must be lowercase, alphanumeric with hyphens only
  // No leading/trailing hyphens, no consecutive hyphens
  const slugPattern = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
  return slugPattern.test(slug);
}
