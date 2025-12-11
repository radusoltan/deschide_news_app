# Plan de Dezvoltare Backend - Deschide News App

**Data creare:** 2025-10-27
**Ultima actualizare:** 2025-10-27

**NOTE:** Acest plan acoperă doar dezvoltarea backend-ului. Frontend are plan separat.

## Stack Tehnologic Backend

**Core:**
- Symfony 7.3 (PHP 8.4)
- PostgreSQL 17
- Redis (DB 1)
- RabbitMQ
- Elasticsearch

**Shared Services:**
- Mercure Hub (port 3000)
- Prometheus (port 9090)
- Grafana (port 3002)

**Development:**
- Port: 8081 (Symfony CLI)
- Database: `deschide_news`

---

## 📋 Starea Actuală a Proiectului

### ✅ Environment Setup (COMPLETED)

**Backend:**
- ✅ Symfony 7.3 instalat în `/var/www/deschide_news_app/deschide_backend`
- ✅ Rulează pe port 8081 (Symfony CLI)
- ✅ PHP 8.4.12 configurat
- ✅ Toate pachetele core instalate via Composer

**Infrastructure:**
- ✅ PostgreSQL 17 disponibil (5432)
- ✅ Redis disponibil (6379, DB 1 alocat)
- ✅ RabbitMQ disponibil (5672/15672)
- ✅ Elasticsearch disponibil (9200)
- ✅ Mercure Hub disponibil (3000)

**Documentație:**
- ✅ CLAUDE.md - Ghid complet pentru Claude Code
- ✅ DEVELOPMENT_ENVIRONMENT.md - Setup complet (716 linii)
- ✅ SETUP_SUMMARY.md - Quick reference
- ✅ README.md - Overview actualizat

### 📦 Pachete Backend DEJA Instalate

#### Symfony Core (7.3)
- Framework Bundle, Console, Runtime
- HTTP Foundation, Routing, Yaml
- Dotenv, Filesystem

#### Database & ORM
- **Doctrine ORM 3.5**
- **Doctrine Bundle 2.18**
- **Doctrine Migrations Bundle 3.5**
- **Doctrine DBAL 3**

#### Security & Authentication
- **Lexik JWT Authentication Bundle 3.1** ✅
- **Gesdinet JWT Refresh Token Bundle 1.5** ✅
- **Security Bundle 7.3**
- Entitatea `RefreshToken` deja creată ✅

#### Multilanguage & Extensions
- **gedmo/doctrine-extensions 3.21** ✅
- **stof/doctrine-extensions-bundle** ✅ (necesită doar configurare)

#### API & Communication
- **NelmioCorsBundle 2.6** ✅ (configurat basic, necesită ajustări)
- **Symfony Serializer 7.3** ✅ (include serializer groups support)

#### Validation & Forms
- **Symfony Validator 7.3** ✅

#### Background Jobs & Messaging
- **Symfony Messenger 7.3** ✅ (pentru async thumbnail generation)

#### Logging & Monitoring
- **Monolog Bundle 3.10** ✅

#### Development Tools
- **Symfony Maker Bundle 1.64** ✅
- **PHPStan 2.1** + **PHPStan Symfony 2.0** ✅
- **PHP CS Fixer 3.89** ✅
- **Deptrac 4.2** ✅
- **PHPUnit 12.4** ✅

#### Other Features
- **Rate Limiter 7.3** ✅
- **Workflow 7.3** ✅
- **Scheduler 7.3** ✅
- **Notifier 7.3** ✅
- **Property Info/Access 7.3** ✅

### ⚙️ Ce Necesită DOAR Configurare

1. **StofDoctrineExtensionsBundle**
   - Creare config: `config/packages/stof_doctrine_extensions.yaml`
   - Activare: translatable, sluggable, timestampable
   - Default locale: `ro`, fallback: `false`

2. **Gedmo Translation Entity Mapping**
   - Adăugare în `doctrine.yaml` mapping pentru `Gedmo\Translatable\Entity`

3. **NelmioCorsBundle**
   - Update pentru Next.js frontend URL
   - Expose headers: `Authorization`, `X-Total-Count`, `Content-Range`
   - Allow credentials: `true`

4. **Security**
   - Configurare JWT firewalls
   - User provider
   - Access control rules

5. **Database**
   - Creare database PostgreSQL
   - Update `.env` credențiale

### 🚫 Ce NU Vom Folosi

- **LiipImagineBundle** ❌ - Vom implementa sistem CUSTOM de thumbnail generation cu GD/Imagick

### 📂 Structura Actuală

```
deschide_backend/
├── config/
│   ├── packages/
│   │   ├── doctrine.yaml ✅
│   │   ├── security.yaml ⚙️ (needs full config)
│   │   ├── lexik_jwt_authentication.yaml ✅
│   │   ├── gesdinet_jwt_refresh_token.yaml ✅
│   │   ├── nelmio_cors.yaml ⚙️ (needs update)
│   │   ├── messenger.yaml ✅
│   │   └── [stof_doctrine_extensions.yaml] ❌ (to create)
│   └── jwt/ (private.pem, public.pem) ✅
├── src/
│   ├── Controller/ (empty)
│   ├── Entity/ (doar RefreshToken)
│   ├── Repository/ (empty)
│   ├── Service/ (empty)
│   ├── Message/ (empty)
│   ├── MessageHandler/ (empty)
│   ├── EventListener/ (empty)
│   ├── Enum/ (empty)
│   └── Kernel.php ✅
├── migrations/ (empty)
├── tests/ (empty)
└── public/
    └── media/ (to create)
```

---

## 🎯 Plan de Dezvoltare - 8 Sprints

### Sprint 0: Infrastructure & Configuration (3-5 zile) ⏳ IN PROGRESS

**Status:** Backend instalat și rulează. Configurare package-uri în curs.

**Obiectiv:** Configurare completă a tuturor package-urilor și serviciilor backend

#### Preconditions:
- ✅ Backend rulează pe port 8081
- ✅ Toate serviciile shared disponibile
- ✅ Documentație completă

#### Tasks Backend:

**1. Configurare StofDoctrineExtensionsBundle**

Creare `config/packages/stof_doctrine_extensions.yaml`:
```yaml
stof_doctrine_extensions:
    default_locale: ro
    translation_fallback: false
    orm:
        default:
            translatable: true
            sluggable: true
            timestampable: true
```

**2. Update Doctrine Configuration**

Adăugare în `config/packages/doctrine.yaml`:
```yaml
doctrine:
    orm:
        mappings:
            gedmo_translatable:
                type: attribute
                prefix: Gedmo\Translatable\Entity
                dir: "%kernel.project_dir%/vendor/gedmo/doctrine-extensions/src/Translatable/Entity"
                is_bundle: false
```

**3. Update NelmioCorsBundle**

