# ThumbnailProfile Entity - Arhitectură Detaliată

Entitatea **ThumbnailProfile** definește configurația pentru generarea automată de thumbnails (dimensiuni, aspect-ratio, mod de crop).

## Proprietăți

### Configuration

| Proprietate | Tip | Validări | Descriere |
|------------|-----|----------|-----------|
| `name` | `string(100)` | Required, Unique, Pattern(^[a-z0-9_]+$) | Identificator unic (ex: "small_square", "large_16_9") |
| `displayName` | `string(255)` | Required, NotBlank | Nume afișat human-readable (ex: "Small Square", "Large 16:9") |
| `width` | `integer` | Required, Range(min=1, max=8000) | Lățimea thumbnail în pixeli |
| `height` | `integer` | Required, Range(min=1, max=8000) | Înălțimea thumbnail în pixeli |
| `aspectRatio` | `string(10)` | Optional, Pattern(^\d+:\d+$) | Aspect ratio (ex: "16:9", "4:3", "1:1") |
| `mode` | `enum` | Required | Modul de generare (crop, fit, fill) |
| `quality` | `integer` | Required, Range(min=1, max=100) | Calitatea JPEG/WebP (1-100, default: 85) |

### Metadata

| Proprietate | Tip | Valori | Descriere |
|------------|-----|--------|-----------|
| `isActive` | `boolean` | `true`, `false` (default: true) | Profilul este activ (se generează automat) |
| `category` | `enum` | `article`, `profile`, `general` | Categoria de utilizare |
| `description` | `text` | Optional | Descriere/notițe despre când se folosește |

### Timestamps

| Proprietate | Tip | Descriere |
|------------|-----|-----------|
| `createdAt` | `datetime` | Data creării (auto-set) |
| `updatedAt` | `datetime` | Data ultimei modificări (auto-update) |

### Relationships

| Proprietate | Relație | Target Entity | Descriere |
|------------|---------|---------------|-----------|
| `thumbnails` | `OneToMany` | `Thumbnail` | Lista de thumbnails generate cu acest profil |

### Computed Fields

| Proprietate | Tip | Descriere |
|------------|-----|-----------|
| `calculatedAspectRatio` | Computed | Aspect ratio calculat din width/height |
| `dimensionsLabel` | Computed | Label format: "1920x1080 (16:9)" |

## Enum Definitions

### ThumbnailMode

```php
enum ThumbnailMode: string
{
    case CROP = 'crop';   // Crop imaginea la dimensiuni exacte (poate pierde margini)
    case FIT = 'fit';     // Fit imaginea în dimensiuni (păstrează aspect ratio, poate avea margini)
    case FILL = 'fill';   // Fill dimensiunile (stretches imaginea dacă e necesar)
}
```

**Explicații:**
- **CROP**: Cropează imaginea la dimensiunile exacte. Imaginea este redimensionată și apoi cropată din centru. Useful pentru thumbnails cu dimensiuni fixe (ex: avatar 150x150)
- **FIT**: Redimensionează imaginea să încapă în dimensiuni, păstrând aspect ratio. Poate avea margini/padding. Useful când vrei să păstrezi întreaga imagine.
- **FILL**: Umple complet dimensiunile, stretch dacă e necesar. Distorsionează imaginea dacă aspect ratio-ul diferă.

### ThumbnailCategory

```php
enum ThumbnailCategory: string
{
    case ARTICLE = 'article';   // Thumbnails pentru imagini de articole
    case PROFILE = 'profile';   // Thumbnails pentru imagini de profil (autori)
    case GENERAL = 'general';   // Thumbnails generale (ambele)
}
```

## Business Rules

### Validări

1. **Name**:
   - Unic în sistem
   - Pattern: doar lowercase, cifre și underscore
   - Exemple: `small_square`, `medium_16_9`, `large_4_3`, `og_image`, `avatar_small`

2. **Width & Height**:
   - Minimum 1px, maximum 8000px
   - Recomandat: minimum 50px pentru usability
   - Combinația (width, height) nu trebuie să fie duplicată (business constraint)

