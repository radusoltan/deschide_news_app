# Thumbnail Entity - Arhitectură Detaliată

Entitatea **Thumbnail** reprezintă o variantă redimensionată a unei imagini, generată automat conform unui **ThumbnailProfile**.

## Proprietăți

### File Information

| Proprietate | Tip | Validări | Descriere |
|------------|-----|----------|-----------|
| `filename` | `string(255)` | Required, NotBlank | Numele fișierului thumbnail generat |
| `path` | `string(500)` | Required, NotBlank | Calea relativă către fișier thumbnail |
| `width` | `integer` | Required, Range(min=1) | Lățimea thumbnail în pixeli |
| `height` | `integer` | Required, Range(min=1) | Înălțimea thumbnail în pixeli |
| `size` | `integer` | Required, Range(min=1) | Dimensiune fișier în bytes |

### Relationships

| Proprietate | Relație | Target Entity | Descriere |
|------------|---------|---------------|-----------|
| `image` | `ManyToOne` | `Image` | Imaginea originală pentru care s-a generat thumbnail-ul |
| `profile` | `ManyToOne` | `ThumbnailProfile` | Profilul folosit pentru generare (dimensiuni, mode, quality) |

### Timestamps

| Proprietate | Tip | Descriere |
|------------|-----|-----------|
| `createdAt` | `datetime` | Data generării thumbnail-ului (auto-set) |

### Computed Fields

| Proprietate | Tip | Descriere |
|------------|-----|-----------|
| `url` | Computed | URL-ul public către thumbnail |
| `formattedSize` | Computed | Dimensiune formatată human-readable |

## Business Rules

### Validări

1. **Filename**:
   - Generat automat la generarea thumbnail-ului
   - Pattern: `{profile_name}/{original_filename}`
   - Ex: `medium_16_9/a3b2c1d4-e5f6.jpg`

2. **Path**:
   - Structură: `uploads/thumbnails/{profile_name}/{filename}`
   - Ex: `uploads/thumbnails/medium_16_9/a3b2c1d4-e5f6.jpg`
   - Organizat per profil pentru management ușor

3. **Width & Height**:
   - Moștenit din `ThumbnailProfile`
   - Dimensiunile exacte pot varia ușor dacă mode=FIT

4. **Size**:
   - Mărimea fișierului generat (de obicei mai mică decât originalul)
   - Depinde de quality settings din profile

5. **Image + Profile (Unique Together)**:
   - O imagine poate avea doar un thumbnail per profil
   - Constraint: UNIQUE(image_id, profile_id)

6. **Cascade Delete**:
   - La ștergerea imaginii → thumbnails se șterg automat
   - La ștergerea profilului → thumbnails rămân (sau se șterg - business decision)

### Generation Logic

