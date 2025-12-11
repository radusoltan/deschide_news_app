# Analiza Import Imagini: Doar Imagini Folosite în Articole Publicate

**Data analiză:** 2025-10-26
**Bază sursă:** MariaDB `newscoop` + filesystem `/home/radu/ext-hdd/alpha/`

---

## 🎯 Decizie: Criterii de Import

**Criteriile alese:**
1. ✅ **Doar imagini folosite în articole** (via `ArticleImages` table)
2. ✅ **Doar pentru articole Published='Y'** (consistent cu decizia articole)
3. ✅ **Profil unic: 640×427** (article_card - aspect ratio 3:2)
4. ✅ **Optimizare JPEG quality 85%** pentru reducere spațiu

**Justificare:**
- Import selectiv bazat pe utilizare reală în articole publicate
- Profil 640×427 optim pentru listări articole (cards, thumbnails)
- Reducere semnificativă spațiu disk (84% economie)
- Consistență cu decizia de import doar articole publicate

---

## 📊 Statistici Generale

### Volumul Total Newscoop

| Metric | Valoare |
|--------|---------|
| **Total imagini în DB** | 165,302 |
| **Total fișiere pe disk** | 1,484,526 (imagini + thumbnails) |
| **Spațiu total disk** | **88 GB** |
| **Dimensiune medie originală** | ~533 KB/imagine |
| **Dimensiuni medii** | 1356×891 pixels |

### Imagini Folosite în Articole Published='Y'

| Metric | Valoare |
|--------|---------|
| **Imagini unice folosite** | **155,332** ✅ |
| **Articole cu imagini** | 152,225 (99.8% din 152,523) |
| **Total relații ArticleImages** | 223,780 |
| **Coverage din total imagini** | **93.96%** |
| **Imagini neutilizate** | 9,970 (6.04%) ❌ |

**Concluzie:** 93.96% din imagini sunt efectiv folosite în articole publicate!

---

## 📸 Distribuție Imagini per Articol

| Nr. Imagini | Articole | Procent | Descriere |
|-------------|----------|---------|-----------|
| **1** | **136,914** | **89.94%** | O singură imagine (majoritatea) |
| **2** | 7,315 | 4.81% | 2 imagini |
| **3** | 2,953 | 1.94% | 3 imagini |
| **4** | 1,775 | 1.17% | 4 imagini |
| **5-10** | 2,798 | 1.84% | Galerii mici (5-10 imagini) |
| **11-20** | 424 | 0.28% | Galerii medii (11-20 imagini) |
| **21-50** | 40 | 0.03% | Galerii mari (21-50 imagini) |
| **50+** | 6 | 0.00% | Galerii foarte mari (max 85 imagini) |

**Observații:**
- **89.94%** articole au **doar 1 imagine** (featured image tipic)
- **96.86%** articole au **1-3 imagini**
- Medie: **1.47 imagini/articol**
- Maxim: **85 imagini** într-un articol (probabil galerie foto)

---

## 🖼️ Distribuție pe Format

| Format | Imagini Unice | Procent | Dimensiuni Medii | Range Dimensiuni |
|--------|---------------|---------|------------------|------------------|
| **JPEG** | **143,049** | **92.09%** | 1356×891 | 100×47 → 7667×7016 |
| **PNG** | **12,244** | **7.88%** | 1168×707 | 100×45 → 4969×6254 |
| **GIF** | **39** | **0.03%** | 1138×763 | 222×249 → 2200×1470 |
| **TOTAL** | **155,332** | **100%** | 1343×879 avg | - |

**Observații:**
- **92%** sunt JPEG (format dominant pentru fotografii)
- **8%** sunt PNG (probabil grafice, logo-uri, screenshots)
- GIF aproape inexistent (39 imagini = 0.03%)
- Dimensiuni variază de la 100×45 (thumbnails?) până la 7667×7016 (high-res)

---

## 💾 Analiza Spațiu Disk

### Volum Original

