# Analiza Procesării Conținutului: Newscoop ArticleData

**Data:** 2025-10-25
**Critica pentru import:** ⚠️ **FOARTE IMPORTANT**

---

## 🔍 Descoperirea Problemei

Newscoop folosește clasa `ArticleData` pentru a **transforma** conținutul articolelor între două formate:

1. **Format Editor (HTML standard)** - în interfața CMS
2. **Format Stocare (Shortcodes proprietare)** - în baza de date

**Problema:** În `Xstiri.FContinut` (BLOB), conținutul este stocat cu **shortcodes Newscoop**, nu HTML pur!

---

## 📋 Shortcodes Identificate

### 1. **Image Shortcode** ⭐ CEL MAI IMPORTANT

**Statistici:**
- **16,182 articole** conțin `<!** Image` shortcode (9.2% din total)
- **1,106 articole** conțin `<img>` tag HTML standard

**Format Shortcode:**
```
<!** Image [template_id] align="[left|right|center]" alt="[text]" sub="[caption]" width="[px]" height="[px]" ratio="[id_ratio]">
```

**Exemple reale:**
```html
<!** Image 12345 align="left" alt="Premierul Moldovei" sub="Foto: gov.md" width="640" height="480">
```

**Ce face Newscoop la afișare:**
```php
// ArticleData transformă shortcode → HTML
<!** Image 12345 align="left" alt="..." sub="..." width="640">
↓
<img id="12345" src="/images/cms-image-000012345.jpg" alt="..." title="..." align="left" width="640" height="480">
```

**Mapare în import:**
- `template_id` = Newscoop Image ID → trebuie mapat la news_app Image ID
- `align` = păstrare directă
- `alt` = păstrare directă
- `sub` (subtitle/caption) = mapare la `<figcaption>` HTML5
- `width`, `height` = păstrare sau responsive conversion

---

### 2. **Internal Link Shortcode**

**Statistici:**
- **3 articole** conțin `<!** Link Internal` (foarte rare)

**Format Shortcode:**
```
<!** Link Internal IdPublication=1&NrIssue=1&NrSection=2&NrArticle=123 TARGET _blank>Text link<!** EndLink>
```

**Ce face Newscoop la afișare:**
```php
// Transformă în URL intern
<!** Link Internal IdPublication=1&NrArticle=123>Citește mai mult<!** EndLink>
↓
<a href="/ro/news/politic/123/titlu-articol">Citește mai mult</a>
```

**Mapare în import:**
- `NrArticle=123` → lookup în `newscoop_id_mapping` → găsește news_app Article ID
- Generare URL: `/articles/{slug}` sau `/api/articles/{id}`
- `TARGET` → păstrare ca `target="_blank"`

---

### 3. **Subheading Shortcode**

**Format Shortcode:**
```
<!** Title>Subtitlu important<!** EndTitle>
```

**HTML original (în editor):**
```html
<span class="campsite_subhead">Subtitlu important</span>
```

**Ce face Newscoop la afișare:**
```php
<!** Title>Text<!** EndTitle>
↓
<h3 class="article-subhead">Text</h3>
// Sau alt stil definit în template
```

**Mapare în import:**
- Convertire la `<h3>` sau `<h4>` standard HTML5
- Sau păstrare `<span class="subhead">` cu CSS news_app

---

### 4. **Snippet Shortcode**

**Format Shortcode:**
```
<!-- Snippet [id] -->
```

**HTML original:**
```html
<div class="camp_snippet" data-snippet-id="123">Snippet 123</div>
```

**Ce face:** Include conținut reutilizabil (embed-uri, widget-uri)

**Mapare în import:**
- Verificare dacă snippet-ul există în Newscoop
- Poate fi ignorat (conținut legacy) sau convertit la embed modern

---

## 🎯 Strategii de Procesare la Import

### Opțiunea 1: PARSE & TRANSFORM (Recomandată) ⭐

**Descriere:** Procesează shortcodes și transformă în HTML modern

