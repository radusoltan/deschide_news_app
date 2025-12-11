Analiză Completă: Sistem Image Management din news_app

Am analizat în detaliu toată funcționalitatea pentru imagini și thumbnails. Iată raportul complet:

  ---
🔍 1. Image Entity (/var/www/news_app/backend/src/Entity/Image.php)

Câmpuri principale:
- ✅ filename - Nume unic generat (hash.ext)
- ✅ originalFilename - Nume original fișier
- ✅ path - Cale relativă (cu subdirectoare hash)
- ✅ mimeType - Tip MIME
- ✅ size - Dimensiune în bytes
- ✅ width, height - Dimensiuni imagine
- ✅ hash (SHA-256, UNIQUE) - Pentru deduplicare
- ✅ dominantColor (#hex) - Pentru placeholders colorat
- ✅ blurPlaceholder (base64 data URI) - Pentru progressive loading
- ✅ altText, caption, description - SEO & Accessibility
- ✅ author, source - Metadata
- ✅ OneToMany cu Thumbnail (cascade remove)
- ✅ Gedmo Timestampable

Features unice:
- Index pe hash pentru căutare rapidă
- Helper methods: getBlurDataUrl(), getDominantColorHex()

  ---
🔍 2. Thumbnail Entity (/var/www/news_app/backend/src/Entity/Thumbnail.php)

Câmpuri principale:
- ✅ image - ManyToOne cu CASCADE DELETE
- ✅ profile - Nume profil (article_card, article_hero, etc.)
- ✅ format - Format (jpg, webp, png, avif)
- ✅ filename - Nume fișier generat
- ✅ publicPath - Cale publică (alias: path)
- ✅ width, height - Dimensiuni thumbnail
- ✅ fileSize - Dimensiune fișier generat
- ✅ status (pending/processing/ready/failed) - Status generare async
- ✅ cropJson - Coordonate crop custom (percentage sau absolut)
- ✅ hasCustomCrop (boolean) - Index pentru căutare rapidă
- ✅ Gedmo Timestampable

Constraints:
- UNIQUE (image_id, profile, format) - Un singur thumbnail per combinație
- Index pe status - Pentru monitorizare thumbnails pending
- Index pe has_custom_crop - Pentru căutare thumbnails custom

Helper methods:
- markAsReady(), markAsFailed(), markAsProcessing() - Status management

  ---
🔍 3. ImageService (/var/www/news_app/backend/src/Service/ImageService.php)

Dependențe:
- Intervention Image (ImageManager)
- EntityManager
- MessageBus (async processing)
- HttpClient (upload from URL)
- Logger
- Filesystem
- ParameterBag (configurație)

Metode publice (10):

a) upload(UploadedFile $file, array $metadata): Image

- Validare file (size, MIME type)
- Read file în memory imediat (prevent cleanup issues)
- Hash SHA-256 pentru deduplicare
- Verificare duplicate DUPĂ mutare fișier (evită race conditions)
- Organizare storage: originals/{hash[0:2]}/{hash[2:4]}/{hash}.ext
- Generate progressive JPEG
- Extract metadata (width, height)
- Generate blur placeholder (20x20, blur 2, base64 data URI)
- Extract dominant color (center pixel, hex)
- Create Image entity
- Dispatch async thumbnail generation

b) uploadFromUrl(string $url, array $metadata): Image

- Download cu HttpClient (timeout 30s, max 60s)
- Validare content-type
- Verificare size limit
- Hash deduplicare
- Same flow ca upload()

c) generateAllThumbnails(Image $image, ?array $profiles): array

- Loop prin profiles (sau toate configurate)
- Loop prin formats (jpg, webp)
- Generate thumbnail per combinație
- Error handling per thumbnail (nu oprește procesul)
- Returnează array de Thumbnail entities

d) generateThumbnail(Image $image, string $profile, string $format, ?array $cropData): Thumbnail

