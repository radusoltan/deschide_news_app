# Media & Thumbnail Service Documentation

## Overview

The media service handles:
- Image uploads (original files)
- Automatic thumbnail generation (10 profiles)
- Image metadata extraction
- CDN integration
- Async thumbnail processing

## Architecture

### Components

1. **ImageService** (`src/Service/ImageService.php`)
   - Handles original image uploads
   - Extracts metadata (dimensions, MIME type, size)
   - Triggers thumbnail generation

2. **VichUploaderBundle**
   - Manages file upload process
   - Generates unique filenames
   - Stores files in `/public/uploads/images/`

3. **Intervention/Image**
   - Image manipulation library
   - Resizing, cropping, format conversion
   - WebP generation

4. **Symfony Messenger** (optional)
   - Async thumbnail generation
   - Background processing for large images

## Upload Flow

### Step 1: Frontend Upload

**Request**:
```http
POST /api/images
Content-Type: multipart/form-data
Authorization: Bearer {jwt_token}

------WebKitFormBoundary
Content-Disposition: form-data; name="file"; filename="photo.jpg"
Content-Type: image/jpeg

{binary_data}
------WebKitFormBoundary
Content-Disposition: form-data; name="alt"

Mountain landscape
------WebKitFormBoundary--
```

### Step 2: Backend Processing

```php
// src/State/ImageProcessor.php

public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
{
    // 1. Upload file (VichUploader)
    $uploadedFile = $data->getFile();
    
    // 2. Generate unique filename
    $filename = $this->generateUniqueFilename($uploadedFile);
    
    // 3. Extract metadata
    $metadata = $this->imageService->extractMetadata($uploadedFile);
    $data->setWidth($metadata['width']);
    $data->setHeight($metadata['height']);
    $data->setMimeType($metadata['mimeType']);
    $data->setSize($metadata['size']);
    
    // 4. Save to database
    $this->entityManager->persist($data);
    $this->entityManager->flush();
    
    // 5. Trigger thumbnail generation (sync or async)
    $this->imageService->generateThumbnails($data);
    
    return $data;
}
```

### Step 3: Thumbnail Generation

```php
// src/Service/ImageService.php

public function generateThumbnails(Image $image): void
{
    $profiles = $this->thumbnailProfileRepository->findAll();
    
    foreach ($profiles as $profile) {
        $this->generateThumbnail($image, $profile);
    }
}

private function generateThumbnail(Image $image, ThumbnailProfile $profile): Thumbnail
{
    // Load original image
    $originalPath = $this->uploadsDir . '/' . $image->getPath();
    $img = Image::make($originalPath);
    
    // Resize according to profile
    switch ($profile->getCropMode()) {
        case 'fit':
            $img->fit($profile->getWidth(), $profile->getHeight());
            break;
        case 'resize':
            $img->resize($profile->getWidth(), $profile->getHeight(), function ($constraint) {
                $constraint->aspectRatio();
            });
            break;
        case 'crop':
            $img->crop($profile->getWidth(), $profile->getHeight());
            break;
    }
    
    // Convert to WebP (if configured)
    if ($profile->getFormat() === 'webp') {
        $img->encode('webp', $profile->getQuality());
    }
    
    // Save thumbnail
    $thumbnailPath = $this->getThumbnailPath($image, $profile);
    $img->save($thumbnailPath);
    
    // Create Thumbnail entity
    $thumbnail = new Thumbnail();
    $thumbnail->setImage($image);
    $thumbnail->setProfile($profile);
    $thumbnail->setFilename($this->getThumbnailFilename($image, $profile));
    $thumbnail->setPath($this->getRelativePath($thumbnailPath));
    $thumbnail->setWidth($img->width());
    $thumbnail->setHeight($img->height());
    $thumbnail->setFileSize(filesize($thumbnailPath));
    
    $this->entityManager->persist($thumbnail);
    $this->entityManager->flush();
    
    return $thumbnail;
}
```

## Storage Structure

### Directory Layout

```
public/uploads/
├── images/                          # Original images
│   ├── image_67890abc12345.jpg
│   ├── image_67890abc12346.png
│   └── ...
└── thumbnails/                      # Generated thumbnails
    ├── hero_big/                    # 1920x1080 WebP
    │   ├── image_67890abc12345.webp
    │   └── image_67890abc12346.webp
    ├── hero_small/                  # 800x600 WebP
    │   ├── image_67890abc12345.webp
    │   └── ...
    ├── article_main/                # 1600x900 WebP
    ├── article_inline/              # 1200x675 WebP
    ├── card_large/                  # 800x600 WebP
    ├── card_medium/                 # 600x400 WebP
    ├── card_small/                  # 400x300 WebP
    ├── list_item/                   # 300x200 WebP
    ├── mobile_hero/                 # 800x600 WebP
    └── gallery/                     # 1920x600 WebP
```

