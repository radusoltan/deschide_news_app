# Author Entity - Arhitectură Detaliată

Entitatea **Author** reprezintă un autor de articole în sistem.

## Proprietăți

### Basic Information

| Proprietate | Tip | Validări | Descriere |
|------------|-----|----------|-----------|
| `firstName` | `string(100)` | Required, NotBlank, Length(max=100) | Prenumele autorului |
| `lastName` | `string(100)` | Required, NotBlank, Length(max=100) | Numele de familie |
| `email` | `string(180)` | Required, Email, Unique | Email-ul autorului (unic în sistem) |
| `slug` | `string(255)` | Required, Unique, Pattern(^[a-z0-9-]+$) | URL-friendly identifier |

### Translatable Fields

| Proprietate | Tip | Validări | Descriere |
|------------|-----|----------|-----------|
| `bio` | `text` | Optional, Length(max=2000) | Biografia autorului |

### Relationships

| Proprietate | Relație | Target Entity | Descriere |
|------------|---------|---------------|-----------|
| `articles` | `ManyToMany` | `Article` | Lista de articole scrise de autor |
| `profileImage` | `OneToOne` | `Image` | Imaginea de profil a autorului |

### Metadata

| Proprietate | Tip | Valori | Descriere |
|------------|-----|--------|-----------|
| `status` | `enum` | `Active`, `Inactive` | Starea autorului (activ/inactiv) |
| `isActive` | `boolean` | `true`, `false` (default: true) | Autor activ în sistem |

### Social Media (Optional)

| Proprietate | Tip | Validări | Descriere |
|------------|-----|----------|-----------|
| `twitter` | `string(100)` | Optional, Length(max=100) | Username Twitter (fără @) |
| `facebook` | `string(255)` | Optional, Url | URL profil Facebook |
| `linkedin` | `string(255)` | Optional, Url | URL profil LinkedIn |
| `website` | `string(255)` | Optional, Url | Website personal |

### Timestamps

| Proprietate | Tip | Descriere |
|------------|-----|-----------|
| `createdAt` | `datetime` | Data creării (auto-set) |
| `updatedAt` | `datetime` | Data ultimei modificări (auto-update) |

### Computed Fields

| Proprietate | Tip | Descriere |
|------------|-----|-----------|
| `fullName` | Computed | Nume complet (firstName + lastName) |
| `articleCount` | Computed | Număr de articole publicate |
| `initials` | Computed | Inițiale (ex: "JD" pentru John Doe) |

## Enum Definitions

### AuthorStatus

```php
enum AuthorStatus: string
{
    case ACTIVE = 'active';      // Autor activ, poate scrie articole
    case INACTIVE = 'inactive';  // Autor inactiv, articolele rămân vizibile
}
```

## Business Rules

### Validări

1. **FirstName & LastName**:
   - Ambele obligatorii
   - Maxim 100 caractere fiecare
   - Validare: nu doar spații

2. **Email**:
   - Format email valid
   - Unic în sistem (un autor = un email)
   - Folosit pentru comunicare/notificări

3. **Slug**:
   - Generat automat din `firstName + lastName`
   - Pattern: doar lowercase, cifre și cratime
   - Unic în sistem
   - Ex: "john-doe", "jane-smith"
   - Poate fi editat manual dacă e nevoie (pentru conflicte)

4. **Bio**:
   - Optional dar recomandat
   - Maxim 2000 caractere
   - Translatable (diferit per limbă)
   - Rich text minimal (permite formatare simplă)

5. **ProfileImage**:
   - Optional
   - OneToOne cu Image (image.author !== null)
   - Thumbnail profiles: avatar_small, avatar_medium, avatar_large

6. **Articles**:
   - ManyToMany cu Article
   - Un autor poate avea 0 sau mai multe articole
   - Artcolele rămân când autorul devine inactive

7. **Status/IsActive**:
   - Default: Active/true la creare
   - Inactive → autorul nu mai apare în UI de creare articole
   - Articolele existente rămân publicate cu numele autorului