**Thumbnail Generator Service:**
```php
namespace App\Service;

use App\Entity\Image;
use App\Entity\Thumbnail;
use App\Entity\ThumbnailProfile;
use Intervention\Image\ImageManager;

class ThumbnailGenerator
{
    public function __construct(
        private ImageManager $imageManager,
        private string $uploadDir,
        private string $thumbnailDir
    ) {}

    public function generate(Image $image, ThumbnailProfile $profile): Thumbnail
    {
        // 1. Load original image
        $originalPath = $this->uploadDir . '/' . $image->getPath();
        $img = $this->imageManager->make($originalPath);

        // 2. Apply transformation based on mode
        switch ($profile->getMode()) {
            case ThumbnailMode::CROP:
                $img->fit($profile->getWidth(), $profile->getHeight());
                break;

            case ThumbnailMode::FIT:
                $img->resize($profile->getWidth(), $profile->getHeight(), function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
                break;

            case ThumbnailMode::FILL:
                $img->resize($profile->getWidth(), $profile->getHeight());
                break;
        }

        // 3. Generate filename and path
        $filename = $image->getFilename();
        $path = sprintf('thumbnails/%s/%s', $profile->getName(), $filename);
        $fullPath = $this->thumbnailDir . '/' . $path;

        // 4. Create directory if not exists
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // 5. Save thumbnail with quality
        $img->save($fullPath, $profile->getQuality());

        // 6. Create Thumbnail entity
        $thumbnail = new Thumbnail();
        $thumbnail->setImage($image);
        $thumbnail->setProfile($profile);
        $thumbnail->setFilename($filename);
        $thumbnail->setPath($path);
        $thumbnail->setWidth($img->width());
        $thumbnail->setHeight($img->height());
        $thumbnail->setSize(filesize($fullPath));

        return $thumbnail;
    }

    public function generateAll(Image $image): array
    {
        $thumbnails = [];
        $profiles = $this->getActiveProfiles($image);

        foreach ($profiles as $profile) {
            $thumbnail = $this->generate($image, $profile);
            $thumbnails[] = $thumbnail;
        }

        return $thumbnails;
    }

    private function getActiveProfiles(Image $image): array
    {
        $category = $image->isProfileImage()
            ? ThumbnailCategory::PROFILE
            : ThumbnailCategory::ARTICLE;

        return $this->profileRepository->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->andWhere('p.category IN (:categories)')
            ->setParameter('categories', [$category, ThumbnailCategory::GENERAL])
            ->getQuery()
            ->getResult();
    }
}
```

### Computed Properties

**URL:**
```php
public function getUrl(): string
{
    return '/media/' . $this->path;
    // Ex: /media/thumbnails/medium_16_9/a3b2c1d4-e5f6.jpg
}
```

**Formatted Size:**

```php
public function getFormattedSize(): string
{
    $units = ['B', 'KB', 'MB'];
    $size = $this->size;
    $unit = 0;

    while ($size >= 1024 && $unit < count($units) - 1) {
        $size /= 1024;
        $unit++;
    }

    return round($size, 2) . ' entity-thumbnail.md' . $units[$unit];
}
```

## Database Schema

```sql
CREATE TABLE thumbnail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_id INT NOT NULL,
    profile_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    path VARCHAR(500) NOT NULL,
    width INT NOT NULL,
    height INT NOT NULL,
    size INT NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (image_id) REFERENCES image(id) ON DELETE CASCADE,
    FOREIGN KEY (profile_id) REFERENCES thumbnail_profile(id) ON DELETE RESTRICT,
    UNIQUE INDEX idx_image_profile (image_id, profile_id),
    INDEX idx_image (image_id),
    INDEX idx_profile (profile_id)
);
```

## Symfony Entity Example

```php
<?php

namespace App\Entity;

use App\Repository\ThumbnailRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ThumbnailRepository::class)]
#[ORM\Index(name: 'idx_image', columns: ['image_id'])]
#[ORM\Index(name: 'idx_profile', columns: ['profile_id'])]
#[ORM\UniqueConstraint(name: 'idx_image_profile', columns: ['image_id', 'profile_id'])]
class Thumbnail
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // File Information
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $filename = null;

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    private ?string $path = null;

    #[ORM\Column]
    #[Assert\Range(min: 1)]
    private ?int $width = null;

    #[ORM\Column]
    #[Assert\Range(min: 1)]
    private ?int $height = null;

    #[ORM\Column]
    #[Assert\Range(min: 1)]
    private ?int $size = null;

    // Relationships
    #[ORM\ManyToOne(targetEntity: Image::class, inversedBy: 'thumbnails')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Image $image = null;

    #[ORM\ManyToOne(targetEntity: ThumbnailProfile::class, inversedBy: 'thumbnails')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?ThumbnailProfile $profile = null;

    // Timestamps
    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    // Getters and setters...

    public function getUrl(): string
    {
        return '/media/' . $this->path;
    }

    public function getFormattedSize(): string
    {
        $units = ['B', 'KB', 'MB'];
        $size = $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }
}
```

## API Response Example

### Single Thumbnail