### Filename Convention

**Original Images**:
```
image_{unique_id}_{width}x{height}_{color}.{ext}
```
Example: `image_69020ece588bd_800x600_ef4444.png`

**Thumbnails**:
```
image_{unique_id}.webp
```
Example: `image_69020ece588bd.webp`

## Thumbnail Profiles

### Configuration

Defined in `src/Entity/ThumbnailProfile.php` and seeded via fixtures:

```php
// src/DataFixtures/ThumbnailProfileFixtures.php

$profiles = [
    [
        'name' => 'hero_big',
        'width' => 1920,
        'height' => 1080,
        'format' => 'webp',
        'quality' => 85,
        'crop_mode' => 'fit',
        'description' => 'Hero section main image'
    ],
    [
        'name' => 'hero_small',
        'width' => 800,
        'height' => 600,
        'format' => 'webp',
        'quality' => 80,
        'crop_mode' => 'fit',
        'description' => 'Hero section smaller images'
    ],
    // ... 8 more profiles
];
```

### Profile Details

| Profile | Dimensions | Format | Quality | Crop Mode | Use Case |
|---------|------------|--------|---------|-----------|----------|
| `hero_big` | 1920×1080 | WebP | 85% | Fit | Homepage hero main |
| `hero_small` | 800×600 | WebP | 80% | Fit | Homepage hero secondary |
| `article_main` | 1600×900 | WebP | 85% | Fit | Article detail header |
| `article_inline` | 1200×675 | WebP | 80% | Fit | Inline article images |
| `card_large` | 800×600 | WebP | 80% | Fit | Large article cards |
| `card_medium` | 600×400 | WebP | 75% | Fit | Medium article cards |
| `card_small` | 400×300 | WebP | 75% | Fit | Small article cards |
| `list_item` | 300×200 | WebP | 70% | Fit | List view thumbnails |
| `mobile_hero` | 800×600 | WebP | 80% | Fit | Mobile hero images |
| `gallery` | 1920×600 | WebP | 85% | Fit | Wide gallery images |

### Crop Modes

- **fit**: Scale to fit inside dimensions, maintain aspect ratio
- **resize**: Resize proportionally to fit
- **crop**: Crop to exact dimensions (center crop)

## CDN Integration

### Development Environment

**CDN URL**: `http://127.0.0.1:8082`

Static file server serves content from `/public/uploads/`

### Production Environment

**Recommended**: Use dedicated CDN (Cloudflare, AWS CloudFront, etc.)

**Configuration**:
```yaml
# config/packages/images.yaml
parameters:
  cdn_url: '%env(CDN_URL)%'
```

**Frontend Usage**:
```typescript
// Frontend .env.local
NEXT_PUBLIC_CDN_URL=http://127.0.0.1:8082

// Build image URL
const imageUrl = `${process.env.NEXT_PUBLIC_CDN_URL}/uploads/${image.path}`;
// Example: http://127.0.0.1:8082/uploads/images/image_69020abc.png

const thumbnailUrl = `${process.env.NEXT_PUBLIC_CDN_URL}/uploads/${thumbnail.path}`;
// Example: http://127.0.0.1:8082/uploads/thumbnails/hero_big/image_69020abc.webp
```

## API Responses

### Image Entity Response

```json
{
  "@context": "/api/contexts/Image",
  "@id": "/api/images/1",
  "@type": "Image",
  "id": 1,
  "filename": "image_69020ece588bd_800x600_ef4444.png",
  "path": "images/image_69020ece588bd_800x600_ef4444.png",
  "originalFilename": "mountain-photo.png",
  "width": 800,
  "height": 600,
  "mimeType": "image/png",
  "size": 245678,
  "alt": "Mountain landscape at sunset",
  "createdAt": "2025-11-05T10:00:00+00:00",
  "updatedAt": "2025-11-05T10:00:00+00:00"
}
```

### Article with Images Response

```json
{
  "@context": "/api/contexts/Article",
  "@id": "/api/articles/140",
  "@type": "Article",
  "id": 140,
  "title": "Breaking News Title",
  "articleImages": [
    {
      "id": 109,
      "image": {
        "id": 1,
        "filename": "image_69020ece588bd.png",
        "path": "images/image_69020ece588bd.png",
        "width": 800,
        "height": 600,
        "mimeType": "image/png",
        "alt": "Mountain landscape"
      },
      "position": 0,
      "isFeatured": true
    }
  ]
}
```

## Async Thumbnail Generation

### Symfony Messenger Integration

