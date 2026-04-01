/**
 * Card Utilities Tests
 */

import {
  getSectionColor,
  getCategorySlugFromArticle,
  getCategoryTitle,
  formatRelativeTime,
  getFirstSentence,
  getBadgeProps,
  getLocalizedBadgeText,
} from '@/components/cards/utils';

describe('getSectionColor', () => {
  it('returns politics color for "politica"', () => {
    expect(getSectionColor('politica')).toBe('var(--color-section-politics)');
  });

  it('returns politics color for "politic"', () => {
    expect(getSectionColor('politic')).toBe('var(--color-section-politics)');
  });

  it('returns politics color for "politics"', () => {
    expect(getSectionColor('politics')).toBe('var(--color-section-politics)');
  });

  it('returns economy color for "economie"', () => {
    expect(getSectionColor('economie')).toBe('var(--color-section-economy)');
  });

  it('returns culture color for "cultura"', () => {
    expect(getSectionColor('cultura')).toBe('var(--color-section-culture)');
  });

  it('returns sport color for "sport"', () => {
    expect(getSectionColor('sport')).toBe('var(--color-section-sport)');
  });

  it('returns society color for "societate"', () => {
    expect(getSectionColor('societate')).toBe('var(--color-section-society)');
  });

  it('returns tech color for "tehnologie"', () => {
    expect(getSectionColor('tehnologie')).toBe('var(--color-section-tech)');
  });

  it('returns world color for "externe"', () => {
    expect(getSectionColor('externe')).toBe('var(--color-section-world)');
  });

  it('returns world color for "international"', () => {
    expect(getSectionColor('international')).toBe('var(--color-section-world)');
  });

  it('returns accent color for unknown category', () => {
    expect(getSectionColor('unknown-category')).toBe('var(--color-accent)');
  });

  it('returns accent color for empty string', () => {
    expect(getSectionColor('')).toBe('var(--color-accent)');
  });

  it('is case-insensitive (uppercase POLITICA)', () => {
    expect(getSectionColor('POLITICA')).toBe('var(--color-section-politics)');
  });
});

describe('getCategorySlugFromArticle', () => {
  it('returns empty string when category is null', () => {
    expect(getCategorySlugFromArticle(null)).toBe('');
  });

  it('returns empty string when category is undefined', () => {
    expect(getCategorySlugFromArticle(undefined)).toBe('');
  });

  it('returns slug from Category object', () => {
    expect(getCategorySlugFromArticle({ id: 1, title: 'Politica', slug: 'politica' })).toBe('politica');
  });

  it('returns slug when category is string', () => {
    expect(getCategorySlugFromArticle('sport')).toBe('sport');
  });

  it('returns empty string for object without slug', () => {
    expect(getCategorySlugFromArticle({ id: 1, title: 'Test' } as any)).toBe('');
  });
});

describe('getCategoryTitle', () => {
  it('returns empty string when category is null', () => {
    expect(getCategoryTitle(null)).toBe('');
  });

  it('returns empty string when category is undefined', () => {
    expect(getCategoryTitle(undefined)).toBe('');
  });

  it('returns title from Category object', () => {
    expect(getCategoryTitle({ id: 1, title: 'Politica', slug: 'politica' })).toBe('Politica');
  });

  it('returns string when category is string', () => {
    expect(getCategoryTitle('Sport')).toBe('Sport');
  });

  it('returns empty string for object without title', () => {
    expect(getCategoryTitle({ id: 1, slug: 'test' } as any)).toBe('');
  });
});

describe('formatRelativeTime', () => {
  it('returns "just now" in English for < 1 minute ago', () => {
    const recent = new Date(Date.now() - 30 * 1000).toISOString();
    expect(formatRelativeTime(recent, 'en')).toBe('just now');
  });

  it('returns "acum" in Romanian for < 1 minute ago', () => {
    const recent = new Date(Date.now() - 30 * 1000).toISOString();
    expect(formatRelativeTime(recent, 'ro')).toBe('acum');
  });

  it('returns "только что" in Russian for < 1 minute ago', () => {
    const recent = new Date(Date.now() - 30 * 1000).toISOString();
    expect(formatRelativeTime(recent, 'ru')).toBe('только что');
  });

  it('returns minutes format for 1-59 minutes ago', () => {
    const fiveMinutesAgo = new Date(Date.now() - 5 * 60 * 1000).toISOString();
    expect(formatRelativeTime(fiveMinutesAgo, 'ro')).toBe('5 min');
  });

  it('returns hours format for 1-23 hours ago', () => {
    const twoHoursAgo = new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString();
    expect(formatRelativeTime(twoHoursAgo, 'en')).toBe('2h');
  });

  it('returns formatted date for older articles', () => {
    const longAgo = new Date(Date.now() - 10 * 24 * 60 * 60 * 1000).toISOString();
    const result = formatRelativeTime(longAgo, 'ro');
    // Should be a date string, not min/h
    expect(result).not.toContain('min');
    expect(result).not.toContain('h');
    expect(result.length).toBeGreaterThan(0);
  });
});