8. **Social Media**:
   - Toate opționale
   - Twitter: doar username (fără @)
   - Altele: URL complet valid

### Computed Properties

**Full Name:**
```php
public function getFullName(): string
{
    return trim($this->firstName . ' ' . $this->lastName);
}
```

**Article Count:**
```php
public function getArticleCount(): int
{
    return $this->articles
        ->filter(fn($article) => $article->getStatus() === ArticleStatus::PUBLISHED)
        ->count();
}
```

**Initials:**
```php
public function getInitials(): string
{
    $first = mb_substr($this->firstName, 0, 1);
    $last = mb_substr($this->lastName, 0, 1);
    return mb_strtoupper($first . $last);
}
```

## Database Schema

```sql
-- Tabelul principal (non-translatable)
CREATE TABLE author (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profile_image_id INT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    slug VARCHAR(255) NOT NULL UNIQUE,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    twitter VARCHAR(100) NULL,
    facebook VARCHAR(255) NULL,
    linkedin VARCHAR(255) NULL,
    website VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (profile_image_id) REFERENCES image(id) ON DELETE SET NULL,
    INDEX idx_slug (slug),
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_is_active (is_active)
);

-- Tabelul de traduceri (managed by Gedmo/KnpLabs)
CREATE TABLE author_translation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    translatable_id INT NOT NULL,
    locale VARCHAR(5) NOT NULL,
    bio TEXT NULL,
    FOREIGN KEY (translatable_id) REFERENCES author(id) ON DELETE CASCADE,
    INDEX idx_translatable (translatable_id, locale)
);

-- ManyToMany cu Article (deja definit în article_author)
```

## Symfony Entity Example

```php
<?php

namespace App\Entity;

use App\Repository\AuthorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: AuthorRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity('email')]
#[UniqueEntity('slug')]
#[ORM\Index(name: 'idx_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_email', columns: ['email'])]
#[ORM\Index(name: 'idx_status', columns: ['status'])]
#[ORM\Index(name: 'idx_is_active', columns: ['is_active'])]
class Author implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Basic Information
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[Gedmo\Slug(fields: ['firstName', 'lastName'], unique: true, updatable: true)]
    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    // Translatable fields
    #[Gedmo\Translatable]
    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $bio = null;

    // Relationships
    #[ORM\ManyToMany(targetEntity: Article::class, mappedBy: 'authors')]
    private Collection $articles;

    #[ORM\OneToOne(targetEntity: Image::class, inversedBy: 'author')]
    #[ORM\JoinColumn(nullable: true, unique: true, onDelete: 'SET NULL')]
    private ?Image $profileImage = null;

    // Metadata
    #[ORM\Column(length: 20, enumType: AuthorStatus::class)]
    private AuthorStatus $status = AuthorStatus::ACTIVE;

    #[ORM\Column]
    private bool $isActive = true;

    // Social Media
    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $twitter = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    private ?string $facebook = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    private ?string $linkedin = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    private ?string $website = null;

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

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getArticleCount(): int
    {
        return $this->articles
            ->filter(fn($article) => $article->getStatus() === ArticleStatus::PUBLISHED)
            ->count();
    }

    public function getInitials(): string
    {
        $first = mb_substr($this->firstName, 0, 1);
        $last = mb_substr($this->lastName, 0, 1);
        return mb_strtoupper($first . $last);
    }

    public function getAvatarUrl(string $size = 'medium'): ?string
    {
        if (!$this->profileImage) {
            return null;
        }

        $profileName = 'avatar_' . $size; // avatar_small, avatar_medium, avatar_large
        $thumbnail = $this->profileImage->getThumbnailByProfile($profileName);

        return $thumbnail ? $thumbnail->getUrl() : null;
    }
}
```

## API Response Example

### Author List Item

