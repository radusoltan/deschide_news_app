# Sprint 2: Image Management cu Intervention Image - PLAN ACTUALIZAT

**Durata:** 10-14 zile
**Obiectiv:** Sistem complet de imagini cu Intervention Image v3, hash-based deduplication, blur placeholders și async thumbnail generation
**Tehnologii:** Intervention Image v3, Symfony Messenger (RabbitMQ), Progressive JPEG

---

## Tasks

### **1. Setup Intervention Image**

```bash
cd /var/www/deschide_news_app/deschide_backend
composer require intervention/image
```

**Configurare** `config/services/intervention.yaml`:
```yaml
services:
    Intervention\Image\ImageManager:
        factory: ['Intervention\Image\Drivers\Gd\Driver', 'create']
        # Alternative: Use Imagick if available
        # factory: ['Intervention\Image\Drivers\Imagick\Driver', 'create']
```

---

### **2. Image Entity** (`src/Entity/Image.php`)

**Câmpuri principale:**
- `filename` (string, 255) - Nume generat (hash.ext)
- `originalFilename` (string, 255) - Nume original
- `path` (string, 255) - Cale relativă cu hash subdirectories
- `mimeType` (string, 100)
- `size` (integer) - Bytes
- `width`, `height` (integer) - Dimensiuni
- **`hash` (string, 64, UNIQUE, INDEX)** - SHA-256 pentru deduplicare
- **`dominantColor` (string, 7, nullable)** - Hex color (#RRGGBB)
- **`blurPlaceholder` (text, nullable)** - Base64 data URI pentru progressive loading
- `altText` (string, 255, nullable) - SEO
- `description` (text, nullable) - Descriere extinsă
- `author` (string, 255, nullable) - Credit fotograf
- `source` (text, nullable) - Sursa imagine (URL sau text)
- `createdAt`, `updatedAt` (Gedmo Timestampable)

**Relații:**
- `thumbnails` (OneToMany cu Thumbnail, cascade remove, orphanRemoval)

**Helper methods:**
- `getBlurDataUrl()` - Alias pentru blurPlaceholder
- `getDominantColorHex()` - Alias pentru dominantColor

**Storage organizare:**
```
public/uploads/images/
  ├── originals/
  │   ├── ab/          # hash[0:2]
  │   │   ├── cd/      # hash[2:4]
  │   │   │   └── abcdef123...xyz.jpg
  └── thumbnails/
      ├── ab/
      │   ├── cd/
      │   │   ├── article_card.jpg
      │   │   ├── article_card.webp
      │   │   └── article_hero.jpg
```

---

### **3. Thumbnail Entity** (`src/Entity/Thumbnail.php`)

**Câmpuri:**
- `image` (ManyToOne cu Image, onDelete CASCADE)
- `profile` (string, 50) - Nume profil (article_card, article_hero, etc.)
- `format` (string, 10) - Format (jpg, webp, png, avif)
- `path` (string, 255) - Cale publică (alias: publicPath pentru backwards compat)
- `width`, `height` (integer) - Dimensiuni finale
- `fileSize` (integer) - Bytes
- **`status` (string, 20, default 'pending')** - pending/processing/ready/failed
- **`cropJson` (json, nullable)** - Coordonate crop custom: `{x, y, width, height}` sau `{p: {x%, y%, width%, height%}}`
- **`hasCustomCrop` (boolean, default false, INDEX)** - Flag pentru căutare rapidă
- `createdAt`, `updatedAt` (Gedmo Timestampable)

**Unique Constraint:** (image_id, profile, format)

**Indexes:**
- `idx_thumbnail_status` pe `status`
- `idx_thumbnail_custom_crop` pe `hasCustomCrop`

**Helper methods:**
- `markAsReady()` - Set status = 'ready'
- `markAsFailed()` - Set status = 'failed'
- `markAsProcessing()` - Set status = 'processing'
- `getPath()` / `setPath()` - Alias pentru publicPath (backwards compat)

---

### **4. Image Configuration** (`config/services/image.yaml`)

```yaml
parameters:
    # Storage paths
    image.storage.root: '%kernel.project_dir%/public/uploads/images'
    image.storage.originals_dir: 'originals'
    image.storage.thumbnails_dir: 'thumbnails'
    image.storage.public_path: '/uploads/images'

    # Upload constraints
    image.upload.max_size: 5242880  # 5MB
    image.upload.allowed_types:
        - 'image/jpeg'
        - 'image/jpg'
        - 'image/png'
        - 'image/x-png'
        - 'image/webp'
        - 'image/gif'

    # Thumbnail profiles pentru news
    image.thumbnail.profiles:
        # Articles
        article_card:
            width: 640
            height: 427
            mode: 'cover'
            description: 'Card thumbnail 3:2 ratio'

        article_hero:
            width: 1600
            height: 600
            mode: 'cover'
            description: 'Hero banner 8:3 ratio'

        article_wide:
            width: 1920
            height: 1080
            mode: 'cover'
            description: 'Wide format 16:9 ratio'

        article_square:
            width: 600
            height: 600
            mode: 'cover'
            description: 'Square for social sharing'

        # Categories
        category_icon:
            width: 400
            height: 400
            mode: 'cover'
            description: 'Category grid 1:1'

        # Authors
        author_avatar:
            width: 200
            height: 200
            mode: 'cover'
            description: 'Author avatar small'

        author_avatar_large:
            width: 400
            height: 400
            mode: 'cover'
            description: 'Author avatar large'

        # Breaking News
        breaking_banner:
            width: 1200
            height: 400
            mode: 'cover'
            description: 'Breaking news banner 3:1'

    # Formats to generate
    image.thumbnail.formats: ['jpg', 'webp', 'avif']

    # Quality settings per format
    image.thumbnail.quality:
        jpg: 85
        webp: 80
        png: 90
        avif: 75

    # Image encoding options
    image.thumbnail.progressive: true

    # Blur placeholder settings
    image.blur_placeholder.size: 20
    image.blur_placeholder.blur: 2
    image.blur_placeholder.quality: 60
```

---

### **5. ImageService** (`src/Service/ImageService.php`)

Service principal consolidat cu toate funcționalitățile:

**Dependențe (constructor):**
- `ImageManager` (Intervention Image)
- `EntityManagerInterface`
- `ImageRepository`
- `MessageBusInterface` (Symfony Messenger)
- `HttpClientInterface` (pentru uploadFromUrl)
- `LoggerInterface`
- `Filesystem` (Symfony)
- `ParameterBagInterface` (pentru config)

**Metode publice (10):**

#### **a) upload(UploadedFile $file, array $metadata = []): Image**
Upload fișier local cu deduplicare hash.

**Flow:**
1. Validate file (size, MIME type)
2. Read file în memory imediat (prevent cleanup issues)
3. Generate SHA-256 hash
4. Check duplicate (findByHash) DUPĂ mutare fișier
5. Generate unique filename: `{hash}.{ext}`
6. Organize storage: `originals/{hash[0:2]}/{hash[2:4]}/{hash}.ext`
7. Save cu Progressive JPEG (dacă applicable)
8. Extract metadata (width, height cu Intervention)
9. Generate blur placeholder (20x20, blur 2, base64 data URI)
10. Extract dominant color (center pixel, hex)
11. Create Image entity
12. Persist & flush
13. **Dispatch async** `GenerateImageThumbnailsMessage`
14. Return Image

**Metadata array opțional:**
```php
[
    'altText' => 'string',
    'description' => 'string',
    'author' => 'string',
    'source' => 'string',
]
```

#### **b) uploadFromUrl(string $url, array $metadata = []): Image**
Download și upload de la URL extern.

**Flow:**
1. Validate URL (filter_var)
2. Download cu HttpClient (timeout 30s, max_duration 60s)
3. Validate content-type
4. Check size limit
5. Generate hash din content
6. Check duplicate (early return)
7. Load image cu Intervention direct din content
8. Same flow ca upload() din pasul 5

**Default metadata:**
- `source` = URL (dacă nu e specificat altul)

#### **c) generateAllThumbnails(Image $image, ?array $profiles = null): array**
Generează toate thumbnail-urile pentru o imagine.

**Flow:**
1. Get profiles (specific sau toate din config)
2. Loop prin profiles
3. Loop prin formats (jpg, webp, avif)
4. Try generateThumbnail() - catch exceptions per thumbnail
5. Log errors dar nu oprește procesul
6. Flush la final
7. Return array de Thumbnail entities

#### **d) generateThumbnail(Image $image, string $profile, string $format, ?array $cropData = null): Thumbnail**
Generează un singur thumbnail.

**Flow:**
1. Validate profile exists în config
2. Validate image file exists
3. Load image cu Intervention
4. Apply crop custom (dacă există cropData)
5. Resize conform profile mode:
   - `cover` - $img->cover(width, height) - Crop to fit
   - `contain` - $img->contain(width, height) - Scale to fit
   - `crop` - $img->crop(width, height) - Center crop
   - `scale` - $img->scale(width, height) - Resize exact
6. Generate path: `thumbnails/{hash[0:2]}/{hash[2:4]}/{profile}.{format}`
7. Save cu quality settings per format (saveThumbnail)
8. Find sau create Thumbnail entity
9. Update dimensions, fileSize, path
10. Mark as ready
11. Save cropJson (dacă există)
12. Return Thumbnail

#### **e) crop(Image $image, string $profile, string $format, array $cropData): Thumbnail**
Apply custom crop la thumbnail.

**Wrapper pentru generateThumbnail cu cropData:**
```php
return $this->generateThumbnail($image, $profile, $format, $cropData);
```

**CropData formats suportate:**
```php
// Percentage (recomandat pentru UI)
['p' => ['x' => 10, 'y' => 20, 'width' => 80, 'height' => 60]]

// Absolute pixels
['x' => 100, 'y' => 200, 'width' => 800, 'height' => 600]
```

#### **f) regenerateThumbnails(Image $image, ?array $profiles = null): void**
Regenerează thumbnails (după edit imagine sau profile changes).

**Flow:**
1. Get thumbnails to regenerate (filtrează după profiles dacă specific)
2. Delete old thumbnail files (disk)
3. Remove Thumbnail entities
4. Flush
5. Generate new thumbnails (generateAllThumbnails)

#### **g) delete(Image $image): void**
Șterge imagine + toate thumbnails.

**Flow:**
1. Delete toate thumbnail files (disk)
2. Delete original file (disk)
3. Remove entity (cascade va șterge Thumbnail entities)
4. Flush

#### **h) getPublicUrl(Image $image): string**
Returnează public URL pentru imagine originală.

```php
return $this->publicPath . '/' . $image->getPath();
// Ex: /uploads/images/originals/ab/cd/abcdef...123.jpg
```

#### **i) getThumbnailUrl(Thumbnail $thumbnail): string**
Returnează public URL pentru thumbnail.

```php
return $this->publicPath . '/' . $thumbnail->getPath();
// Ex: /uploads/images/thumbnails/ab/cd/article_card.jpg
```

#### **j) flush(): void**
Flush EntityManager (helper pentru batch operations).

**Metode private (helper methods):**

1. **extractMetadata(string $path): array**
   - Return ['width' => int, 'height' => int]

2. **generateBlurPlaceholder(string $path): string**
   - Resize la 20x20
   - Apply blur(2)
   - Encode JPEG quality 60
   - Return base64 data URI

3. **extractDominantColor(string $path): string**
   - Resize la 100x100 (fast)
   - Pick center pixel color
   - Return hex (#RRGGBB)

4. **ensureDirectory(string $path): void**
   - Create directories recursive (0755)

5. **convertPercentageCrop(Image $image, array $cropData): array**
   - Convert % → absolute pixels
   - Return ['x', 'y', 'width', 'height']

6. **validateCropData(Image $image, array $cropData): void**
   - Validate boundaries
   - Throw InvalidArgumentException dacă invalid

7. **saveThumbnail(ImageInterface $img, string $path, string $format): void**
   - Match format:
     - jpg/jpeg → toJpeg(quality)->save()
     - webp → toWebp(quality)->save()
     - png → toPng()->save()
     - avif → toAvif(quality)->save()

8. **getImagePath(Image $image): string**
   - Return absolute path: `{storageRoot}/{image->getPath()}`

9. **getThumbnailPath(Image $image, string $profile, string $format): string**
   - Return absolute path pentru thumbnail

10. **validateFile(UploadedFile $file): void**
    - Check isValid()
    - Check size <= maxSize
    - Check MIME type în allowed_types
    - Throw InvalidArgumentException

---

### **6. Repositories**

#### **ImageRepository** (`src/Repository/ImageRepository.php`)

```php
class ImageRepository extends ServiceEntityRepository
{
    /**
     * Find image by SHA-256 hash (pentru deduplicare).
     */
    public function findByHash(string $hash): ?Image
    {
        return $this->findOneBy(['hash' => $hash]);
    }

    /**
     * Find images uploaded today.
     */
    public function findUploadedToday(): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.createdAt >= :today')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get total storage used (bytes).
     */
    public function getTotalStorageUsed(): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('SUM(i.size)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
```

#### **ThumbnailRepository** (`src/Repository/ThumbnailRepository.php`)

```php
class ThumbnailRepository extends ServiceEntityRepository
{
    /**
     * Find pending thumbnails (pentru monitoring/retry).
     */
    public function findPending(): array
    {
        return $this->findBy(['status' => 'pending'], ['createdAt' => 'ASC']);
    }

    /**
     * Find failed thumbnails.
     */
    public function findFailed(): array
    {
        return $this->findBy(['status' => 'failed'], ['createdAt' => 'DESC']);
    }

    /**
     * Find thumbnails with custom crop.
     */
    public function findWithCustomCrop(): array
    {
        return $this->findBy(['hasCustomCrop' => true]);
    }

    /**
     * Count thumbnails by status.
     */
    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
```

---

### **7. Async Processing - Symfony Messenger**

#### **Message** (`src/Message/GenerateImageThumbnailsMessage.php`)

```php
<?php

declare(strict_types=1);

namespace App\Message;

final readonly class GenerateImageThumbnailsMessage
{
    public function __construct(
        public int $imageId,
        public ?array $profiles = null,
    ) {
    }
}
```

#### **Handler** (`src/MessageHandler/GenerateImageThumbnailsHandler.php`)

```php
<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\GenerateImageThumbnailsMessage;
use App\Repository\ImageRepository;
use App\Service\ImageService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GenerateImageThumbnailsHandler
{
    public function __construct(
        private ImageRepository $imageRepository,
        private ImageService $imageService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(GenerateImageThumbnailsMessage $message): void
    {
        $this->logger->info('Processing thumbnail generation', [
            'imageId' => $message->imageId,
            'profiles' => $message->profiles,
        ]);

        $image = $this->imageRepository->find($message->imageId);

        if (!$image) {
            $this->logger->error('Image not found', ['imageId' => $message->imageId]);
            return;
        }

        try {
            $this->imageService->generateAllThumbnails($image, $message->profiles);

            $this->logger->info('Thumbnails generated successfully', [
                'imageId' => $message->imageId,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate thumbnails', [
                'imageId' => $message->imageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // Retry via Messenger
        }
    }
}
```

#### **Messenger Config** (`config/packages/messenger.yaml`)

Update routing pentru async:

```yaml
framework:
    messenger:
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                retry_strategy:
                    max_retries: 3
                    delay: 1000
                    multiplier: 2
                    max_delay: 0

        routing:
            'App\Message\GenerateImageThumbnailsMessage': async
```

---

### **8. Migrations**

```bash
# Generate migration
php bin/console make:migration

# Review migration file
# Should create: image, thumbnail tables + indexes

# Run migration
php bin/console doctrine:migrations:migrate
```

**Expected tables:**
- `image` (with idx_image_hash)
- `thumbnail` (with uniq_image_profile_format, idx_thumbnail_status, idx_thumbnail_custom_crop)

---

### **9. Testing**

**Setup test directories:**
```bash
mkdir -p public/uploads/images/originals
mkdir -p public/uploads/images/thumbnails
chmod -R 775 public/uploads/images
```

**Test upload:**
```php
// In controller or test
$image = $imageService->upload($uploadedFile, [
    'altText' => 'Test image',
    'description' => 'A test image description',
    'author' => 'John Photographer',
]);

// Verify
assert($image->getHash() !== null);
assert($image->getBlurPlaceholder() !== null);
assert($image->getDominantColor() !== null);
```

**Test deduplication:**
```php
$image1 = $imageService->upload($file1);
$image2 = $imageService->upload($file1); // Same file

assert($image1->getId() === $image2->getId()); // Same entity returned
```

**Test async thumbnails:**
```bash
# Start worker
symfony console messenger:consume async -vv

# Upload image (dispatch message)
# Worker will generate thumbnails

# Check status
SELECT * FROM thumbnail WHERE image_id = X;
```

**Manual thumbnail generation:**
```php
$thumbnail = $imageService->generateThumbnail($image, 'article_card', 'webp');
assert($thumbnail->getStatus() === 'ready');
assert(file_exists('public' . $thumbnail->getPath()));
```

---

### **10. Deliverables**

**✅ Sprint 2 Complete Checklist:**

- [x] Intervention Image v3 installed & configured
- [x] Image entity (hash, blurPlaceholder, dominantColor)
- [x] Thumbnail entity (status, cropJson, hasCustomCrop)
- [x] image.yaml configuration (8 profiles, 3 formats)
- [x] ImageService (10 public methods)
- [x] ImageRepository (findByHash, getTotalStorageUsed)
- [x] ThumbnailRepository (findPending, findFailed)
- [x] GenerateImageThumbnailsMessage
- [x] GenerateImageThumbnailsHandler
- [x] Messenger routing configured
- [x] Migrations run successfully
- [x] Storage directories created
- [x] Upload test passed
- [x] Deduplication test passed
- [x] Async generation test passed
- [x] Crop test passed

---

### **Features Implementate**

1. ✅ **Hash-based Deduplication** - Zero duplicate storage
2. ✅ **Blur Placeholders** - Progressive loading UX
3. ✅ **Dominant Color** - Skeleton placeholders
4. ✅ **Multi-format** - JPG + WebP + AVIF
5. ✅ **Async Generation** - No upload timeouts
6. ✅ **Custom Crop** - Per thumbnail
7. ✅ **Progressive JPEG** - Faster perceived loading
8. ✅ **Hash Subdirectories** - Scalable storage
9. ✅ **Upload from URL** - External images
10. ✅ **Status Tracking** - Monitor generation progress

---

### **Next: Sprint 3 - Article Management**

După finalizarea Sprint 2, sistemul de imagini este complet funcțional și gata pentru integrare cu Article entity.
