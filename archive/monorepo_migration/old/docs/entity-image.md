# Image Entity - Arhitectură Detaliată

Entitatea **Image** reprezintă o imagine uploadată în sistem (pentru articole sau profile autori).

## Proprietăți

### File Information

| Proprietate | Tip | Validări | Descriere |
|------------|-----|----------|-----------|
| `filename` | `string(255)` | Required, NotBlank | Numele fișierului (generat unic) |
| `originalFilename` | `string(255)` | Required, NotBlank | Numele original al fișierului uploadat |
| `path` | `string(500)` | Required, NotBlank | Calea relativă către fișier |
| `mimeType` | `string(100)` | Required, Pattern(^image/) | MIME type (image/jpeg, image/png, etc.) |
| `size` | `integer` | Required, Range(min=1, max=10485760) | Dimensiune fișier în bytes (max 10MB) |
| `width` | `integer` | Required, Range(min=1) | Lățimea imaginii în pixeli |
| `height` | `integer` | Required, Range(min=1) | Înălțimea imaginii în pixeli |

### Translatable Fields

| Proprietate | Tip | Validări | Descriere |
|------------|-----|----------|-----------|
| `alt` | `string(255)` | Optional, Length(max=255) | Text alternativ (pentru accessibility și SEO) |
| `caption` | `text` | Optional, Length(max=500) | Descriere/caption pentru imagine |
| `description` | `text` | Optional, Length(max=1000) | Descrierea imaginii (detalii suplimentare) |

### Relationships

| Proprietate | Relație | Target Entity | Descriere |
|------------|---------|---------------|-----------|
| `articleImages` | `OneToMany` | `ArticleImage` | Relațiile cu articole (pivot table) |
| `author` | `OneToOne` | `Author` | Autorul pentru care este imagine de profil (nullable pentru imagini de articol) |
| `thumbnails` | `OneToMany` | `Thumbnail` | Lista de thumbnails generate pentru această imagine |

### Metadata

| Proprietate   | Tip        | Descriere                                                |
|---------------|------------|----------------------------------------------------------|
| `imageAuthor` | `string(255)` | Autorul/sursa/credite imagine (ex: "Reuters", "John Doe") |
| `createdAt`   | `datetime` | Data upload-ului (auto-set)                              |
| `updatedAt`   | `datetime` | Data ultimei modificări (auto-update)                    |

### Computed Fields

| Proprietate | Tip | Descriere |
|------------|-----|-----------|
| `aspectRatio` | Computed | Aspect ratio calculat (width/height) |
| `formattedSize` | Computed | Dimensiune formatată human-readable (ex: "2.5 MB") |
| `isProfileImage` | Computed | True dacă este imagine de profil (author !== null) |

## Business Rules

### Validări

1. **Filename**:
   - Generat automat la upload (UUID + extensie)
   - Pattern: `{uuid}.{extension}` (ex: `a3b2c1d4-e5f6.jpg`)
   - Unic în sistem

2. **OriginalFilename**:
   - Păstrează numele original pentru referință
   - Sanitizat pentru securitate

3. **Path**:
   - Structură: `uploads/images/{year}/{month}/{filename}`
   - Ex: `uploads/images/2025/01/a3b2c1d4-e5f6.jpg`
   - Permite organizare pe disk și CDN

4. **MimeType**:
   - Doar imagini permise: `image/jpeg`, `image/png`, `image/webp`, `image/gif`
   - Validat server-side (nu doar extensie)

5. **Size**:
   - Maximum 10MB per imagine
   - Business constraint poate fi ajustat

6. **Dimensions (Width/Height)**:
   - Minimum 100x100 px recomandat
   - Maximum 8000x8000 px (business constraint)
   - Extrase automat la upload

7. **Alt Text**:
   - Optional dar recomandat (SEO + accessibility)
   - Translatable (diferit per limbă)

8. **ArticleImages (Pivot Table)**:
   - Relația ManyToMany cu Article se gestionează prin tabelul pivot `article_images`
   - Pivot table conține: `position`, `isFeatured`
   - O imagine poate fi atașată la multiple articole (refolosire imagini)