```json
{
  "id": 10,
  "firstName": "John",
  "lastName": "Doe",
  "fullName": "John Doe",
  "slug": "john-doe",
  "email": "john.doe@example.com",
  "bio": "Experienced technology journalist with 10 years in the field.",
  "profileImage": {
    "url": "/media/uploads/images/2025/01/author-profile.jpg",
    "thumbnails": {
      "avatar_small": "/media/thumbnails/avatar_small/author-profile.jpg",
      "avatar_medium": "/media/thumbnails/avatar_medium/author-profile.jpg",
      "avatar_large": "/media/thumbnails/avatar_large/author-profile.jpg"
    }
  },
  "social": {
    "twitter": "johndoe",
    "facebook": "https://facebook.com/johndoe",
    "linkedin": "https://linkedin.com/in/johndoe",
    "website": "https://johndoe.com"
  },
  "articleCount": 42,
  "status": "active",
  "isActive": true,
  "createdAt": "2024-01-01T00:00:00Z",
  "updatedAt": "2025-01-15T10:00:00Z",
  "locale": "ro"
}
```

### Author Detail (with Recent Articles)

```json
{
  "id": 10,
  "firstName": "John",
  "lastName": "Doe",
  "fullName": "John Doe",
  "initials": "JD",
  "slug": "john-doe",
  "email": "john.doe@example.com",
  "bio": "Experienced technology journalist with 10 years in the field.",
  "profileImage": {
    "id": 85,
    "url": "/media/uploads/images/2025/01/author-profile.jpg",
    "alt": "John Doe profile photo",
    "thumbnails": {
      "avatar_small": {
        "url": "/media/thumbnails/avatar_small/author-profile.jpg",
        "width": 50,
        "height": 50
      },
      "avatar_medium": {
        "url": "/media/thumbnails/avatar_medium/author-profile.jpg",
        "width": 150,
        "height": 150
      },
      "avatar_large": {
        "url": "/media/thumbnails/avatar_large/author-profile.jpg",
        "width": 400,
        "height": 400
      }
    }
  },
  "social": {
    "twitter": "johndoe",
    "facebook": "https://facebook.com/johndoe",
    "linkedin": "https://linkedin.com/in/johndoe",
    "website": "https://johndoe.com"
  },
  "articleCount": 42,
  "status": "active",
  "isActive": true,
  "recentArticles": [
    {
      "id": 123,
      "title": "Breaking Tech News",
      "slug": "breaking-tech-news",
      "lead": "Brief summary...",
      "publishedAt": "2025-01-15T12:00:00Z"
    },
    {
      "id": 120,
      "title": "AI Revolution Continues",
      "slug": "ai-revolution-continues",
      "lead": "Another brief summary...",
      "publishedAt": "2025-01-14T10:00:00Z"
    }
  ],
  "createdAt": "2024-01-01T00:00:00Z",
  "updatedAt": "2025-01-15T10:00:00Z",
  "locale": "ro"
}
```

## Query Examples

### Autori Activi

```php
$activeAuthors = $authorRepository->createQueryBuilder('a')
    ->where('a.isActive = true')
    ->andWhere('a.status = :status')
    ->setParameter('status', AuthorStatus::ACTIVE)
    ->orderBy('a.lastName', 'ASC')
    ->addOrderBy('a.firstName', 'ASC')
    ->getQuery()
    ->getResult();
```

### Autori cu Articole Publicate

```php
$authorsWithArticles = $authorRepository->createQueryBuilder('a')
    ->select('a', 'COUNT(art.id) as articleCount')
    ->leftJoin('a.articles', 'art', 'WITH', 'art.status = :published')
    ->where('a.isActive = true')
    ->setParameter('published', ArticleStatus::PUBLISHED)
    ->groupBy('a.id')
    ->having('COUNT(art.id) > 0')
    ->orderBy('articleCount', 'DESC')
    ->getQuery()
    ->getResult();
```

### Autor by Slug

```php
$author = $authorRepository->createQueryBuilder('a')
    ->where('a.slug = :slug')
    ->setParameter('slug', 'john-doe')
    ->leftJoin('a.profileImage', 'img')
    ->addSelect('img')
    ->leftJoin('img.thumbnails', 't')
    ->addSelect('t')
    ->getQuery()
    ->getOneOrNullResult();
```