**Message Class**:
```php
// src/Message/GenerateThumbnailsMessage.php

class GenerateThumbnailsMessage
{
    public function __construct(
        private int $imageId
    ) {}
    
    public function getImageId(): int
    {
        return $this->imageId;
    }
}
```

**Message Handler**:
```php
// src/MessageHandler/GenerateThumbnailsHandler.php

#[AsMessageHandler]
class GenerateThumbnailsHandler
{
    public function __invoke(GenerateThumbnailsMessage $message): void
    {
        $image = $this->imageRepository->find($message->getImageId());
        
        if (!$image) {
            throw new \RuntimeException('Image not found');
        }
        
        $this->imageService->generateThumbnails($image);
    }
}
```

**Dispatch Message**:
```php
// In ImageProcessor
$this->messageBus->dispatch(new GenerateThumbnailsMessage($image->getId()));
```

**Configuration**:
```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    transports:
      async: '%env(MESSENGER_TRANSPORT_DSN)%'
    
    routing:
      'App\Message\GenerateThumbnailsMessage': async
```

**Run Worker**:
```bash
symfony console messenger:consume async -vv
```

## Image Validation

### Upload Constraints

```php
// src/Entity/Image.php

#[Assert\File(
    maxSize: '10M',
    mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    mimeTypesMessage: 'Please upload a valid image (JPEG, PNG, WebP, or GIF)'
)]
private ?UploadedFile $file = null;
```

### Dimension Validation

```php
// src/Validator/ImageDimensions.php

#[Constraint]
class ImageDimensions extends Constraint
{
    public int $minWidth = 400;
    public int $minHeight = 300;
    public int $maxWidth = 5000;
    public int $maxHeight = 5000;
    public string $message = 'Image dimensions must be between {{ minWidth }}×{{ minHeight }} and {{ maxWidth }}×{{ maxHeight }}';
}
```

## Cleanup & Maintenance

### Delete Orphaned Thumbnails

```bash
# Delete thumbnails for deleted images
php bin/console app:cleanup-thumbnails
```

**Implementation**:
```php
// src/Command/CleanupThumbnailsCommand.php

protected function execute(InputInterface $input, OutputInterface $output): int
{
    // Find thumbnails without parent image
    $orphanedThumbnails = $this->thumbnailRepository->findOrphaned();
    
    foreach ($orphanedThumbnails as $thumbnail) {
        // Delete file
        $filePath = $this->uploadsDir . '/' . $thumbnail->getPath();
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete entity
        $this->entityManager->remove($thumbnail);
    }
    
    $this->entityManager->flush();
    
    $output->writeln(sprintf('Deleted %d orphaned thumbnails', count($orphanedThumbnails)));
    
    return Command::SUCCESS;
}
```

## Performance Optimization

### Lazy Loading Prevention

Use eager loading in providers:

```php
// src/State/ArticleProvider.php

$queryBuilder = $repository->createQueryBuilder('a')
    ->leftJoin('a.articleImages', 'ai')
    ->addSelect('ai')
    ->leftJoin('ai.image', 'img')
    ->addSelect('img')
    ->orderBy('ai.position', 'ASC');
```

### Caching Strategy

**CDN Caching**:
- Cache-Control headers for static assets
- Aggressive caching (1 year) for thumbnails
- Filename-based cache busting

**Redis Caching**:
```php
// Cache image metadata
$cache->set("image_{$imageId}", $imageData, 3600); // 1 hour
```

## Security Considerations

### File Upload Security

1. **MIME Type Validation**: Check actual file content, not just extension
2. **File Size Limits**: Max 10MB per upload
3. **Filename Sanitization**: Remove special characters, prevent path traversal
4. **Storage Outside Web Root**: (Future enhancement)
5. **Virus Scanning**: (Planned for production)

### Access Control

- **Public Access**: Original images and thumbnails (read-only)
- **Authenticated Access**: Upload/delete (ROLE_EDITOR+)
- **Admin Access**: Bulk operations (ROLE_ADMIN)

## Troubleshooting

### "Permission denied" on uploads

```bash
# Set correct permissions
chmod -R 775 public/uploads/
chown -R www-data:www-data public/uploads/
```

### Thumbnails not generating

```bash
# Check GD/Imagick extension
php -m | grep -E "gd|imagick"

# Check disk space
df -h public/uploads/

# Check messenger worker running
symfony console messenger:stats
```

### "Intervention\Image\Exception\NotReadableException"

**Cause**: Missing GD or Imagick extension

**Solution**:
```bash
# Install GD (Debian/Ubuntu)
sudo apt-get install php8.4-gd

# Restart PHP-FPM
sudo systemctl restart php8.4-fpm
```

---

**Last Updated**: November 2025
**Service Version**: 1.0
**Dependencies**: VichUploaderBundle, Intervention/Image
