import { generateSlug, transliterate, isValidSlug } from '../slug';

describe('Slug Utilities', () => {
  describe('transliterate', () => {
    it('should transliterate Romanian characters correctly', () => {
      expect(transliterate('Știri Locale')).toBe('Stiri Locale');
      expect(transliterate('Șeful Țării')).toBe('Seful Tarii');
      expect(transliterate('Întâmplări din România')).toBe('Intamplari din Romania');
      expect(transliterate('Ăsta e un test')).toBe('Asta e un test');
    });

    it('should handle uppercase and lowercase', () => {
      expect(transliterate('ȘȚĂÂÎș')).toBe('STAAIs');
      expect(transliterate('ȘȚĂÂÎ')).toBe('STAAI');
    });

    it('should preserve ASCII characters', () => {
      expect(transliterate('Hello World')).toBe('Hello World');
      expect(transliterate('Test123')).toBe('Test123');
    });
  });

  describe('generateSlug', () => {
    it('should generate correct slugs from Romanian text', () => {
      expect(generateSlug('Știri Locale')).toBe('stiri-locale');
      expect(generateSlug('Șeful Țării')).toBe('seful-tarii');
      expect(generateSlug('Întâmplări din România')).toBe('intamplari-din-romania');
      expect(generateSlug('Știri despre educație')).toBe('stiri-despre-educatie');
      expect(generateSlug('Ăsta e un test')).toBe('asta-e-un-test');
    });

    it('should handle multiple spaces and special characters', () => {
      expect(generateSlug('Test    Multiple   Spaces')).toBe('test-multiple-spaces');
      expect(generateSlug('Test!@#$%Special&*()Chars')).toBe('testspecialchars');
      expect(generateSlug('  Leading and trailing spaces  ')).toBe('leading-and-trailing-spaces');
    });

    it('should handle empty strings', () => {
      expect(generateSlug('')).toBe('');
    });

    it('should convert to lowercase', () => {
      expect(generateSlug('UPPERCASE TEXT')).toBe('uppercase-text');
    });

    it('should handle numbers', () => {
      expect(generateSlug('Test 123 Numbers')).toBe('test-123-numbers');
    });
  });

  describe('isValidSlug', () => {
    it('should validate correct slugs', () => {
      expect(isValidSlug('stiri-locale')).toBe(true);
      expect(isValidSlug('test-123')).toBe(true);
      expect(isValidSlug('single')).toBe(true);
    });

    it('should reject invalid slugs', () => {
      expect(isValidSlug('Stiri Locale')).toBe(false); // spaces
      expect(isValidSlug('stiri--locale')).toBe(false); // double hyphen
      expect(isValidSlug('-stiri-locale')).toBe(false); // leading hyphen
      expect(isValidSlug('stiri-locale-')).toBe(false); // trailing hyphen
      expect(isValidSlug('Stiri-Locale')).toBe(false); // uppercase
      expect(isValidSlug('')).toBe(false); // empty
      expect(isValidSlug('știri-locale')).toBe(false); // contains diacritics
    });
  });
});
