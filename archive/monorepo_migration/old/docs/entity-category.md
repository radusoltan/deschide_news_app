# Category Entity - Arhitectură Detaliată

Entitatea **Category** organizează articolele în categorii.

## Proprietăți

### Translatable Fields

| Proprietate | Tip | Validări | Descriere |
|------------|-----|----------|-----------|
| `title` | `string(255)` | Required, NotBlank, Length(max=255) | Numele categoriei |
| `slug` | `string(255)` | Required, Unique(locale+slug), Pattern(^[a-z0-9-]+$) | URL-friendly identifier, generat automat din title |

**Index Compus**: `UNIQUE INDEX idx_category_locale_slug ON category_translations (locale, slug)`

### Relationships

| Proprietate | Relație | Target Entity | Descriere |
|------------|---------|---------------|-----------|
| `articles` | `OneToMany` | `Article` | Lista de articole din această categorie |

### Status & Metadata

| Proprietate | Tip | Valori | Descriere |
|------------|-----|--------|-----------|
| `status` | `enum` | `Active`, `Inactive` | Starea categoriei (vizibilă/ascunsă) |
| `onFrontPage` | `boolean` | `true`, `false` (default: false) | Categorie afișată pe pagina principală |

### Timestamps

| Proprietate | Tip | Descriere |
|------------|-----|-----------|
| `createdAt` | `datetime` | Data creării (auto-set) |
| `updatedAt` | `datetime` | Data ultimei modificări (auto-update) |

### Computed Fields

| Proprietate | Tip | Descriere |
|------------|-----|-----------|
| `articleCount` | Computed | Număr de articole publicate în categorie |

## Enum Definitions

### CategoryStatus

```php
enum CategoryStatus: string
{
    case ACTIVE = 'active';      // Categorie activă, vizibilă
    case INACTIVE = 'inactive';  // Categorie inactivă, ascunsă
}
```

**Comportament:**
- `Active` → Categoria este vizibilă și poate avea articole publicate
- `Inactive` → Categoria este ascunsă; articolele existente rămân, dar categoria nu apare în navigare

## Business Rules

### Validări

1. **Title**:
   - Obligatoriu pentru fiecare limbă
   - Maxim 255 caractere
   - Trebuie să fie unic per limbă (via slug)

2. **Slug**:
   - Generat automat din `title` (slugify)
   - Pattern: doar litere mici, cifre și cratime
   - Unic per locale (poate fi același slug în limbi diferite pentru aceeași categorie)
   - Index compus: `(locale, slug)` UNIQUE

3. **Status**:
   - Default: `Active` la creare
   - Când o categorie devine `Inactive`:
     - Articolele rămân asociate, dar categoria nu apare în meniuri/navigare

4. **OnFrontPage**:
   - Default: `false`
   - Maximum 8-10 categorii pot fi `onFrontPage = true` (business constraint)
   - Folosit pentru a afișa categoriile principale în header/homepage


### Computed Properties

**Article Count:**
```php
public function getArticleCount(): int
{
    return $this->articles
        ->filter(fn($article) => $article->getStatus() === ArticleStatus::PUBLISHED)
        ->count();
}
```


## Database Schema

```sql
-- Tabelul principal (non-translatable)
CREATE TABLE category (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    on_front_page BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_status (status),
    INDEX idx_on_front_page (on_front_page)
);

-- Tabelul de traduceri (managed by Gedmo/KnpLabs)
CREATE TABLE category_translation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    translatable_id INT NOT NULL,
    locale VARCHAR(5) NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    FOREIGN KEY (translatable_id) REFERENCES category(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_category_locale_slug (locale, slug),
    INDEX idx_translatable (translatable_id, locale)
);
```

## Symfony Entity Example

```php
<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_status', columns: ['status'])]
#[ORM\Index(name: 'idx_on_front_page', columns: ['on_front_page'])]
class Category implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Translatable fields
    #[Gedmo\Translatable]
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[Gedmo\Translatable]
    #[Gedmo\Slug(fields: ['title'], unique: true, updatable: false)]
    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    // Relationships - Articles
    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Article::class)]
    private Collection $articles;

    // Status & Metadata
    #[ORM\Column(length: 20, enumType: CategoryStatus::class)]
    private CategoryStatus $status = CategoryStatus::ACTIVE;

    #[ORM\Column]
    private bool $onFrontPage = false;

    // Timestamps
    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    // For translations
    #[Gedmo\Locale]
    private ?string $locale = null;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
    }

    // Getters and setters...

    public function getArticleCount(): int
    {
        return $this->articles
            ->filter(fn($article) => $article->getStatus() === ArticleStatus::PUBLISHED)
            ->count();
    }
}
```