| Metric | Valoare |
|--------|---------|
| **Total imagini Newscoop** | 165,302 |
| **Spațiu total disk** | 88 GB (88,000 MB) |
| **Dimensiune medie/imagine** | ~533 KB |
| **Imagini de importat** | 155,332 (93.96%) |
| **Spațiu original pentru import** | **~80.7 GB** |

### După Resize 640×427 + Optimizare

**Parametri resize:**
- **Target dimensiuni:** 640×427 pixels (aspect ratio 3:2)
- **Area reduction:** 22.61% (273,280 vs 1,208,196 pixels avg)
- **JPEG quality:** 85% (optimizare fără pierdere vizibilă)
- **Compression factor:** ~0.70 (quality 85)

**Size reduction factor:** 0.1582
(= area_ratio 0.2261 × jpeg_quality 0.70)

| Metric | Valoare |
|--------|---------|
| **Spațiu după resize** | **12.77 GB** ✅ |
| **Dimensiune medie/imagine** | **~86 KB** |
| **Economie spațiu** | **67.97 GB (84.18%)** 💾 |
| **Reducere** | **84.18%** din volumul original |

**Comparație:**

| Scenariu | Imagini | Spațiu Disk | vs Original |
|----------|---------|-------------|-------------|
| **Original (toate)** | 165,302 | 88 GB | - |
| **Original (folosite)** | 155,332 | 80.7 GB | -8.3% |
| **Resize 640×427 (folosite)** | **155,332** | **12.77 GB** | **-84.5%** ✅ |

---

## ⏱️ Timp Estimat Import

### Parametri Procesare

| Parametru | Valoare | Detalii |
|-----------|---------|---------|
| **Imagini de importat** | 155,332 | Doar folosite în Published='Y' |
| **Viteză procesare** | ~5 imagini/sec | Cu resize + optimizare JPEG |
| **Operations per imagine** | - | Download → Resize → Optimize → Upload → DB insert |

### Estimare Timp

| Metric | Valoare |
|--------|---------|
| **Timp total estimat** | **8.6 ore** (517 minute) |
| **În background** | Da (rulare paralelă cu Faza 5) |
| **Batch size recomandat** | 100 imagini |
| **Memory usage** | ~500 MB (GD library resize) |

**Breakdown:**
- **Download de la Newscoop storage:** ~1-2 ore (I/O disk)
- **Resize + Optimize:** ~5-6 ore (CPU intensive)
- **Upload + DB insert:** ~1-2 ore (I/O + DB)

**Optimizare:** Rulare în background în timp ce se importă articolele (Faza 5)

---

## 🎨 Profil Thumbnails: article_card (640×427)

### Specificații Profil

```php
// config/packages/app.yaml
app:
    image:
        profiles:
            article_card:
                width: 640
                height: 427
                fit: 'crop'           # Crop to exact dimensions
                quality: 85           # JPEG quality (optimal size/quality)
                format: ['jpg', 'webp']  # Both formats for browser compatibility
                progressive: true     # Progressive JPEG loading
```

### Caracteristici

- **Aspect ratio:** 3:2 (standard pentru cards)
- **Dimensiuni:** 640×427 pixels
- **Fit mode:** Crop (păstrează aspect ratio, croppează excess)
- **Quality:** 85% (sweet spot între dimensiune și calitate)
- **Format:** JPG (primary) + WebP (modern browsers)
- **Progressive:** Da (încărcare progresivă pentru UX)

### Use Cases

✅ **Potrivit pentru:**
- Article cards în listări
- Thumbnails în căutare
- Featured images în homepage
- Related articles widgets
- Mobile displays (Retina-ready)

❌ **Nu este potrivit pentru:**
- Full-width hero images (prea mic)
- Zoom/lightbox (rezoluție limitată)
- Print quality (DPI insuficient)

**Recomandare:** Profil unic 640×427 este **suficient pentru MVP** (arhivă de știri cu focus pe listări și cards).

---

## 🔍 Comparație Opțiuni Import