Update `config/packages/nelmio_cors.yaml`:
```yaml
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
        allow_methods: ['GET', 'OPTIONS', 'POST', 'PUT', 'PATCH', 'DELETE']
        allow_headers: ['Content-Type', 'Authorization', 'Accept-Language']
        expose_headers: ['Authorization', 'X-Total-Count', 'Content-Range']
        allow_credentials: true
        max_age: 3600
    paths:
        '^/api': null
```

Update `.env.local`:
```env
# Allow both localhost:3005 (Next.js dev) and deschide.local (nginx proxy)
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1|deschide\.local)(:[0-9]+)?$'
```

**4. Configurare Security (JWT)**

Update `config/packages/security.yaml` conform `docs/security-authentication.md`:
- JWT authentication pentru `/api/login`
- Refresh token pentru `/api/token/refresh`
- Firewalls: `dev`, `login`, `api`
- Access control: `ROLE_EDITOR`, `ROLE_ADMIN`

**5. Database Setup**
```bash
# Creare database
createdb deschide_news

# Update .env
DATABASE_URL="postgresql://user:password@127.0.0.1:5432/deschide_news?serverVersion=16&charset=utf8"

# Test conexiune
symfony console dbal:run-sql "SELECT 1"
```

**6. Creare Structură Directoare**
```bash
mkdir -p public/media/uploads/images
mkdir -p public/media/thumbnails
mkdir -p src/Message
mkdir -p src/MessageHandler
mkdir -p src/Service
mkdir -p src/EventListener
mkdir -p src/Enum
```

**7. Verificare JWT Keys**
```bash
symfony console lexik:jwt:check-config
```

**8. PHPStan & CS Fixer Setup**
- Verificare `phpstan.dist.neon`
- Verificare `.php-cs-fixer.dist.php`
- Run: `vendor/bin/phpstan analyse`
- Run: `vendor/bin/php-cs-fixer fix --dry-run`

**Deliverables:**
- ✅ StofDoctrineExtensions configurat (translatable, sluggable, timestampable)
- ✅ NelmioCors configurat pentru frontend
- ✅ Security JWT complet configurat
- ✅ Database PostgreSQL `deschide_news` creată
- ✅ User PostgreSQL `deschide_user` creat
- ✅ Structură directoare pentru uploads
- ✅ JWT keys generate și validate
- ✅ Quality tools (PHPStan, CS Fixer) verificate
- ✅ Redis, RabbitMQ, Elasticsearch connections testate
- ✅ DoctrineFixturesBundle verificat (deja instalat)

**Testing:**
```bash
cd /var/www/deschide_news_app/deschide_backend
symfony console doctrine:query:sql "SELECT 1"
redis-cli -n 1 PING
curl http://127.0.0.1:8081/api
```

---

### Sprint 1: Core Entities & User Management (7-10 zile)

**Obiectiv:** Entități de bază și sistem complet de autentificare

**Note:** Frontend tasks pentru acest sprint sunt în plan separat.

#### Tasks:

**1. Enum Classes**

Creare în `src/Enum/`:
```php
// UserRole.php
enum UserRole: string {
    case ROLE_EDITOR = 'ROLE_EDITOR';
    case ROLE_ADMIN = 'ROLE_ADMIN';
}

// CategoryStatus.php
enum CategoryStatus: string {
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
```

**2. Entitatea User**

Conform `docs/security-authentication.md`:
```bash
symfony console make:user User
```

Fields:
- `username` (string, unique, 180) - **USED FOR LOGIN**
- `email` (string, unique, 180) - pentru notificări email (funcționalitate viitoare)
- `roles` (json)
- `password` (string)
- `firstName` (string, 100)
- `lastName` (string, 100)
- `isActive` (boolean, default true)
- `createdAt`, `updatedAt` (Gedmo Timestampable)

Implementează: `UserInterface`, `PasswordAuthenticatedUserInterface`

**IMPORTANT:** Autentificarea se face cu `username`, NU cu email!

**3. Entitatea Category**

Conform `docs/entity-category.md`:
```bash
symfony console make:entity Category
```

Fields:
- `title` (string, 255) - **Gedmo Translatable**
- `slug` (string, 255, unique) - **Gedmo Sluggable** din title
- `status` (CategoryStatus enum)
- `onFrontPage` (boolean, default false)
- `position` (integer, default 0)
- `createdAt`, `updatedAt` (Gedmo Timestampable)

**4. Entitatea Author**

Conform `docs/entity-author.md`:
```bash
symfony console make:entity Author
```

Fields:
- `user` (OneToOne cu User, nullable)
- `firstName` (string, 100)
- `lastName` (string, 100)
- `email` (string, 180, unique)
- `slug` (string, 255, unique) - **Gedmo Sluggable**
- `bio` (text, nullable) - **Gedmo Translatable**
- `profileImage` (ManyToOne cu Image, nullable)
- Social: `twitter`, `facebook`, `linkedin`, `website`
- `createdAt`, `updatedAt` (Gedmo Timestampable)

Methods: `getFullName()`, `getInitials()`

**5. Repositories**

Generate automatic + add custom methods:
- `UserRepository->findByUsername()` (pentru login)
- `UserRepository->findByEmail()` (pentru notificări)
- `CategoryRepository->findActive()`, `findFrontPage()`
- `AuthorRepository->findBySlug()`

**6. Migrations**
```bash
symfony console make:migration
symfony console doctrine:migrations:migrate
```

**7. Fixtures cu DoctrineFixturesBundle**

Creare fixtures în `src/DataFixtures/`:
```bash
symfony console make:fixtures AppFixtures
```

Create separate fixture classes:
- `UserFixtures.php` - Admin user (username: admin, email: admin@deschide.com)
- `CategoryFixtures.php` - 5 categorii de bază (Politică, Economie, Sport, Cultură, Tehnologie)

Load fixtures:
```bash
symfony console doctrine:fixtures:load
```

**8. Authentication Implementation**

**UserRepository:**
- Implementare methods pentru authentication

**Controllers:**
- `UserController` (GET `/api/me`)
- `Admin/UserManagementController` (CRUD, doar ROLE_ADMIN)

**Security config finalizare:**
- User provider cu Doctrine (property: `username`)
- Password hasher
- JWT success/failure handlers (Lexik default)

**Login credentials:**
- Username: admin
- Password: (set in fixtures)

**9. Testing**
- Unit tests pentru User entity
- Tests pentru UserRepository
- Integration tests pentru `/api/login`, `/api/token/refresh`
- Functional tests pentru `/api/me`

**Deliverables:**
- ✅ 3 entități: User, Category, Author
- ✅ 2 enums: UserRole, CategoryStatus
- ✅ Repositories cu custom methods
- ✅ Migrations executate
- ✅ Seed data în database
- ✅ Authentication endpoints funcționale
- ✅ Tests pentru authentication

**Endpoints Sprint 1:**
- `POST /api/login` - Login (Lexik handler)
- `POST /api/token/refresh` - Refresh JWT
- `GET /api/me` - Current user info
- `GET /api/admin/users` - List users (admin)
- `POST /api/admin/users` - Create user (admin)
- `PUT /api/admin/users/{id}` - Update user (admin)
- `DELETE /api/admin/users/{id}` - Delete user (admin)

