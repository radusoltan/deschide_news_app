/**
 * Unit tests for `lib/seo/meta-tags.ts` — focused on the T60.15 / ADR-029
 * `generateCategoryMetadata` change that pipes `translatedSlugs` into
 * `<head>` hreflang alternates so they match the LanguageSwitcher hrefs.
 */

import { generateCategoryMetadata } from '@/lib/seo/meta-tags';

describe('generateCategoryMetadata — translatedSlugs (T60.15)', () => {
  it('emits per-locale hreflang URLs when translatedSlugs is provided', () => {
    const meta = generateCategoryMetadata(
      'Society',
      'society',
      'en',
      undefined,
      { ro: 'societate', en: 'society', ru: 'obshchestvo' },
    );

    const langs = meta.alternates?.languages as Record<string, string>;
    expect(langs).toBeDefined();
    // EN points at translated EN slug
    expect(langs.en).toMatch(/\/en\/society$/);
    // RU points at translated RU slug (NOT /ru/society naive prefix-swap)
    expect(langs.ru).toMatch(/\/ru\/obshchestvo$/);
    expect(langs.ru).not.toMatch(/\/ru\/society/);
    // RO uses the canonical RO slug
    expect(langs.ro).toMatch(/\/ro\/societate$/);
    expect(langs['ro-MD']).toMatch(/\/ro\/societate$/);
    expect(langs['x-default']).toMatch(/\/ro\/societate$/);
  });

  it('skips a locale entry when its translated slug is missing', () => {
    const meta = generateCategoryMetadata(
      'Society',
      'society',
      'en',
      undefined,
      { ro: 'societate', en: 'society' }, // no ru
    );

    const langs = meta.alternates?.languages as Record<string, string>;
    expect(langs.en).toMatch(/\/en\/society$/);
    expect(langs.ru).toBeUndefined();
  });

  it('falls back to legacy naive prefix-swap when translatedSlugs is omitted (D12 backward-compat)', () => {
    const meta = generateCategoryMetadata('Society', 'society', 'en');

    const langs = meta.alternates?.languages as Record<string, string>;
    expect(langs).toBeDefined();
    // Without translatedSlugs the helper falls back to the same slug for all locales.
    // Acceptable for back-compat; new callers should always pass translatedSlugs.
    expect(langs.en).toMatch(/\/en\/society$/);
    expect(langs.ru).toMatch(/\/ru\/society$/);
    expect(langs.ro).toMatch(/\/ro\/society$/);
  });

  it('preserves canonical URL using current-locale categorySlug regardless of translatedSlugs', () => {
    const meta = generateCategoryMetadata(
      'Society',
      'society',
      'en',
      undefined,
      { ro: 'societate', en: 'society', ru: 'obshchestvo' },
    );

    expect(meta.alternates?.canonical).toMatch(/\/en\/society$/);
  });

  it('uses provided description when given', () => {
    const meta = generateCategoryMetadata(
      'Society',
      'society',
      'en',
      'Custom description here',
      { ro: 'societate', en: 'society', ru: 'obshchestvo' },
    );
    expect(meta.description).toBe('Custom description here');
  });

  it('falls back to default per-locale description when omitted', () => {
    const meta = generateCategoryMetadata('Société', 'societe', 'en');
    expect(meta.description).toContain('Société');
  });
});