3. **AspectRatio**:
   - Optional (calculat din width/height dacă nu e specificat)
   - Format: "width:height" (ex: "16:9", "4:3", "1:1")
   - Validat că respectă width/height actual

4. **Mode**:
   - Default: `CROP` pentru thumbnails fixe
   - `FIT` pentru thumbnails responsive
   - `FILL` rar folosit (distorsionează)

5. **Quality**:
   - 1-100 (JPEG/WebP compression quality)
   - Default: 85 (good balance între quality și size)
   - 90-100: high quality (file size mare)
   - 70-85: medium quality (recomandat)
   - 50-70: low quality (file size mic, loss visible)

6. **IsActive**:
   - Doar profilele active se generează automat la upload
   - Profilele inactive rămân în sistem dar nu se mai generează

7. **Category**:
   - Filtrare: la upload de imagine articol → doar profiles cu category=article sau general
   - La upload imagine profil → doar profiles cu category=profile sau general

### Computed Properties

**Calculated Aspect Ratio:**
```php
public function getCalculatedAspectRatio(): string
{
    if ($this->aspectRatio) {
        return $this->aspectRatio;
    }

    $gcd = $this->gcd($this->width, $this->height);
    return ($this->width / $gcd) . ':' . ($this->height / $gcd);
}

private function gcd(int $a, int $b): int
{
    return $b === 0 ? $a : $this->gcd($b, $a % $b);
}
```

**Dimensions Label:**
```php
public function getDimensionsLabel(): string
{
    return sprintf('%dx%d (%s)',
        $this->width,
        $this->height,
        $this->getCalculatedAspectRatio()
    );
}
```

## Database Schema

```sql
CREATE TABLE thumbnail_profile (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(255) NOT NULL,
    width INT NOT NULL,
    height INT NOT NULL,
    aspect_ratio VARCHAR(10) NULL,
    mode VARCHAR(20) NOT NULL DEFAULT 'crop',
    quality INT NOT NULL DEFAULT 85,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    category VARCHAR(20) NOT NULL DEFAULT 'general',
    description TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_name (name),
    INDEX idx_is_active (is_active),
    INDEX idx_category (category),
    UNIQUE INDEX idx_dimensions (width, height, mode)
);
```

## Symfony Entity Example

```php
<?php

namespace App\Entity;

use App\Repository\ThumbnailProfileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ThumbnailProfileRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity('name')]
#[ORM\Index(name: 'idx_name', columns: ['name'])]
#[ORM\Index(name: 'idx_is_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_category', columns: ['category'])]
#[ORM\UniqueConstraint(name: 'idx_dimensions', columns: ['width', 'height', 'mode'])]
class ThumbnailProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Configuration
    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9_]+$/')]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $displayName = null;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 8000)]
    private ?int $width = null;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 8000)]
    private ?int $height = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Regex(pattern: '/^\d+:\d+$/')]
    private ?string $aspectRatio = null;

    #[ORM\Column(length: 20, enumType: ThumbnailMode::class)]
    private ThumbnailMode $mode = ThumbnailMode::CROP;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 100)]
    private int $quality = 85;

    // Metadata
    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(length: 20, enumType: ThumbnailCategory::class)]
    private ThumbnailCategory $category = ThumbnailCategory::GENERAL;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    // Timestamps
    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    // Relationships
    #[ORM\OneToMany(mappedBy: 'profile', targetEntity: Thumbnail::class)]
    private Collection $thumbnails;

    public function __construct()
    {
        $this->thumbnails = new ArrayCollection();
    }

    // Getters and setters...

    public function getCalculatedAspectRatio(): string
    {
        if ($this->aspectRatio) {
            return $this->aspectRatio;
        }

        $gcd = $this->gcd($this->width, $this->height);
        return ($this->width / $gcd) . ':' . ($this->height / $gcd);
    }

    private function gcd(int $a, int $b): int
    {
        return $b === 0 ? $a : $this->gcd($b, $a % $b);
    }

    public function getDimensionsLabel(): string
    {
        return sprintf('%dx%d (%s)',
            $this->width,
            $this->height,
            $this->getCalculatedAspectRatio()
        );
    }
}
```

