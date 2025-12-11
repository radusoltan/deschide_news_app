# Plan de Migrare: Newscoop → news_app

**Data analiză:** 2025-10-25
**Bază sursă:** MariaDB `newscoop` (Newscoop 4.4)
**Bază destinație:** PostgreSQL `news_app`

---

## 📊 Situația Actuală

### Date în Newscoop (MariaDB)

| Entitate | Cantitate | Observații |
|----------|-----------|------------|
| **Articles** | 176,207 | Metadata articole (composite key: Number + IdLanguage) |
| **Xstiri** | 175,629 | Conținut articole tip "stiri" (99.7%) |
| **Xembed** | 233 | Articole embed (0.13%) |
| **Xultrascurte** | 272 | Știri scurte (0.15%) |
| **Xvideo** | 76 | Articole video (0.04%) |
| **Sections** | 43 | Categorii |
| **Authors** | 282 | Autori (18 cu email unic) |
| **Images** | 165,302 | Imagini (toate format cms-image-XXXXXXXXX.jpg/png) |
| **Languages** | 3 | en-US, ro-RO, ru-RU |
| **ArticleAuthors** | - | Relație many-to-many articol-autor |
| **ArticleImages** | - | Relație many-to-many articol-imagine |

**Locație imagini fizice:** `/home/radu/ext-hdd/alpha/`

⚠️ **DESCOPERIRE CRITICĂ:** Conținutul articolelor conține **shortcodes Newscoop** care trebuie transformate în HTML!
- **16,182 articole** (9.2%) au imagini în format shortcode `<!** Image [id] ...>`
- Procesare obligatorie pentru import corect - vezi detalii în secțiunea "Procesarea Conținutului"

### Structura Newscoop

#### Articles Table (Metadata)
```
Composite PK: Number + IdLanguage
├─ Number (ID articol)
├─ IdLanguage (1=en, 2=ro, 15=ru)
├─ Type ('stiri', 'embed', 'ultrascurte', 'video')
├─ Name (titlu scurt)
├─ Published (Y/N/S = Published/Not Published/Submitted)
├─ PublishDate
├─ UploadDate
├─ time_updated
├─ Keywords (CSV)
├─ OnFrontPage (Y/N)
├─ IdUser (creator)
├─ NrSection (FK → Sections)
└─ ArticleOrder
```

#### Xstiri Table (Conținut pentru type='stiri')
```
Composite PK: NrArticle + IdLanguage (FK → Articles)
├─ FTitlu (titlu complet)
├─ Fsubtitlu (subtitlu)
├─ Flead (lead/excerpt) - BLOB
├─ FContinut (content HTML) - BLOB
├─ FBREAKING_NEWS (boolean)
├─ FNEWS_ALERT (boolean)
├─ FFLASH (boolean)
├─ Flive_text - BLOB
├─ Fvideo_embed - BLOB
└─ Alte flag-uri (Falegeri, FDOC, FFOTO, FVIDEO)
```

#### Sections Table
```
PK: id (auto-increment)
├─ Name (varchar 255)
├─ ShortName (varchar 32)
├─ Description (BLOB)
├─ IdLanguage (pentru multilingual)
├─ NrIssue, IdPublication (ierarhie Newscoop)
└─ Template IDs
```

#### Authors Table
```
PK: id
├─ first_name (varchar 100)
├─ last_name (varchar 100)
├─ email (varchar 255, nullable)
├─ type (int, FK către AuthorTypes)
├─ skype (varchar 255)
├─ jabber (varchar 255)
├─ aim (varchar 255)
├─ biography (TEXT)
└─ image (int, FK către Images)

Statistici:
- Total: 282
- Cu email unic: 18
- Cu Skype: 1
- Cu Jabber: 0
- Cu AIM: 0
- Cu biography: 0
- Cu imagine: 27
```

#### ArticleAuthors Table (Many-to-Many)
```
Composite PK: fk_article_number + fk_language_id + fk_author_id + fk_type_id
├─ fk_article_number
├─ fk_language_id
├─ fk_author_id
├─ fk_type_id
└─ order (ordinea autorilor: 1, 2, 3...)
```

#### Images Table
```
PK: Id
├─ ImageFileName (ex: cms-image-000000017.png)
├─ Location ('local' sau 'remote')
├─ URL (varchar 255)
├─ Description (TEXT)
├─ width, height (int)
├─ Photographer (varchar 255)
├─ photographer_url
├─ Place (varchar 255)
├─ Date (varchar 255)
├─ ContentType (MIME type)
├─ Source (varchar 255)
├─ Status (varchar 255)
├─ UploadedByUser (int)
├─ TimeCreated, LastModified (datetime)
└─ ThumbnailFileName (thumbnail vechi Newscoop)
```

#### ArticleImages Table
```
PK: id
├─ NrArticle (FK → Articles.Number)
├─ Number (ordinea imaginii în articol)
├─ IdImage (FK → Images.Id)
└─ is_default (boolean - featured image)
```

---

## 🎯 Structura news_app (Actuală)

### Article Entity
```php
├─ id (PK, auto-increment)
├─ title (string 255) - Gedmo\Translatable
├─ slug (string 255) - Gedmo\Translatable
├─ lead (text) - Gedmo\Translatable
├─ content (text) - Gedmo\Translatable
├─ publishedAt (DateTimeImmutable, nullable)
├─ views (int, default 0)
├─ badge (string: none/breaking/alert/flash)
├─ status (string: new/submitted/published/rejected)
├─ scheduledAt (DateTimeImmutable, nullable)
├─ createdAt, updatedAt (DateTimeImmutable)
├─ authors (ManyToMany → Author) ✅ SUPORT AUTORI MULTIPLI
├─ category (ManyToOne → Category)
├─ images (ManyToMany → Image)
├─ featuredImage (ManyToOne → Image)
├─ relatedArticles (ManyToMany → Article)
└─ tags (ManyToMany → Tag)
```

### Author Entity
```php
├─ id (PK)
├─ firstName (string 100)
├─ lastName (string 100)
├─ email (string 180, unique)
├─ bio (text) - Gedmo\Translatable
├─ profileImage (OneToOne → Image)
└─ articles (ManyToMany ← Article) ✅ SUPORT AUTORI MULTIPLI
```

**Note:** Câmpurile legacy Newscoop (skype, jabber, aim, type) nu sunt importate - irelevante în 2025.

### Category Entity
```php
├─ id (PK)
├─ name (string 100) - Gedmo\Translatable
├─ slug (string 120) - Gedmo\Translatable
├─ description (text) - Gedmo\Translatable
├─ status (string: active/inactive)
├─ image (OneToOne → Image)
├─ articles (OneToMany ← Article)
└─ createdAt, updatedAt (DateTimeImmutable)
```

### Image Entity
```php
├─ id (PK)
├─ filename (string 255)
├─ originalFilename (string 255)
├─ path (string 500)
├─ mimeType (string 100)
├─ size (int bytes)
├─ width, height (int)
├─ hash (string 64, unique - SHA256)
├─ dominantColor (string 7 - #RRGGBB)
├─ blurPlaceholder (text - base64)
├─ altText (string 500) - Gedmo\Translatable
├─ description (text) - Gedmo\Translatable
├─ author (string 255 - photographer credit)
├─ source (string 255)
├─ thumbnails (OneToMany → Thumbnail)
└─ createdAt, updatedAt (DateTimeImmutable)
```

---

## ⚠️ Probleme Identificate

### 1. **Relația Article-Author** ✅ REZOLVATĂ

**Status:** ✅ **IMPLEMENTAT** (Version20251025213637)

news_app suportă acum **ManyToMany** Article ↔ Author, identic cu Newscoop:
- Tabela junction `article_author` creată
- Suport complet pentru autori multipli per articol
- Import va prelua toți autorii din `ArticleAuthors` Newscoop

**Note:** Order-ul autorilor din Newscoop nu este păstrat (arhivă, nu e critic).

### 2. **Multilingual: Composite Key vs Gedmo Translatable**

**Newscoop:**
```
Același articol = multiple rows
├─ Number=123, IdLanguage=2  → "Știri din Moldova" (ro)
├─ Number=123, IdLanguage=1  → "News from Moldova" (en)
└─ Number=123, IdLanguage=15 → "Новости из Молдовы" (ru)

Xstiri:
├─ NrArticle=123, IdLanguage=2  → FContinut (română)
├─ NrArticle=123, IdLanguage=1  → FContinut (engleză)
└─ NrArticle=123, IdLanguage=15 → FContinut (rusă)
```

**news_app:**
```
Un singur row în Article
├─ id=123, title="Știri din Moldova" (limba default: ro)
└─ ext_translations table:
    ├─ locale=en, field=title, content="News from Moldova"
    ├─ locale=en, field=content, content="..."
    ├─ locale=ru, field=title, content="Новости из Молдовы"
    └─ locale=ru, field=content, content="..."
```

**Strategie Import:**
1. Grupare Articles după `Number`
2. Alege limba default (prioritate: ro → en → prima găsită)
3. Crează Article cu date din limba default
4. Pentru celelalte limbi: INSERT în ext_translations

### 3. **Tipuri Multiple de Articole**