## API Response Example

### Category List Response

```json
{
  "id": 5,
  "title": "Politics",
  "slug": "politics",
  "status": "active",
  "onFrontPage": true,
  "articleCount": 42,
  "createdAt": "2025-01-10T10:00:00Z",
  "updatedAt": "2025-01-15T14:30:00Z",
  "locale": "ro"
}
```

### Category Detail Response (with Recent Articles)

```json
{
  "id": 5,
  "title": "Politics",
  "slug": "politics",
  "status": "active",
  "onFrontPage": true,
  "articleCount": 42,
  "recentArticles": [
    {
      "id": 123,
      "title": "Breaking Political News",
      "slug": "breaking-political-news",
      "lead": "Brief summary...",
      "publishedAt": "2025-01-15T12:00:00Z"
    }
  ],
  "locale": "ro"
}
```

## Query Examples

### Categorii Active de pe Front Page

```php
$frontPageCategories = $categoryRepository->createQueryBuilder('c')
    ->where('c.status = :status')
    ->andWhere('c.onFrontPage = true')
    ->setParameter('status', CategoryStatus::ACTIVE)
    ->orderBy('c.title', 'ASC')
    ->getQuery()
    ->getResult();
```

### Toate Categoriile Active

```php
$activeCategories = $categoryRepository->createQueryBuilder('c')
    ->where('c.status = :status')
    ->setParameter('status', CategoryStatus::ACTIVE)
    ->orderBy('c.title', 'ASC')
    ->getQuery()
    ->getResult();
```

### Categorii cu Număr de Articole (Join Optimized)

```php
$categoriesWithCount = $categoryRepository->createQueryBuilder('c')
    ->select('c', 'COUNT(a.id) as articleCount')
    ->leftJoin('c.articles', 'a', 'WITH', 'a.status = :published')
    ->where('c.status = :active')
    ->setParameter('published', ArticleStatus::PUBLISHED)
    ->setParameter('active', CategoryStatus::ACTIVE)
    ->groupBy('c.id')
    ->orderBy('articleCount', 'DESC')
    ->getQuery()
    ->getResult();
```

## Exemple Categorii

### Categorii Tipice pentru Portal de Știri

```
- Politics (onFrontPage=true)
- Sports (onFrontPage=true)
- Technology (onFrontPage=true)
- Business (onFrontPage=true)
- Entertainment (onFrontPage=true)
- Health (onFrontPage=true)
- Science (onFrontPage=true)
- Culture (onFrontPage=true)
```

**Note**: Categoriile sunt simple, fără ierarhie. Fiecare categorie este independentă.

## Considerații

### Performance

- **Eager Loading**: Pentru liste cu articole, folosește JOIN pe `articles`
- **Lazy Loading**: `articles` se încarcă doar când sunt necesare în detaliu
- **Caching**: Lista de categorii poate fi cache-uită (raramente se schimbă)
- **Index-uri**: Pe `status`, `onFrontPage`

### UX Recommendations

- **Max Front Page Categories**: 8-10 categorii pentru navigare principală
- **Sorting**: Sortează alfabetic sau custom order (poate adăuga câmp `position`)
- **Mobile**: Afișează categoriile într-un meniu hamburger sau slider horizontal

### SEO

- **Slug**: URL-friendly, unic per limbă
- **Canonical URL**: `/{locale}/categorie/{slug}`
- **Category Pages**: Fiecare categorie are propria pagină cu articole
- **Schema.org**: Folosește CollectionPage schema pentru pagini de categorie

### Migration Strategy

Când ștergi o categorie cu articole:
```php
// Option 1: Mută articolele la categoria părinte sau la "Uncategorized"
$uncategorized = $categoryRepository->findOneBy(['slug' => 'uncategorized']);
foreach ($category->getArticles() as $article) {
    $article->setCategory($uncategorized);
}

// Option 2: Previne ștergerea dacă are articole
if ($category->getArticles()->count() > 0) {
    throw new \LogicException('Cannot delete category with existing articles');
}
```
