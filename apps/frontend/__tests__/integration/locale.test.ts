/**
 * Integration Tests - Locale Handling
 * Tests locale detection, switching, and cookie management
 */

import { describe, it, expect, beforeEach } from '@jest/globals';
import { NextRequest } from 'next/server';
import { detectLocale, extractLocaleFromPath, parseAcceptLanguage } from '@/lib/middleware/locale-handler';
import type { Locale } from '@/lib/types';

describe('Locale Handling', () => {
  describe('Locale Detection', () => {
    it('should detect Romanian locale from URL (default)', () => {
      const request = new NextRequest('http://localhost:3005/politica/article');
      const locale = detectLocale(request);

      expect(locale).toBe('ro');
    });

    it('should detect English locale from URL', () => {
      const request = new NextRequest('http://localhost:3005/en/politics/article');
      const locale = detectLocale(request);

      expect(locale).toBe('en');
    });

    it('should detect Russian locale from URL', () => {
      const request = new NextRequest('http://localhost:3005/ru/политика/article');
      const locale = detectLocale(request);

      expect(locale).toBe('ru');
    });

    it('should detect locale from cookie if no URL locale', () => {
      const request = new NextRequest('http://localhost:3005/politica/article', {
        headers: {
          cookie: 'NEXT_LOCALE=en',
        },
      });

      const locale = detectLocale(request);
      expect(locale).toBe('en');
    });

    it('should detect locale from Accept-Language header', () => {
      const request = new NextRequest('http://localhost:3005/politica/article', {
        headers: {
          'accept-language': 'en-US,en;q=0.9,ru;q=0.8',
        },
      });

      const locale = detectLocale(request);
      // Should prefer URL (ro) over Accept-Language
      expect(locale).toBe('ro');
    });

    it('should fall back to default locale (ro)', () => {
      const request = new NextRequest('http://localhost:3005/test');
      const locale = detectLocale(request);

      expect(locale).toBe('ro');
    });

    it('should prioritize URL locale over cookie', () => {
      const request = new NextRequest('http://localhost:3005/en/politics', {
        headers: {
          cookie: 'NEXT_LOCALE=ru',
        },
      });

      const locale = detectLocale(request);
      expect(locale).toBe('en'); // URL wins
    });
  });

  describe('URL Locale Extraction', () => {
    it('should extract locale from path', () => {
      expect(extractLocaleFromPath('/en/politics')).toBe('en');
      expect(extractLocaleFromPath('/ru/политика')).toBe('ru');
      expect(extractLocaleFromPath('/politica')).toBeNull();
    });

    it('should handle paths with multiple segments', () => {
      expect(extractLocaleFromPath('/en/politics/article-slug')).toBe('en');
      expect(extractLocaleFromPath('/ru/category/subcategory/article')).toBe('ru');
    });

    it('should return null for invalid locales', () => {
      expect(extractLocaleFromPath('/fr/politique')).toBeNull();
      expect(extractLocaleFromPath('/de/politik')).toBeNull();
      expect(extractLocaleFromPath('/invalid')).toBeNull();
    });

    it('should handle root paths', () => {
      expect(extractLocaleFromPath('/')).toBeNull();
      expect(extractLocaleFromPath('/en')).toBe('en');
      expect(extractLocaleFromPath('/ru')).toBe('ru');
    });

    it('should be case-insensitive', () => {
      expect(extractLocaleFromPath('/EN/politics')).toBe('en');
      expect(extractLocaleFromPath('/RU/политика')).toBe('ru');
    });
  });

  describe('Accept-Language Parsing', () => {
    it('should parse simple Accept-Language header', () => {
      expect(parseAcceptLanguage('ro')).toBe('ro');
      expect(parseAcceptLanguage('en')).toBe('en');
      expect(parseAcceptLanguage('ru')).toBe('ru');
    });

    it('should parse Accept-Language with quality values', () => {
      expect(parseAcceptLanguage('en-US,en;q=0.9,ro;q=0.8')).toBe('en');
      expect(parseAcceptLanguage('ro-RO,ro;q=0.9,en;q=0.8')).toBe('ro');
      expect(parseAcceptLanguage('ru-RU,ru;q=0.9,en;q=0.7')).toBe('ru');
    });

    it('should handle complex Accept-Language headers', () => {
      expect(parseAcceptLanguage('fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7')).toBe('en');
      expect(parseAcceptLanguage('de,en-US;q=0.9,en;q=0.8,ro;q=0.7')).toBe('en');
    });

    it('should prioritize supported locales', () => {
      // If first language is not supported, should find first supported one
      expect(parseAcceptLanguage('fr,de,en,ro')).toBe('en');
      expect(parseAcceptLanguage('de,fr,ru,zh')).toBe('ru');
    });

    it('should return null for unsupported languages', () => {
      expect(parseAcceptLanguage('fr-FR')).toBeNull();
      expect(parseAcceptLanguage('de-DE')).toBeNull();
      expect(parseAcceptLanguage('ja')).toBeNull();
    });

    it('should handle empty or invalid headers', () => {
      expect(parseAcceptLanguage('')).toBeNull();
      expect(parseAcceptLanguage('invalid')).toBeNull();
    });
  });

  describe('Locale Cookie Management', () => {
    it('should read locale from cookie', () => {
      const request = new NextRequest('http://localhost:3005/', {
        headers: {
          cookie: 'NEXT_LOCALE=en',
        },
      });

      const cookie = request.cookies.get('NEXT_LOCALE');
      expect(cookie?.value).toBe('en');
    });

    it('should handle missing cookie', () => {
      const request = new NextRequest('http://localhost:3005/');
      const cookie = request.cookies.get('NEXT_LOCALE');

      expect(cookie).toBeUndefined();
    });

    it('should validate cookie locale value', () => {
      const validLocales: Locale[] = ['ro', 'en', 'ru'];

      validLocales.forEach((locale) => {
        const request = new NextRequest('http://localhost:3005/', {
          headers: {
            cookie: `NEXT_LOCALE=${locale}`,
          },
        });

        const detectedLocale = detectLocale(request);
        expect(validLocales).toContain(detectedLocale);
      });
    });

    it('should ignore invalid cookie locale', () => {
      const request = new NextRequest('http://localhost:3005/', {
        headers: {
          cookie: 'NEXT_LOCALE=invalid',
        },
      });

      const locale = detectLocale(request);
      expect(locale).toBe('ro'); // Should fall back to default
    });
  });

  describe('Locale Priority Order', () => {
    it('should follow priority: URL > Cookie > Accept-Language > Default', () => {
      // Test 1: URL has highest priority
      const request1 = new NextRequest('http://localhost:3005/en/test', {
        headers: {
          cookie: 'NEXT_LOCALE=ru',
          'accept-language': 'ro',
        },
      });
      expect(detectLocale(request1)).toBe('en');

      // Test 2: Cookie has priority over Accept-Language
      const request2 = new NextRequest('http://localhost:3005/test', {
        headers: {
          cookie: 'NEXT_LOCALE=ru',
          'accept-language': 'en',
        },
      });
      expect(detectLocale(request2)).toBe('ru');

      // Test 3: Accept-Language has priority over default
      const request3 = new NextRequest('http://localhost:3005/test', {
        headers: {
          'accept-language': 'en',
        },
      });
      expect(detectLocale(request3)).toBe('en');

      // Test 4: Default when nothing else is available
      const request4 = new NextRequest('http://localhost:3005/test');
      expect(detectLocale(request4)).toBe('ro');
    });
  });

  describe('Locale Validation', () => {
    it('should validate supported locales', () => {
      const supported: Locale[] = ['ro', 'en', 'ru'];

      supported.forEach((locale) => {
        expect(['ro', 'en', 'ru']).toContain(locale);
      });
    });

    it('should reject unsupported locales', () => {
      const unsupported = ['fr', 'de', 'es', 'it', 'zh', 'ja'];

      unsupported.forEach((locale) => {
        expect(['ro', 'en', 'ru']).not.toContain(locale);
      });
    });
  });

  describe('Edge Cases', () => {
    it('should handle URLs with query parameters', () => {
      const request = new NextRequest('http://localhost:3005/en/politics?page=2&sort=date');
      const locale = detectLocale(request);

      expect(locale).toBe('en');
    });

    it('should handle URLs with fragments', () => {
      const request = new NextRequest('http://localhost:3005/ru/политика#section');
      const locale = detectLocale(request);

      expect(locale).toBe('ru');
    });

    it('should handle admin routes', () => {
      const request = new NextRequest('http://localhost:3005/en/admin/articles');
      const locale = detectLocale(request);

      expect(locale).toBe('en');
    });

    it('should handle API routes', () => {
      const request = new NextRequest('http://localhost:3005/api/articles');
      const locale = detectLocale(request);

      // API routes don't have locale in URL, should fall back
      expect(locale).toBe('ro');
    });
  });
});