9. **Author (Profile Image)**:
   - OneToOne cu Author pentru imagini de profil
   - Imagine nu poate fi și ArticleImage și ProfileImage simultan (business rule)
   - Dacă author !== null, imaginea este exclusiv pentru profil

### Computed Properties

**Aspect Ratio:**
```php
public function getAspectRatio(): float
{
    return $this->height > 0 ? round($this->width / $this->height, 2) : 0;
}
```

**Formatted Size:**

```php
public function getFormattedSize(): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $size = $this->size;
    $unit = 0;

    while ($size >= 1024 && $unit < count($units) - 1) {
        $size /= 1024;
        $unit++;
    }

    return round($size, 2) . ' entity-image.md' . $units[$unit];
}
```

**Is Profile Image:**
```php
public function isProfileImage(): bool
{
    return $this->author !== null;
}
```

### Automatic Thumbnail Generation

La upload, se generează automat thumbnails pentru toate **ThumbnailProfiles** active:
```php
public function generateThumbnails(ThumbnailProfileRepository $profileRepo): void
{
    $profiles = $profileRepo->findBy(['isActive' => true]);

    foreach ($profiles as $profile) {
        $thumbnail = new Thumbnail();
        $thumbnail->setImage($this);
        $thumbnail->setProfile($profile);
        $thumbnail->generate(); // Generare fizică + salvare metadata

        $this->thumbnails->add($thumbnail);
    }
}
```

## Database Schema

```sql
-- Tabelul principal (non-translatable)
CREATE TABLE image (
    id INT AUTO_INCREMENT PRIMARY KEY,
    author_id INT NULL,
    filename VARCHAR(255) NOT NULL UNIQUE,
    original_filename VARCHAR(255) NOT NULL,
    path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size INT NOT NULL,
    width INT NOT NULL,
    height INT NOT NULL,
    image_author VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (author_id) REFERENCES author(id) ON DELETE CASCADE,
    INDEX idx_author (author_id),
    INDEX idx_filename (filename)
);

-- Tabelul pivot Article-Image (ManyToMany cu metadata)
CREATE TABLE article_image (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    image_id INT NOT NULL,
    position INT NOT NULL DEFAULT 0,
    is_featured BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE,
    FOREIGN KEY (image_id) REFERENCES image(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_article_image (article_id, image_id),
    INDEX idx_article (article_id),
    INDEX idx_image (image_id),
    INDEX idx_position (article_id, position),
    INDEX idx_featured (article_id, is_featured)
);

-- Tabelul de traduceri (managed by Gedmo/KnpLabs)
CREATE TABLE image_translation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    translatable_id INT NOT NULL,
    locale VARCHAR(5) NOT NULL,
    alt VARCHAR(255) NULL,
    caption TEXT NULL,
    FOREIGN KEY (translatable_id) REFERENCES image(id) ON DELETE CASCADE,
    INDEX idx_translatable (translatable_id, locale)
);
```

## Symfony Entity Example

