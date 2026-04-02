import {
  getSectionColor,
  getCategorySlugFromArticle,
  getCategoryTitle,
  formatRelativeTime,
  getFirstSentence,
  getBadgeProps,
  getLocalizedBadgeText,
} from '@/components/cards/utils';

describe('Card Utilities', () => {
  describe('getSectionColor', () => {
    it('returns politics color for politica', () => {
      expect(getSectionColor('politica')).toBe('var(--color-section-politics)');
    });

    it('returns politics color for politics (en)', () => {
      expect(getSectionColor('politics')).toBe('var(--color-section-politics)');
    });

    it('returns economy color', () => {
      expect(getSectionColor('economie')).toBe('var(--color-section-economy)');
    });

    it('returns culture color', () => {
      expect(getSectionColor('cultura')).toBe('var(--color-section-culture)');
    });

    it('returns sport color', () => {
      expect(getSectionColor('sport')).toBe('var(--color-section-sport)');
    });

    it('returns opinion color', () => {
      expect(getSectionColor('opinii')).toBe('var(--color-section-opinion)');
    });

    it('returns society color', () => {
      expect(getSectionColor('societate')).toBe('var(--color-section-society)');
    });

    it('returns tech color', () => {
      expect(getSectionColor('tehnologie')).toBe('var(--color-section-tech)');
    });

    it('returns world color', () => {
      expect(getSectionColor('externe')).toBe('var(--color-section-world)');
    });

    it('is case-insensitive', () => {
      expect(getSectionColor('POLITICA')).toBe('var(--color-section-politics)');
    });

    it('returns accent fallback for unknown slug', () => {
      expect(getSectionColor('unknown')).toBe('var(--color-accent)');
    });

    it('returns accent fallback for empty string', () => {
      expect(getSectionColor('')).toBe('var(--color-accent)');
    });
  });

  describe('getCategorySlugFromArticle', () => {
    it('returns slug from category object', () => {
      expect(getCategorySlugFromArticle({ slug: 'politica', title: 'Politica' } as any)).toBe('politica');
    });

    it('returns string category as-is', () => {
      expect(getCategorySlugFromArticle('economia')).toBe('economia');
    });

    it('returns empty string for null', () => {
      expect(getCategorySlugFromArticle(null)).toBe('');
    });

    it('returns empty string for undefined', () => {
      expect(getCategorySlugFromArticle(undefined)).toBe('');
    });

    it('returns empty string for object without slug', () => {
      expect(getCategorySlugFromArticle({} as any)).toBe('');
    });
  });

  describe('getCategoryTitle', () => {
    it('returns title from category object', () => {
      expect(getCategoryTitle({ slug: 'politica', title: 'Politica' } as any)).toBe('Politica');
    });

    it('returns string category as-is', () => {
      expect(getCategoryTitle('Economia')).toBe('Economia');
    });

    it('returns empty string for null', () => {
      expect(getCategoryTitle(null)).toBe('');
    });

    it('returns empty string for undefined', () => {
      expect(getCategoryTitle(undefined)).toBe('');
    });
  });

  describe('formatRelativeTime', () => {
    it('returns "acum" for very recent dates in ro', () => {
      const now = new Date().toISOString();
      expect(formatRelativeTime(now, 'ro')).toBe('acum');
    });

    it('returns "just now" for very recent dates in en', () => {
      const now = new Date().toISOString();
      expect(formatRelativeTime(now, 'en')).toBe('just now');
    });

    it('returns "только что" for very recent dates in ru', () => {
      const now = new Date().toISOString();
      expect(formatRelativeTime(now, 'ru')).toBe('только что');
    });

    it('returns minutes for recent past', () => {
      const fiveMinAgo = new Date(Date.now() - 5 * 60000).toISOString();
      expect(formatRelativeTime(fiveMinAgo, 'ro')).toBe('5 min');
    });

    it('returns hours for same day', () => {
      const threeHoursAgo = new Date(Date.now() - 3 * 3600000).toISOString();
      expect(formatRelativeTime(threeHoursAgo, 'ro')).toBe('3h');
    });

    it('returns formatted date for older', () => {
      const threeDaysAgo = new Date(Date.now() - 3 * 86400000).toISOString();
      const result = formatRelativeTime(threeDaysAgo, 'ro');
      expect(typeof result).toBe('string');
      expect(result).not.toBe('acum');
    });
  });

  describe('getFirstSentence', () => {
    it('extracts first sentence from HTML', () => {
      expect(getFirstSentence('<p>Hello world. More text here.</p>')).toBe('Hello world.');
    });

    it('handles text without sentences', () => {
      expect(getFirstSentence('Short text')).toBe('Short text');
    });

    it('truncates long text without sentences', () => {
      const longText = 'a'.repeat(200);
      const result = getFirstSentence(longText, 150);
      expect(result.length).toBeLessThanOrEqual(153); // 150 + '...'
      expect(result.endsWith('...')).toBe(true);
    });

    it('strips HTML tags', () => {
      expect(getFirstSentence('<b>Bold</b> text.')).toBe('Bold text.');
    });

    it('handles question marks', () => {
      expect(getFirstSentence('Is this a question? More text.')).toBe('Is this a question?');
    });

    it('handles exclamation marks', () => {
      expect(getFirstSentence('Wow! More text.')).toBe('Wow!');
    });
  });

  describe('getBadgeProps', () => {
    it('returns breaking badge props', () => {
      const result = getBadgeProps('breaking');
      expect(result).toEqual({
        text: 'BREAKING',
        className: expect.stringContaining('bg-[var(--color-breaking)]'),
      });
    });

    it('returns alert badge props', () => {
      const result = getBadgeProps('alert');
      expect(result?.text).toBe('ALERT');
    });

    it('returns flash badge props', () => {
      const result = getBadgeProps('flash');
      expect(result?.text).toBe('FLASH');
    });

    it('returns null for null badge', () => {
      expect(getBadgeProps(null)).toBeNull();
    });

    it('returns null for undefined badge', () => {
      expect(getBadgeProps(undefined)).toBeNull();
    });

    it('returns null for unknown badge', () => {
      expect(getBadgeProps('unknown')).toBeNull();
    });

    it('is case-insensitive', () => {
      expect(getBadgeProps('BREAKING')).toBeTruthy();
    });
  });

  describe('getLocalizedBadgeText', () => {
    it('returns Romanian text for breaking', () => {
      expect(getLocalizedBadgeText('breaking', 'ro')).toBe('URGENT');
    });

    it('returns English text for breaking', () => {
      expect(getLocalizedBadgeText('breaking', 'en')).toBe('BREAKING');
    });

    it('returns Russian text for breaking', () => {
      expect(getLocalizedBadgeText('breaking', 'ru')).toBe('СРОЧНО');
    });

    it('returns Romanian text for alert', () => {
      expect(getLocalizedBadgeText('alert', 'ro')).toBe('ALERTĂ');
    });

    it('returns Russian text for alert', () => {
      expect(getLocalizedBadgeText('alert', 'ru')).toBe('ТРЕВОГА');
    });

    it('returns null for null badge', () => {
      expect(getLocalizedBadgeText(null, 'ro')).toBeNull();
    });

    it('returns null for undefined badge', () => {
      expect(getLocalizedBadgeText(undefined, 'ro')).toBeNull();
    });

    it('returns uppercased badge for unknown badge type', () => {
      expect(getLocalizedBadgeText('custom', 'ro')).toBe('CUSTOM');
    });
  });
});
