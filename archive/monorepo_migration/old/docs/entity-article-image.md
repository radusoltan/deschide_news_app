# ArticleImage Entity - Pivot Table

Entitatea **ArticleImage** este un tabel pivot care gestionează relația ManyToMany între **Article** și **Image**, cu metadata suplimentară specifică acestei relații.

## Proprietăți

### Pivot Metadata

| Proprietate | Tip | Validări | Descriere |
|------------|-----|----------|-----------|
| `position` | `integer` | Required, Range(min=0) | Ordinea imaginii în galeria articolului (0-based index) |
| `isFeatured` | `boolean` | Required | Imaginea este featured image pentru acest articol |

### Relationships

| Proprietate | Relație | Target Entity | Descriere |
|------------|---------|---------------|-----------|
| `article` | `ManyToOne` | `Article` | Articolul |
| `image` | `ManyToOne` | `Image` | Imaginea |

### Timestamps

| Proprietate | Tip | Descriere |
|------------|-----|-----------|
| `createdAt` | `datetime` | Când a fost adăugată imaginea la articol (auto-set) |

## Business Rules

### Validări

1. **Unique Constraint**:
   - `(article_id, image_id)` UNIQUE
   - O imagine nu poate fi adăugată de 2 ori la același articol

2. **Position**:
   - Ordinea în galeria de imagini (0, 1, 2, ...)
   - Auto-incrementat la adăugare (ultima poziție + 1)
   - Permite reordonare manuală

3. **IsFeatured**:
   - Doar o imagine poate fi featured per articol
   - La setarea unei imagini ca featured, celelalte se dezactivează automat
   - Dacă nu există featured explicit, prima imagine (position=0) este considerată featured

4. **Cascade Delete**:
   - La ștergerea articolului → șterge toate relațiile ArticleImage
   - La ștergerea imaginii → șterge toate relațiile ArticleImage
   - Imaginea fizică NU se șterge dacă e folosită în alte articole

5. **Article Image Limit**:
   - Un articol poate avea 1-50 imagini (validat la nivel de Article)
   - Se numără via COUNT(article_images WHERE article_id = X)

## Database Schema

```sql
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
```

## Symfony Entity Example

```php
<?php

namespace App\Entity;

use App\Repository\ArticleImageRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ArticleImageRepository::class)]
#[ORM\Table(name: 'article_image')]
#[ORM\UniqueConstraint(name: 'idx_article_image', columns: ['article_id', 'image_id'])]
#[ORM\Index(name: 'idx_article', columns: ['article_id'])]
#[ORM\Index(name: 'idx_image', columns: ['image_id'])]
#[ORM\Index(name: 'idx_position', columns: ['article_id', 'position'])]
#[ORM\Index(name: 'idx_featured', columns: ['article_id', 'is_featured'])]
class ArticleImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Relationships
    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'articleImages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Article $article = null;

    #[ORM\ManyToOne(targetEntity: Image::class, inversedBy: 'articleImages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Image $image = null;

    // Pivot Metadata
    #[ORM\Column]
    #[Assert\Range(min: 0)]
    private int $position = 0;

    #[ORM\Column]
    private bool $isFeatured = false;

    // Timestamps
    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    // Getters and setters...

    public function __construct(Article $article, Image $image, int $position = 0, bool $isFeatured = false)
    {
        $this->article = $article;
        $this->image = $image;
        $this->position = $position;
        $this->isFeatured = $isFeatured;
    }
}
```

## Updated Article Entity

```php
// In Article.php

#[ORM\OneToMany(mappedBy: 'article', targetEntity: ArticleImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
#[ORM\OrderBy(['position' => 'ASC'])]
#[Assert\Count(min: 1, max: 50, minMessage: 'Article must have at least one image', maxMessage: 'Article cannot have more than 50 images')]
private Collection $articleImages;

public function __construct()
{
    $this->authors = new ArrayCollection();
    $this->articleImages = new ArrayCollection();
}

// Helper methods
public function getImages(): Collection
{
    return $this->articleImages->map(fn($ai) => $ai->getImage());
}

public function getFeaturedImage(): ?Image
{
    // Find explicitly featured image
    $featured = $this->articleImages->filter(fn($ai) => $ai->isFeatured())->first();

    if ($featured) {
        return $featured->getImage();
    }

    // Fallback to first image
    $first = $this->articleImages->first();
    return $first ? $first->getImage() : null;
}

public function addImage(Image $image, int $position = null, bool $isFeatured = false): self
{
    // Check if image already exists
    foreach ($this->articleImages as $ai) {
        if ($ai->getImage() === $image) {
            return $this;
        }
    }

    // Auto position
    if ($position === null) {
        $position = $this->articleImages->count();
    }

    // If setting as featured, unfeatured others
    if ($isFeatured) {
        foreach ($this->articleImages as $ai) {
            $ai->setIsFeatured(false);
        }
    }

    $articleImage = new ArticleImage($this, $image, $position, $isFeatured);
    $this->articleImages->add($articleImage);

    return $this;
}

public function removeImage(Image $image): self
{
    foreach ($this->articleImages as $ai) {
        if ($ai->getImage() === $image) {
            $this->articleImages->removeElement($ai);
            break;
        }
    }

    return $this;
}

public function setFeaturedImage(Image $image): self
{
    foreach ($this->articleImages as $ai) {
        $ai->setIsFeatured($ai->getImage() === $image);
    }

    return $this;
}

public function reorderImages(array $imageIdsInOrder): self
{
    foreach ($imageIdsInOrder as $position => $imageId) {
        foreach ($this->articleImages as $ai) {
            if ($ai->getImage()->getId() === $imageId) {
                $ai->setPosition($position);
                break;
            }
        }
    }

    return $this;
}
```