### Top Autori (Most Articles)

```php
$topAuthors = $authorRepository->createQueryBuilder('a')
    ->select('a', 'COUNT(art.id) as HIDDEN articleCount')
    ->leftJoin('a.articles', 'art', 'WITH', 'art.status = :published')
    ->where('a.isActive = true')
    ->setParameter('published', ArticleStatus::PUBLISHED)
    ->groupBy('a.id')
    ->orderBy('articleCount', 'DESC')
    ->setMaxResults(10)
    ->getQuery()
    ->getResult();
```

## Frontend Examples

### Author Card Component (React)

```typescript
interface AuthorCardProps {
  author: {
    id: number;
    fullName: string;
    slug: string;
    bio: string;
    articleCount: number;
    profileImage?: {
      thumbnails: {
        avatar_medium: string;
      };
    };
  };
}

function AuthorCard({ author }: AuthorCardProps) {
  return (
    <Link href={`/autor/${author.slug}`} className="author-card">
      <div className="avatar">
        {author.profileImage ? (
          <img
            src={author.profileImage.thumbnails.avatar_medium}
            alt={author.fullName}
            width={150}
            height={150}
          />
        ) : (
          <div className="avatar-placeholder">
            {getInitials(author.fullName)}
          </div>
        )}
      </div>
      <div className="info">
        <h3>{author.fullName}</h3>
        <p className="bio">{truncate(author.bio, 120)}</p>
        <span className="article-count">
          {author.articleCount} articole
        </span>
      </div>
    </Link>
  );
}
```

### Author Byline Component

```typescript
function ArticleByline({ authors }: { authors: Author[] }) {
  return (
    <div className="byline">
      <span>de</span>
      {authors.map((author, index) => (
        <span key={author.id}>
          <Link href={`/autor/${author.slug}`}>
            {author.profileImage && (
              <img
                src={author.profileImage.thumbnails.avatar_small}
                alt={author.fullName}
                width={30}
                height={30}
              />
            )}
            <span>{author.fullName}</span>
          </Link>
          {index < authors.length - 1 && <span>, </span>}
        </span>
      ))}
    </div>
  );
}
```

## Considerații

### Performance

- **Eager Loading**: Încarcă profileImage cu thumbnails pentru liste de autori
- **Lazy Loading**: articles se încarcă doar când sunt necesare
- **Caching**: Cache lista de autori activi (se schimbă rar)
- **Index-uri**: Pe `slug`, `email`, `status`, `isActive`

### UX

- **Avatar Fallback**: Dacă nu există profileImage, afișează inițiale în cerc colorat
- **Author Pages**: Fiecare autor are propria pagină cu articole și bio
- **Social Links**: Afișează iconițe pentru social media links

### SEO

- **Slug**: URL-friendly, unic
- **Canonical URL**: `/{locale}/autor/{slug}`
- **Schema.org**: Folosește Person schema pentru autori
- **Rel="author"**: Link către pagina autorului din articole

### Security

- **Email Privacy**: Nu expune email-ul în API publice (doar în admin)
- **Social Validation**: Validează URL-uri de social media

### Management

- **Inactive Authors**: Când un autor pleacă, setează `isActive = false`
- **Merge Authors**: Implementează funcționalitate de merge pentru duplicate
- **Author Statistics**: Dashboard cu statistici per autor (articole, views, etc.)

### Migration Strategy

```php
// Transfer articole de la un autor la altul
public function transferArticles(Author $from, Author $to): void
{
    foreach ($from->getArticles() as $article) {
        $article->removeAuthor($from);
        $article->addAuthor($to);
    }

    $this->entityManager->flush();
}

// Merge autori duplicați
public function mergeAuthors(Author $duplicate, Author $primary): void
{
    // Transfer articole
    $this->transferArticles($duplicate, $primary);

    // Marchează duplicatul ca inactive
    $duplicate->setIsActive(false);
    $duplicate->setStatus(AuthorStatus::INACTIVE);

    $this->entityManager->flush();
}
```