---

### Sprint 2: Image Management System cu Intervention Image (10-14 zile)

**Obiectiv:** Sistem complet de imagini cu Intervention Image v3, hash-based deduplication, blur placeholders și async thumbnail generation

**Tehnologii:** Intervention Image v3, Symfony Messenger (RabbitMQ), Progressive JPEG

#### Tasks:

**1. Setup Intervention Image**

```bash
composer require intervention/image
```

Configurare `config/services/intervention.yaml`:
```yaml
services:
    Intervention\Image\ImageManager:
        factory: ['Intervention\Image\Drivers\Gd\Driver', 'create']
        # Alternative: Use Imagick if available
        # factory: ['Intervention\Image\Drivers\Imagick\Driver', 'create']
```

**2. Image Entity** (`src/Entity/Image.php`)

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

**3. Thumbnail Entity** (`src/Entity/Thumbnail.php`)

**Câmpuri:**
- `image` (ManyToOne cu Image, onDelete CASCADE)
- `profile` (string, 50) - Nume profil (article_card, article_hero, etc.)
- `format` (string, 10) - Format (jpg, webp, png, avif)
- `path` (string, 255) - Cale publică (alias: publicPath)
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

**4. Image Configuration** (`config/services/image.yaml`)

```yaml
parameters:
    # Storage paths
    image.storage.root: '%kernel.project_dir%/public/uploads/images'
    image.storage.originals_dir: 'originals'
    image.storage.thumbnails_dir: 'thumbnails'
    image.storage.public_path: '/uploads/images'

    # Upload constraints
    image.upload.max_size: 5242880  # 5MB (news nu necesită 10MB)
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
    image.thumbnail.interlace: true
    image.thumbnail.optimize: true

    # Blur placeholder settings
    image.blur_placeholder.size: 20
    image.blur_placeholder.blur: 2
    image.blur_placeholder.quality: 60
```

**5. ImageService** (`src/Service/ImageService.php`)

Service principal cu 10 metode publice:

```php
class ThumbnailGeneratorService
{
    public function __construct(
        private string $uploadDir,
        private string $thumbnailDir,
        private EntityManagerInterface $em
    ) {}

    public function generateThumbnail(
        Image $image,
        ThumbnailProfile $profile
    ): Thumbnail {
        // Detectare GD vs Imagick
        $engine = $this->detectImageEngine();

        // Load source image
        $sourcePath = $this->uploadDir . '/' . $image->getPath();
        $sourceImg = $this->loadImage($sourcePath, $engine);

        // Calculate target dimensions
        [$targetWidth, $targetHeight] = $this->calculateDimensions(
            $image->getWidth(),
            $image->getHeight(),
            $profile
        );

        // Resize based on mode
        $thumbnail = match($profile->getMode()) {
            'crop' => $this->cropImage($sourceImg, $targetWidth, $targetHeight, $engine),
            'fit' => $this->fitImage($sourceImg, $targetWidth, $targetHeight, $engine),
            'fill' => $this->fillImage($sourceImg, $targetWidth, $targetHeight, $engine),
        };

        // Generate filename & path
        $filename = $this->generateThumbnailFilename($image, $profile);
        $path = $profile->getName() . 'development-plan.md/' . $filename;
        $fullPath = $this->thumbnailDir . '/' . $path;

        // Save thumbnail
        $this->saveImage($thumbnail, $fullPath, $profile->getQuality(), $engine);

        // Create Thumbnail entity
        $thumbnailEntity = new Thumbnail();
        $thumbnailEntity->setImage($image);
        $thumbnailEntity->setProfile($profile);
        $thumbnailEntity->setFilename($filename);
        $thumbnailEntity->setPath($path);
        $thumbnailEntity->setWidth($targetWidth);
        $thumbnailEntity->setHeight($targetHeight);
        $thumbnailEntity->setSize(filesize($fullPath));

        return $thumbnailEntity;
    }

    private function detectImageEngine(): string
    {
        if (extension_loaded('imagick')) {
            return 'imagick';
        }
        if (extension_loaded('gd')) {
            return 'gd';
        }
        throw new \RuntimeException('No image processing library available');
    }

    private function cropImage($img, int $width, int $height, string $engine)
    {
        // Implementation pentru crop (center crop)
    }

    private function fitImage($img, int $width, int $height, string $engine)
    {
        // Implementation pentru fit (maintain aspect, no crop)
    }

    private function fillImage($img, int $width, int $height, string $engine)
    {
        // Implementation pentru fill (stretch)
    }
}
```

**4. ImageUploadService**

```php
class ImageUploadService
{
    public function uploadImage(
        UploadedFile $file,
        ?string $alt = null,
        ?string $imageAuthor = null
    ): Image {
        // Validate file
        $this->validateImage($file);

        // Generate unique filename
        $filename = $this->generateUniqueFilename($file);

        // Organize by date: /2025/10/filename.jpg
        $relativePath = date('Y') . '/' . date('m') . '/' . $filename;
        $fullPath = $this->uploadDir . '/' . $relativePath;

        // Create directory if needed
        $this->filesystem->mkdir(dirname($fullPath));

        // Move file
        $file->move(dirname($fullPath), $filename);

        // Extract metadata
        [$width, $height] = getimagesize($fullPath);

        // Create Image entity
        $image = new Image();
        $image->setFilename($filename);
        $image->setPath($relativePath);
        $image->setMimeType($file->getMimeType());
        $image->setSize($file->getSize());
        $image->setWidth($width);
        $image->setHeight($height);
        $image->setAlt($alt);
        $image->setImageAuthor($imageAuthor);

        $this->em->persist($image);
        $this->em->flush();

        // Dispatch message pentru thumbnail generation
        $this->messageBus->dispatch(
            new GenerateThumbnailsMessage($image->getId())
        );

        return $image;
    }

    private function validateImage(UploadedFile $file): void
    {
        // Max size: 10MB
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new \InvalidArgumentException('File too large');
        }

        // Allowed types
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedTypes)) {
            throw new \InvalidArgumentException('Invalid file type');
        }
    }
}
```

**5. Messenger: Async Thumbnail Generation**

**Message:**
```php
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

**Handler:**
```php
#[AsMessageHandler]
class GenerateThumbnailsHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ThumbnailGeneratorService $thumbnailGenerator,
        private ThumbnailProfileRepository $profileRepository
    ) {}

    public function __invoke(GenerateThumbnailsMessage $message): void
    {
        $image = $this->em->find(Image::class, $message->getImageId());

        if (!$image) {
            return;
        }

        // Get all profiles
        $profiles = $this->profileRepository->findAll();

        // Generate thumbnail pentru fiecare profile
        foreach ($profiles as $profile) {
            $thumbnail = $this->thumbnailGenerator->generateThumbnail($image, $profile);
            $this->em->persist($thumbnail);
        }

        $this->em->flush();
    }
}
```

**6. ImageDeleteService**

```php
class ImageDeleteService
{
    public function deleteImage(Image $image): void
    {
        // Check if image is used in articles
        if ($image->getArticleImages()->count() > 0) {
            throw new \LogicException(
                'Cannot delete image that is used in articles'
            );
        }

        // Delete physical file
        $imagePath = $this->uploadDir . '/' . $image->getPath();
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }

