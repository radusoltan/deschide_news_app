# Slug Transliteration for Romanian Characters

This document explains how slug generation handles Romanian characters (Ș/ș, Ț/ț, Ă/ă, Â/â, Î/î) in both frontend and backend.

## Problem

Romanian characters were not being properly transliterated when generating URL-friendly slugs:
- "Știri Locale" was becoming "tiri-locale" (missing "Ș")
- Should be "stiri-locale"

This happened because:
- **Frontend**: Used a simple regex `/[^\w\s-]/g` that removed characters it didn't recognize
- **Backend**: Behat Transliterator's `urlize()` method uses `unaccent()` which has a hardcoded list that excludes Romanian Ș/ș (U+0218/U+0219) and Ț/ț (U+021A/U+021B)

## Solution

### Frontend Implementation

**Location**: `/var/www/deschide_news_app/apps/frontend/lib/utils/slug.ts`

Created a dedicated slug utility module with proper Romanian character transliteration:

```typescript
// Transliteration map for Romanian characters
const ROMANIAN_TRANSLITERATION_MAP: Record<string, string> = {
  'Ș': 'S', 'ș': 's',
  'Ț': 'T', 'ț': 't',
  'Ă': 'A', 'ă': 'a',
  'Â': 'A', 'â': 'a',
  'Î': 'I', 'î': 'i',
  // ... plus other common diacritics
};

export function generateSlug(text: string): string {
  if (!text) return '';

  return transliterate(text)
    .toLowerCase()
    .replace(/[^\w\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/--+/g, '-')
    .replace(/^-+|-+$/g, '')
    .trim();
}
```

**Features**:
- `transliterate()` - Converts Romanian characters to ASCII
- `generateSlug()` - Creates URL-friendly slugs
- `isValidSlug()` - Validates slug format

**Usage in CategoryForm**:

```typescript
import { generateSlug } from '@/lib/utils/slug';

const handleGenerateSlug = () => {
  const slug = generateSlug(formData.title);
  setFormData({ ...formData, slug });
};
```

### Backend Implementation

**Location**: `/var/www/deschide_news_app/apps/backend/src/Service/RomanianSlugger.php`

Created a custom slugger service that uses Behat's `transliterate()` method (which correctly handles Romanian characters) instead of `urlize()`:

```php
class RomanianSlugger
{
    public static function slugifyStatic(string $text, string $separator = '-'): string
    {
        // Use transliterate() instead of urlize() for proper Romanian support
        return Transliterator::transliterate($text, $separator);
    }
}
```

**Configuration**:

Event subscriber automatically configures Gedmo Sluggable listener:

**File**: `/var/www/deschide_news_app/apps/backend/src/EventSubscriber/SluggableTransliteratorSubscriber.php`

```php
class SluggableTransliteratorSubscriber implements EventSubscriberInterface
{
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Set custom transliterator for Romanian character handling
        $this->sluggableListener->setTransliterator([RomanianSlugger::class, 'slugifyStatic']);
    }
}
```

This ensures that all entities using `#[Gedmo\Slug]` annotation will properly transliterate Romanian characters.

## Testing

### Backend Test Command

**Location**: `/var/www/deschide_news_app/apps/backend/src/Command/TestSlugGenerationCommand.php`

```bash
symfony console app:test:slug-generation
```

Tests slug generation with Romanian titles and verifies correct transliteration.

### Frontend Test File

**Location**: `/var/www/deschide_news_app/apps/frontend/lib/utils/__tests__/slug.test.ts`

Contains comprehensive unit tests for:
- Character transliteration
- Slug generation
- Slug validation

Run with:
```bash
cd /var/www/deschide_news_app/apps/frontend
pnpm test slug
```

## Test Cases

All these test cases pass in both frontend and backend:

| Input                      | Expected Slug              | Result |
|----------------------------|----------------------------|--------|
| Știri Locale               | stiri-locale               | ✅     |
| Șeful Țării                | seful-tarii                | ✅     |
| Întâmplări din România     | intamplari-din-romania     | ✅     |
| Știri despre educație      | stiri-despre-educatie      | ✅     |
| Ăsta e un test             | asta-e-un-test             | ✅     |

## Romanian Character Mapping

| Character | Unicode | ASCII Equivalent | Notes |
|-----------|---------|------------------|-------|
| Ș / ș     | U+0218 / U+0219 | S / s | S with comma below |
| Ț / ț     | U+021A / U+021B | T / t | T with comma below |
| Ă / ă     | U+0102 / U+0103 | A / a | A with breve |
| Â / â     | U+00C2 / U+00E2 | A / a | A with circumflex |
| Î / î     | U+00CE / U+00EE | I / i | I with circumflex |

## Files Modified/Created

### Frontend
- ✅ Created: `apps/frontend/lib/utils/slug.ts` - Slug generation utilities
- ✅ Created: `apps/frontend/lib/utils/__tests__/slug.test.ts` - Unit tests
- ✅ Modified: `apps/frontend/app/[locale]/admin/categories/components/CategoryForm.tsx` - Use new slug utility

### Backend
- ✅ Created: `apps/backend/src/Service/RomanianSlugger.php` - Custom slugger service
- ✅ Created: `apps/backend/src/EventSubscriber/SluggableTransliteratorSubscriber.php` - Configure Gedmo
- ✅ Created: `apps/backend/src/Command/TestSlugGenerationCommand.php` - Test command
- ✅ Modified: `apps/backend/config/services.yaml` - Register RomanianSlugger service

### Documentation
- ✅ Created: `docs/SLUG_TRANSLITERATION.md` - This file

## Technical Notes

### Why Behat's `transliterate()` vs `urlize()`?

The Behat Transliterator library has two methods:

1. **`urlize($text)`**:
   - Uses `unaccent()` method
   - Has a hardcoded list of ~100 characters
   - **Missing** Romanian Ș/ș and Ț/ț
   - Result: "Știri" → "tiri" ❌

2. **`transliterate($text)`**:
   - Uses full UTF-8 to ASCII conversion tables (`data/x01.php`, `data/x02.php`, etc.)
   - Includes **all** Unicode blocks with proper mappings
   - **Includes** Romanian Ș/ș and Ț/ț in `data/x02.php`
   - Result: "Știri" → "stiri" ✅

We configure Gedmo to use `transliterate()` instead of the default `urlize()`.

### Unicode Blocks

Romanian characters are spread across two Unicode blocks:

- **Block 0x01** (Latin Extended-A):
  - Ă/ă (U+0102/U+0103)

- **Block 0x02** (Latin Extended-B):
  - Ș/ș (U+0218/U+0219) - Position 0x18/0x19 in array
  - Ț/ț (U+021A/U+021B) - Position 0x1A/0x1B in array

Behat's `transliterate()` correctly maps these from the data files:
- `vendor/behat/transliterator/src/Behat/Transliterator/data/x01.php`
- `vendor/behat/transliterator/src/Behat/Transliterator/data/x02.php`

## Future Improvements

- [ ] Add more comprehensive frontend tests with Jest
- [ ] Consider caching transliteration results for performance
- [ ] Add transliteration for other language-specific characters if needed
- [ ] Add admin UI option to customize slug generation rules

## References

- [Behat Transliterator GitHub](https://github.com/Behat/Transliterator)
- [Gedmo Doctrine Extensions](https://github.com/doctrine-extensions/DoctrineExtensions)
- [Romanian diacritics on Wikipedia](https://en.wikipedia.org/wiki/Romanian_alphabet#Diacritics)
- [Unicode Latin Extended-B](https://en.wikipedia.org/wiki/Latin_Extended-B)

---

**Status**: ✅ Implemented and tested (November 25, 2025)
**Tested with**: Symfony 7.3, Next.js 16, PHP 8.4, Node.js 22