| Type | Count | % | Tabela conținut |
|------|-------|---|-----------------|
| stiri | 175,626 | 99.67% | Xstiri |
| embed | 233 | 0.13% | Xembed |
| ultrascurte | 272 | 0.15% | Xultrascurte |
| video | 76 | 0.04% | Xvideo |

**Opțiuni:**

**A. Import doar `stiri` (99.67%)**
- Ignorăm embed/ultrascurte/video
- Simplu, rapid
- Pierdem 581 articole (0.33%)

**B. Conversie toate tipurile în Article**
- Mapare Xembed/Xvideo/Xultrascurte → Article.content
- Adăugare metadata pentru tip original
- Complex, dar păstrează toate datele

### 5. **Imagini: 165,302 Fișiere**

**Provocări:**
- Format Newscoop: `cms-image-000000017.png` (9 digits zero-padded)
- news_app: Hash-based storage + thumbnails async
- 165k fișiere × 6 profiles × 2 formats = **~2 milioane thumbnails de generat**

**Estimate timp:**
- Copiere + hash: ~2-3 ore
- Generare thumbnails: ~6-8 ore (async queue)
- **Total: ~10 ore**

**Optimizări:**
- Import batch (100 imagini)
- Verificare duplicate prin hash (evită reprocessare)
- Skip imagini fără fișier fizic
- Prioritizare: imagini folosite în articole → imagini orfane

### 6. **Conținut BLOB (HTML)**

Xstiri:
- `FContinut` = mediumblob (HTML)
- `Flead` = mediumblob (HTML)

**Tratare:**
```php
// La citire
$content = $row['FContinut'];
if (is_resource($content)) {
    $content = stream_get_contents($content);
}

// Opțional: sanitizare HTML
$content = $this->htmlSanitizer->sanitize($content);
```

### 7. **Secțiuni Multilinguale** ✅ ANALIZATĂ

```sql
SELECT id, Name, ShortName, IdLanguage FROM Sections;
-- id=1, Name="politic", IdLanguage=2 (ro)
-- id=12, Name="investigations", IdLanguage=1 (en)
```

**Status:** ✅ **Analiză completă disponibilă în `SECTIONS_ANALYSIS.md`**

**Rezumat concluzii:**
- **43 rânduri** în Newscoop → **18 secțiuni unice** (Number = Section ID)
- **10 secțiuni** cu 3 limbi (EN+RO+RU)
- **5 secțiuni** cu 2 limbi (RO+RU)
- **3 secțiuni** incomplete (doar 1 limbă)
- **Top 3 secțiuni** = 76.8% din articole (Social, Externe, Politic)
- **Inconsistențe identificate:** Number 10 (video/opinii/видео), Number 17+21 (posibile duplicate)

**Strategie import:**
- Import **15-18 categorii** în news_app (nu toate cele 43 rânduri)
- Grupare după `Number` pentru traduceri
- Validare manuală pentru secțiunile cu inconsistențe
- Ignorare duplicate și secțiuni cu 0 articole

**Detalii:** Vezi `SECTIONS_ANALYSIS.md` pentru analiza completă

---

## ⚠️ PROCESAREA CONȚINUTULUI (CRITICĂ!)

### Problema: Shortcodes Newscoop în Conținut

Newscoop folosește clasa `ArticleData` pentru a transforma conținutul între două formate:
1. **Format Editor** (HTML standard) - în interfața CMS
2. **Format Stocare** (Shortcodes proprietare) - în baza de date `Xstiri.FContinut`

**Impact:** Conținutul din DB conține shortcodes, NU HTML pur!

### Shortcodes Identificate

#### 1. Image Shortcode ⚠️ CEL MAI IMPORTANT

**Statistici:**
- **16,182 articole** (9.2%) conțin shortcode `<!** Image>`
- **1,106 articole** (0.6%) conțin `<img>` tag HTML standard

**Format în DB:**
```
<!** Image 12345 align="left" alt="Premierul Moldovei" sub="Foto: gov.md" width="640" height="480">
```

**Trebuie transformat în:**
```html
<figure class="align-left">
  <img src="/uploads/images/thumbnails/ab/cd/abc123.../article_wide.webp"
       alt="Premierul Moldovei"
       loading="lazy"
       width="640"
       height="480">
  <figcaption>Foto: gov.md</figcaption>
</figure>
```

**Mapări necesare:**
- `12345` (Newscoop Image ID) → lookup în `newscoop_id_mapping` → news_app Image ID
- `align` → class CSS (`align-left`, `align-right`, `align-center`)
- `alt` → atribut img
- `sub` (subtitle) → `<figcaption>`
- `width`, `height` → atribute img (sau responsive)

#### 2. Internal Link Shortcode

**Statistici:** 3 articole (foarte rare, dar importante)

**Format în DB:**
```
<!** Link Internal IdPublication=1&NrArticle=123 TARGET _blank>Citește mai mult<!** EndLink>
```

**Trebuie transformat în:**
```html
<a href="/articles/titlu-articol-123" target="_blank">Citește mai mult</a>
```

**Mapări necesare:**
- `NrArticle=123` → lookup în `newscoop_id_mapping` → news_app Article slug

#### 3. Subheading Shortcode

**Format în DB:**
```
<!** Title>Subtitlu important<!** EndTitle>
```

**Trebuie transformat în:**
```html
<h3 class="article-subheading">Subtitlu important</h3>
```

#### 4. Snippet Shortcode (Opțional)

**Format în DB:**
```
<!-- Snippet 123 -->
```

**Tratare:** Remove (conținut legacy, embed-uri vechi)

### Consecințe dacă NU procesăm:

❌ **16,182 articole** (9.2%) vor avea imagini BROKEN:
```html
<!-- În loc de imagine, utilizatorul va vedea: -->
<!** Image 12345 align="left" alt="..." sub="..." width="640" height="480">
```

❌ Conținut imposibil de editat în news_app CMS
❌ SEO dezastru (Google indexează shortcodes, nu conținut real)
❌ Experiență utilizator distructivă
❌ Arhiva devine inutilizabilă

### Soluția: NewscoopContentProcessor Service ⭐ OBLIGATORIU

**Implementare:** Service dedicat pentru procesare regex și transformare shortcodes

**Funcționalități:**
1. Parse shortcodes Image și transformare în `<figure><img><figcaption>`
2. Mapare Newscoop Image ID → news_app Image ID (via `newscoop_id_mapping`)
3. Generare URL imagini prin `ImageService::getThumbnailUrl()`
4. Parse shortcodes Internal Link și transformare în `<a href>`
5. Mapare Newscoop Article Number → news_app Article slug
6. Transformare subheadings în `<h3>`
7. Curățare HTML (remove snippets, sanitize)
8. Logging detaliat pentru imagini/articole not found

**Integrare:** În `ImportArticlesCommand`, înainte de `$article->setContent()`

**Overhead timp:** +10-15% (2.5h → 2.8-3h pentru Faza 5) - **ACCEPTABIL** pentru beneficii!

**Detalii complete:** Vezi `CONTENT_PROCESSING_ANALYSIS.md`

---

## 🛠️ Modificări Necesare în news_app

### A. Configurare Doctrine - Conexiune MariaDB

**config/packages/doctrine.yaml:**
```yaml
doctrine:
    dbal:
        connections:
            default:
                url: '%env(resolve:DATABASE_URL)%'
                driver: 'pdo_pgsql'

            newscoop:
                url: '%env(resolve:NEWSCOOP_DATABASE_URL)%'
                driver: 'pdo_mysql'
                charset: utf8mb4
                server_version: '10.11'
```

**.env.local:**
```bash
NEWSCOOP_DATABASE_URL="mysql://root:sr324395@localhost:3306/newscoop?charset=utf8mb4"
```

### B. Servicii și Helpers

#### 1. NewscoopDatabaseService
```php
// Wrapper pentru queries la MariaDB
class NewscoopDatabaseService
{
    public function getArticlesByNumber(int $number): array;
    public function getXstiriContent(int $number, int $languageId): ?array;
    public function getSectionById(int $id): ?array;
    public function getAuthorById(int $id): ?array;
    public function getImageById(int $id): ?array;

    /**
     * Get related articles from context_boxes
     * @return array [['fk_article_no' => int, 'order_number' => int], ...]
     */
    public function getRelatedArticles(int $articleNumber): array
    {
        $contextBox = $this->connection->fetchAssociative(
            "SELECT id FROM context_boxes WHERE fk_article_no = ?",
            [$articleNumber]
        );

        if (!$contextBox) {
            return [];
        }

        return $this->connection->fetchAllAssociative(
            "SELECT fk_article_no, order_number
             FROM context_articles
             WHERE fk_context_id = ?
             ORDER BY order_number ASC",
            [$contextBox['id']]
        );
    }
}
```

#### 2. NewscoopMapperService
```php
// Conversie date Newscoop → news_app entities
class NewscoopMapperService
{
    public function mapArticle(array $newscoopData): Article;
    public function mapAuthor(array $newscoopData): Author;
    public function mapCategory(array $newscoopData): Category;
    public function mapImage(array $newscoopData): Image;

    public function mapStatus(string $newscoopStatus): string;
    public function mapBadge(array $flags): string;
    public function mapLanguageCode(int $languageId): string;
}
```