**Implementare:**
```php
class NewscoopContentProcessor
{
    /**
     * Process Newscoop shortcodes în content
     */
    public function processContent(string $content, int $newscoopArticleNumber): string
    {
        // 1. Transform Image shortcodes
        $content = $this->processImageShortcodes($content, $newscoopArticleNumber);

        // 2. Transform Internal Link shortcodes
        $content = $this->processInternalLinks($content);

        // 3. Transform Title shortcodes
        $content = $this->processSubheadings($content);

        // 4. Remove/ignore Snippet shortcodes
        $content = $this->removeSnippets($content);

        // 5. Clean HTML
        $content = $this->cleanHtml($content);

        return $content;
    }

    /**
     * Transform: <!** Image [id] ...> → <figure><img><figcaption>
     */
    private function processImageShortcodes(string $content, int $articleNumber): string
    {
        $pattern = '/<!\\*\\* Image (\d+)(?: align="([^"]*)")?(?: alt="([^"]*)")?(?: sub="([^"]*)")?(?: width="([^"]*)")?(?: height="([^"]*)")?>/';

        return preg_replace_callback($pattern, function ($matches) {
            [, $newscoopImageId, $align, $alt, $caption, $width, $height] = array_pad($matches, 7, null);

            // Map Newscoop Image ID → news_app Image ID
            $newsAppImageId = $this->getMappedId('image', $newscoopImageId);
            if (!$newsAppImageId) {
                $this->logger->warning("Image not found", ['newscoop_id' => $newscoopImageId]);
                return ''; // Remove shortcode dacă imaginea lipsește
            }

            // Get Image entity pentru URL
            $image = $this->imageRepo->find($newsAppImageId);
            if (!$image) {
                return '';
            }

            // Generate modern HTML5 figure
            $html = '<figure';
            if ($align) {
                $html .= ' class="align-' . htmlspecialchars($align) . '"';
            }
            $html .= '>';

            // Responsive image cu srcset
            $html .= sprintf(
                '<img src="%s" alt="%s" loading="lazy" width="%s" height="%s">',
                $this->imageService->getThumbnailUrl($image, 'article_wide', 'webp'),
                htmlspecialchars($alt ?? ''),
                $width ?? $image->getWidth(),
                $height ?? $image->getHeight()
            );

            // Caption (subtitle)
            if ($caption) {
                $html .= '<figcaption>' . htmlspecialchars($caption) . '</figcaption>';
            }

            $html .= '</figure>';

            return $html;
        }, $content);
    }

    /**
     * Transform: <!** Link Internal ...>text<!** EndLink> → <a href="/articles/...">
     */
    private function processInternalLinks(string $content): string
    {
        $pattern = '/<!\\*\\* Link Internal ([^>]+)(?:TARGET ([^>]+))?>(.*?)<!\\*\\* EndLink>/s';

        return preg_replace_callback($pattern, function ($matches) {
            [, $queryString, $target, $linkText] = array_pad($matches, 4, null);

            // Parse query string: "NrArticle=123&IdLanguage=2"
            parse_str($queryString, $params);

            if (!isset($params['NrArticle'])) {
                $this->logger->warning("Invalid internal link", ['query' => $queryString]);
                return $linkText; // Returnează doar text fără link
            }

            // Map Newscoop Article Number → news_app Article ID
            $newsAppArticleId = $this->getMappedId('article', $params['NrArticle']);
            if (!$newsAppArticleId) {
                $this->logger->warning("Linked article not found", ['newscoop_number' => $params['NrArticle']]);
                return $linkText;
            }

            // Get Article pentru slug
            $article = $this->articleRepo->find($newsAppArticleId);
            if (!$article) {
                return $linkText;
            }

            // Generate URL
            $url = '/articles/' . $article->getSlug();

            // Build <a> tag
            $html = sprintf(
                '<a href="%s"%s>%s</a>',
                htmlspecialchars($url),
                $target ? ' target="' . htmlspecialchars($target) . '"' : '',
                $linkText
            );

            return $html;
        }, $content);
    }

    /**
     * Transform: <!** Title>text<!** EndTitle> → <h3>text</h3>
     */
    private function processSubheadings(string $content): string
    {
        return preg_replace(
            '/<!\\*\\* Title>(.*?)<!\\*\\* EndTitle>/s',
            '<h3 class="article-subheading">$1</h3>',
            $content
        );
    }

    /**
     * Remove snippet shortcodes (optional: could implement snippet import)
     */
    private function removeSnippets(string $content): string
    {
        return preg_replace('/<!-- Snippet \d+ -->/', '', $content);
    }

    /**
     * Clean and sanitize HTML
     */
    private function cleanHtml(string $content): string
    {
        // Optional: Use HTMLPurifier sau DOMDocument pentru clean-up
        // - Remove empty tags
        // - Fix malformed HTML
        // - Sanitize dangerous attributes
        return $content;
    }
}
```

**Pro:**
- ✅ Conținut 100% compatibil cu news_app
- ✅ HTML modern (figure, figcaption, responsive images)
- ✅ Link-uri interne funcționale către news_app articles
- ✅ SEO-friendly, accessibility-friendly

**Contra:**
- Complexitate medie (regex parsing)
- Timp procesare: +5-10% în Faza 5

---

### Opțiunea 2: STORE AS-IS + RENDER FILTER (Nu recomandat)

**Descriere:** Stochează content cu shortcodes, procesează la afișare