| Opțiune | Imagini | Spațiu | Timp | Complexitate | Recomandare |
|---------|---------|--------|------|--------------|-------------|
| **1. Toate imaginile** | 165,302 | 88 GB | ~9h | Medie | ❌ |
| **2. Metadata doar** | 0 fizice | ~50 MB | 30min | Mică | ❌ |
| **3. Folosite (originale)** | 155,332 | 80.7 GB | ~8h | Medie | ❌ |
| **4. Folosite (640×427)** | **155,332** | **12.77 GB** | **8.6h** | Medie | ✅ ⭐ |

**Decizie finală:** **Opțiunea 4** - Import doar imagini folosite, resize la 640×427 + optimizare

**Motivație:**
1. **Coverage excelent:** 93.96% din imagini (toate cele folosite)
2. **Spațiu optim:** 12.77 GB vs 88 GB (84% economie)
3. **Performance:** ~86 KB/imagine = încărcare rapidă
4. **Quality suficient:** 640×427 la quality 85 = vizibil bun pe web
5. **Consistență:** Doar imagini pentru articole Published='Y'

---

## 📋 Plan Implementare

### 1. **Query Principal Import**

```sql
-- Imagini folosite în articole Published='Y'
SELECT DISTINCT
    i.Id,
    i.ImageFileName,
    i.Location,
    i.Description,
    i.width,
    i.height,
    i.ContentType,
    i.Photographer,
    i.TimeCreated
FROM Images i
INNER JOIN ArticleImages ai ON i.Id = ai.IdImage
INNER JOIN Articles a ON ai.NrArticle = a.Number
WHERE a.Type = 'stiri'
  AND a.Published = 'Y'
ORDER BY i.Id;
```

**Output:** 155,332 imagini de importat

### 2. **Procesare per Imagine**

```php
// În ImportImagesCommand.php

foreach ($images as $imageData) {
    // 1. Construire path original
    $originalPath = '/home/radu/ext-hdd/alpha/' . $imageData['Location'] . '/' . $imageData['ImageFileName'];

    if (!file_exists($originalPath)) {
        $this->logger->warning("Image file not found: {$originalPath}");
        continue;
    }

    // 2. Upload prin ImageService (cu resize automat)
    $uploadedFile = new UploadedFile(
        $originalPath,
        $imageData['ImageFileName'],
        $imageData['ContentType'],
        null,
        true  // test mode
    );

    // 3. ImageService procesează automat:
    //    - Calculează SHA256 hash (deduplication)
    //    - Extrage metadata (width, height, dominant color, blur)
    //    - Salvează original
    //    - Creează Image entity
    //    - Dispatch GenerateImageThumbnailsMessage (async)
    $image = $this->imageService->uploadImage(
        $uploadedFile,
        $imageData['Description'] ?? null,  // alt text
        $imageData['Photographer'] ?? null
    );

    // 4. Log mapping Newscoop ID → news_app ID
    $this->logMapping('image', $imageData['Id'], $image->getId());

    // 5. Async: GenerateImageThumbnailsMessage va crea:
    //    - article_card (640×427) în JPG și WebP
    //    Doar acest profil (nu article_hero, article_wide, etc.)
}
```

### 3. **Async Thumbnail Generation**

```php
// În GenerateImageThumbnailsMessageHandler.php

public function __invoke(GenerateImageThumbnailsMessage $message): void
{
    $image = $this->imageRepository->find($message->getImageId());

    // ✅ DOAR profil article_card pentru MVP
    $profiles = ['article_card'];  // Nu toate profilurile!

    foreach ($profiles as $profile) {
        $profileConfig = $this->imageConfig['profiles'][$profile];

        foreach (['jpg', 'webp'] as $format) {
            // Resize la 640×427 + crop + optimize quality 85
            $thumbnail = $this->imageService->generateThumbnail(
                $image,
                $profile,
                $format,
                $profileConfig
            );

            // Persist Thumbnail entity
            $this->em->persist($thumbnail);
        }
    }

    $this->em->flush();
}
```

### 4. **Comandă Import**