#### 3. ImageMigrationService
```php
class ImageMigrationService
{
    public function copyImageFromNewscoop(string $filename): ?string;
    public function generateHashForFile(string $path): string;
    public function processImage(string $sourcePath): Image;
}
```

#### 4. NewscoopContentProcessor ⭐ CRITIC - NOU!
```php
/**
 * Procesează shortcodes Newscoop și transformă în HTML modern
 * OBLIGATORIU pentru import corect - 16,182 articole afectate!
 */
class NewscoopContentProcessor
{
    private LoggerInterface $logger;
    private ImageRepository $imageRepo;
    private ArticleRepository $articleRepo;
    private ImageService $imageService;
    private array $processingStats = [
        'images_processed' => 0,
        'images_not_found' => 0,
        'images_skipped_invalid' => 0,
        'internal_links_processed' => 0,
        'internal_links_broken' => 0,
        'subheadings_converted' => 0,
        'snippets_removed' => 0,
    ];

    /**
     * Procesează tot conținutul: shortcodes → HTML
     * @param string $content Conținut raw din Xstiri.FContinut (BLOB)
     * @param int $newscoopArticleNumber Pentru logging
     * @return string HTML processed, ready for Article.content
     */
    public function processContent(string $content, int $newscoopArticleNumber): string
    {
        // 1. Transform Image shortcodes → <figure><img><figcaption>
        $content = $this->processImageShortcodes($content, $newscoopArticleNumber);

        // 2. Transform Internal Link shortcodes → <a href>
        $content = $this->processInternalLinks($content);

        // 3. Transform Title shortcodes → <h3>
        $content = $this->processSubheadings($content);

        // 4. Remove Snippet shortcodes (legacy)
        $content = $this->removeSnippets($content);

        // 5. Clean HTML (optional: sanitize, fix malformed tags)
        $content = $this->cleanHtml($content);

        return $content;
    }

    /**
     * Transform: <!** Image [id] ...> → <figure><img><figcaption>
     * Regex: /<!\\*\\* Image (\d+)(?: align="([^"]*)")?(?: alt="([^"]*)")?(?: sub="([^"]*)")?(?: width="([^"]*)")?(?: height="([^"]*)")?>/
     */
    private function processImageShortcodes(string $content, int $articleNumber): string
    {
        $pattern = '/<!\\*\\* Image (\d+)(?:\s+align="([^"]*)")?(?:\s+alt="([^"]*)")?(?:\s+sub="([^"]*)")?(?:\s+width="([^"]*)")?(?:\s+height="([^"]*)")?>/';

        return preg_replace_callback($pattern, function ($matches) use ($articleNumber) {
            // Extract captured groups (pad to ensure all exist)
            [, $newscoopImageId, $align, $alt, $caption, $width, $height] = array_pad($matches, 7, null);

            // Map Newscoop Image ID → news_app Image ID
            $newsAppImageId = $this->getMappedId('image', (int)$newscoopImageId);

            if (!$newsAppImageId) {
                $this->logger->warning("Image shortcode: Image not found in mapping", [
                    'newscoop_image_id' => $newscoopImageId,
                    'article_number' => $articleNumber,
                ]);
                $this->processingStats['images_not_found']++;
                return ''; // Remove shortcode if image missing
            }

            // Get Image entity
            $image = $this->imageRepo->find($newsAppImageId);
            if (!$image) {
                $this->logger->warning("Image shortcode: Image entity not found", [
                    'news_app_image_id' => $newsAppImageId,
                    'article_number' => $articleNumber,
                ]);
                $this->processingStats['images_not_found']++;
                return '';
            }

            $this->processingStats['images_processed']++;

            // Generate modern HTML5 <figure>
            $html = '<figure';
            if ($align && in_array($align, ['left', 'right', 'center'])) {
                $html .= ' class="align-' . htmlspecialchars($align, ENT_QUOTES, 'UTF-8') . '"';
            }
            $html .= '>';

            // Generate <img> with responsive thumbnail
            $thumbnailUrl = $this->imageService->getThumbnailUrl($image, 'article_wide', 'webp');
            $html .= sprintf(
                '<img src="%s" alt="%s" loading="lazy" width="%s" height="%s">',
                htmlspecialchars($thumbnailUrl, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($alt ?? '', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($width ?? $image->getWidth(), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($height ?? $image->getHeight(), ENT_QUOTES, 'UTF-8')
            );

            // Add <figcaption> if subtitle exists
            if ($caption) {
                $html .= '<figcaption>' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</figcaption>';
            }

            $html .= '</figure>';

            return $html;
        }, $content);
    }

    /**
     * Transform: <!** Link Internal ...>text<!** EndLink> → <a href>
     * Regex: /<!\\*\\* Link Internal ([^>]+)(?:\s+TARGET\s+([^>]+))?>(.*?)<!\\*\\* EndLink>/s
     */
    private function processInternalLinks(string $content): string
    {
        $pattern = '/<!\\*\\* Link Internal ([^>]+)(?:\s+TARGET\s+([^>]+))?>(.*?)<!\\*\\* EndLink>/s';

        return preg_replace_callback($pattern, function ($matches) {
            [, $queryString, $target, $linkText] = array_pad($matches, 4, null);

            // Parse query string: "IdPublication=1&NrArticle=123&IdLanguage=2"
            parse_str(trim($queryString), $params);

            if (!isset($params['NrArticle'])) {
                $this->logger->warning("Internal link: Missing NrArticle parameter", [
                    'query_string' => $queryString,
                ]);
                $this->processingStats['internal_links_broken']++;
                return $linkText; // Return just text without link
            }

            // Map Newscoop Article Number → news_app Article ID
            $newsAppArticleId = $this->getMappedId('article', (int)$params['NrArticle']);
            if (!$newsAppArticleId) {
                $this->logger->warning("Internal link: Article not found in mapping", [
                    'newscoop_article_number' => $params['NrArticle'],
                ]);
                $this->processingStats['internal_links_broken']++;
                return $linkText;
            }

            // Get Article entity for slug
            $article = $this->articleRepo->find($newsAppArticleId);
            if (!$article) {
                $this->processingStats['internal_links_broken']++;
                return $linkText;
            }

            $this->processingStats['internal_links_processed']++;

            // Generate URL (adjust based on your routing)
            $url = '/articles/' . $article->getSlug();

            // Build <a> tag
            $html = sprintf(
                '<a href="%s"%s>%s</a>',
                htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                $target ? ' target="' . htmlspecialchars(trim($target), ENT_QUOTES, 'UTF-8') . '"' : '',
                $linkText
            );

            return $html;
        }, $content);
    }

    /**
     * Transform: <!** Title>text<!** EndTitle> → <h3>
     */
    private function processSubheadings(string $content): string
    {
        $pattern = '/<!\\*\\* Title>(.*?)<!\\*\\* EndTitle>/s';

        $processed = preg_replace_callback($pattern, function ($matches) {
            $this->processingStats['subheadings_converted']++;
            return '<h3 class="article-subheading">' . $matches[1] . '</h3>';
        }, $content);

        return $processed;
    }

    /**
     * Remove snippet shortcodes (legacy content)
     */
    private function removeSnippets(string $content): string
    {
        $pattern = '/<!-- Snippet \d+ -->/';

        $processed = preg_replace_callback($pattern, function () {
            $this->processingStats['snippets_removed']++;
            return '';
        }, $content);

        return $processed;
    }

    /**
     * Clean HTML (optional: sanitize, fix malformed tags)
     */
    private function cleanHtml(string $content): string
    {
        // Optional: Use HTMLPurifier or DOMDocument for deep cleaning
        // For now, basic cleanup:

        // Remove empty paragraphs
        $content = preg_replace('/<p>\s*<\/p>/', '', $content);

        // Remove multiple consecutive <br>
        $content = preg_replace('/(<br\s*\/?>\s*){3,}/', '<br><br>', $content);

        return trim($content);
    }

    /**
     * Get mapped ID from newscoop_id_mapping table
     */
    private function getMappedId(string $entityType, int $newscoopId): ?int
    {
        // Query newscoop_id_mapping table
        // Returns news_app ID or null if not found
        // Implementation depends on your mapping storage strategy
    }

    /**
     * Get processing statistics
     */
    public function getStats(): array
    {
        return $this->processingStats;
    }

    /**
     * Reset statistics (call at batch start)
     */
    public function resetStats(): void
    {
        foreach ($this->processingStats as $key => $value) {
            $this->processingStats[$key] = 0;
        }
    }
}
```

---

## 📋 Plan de Execuție - 6 Faze

### **FAZA 0: Pregătire (1-2 ore)**

**0.1. Setup Conexiuni**
- [x] Configurare Doctrine dual connection (PostgreSQL + MariaDB)
- [ ] Test conexiune la newscoop database
- [ ] Verificare acces la `/home/radu/ext-hdd/alpha/`

**0.2. Creare Infrastructură**
```bash
# Migration log table
CREATE TABLE migration_log (
    id SERIAL PRIMARY KEY,
    batch_id VARCHAR(50),
    entity_type VARCHAR(50),
    entity_id INT,
    newscoop_id VARCHAR(100),
    status VARCHAR(20), -- 'success', 'failed', 'skipped'
    error_message TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

# Temporary mapping table pentru ID-uri
CREATE TABLE newscoop_id_mapping (
    newscoop_entity VARCHAR(50),
    newscoop_id INT,
    news_app_id INT,
    PRIMARY KEY (newscoop_entity, newscoop_id)
);
```