- Validare profile exists
- Load image cu Intervention
- Apply crop custom (dacă există)
- Resize conform profile config:
    - cover - Crop to fit (default)
    - contain - Scale to fit
    - crop - Center crop
    - scale - Resize exact
- Generate path: thumbnails/{hash[0:2]}/{hash[2:4]}/{profile}.{format}
- Save cu quality settings per format
- Find sau create Thumbnail entity
- Mark as ready
- Return Thumbnail

e) crop(Image $image, string $profile, string $format, array $cropData): Thumbnail

- Wrapper pentru generateThumbnail cu cropData
- Suportă percentage crop: {p: {x, y, width, height}}
- Sau absolute pixels: {x, y, width, height}

f) regenerateThumbnails(Image $image, ?array $profiles): void

- Delete old thumbnail files
- Remove Thumbnail entities
- Flush
- Generate new thumbnails

g) delete(Image $image): void

- Delete toate thumbnail files
- Delete original file
- Remove entity (cascade remove thumbnails)

h) getPublicUrl(Image $image): string

- Returnează public path pentru imagine originală

i) getThumbnailUrl(Thumbnail $thumbnail): string

- Returnează public path pentru thumbnail

j) flush(): void

- Flush EntityManager

Metode private (9):
- extractMetadata() - width/height
- generateBlurPlaceholder() - 20x20 blur base64
- extractDominantColor() - center pixel hex
- ensureDirectory() - mkdir recursive
- convertPercentageCrop() - % → pixels
- validateCropData() - boundaries validation
- saveThumbnail() - format-specific encoding (jpg/webp/png/avif)
- getImagePath() - absolute path pentru Image
- getThumbnailPath() - absolute path pentru Thumbnail
- validateFile() - upload validation

  ---
🔍 4. Async Processing (Message + Handler)

GenerateImageThumbnailsMessage:
readonly class {
int $imageId,
?array $profiles = null  // Specific profiles sau toate
}

GenerateImageThumbnailsHandler:
- Fetch Image by ID
- Call imageService->generateAllThumbnails()
- Error logging cu trace
- Throw exception pentru retry (Messenger)

  ---
🔍 5. Configurație (config/services/image.yaml)

# Storage
image.storage.root: '%kernel.project_dir%/public/uploads/images'
image.storage.originals_dir: 'originals'
image.storage.thumbnails_dir: 'thumbnails'
image.storage.public_path: '/uploads/images'

# Upload
image.upload.max_size: 10485760  # 10MB
image.upload.allowed_types: [jpeg, png, webp, gif]

# Thumbnail Profiles (6 profiles)
image.thumbnail.profiles:
article_card: 640x427 (3:2 cover)
article_hero: 1600x600 (8:3 cover)
article_wide: 1920x1080 (16:9 cover)
category_thumbnail: 400x400 (1:1 cover)
author_avatar: 200x200 (1:1 cover)
author_avatar_large: 400x400 (1:1 cover)

# Formats
image.thumbnail.formats: ['jpg', 'webp']

# Quality
image.thumbnail.quality:
jpg: 85, webp: 80, png: 90, avif: 75

# Encoding
image.thumbnail.progressive: true
image.thumbnail.interlace: true
image.thumbnail.optimize: true

# Blur Placeholder
image.blur_placeholder.size: 20
image.blur_placeholder.blur: 2
image.blur_placeholder.quality: 60

  ---
🎯 Recomandări pentru deschide_news_app

✅ Ce să PĂSTRĂM (100% identic)

1. Image Entity - Toate câmpurile:
   - Hash-based deduplication (CRITICAL)
   - Blur placeholder pentru UX
   - Dominant color pentru placeholders
   - Metadata SEO (altText, caption, description, author, source)
2. Thumbnail Entity - Toate câmpurile:
   - Status tracking (async generation)
   - Custom crop support (cropJson, hasCustomCrop)
   - Unique constraint (image, profile, format)
   - Indexes pentru performance