```bash
php bin/console app:newscoop:import:images \
    --published-only \     # Doar pentru articole Published='Y'
    --profile=article_card \  # Doar profil 640×427
    --batch-size=100 \
    --async-thumbnails \   # Generare thumbnails în background
    [--dry-run] \
    [--limit=1000]         # Pentru test
```

**Output așteptat:**
```
Image Import Progress:
[████████████████████████████████] 155,332/155,332 (100%)

Summary:
✓ Total images processed: 155,332
✓ Successfully imported: 154,800 (99.7%)
✓ Deduplicated (SHA256): 532 (0.3%)
✓ Thumbnails queued (async): 154,800 × 2 formats = 309,600
✓ Disk space used: 12.77 GB
✓ Avg size per image: 86 KB
⚠ Missing files: 500 (0.3%)
✗ Failed: 32 (0.02%)

Async Thumbnail Generation:
⏳ Queued: 309,600 thumbnails (article_card JPG + WebP)
⏱️  Estimated completion: 2-3 hours
```

---

## 🔗 Mapare Relații Article-Image

### Proces Import Relații

După import imagini, mapare relații în `ImportArticlesCommand`:

```php
// În ImportArticlesCommand.php - după crearea Article entity

private function importArticleImages(int $newscoopArticleNumber, Article $article): void
{
    // Query ArticleImages pentru acest articol
    $articleImages = $this->newscoopConnection->executeQuery(
        "SELECT IdImage, is_default
         FROM ArticleImages
         WHERE NrArticle = ?
         ORDER BY is_default DESC, IdImage ASC",
        [$newscoopArticleNumber]
    )->fetchAllAssociative();

    foreach ($articleImages as $ai) {
        // Map Newscoop image ID → news_app ID
        $newsAppImageId = $this->getMappedId('image', $ai['IdImage']);

        if (!$newsAppImageId) {
            $this->logger->warning("Image not found for mapping", [
                'article' => $newscoopArticleNumber,
                'newscoop_image_id' => $ai['IdImage']
            ]);
            continue;
        }

        $imageEntity = $this->imageRepository->find($newsAppImageId);

        if ($imageEntity) {
            // Add to images collection
            $article->addImage($imageEntity);

            // Set featured image (is_default = 1 în Newscoop)
            if ($ai['is_default'] == 1) {
                $article->setFeaturedImage($imageEntity);
            }
        }
    }
}

// Apelare în main import loop:
$this->importArticleImages($articleNumber, $article);
```

**Note:**
- Prima imagine cu `is_default=1` devine featured image
- Toate imaginile sunt adăugate în collection `$article->images`
- Relații Many-to-Many (un imagine poate fi în multiple articole)

---

## ✅ Verificări Post-Import

### 1. Verificare Integritate

```bash
php bin/console app:newscoop:post-validate --images-only
```

**Checks:**
```
✓ Total images imported: 155,332
✓ Images with thumbnails: 154,800 (99.7%)
✓ Featured images set: 152,100 (99.8% articles)
✓ Article-image relationships: 223,780
✓ Orphaned images: 0
✓ Missing thumbnails: 532 (queued for retry)
✗ Failed thumbnails: 32 (< 0.1%)
```

### 2. Verificare Dimensiune Disk

```bash
# Check disk usage pentru originals + thumbnails
du -sh public/uploads/images/originals/
du -sh public/uploads/images/thumbnails/

# Expected output:
# originals/: ~12.77 GB (155,332 × ~86 KB avg)
# thumbnails/: ~12.77 GB (154,800 × 2 formats × ~86 KB avg)
# TOTAL: ~25.54 GB (vs 80.7 GB original = 68% reducere)
```

### 3. Verificare Sample Visual

```sql
-- Select random images pentru verificare vizuală
SELECT id, hash, width, height, dominant_color
FROM image
ORDER BY RANDOM()
LIMIT 20;

-- Pentru fiecare, check thumbnail URL:
-- /uploads/images/thumbnails/{hash_prefix}/article_card.jpg
-- /uploads/images/thumbnails/{hash_prefix}/article_card.webp
```