**0.4. Creare Servicii Base**
- [ ] NewscoopDatabaseService
- [ ] NewscoopMapperService
- [ ] NewscoopContentProcessor ⚠️ **CRITIC - OBLIGATORIU!**
- [ ] MigrationLoggerService

---

### **FAZA 1: Validare & Analiză (30 min)**

**Comandă:**
```bash
php bin/console app:newscoop:validate
```

**Verificări:**
- ✓ Conexiune MariaDB funcțională
- ✓ Conexiune PostgreSQL funcțională
- ✓ Acces la directorul imagini

**Statistici Colectate:**
```
ARTICLES:
  Total: 176,207
  ├─ Type 'stiri': 175,626 (99.67%)
  ├─ Type 'embed': 233 (0.13%)
  ├─ Type 'ultrascurte': 272 (0.15%)
  └─ Type 'video': 76 (0.04%)

  By Status:
  ├─ Published (Y): X
  ├─ Not Published (N): X
  └─ Submitted (S): X

  By Language:
  ├─ Romanian (2): X
  ├─ English (1): X
  └─ Russian (15): X

XSTIRI CONTENT:
  Total: 175,629
  ├─ With FTitlu: X
  ├─ With Flead: X
  ├─ With FContinut: X
  └─ BREAKING_NEWS=1: X

SECTIONS:
  Total: 43
  ├─ Romanian: X
  ├─ English: X
  └─ Russian: X

  Duplicate check (same name, different languages):
  └─ Results...

AUTHORS:
  Total: 282
  ├─ With unique email: 18
  ├─ With image: 27
  ├─ With biography: 0
  └─ Missing last_name: X

IMAGES:
  Total: 165,302
  ├─ Location='local': X
  ├─ Location='remote': X
  ├─ Files found in /home/radu/ext-hdd/alpha/: X
  ├─ Files missing: X
  └─ Orphaned (not linked to articles): X

ARTICLE-AUTHOR RELATIONSHIPS:
  Total links: X
  ├─ Articles with 1 author: X
  ├─ Articles with 2+ authors: X
  └─ Max authors per article: X

ARTICLE-IMAGE RELATIONSHIPS:
  Total links: X
  ├─ Articles with images: X
  ├─ Articles with featured image: X
  └─ Average images per article: X

DATA QUALITY ISSUES:
  ⚠ Articles without author: X
  ⚠ Articles without section: X
  ⚠ Articles with empty content: X
  ⚠ Authors with duplicate emails: X
  ⚠ Sections with duplicate names: X
```

**Output:** Raport JSON + human-readable text

---

### **FAZA 2: Import Categorii (15 min)**

**Comandă:**
```bash
php bin/console app:newscoop:import:categories [--dry-run] [--verbose]
```

**Proces:**

**Strategie (bazată pe SECTIONS_ANALYSIS.md):**
- Query după `Number` (nu după `id`) pentru grupare traduceri
- Import **15-18 categorii** din 18 secțiuni unice (excludere duplicate/incomplete)
- Validare manuală pentru Number 10 (inconsistență video/opinii)
- Ignorare secțiuni cu 0 articole (Number 17, 21)

1. **Analiză Sections cu grupare după Number:**
   ```sql
   SELECT Number, id, Name, ShortName, Description, IdLanguage
   FROM Sections
   ORDER BY Number, IdLanguage;
   ```

2. **Grupare după Number** (Number = Section ID în Newscoop)

3. **Pentru fiecare grup (Number):**
   ```php
   // Obține toate limbile pentru acest Number
   $translations = $this->getSectionTranslations($number);

   // Limba default (română prioritară, sau prima disponibilă)
   $defaultLang = $translations['ro'] ?? $translations['en'] ?? $translations['ru'] ?? reset($translations);

   $category = new Category();
   $category->setName($defaultLang['Name']);
   $category->setSlug($defaultLang['ShortName']);
   $category->setDescription($defaultLang['Description']);
   $category->setStatus('active');

   $em->persist($category);
   $em->flush(); // Need ID

   // Traduceri pentru alte limbi
   foreach ($translations as $langCode => $data) {
       if ($langCode === $defaultLangCode) continue; // Skip default

       $repo->translate($category, 'name', $langCode, $data['Name']);
       $repo->translate($category, 'slug', $langCode, $data['ShortName']);
       $repo->translate($category, 'description', $langCode, $data['Description']);
   }

   // Log mapping (folosește Number ca Newscoop ID!)
   INSERT INTO newscoop_id_mapping VALUES ('section', $number, $category->getId());
   ```

4. **Fallback pentru secțiuni fără mapping:**
   - Creare "Uncategorized" (en), "Necategorizat" (ro), "Некатегоризировано" (ru)

**Output:**
```
Categories Import Summary:
✓ Processed: 18 unique sections (from 43 rows)
✓ Created: 15-18 categories
✓ Translations added: 25-30 (ro/en/ru)
⚠ Skipped (duplicates/incomplete): 0-3
⚠ Manual validation needed: Number 10 (video/opinii inconsistency)
✗ Failed: 0
```

---

### **FAZA 3: Import Autori (20 min)**

**Comandă:**
```bash
php bin/console app:newscoop:import:authors [--dry-run] [--with-images]
```

**Proces:**

1. **Query Newscoop:**
   ```sql
   SELECT id, first_name, last_name, email, biography, image
   FROM Authors
   ORDER BY id;
   ```

2. **Pentru fiecare autor:**
   ```php
   // Check duplicate email
   if ($email && $existingAuthor = $authorRepo->findOneBy(['email' => $email])) {
       // Log duplicate, skip sau merge
       continue;
   }

   $author = new Author();
   $author->setFirstName($firstName ?: 'Unknown');
   $author->setLastName($lastName ?: 'Unknown');
   $author->setEmail($email ?: null);
   $author->setBio($biography); // Doar în limba default

   // Image (dacă --with-images și image ID exists)
   if ($imageId && $withImages) {
       $profileImage = $this->importAuthorImage($imageId);
       $author->setProfileImage($profileImage);
   }

   $em->persist($author);

   // Log mapping
   INSERT INTO newscoop_id_mapping VALUES ('author', $newscoopId, $author->getId());
   ```

3. **Creare "Unknown Author" fallback:**
   ```php
   $unknownAuthor = new Author();
   $unknownAuthor->setFirstName('Unknown');
   $unknownAuthor->setLastName('Author');
   $unknownAuthor->setEmail('unknown@system.local');
   ```

**Output:**
```
Authors Import Summary:
✓ Total authors in Newscoop: 282
✓ Imported: X
✓ Skipped (duplicate email): X
✓ Profile images imported: X
⚠ Authors with missing email: X
⚠ Images not found: X
✗ Failed: X

Created fallback author: Unknown Author (ID: X)
```

---

### **FAZA 4: Import Imagini Folosite + Resize 640×427 (8.6 ore)**

**Volum (ACTUALIZAT - Decizie Finală):**
- **155,332 imagini** (doar folosite în articole Published='Y')
- **Profil unic:** article_card (640×427, aspect ratio 3:2)
- **Optimizare:** JPEG quality 85% + WebP format
- **Spațiu disk final:** 12.77 GB (vs 88 GB original = **85.5% economie**)
- **Dimensiune medie:** ~86 KB/imagine (vs ~533 KB original)
- **Coverage:** 99.8% articole au imagini, 93.96% din total imagini

**Comandă:**
```bash
php bin/console app:newscoop:import:images \
    --source-path=/home/radu/ext-hdd/alpha \
    --published-only \      # ✅ Doar pentru articole Published='Y'
    --profile=article_card \  # ✅ Doar profil 640×427
    --batch-size=100 \
    --limit=1000 \  # Pentru test, apoi remove
    [--dry-run] \
    [--skip-thumbnails]  # Skip async generation pentru test rapid
```

**Proces:**

1. **Query Newscoop Images (doar folosite în Published='Y'):**
   ```sql
   SELECT DISTINCT
       i.Id, i.ImageFileName, i.Location, i.URL,
       i.Description, i.width, i.height,
       i.Photographer, i.Source, i.TimeCreated,
       i.ContentType
   FROM Images i
   INNER JOIN ArticleImages ai ON i.Id = ai.IdImage
   INNER JOIN Articles a ON ai.NrArticle = a.Number
   WHERE i.Location = 'local'
     AND a.Type = 'stiri'
     AND a.Published = 'Y'  -- ✅ Doar pentru articole publicate
   ORDER BY i.Id;
   ```

   **Note:**
   - `INNER JOIN` garantează doar imagini efectiv folosite
   - `Published='Y'` consistent cu decizia articole
   - **Rezultat:** 155,332 imagini (vs 165,302 total)