```php
<?php

namespace App\Entity;

use App\Repository\ImageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ImageRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity('filename')]
#[ORM\Index(name: 'idx_article', columns: ['article_id'])]
#[ORM\Index(name: 'idx_author', columns: ['author_id'])]
#[ORM\Index(name: 'idx_filename', columns: ['filename'])]
#[ORM\Index(name: 'idx_position', columns: ['position'])]
class Image implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // File Information
    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    private ?string $filename = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $originalFilename = null;

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    private ?string $path = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^image\//')]
    private ?string $mimeType = null;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 10485760)]
    private ?int $size = null;

    #[ORM\Column]
    #[Assert\Range(min: 1)]
    private ?int $width = null;

    #[ORM\Column]
    #[Assert\Range(min: 1)]
    private ?int $height = null;

    // Translatable fields
    #[Gedmo\Translatable]
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $alt = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $caption = null;

    // Relationships
    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Article $article = null;

    #[ORM\OneToOne(targetEntity: Author::class, mappedBy: 'profileImage')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Author $author = null;

    #[ORM\OneToMany(mappedBy: 'image', targetEntity: Thumbnail::class, cascade: ['persist', 'remove'])]
    private Collection $thumbnails;

    // Metadata
    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $isFeatured = false;

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
        $this->thumbnails = new ArrayCollection();
        $this->articleImages = new ArrayCollection();
    }

    // Getters and setters...

    public function getAspectRatio(): float
    {
        return $this->height > 0 ? round($this->width / $this->height, 2) : 0;
    }

    public function getFormattedSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    public function isProfileImage(): bool
    {
        return $this->author !== null;
    }

    public function isUsedInArticles(): bool
    {
        return $this->articleImages->count() > 0;
    }

    public function getUsageCount(): int
    {
        return $this->articleImages->count();
    }

    public function getArticles(): Collection
    {
        return $this->articleImages->map(fn($ai) => $ai->getArticle());
    }

    public function getThumbnailByProfile(string $profileName): ?Thumbnail
    {
        foreach ($this->thumbnails as $thumbnail) {
            if ($thumbnail->getProfile()->getName() === $profileName) {
                return $thumbnail;
            }
        }

        return null;
    }
}
```

## API Response Example

### Image for Article

```json
{
  "id": 50,
  "filename": "a3b2c1d4-e5f6.jpg",
  "originalFilename": "breaking-news-photo.jpg",
  "path": "uploads/images/2025/01/a3b2c1d4-e5f6.jpg",
  "mimeType": "image/jpeg",
  "size": 2621440,
  "formattedSize": "2.5 MB",
  "width": 1920,
  "height": 1080,
  "aspectRatio": 1.78,
  "alt": "Breaking news event photograph",
  "caption": "Scene from the breaking news event",
  "position": 0,
  "isFeatured": true,
  "isProfileImage": false,
  "thumbnails": {
    "small_square": {
      "url": "/media/thumbnails/small_square/a3b2c1d4-e5f6.jpg",
      "width": 150,
      "height": 150
    },
    "medium_16_9": {
      "url": "/media/thumbnails/medium_16_9/a3b2c1d4-e5f6.jpg",
      "width": 640,
      "height": 360
    },
    "large_16_9": {
      "url": "/media/thumbnails/large_16_9/a3b2c1d4-e5f6.jpg",
      "width": 1920,
      "height": 1080
    },
    "og_image": {
      "url": "/media/thumbnails/og_image/a3b2c1d4-e5f6.jpg",
      "width": 1200,
      "height": 630
    }
  },
  "createdAt": "2025-01-15T10:30:00Z",
  "updatedAt": "2025-01-15T10:30:00Z",
  "locale": "ro"
}
```

### Image for Author Profile

```json
{
  "id": 85,
  "filename": "b7c8d9e0-f1a2.jpg",
  "originalFilename": "john-doe-avatar.jpg",
  "mimeType": "image/jpeg",
  "size": 524288,
  "formattedSize": "512 KB",
  "width": 800,
  "height": 800,
  "aspectRatio": 1.0,
  "alt": "John Doe profile photo",
  "isProfileImage": true,
  "thumbnails": {
    "avatar_small": {
      "url": "/media/thumbnails/avatar_small/b7c8d9e0-f1a2.jpg",
      "width": 50,
      "height": 50
    },
    "avatar_medium": {
      "url": "/media/thumbnails/avatar_medium/b7c8d9e0-f1a2.jpg",
      "width": 150,
      "height": 150
    },
    "avatar_large": {
      "url": "/media/thumbnails/avatar_large/b7c8d9e0-f1a2.jpg",
      "width": 400,
      "height": 400
    }
  },
  "createdAt": "2025-01-10T08:00:00Z",
  "locale": "ro"
}
```

## Upload Flow

### 1. Client Upload Request

```http
POST /api/admin/images/upload
Content-Type: multipart/form-data

file: <binary>
type: "article" | "profile"
articleId: 123 (optional, for article images)
authorId: 45 (optional, for profile images)
```

### 2. Server Processing