---

## 📊 Impact asupra Planului de Migrare

### Modificări Estimări

| Metric | Estimare Inițială | **Estimare Reală** | Diferență |
|--------|-------------------|--------------------|-----------|
| **Imagini de importat** | 165,302 (toate) | **155,332 (folosite)** | -9,970 (-6%) |
| **Spațiu disk necesar** | 88 GB (originale) | **12.77 GB (640×427)** | **-75.23 GB (-85.5%)** ✅ |
| **Timp import** | 9-10h (toate) | **8.6h (folosite + resize)** | -0.4h (-4%) |
| **Thumbnail profiles** | 6 profiles | **1 profile (article_card)** | -83% procesare |

### Update Volum Date (MIGRATION_SUMMARY.md)

| Entitate | Total Newscoop | **Import (Optimizat)** ✅ | Detalii |
|----------|----------------|---------------------------|---------|
| **Images (total)** | 165,302 | - | - |
| **Images (folosite)** | 155,332 (93.96%) | **155,332** | Doar în articole Published='Y' |
| **Spațiu disk original** | 88 GB | - | - |
| **Spațiu disk optimizat** | - | **12.77 GB** | Resize 640×427 + quality 85 |
| **Economie spațiu** | - | **75.23 GB (85.5%)** | - |
| **Thumbnail profile** | - | **article_card (640×427)** | JPG + WebP |

---

## 🎯 Recomandări Finale

### Pentru MVP (Arhivă Știri):

✅ **Decizie confirmată:**
- Import **doar imagini folosite** în articole Published='Y'
- Resize la **640×427 (article_card)**
- Optimizare **JPEG quality 85%**
- Format: **JPG + WebP**
- Generare **async thumbnails** (background)

✅ **Beneficii:**
1. **Coverage excelent:** 93.96% din imagini (toate cele relevante)
2. **Spațiu minim:** 12.77 GB vs 88 GB (85.5% economie) 💾
3. **Performance web:** ~86 KB/imagine = încărcare rapidă
4. **Quality suficient:** 640×427 @ quality 85 = vizual bun
5. **Timp rezonabil:** 8.6 ore (rulare background)

✅ **Trade-offs acceptate:**
- ❌ Nu vom avea rezoluții înalte pentru zoom/print
- ❌ Nu vom avea profile multiple (hero, wide, etc.)
- ✅ Suficient pentru listări, cards, thumbnails
- ✅ Putem regenera profile suplimentare mai târziu dacă necesar

### Extensii Viitoare (Post-MVP):

Dacă apare nevoia, putem:
1. **Regenera thumbnails** cu profile suplimentare:
   ```bash
   php bin/console app:image:regenerate-thumbnails \
       --profile=article_hero \
       --profile=article_wide
   ```

2. **Import imagini high-res** pentru articole selectate:
   ```bash
   php bin/console app:newscoop:import:images \
       --high-res \
       --article-ids=123,456,789
   ```

3. **Optimizare ulterioară** cu WebP/AVIF pentru economie suplimentară

---

## 📝 Concluzie

**Decizie finală:** Import **155,332 imagini** folosite în articole Published='Y', resize la **640×427**, optimizare **quality 85%**

**Rezultat:**
- ✅ **12.77 GB** spațiu disk (vs 88 GB original)
- ✅ **~86 KB** dimensiune medie/imagine
- ✅ **8.6 ore** timp import (background)
- ✅ **93.96%** coverage imagini relevante
- ✅ **99.8%** articole cu imagini

**Impact plan migrare:**
- **-75.23 GB** economie spațiu disk (85.5%)
- **Faza 4 (Imagini):** 8.6 ore background (vs 9-10h inițial)
- **Timp total:** Fără impact (rulare paralelă cu Faza 5)

**Status:** ✅ **Analiză completă, decizie confirmată, gata pentru implementare!**

---

**Data completare analiză:** 2025-10-26
**Decizie luată de:** Radu (utilizator)
**Implementare:** Pregătită pentru Faza 4 (Import Imagini)