```json
{
  "id": 150,
  "filename": "a3b2c1d4-e5f6.jpg",
  "path": "thumbnails/medium_16_9/a3b2c1d4-e5f6.jpg",
  "url": "/media/thumbnails/medium_16_9/a3b2c1d4-e5f6.jpg",
  "width": 640,
  "height": 360,
  "size": 65536,
  "formattedSize": "64 KB",
  "profile": {
    "id": 2,
    "name": "medium_16_9",
    "displayName": "Medium 16:9"
  },
  "createdAt": "2025-01-15T10:31:00Z"
}
```

### Thumbnails Grouped by Profile (Common Format)

```json
{
  "imageId": 50,
  "thumbnails": {
    "small_square": {
      "url": "/media/thumbnails/small_square/a3b2c1d4-e5f6.jpg",
      "width": 150,
      "height": 150,
      "size": "25 KB"
    },
    "medium_16_9": {
      "url": "/media/thumbnails/medium_16_9/a3b2c1d4-e5f6.jpg",
      "width": 640,
      "height": 360,
      "size": "64 KB"
    },
    "large_16_9": {
      "url": "/media/thumbnails/large_16_9/a3b2c1d4-e5f6.jpg",
      "width": 1920,
      "height": 1080,
      "size": "256 KB"
    },
    "og_image": {
      "url": "/media/thumbnails/og_image/a3b2c1d4-e5f6.jpg",
      "width": 1200,
      "height": 630,
      "size": "128 KB"
    }
  }
}
```

## Query Examples

### Thumbnails pentru o Imagine

```php
$thumbnails = $thumbnailRepository->createQueryBuilder('t')
    ->where('t.image = :image')
    ->setParameter('image', $image)
    ->leftJoin('t.profile', 'p')
    ->addSelect('p')
    ->getQuery()
    ->getResult();
```

### Thumbnail Specific pentru Imagine și Profil

```php
$thumbnail = $thumbnailRepository->findOneBy([
    'image' => $image,
    'profile' => $profile
]);

// Sau by profile name
$thumbnail = $thumbnailRepository->createQueryBuilder('t')
    ->join('t.profile', 'p')
    ->where('t.image = :image')
    ->andWhere('p.name = :profileName')
    ->setParameter('image', $image)
    ->setParameter('profileName', 'medium_16_9')
    ->getQuery()
    ->getOneOrNullResult();
```

### Regenerare Thumbnails

```php
// Pentru o imagine specifică
public function regenerateThumbnails(Image $image): void
{
    // 1. Șterge thumbnails existente
    foreach ($image->getThumbnails() as $thumbnail) {
        $this->deleteThumbnailFile($thumbnail);
        $this->entityManager->remove($thumbnail);
    }
    $this->entityManager->flush();

    // 2. Generează thumbnails noi
    $thumbnails = $this->thumbnailGenerator->generateAll($image);

    foreach ($thumbnails as $thumbnail) {
        $this->entityManager->persist($thumbnail);
    }
    $this->entityManager->flush();
}

// Pentru un profil (toate imaginile)
public function regenerateProfileThumbnails(ThumbnailProfile $profile): void
{
    // Șterge toate thumbnails cu acest profil
    $thumbnails = $this->thumbnailRepository->findBy(['profile' => $profile]);

    foreach ($thumbnails as $thumbnail) {
        $image = $thumbnail->getImage();
        $this->deleteThumbnailFile($thumbnail);
        $this->entityManager->remove($thumbnail);
        $this->entityManager->flush();

        // Regenerează
        $newThumbnail = $this->thumbnailGenerator->generate($image, $profile);
        $this->entityManager->persist($newThumbnail);
    }

    $this->entityManager->flush();
}
```

## Upload & Generation Flow

### Complete Flow