3. ImageService - Toate metodele:
   - Deduplicare prin hash
   - Upload from URL
   - Async thumbnail generation
   - Multi-format support (jpg + webp minim)
   - Percentage crop
   - Progressive JPEG
4. Async Processing - Message + Handler:
   - Evită timeout-uri pe upload
   - Scalabil pentru volume mari
5. Storage Organization:
   - Hash-based subdirectories (evită 1000+ files în același folder)
   - Separate originals/thumbnails

⚠️ Ce să ADAPTĂM pentru deschide_news_app

1. Thumbnail Profiles - Adaptate pentru news:
# Pentru Articles
article_card: 640x427 (3:2)      # List view
article_hero: 1600x600 (8:3)     # Article header
article_wide: 1920x1080 (16:9)   # Full width
article_square: 600x600 (1:1)    # Social sharing

# Pentru Categories
category_icon: 400x400 (1:1)     # Category grid

# Pentru Authors
author_avatar: 200x200 (1:1)     # Small avatar
author_avatar_large: 400x400 (1:1) # Profile page

# Breaking News
breaking_banner: 1200x400 (3:1)  # Top banner
2. Formats - Adaugă AVIF (modern browsers):
   image.thumbnail.formats: ['jpg', 'webp', 'avif']
3. Upload Limits:
   image.upload.max_size: 5242880  # 5MB (news nu necesită 10MB)

❌ Ce să ELIMINĂM (nu e necesar)

1. Caption field din Image entity:
   - description este suficient
   - Evită confuzie altText vs caption vs description
2. filename field din Thumbnail:
   - publicPath este suficient

🆕 Ce să ADĂUGĂM pentru deschide_news_app

1. EXIF Data Extraction (pentru jurnalism):
   // În Image entity
   private ?string $exifCopyright = null;
   private ?string $exifCameraModel = null;
   private ?\DateTimeImmutable $exifDateTaken = null;
   private ?string $exifGpsCoordinates = null;
2. Image Optimization (pentru SEO/Performance):
   // În ImageService
   private function optimizeImage(string $path): void {
   // Optipng pentru PNG
   // Jpegoptim pentru JPEG
   // Reduce file size fără pierdere vizibilă
   }
3. Watermark Support (pentru copyright):
   // În ImageService
   public function addWatermark(Image $image, string $text): void
4. Image Moderation (pentru uploaded content):
   // În Image entity
   private bool $isModerated = false;
   private ?string $moderationStatus = 'pending'; // pending/approved/rejected

  ---
📦 Implementare Recomandată pentru Sprint 2

Faza 1: Core Entities

- ✅ Image entity (fără caption)
- ✅ Thumbnail entity (complet)
- ✅ ImageRepository, ThumbnailRepository

Faza 2: ImageService

- ✅ Intervention Image setup
- ✅ upload() + uploadFromUrl()
- ✅ generateAllThumbnails() + generateThumbnail()
- ✅ crop() + regenerateThumbnails()
- ✅ delete()
- ✅ getPublicUrl() + getThumbnailUrl()

Faza 3: Async Processing

- ✅ GenerateImageThumbnailsMessage
- ✅ GenerateImageThumbnailsHandler
- ✅ Messenger configuration (RabbitMQ)

Faza 4: Configuration

- ✅ image.yaml cu profiles pentru news
- ✅ Messenger routing (async transport)

Faza 5: Testing

- ✅ Upload test
- ✅ Deduplication test
- ✅ Thumbnail generation test
- ✅ Crop test

  ---
Concluzie: news_app are un sistem production-ready și battle-tested pentru image management. Recomand să păstrăm ~95% din funcționalitate și să adaptăm doar profilele de thumbnails pentru specificul
news-ului. Sistemul de deduplicare, blur placeholders și async processing sunt CRITICAL pentru performance și UX.