## Thumbnail Profiles Predefinite

### Pentru Articole

```php
// Small Square - pentru preview-uri mici, liste
[
    'name' => 'small_square',
    'displayName' => 'Small Square',
    'width' => 150,
    'height' => 150,
    'aspectRatio' => '1:1',
    'mode' => ThumbnailMode::CROP,
    'quality' => 80,
    'category' => ThumbnailCategory::ARTICLE,
    'description' => 'Small square thumbnail for article previews'
]

// Medium 16:9 - pentru card-uri articole
[
    'name' => 'medium_16_9',
    'displayName' => 'Medium 16:9',
    'width' => 640,
    'height' => 360,
    'aspectRatio' => '16:9',
    'mode' => ThumbnailMode::CROP,
    'quality' => 85,
    'category' => ThumbnailCategory::ARTICLE,
    'description' => 'Medium size for article cards'
]

// Large 16:9 - pentru hero images
[
    'name' => 'large_16_9',
    'displayName' => 'Large 16:9',
    'width' => 1920,
    'height' => 1080,
    'aspectRatio' => '16:9',
    'mode' => ThumbnailMode::CROP,
    'quality' => 90,
    'category' => ThumbnailCategory::ARTICLE,
    'description' => 'Large hero image for article detail'
]

// OG Image - pentru social media sharing
[
    'name' => 'og_image',
    'displayName' => 'Open Graph Image',
    'width' => 1200,
    'height' => 630,
    'aspectRatio' => '1.91:1',
    'mode' => ThumbnailMode::CROP,
    'quality' => 85,
    'category' => ThumbnailCategory::ARTICLE,
    'description' => 'Optimized for Facebook, Twitter, LinkedIn sharing'
]

// Mobile Thumbnail
[
    'name' => 'mobile_4_3',
    'displayName' => 'Mobile 4:3',
    'width' => 480,
    'height' => 360,
    'aspectRatio' => '4:3',
    'mode' => ThumbnailMode::CROP,
    'quality' => 80,
    'category' => ThumbnailCategory::ARTICLE,
    'description' => 'Mobile-optimized thumbnail'
]
```

### Pentru Profile Autori

```php
// Avatar Small - pentru comments, bylines
[
    'name' => 'avatar_small',
    'displayName' => 'Avatar Small',
    'width' => 50,
    'height' => 50,
    'aspectRatio' => '1:1',
    'mode' => ThumbnailMode::CROP,
    'quality' => 80,
    'category' => ThumbnailCategory::PROFILE,
    'description' => 'Small avatar for comments and bylines'
]

// Avatar Medium - pentru author cards
[
    'name' => 'avatar_medium',
    'displayName' => 'Avatar Medium',
    'width' => 150,
    'height' => 150,
    'aspectRatio' => '1:1',
    'mode' => ThumbnailMode::CROP,
    'quality' => 85,
    'category' => ThumbnailCategory::PROFILE,
    'description' => 'Medium avatar for author cards'
]

// Avatar Large - pentru author pages
[
    'name' => 'avatar_large',
    'displayName' => 'Avatar Large',
    'width' => 400,
    'height' => 400,
    'aspectRatio' => '1:1',
    'mode' => ThumbnailMode::CROP,
    'quality' => 90,
    'category' => ThumbnailCategory::PROFILE,
    'description' => 'Large avatar for author profile pages'
]
```

## API Response Example

```json
{
  "id": 1,
  "name": "medium_16_9",
  "displayName": "Medium 16:9",
  "width": 640,
  "height": 360,
  "aspectRatio": "16:9",
  "calculatedAspectRatio": "16:9",
  "dimensionsLabel": "640x360 (16:9)",
  "mode": "crop",
  "quality": 85,
  "isActive": true,
  "category": "article",
  "description": "Medium size for article cards",
  "createdAt": "2025-01-01T00:00:00Z",
  "updatedAt": "2025-01-01T00:00:00Z"
}
```