        // Delete all thumbnails
        foreach ($image->getThumbnails() as $thumbnail) {
            $thumbPath = $this->thumbnailDir . '/' . $thumbnail->getPath();
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }
        }

        // Delete from database (cascade will handle thumbnails)
        $this->em->remove($image);
        $this->em->flush();
    }
}
```

**7. API Controllers**

**Public/ImageController:**
```php
#[Route('/api/public/images')]
class ImageController
{
    #[Route('/{id}', methods: ['GET'])]
    public function getImage(int $id): JsonResponse
    {
        // Return image with all thumbnails
    }
}
```

**Admin/ImageController:**
```php
#[Route('/api/admin/images')]
#[IsGranted('ROLE_ADMIN')] // dam posilitatea si ROLE_EDITOR sa poata manipula imagini
class ImageController
{
    #[Route('', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        // Upload image, return 202 Accepted
        // Thumbnails generating in background
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // Paginated list
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        // Single image with thumbnails
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        // Delete if not used
    }

    #[Route('/{id}/regenerate-thumbnails', methods: ['POST'])]
    public function regenerateThumbnails(int $id): JsonResponse
    {
        // Re-dispatch GenerateThumbnailsMessage
    }
}
```

**8. Serialization Groups**

```php
#[Groups(['image:read', 'image:list'])]
private ?int $id = null;

#[Groups(['image:read', 'image:list', 'article:read'])]
private ?string $path = null;
```

**9. Testing**
- Unit tests pentru ThumbnailGeneratorService (mock GD/Imagick)
- Tests pentru ImageUploadService
- Tests pentru message handling
- Integration tests pentru upload API
- Tests pentru delete cu relationship checks

**Deliverables:**
- ✅ 3 entități: Image, Thumbnail, ThumbnailProfile
- ✅ ThumbnailGeneratorService custom (GD + Imagick support)
- ✅ ImageUploadService, ImageDeleteService
- ✅ Async thumbnail generation via Messenger
- ✅ 10 thumbnail profiles în database
- ✅ Upload API funcțional (202 Accepted)
- ✅ Public API pentru images
- ✅ Serialization groups
- ✅ Tests complete

**Endpoints Sprint 2:**
- `POST /api/admin/images` - Upload image (202 Accepted)
- `GET /api/admin/images` - List images (paginated)
- `GET /api/admin/images/{id}` - Get image details
- `DELETE /api/admin/images/{id}` - Delete image
- `POST /api/admin/images/{id}/regenerate-thumbnails` - Regenerate
- `GET /api/public/images/{id}` - Public image access

---

### Sprint 3: Article Entity & Pivot Tables (10-14 zile)

**Obiectiv:** Entitate Article completă cu toate relațiile

#### Tasks:

**1. Enums pentru Article**

```php
// src/Enum/ArticleStatus.php
enum ArticleStatus: string {
    case NEW = 'new';
    case SUBMITTED = 'submitted';
    case PUBLISHED = 'published';
}

// src/Enum/ArticleBadge.php
enum ArticleBadge: string {
    case BREAKING = 'breaking';
    case ALERT = 'alert';
    case FLASH = 'flash';
}
```

**2. Entitatea Article**

Conform `docs/entity-article.md`:

Fields:
- `title` (string, 255) - **Gedmo Translatable**
- `slug` (string, 255, unique) - **Gedmo Sluggable**
- `lead` (text, nullable) - **Gedmo Translatable**
- `content` (text) - **Gedmo Translatable**
- `category` (ManyToOne cu Category)
- `authors` (ManyToMany cu Author) - max 5
- `status` (ArticleStatus enum)
- `badge` (ArticleBadge enum, nullable)
- `isFeatured` (boolean)
- `viewCount` (integer, default 0)
- `readingTime` (integer, nullable) - minutes
- `publishedAt` (datetime, nullable)
- `createdAt`, `updatedAt` (Gedmo Timestampable)

Relations:
- `articleImages` (OneToMany cu ArticleImage pivot)

Validation:
- Authors: min 1, max 5
- Images: min 1, max 50 (via ArticleImage count)

**3. Entitatea ArticleImage (Pivot)**

Conform `docs/entity-article-image.md`:

Fields:
- `article` (ManyToOne cu Article)
- `image` (ManyToOne cu Image)
- `position` (integer, default 0) - sorting order
- `isFeatured` (boolean, default false) - featured image
- `createdAt`

Constraints:
- Unique: `(article_id, image_id)`
- Index: `(article_id, position)`
- Index: `(article_id, is_featured)`

Business rules:
- Doar o imagine poate fi featured per article
- Position auto-increment la adăugare
- Cascade delete

**4. ArticleRepository**

Custom methods cu Gedmo query hints:
```php
public function findPublishedByLocale(
    string $locale,
    int $limit = 10,
    int $offset = 0
): array {
    $query = $this->createQueryBuilder('a')
        ->leftJoin('a.category', 'c')
        ->leftJoin('a.authors', 'authors')
        ->addSelect('c', 'authors')
        ->where('a.status = :status')
        ->setParameter('status', ArticleStatus::PUBLISHED)
        ->orderBy('a.publishedAt', 'DESC')
        ->setMaxResults($limit)
        ->setFirstResult($offset)
        ->getQuery();

    // Gedmo hints
    $query->setHint(
        \Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER,
        'Gedmo\\Translatable\\Query\\TreeWalker\\TranslationWalker'
    );
    $query->setHint(
        TranslatableListener::HINT_TRANSLATABLE_LOCALE,
        $locale
    );
    $query->setHint(
        TranslatableListener::HINT_INNER_JOIN,
        true // Strict mode
    );

    return $query->getResult();
}

public function findOneBySlugAndLocale(string $slug, string $locale): ?Article;
public function findByCategory(Category $category, string $locale): array;
public function findByAuthor(Author $author, string $locale): array;
public function findFeatured(string $locale, int $limit = 5): array;
```

**5. ArticleImageService**

```php
class ArticleImageService
{
    public function attachImage(
        Article $article,
        Image $image,
        ?int $position = null,
        bool $featured = false
    ): ArticleImage {
        // Validate: max 50 images
        if ($article->getArticleImages()->count() >= 50) {
            throw new \LogicException('Max 50 images per article');
        }

        // Validate: image not already attached
        foreach ($article->getArticleImages() as $ai) {
            if ($ai->getImage() === $image) {
                throw new \LogicException('Image already attached');
            }
        }

        // Auto position
        if ($position === null) {
            $position = $article->getArticleImages()->count();
        }

        // If featured, unfeatured others
        if ($featured) {
            $this->unfeaturedAll($article);
        }

        // Create pivot
        $articleImage = new ArticleImage();
        $articleImage->setArticle($article);
        $articleImage->setImage($image);
        $articleImage->setPosition($position);
        $articleImage->setIsFeatured($featured);

        $this->em->persist($articleImage);
        $this->em->flush();

        return $articleImage;
    }

