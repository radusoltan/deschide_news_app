# Multilanguage Model Documentation

## Overview

Deschide News implements multilingual content using **Gedmo Translatable Extension** for Doctrine ORM. This allows seamless translation of entities across multiple languages while maintaining a clean database schema and efficient queries.

---

## 🌍 Supported Languages

| Code | Language | Status | Primary Use |
|------|----------|--------|-------------|
| **ro** | Romanian | ✅ Default | Romania, Moldova |
| **en** | English | ✅ Active | International audience |
| **ru** | Russian | ✅ Active | Moldova, diaspora |

**Default Locale**: Romanian (`ro`)  
**Fallback**: Always falls back to Romanian if translation missing

---

## 🏗️ Architecture

### Gedmo Translatable Extension

**Bundle**: `stof/doctrine-extensions-bundle`  
**Extension**: `gedmo/doctrine-extensions`

**Key Features**:
- Transparent translation handling
- Separate translation table (`ext_translations`)
- Query hints for locale selection
- Automatic fallback mechanism
- Support for lazy/eager loading

### Configuration

**File**: `config/packages/stof_doctrine_extensions.yaml`

```yaml
stof_doctrine_extensions:
  default_locale: ro
  translation_fallback: true
  orm:
    default:
      translatable: true
      sluggable: true
      timestampable: true
```

---

## 📊 Database Schema

### Translation Storage

**Table**: `ext_translations`

```sql
CREATE TABLE ext_translations (
  id SERIAL PRIMARY KEY,
  locale VARCHAR(5) NOT NULL,               -- Language code (ro, en, ru)
  object_class VARCHAR(191) NOT NULL,       -- Entity class name
  field VARCHAR(32) NOT NULL,               -- Field name
  foreign_key VARCHAR(64) NOT NULL,         -- Entity ID
  content TEXT,                             -- Translated value
  created_at TIMESTAMP DEFAULT NOW(),
  updated_at TIMESTAMP DEFAULT NOW(),
  
  -- Unique constraint for lookups
  CONSTRAINT lookup_unique UNIQUE (locale, object_class, foreign_key, field)
);

-- Performance index
CREATE INDEX idx_translations_lookup 
ON ext_translations(locale, object_class, foreign_key, field);
```

### Example Data

```sql
-- Article ID 1 translations
| id | locale | object_class        | field   | foreign_key | content                    |
|----|--------|---------------------|---------|-------------|----------------------------|
| 1  | en     | App\Entity\Article  | title   | 1           | Breaking News Today        |
| 2  | ru     | App\Entity\Article  | title   | 1           | Срочные новости сегодня    |
| 3  | en     | App\Entity\Article  | lead    | 1           | This is the English lead   |
| 4  | ru     | App\Entity\Article  | lead    | 1           | Это русское резюме         |
| 5  | en     | App\Entity\Article  | slug    | 1           | breaking-news-today        |
| 6  | ru     | App\Entity\Article  | slug    | 1           | srochnye-novosti-segodnya  |

-- Category ID 5 translations
| 7  | en     | App\Entity\Category | name    | 5           | Politics                   |
| 8  | ru     | App\Entity\Category | name    | 5           | Политика                   |
```

---

## 🔧 Implementation

### Entity Annotation

**Example**: `src/Entity/Article.php`

```php
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;

#[ORM\Entity]
class Article implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Translatable field
    #[ORM\Column(length: 255)]
    #[Gedmo\Translatable]
    private ?string $title = null;

    // Translatable field
    #[ORM\Column(type: 'text')]
    #[Gedmo\Translatable]
    private ?string $lead = null;

    // Translatable field
    #[ORM\Column(type: 'text')]
    #[Gedmo\Translatable]
    private ?string $content = null;

    // Translatable slug (auto-generated from title)
    #[ORM\Column(length: 255)]
    #[Gedmo\Translatable]
    #[Gedmo\Slug(fields: ['title'], updatable: false)]
    private ?string $slug = null;

    // Non-translatable field
    #[ORM\Column(length: 50)]
    private ?string $status = null;

    // Locale field (not persisted to database)
    #[Gedmo\Locale]
    private ?string $locale = null;

    // Getters and setters
    public function setTranslatableLocale(string $locale): void
    {
        $this->locale = $locale;
    }
}
```

### Translatable Fields by Entity