2. **Pentru fiecare imagine (batch 100) cu resize 640×427:**
   ```php
   // Construire path original
   $sourceFile = $sourcePath . '/' . $imageFileName;  // ex: cms-image-000000017.png

   // Verificare existență
   if (!file_exists($sourceFile)) {
       $logger->warning("Image file not found", ['id' => $id, 'file' => $imageFileName]);
       continue;
   }

   // Citire fișier ORIGINAL (pentru hash)
   $fileContent = file_get_contents($sourceFile);
   $hash = hash('sha256', $fileContent);

   // Check duplicate prin hash
   if ($existingImage = $imageRepo->findOneBy(['hash' => $hash])) {
       // Map la existent
       INSERT INTO newscoop_id_mapping VALUES ('image', $newscoopId, $existingImage->getId());
       continue;
   }

   // ✅ RESIZE la 640×427 (article_card profile)
   $resizedImagePath = $this->imageService->resizeImage(
       $sourceFile,
       640,  // width
       427,  // height
       'crop',  // fit mode (crop to exact dimensions)
       85    // JPEG quality (optimizare)
   );

   // Hash DUPĂ resize (pentru storage deduplicated)
   $resizedContent = file_get_contents($resizedImagePath);
   $resizedHash = hash('sha256', $resizedContent);

   // Creare Image entity cu dimensiuni RESIZE
   $image = new Image();
   $image->setFilename($resizedHash . '.jpg');  // Întotdeauna JPG
   $image->setOriginalFilename($imageFileName);
   $image->setHash($resizedHash);
   $image->setMimeType('image/jpeg');
   $image->setSize(filesize($resizedImagePath));

   // Metadata RESIZE (nu original!)
   $image->setWidth(640);   // Fixed width
   $image->setHeight(427);  // Fixed height

   // Extract dominant color & blur placeholder din RESIZED
   $dominantColor = $this->imageService->extractDominantColor($resizedImagePath);
   $blurPlaceholder = $this->imageService->generateBlurPlaceholder($resizedImagePath);
   $image->setDominantColor($dominantColor);
   $image->setBlurPlaceholder($blurPlaceholder);

   // Metadata din Newscoop
   $image->setAltText($description);
   $image->setDescription($description);
   $image->setAuthor($photographer);  // Photographer → author
   $image->setSource($source);

   // Copiere fișier RESIZED (nu original!)
   $destPath = $this->imageService->getStoragePath($resizedHash);
   copy($resizedImagePath, $destPath);
   $image->setPath($destPath);

   $em->persist($image);

   // ✅ Trigger async thumbnail generation DOAR pentru article_card
   // (JPG + WebP variants)
   if (!$skipThumbnails) {
       $messageBus->dispatch(
           new GenerateImageThumbnailsMessage(
               $image->getId(),
               ['article_card']  // Doar acest profil!
           )
       );
   }

   // Log mapping
   INSERT INTO newscoop_id_mapping VALUES ('image', $newscoopId, $image->getId());

   // Cleanup temp resized file
   unlink($resizedImagePath);
   ```

3. **Batch flush la fiecare 100:**
   ```php
   if ($i % 100 === 0) {
       $em->flush();
       $em->clear();
       gc_collect_cycles();
   }
   ```

**Output (ACTUALIZAT cu resize 640×427):**
```
Images Import Progress (Published='Y' only):
[████████████████████████████████] 155,332/155,332 (100%)

Summary:
✓ Total images in Newscoop DB: 165,302
✓ Images used in Published='Y': 155,332 (93.96%)
✓ Files found on disk: 154,800 (99.7%)
✓ Imported with resize 640×427: 154,300 (99.3%)
✓ Skipped (duplicate hash after resize): 500 (0.3%)
⚠ Files not found on disk: 532 (0.3%)
✗ Failed (resize/processing error): 32 (0.02%)

Storage (AFTER resize):
  ✅ Total size: 12.77 GB (vs 80.7 GB original = 84.2% economie!)
  ✅ Average per image: ~86 KB (vs ~533 KB original)
  ✅ All images: 640×427 @ JPEG quality 85%

Profile Used:
  ✅ article_card only (640×427, aspect ratio 3:2)
  ✅ Format: JPEG (quality 85%)
  ✅ Fit mode: crop (exact dimensions)

Thumbnails:
  ⏳ Queued for async generation: 154,300 × 2 formats = 308,600
     - article_card.jpg (JPEG quality 85%)
     - article_card.webp (WebP quality 85%)
  ⏱️  Estimated time: 2-3 hours (doar 1 profil vs 6 profile)

Coverage:
  ✅ 99.8% articole Published='Y' au imagini
  ✅ Medie: 1.47 imagini/articol
  ✅ 89.9% articole cu 1 imagine (featured)
```

---

### **FAZA 5: Import Articole + Traduceri + Processing (2.5-3h)**

**Volum (ACTUALIZAT cu Published='Y'):**
- **152,523 articole unice** (Type='stiri' + Published='Y')
- **21,147 traduceri** (ext_translations)
- **13,323 articole** cu shortcode processing (8.73%)
- **Total înregistrări:** 173,670

**Comandă:**
```bash
php bin/console app:newscoop:import:articles \
    --type=stiri \
    --published=Y \  # ✅ DOAR ARTICOLE PUBLICATE
    --batch-size=50 \
    --limit=100 \  # Pentru test
    [--dry-run] \
    [--skip-images] \  # Skip article-image relationships dacă imaginile nu sunt importate
    [--date-from=2016-01-01] \  # Filter by PublishDate (opțional)
    [--language=ro]  # Import doar o limbă specific (opțional)
```

**Proces:**

1. **Query Articles + Xstiri (JOIN) cu filtrare Published='Y':**
   ```sql
   SELECT
       a.Number, a.IdLanguage, a.Type, a.Name,
       a.Published, a.PublishDate, a.UploadDate, a.time_updated,
       a.Keywords, a.OnFrontPage, a.IdUser, a.NrSection,
       x.FTitlu, x.Fsubtitlu, x.Flead, x.FContinut,
       x.FBREAKING_NEWS, x.FNEWS_ALERT, x.FFLASH
   FROM Articles a
   INNER JOIN Xstiri x ON a.Number = x.NrArticle AND a.IdLanguage = x.IdLanguage
   WHERE a.Type = 'stiri'
     AND a.Published = 'Y'  -- ✅ DOAR PUBLICATE
   ORDER BY a.Number, a.IdLanguage;
   ```

   **Note:**
   - `INNER JOIN` garantează că avem conținut în Xstiri
   - `Published = 'Y'` exclude draft-uri (N) și submitted (S)
   - **Rezultat:** 173,670 înregistrări (152,523 unice + 21,147 traduceri)

2. **Grupare după Number:**
   ```php
   $articleGroups = [];
   foreach ($rows as $row) {
       $articleGroups[$row['Number']][$row['IdLanguage']] = $row;
   }
   ```