    public function detachImage(Article $article, Image $image): void;
    public function reorderImages(Article $article, array $imageIdsInOrder): void;
    public function setFeaturedImage(Article $article, Image $image): void;
}
```

**6. Helper Methods în Article Entity**

```php
class Article
{
    public function getImages(): Collection
    {
        return $this->articleImages->map(fn($ai) => $ai->getImage());
    }

    public function getFeaturedImage(): ?Image
    {
        $featured = $this->articleImages->filter(
            fn($ai) => $ai->isFeatured()
        )->first();

        if ($featured) {
            return $featured->getImage();
        }

        // Fallback to first image
        $first = $this->articleImages->first();
        return $first ? $first->getImage() : null;
    }

    public function addAuthor(Author $author): self
    {
        if ($this->authors->count() >= 5) {
            throw new \LogicException('Max 5 authors per article');
        }
        // ...
    }

    public function calculateReadingTime(): int
    {
        // ~200 words per minute
        $words = str_word_count(strip_tags($this->content));
        return (int) ceil($words / 200);
    }
}
```

**7. Migrations**

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

**8. Testing**
- Unit tests pentru Article entity
- Tests pentru ArticleImage pivot
- Tests pentru ArticleImageService
- Tests pentru ArticleRepository query methods
- Tests pentru validation (max authors, max images)
- Tests pentru Gedmo translation

**Deliverables:**
- ✅ Article entity completă
- ✅ ArticleImage pivot entity
- ✅ 2 enums: ArticleStatus, ArticleBadge
- ✅ ArticleRepository cu Gedmo hints
- ✅ ArticleImageService
- ✅ Helper methods în Article
- ✅ Migrations executate
- ✅ Tests complete

---

### Sprint 4: Public API - Articles & Categories (7-10 zile)

**Obiectiv:** API public complet pentru frontend Next.js

#### Tasks:

**1. LocaleListener**

Conform `docs/multilanguage-system.md`:
```php
#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
class LocaleListener
{
    private const SUPPORTED_LOCALES = ['ro', 'en', 'ru'];
    private const DEFAULT_LOCALE = 'ro';

    public function __construct(
        private TranslatableListener $translatableListener
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Get locale from Accept-Language header
        $locale = $this->getLocaleFromHeader(
            $request->headers->get('Accept-Language')
        );

        $request->setLocale($locale);
        $this->translatableListener->setTranslatableLocale($locale);
    }

    private function getLocaleFromHeader(?string $header): string
    {
        // Parse Accept-Language header
        // Return best match from SUPPORTED_LOCALES
    }
}
```

**2. Public Article Controller**

```php
#[Route('/api/public/articles')]
class ArticleController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $locale = $request->getLocale();
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);

        $articles = $this->articleRepository->findPublishedByLocale(
            $locale,
            $limit,
            ($page - 1) * $limit
        );

        return $this->json($articles, context: [
            'groups' => ['article:list'],
            'locale' => $locale
        ]);
    }

    #[Route('/{slug}', methods: ['GET'])]
    public function get(string $slug, Request $request): JsonResponse
    {
        $locale = $request->getLocale();
        $article = $this->articleRepository->findOneBySlugAndLocale($slug, $locale);

        if (!$article) {
            return $this->json([
                'error' => 'Article not found or not available in this language',
                'locale' => $locale
            ], 404);
        }

        // Increment view count
        $this->articleService->incrementViewCount($article);

        return $this->json($article, context: [
            'groups' => ['article:read']
        ]);
    }

    #[Route('/featured', methods: ['GET'])]
    public function featured(Request $request): JsonResponse;

    #[Route('/category/{categorySlug}', methods: ['GET'])]
    public function byCategory(string $categorySlug, Request $request): JsonResponse;
}
```

**3. Public Category Controller**

```php
#[Route('/api/public/categories')]
class CategoryController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $locale = $request->getLocale();
        $categories = $this->categoryRepository->findActiveByLocale($locale);

        return $this->json($categories, context: [
            'groups' => ['category:list']
        ]);
    }

    #[Route('/front-page', methods: ['GET'])]
    public function frontPage(Request $request): JsonResponse
    {
        $locale = $request->getLocale();
        $categories = $this->categoryRepository->findFrontPageByLocale($locale);

        return $this->json($categories, context: [
            'groups' => ['category:list']
        ]);
    }

    #[Route('/{slug}', methods: ['GET'])]
    public function get(string $slug, Request $request): JsonResponse;
}
```

**4. Public Author Controller**

```php
#[Route('/api/public/authors')]
class AuthorController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse;

    #[Route('/{slug}', methods: ['GET'])]
    public function get(string $slug, Request $request): JsonResponse;

    #[Route('/{slug}/articles', methods: ['GET'])]
    public function articles(string $slug, Request $request): JsonResponse;
}
```

**5. Serialization Groups**

În entities, adăugare groups pentru API:
```php
// Article.php
#[Groups(['article:list', 'article:read'])]
private ?int $id = null;

#[Groups(['article:list', 'article:read'])]
private ?string $title = null;

#[Groups(['article:read'])]
private ?string $content = null;

#[Groups(['article:list', 'article:read'])]
#[MaxDepth(1)]
private ?Category $category = null;
```

**6. Pagination Helper**

```php
class PaginationHelper
{
    public function paginate(
        array $items,
        int $total,
        int $page,
        int $limit
    ): array {
        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => (int) ceil($total / $limit)
            ]
        ];
    }
}
```

**7. Response Headers pentru Pagination**

Adăugare headers:
- `X-Total-Count`
- `Content-Range`
- `Link` (prev/next)

**8. HTTP Caching**

Adăugare cache headers:
```php
$response = new JsonResponse($data);
$response->setPublic();
$response->setMaxAge(300); // 5 minutes
$response->headers->addCacheControlDirective('must-revalidate');
```

**9. Testing**
- Integration tests pentru toate endpoints
- Tests pentru locale handling
- Tests pentru pagination
- Tests pentru serialization groups
- Tests pentru 404 când traducere lipsește

**Deliverables:**
- ✅ LocaleListener activ
- ✅ 10+ public endpoints
- ✅ Serialization groups configurate
- ✅ Pagination implementată
- ✅ HTTP caching
- ✅ Tests complete

**Endpoints Sprint 4:**
- `GET /api/public/articles` - List articles (paginated, locale-aware)
- `GET /api/public/articles/{slug}` - Get article
- `GET /api/public/articles/featured` - Featured articles
- `GET /api/public/articles/category/{slug}` - Articles by category
- `GET /api/public/categories` - List categories
- `GET /api/public/categories/front-page` - Front page categories
- `GET /api/public/categories/{slug}` - Get category
- `GET /api/public/authors` - List authors
- `GET /api/public/authors/{slug}` - Get author
- `GET /api/public/authors/{slug}/articles` - Author's articles

---

### Sprint 5: Admin API - Content Management (10-14 zile)

**Obiectiv:** CMS complet pentru admin

#### Tasks:

**1. Admin Article Controller**

```php
#[Route('/api/admin/articles')]
#[IsGranted('ROLE_ADMIN')]
class ArticleController
{
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse;

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse;

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse;