```
1. User uploads image
   ↓
2. ImageController validates file
   ↓
3. ImageService creates Image entity
   ↓
4. Image saved to disk (uploads/images/{year}/{month}/{filename})
   ↓
5. ThumbnailGenerator::generateAll(image) called
   ↓
6. For each active ThumbnailProfile (filtered by category):
   a. Load original image
   b. Apply transformation (crop/fit/fill)
   c. Save to uploads/thumbnails/{profile_name}/{filename}
   d. Create Thumbnail entity
   e. Persist to database
   ↓
7. Return Image with all thumbnails
```

### Event Listener Approach

```php
namespace App\EventListener;

use App\Entity\Image;
use App\Service\ThumbnailGenerator;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Image::class)]
class ImageThumbnailListener
{
    public function __construct(
        private ThumbnailGenerator $thumbnailGenerator
    ) {}

    public function postPersist(Image $image, PostPersistEventArgs $args): void
    {
        // Generate thumbnails automatically after image is persisted
        $thumbnails = $this->thumbnailGenerator->generateAll($image);

        $entityManager = $args->getObjectManager();

        foreach ($thumbnails as $thumbnail) {
            $entityManager->persist($thumbnail);
        }

        $entityManager->flush();
    }
}
```

## Serving Thumbnails

### Nginx Configuration

```nginx
# Serve thumbnails directly from disk
location /media/thumbnails/ {
    alias /var/www/deschide_news_app/uploads/thumbnails/;
    expires 30d;
    add_header Cache-Control "public, immutable";
    add_header X-Content-Type-Options "nosniff";
}

# Fallback to original if thumbnail missing
location /media/thumbnails {
    try_files $uri /api/thumbnails/generate$request_uri;
}
```

### On-Demand Generation Controller

```php
#[Route('/api/thumbnails/generate/{profileName}/{filename}', name: 'thumbnail_generate')]
public function generateOnDemand(
    string $profileName,
    string $filename,
    ThumbnailGenerator $generator,
    ImageRepository $imageRepo,
    ThumbnailProfileRepository $profileRepo
): Response
{
    // Find image and profile
    $image = $imageRepo->findOneBy(['filename' => $filename]);
    $profile = $profileRepo->findOneBy(['name' => $profileName]);

    if (!$image || !$profile) {
        throw $this->createNotFoundException();
    }

    // Check if thumbnail exists
    $thumbnail = $image->getThumbnailByProfile($profileName);

    if (!$thumbnail) {
        // Generate on-demand
        $thumbnail = $generator->generate($image, $profile);
        $this->entityManager->persist($thumbnail);
        $this->entityManager->flush();
    }

    // Serve file
    return $this->file($this->uploadDir . '/' . $thumbnail->getPath());
}
```

## Considerații

### Performance

- **Async Generation**: Generează thumbnails async (queue) pentru imagini mari
- **CDN**: Servește thumbnails prin CDN
- **Lazy Generation**: Generează on-demand dacă lipsește (fallback)
- **Caching**: Cache URL-urile thumbnails în frontend

### Storage

- **Disk Space**: Thumbnails ocupă spațiu - monitoring
- **Cleanup**: La ștergerea imaginii, șterge și thumbnails de pe disk
- **Backup**: Backup doar imagini originale, thumbnails se pot regenera

### Optimization

- **WebP**: Generează și versiuni WebP pentru thumbnails
- **Progressive JPEG**: Folosește progressive encoding
- **Quality Balance**: 80-85 pentru majoritatea thumbnails

### Error Handling

- **Generation Failure**: Log erori, păstrează imagine originală
- **Missing Thumbnails**: Fallback la imagine originală sau generare on-demand
- **Corrupt Files**: Validare după generare

### Management Commands

```bash
# Regenerează toate thumbnails
php bin/console app:thumbnails:regenerate-all

# Regenerează thumbnails pentru un profil
php bin/console app:thumbnails:regenerate-profile medium_16_9

# Cleanup thumbnails orfane (imagini șterse)
php bin/console app:thumbnails:cleanup-orphans

# Generate missing thumbnails
php bin/console app:thumbnails:generate-missing
```