3. **Pentru fiecare grup (= un articol multilingual):**
   ```php
   $articleData = $articleGroups[$number];

   // Alegere limba default (prioritate: ro → en → prima)
   $defaultLang = $articleData[2] ?? $articleData[1] ?? reset($articleData);
   $defaultLocale = $this->mapLanguageId($defaultLang['IdLanguage']); // 2→'ro', 1→'en', 15→'ru'

   // Decodare BLOB
   $rawContent = stream_get_contents($defaultLang['FContinut']);
   $rawLead = stream_get_contents($defaultLang['Flead']);

   // ⚠️ CRITIC: Procesare shortcodes Newscoop → HTML modern
   // Fără aceasta, 13,323 articole (Published='Y') vor avea imagini BROKEN!
   $processedContent = $this->contentProcessor->processContent(
       $rawContent,
       $number  // Newscoop article number pentru logging
   );

   // Lead nu conține de obicei shortcodes, dar procesăm pentru siguranță
   $processedLead = $this->contentProcessor->processContent(
       $rawLead,
       $number
   );

   // Creare Article
   $article = new Article();
   $article->setTitle($defaultLang['FTitlu'] ?: $defaultLang['Name']);
   $article->setLead($processedLead);  // Lead procesat
   $article->setContent($processedContent);  // Content procesat - IMAGINI FUNCȚIONALE!

   // Status mapping
   // ✅ SIMPLIFICAT: Toate articolele importate sunt Published='Y'
   $article->setStatus('published');  // Întotdeauna 'published' (filtrăm la query)

   // Badge mapping
   $badge = 'none';
   if ($defaultLang['FBREAKING_NEWS']) $badge = 'breaking';
   elseif ($defaultLang['FNEWS_ALERT']) $badge = 'alert';
   elseif ($defaultLang['FFLASH']) $badge = 'flash';
   $article->setBadge($badge);

   // Dates
   $article->setPublishedAt($defaultLang['PublishDate'] ? new \DateTimeImmutable($defaultLang['PublishDate']) : null);
   $article->setCreatedAt(new \DateTimeImmutable($defaultLang['UploadDate']));
   $article->setUpdatedAt(new \DateTimeImmutable($defaultLang['time_updated']));

   // Category (lookup mapping table)
   $categoryId = $this->getMappedId('section', $defaultLang['NrSection']);
   $category = $categoryRepo->find($categoryId) ?? $fallbackCategory;
   $article->setCategory($category);

   // Authors (query ArticleAuthors)
   $authors = $this->getArticleAuthors($number, $defaultLang['IdLanguage']);
   foreach ($authors as $authorData) {
       $authorId = $this->getMappedId('author', $authorData['fk_author_id']);
       $author = $authorRepo->find($authorId) ?? $unknownAuthor;

       // ManyToMany Article ↔ Author (implementat!)
       $article->addAuthor($author);
   }

   // Tags (din Keywords)
   if ($keywords = $defaultLang['Keywords']) {
       $tagNames = array_map('trim', explode(',', $keywords));
       foreach ($tagNames as $tagName) {
           $tag = $tagRepo->findOneBy(['name' => $tagName]) ?? new Tag($tagName);
           $article->addTag($tag);
       }
   }

   // Persist article default language
   $em->persist($article);

   // ⚠️ IMPORTANT: Process translations BEFORE flush
   // Procesare traduceri pentru alte limbi
   $otherLanguages = array_filter($articleData, fn($langId) => $langId !== $defaultLang['IdLanguage'], ARRAY_FILTER_USE_KEY);

   foreach ($otherLanguages as $langId => $langData) {
       $locale = $this->mapLanguageId($langId); // 2→'ro', 1→'en', 15→'ru'

       // Decodare BLOB pentru traducere
       $rawContentTrans = stream_get_contents($langData['FContinut']);
       $rawLeadTrans = stream_get_contents($langData['Flead']);

       // ⚠️ CRITIC: Procesare shortcodes pentru traduceri!
       $processedContentTrans = $this->contentProcessor->processContent($rawContentTrans, $number);
       $processedLeadTrans = $this->contentProcessor->processContent($rawLeadTrans, $number);

       // Set translatable locale
       $article->setTranslatableLocale($locale);
       $article->setTitle($langData['FTitlu'] ?: $langData['Name']);
       $article->setLead($processedLeadTrans);
       $article->setContent($processedContentTrans);

       // Persist pentru această limbă
       $em->persist($article);

       $this->logger->info('Translation processed', [
           'article_number' => $number,
           'locale' => $locale,
           'title' => $langData['FTitlu'],
       ]);
   }

   // Reset la limba default
   $article->setTranslatableLocale($defaultLocale);

   // Metadata (opțional)
   $article->setMetadata([
       'newscoop_number' => $number,
       'newscoop_type' => 'stiri',
       'languages_count' => count($articleData),  // Câte limbi are acest articol
       'available_locales' => array_map(fn($id) => $this->mapLanguageId($id), array_keys($articleData)),
       'on_front_page' => $defaultLang['OnFrontPage'] === 'Y',
       'subtitle' => $defaultLang['Fsubtitlu'] ?? null,
   ]);

   $em->persist($article);
   $em->flush(); // Need ID pentru translations

   // Traduceri pentru alte limbi
   unset($articleData[$defaultLang['IdLanguage']]); // Remove default
   foreach ($articleData as $langId => $translation) {
       $locale = $this->mapLanguageId($langId);

       // Decodare BLOB pentru traducere
       $rawTransContent = stream_get_contents($translation['FContinut']);
       $rawTransLead = stream_get_contents($translation['Flead']);

       // ⚠️ IMPORTANT: Procesare shortcodes și în traduceri!
       $processedTransContent = $this->contentProcessor->processContent(
           $rawTransContent,
           $number  // Same article number
       );
       $processedTransLead = $this->contentProcessor->processContent(
           $rawTransLead,
           $number
       );

       $repo->translate($article, 'title', $locale, $translation['FTitlu']);
       $repo->translate($article, 'content', $locale, $processedTransContent);  // Procesat!
       $repo->translate($article, 'lead', $locale, $processedTransLead);  // Procesat!
       // slug auto-generate prin Gedmo
   }

   // Images (dacă nu --skip-images)
   if (!$skipImages) {
       $articleImages = $this->getArticleImages($number);
       foreach ($articleImages as $imgData) {
           $imageId = $this->getMappedId('image', $imgData['IdImage']);
           if ($image = $imageRepo->find($imageId)) {
               $article->addImage($image);

               // Featured image
               if ($imgData['is_default']) {
                   $article->setFeaturedImage($image);
               }
           }
       }
   }

   // Related Articles (din context_boxes)
   $relatedArticles = $this->getRelatedArticles($number);
   foreach ($relatedArticles as $relatedData) {
       $relatedId = $this->getMappedId('article', $relatedData['fk_article_no']);
       if ($relatedArticle = $articleRepo->find($relatedId)) {
           $article->addRelatedArticle($relatedArticle);
       }
   }

   $em->flush();
   $em->clear(); // Clear memory

   // Log
   INSERT INTO migration_log VALUES (
       $batchId, 'article', $article->getId(), $number, 'success', null
   );
   ```

4. **Batch processing:**
   ```php
   foreach (array_chunk($articleGroups, 50) as $batch) {
       // Process batch
       $em->flush();
       $em->clear();
       gc_collect_cycles();
   }
   ```

**Output (ACTUALIZAT cu Published='Y'):**
```
Articles Import Progress:
[████████████████████████████████] 173,670/173,670 (100%)

Batch #3474: Processing articles 173,601-173,650...
  ✓ Article 173,601 (ro, ru) → ID 152,500
  ✓ Article 173,602 (ro) → ID 152,501
  ⚠ Article 173,603: Missing author, using fallback
  ⚠ Article 173,604: Shortcode processing: 3 images transformed
  ✗ Article 173,605: Failed (error details...)

Summary:
✓ Total articles processed: 173,670 (Published='Y' only)
✓ Successfully imported: 172,800 (99.5%)
✓ Unique articles created: 152,523
✓ Translations added to ext_translations: 21,147
✓ Shortcodes processed: 13,323 articles (8.73%)
✓ Tags created: ~5,200
✓ Images linked: ~230,000
⚠ Used fallback category: 1,234
⚠ Used fallback author: 567
⚠ Missing images skipped: 3,456
✗ Failed: 1,126

Content Processing Statistics (SHORTCODES):
✓ Image shortcodes processed: 16,045 (99.2% success)
⚠ Image shortcodes failed (image not found): 137 (0.8%)
✓ Internal link shortcodes processed: 3 (100% success)
⚠ Internal link shortcodes broken: 0
✓ Subheadings converted: 1,234
✓ Snippets removed: 45
📊 Total regex operations: ~48,000 (3 passes × 16k articles with shortcodes)

Translation Statistics (MULTILINGUAL):
✓ Articles with translations: 20,703 (13.37%)
✓ Monolingual articles: 134,102 (86.63%)

Distribution by language count:
  ├─ 1 language (monolingual): 134,102 articles
  ├─ 2 languages (bilingual): 20,004 articles
  └─ 3 languages (trilingual): 699 articles

Translation combinations:
  ├─ Romanian only: 126,211 articles (81.53%)
  ├─ Romanian + Russian: 19,559 articles (12.63%)
  ├─ Romanian + Russian + English: 699 articles (0.45%)
  ├─ Russian only: 7,859 articles (5.08%)
  ├─ Romanian + English: 445 articles (0.29%)
  └─ English only: 32 articles (0.02%)

Total translations by language:
  ├─ Romanian: 146,914 records (83.38% of all article records)
  ├─ Russian: 28,117 records (15.96% of all article records)
  └─ English: 1,176 records (0.67% of all article records)

⚠️ IMPORTANT: Verifică că toate traducerile au fost importate corect!
   Run post-validation queries to ensure translation integrity.

Performance:
  Duration: 2h 54m 18s (includes +15% content processing overhead)
  Average: 17 articles/sec (down from 19 due to regex processing)
  Peak memory: 512 MB
  Content processing: ~10ms average per article with shortcodes
```

---

### **FAZA 6: Post-Migration Validare (30 min)**

**Comandă:**
```bash
php bin/console app:newscoop:post-validate
```

**Verificări:**

1. **Integritate Referințe:**
   ```sql
   -- Articole fără categorie
   SELECT COUNT(*) FROM article WHERE category_id IS NULL;

   -- Articole fără autor (dacă single author)
   SELECT COUNT(*) FROM article WHERE author_id IS NULL;

   -- Articole fără traduceri
   SELECT a.id FROM article a
   LEFT JOIN ext_translations t ON t.foreign_key::int = a.id
       AND t.object_class = 'App\\Entity\\Article'
   WHERE t.id IS NULL;

   -- Imagini fără thumbnails
   SELECT i.id FROM image i
   LEFT JOIN thumbnail t ON t.image_id = i.id
   WHERE t.id IS NULL;

   -- Verificare related articles
   SELECT COUNT(*) as articles_with_related,
          SUM(related_count) as total_relationships
   FROM (
       SELECT article_id, COUNT(*) as related_count
       FROM article_related
       GROUP BY article_id
   ) as stats;
   ```

2. **Comparație Statistici:**
   ```
   NEWSCOOP vs NEWS_APP:

   Articles:
     Newscoop (stiri): 175,626
     news_app: 174,500
     Difference: -1,126 (0.64%)

   Categories:
     Newscoop Sections: 43
     news_app: 15 (grouped by name)

   Authors:
     Newscoop: 282
     news_app: 283 (includes Unknown Author)

   Images:
     Newscoop: 165,302
     news_app: 162,100
     Difference: -3,202 (files not found)

   Related Articles:
     Newscoop: 154,748 articles with related (3,807 relationships)
     news_app: X articles with related (Y relationships)
     Average related per article: ~2.5
   ```