describe('getFirstSentence', () => {
  it('extracts first sentence ending in period', () => {
    const html = '<p>First sentence. Second sentence.</p>';
    expect(getFirstSentence(html)).toBe('First sentence.');
  });

  it('extracts first sentence ending in exclamation mark', () => {
    const html = '<p>Amazing news! More details follow.</p>';
    expect(getFirstSentence(html)).toBe('Amazing news!');
  });

  it('extracts first sentence ending in question mark', () => {
    const html = '<p>What happened? Read more.</p>';
    expect(getFirstSentence(html)).toBe('What happened?');
  });

  it('strips HTML tags before extracting', () => {
    const html = '<strong>Breaking:</strong> <em>This is news.</em>';
    expect(getFirstSentence(html)).toBe('Breaking: This is news.');
  });

  it('returns truncated text at maxLength when no sentence found', () => {
    const html = '<p>' + 'A'.repeat(200) + '</p>';
    const result = getFirstSentence(html, 100);
    expect(result.length).toBe(103); // 100 chars + '...'
    expect(result.endsWith('...')).toBe(true);
  });

  it('returns full text when shorter than maxLength and no period', () => {
    const html = '<p>Short text</p>';
    expect(getFirstSentence(html, 150)).toBe('Short text');
  });
});

describe('getBadgeProps', () => {
  it('returns null when badge is null', () => {
    expect(getBadgeProps(null)).toBeNull();
  });

  it('returns null when badge is undefined', () => {
    expect(getBadgeProps(undefined)).toBeNull();
  });

  it('returns null for empty string', () => {
    expect(getBadgeProps('')).toBeNull();
  });

  it('returns BREAKING props for "breaking"', () => {
    const props = getBadgeProps('breaking');
    expect(props?.text).toBe('BREAKING');
    expect(props?.className).toContain('animate-breaking-pulse');
  });

  it('returns ALERT props for "alert"', () => {
    const props = getBadgeProps('alert');
    expect(props?.text).toBe('ALERT');
  });

  it('returns FLASH props for "flash"', () => {
    const props = getBadgeProps('flash');
    expect(props?.text).toBe('FLASH');
  });

  it('returns null for unknown badge type', () => {
    expect(getBadgeProps('unknown')).toBeNull();
  });

  it('is case-insensitive for BREAKING', () => {
    const props = getBadgeProps('BREAKING');
    expect(props?.text).toBe('BREAKING');
  });
});

describe('getLocalizedBadgeText', () => {
  it('returns null when badge is null', () => {
    expect(getLocalizedBadgeText(null, 'ro')).toBeNull();
  });

  it('returns null when badge is undefined', () => {
    expect(getLocalizedBadgeText(undefined, 'ro')).toBeNull();
  });

  it('returns "URGENT" for breaking in Romanian', () => {
    expect(getLocalizedBadgeText('breaking', 'ro')).toBe('URGENT');
  });

  it('returns "BREAKING" for breaking in English', () => {
    expect(getLocalizedBadgeText('breaking', 'en')).toBe('BREAKING');
  });

  it('returns "СРОЧНО" for breaking in Russian', () => {
    expect(getLocalizedBadgeText('breaking', 'ru')).toBe('СРОЧНО');
  });

  it('returns "ALERTĂ" for alert in Romanian', () => {
    expect(getLocalizedBadgeText('alert', 'ro')).toBe('ALERTĂ');
  });

  it('returns "ALERT" for alert in English', () => {
    expect(getLocalizedBadgeText('alert', 'en')).toBe('ALERT');
  });

  it('returns "FLASH" for flash in Romanian', () => {
    expect(getLocalizedBadgeText('flash', 'ro')).toBe('FLASH');
  });

  it('returns uppercase badge for unknown locale', () => {
    expect(getLocalizedBadgeText('breaking', 'fr')).toBe('BREAKING');
  });

  it('returns uppercase for completely unknown badge', () => {
    expect(getLocalizedBadgeText('custom', 'ro')).toBe('CUSTOM');
  });
});