#### Article
- ✅ `title` - Article headline
- ✅ `lead` - Summary/introduction
- ✅ `content` - Full article body
- ✅ `slug` - URL-friendly identifier
- ✅ `metaTitle` - SEO title
- ✅ `metaDescription` - SEO description
- ❌ `status` - Not translatable (same across locales)
- ❌ `publishedAt` - Not translatable
- ❌ `viewsCount` - Not translatable

#### Category
- ✅ `name` - Category name
- ✅ `description` - Category description
- ✅ `slug` - URL slug
- ❌ `parent` - Relationship, not translatable
- ❌ `position` - Not translatable

#### LiveText
- ✅ `title` - Event title
- ✅ `description` - Event description
- ❌ `status` - Not translatable
- ❌ `startTime` - Not translatable

#### LiveTextPost
- ✅ `content` - Post content
- ❌ `isPinned` - Not translatable
- ❌ `isBreaking` - Not translatable

---

## 🔍 Querying Translations

### API Platform State Provider

**File**: `src/State/ArticleProvider.php`

```php
use Gedmo\Translatable\TranslatableListener;

class ArticleProvider implements ProviderInterface
{
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): object|array|null {
        // Extract locale from Accept-Language header
        $locale = $context['filters']['locale'] ?? 'ro';
        
        // Build query
        $queryBuilder = $this->repository->createQueryBuilder('a');
        
        // Apply translatable locale hint
        $query = $queryBuilder->getQuery();
        $query->setHint(
            TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );
        
        // Optional: Use INNER JOIN to only return entities with translations
        $query->setHint(
            TranslatableListener::HINT_INNER_JOIN,
            true  // Strict mode - only return if translation exists
        );
        
        return $query->getResult();
    }
}
```

### Locale Detection

**Header-based**:
```http
GET /api/articles
Accept-Language: en

Response: Articles in English
```

**Query parameter**:
```http
GET /api/articles?locale=ru

Response: Articles in Russian
```

**API Platform Context**:
```php
// In State Provider
$locale = $context['filters']['locale'] 
    ?? $request->headers->get('Accept-Language') 
    ?? 'ro';
```

---

## 🔄 Translation Workflow

### Creating Translations

**Step 1: Create entity in default locale (Romanian)**

```php
$article = new Article();
$article->setTitle('Știri de ultimă oră');  // Romanian
$article->setLead('Acesta este rezumatul...');
$article->setContent('Conținut complet...');

$entityManager->persist($article);
$entityManager->flush();
// Article ID: 1, title saved in articles table
```

**Step 2: Add English translation**

```php
// Load article
$article = $repository->find(1);

// Set locale to English
$article->setTranslatableLocale('en');

// Set translated fields
$article->setTitle('Breaking News');
$article->setLead('This is the summary...');
$article->setContent('Full content...');

$entityManager->persist($article);
$entityManager->flush();
// Translation saved to ext_translations table
```

**Step 3: Add Russian translation**

```php
$article = $repository->find(1);
$article->setTranslatableLocale('ru');
$article->setTitle('Срочные новости');
$article->setLead('Это резюме...');
$article->setContent('Полный контент...');

$entityManager->persist($article);
$entityManager->flush();
```

### Retrieving Translations

**Get article in specific locale**:

```php
// Get article in English
$query = $repository->createQueryBuilder('a')
    ->where('a.id = :id')
    ->setParameter('id', 1)
    ->getQuery();

$query->setHint(TranslatableListener::HINT_TRANSLATABLE_LOCALE, 'en');
$article = $query->getSingleResult();

echo $article->getTitle();  // "Breaking News"
```

**Get article with fallback**:

```php
// Request Spanish (not available), falls back to Romanian
$query->setHint(TranslatableListener::HINT_TRANSLATABLE_LOCALE, 'es');
$article = $query->getSingleResult();

echo $article->getTitle();  // "Știri de ultimă oră" (Romanian default)
```

---

## 🚀 Performance Optimization

### Eager Loading Translations

```php
// Load translations for multiple articles at once
$query = $repository->createQueryBuilder('a')
    ->getQuery();

$query->setHint(TranslatableListener::HINT_TRANSLATABLE_LOCALE, 'en');
$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, TranslationWalker::class);

$articles = $query->getResult();
// All English translations loaded in single query
```

### Caching Strategy

**Redis Cache** (optional):
```php
// Cache translated article
$cacheKey = "article_{$id}_locale_{$locale}";
$cached = $redis->get($cacheKey);

if (!$cached) {
    $article = $this->getArticle($id, $locale);
    $redis->setex($cacheKey, 3600, serialize($article));
}
```