    #[Route('/{id}/status', methods: ['PATCH'])]
    public function changeStatus(int $id, Request $request): JsonResponse;

    #[Route('/{id}/publish', methods: ['POST'])]
    public function publish(int $id): JsonResponse;

    #[Route('/{id}/unpublish', methods: ['POST'])]
    public function unpublish(int $id): JsonResponse;

    #[Route('/{id}/authors', methods: ['PUT'])]
    public function updateAuthors(int $id, Request $request): JsonResponse;

    #[Route('/{id}/images', methods: ['POST'])]
    public function attachImage(int $id, Request $request): JsonResponse;

    #[Route('/{id}/images/{imageId}', methods: ['DELETE'])]
    public function detachImage(int $id, int $imageId): JsonResponse;

    #[Route('/{id}/images/reorder', methods: ['PUT'])]
    public function reorderImages(int $id, Request $request): JsonResponse;

    #[Route('/{id}/images/{imageId}/featured', methods: ['POST'])]
    public function setFeaturedImage(int $id, int $imageId): JsonResponse;
}
```

**2. Admin Category Controller**

```php
#[Route('/api/admin/categories')]
#[IsGranted('ROLE_ADMIN')]
class CategoryController
{
    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse;

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse;

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse;

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse;

    #[Route('/{id}/activate', methods: ['POST'])]
    public function activate(int $id): JsonResponse;

    #[Route('/{id}/deactivate', methods: ['POST'])]
    public function deactivate(int $id): JsonResponse;
}
```

**3. Admin Author Controller**

```php
#[Route('/api/admin/authors')]
#[IsGranted('ROLE_ADMIN')]
class AuthorController
{
    // Full CRUD
    #[Route('', methods: ['GET', 'POST'])]
    #[Route('/{id}', methods: ['GET', 'PUT', 'DELETE'])]
}
```

**4. Translation Management Controller**

Conform `docs/multilanguage-system.md`:
```php
#[Route('/api/admin/translations')]
#[IsGranted('ROLE_ADMIN')]
class TranslationController
{
    #[Route('/article/{id}', methods: ['GET'])]
    public function getArticleTranslations(int $id): JsonResponse
    {
        $article = $this->articleRepository->find($id);
        $translationRepo = $this->em->getRepository(Translation::class);
        $translations = $translationRepo->findTranslations($article);

        return $this->json([
            'articleId' => $id,
            'translations' => $translations,
            'supportedLocales' => ['ro', 'en', 'ru']
        ]);
    }

    #[Route('/article/{id}/{locale}', methods: ['PUT'])]
    public function updateTranslation(
        int $id,
        string $locale,
        Request $request
    ): JsonResponse {
        $article = $this->articleRepository->find($id);
        $data = json_decode($request->getContent(), true);

        $translationRepo = $this->em->getRepository(Translation::class);
        $translationRepo->translate($article, 'title', $locale, $data['title'])
            ->translate($article, 'slug', $locale, $data['slug'])
            ->translate($article, 'lead', $locale, $data['lead'])
            ->translate($article, 'content', $locale, $data['content']);

        $this->em->persist($article);
        $this->em->flush();

        return $this->json(['message' => 'Translation updated']);
    }

    #[Route('/article/{id}/{locale}', methods: ['DELETE'])]
    public function deleteTranslation(int $id, string $locale): JsonResponse;

    #[Route('/status', methods: ['GET'])]
    public function getTranslationStatus(): JsonResponse;
}
```

**5. Input Validation**

Create DTOs pentru validation:
```php
class CreateArticleDto
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $title;

    #[Assert\NotBlank]
    public string $content;

    #[Assert\NotNull]
    public int $categoryId;

    #[Assert\Count(min: 1, max: 5)]
    public array $authorIds;

    #[Assert\Count(min: 1, max: 50)]
    public array $imageIds;
}
```

Use Symfony Validator în controllers:
```php
$errors = $this->validator->validate($dto);
if (count($errors) > 0) {
    return $this->json(['errors' => (string) $errors], 400);
}
```

**6. Custom Constraints**

```php
#[Attribute]
class MaxAuthors extends Constraint
{
    public string $message = 'Article cannot have more than {{ limit }} authors.';
}

class MaxAuthorsValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (count($value) > 5) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ limit }}', 5)
                ->addViolation();
        }
    }
}
```

**7. Error Handling**

Create custom exception listener:
```php
#[AsEventListener(event: KernelEvents::EXCEPTION)]
class ExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $response = new JsonResponse([
            'error' => $exception->getMessage(),
            'code' => $exception->getCode()
        ], $this->getStatusCode($exception));

        $event->setResponse($response);
    }
}
```

**8. Testing**
- Integration tests pentru toate CRUD operations
- Tests pentru validation
- Tests pentru authorization (ROLE_ADMIN)
- Tests pentru translation management
- Tests pentru error responses

**Deliverables:**
- ✅ 30+ admin endpoints
- ✅ Full CRUD pentru Article, Category, Author
- ✅ Translation management API
- ✅ Input validation cu DTOs
- ✅ Custom constraints
- ✅ Error handling centralizat
- ✅ Authorization verificată
- ✅ Tests complete

**Endpoints Sprint 5:** (30+ endpoints - vezi Task 1-4)

---

### Sprint 6: Multilanguage System Finalization (5-7 zile)

**Obiectiv:** Finalizare și testare sistem multilanguage

#### Tasks:

**1. Verificare Gedmo Configuration**
- Test translatable entities
- Test ext_translations table
- Test query hints functionality

**2. Repository Updates**
- Verify toate repository methods folosesc Gedmo hints
- Verify HINT_INNER_JOIN pentru strict mode
- Add fallback queries where needed (optional)

**3. Translation Workflow Testing**
- Test creare article în RO
- Test adăugare traduceri EN, RU
- Test query cu diferite locales
- Test strict mode (404 când traducere lipsește)

**4. LocaleListener Testing**
- Test Accept-Language parsing
- Test locale detection
- Test fallback la default locale

**5. API Response Updates**
- Add `locale` field în toate responses
- Add `availableLocales` array în article details
- Update documentation

**6. Edge Cases**
- What happens când admin șterge traducere?
- What happens când article are doar RO translation?
- Comportament pentru partial translations

**7. Performance Testing**
- N+1 query detection
- Query optimization cu Gedmo hints
- Test cu volume mare de articole

**Deliverables:**
- ✅ Multilanguage 100% funcțional
- ✅ LocaleListener testat complet
- ✅ Toate queries cu Gedmo hints
- ✅ Strict mode verificat
- ✅ Edge cases handled
- ✅ Performance optimizat

---

### Sprint 7: Advanced Features & Optimization (7-10 zile)

**Obiectiv:** Features avansate și optimizări

#### Tasks:

**1. Search Functionality**

```php
#[Route('/api/public/search')]
class SearchController
{
    #[Route('', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q');
        $locale = $request->getLocale();