## Query Examples

### Active Profiles pentru Articole

```php
$articleProfiles = $profileRepository->createQueryBuilder('p')
    ->where('p.isActive = true')
    ->andWhere('p.category IN (:categories)')
    ->setParameter('categories', [
        ThumbnailCategory::ARTICLE,
        ThumbnailCategory::GENERAL
    ])
    ->orderBy('p.width', 'ASC')
    ->getQuery()
    ->getResult();
```

### Active Profiles pentru Profile Autori

```php
$profileProfiles = $profileRepository->createQueryBuilder('p')
    ->where('p.isActive = true')
    ->andWhere('p.category IN (:categories)')
    ->setParameter('categories', [
        ThumbnailCategory::PROFILE,
        ThumbnailCategory::GENERAL
    ])
    ->orderBy('p.width', 'ASC')
    ->getQuery()
    ->getResult();
```

### Find by Name

```php
$profile = $profileRepository->findOneBy(['name' => 'medium_16_9']);
```

## Fixtures Example

```php
// src/DataFixtures/ThumbnailProfileFixtures.php
namespace App\DataFixtures;

use App\Entity\ThumbnailProfile;
use App\Enum\ThumbnailMode;
use App\Enum\ThumbnailCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ThumbnailProfileFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $profiles = [
            // Article profiles
            ['small_square', 'Small Square', 150, 150, '1:1', ThumbnailMode::CROP, 80, ThumbnailCategory::ARTICLE],
            ['medium_16_9', 'Medium 16:9', 640, 360, '16:9', ThumbnailMode::CROP, 85, ThumbnailCategory::ARTICLE],
            ['large_16_9', 'Large 16:9', 1920, 1080, '16:9', ThumbnailMode::CROP, 90, ThumbnailCategory::ARTICLE],
            ['og_image', 'Open Graph', 1200, 630, '1.91:1', ThumbnailMode::CROP, 85, ThumbnailCategory::ARTICLE],

            // Profile avatars
            ['avatar_small', 'Avatar Small', 50, 50, '1:1', ThumbnailMode::CROP, 80, ThumbnailCategory::PROFILE],
            ['avatar_medium', 'Avatar Medium', 150, 150, '1:1', ThumbnailMode::CROP, 85, ThumbnailCategory::PROFILE],
            ['avatar_large', 'Avatar Large', 400, 400, '1:1', ThumbnailMode::CROP, 90, ThumbnailCategory::PROFILE],
        ];

        foreach ($profiles as [$name, $displayName, $width, $height, $ratio, $mode, $quality, $category]) {
            $profile = new ThumbnailProfile();
            $profile->setName($name);
            $profile->setDisplayName($displayName);
            $profile->setWidth($width);
            $profile->setHeight($height);
            $profile->setAspectRatio($ratio);
            $profile->setMode($mode);
            $profile->setQuality($quality);
            $profile->setCategory($category);
            $profile->setIsActive(true);

            $manager->persist($profile);
        }

        $manager->flush();
    }
}
```

## Considerații

### Performance

- **Caching**: Profilele se schimbă rar, cache-uiește lista
- **Preload**: La startup, încarcă toate profilele active în memorie

### Management

- **Admin Interface**: Permite crearea/editarea de profile custom
- **Regenerare**: Dacă modifici un profil, permite regenerarea thumbnails existente

### Best Practices

- **Naming Convention**: `{size}_{aspectratio}` (ex: medium_16_9, small_square)
- **Quality Balance**: 80-85 pentru majoritatea thumbnails, 90+ doar pentru hero images
- **Responsive**: Creează multiple profile sizes pentru responsive design
- **WebP Support**: Consideră generarea și thumbnails WebP pentru performanță

### Migration Strategy

- **Seed Data**: Folosește fixtures pentru profile predefinite
- **Versioning**: Dacă modifici un profil, consideră crearea unui profil nou
- **Backwards Compatibility**: Păstrează profilele vechi pentru imagini existente