3. **Sample Content Check:**
   ```php
   // Random 10 articles - compare Newscoop vs news_app
   SELECT a.Number, a.IdLanguage, x.FTitlu
   FROM Articles a
   JOIN Xstiri x ON a.Number = x.NrArticle AND a.IdLanguage = x.IdLanguage
   WHERE a.Type = 'stiri'
   ORDER BY RAND()
   LIMIT 10;

   // Fetch în news_app și compare title, content length
   ```

4. **Content Processing Validation (CRITIC!):**
   ```sql
   -- Verificare shortcodes neprocesate (NU TREBUIE SĂ EXISTE!)
   SELECT COUNT(*) as broken_images
   FROM article
   WHERE content LIKE '%<!** Image%'
      OR lead LIKE '%<!** Image%';

   -- RESULT AȘTEPTAT: 0
   -- Dacă > 0: EROARE CRITICĂ - shortcodes neprocesate!

   -- Verificare link-uri interne neprocesate
   SELECT COUNT(*) as broken_links
   FROM article
   WHERE content LIKE '%<!** Link Internal%';

   -- RESULT AȘTEPTAT: 0

   -- Verificare imagini în content (trebuie să fie <figure><img>)
   SELECT COUNT(*) as articles_with_figures
   FROM article
   WHERE content LIKE '%<figure%'
      AND content LIKE '%<img%';

   -- RESULT AȘTEPTAT: ~16,000+ articole

   -- Sample verificare calitate HTML
   SELECT id, LEFT(content, 500) as content_sample
   FROM article
   WHERE content LIKE '%<figure%'
   LIMIT 5;

   -- Verificare manual: HTML corect formatat, fără shortcodes
   ```

5. **Translation Validation (OBLIGATORIU!):**
   ```sql
   -- ⚠️ VERIFICARE CRITICĂ: Traduceri importate corect

   -- Statistici traduceri în news_app
   SELECT
       COUNT(DISTINCT a.id) as total_articles,
       COUNT(DISTINCT CASE WHEN et.locale IS NOT NULL THEN a.id END) as articles_with_translations,
       COUNT(et.id) as total_translations
   FROM article a
   LEFT JOIN ext_translations et ON et.foreign_key::text = a.id::text
       AND et.object_class = 'App\\Entity\\Article';

   -- RESULT AȘTEPTAT:
   -- total_articles: ~154,805
   -- articles_with_translations: ~20,703 (13.37%)
   -- total_translations: ~21,402

   -- Verificare distribuție traduceri pe limbi
   SELECT
       et.locale,
       COUNT(*) as translation_count,
       COUNT(DISTINCT et.foreign_key) as articles_translated
   FROM ext_translations et
   WHERE et.object_class = 'App\\Entity\\Article'
   GROUP BY et.locale
   ORDER BY translation_count DESC;

   -- RESULT AȘTEPTAT:
   -- 'ru': ~28,117 translations
   -- 'en': ~1,176 translations

   -- Verificare traduceri complete (articole cu 2 limbi în Newscoop)
   SELECT COUNT(*) as expected_bilingual
   FROM (
       SELECT Number FROM newscoop.Articles
       GROUP BY Number
       HAVING COUNT(*) = 2
   ) as bilingual;

   -- Compară cu:
   SELECT COUNT(DISTINCT foreign_key) as actual_articles_with_one_translation
   FROM ext_translations
   WHERE object_class = 'App\\Entity\\Article'
   GROUP BY foreign_key
   HAVING COUNT(*) = 1;

   -- ⚠️ CELE 2 NUMERE TREBUIE SĂ FIE APROPIATE! (~20,004)

   -- Verificare traduceri trilingve (ro+ru+en)
   SELECT COUNT(DISTINCT foreign_key) as trilingual_articles
   FROM ext_translations
   WHERE object_class = 'App\\Entity\\Article'
   GROUP BY foreign_key
   HAVING COUNT(DISTINCT locale) = 2;  -- 2 traduceri + default = 3 limbi

   -- RESULT AȘTEPTAT: ~699 articole

   -- Sample verificare: articol cu traduceri complete
   SELECT
       a.id,
       a.title as default_title,
       et.locale,
       et.content->>'title' as translated_title
   FROM article a
   JOIN ext_translations et ON et.foreign_key::text = a.id::text
   WHERE et.object_class = 'App\\Entity\\Article'
     AND a.id IN (
         SELECT foreign_key::integer
         FROM ext_translations
         WHERE object_class = 'App\\Entity\\Article'
         GROUP BY foreign_key
         HAVING COUNT(*) >= 2
         LIMIT 5
     )
   ORDER BY a.id, et.locale;

   -- Verificare manual: titlurile sunt diferite în limbi diferite

   -- ❌ CĂUTARE PROBLEME: Articole care ar fi trebuit să aibă traduceri dar nu au
   WITH newscoop_translated AS (
       SELECT DISTINCT Number
       FROM newscoop.Articles
       GROUP BY Number
       HAVING COUNT(*) > 1
   ),
   newsapp_translated AS (
       SELECT DISTINCT foreign_key::integer as article_id
       FROM ext_translations
       WHERE object_class = 'App\\Entity\\Article'
   )
   SELECT COUNT(*) as missing_translations
   FROM newscoop_translated nt
   LEFT JOIN newscoop_id_mapping nim ON nim.newscoop_id = nt.Number
       AND nim.entity_type = 'article'
   LEFT JOIN newsapp_translated nat ON nat.article_id = nim.news_app_id
   WHERE nat.article_id IS NULL;

   -- RESULT AȘTEPTAT: 0 (sau foarte puține - doar cele cu erori)
   -- Dacă > 100: PROBLEMA MAJORĂ în procesarea traducerilor!
   ```

6. **Error Report:**
   ```sql
   SELECT entity_type, status, COUNT(*) as count,
          array_agg(DISTINCT error_message) as errors
   FROM migration_log
   GROUP BY entity_type, status;
   ```

**Output:**
```
Post-Migration Validation Report
================================

✓ PASSED CHECKS:
  - All categories have valid translations
  - No orphaned article-author relationships
  - Image hash uniqueness maintained
  - Published articles have publishedAt dates
  - ✅ Content shortcodes processed: 0 unparsed shortcodes found
  - ✅ Images in content: 16,045 articles with <figure><img> tags
  - ✅ HTML quality: All sampled articles have valid HTML5
  - ✅ Translation counts match Newscoop:
      * Total articles with translations: 20,703 (expected: 20,703)
      * Russian translations: 28,117 (expected: 28,117)
      * English translations: 1,176 (expected: 1,176)
      * Bilingual articles: 20,004 (expected: 20,004)
      * Trilingual articles: 699 (expected: 699)
  - ✅ Missing translations: 0 (all translated articles preserved)

⚠ WARNINGS:
  - 1,234 articles using fallback category "Uncategorized"
  - 567 articles using fallback author "Unknown Author"
  - 3,202 images not imported (files not found)
  - 134,102 articles have only one language (monolingve în Newscoop - normal)
  - 137 image shortcodes failed (image not found in mapping - 0.8%)

✗ ERRORS:
  - 45 articles with empty content (need manual review)
  - 12 duplicate image hashes (possible corruption)
  - 3 categories with identical slugs (conflict)
  - ❌ 0 articles with unprocessed shortcodes (GOOD!)
  - ❌ 0 missing translations (GOOD!)

RECOMMENDATIONS:
  1. Review articles with fallback category (IDs: 123, 456, ...)
  2. Check missing image files in Newscoop storage
  3. Manually fix empty content articles
  4. Resolve category slug conflicts
  5. Consider re-importing failed articles (1,126 total)

Full error log: var/log/migration_errors.log
Detailed report: var/reports/migration_report_2025-10-25.json
```

---

## 🚀 Comenzi de Implementat

```
backend/src/Command/Newscoop/
├── ValidateCommand.php                    # Faza 1
├── ImportCategoriesCommand.php            # Faza 2
├── ImportAuthorsCommand.php               # Faza 3
├── ImportImagesCommand.php                # Faza 4
├── ImportArticlesCommand.php              # Faza 5
├── PostValidateCommand.php                # Faza 6
└── RollbackCommand.php                    # Rollback util
```

**Servicii Auxiliare:**
```
backend/src/Service/Newscoop/
├── NewscoopDatabaseService.php            # MariaDB queries
├── NewscoopMapperService.php              # Data mapping
├── NewscoopContentProcessor.php           # ⚠️ CRITIC! Shortcode processing
├── ImageMigrationService.php              # Image processing
├── MigrationLoggerService.php             # Logging & tracking
└── ValidationService.php                  # Data validation
```

---

## ⚙️ Parametri Comuni pentru Toate Comenzile

| Flag | Descriere |
|------|-----------|
| `--dry-run` | Preview fără a scrie în DB |
| `--verbose` / `-v` | Output detaliat |
| `--batch-size=N` | Procesare în batch-uri de N |
| `--limit=N` | Limită pentru teste |
| `--continue-on-error` | Continuă dacă întâmpină erori |
| `--log-file=path` | Custom log file path |

---

## 📊 Estimate Timp Total (ACTUALIZAT FINAL)

**Volumul Final de Import:**
- **152,523 articole unice** (Type='stiri' + Published='Y')
- **21,147 traduceri** (ext_translations)
- **155,332 imagini** (folosite în Published='Y', resize 640×427)
- **13,323 articole** cu shortcode processing (8.73%)
- **15-20 categorii** (din 18 secțiuni unice)
- **~283 autori**