**Implementare:**
```php
// La import: stochează direct FContinut → Article.content (cu shortcodes)

// La afișare (Twig filter sau API serializer):
{{ article.content|newscoop_shortcodes }}
```

**Pro:**
- Import foarte rapid (zero procesare)
- Păstrează "original" Newscoop

**Contra:**
- ❌ Conținut "mort" - shortcodes inutile în news_app
- ❌ Dependență pe logică Newscoop legacy
- ❌ Imposibil de editat în news_app CMS
- ❌ Search engines văd shortcodes, nu conținut real

---

### Opțiunea 3: MANUAL CLEANUP (Doar pentru teste)

**Descriere:** Import fără procesare, curățare manuală later

**Pro:**
- Import ultra-rapid

**Contra:**
- ❌ 16,182 articole cu imagini broken
- ❌ Nu e viabil pentru producție

---

## 🎯 Recomandare Finală

### **OPȚIUNEA 1: Parse & Transform** ⭐⭐⭐⭐⭐

**Motivație:**
1. **16,182 articole** (9.2%) au imagini în shortcodes → TREBUIE procesate
2. Link-uri interne (3 articole) - puține, dar importante pentru navigare
3. Conținut final = HTML curat, modern, editable în news_app
4. Overhead timp: ~10% (2-3 ore → 2.5-3.5 ore pentru Faza 5)

**Impact:**
- **Critical:** Fără procesare, 16k+ articole vor avea `<!** Image 123>` în loc de imagini
- **SEO:** HTML curat e indexabil de Google
- **UX:** Conținut afișabil corect în frontend
- **Maintenance:** Nu depindem de legacy Newscoop logic

---

## 📋 Plan Implementare

### Pas 1: Creare NewscoopContentProcessor Service
```bash
backend/src/Service/Newscoop/NewscoopContentProcessor.php
```

**Metode:**
- `processContent(string $content, int $newscoopArticleNumber): string`
- `processImageShortcodes()`
- `processInternalLinks()`
- `processSubheadings()`
- `removeSnippets()`
- `cleanHtml()`

### Pas 2: Integrare în ImportArticlesCommand
```php
// În bucla de import articole
$rawContent = stream_get_contents($defaultLang['FContinut']);

// IMPORTANT: Process shortcodes
$processedContent = $this->contentProcessor->processContent(
    $rawContent,
    $number  // Newscoop article number pentru logging
);

$article->setContent($processedContent);
```

### Pas 3: Testing pe Sample
```bash
# Test pe 100 articole cu imagini
php bin/console app:newscoop:import:articles \
    --dry-run \
    --limit=100 \
    --filter="has_images"
```

**Verificări:**
- [ ] Shortcodes `<!** Image>` transformate în `<figure><img>`
- [ ] Image IDs mapate corect (Newscoop → news_app)
- [ ] Caption-uri (sub) transformate în `<figcaption>`
- [ ] Link-uri interne funcționale
- [ ] HTML valid (no broken tags)

### Pas 4: Logging & Error Handling
```php
// Log statistici procesare
$stats = [
    'images_processed' => 0,
    'images_not_found' => 0,
    'internal_links_processed' => 0,
    'internal_links_broken' => 0,
    'subheadings_converted' => 0,
];

// În fiecare callback regex:
if (!$newsAppImageId) {
    $stats['images_not_found']++;
    $this->logger->warning("Image not found in mapping", [
        'newscoop_image_id' => $newscoopImageId,
        'article_number' => $articleNumber,
    ]);
} else {
    $stats['images_processed']++;
}
```

**Output după import:**
```
Content Processing Statistics:
  ✓ Images processed: 16,045
  ⚠ Images not found (skipped): 137
  ✓ Internal links processed: 3
  ⚠ Internal links broken (removed): 0
  ✓ Subheadings converted: 1,234
  ✓ Snippets removed: 45
```

---

## 🔧 Regex Patterns (Reference)

### Image Shortcode Pattern
```regex
/<!\\*\\* Image (\d+)(?: align="([^"]*)")?(?: alt="([^"]*)")?(?: sub="([^"]*)")?(?: width="([^"]*)")?(?: height="([^"]*)")?>/
```

**Capture Groups:**
1. `\d+` - Image ID (Newscoop)
2. `align="(...)"` - left/right/center (optional)
3. `alt="(...)"` - Alt text (optional)
4. `sub="(...)"` - Caption/subtitle (optional)
5. `width="(...)"` - Width px (optional)
6. `height="(...)"` - Height px (optional)

### Internal Link Pattern
```regex
/<!\\*\\* Link Internal ([^>]+)(?:TARGET ([^>]+))?>(.*?)<!\\*\\* EndLink>/s
```