## Updated Image Entity

```php
// In Image.php

#[ORM\OneToMany(mappedBy: 'image', targetEntity: ArticleImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
private Collection $articleImages;

public function __construct()
{
    $this->thumbnails = new ArrayCollection();
    $this->articleImages = new ArrayCollection();
}

public function getArticles(): Collection
{
    return $this->articleImages->map(fn($ai) => $ai->getArticle());
}

public function isUsedInArticles(): bool
{
    return $this->articleImages->count() > 0;
}

public function getUsageCount(): int
{
    return $this->articleImages->count();
}
```

## Service Layer Example

### ArticleImageService

```php
namespace App\Service;

use App\Entity\Article;
use App\Entity\Image;
use App\Entity\ArticleImage;
use Doctrine\ORM\EntityManagerInterface;

class ArticleImageService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    public function attachImage(Article $article, Image $image, ?int $position = null, bool $featured = false): ArticleImage
    {
        // Validate: image not already attached
        $existing = $this->em->getRepository(ArticleImage::class)->findOneBy([
            'article' => $article,
            'image' => $image
        ]);

        if ($existing) {
            throw new \LogicException('Image already attached to this article');
        }

        // Validate: article image count
        if ($article->getArticleImages()->count() >= 50) {
            throw new \LogicException('Article cannot have more than 50 images');
        }

        // Auto-position
        if ($position === null) {
            $position = $article->getArticleImages()->count();
        }

        // If featured, unfeatured others
        if ($featured) {
            $this->unfeaturedAll($article);
        }

        // Create pivot
        $articleImage = new ArticleImage($article, $image, $position, $featured);
        $this->em->persist($articleImage);

        return $articleImage;
    }

    public function detachImage(Article $article, Image $image): void
    {
        $articleImage = $this->em->getRepository(ArticleImage::class)->findOneBy([
            'article' => $article,
            'image' => $image
        ]);

        if (!$articleImage) {
            return;
        }

        $wasFeatured = $articleImage->isFeatured();

        $this->em->remove($articleImage);
        $this->em->flush();

        // If removed image was featured, make first image featured
        if ($wasFeatured) {
            $first = $article->getArticleImages()->first();
            if ($first) {
                $first->setIsFeatured(true);
                $this->em->flush();
            }
        }

        // Reorder positions
        $this->reorderPositions($article);
    }

    public function setFeatured(Article $article, Image $image): void
    {
        $this->unfeaturedAll($article);

        $articleImage = $this->em->getRepository(ArticleImage::class)->findOneBy([
            'article' => $article,
            'image' => $image
        ]);

        if ($articleImage) {
            $articleImage->setIsFeatured(true);
            $this->em->flush();
        }
    }

    public function reorder(Article $article, array $imageIdsInOrder): void
    {
        foreach ($imageIdsInOrder as $position => $imageId) {
            $articleImage = $this->em->getRepository(ArticleImage::class)
                ->createQueryBuilder('ai')
                ->where('ai.article = :article')
                ->andWhere('ai.image = :imageId')
                ->setParameter('article', $article)
                ->setParameter('imageId', $imageId)
                ->getQuery()
                ->getOneOrNullResult();

            if ($articleImage) {
                $articleImage->setPosition($position);
            }
        }

        $this->em->flush();
    }

    private function unfeaturedAll(Article $article): void
    {
        foreach ($article->getArticleImages() as $ai) {
            $ai->setIsFeatured(false);
        }
    }

    private function reorderPositions(Article $article): void
    {
        $images = $article->getArticleImages()->toArray();
        usort($images, fn($a, $b) => $a->getPosition() <=> $b->getPosition());

        foreach ($images as $position => $articleImage) {
            $articleImage->setPosition($position);
        }

        $this->em->flush();
    }
}
```