| Fază | Durata | Paralelizabil | Volum |
|------|--------|---------------|-------|
| 0. Pregătire | 1-2 ore | Nu | Setup dual DB + servicii |
| 0.1. Implementare NewscoopContentProcessor | **2-3 ore** | Nu | **PRIORITATE!** |
| 1. Validare | 30 min | Nu | Statistici + checks |
| 2. Categorii | 15 min | Nu | 15-20 categorii (din 18 unice) |
| 3. Autori | 20 min | Nu | ~283 autori |
| 4. **Imagini (resize 640×427)** | **8.6 ore** | **Da (async)** | **155k imagini @ ~86 KB** |
| 5. Articole + **Content Processing** | **2.5-3 ore** | Parțial | **152k articole + 13k shortcodes** |
| 6. Post-validare + **Checks** | **45 min** | Nu | Shortcodes + traduceri |

**TOTAL (secvențial):** **15-18 ore**
**TOTAL (cu paralelizare Faza 4):** **~6-9 ore** active + 8.6 ore background thumbnails

**Beneficii optimizări Published='Y' + resize 640×427:**
- ✅ **-10-15 min** (mai puține articole: 152k vs 154k)
- ✅ **-2,859 articole** cu shortcodes (13k vs 16k)
- ✅ **-75 GB** disk space (12.77 GB vs 88 GB = **85.5% economie!**)
- ✅ **-70% timp** thumbnails (1 profil vs 6 profile)

**Strategie Optimă (ACTUALIZATĂ):**
1. **Implementare NewscoopContentProcessor:** ~3 ore (ÎNAINTE de Faza 0!)
2. Rulare Faza 0-3: ~2.5 ore (pregătire + categorii + autori)
3. **Start Faza 4 (imagini resize 640×427) → background** (8.6 ore async)
4. Rulare Faza 5 (articole + content processing): ~2.5-3 ore
5. Așteptare finalizare thumbnails: ~6 ore (background - JPG+WebP pentru article_card)
6. Faza 6 validare + shortcode + translation check: ~45 min

**Total real:** ~12-15 ore (8.6 ore background) = **~6-7 ore active work**

⚠️ **CRITIC:** Fără NewscoopContentProcessor, 13,323 articole (8.73%) vor avea imagini BROKEN!

---

## 🎯 Decizii Necesare - STATUS: ✅ 7/7 COMPLETE!

### **DECIZIE 1: Relația Article-Author** ✅ REZOLVATĂ

- [x] ~~**Opțiunea C:** Refactorizare ManyToMany~~ ✅ **IMPLEMENTAT** (Version20251025213637)

### **DECIZIE 2: Tipuri Articole + Status** ✅ DECISĂ

- [x] ~~Import doar `stiri` (99.67% - simplu)~~ ✅ **DECISĂ**
- [x] ~~Import doar Published='Y'~~ ✅ **DECISĂ**
- **Rezultat:** 152,523 articole unice (98.85% coverage)
- **Detalii:** Vezi `PUBLISHED_ARTICLES_ANALYSIS.md`

### **DECIZIE 3: Imagini** ✅ DECISĂ

- [x] ~~Import doar imagini folosite în articole Published='Y'~~ ✅ **DECISĂ**
- [x] ~~Resize 640×427 (article_card profile)~~ ✅ **DECISĂ**
- [x] ~~Optimizare JPEG quality 85%~~ ✅ **DECISĂ**
- **Rezultat:** 155,332 imagini, 12.77 GB (85.5% economie)
- **Detalii:** Vezi `IMAGE_IMPORT_ANALYSIS.md`

### **DECIZIE 4: Secțiuni** ✅ ANALIZATĂ

- [x] ~~Analiză completă structură secțiuni~~ ✅ **COMPLETĂ**
- **Rezultat:** 43 rânduri → 18 secțiuni unice (Number = Section ID)
- **Strategie:** Grupare după Number pentru traduceri, import 15-18 categorii
- **Detalii:** Vezi `SECTIONS_ANALYSIS.md`

### **DECIZIE 5: Content Processing** ✅ DECISĂ

- [x] ~~Implementare NewscoopContentProcessor~~ ✅ **DECISĂ - OBLIGATORIE**
- **Impact:** 13,323 articole (8.73%) cu shortcodes
- **Timp:** 2-3 ore implementare + 15% overhead la import
- **Detalii:** Vezi `CONTENT_PROCESSING_ANALYSIS.md`

### **DECIZIE 6: Traduceri** ✅ DECISĂ

- [x] ~~Strategie import multilingual~~ ✅ **DECISĂ**
- **Strategie:** Gedmo Translatable cu ext_translations
- **Volum:** 21,147 traduceri (13.8% articole)
- **Detalii:** Vezi `TRANSLATION_ANALYSIS.md`

### **DECIZIE 7: Related Articles** ✅ DECISĂ

- [x] ~~Opțiune implementare~~ ✅ **DECISĂ - Opțiunea 1 (Minimal)**
- **Status:** 95% implementat (lipsește doar import logic)
- **Volum:** ~3,800 relații
- **Detalii:** Vezi `RELATED_ARTICLES_ANALYSIS.md`

---

## ✅ Checklist Pre-Implementare

### Decizii Strategice (7/7 Complete)
- [x] Article-Author: ManyToMany ✅ IMPLEMENTAT
- [x] Tipuri + Status: Type='stiri' + Published='Y' ✅
- [x] Imagini: Folosite + resize 640×427 ✅
- [x] Sections: 18 unice → 15-18 categorii ✅
- [x] Content Processing: NewscoopContentProcessor ✅
- [x] Traduceri: Gedmo Translatable ✅
- [x] Related Articles: Opțiunea 1 (Minimal) ✅

### Documentație (8/8 Complete)
- [x] NEWSCOOP_MIGRATION_PLAN.md (acest document)
- [x] PUBLISHED_ARTICLES_ANALYSIS.md
- [x] IMAGE_IMPORT_ANALYSIS.md
- [x] SECTIONS_ANALYSIS.md
- [x] CONTENT_PROCESSING_ANALYSIS.md
- [x] TRANSLATION_ANALYSIS.md
- [x] RELATED_ARTICLES_ANALYSIS.md
- [x] MIGRATION_SUMMARY.md

### Modificări Cod (1/2)
- [x] ~~Article-Author ManyToMany~~ ✅ COMPLETAT
- [ ] NewscoopContentProcessor service ⚠️ **SINGURA TASK RĂMASĂ**

---

## 🚀 Ready pentru Implementare!

**Status:** ✅ **TOATE DECIZIILE LUATE, PLAN COMPLET**

**Volum final:**
- 152,523 articole unice
- 21,147 traduceri
- 155,332 imagini (12.77 GB)
- 15-20 categorii
- ~283 autori

**Timp total:** 12-18 ore (6-9 ore active + 8.6 ore background)
**Economie spațiu:** 75 GB (85.5%)

**Next Step:** Implementare NewscoopContentProcessor (2-3 ore), apoi Faza 0!

---

**Planificare finalizată: 2025-10-26** 📋
**Gata pentru execuție!** 🚀

---

## ✅ Next Steps

**După aprobarea planului:**

1. **Setup inițial:**
   - Configurare Doctrine dual connection
   - Creare migration_log și newscoop_id_mapping tables
   - Implementare servicii base (NewscoopDatabaseService, NewscoopMapperService)

2. **Implementare Faza 1 (Validare):**
   - Creare `ValidateCommand`
   - Rulare pe date reale
   - Analiză rezultate → rafinare plan

3. **Decizie pe baza rezultatelor validării:**
   - Verificare duplicate sections (traduceri vs entități separate)
   - Verificare articole cu mulți autori (statistici)
   - Verificare imagini missing (pattern?)

4. **Implementare faze 2-6 incremental**

---

## 📝 Notes

- **Backup:** Creează backup complet PostgreSQL înainte de import
- **Test Environment:** Testează pe bază clonată înainte de producție
- **Rollback:** Folosește `migration_log.batch_id` pentru rollback selective
- **Performance:** Monitor PostgreSQL connections, memory usage
- **Idempotency:** Comenzile trebuie să fie reentrant (poți relua dacă fail)

### Related Articles - Implementare Minimală

✅ **Decizie:** Opțiunea 1 (Minimal) - Sorting automat

**Implementare:**
1. Import relații din `context_boxes` → `article_related` (ManyToMany existent)
2. Ordinea `order_number` din Newscoop se ignoră (arhivă - nu e critică)
3. Sorting în frontend/API prin:
   - `publishedAt DESC` (articole recente)
   - `views DESC` (articole populare)
   - `createdAt DESC` (fallback)

**Query example în Repository:**
```php
public function findRelatedArticlesSorted(Article $article): array
{
    return $this->createQueryBuilder('a')
        ->join('a.relatedArticles', 'ra')
        ->where('a.id = :articleId')
        ->setParameter('articleId', $article->getId())
        ->orderBy('ra.publishedAt', 'DESC')
        ->getQuery()
        ->getResult();
}
```

**API Platform:** Adaugă custom query parameter pentru sorting
```php
#[QueryParameter('relatedSort', filter: new OrderFilter())]
```

---

**Pregătit pentru aprobare și implementare.**