        // Full-text search în articles
        $articles = $this->articleRepository->search($query, $locale);

        return $this->json($articles, context: [
            'groups' => ['article:list']
        ]);
    }
}
```

Implementation în ArticleRepository:
```php
public function search(string $query, string $locale, int $limit = 20): array
{
    $qb = $this->createQueryBuilder('a')
        ->where('a.status = :status')
        ->andWhere('a.title LIKE :query OR a.content LIKE :query')
        ->setParameter('status', ArticleStatus::PUBLISHED)
        ->setParameter('query', '%' . $query . '%')
        ->setMaxResults($limit)
        ->getQuery();

    // Apply Gedmo hints
    // ...

    return $qb->getResult();
}
```

**2. Statistics Endpoints**

```php
#[Route('/api/admin/statistics')]
#[IsGranted('ROLE_ADMIN')]
class StatisticsController
{
    #[Route('/articles', methods: ['GET'])]
    public function articleStats(): JsonResponse
    {
        return $this->json([
            'total' => $this->articleRepository->count([]),
            'published' => $this->articleRepository->countByStatus(ArticleStatus::PUBLISHED),
            'byCategory' => $this->articleRepository->countByCategory(),
            'topViewed' => $this->articleRepository->findTopViewed(10)
        ]);
    }

    #[Route('/authors', methods: ['GET'])]
    public function authorStats(): JsonResponse;

    #[Route('/categories', methods: ['GET'])]
    public function categoryStats(): JsonResponse;
}
```

**3. Rate Limiting**

Configuration în `config/packages/rate_limiter.yaml`:
```yaml
framework:
    rate_limiter:
        api_public:
            policy: 'sliding_window'
            limit: 100
            interval: '1 minute'

        api_search:
            policy: 'fixed_window'
            limit: 20
            interval: '1 minute'
```

Apply în controllers:
```php
#[RateLimit(limiter: 'api_public')]
class ArticleController
{
    // ...
}
```

**4. Performance Optimization**

- Add database indexes:
  ```sql
  CREATE INDEX idx_article_published_at ON article(published_at DESC) WHERE status = 'published';
  CREATE INDEX idx_article_featured ON article(is_featured) WHERE status = 'published';
  ```

- Query optimization:
  - Eager loading pentru relationships
  - Batch loading cu Doctrine hints
  - Partial objects pentru list views

- HTTP Caching:
  - ETags pentru individual resources
  - Cache-Control headers
  - Symfony HTTP Cache consideration

**5. Workflow System**

Folosind Symfony Workflow component:
```yaml
framework:
    workflows:
        article:
            type: 'state_machine'
            marking_store:
                type: 'method'
                property: 'status'
            supports:
                - App\Entity\Article
            initial_marking: new
            places:
                - new
                - submitted
                - published
            transitions:
                submit:
                    from: new
                    to: submitted
                publish:
                    from: [new, submitted]
                    to: published
                reject:
                    from: submitted
                    to: new
```

**6. Monitoring & Logging**

Structured logging cu Monolog:
```php
$this->logger->info('Article published', [
    'article_id' => $article->getId(),
    'user_id' => $this->getUser()->getId(),
    'locale' => $locale
]);
```

Error tracking setup:
- Configure Monolog pentru errors
- Slack/email notifications pentru critical errors

**7. Background Jobs Monitoring**

Dashboard pentru Messenger:
- Failed messages count
- Processing queue size
- Average processing time

**Deliverables:**
- ✅ Search API funcțional
- ✅ Statistics endpoints
- ✅ Rate limiting active
- ✅ Database indexes optimized
- ✅ Query performance improved
- ✅ Workflow system implemented
- ✅ Monitoring & logging setup

**Endpoints Sprint 7:**
- `GET /api/public/search?q={query}` - Search articles
- `GET /api/admin/statistics/articles` - Article stats
- `GET /api/admin/statistics/authors` - Author stats
- `GET /api/admin/statistics/categories` - Category stats

---

### Sprint 8: Testing, Documentation & Production Ready (10-14 zile)

**Obiectiv:** Production-ready application

#### Tasks:

**1. Complete Test Coverage**

Target: 80%+ code coverage

**Unit Tests:**
- All entities
- All services
- All repositories
- Validation constraints

**Integration Tests:**
- All API endpoints
- Authentication flow
- Image upload flow
- Translation workflow

**Functional Tests:**
- Complete article lifecycle
- Multi-user scenarios
- Locale switching

Run coverage:
```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html coverage/
```

**2. API Documentation**

Generate OpenAPI specification:
```bash
composer require nelmio/api-doc-bundle --dev
```

Configure:
```yaml
nelmio_api_doc:
    documentation:
        info:
            title: Deschide News API
            version: 1.0.0
    areas:
        default:
            path_patterns:
                - ^/api
```

Access docs at `/api/doc`

Add annotations la controllers:
```php
#[OA\Get(
    path: '/api/public/articles',
    summary: 'Get published articles',
    parameters: [
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer'))
    ],
    responses: [
        new OA\Response(response: 200, description: 'Success')
    ]
)]
public function list(Request $request): JsonResponse
```

**3. Code Quality Finalization**

**PHPStan Level 8:**
```bash
vendor/bin/phpstan analyse --level=8 src/
```

Fix toate issues până la 0 errors.

**PHP CS Fixer:**
```bash
vendor/bin/php-cs-fixer fix
```

**Deptrac Architecture Validation:**
```yaml
# deptrac.yaml
deptrac:
    layers:
        - name: Controller
          collectors:
              - type: directory
                value: src/Controller/.*
        - name: Service
          collectors:
              - type: directory
                value: src/Service/.*
        - name: Repository
          collectors:
              - type: directory
                value: src/Repository/.*
        - name: Entity
          collectors:
              - type: directory
                value: src/Entity/.*

    ruleset:
        Controller:
            - Service
            - Repository
            - Entity
        Service:
            - Repository
            - Entity
        Repository:
            - Entity
        Entity: ~
```

Run: `vendor/bin/deptrac analyse`

**4. Security Audit**

**OWASP Top 10 Checklist:**
- [ ] SQL Injection prevention (Doctrine parameterized queries)
- [ ] XSS prevention (Symfony escaping)
- [ ] CSRF protection (Stateless API)
- [ ] Broken authentication (JWT security)
- [ ] Sensitive data exposure (HTTPS only in prod)
- [ ] XML external entities (Not applicable)
- [ ] Broken access control (IsGranted checks)
- [ ] Security misconfiguration (Environment configs)
- [ ] Using components with known vulnerabilities (`composer audit`)
- [ ] Insufficient logging & monitoring (Monolog)

Run Symfony security checker:
```bash
symfony security:check
composer audit
```

**JWT Security:**
- Verify JWT secret is strong
- Verify token expiration (1h for access, 30d for refresh)
- Verify refresh token rotation
- HttpOnly cookies pentru refresh tokens

**Rate Limiting Review:**
- Verify all public endpoints have rate limits
- Adjust limits based on expected traffic

**5. Environment Configuration**

`.env.prod` template:
```env
APP_ENV=prod
APP_SECRET=CHANGE_ME_TO_STRONG_SECRET