**Capture Groups:**
1. `([^>]+)` - Query string (IdPublication=1&NrArticle=123)
2. `([^>]+)` - Target (_blank, _self) (optional)
3. `(.*?)` - Link text (non-greedy)

### Subheading Pattern
```regex
/<!\\*\\* Title>(.*?)<!\\*\\* EndTitle>/s
```

**Capture Groups:**
1. `(.*?)` - Subheading text (non-greedy, multiline)

---

## 📊 Statistici Impact

| Procesare | Articole Afectate | % Total | Critica |
|-----------|------------------|---------|---------|
| **Image shortcodes** | 16,182 | 9.2% | ⚠️ **CRITICA** |
| **IMG tags HTML** | 1,106 | 0.6% | ✅ OK (păstrare) |
| **Internal links** | 3 | 0.002% | ⚙️ Nice to have |
| **Subheadings** | ? | ? | ⚙️ Nice to have |
| **Snippets** | ? | ? | ⚙️ Opțional |

**Concluzie:** **16,182 articole** (9.2%) nu vor avea imagini dacă nu procesăm shortcodes!

---

## ⚠️ Riscuri & Mitigare

### Risc 1: Image ID Mapping Lipsă
**Problemă:** Newscoop Image ID nu există în `newscoop_id_mapping`
**Cauză:** Imaginea nu a fost importată (fișier lipsă, eroare procesare)
**Mitigare:**
- Log warning cu detalii (article number, image ID)
- Skip shortcode (returnează string gol)
- Raport final cu lista imagini lipsă pentru review manual

### Risc 2: Malformed Shortcodes
**Problemă:** Shortcode incomplet sau corrupt în DB
**Exemplu:** `<!** Image 123 align="left"` (lipsește `>`)
**Mitigare:**
- Regex non-greedy pentru toleranță
- Try-catch în callback-uri
- Log malformed content pentru investigare

### Risc 3: Performance (16k+ Regex Operations)
**Problemă:** Procesare regex pe 175k articole, multe cu imagini multiple
**Mitigare:**
- Batch processing (flush la 50 articole)
- Compilare regex o singură dată (nu în loop)
- Progress bar pentru monitoring
- Estimate: +10-15% timp (2.5h → 2.8h)

---

## ✅ Checklist Implementare

- [ ] **Creare NewscoopContentProcessor service**
  - [ ] Method: `processContent()`
  - [ ] Method: `processImageShortcodes()`
  - [ ] Method: `processInternalLinks()`
  - [ ] Method: `processSubheadings()`
  - [ ] Method: `removeSnippets()`
  - [ ] Method: `cleanHtml()`

- [ ] **Testing Regex Patterns**
  - [ ] Test Image shortcode cu toate variațiile
  - [ ] Test Internal Link shortcode
  - [ ] Test Subheading shortcode
  - [ ] Test pe conținut real din Newscoop

- [ ] **Integrare în ImportArticlesCommand**
  - [ ] Inject NewscoopContentProcessor
  - [ ] Call `processContent()` înainte de `setContent()`
  - [ ] Logging statistici procesare

- [ ] **Error Handling & Logging**
  - [ ] Warning pentru imagini lipsă
  - [ ] Warning pentru link-uri broken
  - [ ] Statistici finale (processed/failed)

- [ ] **Testing pe Sample Data**
  - [ ] Import 100 articole cu --dry-run
  - [ ] Verificare HTML generat
  - [ ] Verificare imagini mapate corect
  - [ ] Verificare link-uri interne funcționale

- [ ] **Full Import Test**
  - [ ] Run pe DB clone/staging
  - [ ] Monitor performance (memory, time)
  - [ ] Review raport final statistici
  - [ ] Manual check sample articles în news_app

---

## 🎯 Concluzie

**Procesarea conținutului e OBLIGATORIE pentru un import de succes!**

**Fără procesare:**
- ❌ 16,182 articole (9.2%) vor avea imagini broken
- ❌ Conținut va conține shortcodes inutile în news_app
- ❌ Imposibil de editat în CMS
- ❌ SEO impact negativ

**Cu procesare (Opțiunea 1):**
- ✅ Conținut HTML modern, curat
- ✅ Imagini funcționale cu responsive layout
- ✅ Link-uri interne către articles migrated
- ✅ Editabil în news_app CMS
- ✅ SEO-friendly, accessibility-friendly

**Overhead:** +10-15% timp import (2.5h → 2.8h) - **ACCEPTABIL** pentru beneficii!

---

**Recomandare:** ⭐ **IMPLEMENTEAZĂ Opțiunea 1 (Parse & Transform)** înainte de Faza 5!