## API Examples

### Add Image to Article

```http
POST /api/admin/articles/123/images
Content-Type: application/json

{
  "imageId": 456,
  "position": 0,
  "isFeatured": true
}
```

**Response:**
```json
{
  "id": 789,
  "article": {
    "id": 123,
    "title": "Breaking News"
  },
  "image": {
    "id": 456,
    "filename": "image.jpg",
    "url": "/media/uploads/images/2025/01/image.jpg"
  },
  "position": 0,
  "isFeatured": true,
  "createdAt": "2025-01-15T10:00:00Z"
}
```

### Remove Image from Article

```http
DELETE /api/admin/articles/123/images/456
```

### Set Featured Image

```http
PUT /api/admin/articles/123/images/456/featured
```

### Reorder Images

```http
PUT /api/admin/articles/123/images/reorder
Content-Type: application/json

{
  "imageIds": [456, 789, 123, 999]
}
```

### Get Article with Images

```json
{
  "id": 123,
  "title": "Breaking News",
  "images": [
    {
      "id": 456,
      "filename": "hero-image.jpg",
      "url": "/media/uploads/images/2025/01/hero-image.jpg",
      "alt": "Hero image",
      "position": 0,
      "isFeatured": true,
      "thumbnails": {
        "medium_16_9": "/media/thumbnails/medium_16_9/hero-image.jpg",
        "large_16_9": "/media/thumbnails/large_16_9/hero-image.jpg"
      }
    },
    {
      "id": 789,
      "filename": "gallery-1.jpg",
      "url": "/media/uploads/images/2025/01/gallery-1.jpg",
      "position": 1,
      "isFeatured": false
    }
  ]
}
```

## Query Examples

### Get Featured Image for Article

```php
$featuredImage = $articleImageRepository->createQueryBuilder('ai')
    ->select('ai', 'i', 't')
    ->join('ai.image', 'i')
    ->leftJoin('i.thumbnails', 't')
    ->where('ai.article = :article')
    ->andWhere('ai.isFeatured = true')
    ->setParameter('article', $article)
    ->getQuery()
    ->getOneOrNullResult();

$image = $featuredImage?->getImage();
```

### Get All Images for Article (Ordered)

```php
$articleImages = $articleImageRepository->createQueryBuilder('ai')
    ->select('ai', 'i', 't')
    ->join('ai.image', 'i')
    ->leftJoin('i.thumbnails', 't')
    ->where('ai.article = :article')
    ->setParameter('article', $article)
    ->orderBy('ai.position', 'ASC')
    ->getQuery()
    ->getResult();
```

### Find Articles Using Image

```php
$articles = $articleImageRepository->createQueryBuilder('ai')
    ->select('a')
    ->join('ai.article', 'a')
    ->where('ai.image = :image')
    ->andWhere('a.status = :published')
    ->setParameter('image', $image)
    ->setParameter('published', ArticleStatus::PUBLISHED)
    ->getQuery()
    ->getResult();
```

## Benefits of Pivot Table Approach

### ✅ Advantages

1. **Image Reusability**: Aceeași imagine poate fi folosită în multiple articole
2. **Flexible Metadata**: `position` și `isFeatured` sunt specific relației Article-Image
3. **Independent Management**: Imaginile pot exista independent de articole
4. **Better Performance**: Query optimization prin index-uri pe pivot table
5. **Clear Separation**: Metadata imagine (alt, caption) vs metadata relație (position, featured)
6. **Easier Reordering**: Reordonare imagini fără să modifici entitatea Image
7. **Audit Trail**: `createdAt` pe pivot → când a fost adăugată imaginea la articol

### 📊 Use Cases

- **Stock Images**: Imagini refolosite în multiple articole
- **Series Articles**: Aceeași imagine featured pentru seria de articole
- **Image Library**: Biblioteca centrală de imagini partajată
- **Performance**: Lazy loading - încarcă doar imaginile necesare pentru articol

## Considerații

### Migration from Old Structure

Dacă exista o structură veche (ManyToOne), migra datele:

```php
// Migration script
foreach ($articles as $article) {
    $position = 0;
    foreach ($article->getOldImages() as $oldImage) {
        $articleImage = new ArticleImage(
            $article,
            $oldImage->getImage(),
            $position++,
            $oldImage->isFeatured()
        );
        $em->persist($articleImage);
    }
}
$em->flush();
```

### Performance

- **Eager Loading**: Folosește JOIN pe article_images și images pentru liste
- **Index-uri**: Pe `(article_id, position)` pentru sorting rapid
- **Caching**: Cache relațiile article-images pentru articole populare