DATABASE_URL="postgresql://user:password@db:5432/deschide_news?serverVersion=16&charset=utf8"

CORS_ALLOW_ORIGIN='^https://deschide\.com$'

JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=CHANGE_ME

MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
```

**6. Database Migration Strategy**

Document migration process:
```bash
# Backup database
pg_dump deschide_news > backup.sql

# Run migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Verify
php bin/console doctrine:schema:validate
```

**7. Deployment Guide**

Create `docs/deployment.md`:
- Server requirements (PHP 8.2, PostgreSQL 16, Nginx)
- Installation steps
- Environment configuration
- SSL/TLS setup
- Systemd service pentru Messenger workers
- Cron jobs pentru Scheduler
- Backup strategy
- Monitoring setup

**8. CI/CD Pipeline**

GitHub Actions example (`.github/workflows/ci.yml`):
```yaml
name: CI

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - run: composer install
      - run: vendor/bin/phpstan analyse
      - run: vendor/bin/php-cs-fixer fix --dry-run
      - run: vendor/bin/phpunit
```

**9. Performance Testing**

Load testing cu Apache Bench sau k6:
```bash
ab -n 1000 -c 10 http://localhost/api/public/articles
```

Targets:
- Response time < 200ms pentru article list
- Support 100+ concurrent users
- No memory leaks în long-running processes

**10. Documentation Finalization**

Update all docs:
- README.md cu installation & usage
- API documentation complete
- Developer onboarding guide
- Contribution guidelines

**Deliverables:**
- ✅ 80%+ test coverage
- ✅ PHPStan level 8 passed
- ✅ PHP CS Fixer clean
- ✅ Deptrac validation passed
- ✅ OpenAPI documentation complete
- ✅ Security audit passed
- ✅ Deployment guide created
- ✅ CI/CD pipeline setup
- ✅ Performance targets met
- ✅ Production-ready application

---

## 📊 Timeline Summary

| Sprint | Durată | Tasks | Status |
|--------|--------|-------|--------|
| Sprint 0 | 3-5 zile | Infrastructure & Config | 🔴 Not Started |
| Sprint 1 | 7-10 zile | Core Entities & Auth | 🔴 Not Started |
| Sprint 2 | 10-14 zile | Custom Image System | 🔴 Not Started |
| Sprint 3 | 10-14 zile | Article & Pivot Tables | 🔴 Not Started |
| Sprint 4 | 7-10 zile | Public API | 🔴 Not Started |
| Sprint 5 | 10-14 zile | Admin API & CMS | 🔴 Not Started |
| Sprint 6 | 5-7 zile | Multilanguage Finalization | 🔴 Not Started |
| Sprint 7 | 7-10 zile | Advanced Features | 🔴 Not Started |
| Sprint 8 | 10-14 zile | Testing & Production | 🔴 Not Started |

**Total estimat:** 69-98 zile (~14-20 săptămâni, ~3.5-5 luni)

---

## 🎯 Success Criteria

### Funcționalitate
- [x] Toate pachetele necesare instalate
- [ ] Toate entitățile implementate (7 entități + 2 pivot)
- [ ] Authentication JWT funcțional
- [ ] Multilanguage ro/en/ru funcțional (strict mode)
- [ ] Custom thumbnail system funcțional
- [ ] 40+ API endpoints implementate
- [ ] CMS admin complet

### Calitate Cod
- [ ] PHPStan level 8 - 0 errors
- [ ] 80%+ test coverage
- [ ] PHP CS Fixer compliance
- [ ] Deptrac architecture validation passed

### Performance
- [ ] Response time < 200ms (average)
- [ ] Support 100+ concurrent users
- [ ] Database optimizat (indexes, queries)
- [ ] Async thumbnail generation funcțional

### Security
- [ ] OWASP top 10 compliance
- [ ] JWT implementation secure
- [ ] Input validation pe toate endpoints
- [ ] Rate limiting activ
- [ ] `composer audit` - 0 vulnerabilities

### Documentation
- [ ] OpenAPI specification completă
- [ ] README actualizat
- [ ] Deployment guide
- [ ] Developer onboarding guide

---

## 🔧 Tehnologii & Tools

### Backend Stack
- **PHP 8.2+**
- **Symfony 7.3**
- **PostgreSQL 16**
- **Doctrine ORM 3.5**

### Key Packages
- Lexik JWT + Gesdinet Refresh Token
- Gedmo Extensions (Translatable, Sluggable, Timestampable)
- NelmioCors
- Symfony Serializer, Validator, Messenger
- **Custom Thumbnail System** (GD/Imagick)

### Development Tools
- PHPStan 2.1 (level 8)
- PHP CS Fixer 3.89
- Deptrac 4.2
- PHPUnit 12.4

### Deployment
- Nginx
- PHP-FPM
- Systemd (pentru Messenger workers)
- GitHub Actions (CI/CD)

---

## ⚠️ Important Notes

### Custom Thumbnail System
- **NU folosim LiipImagineBundle**
- Implementare custom cu GD sau Imagick
- Async generation via Messenger
- 10 profiles predefinite
- Support JPEG, PNG, WebP

### Multilanguage
- Strict mode - 404 dacă traducere lipsește
- Accept-Language header pentru locale detection
- Gedmo query hints în toate repository methods
- Translation management API pentru admin

### Dependencies
- Sprint 1 → Sprint 2: Author needs Image (avatar)
- Sprint 2 → Sprint 3: Article needs Images
- Sprint 3 → Sprint 4,5: API needs Article
- All → Sprint 6: Multilanguage affects all entities

### Risks & Mitigation
1. **Custom thumbnail generation complexity** → Start early, test cu ambele libraries (GD/Imagick)
2. **Gedmo translation learning curve** → Study docs în Sprint 0, implement în Sprint 1
3. **N+1 query issues** → Use Doctrine profiler, optimize eager loading
4. **JWT security** → Follow Lexik best practices, security audit în Sprint 8

---

## 📚 References

- [Symfony 7.3 Docs](https://symfony.com/doc/7.3/index.html)
- [Doctrine ORM](https://www.doctrine-project.org/projects/orm.html)
- [Gedmo Translatable](https://github.com/doctrine-extensions/DoctrineExtensions/blob/main/doc/translatable.md)
- [Lexik JWT Bundle](https://github.com/lexik/LexikJWTAuthenticationBundle)
- Project Docs: `/docs/`
- Multilanguage Docs: `/docs/multilanguage-system.md`
- Security Docs: `/docs/security-authentication.md`

---

**Document Status:** ✅ Finalized
**Aprobat pentru implementare:** Pending