### Database Indexing

```sql
-- Already applied in schema
CREATE INDEX idx_translations_lookup 
ON ext_translations(locale, object_class, foreign_key, field);

-- Additional index for content search
CREATE INDEX idx_translations_content 
ON ext_translations USING gin(to_tsvector('english', content));
```

---

## 🌐 Frontend Integration

### API Request with Locale

**JavaScript/TypeScript**:
```typescript
async function fetchArticle(id: number, locale: string) {
  const response = await fetch(`${API_URL}/api/articles/${id}`, {
    headers: {
      'Accept-Language': locale,
      'Accept': 'application/ld+json'
    }
  });
  
  return response.json();
}

// Usage
const article = await fetchArticle(1, 'en');  // Get English version
```

### Language Switcher

**Next.js Example**:
```typescript
// components/LanguageSwitcher.tsx
export function LanguageSwitcher() {
  const router = useRouter();
  const { locale, pathname } = useParams();
  
  const switchLocale = (newLocale: string) => {
    // Maintain current page, change locale
    router.push(pathname, { locale: newLocale });
  };
  
  return (
    <div>
      <button onClick={() => switchLocale('ro')}>RO</button>
      <button onClick={() => switchLocale('en')}>EN</button>
      <button onClick={() => switchLocale('ru')}>RU</button>
    </div>
  );
}
```

---

## 🔧 Translation Management

### Console Commands

**Create translation**:
```bash
# Manual creation via SQL
symfony console doctrine:query:sql "
  INSERT INTO ext_translations (locale, object_class, field, foreign_key, content)
  VALUES ('en', 'App\\Entity\\Article', 'title', '1', 'English Title')
"
```

**Check missing translations**:
```bash
# Custom command (to be implemented)
symfony console app:translations:check-missing --locale=en
# Output: Article #5 missing English translation for: title, lead
```

**Import translations from CSV** (planned):
```bash
symfony console app:translations:import translations.csv --locale=ru
```

---

## 🐛 Troubleshooting

### Translation not showing

**Problem**: Requesting English article but getting Romanian

**Causes**:
1. Translation doesn't exist in `ext_translations`
2. Locale hint not applied to query
3. Fallback enabled (working as designed)

**Solution**:
```bash
# Check if translation exists
symfony console doctrine:query:sql "
  SELECT * FROM ext_translations 
  WHERE object_class = 'App\\Entity\\Article' 
  AND foreign_key = '1' 
  AND locale = 'en'
"

# If missing, create translation programmatically
```

### Performance issues

**Problem**: Slow queries when loading translations

**Solution**:
```php
// Use strict mode to skip missing translations
$query->setHint(TranslatableListener::HINT_INNER_JOIN, true);

// Limit fields fetched
$queryBuilder->select('a.id, a.title, a.slug');  // Partial select
```

### Slug conflicts across locales

**Problem**: Same slug in different languages

**Solution**: Slugs are locale-specific and stored separately:
- Romanian: `/ro/politica/articol-nou`
- English: `/en/politics/new-article`
- Russian: `/ru/politika/novaya-statya`

---

## 📊 Statistics

### Translation Coverage (Example Data)

| Entity | Total | Romanian | English | Russian |
|--------|-------|----------|---------|---------|
| Articles | 500 | 500 (100%) | 450 (90%) | 300 (60%) |
| Categories | 20 | 20 (100%) | 20 (100%) | 18 (90%) |
| LiveTexts | 50 | 50 (100%) | 40 (80%) | 25 (50%) |

**Target**: 100% translation coverage for all active content

---

## 🔮 Future Enhancements

### Planned Features
1. **Translation Admin UI**: In-app translation editor
2. **Translation Status Tracking**: Mark translations as "pending review", "approved"
3. **Machine Translation Integration**: DeepL API for auto-translation drafts
4. **Translation Memory**: Suggest previous translations for similar content
5. **Translation Workflow**: Assign translators, track progress
6. **Version Control**: Track translation history, rollback capability

### Under Consideration
- **More Languages**: Ukrainian, French, Spanish
- **Regional Variants**: ro-MD (Moldova) vs ro-RO (Romania)
- **Locale-specific Content**: Some articles only in certain languages

---

**Last Updated**: November 5, 2025  
**Version**: 1.0  
**Gedmo Version**: 3.x