```php
public function uploadImage(UploadedFile $file, string $type, ?int $articleId, ?int $authorId): Image
{
    // 1. Validate file
    $this->validateImage($file);

    // 2. Generate unique filename
    $filename = Uuid::v4() . '.' . $file->guessExtension();

    // 3. Determine storage path
    $path = sprintf('uploads/images/%s/%s/%s',
        date('Y'),
        date('m'),
        $filename
    );

    // 4. Extract image dimensions
    $imageInfo = getimagesize($file->getPathname());
    [$width, $height] = $imageInfo;

    // 5. Move file to storage
    $file->move($this->getUploadDir(), $path);

    // 6. Create Image entity
    $image = new Image();
    $image->setFilename($filename);
    $image->setOriginalFilename($file->getClientOriginalName());
    $image->setPath($path);
    $image->setMimeType($file->getMimeType());
    $image->setSize($file->getSize());
    $image->setWidth($width);
    $image->setHeight($height);

    // 7. Associate with Article or Author
    if ($type === 'article' && $articleId) {
        $article = $this->articleRepository->find($articleId);
        $image->setArticle($article);
    } elseif ($type === 'profile' && $authorId) {
        $author = $this->authorRepository->find($authorId);
        $image->setAuthor($author);
    }

    // 8. Generate thumbnails
    $this->thumbnailService->generateThumbnails($image);

    // 9. Persist
    $this->entityManager->persist($image);
    $this->entityManager->flush();

    return $image;
}
```

## Query Examples

### Imagini pentru un Articol

```php
$images = $imageRepository->createQueryBuilder('i')
    ->where('i.article = :article')
    ->setParameter('article', $article)
    ->orderBy('i.position', 'ASC')
    ->getQuery()
    ->getResult();
```

### Featured Image pentru Articol

```php
$featuredImage = $imageRepository->createQueryBuilder('i')
    ->where('i.article = :article')
    ->andWhere('i.isFeatured = true')
    ->setParameter('article', $article)
    ->setMaxResults(1)
    ->getQuery()
    ->getOneOrNullResult();

// Fallback: prima imagine
if (!$featuredImage) {
    $featuredImage = $imageRepository->createQueryBuilder('i')
        ->where('i.article = :article')
        ->setParameter('article', $article)
        ->orderBy('i.position', 'ASC')
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
}
```

### Imagini Orfane (Cleanup)

```php
$orphanImages = $imageRepository->createQueryBuilder('i')
    ->where('i.article IS NULL')
    ->andWhere('i.author IS NULL')
    ->andWhere('i.createdAt < :threshold')
    ->setParameter('threshold', new \DateTime('-7 days'))
    ->getQuery()
    ->getResult();

// Delete orphans
foreach ($orphanImages as $image) {
    $this->deleteImage($image);
}
```

## Considerații

### Performance

- **Lazy Loading**: Thumbnails se încarcă on-demand
- **Eager Loading**: Pentru liste de articole cu imagini, folosește JOIN
- **CDN**: Servește imaginile prin CDN pentru performanță
- **Index-uri**: Pe `article_id`, `author_id`, `filename`, `position`

### Storage

- **Local**: Pentru development
- **Cloud Storage**: S3, Cloudinary, etc. pentru production
- **Backup**: Backup regulat pentru uploads/images/

### Security

- **Validation**: Verifică MIME type server-side (nu doar extensie)
- **Sanitization**: Sanitizează filename-ul original
- **Max Size**: Limită upload size (10MB default)
- **Malware Scan**: Opțional, scanează fișierele uploadate

### SEO & Accessibility

- **Alt Text**: Obligatoriu pentru accessibility
- **Lazy Loading**: Implementează lazy loading în frontend
- **WebP**: Consideră conversie la WebP pentru performanță
- **Responsive**: Servește thumbnails potrivite pentru device

### Cleanup Strategy

- **Orphan Images**: Job care șterge imagini nefolosite după X zile
- **Cascade Delete**: Imaginile se șterg automat când articolul/autorul este șters
- **Soft Delete**: Opțional, implementează soft delete pentru recovery
