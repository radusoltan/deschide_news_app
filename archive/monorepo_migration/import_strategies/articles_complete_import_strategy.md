# 📋 Strategie IMPORT COMPLET Articole - Toate Sursele

## 🎯 Obiectiv

Import COMPLET al articolelor din toate cele 3 surse de date (Newscoop, Beta, Webflow), cu:
- Păstrarea integrității datelor istorice
- Deduplicare inteligentă între surse
- Mapare relații complete (categorii, autori, imagini)
- Traduceri multilingve (ro, en, ru)
- Metadata completă (SEO, featured, badges)
- Import imagini optimizat

---

## 📊 ANALIZA SURSELOR DE DATE

### Sursa 1: Newscoop CMS (MySQL) - ARHIVĂ PRINCIPALĂ ⭐

**Caracteristici:**
- **Bază de date**: MySQL/MariaDB (legacy CMS Newscoop 4.4)
- **Perioada**: 2016-2024 (9 ani de arhivă completă)
- **Volume reale** (Type='stiri' + Published='Y'):
  - **152,523 articole unice**
  - **173,670 total înregistrări** (cu traduceri)
  - **21,147 traduceri** (13.4% articole au 2-3 limbi)
- **Limbi**: RO (83.3%), RU (16%), EN (0.66%)
- **Status**: Published='Y' (doar articole publicate)

**Structură date Newscoop:**

| Tabela | Câmpuri principale | Descriere |
|--------|-------------------|-----------|
| **Articles** | Number, IdLanguage, Published, PublishDate, OnFrontPage, Keywords, NrSection | Metadata articol |
| **Xstiri** | FTitlu, Fsubtitlu, Flead, FContinut, FBREAKING_NEWS, FNEWS_ALERT, FFLASH | Conținut custom |
| **Images** | Id, ImageFileName, Location, ContentType, width, height | Imagini originale |
| **ArticleImages** | NrArticle, IdImage, is_default | Relații articol-imagine |
| **ArticleAuthors** | fk_article_no, fk_author_id | Relații articol-autor |
| **Sections** | Number, Name, IdLanguage | Categorii (sections) |

**Distribuție articole pe limbi** (Published='Y'):

| Limbă | Total Articole | Articole Unice | Procent |
|-------|----------------|----------------|---------|
| 🇷🇴 **Română** | **144,659** | 144,659 | **83.30%** |
| 🇷🇺 **Rusă** | **27,865** | 27,865 | 16.04% |
| 🇬🇧 **Engleză** | **1,146** | 1,146 | 0.66% |
| **TOTAL** | **173,670** | **173,670** | **100%** |

**Distribuție traduceri** (câte limbi are fiecare articol):

| Număr Limbi | Articole Unice | Procent | Descriere |
|-------------|----------------|---------|-----------|
| **1 limbă** | **132,059** | **86.58%** | Monolingve (doar o limbă) |
| **2 limbi** | **19,781** | 12.97% | Bilingve (ro+ru cel mai probabil) |
| **3 limbi** | **683** | 0.45% | Trilingve (ro+ru+en) |
| **TOTAL** | **152,523** | **100%** | - |

**Distribuție temporală** (articole publicate pe ani):

| An | Total Articole | Articole Unice | Medie/Lună |
|----|----------------|----------------|------------|
| 2016 | 6,803 | 4,864 | ~405 |
| 2017 | 22,534 | 17,499 | ~1,458 |
| 2018 | 21,608 | 17,302 | ~1,442 |
| 2019 | 19,073 | 16,377 | ~1,365 |
| 2020 | 19,527 | 18,267 | ~1,522 |
| 2021 | 21,028 | 19,748 | ~1,646 |
| **2022** | **24,105** | **22,567** | **~1,880** 🔥 |
| 2023 | 22,372 | 20,714 | ~1,726 |
| 2024 | 16,620 | 15,186 | ~1,266 (până în oct) |
| **TOTAL** | **173,670** | **152,523** | ~1,412/lună |

**Tipuri articole identificate** (by flags/badges):

| Tip | Camp Newscoop | Volume estimate | Prioritate |
|-----|---------------|-----------------|------------|
| **Știri standard** | (none) | ~138,000 | 🔴 ESENȚIAL |
| **Breaking News** | FBREAKING_NEWS=1 | ~500 | 🔴 ESENȚIAL |
| **News Alert** | FNEWS_ALERT=1 | ~300 | 🟠 IMPORTANT |
| **Flash** | FFLASH=1 | ~200 | 🟠 IMPORTANT |
| **Featured** | OnFrontPage='Y' | ~10,000 | 🔴 ESENȚIAL |
| **Cu shortcodes** | FContinut LIKE '%<!**%' | **13,323** | ⚠️ **PROCESARE OBLIGATORIE** |

**Relații disponibile:**
- ✅ Categorii: NrSection → Sections (43 rows → 18 sections unice)
- ✅ Autori: ArticleAuthors junction table (282 autori)
- ✅ Imagini: ArticleImages + Images (155,332 imagini folosite)
- ✅ Traduceri: IdLanguage (2=ro, 15=ru, 1=en) + same Number

**Probleme cunoscute:**
- ⚠️ **13,323 articole cu shortcodes** `<!** Image [id] ...>` - NECESITĂ PROCESARE
- ⚠️ HTML legacy (tag-uri deprecated, inline styles)
- ⚠️ Imagini cu path-uri absolute vechi
- ⚠️ Keywords în format CSV (necesită split în array)
- ⚠️ Unele articole fără content (FContinut NULL) - SKIP import

---

### Sursa 2: Beta Site (beta.deschide.md) - CONȚINUT RECENT

**Caracteristici:**
- **Platformă**: WordPress/Custom CMS (verificare necesară)
- **Perioada**: 2020-2024 (overlap masiv cu Newscoop)
- **Volume estimate**: 5,000-10,000 articole
- **Limbi**: RO, RU, EN
- **Status**: Mostly published

**Overlap cu Newscoop**: ~70-80% (aceleași articole republicate)

**Tipuri articole identificate:**

| Tip | Volume estimate | Prioritate | Observații |
|-----|-----------------|------------|------------|
| **Știri recente** | ~6,000 | 🔴 ESENȚIAL | Overlap Newscoop 2020-2024 |
| **Bloguri** | ~500 | 🟡 MEDIU | Categorie "Bloguri" |
| **Social Media** | ~300 | 🟢 OPȚIONAL | Posts din social |
| **No Comment** | ~200 | 🟡 MEDIU | Rubrica specială |
| **Live Text** | ~100 | 🟠 IMPORTANT | Reportaje live |
| **Sondaje** | ~50 | 🟢 OPȚIONAL | Articole cu polls |

**Caracteristici specifice:**
- ✅ Metadata SEO mai bună (metaTitle, metaDescription)
- ✅ Imagini optimizate (thumbnails generate)
- ✅ URLs moderne (slugs clean)
- ⚠️ **OVERLAP MARE cu Newscoop** (~70-80% duplicate)
- ⚠️ Format embeduri diferit (YouTube, Twitter, etc.)
- ❓ Sistem traduceri (de verificat)

**Relații:**
- ✅ Categorii (sistem propriu WordPress?)
- ✅ Autori
- ✅ Featured images
- ❓ Traduceri (de investigat)

---

### Sursa 3: Webflow (deschide.md) - SITE LIVE ACTUAL

**Caracteristici:**
- **Platformă**: Webflow CMS
- **Perioada**: 2022-2024 (ultimele 2-3 ani)
- **Volume estimate**: 2,000-3,000 articole
- **Limbi**: RO, EN, RU (Webflow locales)
- **Status**: Live production

**Overlap**: ~90% cu Newscoop/Beta (aceleași articole migrate)

**Tipuri articole:**

| Tip | Volume estimate | Prioritate | Observații |
|-----|-----------------|------------|------------|
| **Știri live** | ~2,000 | 🔴 ESENȚIAL | Conținut actual |
| **Dialog deschis** | ~200 | 🟠 IMPORTANT | Interviuri/dezbateri |
| **Anti-Fake** | ~100 | 🟠 IMPORTANT | Fact-checking |
| **Advertorial** | ~50 | 🟡 MEDIU | Conținut sponsorizat |
| **Alegeri** | ~100 | 🟠 IMPORTANT | Coverage electoral |

**Caracteristici specifice:**
- ✅ Clean HTML/Rich text (Webflow editor)
- ✅ Imagini CDN optimizate (Webflow CDN)
- ✅ SEO metadata completă
- ✅ Responsive images (srcset automat)
- ✅ Traduceri native (Webflow locales)
- ⚠️ **OVERLAP MAXIM** cu Newscoop/Beta

**Relații:**
- ✅ Categorii: 14 categorii (vezi categories_complete_import_strategy.md)
- ✅ Autori: sistem Webflow
- ✅ Featured images + galleries
- ✅ Traduceri: Webflow locales (ro, en, ru)

---

## 🔍 ANALIZA DEDUPLICARE - Problema Overlap

### Problema: Același Articol în 3 Surse

**Exemplu real:**
Un articol "Declarația președintelui..." publicat în martie 2023:

1. **Newscoop**: Number=145678, IdLanguage=2 (RO), PublishDate='2023-03-15'
2. **Beta**: post_id=9876, published='2023-03-15'
3. **Webflow**: item_id='abc123xyz', created='2023-03-15'

**Aceeași știre, 3 ID-uri diferite!**

### Criterii de Identificare Duplicat

**Matching criteria** (în ordine de încredere):

1. **Webcode match** (Newscoop → Webflow)
   - Newscoop Articles.webcode == Webflow slug
   - Acuratețe: 90%+ (dacă există)

2. **Slug normalizat**
   ```php
   function normalizeSlug(string $slug): string {
       $slug = mb_strtolower($slug);
       $slug = transliterate($slug); // ș→s, ț→t, etc.
       $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
       return trim($slug, '-');
   }
   ```

3. **Title normalizat + PublishDate** (±24h)
   ```php
   function normalizeTitle(string $title): string {
       $title = mb_strtolower($title);
       $title = preg_replace('/\s+/', ' ', $title); // whitespace
       $title = preg_replace('/[^\p{L}\p{N}\s]/u', '', $title); // punctuation
       return trim($title);
   }

   function isSameDate(DateTime $d1, DateTime $d2): bool {
       $diff = abs($d1->getTimestamp() - $d2->getTimestamp());
       return $diff <= 86400; // ±1 zi (24h)
   }
   ```

4. **Content similarity** (primele 200 caractere)
   ```php
   function contentSimilarity(string $c1, string $c2): float {
       $excerpt1 = substr(strip_tags($c1), 0, 200);
       $excerpt2 = substr(strip_tags($c2), 0, 200);
       similar_text($excerpt1, $excerpt2, $percent);
       return $percent; // 0-100%
   }

   // Threshold: 80%+ = duplicate
   ```

### Strategii Deduplicare

**Opțiunea A: Newscoop ca SURSĂ PRIMARĂ** ⭐ **RECOMANDAT**

```
Prioritate import:
1. Newscoop (152,523 articole) → import TOATE
2. Beta → import DOAR dacă NOT EXISTS în Newscoop (match by title+date)
3. Webflow → import DOAR dacă NOT EXISTS în Newscoop/Beta (match by slug)

Enrichment metadata:
- Pentru articole matched: UPDATE metadata din Beta/Webflow
  - metaTitle (dacă NULL în Newscoop)
  - metaDescription (dacă NULL)
  - ogImage (dacă calitate mai bună)
```

**Avantaje:**
- ✅ Arhiva COMPLETĂ (2016-2024, 9 ani)
- ✅ Volume maxime (152k articole vs 2-3k în Webflow)
- ✅ Relații stabilite (categorii, autori, imagini)
- ✅ ID-uri stabile (Newscoop Number)
- ✅ Traduceri complete (21k)

**Dezavantaje:**
- ⚠️ HTML legacy (necesită cleanup)
- ⚠️ Shortcodes (13k articole necesită procesare)
- ⚠️ Metadata SEO incompletă (fix cu enrichment)

**Estimare volume finale:**
- Newscoop: 152,523 articole
- Beta (delta): ~1,500-2,000 articole noi
- Webflow (delta): ~200-500 articole noi
- **TOTAL: ~154,000-155,000 articole unice**

---

**Opțiunea B: Webflow ca SURSĂ PRIMARĂ**

```
Prioritate import:
1. Webflow (2,500 articole) → import direct
2. Beta → import dacă NOT în Webflow
3. Newscoop → import doar arhiva PRE-2022
```

**Avantaje:**
- ✅ Clean HTML (Webflow editor)
- ✅ SEO metadata perfectă
- ✅ Imagini optimizate (CDN)

**Dezavantaje:**
- ❌ Volume mici (~2,500 vs 152,000)
- ❌ Pierde arhiva 2016-2022
- ❌ Site "gol" fără conținut istoric
- ❌ **NU RECOMANDAT pentru site de știri**

---

**Opțiunea C: MERGE INTELIGENT (Cea mai complexă)**

```
Pentru fiecare articol:
1. Găsește duplicatele în toate 3 sursele
2. Alege "best version":
   IF exists în Webflow → use Webflow metadata
   ELSE IF exists în Beta → use Beta metadata
   ELSE → use Newscoop
3. Merge content & metadata din toate sursele
   - Webflow: metaTitle, metaDescription, ogImage
   - Beta: keywords, tags
   - Newscoop: content, relații, traduceri
```

**Avantaje:**
- ✅ "Best of all worlds"
- ✅ Metadata optimă
- ✅ HTML curat unde posibil

**Dezavantaje:**
- ❌ Foarte complex (risc bugs)
- ❌ Timp mare de procesare
- ❌ Dificil de debug
- ❌ Risc inconsistențe

---

## 🎨 PROPUNERI DE STRATEGIE - Import Complet

### Opțiunea 1: ARHIVĂ COMPLETĂ (Newscoop + Delta + Enrichment) ⭐ **RECOMANDAT**

**Workflow în 5 faze:**

**FAZA 1: Import Newscoop RO (Limba Primară)**
- Query: `Type='stiri' AND Published='Y' AND IdLanguage=2`
- Volume: **144,659 articole** (RO)
- Include relații: categorii (NrSection), autori (ArticleAuthors), imagini (ArticleImages)
- Procesare: **13,323 articole cu shortcodes** (8.73%)
- Timp estimat: **2.5-3 ore**

**FAZA 2: Import Traduceri Newscoop (RU + EN)**
- RU: `IdLanguage=15` → **27,865 articole**
- EN: `IdLanguage=1` → **1,146 articole**
- Mapare: Same `Number` → link la articolul RO principal
- Sistem: Gedmo Translatable (ext_translations table)
- Timp estimat: **1-2 ore**

**FAZA 3: Import Delta Beta**
- Deduplicare: Match by (title_normalized + publishDate ±24h)
- Skip dacă EXISTS în Newscoop
- Import doar NOUTĂȚI: **~1,500-2,000 articole**
- Enrichment: UPDATE metadata pentru matched articles
- Timp estimat: **20-30 min**

**FAZA 4: Import Delta Webflow**
- Deduplicare: Match by (slug_normalized + publishDate)
- Skip dacă EXISTS în Newscoop/Beta
- Import doar NOUTĂȚI: **~200-500 articole**
- Enrichment: UPDATE metadata pentru matched articles
- Timp estimat: **10-15 min**

**FAZA 5: Metadata Enrichment**
- Pentru articole MATCHED (exists în multiple surse):
  ```sql
  UPDATE article SET
    meta_title = webflow.seo_title
    WHERE meta_title IS NULL AND webflow.seo_title IS NOT NULL;

  UPDATE article SET
    meta_description = webflow.seo_description
    WHERE meta_description IS NULL;
  ```
- Câmpuri enriched: metaTitle, metaDescription, keywords, ogImage
- Timp estimat: **30-60 min**

**Total volume estimate:**

| Sursă | Articole importate | Traduceri | Total intrări |
|-------|-------------------|-----------|---------------|
| Newscoop RO | 144,659 | - | 144,659 |
| Newscoop RU | - | 27,865 | 27,865 |
| Newscoop EN | - | 1,146 | 1,146 |
| Beta (delta) | 1,800 | ~500 | 2,300 |
| Webflow (delta) | 400 | ~100 | 500 |
| **TOTAL** | **~147,000** | **~29,600** | **~176,500 rows** |

**Note:**
- Articole unice: **~147,000**
- Total cu traduceri: **~176,500 database rows**
- Gedmo Translatable: articles + article_translations (2 tables)

**Avantaje:**
- ✅ Arhiva COMPLETĂ (2016-2024, 9 ani)
- ✅ Fără pierderi de date
- ✅ Relații păstrate (categorii, autori, imagini)
- ✅ Deduplicare automată
- ✅ Metadata îmbunătățită din surse multiple
- ✅ SEO optim (enrichment din Webflow/Beta)

**Dezavantaje:**
- ⚠️ Volume mari (timp import 4-6h)
- ⚠️ HTML legacy în articole 2016-2020
- ⚠️ Procesare shortcodes necesară (13k articole)

**Durata estimată:**
- Import Newscoop RO: **2.5-3h** (144k + shortcodes + relații)
- Traduceri: **1-2h** (29k traduceri)
- Delta Beta/Webflow: **30-45 min** (2k articole)
- Enrichment: **30-60 min** (metadata updates)
- **TOTAL: 5-7 ore** (cu workers paraleli)

---

### Opțiunea 2: RECENT + ARHIVĂ SELECTIVĂ (2020+)

**Workflow:**
1. **Import Webflow** (2022-2024): ~2,500 articole
2. **Import Beta** (2020-2022, NOT în Webflow): ~3,000 articole
3. **Import Newscoop** (2020-2024, NOT în Webflow/Beta): ~5,000 articole
4. **Import Arhivă Newscoop** (2016-2019, doar Featured/Breaking): ~1,000 articole

**Total: ~11,500 articole**

**Avantaje:**
- ✅ Focus pe conținut recent (2020-2024)
- ✅ HTML majoritar curat
- ✅ Metadata SEO bună
- ✅ Import rapid (2-3 ore)

**Dezavantaje:**
- ❌ Pierde ~140,000 articole vechi (2016-2019)
- ❌ Arhiva incompletă
- ❌ Links externe sparte (articole lipsa)
- ❌ Site pare "gol" pentru arhivă

---

### Opțiunea 3: DOAR WEBFLOW (Clean Start)

**Workflow:**
1. Import doar Webflow: ~2,500 articole (2022-2024)
2. Traduceri Webflow: RO, EN, RU
3. Skip arhiva completă

**Total: ~2,500-3,000 articole**

**Avantaje:**
- ✅ Clean HTML perfect
- ✅ SEO optim
- ✅ Import foarte rapid (30-45 min)
- ✅ Zero probleme legacy

**Dezavantaje:**
- ❌ Pierde TOATĂ arhiva (150,000+ articole)
- ❌ Site aproape gol
- ❌ Fără conținut istoric
- ❌ **NU RECOMANDAT** pentru site de știri cu istorie

---

### Opțiunea 4: NEWSCOOP ONLY (Maximum Archive)

**Workflow:**
1. Import tot Newscoop: RO + RU + EN
2. Skip Beta și Webflow
3. Include/exclude drafts (optional)

**Total: ~152,500 articole (published only) sau ~175,600 (cu drafts)**

**Avantaje:**
- ✅ Arhiva MAXIMĂ (2016-2024)
- ✅ Toate relațiile păstrate
- ✅ Import direct (fără deduplicare complexă)
- ✅ Rapid (2.5-3 ore)

**Dezavantaje:**
- ❌ HTML legacy (multe articole vechi)
- ❌ Metadata SEO incompletă
- ❌ Pierde îmbunătățiri din Beta/Webflow
- ❌ Imagini cu paths vechi

---

## 📋 TABEL COMPARATIV - 4 Opțiuni

| Criteriu | Opțiunea 1<br>**ARHIVĂ COMPLETĂ** | Opțiunea 2<br>RECENT+SELECT | Opțiunea 3<br>WEBFLOW ONLY | Opțiunea 4<br>NEWSCOOP ONLY |
|----------|-----------------------------------|----------------------------|---------------------------|----------------------------|
| **Volume articole** | **~147,000** | 11,500 | 2,500 | 152,500 |
| **Volume cu traduceri** | **~176,500 rows** | ~15,000 | ~3,500 | ~173,700 |
| **Perioada acoperită** | **2016-2024 (9 ani)** | 2020-2024 (4 ani) | 2022-2024 (2 ani) | 2016-2024 (9 ani) |
| **Arhivă completă** | ✅ **DA** | ⚠️ Parțial | ❌ NU | ✅ DA |
| **HTML curat** | ⚠️ **Mixt (cleanup)** | ✅ Majoritar | ✅ Tot | ❌ Legacy |
| **SEO metadata** | ✅ **Merged (optim)** | ✅ Bună | ✅ Perfectă | ⚠️ Incompletă |
| **Relații păstrate** | ✅ **Toate** | ✅ Majoritatea | ✅ Da | ✅ Toate |
| **Deduplicare** | ✅ **Da (3 surse)** | ✅ Da (3 surse) | ❌ Nu (1 sursă) | ❌ Nu (1 sursă) |
| **Shortcodes processing** | ⚠️ **13,323 articole** | ~2,000 | 0 | 13,323 |
| **Traduceri** | ✅ **29,600** | ~4,000 | ~1,000 | 29,600 |
| **Timp import** | **5-7 ore** | 2-3 ore | 30-45 min | 2.5-3 ore |
| **Complexitate** | 🔴 **Înaltă** | 🟡 Medie | 🟢 Scăzută | 🟢 Scăzută |
| **Risc erori** | 🟡 **Mediu** | 🟡 Mediu | 🟢 Scăzut | 🟢 Scăzut |
| **Compatibilitate links** | ✅ **Maximă** | ⚠️ Medie | ❌ Scăzută | ✅ Maximă |
| **Recomandat pentru** | **Site cu arhivă** ⭐ | Site modern | PoC/Demo | Import rapid |

---

## 🎯 RECOMANDAREA MEA

### **Opțiunea 1: ARHIVĂ COMPLETĂ (Newscoop + Delta + Enrichment)** ⭐

**Justificare:**

1. ✅ **Arhivă completă** - Un site de știri TREBUIE să păstreze istoria
   - 9 ani de conținut (2016-2024)
   - Continuitate editorială
   - Referințe istorice păstrate

2. ✅ **SEO benefits masive**
   - 147,000 articole indexate vs 2,500 (Webflow only)
   - Google loves content volume
   - Long-tail keywords coverage

3. ✅ **Link preservation**
   - Links externe către articole vechi FUNCȚIONEAZĂ
   - Zero 404 errors pentru referințe
   - Webcode mapping pentru redirects

4. ✅ **Content rich**
   - Site nu apare "gol"
   - Arhiva consultabilă
   - Credibilitate jurnalistică

5. ✅ **Metadata îmbunătățită**
   - Merge "best of all sources"
   - Webflow metadata SEO
   - Beta keywords/tags
   - Newscoop content & relații

6. ✅ **Traduceri complete**
   - 29,600 traduceri (RU, EN)
   - Multi-language SEO
   - Audiență extinsă

**Structura finală:**

| Component | Volume |
|-----------|--------|
| **Articole unice RO** | 144,659 |
| **Articole delta (Beta/Webflow)** | ~2,200 |
| **Total articole unice** | **~147,000** |
| **Traduceri RU** | 27,865 |
| **Traduceri EN** | 1,146 |
| **Traduceri delta** | ~600 |
| **Total traduceri** | **~29,600** |
| **TOTAL DATABASE ROWS** | **~176,500** |

**Trade-offs acceptate:**
- ⚠️ Timp import: 5-7 ore (OK pentru import one-time)
- ⚠️ HTML legacy: 13k articole cu shortcodes (NECESITĂ cleanup service)
- ⚠️ Complexitate: Medie-înaltă (dar manageable)

---

## 📸 IMPORT IMAGINI - Strategie Integrată

### Date Reale din Analiza Imagini

**Volume Newscoop:**

| Metric | Valoare |
|--------|---------|
| **Total imagini în DB** | 165,302 |
| **Imagini FOLOSITE** (în articole Published='Y') | **155,332** (93.96%) |
| **Imagini NEUTILIZATE** | 9,970 (6.04%) |
| **Articole cu imagini** | 152,225 (99.8% din 152,523) |
| **Total relații ArticleImages** | 223,780 |

**Distribuție imagini per articol:**

| Nr. Imagini | Articole | Procent | Descriere |
|-------------|----------|---------|-----------|
| **1** | **136,914** | **89.94%** | O singură imagine (majoritatea) |
| **2** | 7,315 | 4.81% | 2 imagini |
| **3** | 2,953 | 1.94% | 3 imagini |
| **4** | 1,775 | 1.17% | 4 imagini |
| **5-10** | 2,798 | 1.84% | Galerii mici |
| **11-20** | 424 | 0.28% | Galerii medii |
| **21-50** | 40 | 0.03% | Galerii mari |
| **50+** | 6 | 0.00% | Galerii foarte mari (max 85) |

**Observații:**
- **89.94%** articole au doar **1 imagine** (featured image tipic)
- **96.86%** articole au **1-3 imagini**
- **Medie: 1.47 imagini/articol**
- Maxim: **85 imagini** într-un articol (galerie foto)

**Distribuție pe format:**

| Format | Imagini Unice | Procent | Dimensiuni Medii | Range |
|--------|---------------|---------|------------------|-------|
| **JPEG** | **143,049** | **92.09%** | 1356×891 | 100×47 → 7667×7016 |
| **PNG** | **12,244** | **7.88%** | 1168×707 | 100×45 → 4969×6254 |
| **GIF** | **39** | **0.03%** | 1138×763 | 222×249 → 2200×1470 |
| **TOTAL** | **155,332** | **100%** | 1343×879 | - |

### Strategie Import Imagini: Resize Optimizat ⭐ RECOMANDAT

**Decizie:** Import doar imagini FOLOSITE + resize la 640×427 (article_card profile)

**Parametri optimizare:**

| Parametru | Valoare | Justificare |
|-----------|---------|-------------|
| **Target dimensiuni** | 640×427 pixels | Aspect ratio 3:2 (standard cards) |
| **Area reduction** | 22.61% vs average | 273,280 vs 1,208,196 pixels |
| **JPEG quality** | 85% | Sweet spot (quality vs size) |
| **Format output** | JPG + WebP | JPG fallback, WebP modern |
| **Progressive** | Da | Better UX (progressive loading) |
| **Fit mode** | Crop | Păstrează aspect ratio exact |

**Calcul economie spațiu:**

| Metric | Valoare Original | După Resize | Economie |
|--------|------------------|-------------|----------|
| **Total imagini** | 165,302 | 155,332 (folosite) | -9,970 |
| **Spațiu disk** | **88 GB** | **12.77 GB** | **-75.23 GB (-85.5%)** 💾 |
| **Dimensiune medie/imagine** | ~533 KB | **~86 KB** | **-447 KB (-84%)** |
| **Size reduction factor** | - | 0.1582 | - |

**Formula calcul:**
```
size_reduction = area_ratio × jpeg_quality
               = 0.2261 × 0.70
               = 0.1582

new_size = 80.7 GB × 0.1582 = 12.77 GB
```

**Comparație opțiuni:**

| Scenariu | Imagini | Spațiu Disk | vs Original |
|----------|---------|-------------|-------------|
| Original (toate) | 165,302 | 88 GB | - |
| Original (folosite) | 155,332 | 80.7 GB | -8.3% |
| **Resize 640×427 (folosite)** | **155,332** | **12.77 GB** | **-84.5%** ✅ |

### Proces Import Imagini

**Comandă:**
```bash
symfony console app:import:images \
    --published-only \          # Doar pentru articole Published='Y'
    --profile=article_card \    # Profil 640×427
    --batch-size=100 \
    --async-thumbnails \        # Generate thumbnails în background
    [--dry-run] \
    [--limit=1000]              # Pentru test
```

**Workflow per imagine:**

1. **Query Newscoop:**
   ```sql
   SELECT DISTINCT
       i.Id, i.ImageFileName, i.Location, i.Description,
       i.width, i.height, i.ContentType, i.Photographer
   FROM Images i
   INNER JOIN ArticleImages ai ON i.Id = ai.IdImage
   INNER JOIN Articles a ON ai.NrArticle = a.Number
   WHERE a.Type = 'stiri' AND a.Published = 'Y'
   ORDER BY i.Id;
   ```

2. **Path original:** `/mnt/newscoop-storage/{Location}/{ImageFileName}`

3. **Upload prin ImageService:**
   ```php
   $uploadedFile = new UploadedFile($originalPath, $filename, $mimeType);

   // ImageService procesează automat:
   // - SHA256 hash (deduplication)
   // - Metadata (width, height, dominant_color, blur_hash)
   // - Salvare original
   // - Create Image entity
   // - Dispatch GenerateImageThumbnailsMessage (async)
   $image = $imageService->uploadImage(
       $uploadedFile,
       $description,  // alt text
       $photographer
   );
   ```

4. **Log mapping:** `newscoop_migration_log`
   ```php
   logMapping('image', $newscoopId, $deschideId);
   ```

5. **Async thumbnail generation:**
   - GenerateImageThumbnailsMessage queued
   - Worker procesează: resize 640×427, quality 85%, crop
   - Output: `article_card.jpg` + `article_card.webp`

**Output așteptat:**
```
Image Import Progress:
[████████████████████████████████] 155,332/155,332 (100%)

Summary:
✓ Total images processed: 155,332
✓ Successfully imported: 154,800 (99.7%)
✓ Deduplicated (SHA256): 532 (0.3%)
✓ Thumbnails queued (async): 309,600 (154,800 × 2 formats)
✓ Disk space used: 12.77 GB (vs 88 GB = 85.5% economie)
✓ Avg size per image: 86 KB (vs 533 KB original)
⚠ Missing files: 500 (0.3% - files not found on disk)
✗ Failed: 32 (0.02% - corrupt images)

Async Thumbnail Generation:
⏳ Queued: 309,600 thumbnails (article_card JPG + WebP)
⏱️  Estimated completion: 2-3 hours
```

### Timp Estimat Import Imagini

| Operație | Timp | Detalii |
|----------|------|---------|
| **Download/Copy** | 1-2 ore | I/O disk (copy din Newscoop storage) |
| **Resize + Optimize** | 5-6 ore | CPU intensive (GD library) |
| **Upload + DB insert** | 1-2 ore | I/O + database |
| **TOTAL** | **8-10 ore** | Cu ~5 imagini/sec |

**Optimizare:** Rulare în **background** paralel cu Faza 5 (import articole)

### Profil Thumbnail: article_card (640×427)

**Specificații profil:**

```yaml
# config/packages/vich_uploader.yaml
app:
    image:
        profiles:
            article_card:
                width: 640
                height: 427
                fit: 'crop'             # Crop exact dimensions
                quality: 85             # JPEG quality 85%
                format: ['jpg', 'webp'] # Both formats
                progressive: true       # Progressive JPEG
```

**Use cases:**

✅ **Potrivit pentru:**
- Article cards în listări
- Thumbnails în căutare
- Featured images în homepage
- Related articles widgets
- Mobile displays (Retina-ready)
- Listări categorii/autori

❌ **NU este potrivit pentru:**
- Full-width hero images (prea mic)
- Zoom/lightbox (rezoluție limitată)
- Print quality (DPI insuficient)
- High-res photo galleries

**Recomandare:** Profil unic **article_card 640×427** este **SUFICIENT pentru MVP** (arhivă știri cu focus pe listări și cards).

### Mapare Relații Article-Image

**În ImportArticlesCommand.php** (după crearea Article entity):

```php
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

    $position = 0;
    foreach ($articleImages as $ai) {
        // Map Newscoop image ID → news_app ID
        $deschideImageId = $this->getMappedId('image', $ai['IdImage']);

        if (!$deschideImageId) {
            $this->logger->warning("Image not found for article", [
                'article_number' => $newscoopArticleNumber,
                'newscoop_image_id' => $ai['IdImage']
            ]);
            continue;
        }

        $imageEntity = $this->imageRepository->find($deschideImageId);

        if ($imageEntity) {
            // Create ArticleImage entity (junction table)
            $articleImage = new ArticleImage();
            $articleImage->setArticle($article);
            $articleImage->setImage($imageEntity);
            $articleImage->setPosition($position++);
            $articleImage->setIsFeatured($ai['is_default'] == 1);

            $this->em->persist($articleImage);
        }
    }
}

// Apelare în main import loop:
$this->importArticleImages($newscoopArticleNumber, $article);
```

**Note:**
- Prima imagine cu `is_default=1` → `ArticleImage.isFeatured = true`
- Relație ManyToMany prin `ArticleImage` junction entity
- Position preservation (ordinea din Newscoop)
- O imagine poate fi în multiple articole (Many-to-Many)

### Verificări Post-Import Imagini

**Comandă validare:**
```bash
symfony console app:post-validate:images
```

**Checks:**
```
✓ Total images imported: 155,332
✓ Images with article_card thumbnails: 154,800 (99.7%)
✓ Featured images set: 152,100 articles (99.8%)
✓ Article-image relationships: 223,780
✓ Orphaned images: 0 (toate au article relation)
✓ Missing thumbnails: 532 (queued for retry)
✗ Failed thumbnails: 32 (< 0.1% - corrupt source)

Disk usage:
  Originals: 12.77 GB (155,332 × ~86 KB avg)
  Thumbnails JPG: 12.77 GB (154,800 × ~86 KB)
  Thumbnails WebP: 10.21 GB (154,800 × ~69 KB)
  TOTAL: 35.75 GB (vs 88 GB original = 59% economie)
```

**Queries verificare:**

```sql
-- Total imagini importate
SELECT COUNT(*) FROM image;
-- Expected: 155,332

-- Imagini cu thumbnails
SELECT COUNT(DISTINCT i.id)
FROM image i
INNER JOIN thumbnail t ON i.id = t.image_id
WHERE t.profile = 'article_card';
-- Expected: ~154,800

-- Articole fără featured image
SELECT COUNT(*)
FROM article a
LEFT JOIN article_image ai ON a.id = ai.article_id AND ai.is_featured = true
WHERE ai.id IS NULL;
-- Expected: < 500 (0.3%)

-- Relații article-image
SELECT COUNT(*) FROM article_image;
-- Expected: 223,780
```

---

## 🛠️ DETALII IMPLEMENTARE - Opțiunea 1

### FAZA 1: Import Newscoop RO (Articole Principale)

**Query principal:**
```sql
SELECT
    a.Number,
    a.IdLanguage,
    a.Name,
    a.PublishDate,
    a.UploadDate,
    a.time_updated,
    a.Keywords,
    a.OnFrontPage,
    a.NrSection,
    a.webcode,
    x.FTitlu,
    x.Fsubtitlu,
    x.Flead,
    x.FContinut,
    x.FBREAKING_NEWS,
    x.FNEWS_ALERT,
    x.FFLASH
FROM Articles a
INNER JOIN Xstiri x ON a.Number = x.NrArticle AND a.IdLanguage = x.IdLanguage
WHERE a.Type = 'stiri'
  AND a.Published = 'Y'
  AND a.IdLanguage = 2  -- RO only
ORDER BY a.Number;
```

**Mapare câmpuri:**

| Newscoop | Deschide Entity | Transformare |
|----------|----------------|--------------|
| `Number` | `newscoopId` (int) | Direct |
| `FTitlu` | `title` (string, max 255) | Trim + cleanup |
| `Fsubtitlu` | `subtitle` (nullable) | Trim |
| `Flead` | `lead` (text) | HTML cleanup light |
| `FContinut` | `content` (text) | **NewscoopContentProcessor** (shortcodes!) |
| `Published='Y'` | `status` | 'published' |
| `PublishDate` | `publishedAt` | DateTime |
| `OnFrontPage='Y'` | `isFeatured` | true/false |
| `Keywords` | `keywords` | CSV split → array |
| `FBREAKING_NEWS=1` | `badge` | 'breaking' |
| `FNEWS_ALERT=1` | `badge` (fallback) | 'alert' |
| `FFLASH=1` | `badge` (fallback) | 'flash' |
| `NrSection` | `category` | Lookup in migration_log (category mapping) |
| `time_updated` | `updatedAt` | DateTime |
| `webcode` | `webcode` | String (pentru redirects) |

**Relații (import ulterior în aceeași fază):**
- **Category**: via `NrSection` → lookup în `newscoop_migration_log`
- **Autori**: via `ArticleAuthors` junction table
- **Imagini**: via `ArticleImages` junction table (vezi secțiunea Imagini)

**Filtre stricte:**
- `Type = 'stiri'` (exclude embed, ultrascurte, video)
- `Published = 'Y'` (exclude drafts N și submitted S)
- `IdLanguage = 2` (doar RO în Faza 1)
- `FContinut IS NOT NULL` (skip articole goale)

**Procesare shortcodes** (13,323 articole = 8.73%):

```php
// În ImportArticlesCommand.php
use App\Service\Import\NewscoopContentProcessor;

private function processArticleContent(array $articleData): string
{
    $content = $articleData['FContinut'];

    // Check dacă are shortcodes
    if (str_contains($content, '<!**')) {
        // NewscoopContentProcessor service
        $content = $this->contentProcessor->processShortcodes(
            $content,
            $articleData['Number'],  // pentru logging
            $this->migrationLogger   // pentru image ID mapping
        );
    }

    // HTML cleanup basic
    $content = $this->cleanHtmlContent($content);

    return $content;
}
```

**Volume:**
- Estimate: **144,659 articole**
- Cu shortcodes: **~12,600** (8.73% × 144,659)
- Batch size: 50 articole/batch (mai mic pentru procesare)
- Workers: 4 paralel
- Durata: **2.5-3 ore** (+15% overhead pentru shortcodes)

---

### FAZA 2: Import Traduceri Newscoop (RU, EN)

**Comenzi:**
```bash
symfony console app:import:translations --locale=ru --batch-size=100
symfony console app:import:translations --locale=en --batch-size=100
```

**Logica Gedmo Translatable:**

1. Găsește articolul RO cu același `Number` în Newscoop
2. Lookup `deschide_id` în `newscoop_migration_log`
3. Load Article entity (RO principal)
4. Set locale RU/EN și update câmpuri translatabile
5. Persist → Gedmo creează row în `article_translations`

```php
// În ImportTranslationsCommand.php

foreach ($translations as $translationData) {
    $newscoopNumber = $translationData['Number'];
    $targetLocale = $translationData['IdLanguage'] == 15 ? 'ru' : 'en';

    // Find Romanian article (principal)
    $deschideArticleId = $this->getMappedId('article', $newscoopNumber, 'ro');

    if (!$deschideArticleId) {
        $this->logger->warning("Romanian article not found for translation", [
            'newscoop_number' => $newscoopNumber,
            'target_locale' => $targetLocale
        ]);
        continue;
    }

    $article = $this->articleRepository->find($deschideArticleId);

    // Set translation locale
    $article->setTranslatableLocale($targetLocale);

    // Update translatable fields
    $article->setTitle($translationData['FTitlu']);
    $article->setSubtitle($translationData['Fsubtitlu']);
    $article->setLead($translationData['Flead']);
    $article->setContent($this->processContent($translationData['FContinut']));
    $article->setKeywords($this->splitKeywords($translationData['Keywords']));

    $this->em->persist($article);
    $this->em->flush();  // Force insert în ext_translations

    // Log translation
    $this->migrationLogger->logSuccess(
        'article_translation',
        $newscoopNumber,
        $deschideArticleId,
        ['locale' => $targetLocale]
    );
}
```

**Câmpuri traduse** (Gedmo Translatable):

| Camp Deschide | Translatable | Stocare |
|---------------|--------------|---------|
| `title` | ✅ Yes | ext_translations |
| `subtitle` | ✅ Yes | ext_translations |
| `lead` | ✅ Yes | ext_translations |
| `content` | ✅ Yes | ext_translations |
| `keywords` | ✅ Yes | ext_translations |
| `slug` | ✅ Yes (auto) | ext_translations |
| `status` | ❌ No | articles (shared) |
| `publishedAt` | ❌ No | articles (shared) |
| `isFeatured` | ❌ No | articles (shared) |
| `category` | ❌ No | articles (shared) |
| `author` | ❌ No | articles (shared) |

**Volume:**
- RU: **27,865 traduceri**
- EN: **1,146 traduceri**
- Total: **29,011 traduceri**
- Durata: **1-2 ore** (batch 100, mai rapid că nu are relații)

---

### FAZA 3: Import Delta Beta

**Comandă:**
```bash
symfony console app:import:beta-articles --dry-run
symfony console app:import:beta-articles --batch-size=50
```

**Logica deduplicare:**

```php
function isDuplicate(array $betaArticle): bool|int
{
    // 1. Check by slug (fastest)
    $slug = $this->normalizeSlug($betaArticle['slug']);
    $existing = $this->articleRepository->findOneBy(['slug' => $slug]);

    if ($existing) {
        return $existing->getId(); // Return ID pentru enrichment
    }

    // 2. Check by title + publishDate (±24h)
    $titleNorm = $this->normalizeTitle($betaArticle['title']);
    $publishDate = new \DateTime($betaArticle['published_date']);

    $qb = $this->articleRepository->createQueryBuilder('a');
    $qb->where('LOWER(a.title) = :title')
       ->andWhere('a.publishedAt BETWEEN :dateStart AND :dateEnd')
       ->setParameter('title', $titleNorm)
       ->setParameter('dateStart', $publishDate->modify('-1 day'))
       ->setParameter('dateEnd', $publishDate->modify('+2 days'));

    $existing = $qb->getQuery()->getOneOrNullResult();

    if ($existing) {
        return $existing->getId(); // Matched
    }

    return false; // NOT duplicate → import as new
}

// În import loop:
foreach ($betaArticles as $betaArticle) {
    $duplicateId = $this->isDuplicate($betaArticle);

    if ($duplicateId) {
        // UPDATE metadata dacă lipsește
        $this->enrichMetadata($duplicateId, $betaArticle);
        $stats['enriched']++;
    } else {
        // Import as new article
        $this->importBetaArticle($betaArticle);
        $stats['imported']++;
    }
}
```

**Metadata enrichment:**

```php
function enrichMetadata(int $articleId, array $betaData): void
{
    $article = $this->articleRepository->find($articleId);

    // Update doar dacă NULL în Newscoop
    if (!$article->getMetaTitle() && !empty($betaData['seo_title'])) {
        $article->setMetaTitle($betaData['seo_title']);
    }

    if (!$article->getMetaDescription() && !empty($betaData['seo_description'])) {
        $article->setMetaDescription($betaData['seo_description']);
    }

    // Merge keywords (unique)
    if (!empty($betaData['tags'])) {
        $existingKeywords = $article->getKeywords() ?? [];
        $newKeywords = explode(',', $betaData['tags']);
        $merged = array_unique(array_merge($existingKeywords, $newKeywords));
        $article->setKeywords($merged);
    }

    $this->em->persist($article);
    $this->em->flush();
}
```

**Volume estimate:**
- Total Beta: **~8,000 articole**
- Duplicates matched: **~6,000** (75% în Newscoop)
- **Importate NOI**: **~2,000 articole**
- **Enriched**: **~6,000 articole** (metadata updates)
- Durata: **20-30 min**

---

### FAZA 4: Import Delta Webflow

**Comandă:**
```bash
symfony console app:import:webflow-articles --dry-run
symfony console app:import:webflow-articles
```

**Logica deduplicare** (similară Beta, dar prioritate slug):

```php
function isDuplicate(array $webflowArticle): bool|int
{
    // 1. Check by webcode (Newscoop.webcode == Webflow.slug)
    if (!empty($webflowArticle['slug'])) {
        $existing = $this->articleRepository->findOneBy([
            'webcode' => $webflowArticle['slug']
        ]);

        if ($existing) {
            return $existing->getId();
        }
    }

    // 2. Check by slug normalizat
    $slug = $this->normalizeSlug($webflowArticle['slug']);
    $existing = $this->articleRepository->findOneBy(['slug' => $slug]);

    if ($existing) {
        return $existing->getId();
    }

    // 3. Check by title + date (±24h)
    return $this->checkByTitleAndDate($webflowArticle);
}
```

**Metadata enrichment** (similar Beta, dar mai complet):

```php
function enrichMetadata(int $articleId, array $webflowData): void
{
    $article = $this->articleRepository->find($articleId);

    // SEO metadata (prioritate - Webflow are cele mai bune)
    if (!empty($webflowData['seo_title'])) {
        $article->setMetaTitle($webflowData['seo_title']); // overwrite
    }

    if (!empty($webflowData['seo_description'])) {
        $article->setMetaDescription($webflowData['seo_description']);
    }

    // OG Image (dacă Webflow are calitate mai bună)
    if (!empty($webflowData['og_image_url'])) {
        // Compare cu existing featured image
        $this->updateOgImageIfBetter($article, $webflowData['og_image_url']);
    }

    // Keywords merge
    if (!empty($webflowData['keywords'])) {
        $this->mergeKeywords($article, $webflowData['keywords']);
    }

    $this->em->persist($article);
}
```

**Volume estimate:**
- Total Webflow: **~2,500 articole**
- Duplicates matched: **~2,000** (80% în Newscoop/Beta)
- **Importate NOI**: **~400-500 articole**
- **Enriched**: **~2,000 articole** (metadata updates)
- Durata: **10-15 min**

---

### FAZA 5: Metadata Enrichment Batch

**Comandă:**
```bash
symfony console app:enrich:metadata --source=webflow --batch-size=100
symfony console app:enrich:metadata --source=beta --batch-size=100
```

**Queries batch update:**

```sql
-- Update metaTitle din Webflow (pentru matched articles)
UPDATE article a
SET meta_title = w.seo_title
FROM webflow_mapping wm
INNER JOIN webflow_articles w ON wm.webflow_id = w.id
WHERE a.id = wm.deschide_article_id
  AND a.meta_title IS NULL
  AND w.seo_title IS NOT NULL;

-- Update metaDescription
UPDATE article a
SET meta_description = w.seo_description
FROM webflow_mapping wm
INNER JOIN webflow_articles w ON wm.webflow_id = w.id
WHERE a.id = wm.deschide_article_id
  AND a.meta_description IS NULL
  AND w.seo_description IS NOT NULL;

-- Merge keywords (JSON array merge)
UPDATE article a
SET keywords = (
    SELECT array_agg(DISTINCT keyword)
    FROM (
        SELECT unnest(a.keywords) AS keyword
        UNION
        SELECT unnest(w.keywords) AS keyword
    ) merged
)
FROM webflow_mapping wm
INNER JOIN webflow_articles w ON wm.webflow_id = w.id
WHERE a.id = wm.deschide_article_id;
```

**Câmpuri enriched:**

| Camp | Sursă prioritate | Logică |
|------|------------------|--------|
| `metaTitle` | Webflow > Beta > NULL | Update doar dacă NULL |
| `metaDescription` | Webflow > Beta > NULL | Update doar dacă NULL |
| `keywords` | Merge (unique) | Append + deduplicate |
| `ogImage` | Webflow (dacă mai bună) | Compare quality/resolution |

**Durata:** **30-60 min** (batch updates SQL rapide)

---

## 📊 STRUCTURA FINALĂ - Statistici Estimate

### Volume Totale (Opțiunea 1)

| Sursă | Articole Unice | Traduceri | Total Rows | Procent |
|-------|----------------|-----------|------------|---------|
| **Newscoop RO** | 144,659 | - | 144,659 | 81.9% |
| **Newscoop RU** | - | 27,865 | 27,865 | 15.8% |
| **Newscoop EN** | - | 1,146 | 1,146 | 0.6% |
| **Beta (delta)** | 1,800 | ~500 | 2,300 | 1.3% |
| **Webflow (delta)** | 400 | ~100 | 500 | 0.3% |
| **TOTAL** | **~146,900** | **~29,600** | **~176,500** | **100%** |

**Note:**
- **Articole unice**: ~146,900
- **Total database rows**: ~176,500 (articles + article_translations)
- **Traduceri**: ~29,600 (16.8% din total rows)
- **Gedmo Translatable**: 2 tables (articles + ext_translations)

### Distribuție pe Categorii (Estimate)

| Categorie | Volume | % din total | Sursă dominantă |
|-----------|--------|-------------|-----------------|
| **Politic** | ~40,000 | 27% | Newscoop |
| **Social** | ~35,000 | 24% | Newscoop |
| **Economic** | ~20,000 | 14% | Newscoop |
| **Externe** | ~18,000 | 12% | Newscoop |
| **Cultură** | ~10,000 | 7% | Newscoop |
| **Sport** | ~8,000 | 5% | Newscoop |
| **Editorial** | ~5,000 | 3% | Newscoop |
| **Investigații** | ~3,000 | 2% | Newscoop |
| **Opinii** | ~2,500 | 2% | Newscoop |
| **Altele** | ~6,400 | 4% | Mix |
| **TOTAL** | **~146,900** | **100%** | - |

### Distribuție pe Ani

| Perioada | Volume | Sursă principală | % |
|----------|--------|------------------|---|
| **2016-2017** | ~22,000 | Newscoop | 15% |
| **2018-2019** | ~33,700 | Newscoop | 23% |
| **2020-2021** | ~38,000 | Newscoop | 26% |
| **2022-2023** | ~42,700 | Newscoop + Beta + Webflow | 29% |
| **2024** | ~10,500 | Beta + Webflow + Newscoop | 7% |
| **TOTAL** | **~146,900** | - | **100%** |

### Tipuri Articole (Badge Distribution)

| Badge | Volume | % | Descriere |
|-------|--------|---|-----------|
| (none) | ~133,000 | 90.5% | Știri standard |
| breaking | ~500 | 0.3% | Breaking news |
| exclusive | ~1,000 | 0.7% | Exclusive |
| analysis | ~3,000 | 2.0% | Analiză |
| opinion | ~2,500 | 1.7% | Opinii/Editoriale |
| video | ~300 | 0.2% | Video embed |
| photo_gallery | ~600 | 0.4% | Galerii foto |
| interview | ~6,000 | 4.1% | Interviuri |
| **TOTAL** | **~146,900** | **100%** | - |

### Statistici Procesare Conținut

| Metric | Volume | % |
|--------|--------|---|
| **Articole cu shortcodes** (Newscoop) | **13,323** | 9.1% |
| **Shortcodes procesate** | ~13,323 | - |
| **Imagini mapate în shortcodes** | ~25,000 | - |
| **Links interne mapate** | ~5,000 | - |
| **HTML cleanup** (toate) | 146,900 | 100% |
| **Keywords split** (CSV→array) | ~80,000 | 54% |

---

## ⚙️ COMENZI SYMFONY - Implementation

### Comenzi Existente ✅ (verificate)

```bash
# Import articole Newscoop (RO)
symfony console app:import:articles --locale=ro --batch-size=50 --limit=1000

# Import articole cu relații (authors, categories, images)
symfony console app:import:articles-with-relations --locale=ro --batch-size=50

# Import traduceri
symfony console app:import:translations --locale=ru --batch-size=100
symfony console app:import:translations --locale=en --batch-size=100

# Import dependințe (ÎNAINTE de articole)
symfony console app:import:categories
symfony console app:import:authors
symfony console app:import:images --published-only --profile=article_card
```

### Comenzi Noi de Implementat ⬜

```bash
# Import Beta articles (cu deduplicare)
symfony console app:import:beta-articles \
    [--dry-run] \
    [--batch-size=50] \
    [--limit=100]

# Import Webflow articles (cu deduplicare)
symfony console app:import:webflow-articles \
    [--dry-run] \
    [--batch-size=50]

# Enrich metadata din surse externe
symfony console app:enrich:metadata \
    --source=webflow|beta \
    [--dry-run] \
    [--batch-size=100]

# Verificare duplicates
symfony console app:articles:find-duplicates \
    [--threshold=80] \      # Similarity threshold (%)
    [--fix] \               # Auto-fix (merge duplicates)
    [--dry-run]

# Cleanup HTML legacy
symfony console app:articles:cleanup-html \
    [--batch-size=100] \
    [--limit=1000] \
    [--dry-run]

# Generate missing slugs
symfony console app:articles:generate-slugs \
    [--force] \             # Regenerate existing slugs
    [--transliterate]       # Romanian chars → ASCII

# Reindex Elasticsearch (după import)
symfony console app:elasticsearch:index-articles \
    --force \
    --batch-size=500
```

---

## 🚨 PROBLEME ANTICIPATE & SOLUȚII

### Problema 1: Volume Mari (150,000+ articole)

**Simptome:**
- PHP timeout (max_execution_time)
- Memory exhausted
- Import foarte lent
- Database connection timeout

**Soluții:**

✅ **1. Batch Processing**
```php
// În ImportArticlesCommand.php
private const BATCH_SIZE = 50; // Articole per batch

for ($offset = 0; $offset < $total; $offset += self::BATCH_SIZE) {
    $articles = $this->fetchArticlesBatch($offset, self::BATCH_SIZE);

    foreach ($articles as $articleData) {
        $this->importArticle($articleData);
    }

    // Clear EntityManager după fiecare batch
    $this->em->flush();
    $this->em->clear(); // IMPORTANT: prevent memory leak!

    // Garbage collection
    gc_collect_cycles();
}
```

✅ **2. Pagination cu offset/limit**
```bash
# Terminal 1
symfony console app:import:articles --locale=ro --offset=0 --limit=40000

# Terminal 2
symfony console app:import:articles --locale=ro --offset=40000 --limit=40000

# Terminal 3
symfony console app:import:articles --locale=ro --offset=80000 --limit=40000

# Terminal 4
symfony console app:import:articles --locale=ro --offset=120000
```

✅ **3. Workers paraleli** (Symfony Messenger)
```bash
# Start 4 workers
symfony console messenger:consume async -vv --limit=1000  # Worker 1
symfony console messenger:consume async -vv --limit=1000  # Worker 2
symfony console messenger:consume async -vv --limit=1000  # Worker 3
symfony console messenger:consume async -vv --limit=1000  # Worker 4
```

✅ **4. PHP configuration**
```ini
# php.ini (pentru import)
memory_limit = 2G
max_execution_time = 0  # Unlimited (CLI only)
max_input_time = -1
```

✅ **5. Database connection tuning**
```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        default_connection: default
        connections:
            default:
                options:
                    1002: "SET SESSION wait_timeout=28800"  # 8h
```

---

### Problema 2: HTML Legacy (Newscoop)

**Simptome:**
- Tags deprecated: `<font>`, `<center>`, `<marquee>`
- Inline styles: `style="color:red"`
- Embedded scripts: `<script>alert()</script>`
- Image paths absolute: `http://old-site.md/images/...`
- Broken embeds: Flash, old YouTube format

**Soluții:**

✅ **HTML Cleanup Service**
```php
// src/Service/Import/HtmlCleanupService.php

class HtmlCleanupService
{
    public function cleanHtml(string $html): string
    {
        // 1. Remove deprecated tags (păstrează doar safe tags)
        $html = strip_tags($html, '<p><br><a><strong><em><ul><ol><li><h2><h3><h4><blockquote><img><figure><figcaption>');

        // 2. Remove inline styles
        $html = preg_replace('/style="[^"]*"/', '', $html);

        // 3. Remove inline event handlers
        $html = preg_replace('/on\w+="[^"]*"/', '', $html);

        // 4. Fix image paths (absolute → relative)
        $html = preg_replace(
            '/src="https?:\/\/(old-site\.md|newscoop\.local)\//',
            'src="/',
            $html
        );

        // 5. Remove empty paragraphs
        $html = preg_replace('/<p>(\s|&nbsp;)*<\/p>/', '', $html);

        // 6. Fix broken YouTube embeds
        $html = $this->fixYouTubeEmbeds($html);

        // 7. Remove excessive whitespace
        $html = preg_replace('/\s+/', ' ', $html);

        return trim($html);
    }

    private function fixYouTubeEmbeds(string $html): string
    {
        // Old: <object>...</object> sau <embed>
        // New: <iframe src="https://youtube.com/embed/{id}">

        // Pattern pentru old YouTube embeds
        $pattern = '/<object.*?youtube\.com\/v\/([a-zA-Z0-9_-]+).*?<\/object>/is';
        $replacement = '<iframe width="560" height="315" src="https://www.youtube.com/embed/$1" frameborder="0" allowfullscreen></iframe>';

        return preg_replace($pattern, $replacement, $html);
    }
}
```

✅ **Comandă batch cleanup**
```bash
symfony console app:articles:cleanup-html \
    --batch-size=100 \
    --dry-run  # Test first!

# Output:
# Processing batch 1/1469...
# ✓ Article 12345: Removed 5 deprecated tags, 12 inline styles
# ✓ Article 12346: Fixed 1 YouTube embed, removed 3 empty <p>
# ...
# Summary:
#   Total: 146,900 articles
#   Modified: 42,300 (28.8%)
#   Deprecated tags removed: 125,000
#   Inline styles removed: 89,000
#   YouTube embeds fixed: 1,200
```

---

### Problema 3: Shortcodes Newscoop (13,323 articole)

**Simptome:**
- Content cu `<!** Image [id] ...>` raw în HTML
- Imagini nu se afișează (broken image IDs)
- Links interne sparte (old article IDs)

**Soluții:**

✅ **NewscoopContentProcessor Service** (PRIORITATE MAXIMĂ!)

```php
// src/Service/Import/NewscoopContentProcessor.php

class NewscoopContentProcessor
{
    private const REGEX_IMAGE = '/<!\\*\\*\s+Image\s+\[(\d+)\]\s+align="([^"]+)"\s+alt="([^"]*)"\s+sub="([^"]*)"\s+\\*\\*>/';
    private const REGEX_LINK = '/<!\\*\\*\s+Link\s+Internal\s+\[(\d+)\]\s+text="([^"]*)"\s+\\*\\*>/';

    public function processShortcodes(
        string $content,
        int $articleNumber,
        MigrationLoggerService $logger
    ): string {
        // 1. Process Image shortcodes
        $content = $this->processImageShortcodes($content, $articleNumber, $logger);

        // 2. Process Link Internal shortcodes
        $content = $this->processLinkShortcodes($content, $logger);

        // 3. Remove other shortcodes (Title, Snippet)
        $content = $this->removeUnsupportedShortcodes($content);

        return $content;
    }

    private function processImageShortcodes(
        string $content,
        int $articleNumber,
        MigrationLoggerService $logger
    ): string {
        return preg_replace_callback(
            self::REGEX_IMAGE,
            function ($matches) use ($articleNumber, $logger) {
                $newscoopImageId = (int)$matches[1];
                $align = $matches[2];
                $alt = $matches[3];
                $caption = $matches[4];

                // Map Newscoop image ID → Deschide ID
                $deschideImageId = $logger->getMappedId('image', $newscoopImageId);

                if (!$deschideImageId) {
                    $logger->warning("Image not found in mapping", [
                        'article_number' => $articleNumber,
                        'newscoop_image_id' => $newscoopImageId
                    ]);
                    return ''; // Remove shortcode
                }

                // Get Image entity
                $image = $this->imageRepository->find($deschideImageId);

                if (!$image) {
                    return '';
                }

                // Generate HTML5 <figure>
                $alignClass = match($align) {
                    'left' => 'float-left',
                    'right' => 'float-right',
                    default => '',
                };

                $html = sprintf(
                    '<figure class="%s"><img src="/uploads/images/%s" alt="%s" loading="lazy">',
                    $alignClass,
                    $image->getPath(),
                    htmlspecialchars($alt)
                );

                if (!empty($caption)) {
                    $html .= sprintf('<figcaption>%s</figcaption>', htmlspecialchars($caption));
                }

                $html .= '</figure>';

                return $html;
            },
            $content
        );
    }

    private function processLinkShortcodes(string $content, MigrationLoggerService $logger): string
    {
        return preg_replace_callback(
            self::REGEX_LINK,
            function ($matches) use ($logger) {
                $newscoopArticleNumber = (int)$matches[1];
                $linkText = $matches[2];

                // Map Newscoop article Number → Deschide ID
                $deschideArticleId = $logger->getMappedId('article', $newscoopArticleNumber);

                if (!$deschideArticleId) {
                    // Fallback: return plain text
                    return $linkText;
                }

                // Get Article slug
                $article = $this->articleRepository->find($deschideArticleId);

                if (!$article) {
                    return $linkText;
                }

                // Generate link
                return sprintf(
                    '<a href="/articole/%s">%s</a>',
                    $article->getSlug(),
                    htmlspecialchars($linkText)
                );
            },
            $content
        );
    }
}
```

✅ **Test shortcode processing**
```bash
symfony console app:test:shortcode-processing --article=12345

# Output:
# Original content (excerpt):
# "<!** Image [456] align="left" alt="Protest" sub="Protestatari în fața parlamentului" **>"
#
# Processed content:
# "<figure class="float-left">
#   <img src="/uploads/images/abc123.jpg" alt="Protest" loading="lazy">
#   <figcaption>Protestatari în fața parlamentului</figcaption>
# </figure>"
#
# ✓ Shortcode processed successfully
# ✓ Image ID mapped: 456 → 789 (deschide)
```

**Timp procesare:** +15% overhead pentru articolele cu shortcodes (~20 min extra)

---

### Problema 4: Deduplicare Imperfectă

**Simptome:**
- Același articol importat 2-3 ori (din Newscoop, Beta, Webflow)
- Title-uri cu diferențe minore ("Titlu" vs "Titlu.")
- Publish dates cu ore diferite

**Soluții:**

✅ **1. Normalizare title**
```php
function normalizeTitle(string $title): string
{
    $title = mb_strtolower($title, 'UTF-8');
    $title = preg_replace('/\s+/', ' ', $title); // whitespace
    $title = trim($title);
    $title = preg_replace('/[^\p{L}\p{N}\s]/u', '', $title); // punctuation
    $title = preg_replace('/\s+/', ' ', $title); // again (after punctuation removal)

    // Transliterate Romanian chars
    $title = str_replace(
        ['ă', 'â', 'î', 'ș', 'ț', 'Ă', 'Â', 'Î', 'Ș', 'Ț'],
        ['a', 'a', 'i', 's', 't', 'a', 'a', 'i', 's', 't'],
        $title
    );

    return $title;
}
```

✅ **2. Date fuzzy matching**
```php
function isSameDate(\DateTime $d1, \DateTime $d2, int $marginHours = 24): bool
{
    $diff = abs($d1->getTimestamp() - $d2->getTimestamp());
    return $diff <= ($marginHours * 3600);
}

// Usage:
if ($this->isSameDate($newscoopDate, $betaDate, 24)) {
    // Probabil același articol (±24h)
}
```

✅ **3. Content similarity** (pentru edge cases)
```php
function contentSimilarity(string $c1, string $c2, int $excerptLength = 200): float
{
    $excerpt1 = substr(strip_tags($c1), 0, $excerptLength);
    $excerpt2 = substr(strip_tags($c2), 0, $excerptLength);

    similar_text($excerpt1, $excerpt2, $percent);

    return $percent; // 0-100%
}

// Usage:
if ($this->contentSimilarity($newscoopContent, $betaContent) >= 80) {
    // Probabil duplicate (80%+ match)
}
```

✅ **4. Comandă find duplicates**
```bash
symfony console app:articles:find-duplicates \
    --threshold=80 \  # Similarity threshold
    --fix \           # Auto-merge
    --dry-run

# Output:
# Finding duplicates (threshold: 80%)...
# [████████████████] 146,900/146,900
#
# Found 127 potential duplicates:
#
# Group 1:
#   Article #12345 (Newscoop): "Președintele a declarat..."
#   Article #45678 (Beta): "Presedintele a declarat..." (similarity: 95%)
#   → Action: Merge #45678 into #12345
#
# Group 2:
#   Article #23456 (Newscoop): "Protestatar arestați după..."
#   Article #56789 (Webflow): "Protestatar arestati dupa..." (similarity: 92%)
#   → Action: Merge #56789 into #23456
# ...
#
# Summary:
#   Total duplicates: 127 groups (254 articles)
#   Auto-merged: 127 (with --fix)
#   Manual review needed: 0
```

---

### Problema 5: Relații Lipsă (Categorii, Autori, Imagini)

**Simptome:**
- `article.category_id = NULL`
- `article_author` junction table goală
- `article_image` fără relații
- FK constraint errors

**Soluții:**

✅ **1. Import dependințe ÎNAINTE**
```bash
# Ordinea CORECTĂ:
symfony console app:import:categories          # FAZA 2
symfony console app:import:authors             # FAZA 3
symfony console app:import:images \            # FAZA 4
    --published-only \
    --profile=article_card &                   # Background
symfony console app:import:articles \          # FAZA 5 (DUPĂ 2-4!)
    --locale=ro
```

✅ **2. Fallback categorii**
```php
function getCategoryOrFallback(int $newscoopSectionId): Category
{
    // 1. Lookup in migration_log
    $deschideCategoryId = $this->migrationLogger->getMappedId(
        'category',
        $newscoopSectionId
    );

    if ($deschideCategoryId) {
        $category = $this->categoryRepository->find($deschideCategoryId);
        if ($category) {
            return $category;
        }
    }

    // 2. Fallback: "Social" (default category)
    $category = $this->categoryRepository->findOneBy(['slug' => 'social']);

    if (!$category) {
        // 3. Last resort: Create "Uncategorized"
        $category = new Category();
        $category->setName('Uncategorized');
        $category->setSlug('uncategorized');
        $this->em->persist($category);
        $this->em->flush();
    }

    return $category;
}
```

✅ **3. Verificare post-import**
```bash
# Găsește articole fără categorie
symfony console doctrine:query:sql "
  SELECT id, title, newscoop_id
  FROM article
  WHERE category_id IS NULL
  LIMIT 100
"

# Fix automat
symfony console app:articles:fix-missing-categories

# Output:
# Finding articles without category...
# Found: 234 articles (0.16%)
#
# Fixing:
#   Article #12345: Set category = 'Social' (fallback)
#   Article #12346: Set category = 'Social' (fallback)
# ...
# ✓ Fixed 234 articles
```

✅ **4. Verificare autori**
```sql
-- Articole fără autori (junction table goală)
SELECT COUNT(*)
FROM article a
LEFT JOIN article_author aa ON a.id = aa.article_id
WHERE aa.article_id IS NULL;

-- Expected: 0 (toate articolele trebuie să aibă măcar 1 autor)
```

✅ **5. Verificare imagini**
```sql
-- Articole fără featured image
SELECT COUNT(*)
FROM article a
LEFT JOIN article_image ai ON a.id = ai.article_id AND ai.is_featured = true
WHERE ai.id IS NULL;

-- Expected: < 500 (99.7% trebuie să aibă featured image)
```

---

## 📅 PLAN DE EXECUȚIE - Timeline Detaliat

### Ziua 0: Pregătire Infrastructură (2-3 ore)

**Task-uri:**
- [ ] **Backup database** PostgreSQL (pg_dump)
  ```bash
  pg_dump -h localhost -U deschide_admin deschide_news > backup_pre_import.sql
  ```
- [ ] **Verificare conexiuni:**
  - [ ] Newscoop MySQL: `mysql -h localhost -u newscoop_user -p newscoop`
  - [ ] Beta API: `curl https://beta.deschide.md/api/articles`
  - [ ] Webflow API: `curl -H "Authorization: Bearer TOKEN" https://api.webflow.com/...`
- [ ] **Implementare NewscoopContentProcessor** service (2-3h) ⚠️ **PRIORITATE MAXIMĂ**
  ```bash
  # Test procesare shortcodes
  symfony console app:test:shortcode-processing --article=12345
  ```
- [ ] **Run comenzi dependințe:**
  ```bash
  symfony console app:import:categories --dry-run
  symfony console app:import:authors --dry-run
  symfony console app:import:images --limit=10 --dry-run
  ```
- [ ] **Test import articole (sample):**
  ```bash
  symfony console app:import:articles --locale=ro --limit=10 --dry-run
  ```

**Verificare:**
```bash
# Check migration_log table exists
symfony console doctrine:query:sql "SELECT COUNT(*) FROM newscoop_migration_log"

# Check NewscoopContentProcessor
symfony console app:test:shortcode-processing --article=12345
```

---

### Ziua 1: Import Categorii + Autori (1-1.5 ore)

**FAZA 2: Import Categorii** (15-20 min)

```bash
# Import Newscoop Sections → Categories
symfony console app:import:categories

# Output așteptat:
# ✓ Found 43 sections in Newscoop (18 unique)
# ✓ Imported 18 categories
# ✓ Logged 43 mappings (Newscoop section ID → Deschide category ID)
```

**Verificare:**
```sql
SELECT COUNT(*) FROM category;
-- Expected: 18-20

SELECT entity_type, COUNT(*)
FROM newscoop_migration_log
WHERE entity_type = 'category'
GROUP BY entity_type;
-- Expected: 43 mappings
```

**FAZA 3: Import Autori** (20-30 min)

```bash
# Import Newscoop Authors
symfony console app:import:authors

# Output așteptat:
# ✓ Found 282 authors in Newscoop
# ✓ Imported 282 authors
# ✓ Created 1 fallback author: "Unknown Author"
# ✓ Logged 283 mappings
```

**Verificare:**
```sql
SELECT COUNT(*) FROM author;
-- Expected: 283 (282 + Unknown)

SELECT name FROM author WHERE slug = 'unknown-author';
-- Expected: "Unknown Author"
```

---

### Ziua 2-3: Import Imagini (8-10 ore BACKGROUND)

**FAZA 4: Import Imagini** (start background, continuă în Ziua 3-4)

```bash
# Start import în background (Terminal dedicat)
nohup symfony console app:import:images \
    --published-only \
    --profile=article_card \
    --batch-size=100 \
    > logs/import_images.log 2>&1 &

# Monitor progress:
tail -f logs/import_images.log

# Output live:
# [2025-01-15 10:00:00] Processing batch 1/1554...
# [2025-01-15 10:00:15] ✓ Image 1-100: imported (avg 6.7 images/sec)
# [2025-01-15 10:00:30] ✓ Image 101-200: imported (avg 6.5 images/sec)
# ...
# [2025-01-15 18:30:00] ✓ Import completed!
# Summary:
#   Total: 155,332 images
#   Imported: 154,800 (99.7%)
#   Deduplicated: 532 (0.3%)
#   Failed: 32 (0.02%)
#   Disk space: 12.77 GB
```

**Verificare intermitentă:**
```bash
# Check progress
symfony console doctrine:query:sql "
  SELECT COUNT(*) as imported_images FROM image
"

# Check thumbnails queue
symfony console messenger:stats
# Expected: ~300,000 messages queued (2 formats × 150k images)
```

**Start thumbnail workers** (4 workers paraleli):
```bash
# Terminal 1-4
symfony console messenger:consume async -vv --limit=10000 &
symfony console messenger:consume async -vv --limit=10000 &
symfony console messenger:consume async -vv --limit=10000 &
symfony console messenger:consume async -vv --limit=10000 &
```

**Durata:** 8-10 ore (rulare background, nu blochează Ziua 3-4)

---

### Ziua 3: Import Articole Newscoop RO (2.5-3 ore)

**FAZA 5.1: Import Newscoop RO** (articole principale)

**Opțiune A: Import serial** (1 worker)
```bash
symfony console app:import:articles \
    --locale=ro \
    --batch-size=50 \
    -vv

# Output live:
# [████████████████████████████████] 144,659/144,659
#
# Summary:
#   Total: 144,659 articles
#   Imported: 144,234 (99.7%)
#   Shortcodes processed: 12,601 (8.7%)
#   Skipped (no content): 425 (0.3%)
#   Failed: 0
#   Duration: 2h 47min
```

**Opțiune B: Import paralel** (4 workers - mai rapid!)
```bash
# Terminal 1
symfony console app:import:articles --locale=ro --offset=0 --limit=36000 &

# Terminal 2
symfony console app:import:articles --locale=ro --offset=36000 --limit=36000 &

# Terminal 3
symfony console app:import:articles --locale=ro --offset=72000 --limit=36000 &

# Terminal 4
symfony console app:import:articles --locale=ro --offset=108000 &

# Wait pentru toate
wait

# Durata: 1h 30min - 2h (paralel)
```

**Verificare:**
```sql
-- Total articole importate (RO)
SELECT COUNT(*) FROM article WHERE locale = 'ro';
-- Expected: ~144,234

-- Articole cu categorii
SELECT COUNT(*) FROM article WHERE category_id IS NOT NULL;
-- Expected: ~143,800 (99.7%)

-- Articole cu autori
SELECT COUNT(DISTINCT aa.article_id)
FROM article_author aa;
-- Expected: ~144,234 (100%)

-- Articole cu featured image
SELECT COUNT(DISTINCT ai.article_id)
FROM article_image ai
WHERE ai.is_featured = true;
-- Expected: ~143,500 (99.5%)
```

---

### Ziua 4: Import Traduceri (1-2 ore)

**FAZA 5.2: Import Traduceri RU + EN**

```bash
# Import traduceri RU
symfony console app:import:translations --locale=ru --batch-size=100

# Output:
# [████████████████████████████████] 27,865/27,865
# ✓ Imported 27,865 RU translations
# Duration: 45 min

# Import traduceri EN
symfony console app:import:translations --locale=en --batch-size=100

# Output:
# [████████████████████████████████] 1,146/1,146
# ✓ Imported 1,146 EN translations
# Duration: 3 min
```

**Verificare:**
```sql
-- Total traduceri în ext_translations
SELECT locale, COUNT(*) as translations
FROM ext_translations
WHERE object_class = 'App\\Entity\\Article'
GROUP BY locale;

-- Expected output:
-- locale | translations
-- -------+-------------
-- ru     | 27,865
-- en     | 1,146

-- Verificare articole trilingve
SELECT COUNT(*) as trilingv FROM (
    SELECT foreign_key
    FROM ext_translations
    WHERE object_class = 'App\\Entity\\Article'
    GROUP BY foreign_key
    HAVING COUNT(DISTINCT locale) = 2  -- ro (implicit) + 2 traduceri = 3 total
) sub;
-- Expected: ~683 (articole cu ro+ru+en)
```

---

### Ziua 5: Import Delta Beta + Webflow (1-1.5 ore)

**FAZA 5.3: Import Delta Beta**

```bash
symfony console app:import:beta-articles --batch-size=50

# Output:
# Fetching articles from Beta API...
# [████████████████████████████████] 8,000/8,000
#
# Deduplication results:
#   Total Beta articles: 8,000
#   Matched (duplicates): 6,200 (77.5%)
#   Imported (new): 1,800 (22.5%)
#   Enriched metadata: 6,200
#
# Summary:
#   New articles imported: 1,800
#   Articles enriched: 6,200
#   Duration: 25 min
```

**FAZA 5.4: Import Delta Webflow**

```bash
symfony console app:import:webflow-articles

# Output:
# Fetching articles from Webflow API...
# [████████████████████████████████] 2,500/2,500
#
# Deduplication results:
#   Total Webflow articles: 2,500
#   Matched (duplicates): 2,000 (80%)
#   Imported (new): 500 (20%)
#   Enriched metadata: 2,000
#
# Summary:
#   New articles imported: 500
#   Articles enriched: 2,000
#   Duration: 12 min
```

**Verificare volume finale:**
```sql
-- Total articole unice
SELECT COUNT(*) FROM article;
-- Expected: ~146,500 (144,234 + 1,800 + 500)

-- Total cu traduceri (database rows)
SELECT
    (SELECT COUNT(*) FROM article) +
    (SELECT COUNT(*) FROM ext_translations WHERE object_class = 'App\\Entity\\Article')
    as total_rows;
-- Expected: ~176,500
```

---

### Ziua 6: Metadata Enrichment + Cleanup (2-3 ore)

**FAZA 5.5: Metadata Enrichment**

```bash
# Enrich din Webflow (prioritate)
symfony console app:enrich:metadata --source=webflow --batch-size=100

# Output:
# Processing 2,000 matched articles...
# [████████████████████████████████] 2,000/2,000
#
# Updated:
#   metaTitle: 1,234 (61.7%)
#   metaDescription: 1,567 (78.4%)
#   keywords (merged): 1,890 (94.5%)
#
# Duration: 18 min

# Enrich din Beta
symfony console app:enrich:metadata --source=beta --batch-size=100

# Output:
# Processing 6,200 matched articles...
# Updated:
#   metaTitle: 892 (14.4%)
#   metaDescription: 1,234 (19.9%)
#   keywords (merged): 3,456 (55.7%)
#
# Duration: 32 min
```

**HTML Cleanup** (optional, dar recomandat)

```bash
symfony console app:articles:cleanup-html --batch-size=100

# Output:
# Processing 146,500 articles...
# [████████████████████████████████] 146,500/146,500
#
# Cleaned:
#   Deprecated tags removed: 87,234 tags
#   Inline styles removed: 123,456 attributes
#   Empty paragraphs removed: 45,678
#   YouTube embeds fixed: 1,234
#   Modified articles: 42,300 (28.9%)
#
# Duration: 1h 20min
```

**Generate missing slugs** (dacă există)

```bash
symfony console app:articles:generate-slugs

# Output:
# Found 0 articles without slug
# ✓ All articles have slugs!
```

---

### Ziua 7: Elasticsearch Indexing (1.5-2 ore)

**Reindex articole**

```bash
symfony console app:elasticsearch:index-articles --force --batch-size=500

# Output:
# Creating indices (ro, en, ru)...
# ✓ Index created: deschide_articles_ro
# ✓ Index created: deschide_articles_en
# ✓ Index created: deschide_articles_ru
#
# Indexing articles...
# [████████████████████████████████] 146,500/146,500
#
# Summary:
#   Total articles: 146,500
#   Indexed (ro): 144,234
#   Indexed (ru): 1,800
#   Indexed (en): 466
#   Failed: 0
#   Duration: 1h 47min
```

**Reindex imagini**

```bash
symfony console app:elasticsearch:index-images --force

# Output:
# [████████████████████████████████] 155,332/155,332
# ✓ Indexed 155,332 images
# Duration: 35 min
```

---

### Ziua 8: Verificare Finală + Post-Validation (2-3 ore)

**FAZA 6: Post-Validare Completă**

```bash
symfony console app:post-validate

# Output complet:
#
# ========================================
# POST-IMPORT VALIDATION REPORT
# ========================================
#
# 1. ARTICOLE
# ----------------------------------------
# ✓ Total articles imported: 146,500
# ✓ Articles with category: 145,900 (99.6%)
# ⚠ Articles without category: 600 (0.4%)
# ✓ Articles with author: 146,500 (100%)
# ✓ Articles with featured image: 145,800 (99.5%)
# ⚠ Articles without featured image: 700 (0.5%)
#
# 2. TRADUCERI
# ----------------------------------------
# ✓ Total translations (RU): 27,865
# ✓ Total translations (EN): 1,146
# ✓ Trilingve articles: 683
# ✓ Bilingve articles: 19,781
# ✓ Monolingve articles: 126,036
#
# 3. SHORTCODES
# ----------------------------------------
# ✓ Articles processed: 13,323 (9.1%)
# ✓ Image shortcodes mapped: 24,567
# ✓ Link shortcodes mapped: 4,890
# ⚠ Unmapped image shortcodes: 123 (0.5%)
# ⚠ Unmapped link shortcodes: 56 (1.1%)
#
# 4. IMAGINI
# ----------------------------------------
# ✓ Total images imported: 155,332
# ✓ Images with thumbnails: 154,800 (99.7%)
# ✓ Article-image relationships: 223,780
# ⚠ Missing thumbnails: 532 (0.3% - queued retry)
# ✗ Failed thumbnails: 32 (0.02%)
#
# 5. RELAȚII
# ----------------------------------------
# ✓ Categories mapped: 43/43 (100%)
# ✓ Authors mapped: 282/282 (100%)
# ✓ Images mapped: 155,300/155,332 (99.98%)
# ⚠ Orphaned articles: 0
# ⚠ Orphaned images: 0
#
# 6. ELASTICSEARCH
# ----------------------------------------
# ✓ Indexed articles (ro): 144,234
# ✓ Indexed articles (ru): 1,800
# ✓ Indexed articles (en): 466
# ✓ Indexed images: 155,332
# ✗ Index errors: 0
#
# 7. DISK USAGE
# ----------------------------------------
# ✓ Images (originals): 12.77 GB
# ✓ Thumbnails (JPG): 12.77 GB
# ✓ Thumbnails (WebP): 10.21 GB
# ✓ Total disk: 35.75 GB (vs 88 GB original = 59% economie)
#
# ========================================
# OVERALL STATUS: ✅ SUCCESS (99.6% data integrity)
# ========================================
#
# Warnings to review:
#   - 600 articles without category → Run: app:articles:fix-missing-categories
#   - 700 articles without featured image → Manual review
#   - 532 images without thumbnails → Retry: app:thumbnails:retry-failed
#   - 123 unmapped image shortcodes → Check newscoop_migration_log
#
# Recommendations:
#   1. Run fix-missing-categories command
#   2. Retry failed thumbnails
#   3. Review unmapped shortcodes manually (123 cases)
#   4. Backup database (post-import)
#   5. Monitor Elasticsearch queries
```

**Fix warnings:**

```bash
# 1. Fix categorii lipsă
symfony console app:articles:fix-missing-categories
# ✓ Fixed 600 articles (set category = 'Social')

# 2. Retry failed thumbnails
symfony console app:thumbnails:retry-failed
# ✓ Retried 532 images
# ✓ Success: 500 (94%)
# ✗ Failed: 32 (6% - corrupt source images)

# 3. Review unmapped shortcodes
symfony console app:articles:list-unmapped-shortcodes > unmapped_shortcodes.txt
# Manual review necesară (123 cases)
```

**Backup final:**

```bash
# Database backup (post-import)
pg_dump -h localhost -U deschide_admin deschide_news > backup_post_import_$(date +%Y%m%d).sql

# Compress
gzip backup_post_import_*.sql

# Size check
ls -lh backup_*.sql.gz
# Expected: ~2-3 GB (compressed)
```

**Verificare frontend** (manual):

```bash
# Start frontend dev server
cd /var/www/deschide_news_app/deschide_frontend
pnpm dev

# Browse:
# - http://localhost:3005 (homepage)
# - http://localhost:3005/ro/politic (categorie)
# - http://localhost:3005/ro/articole/[slug] (articol random)
# - Check images loading
# - Check translations (ro, ru, en)
```

---

## 📊 REZUMAT FINAL - Timeline & Statistici

### Timp Total Import

| Fază | Descriere | Durata | Tip |
|------|-----------|--------|-----|
| **Ziua 0** | Pregătire + NewscoopContentProcessor | 2-3h | Active |
| **Ziua 1** | Categorii + Autori | 1-1.5h | Active |
| **Ziua 2-3** | Imagini (start background) | 8-10h | **Background** |
| **Ziua 3** | Articole Newscoop RO | 2.5-3h | Active |
| **Ziua 4** | Traduceri RU + EN | 1-2h | Active |
| **Ziua 5** | Delta Beta + Webflow | 1-1.5h | Active |
| **Ziua 6** | Enrichment + Cleanup | 2-3h | Active |
| **Ziua 7** | Elasticsearch indexing | 1.5-2h | Active |
| **Ziua 8** | Post-validare + Fix | 2-3h | Active |
| **TOTAL** | **14-20 ore active + 8-10h background** | **22-30h** | **~4-5 zile** |

**Note:**
- **Imagini rulează în background** (Ziua 2-3) paralel cu alte faze
- **Workers paraleli** pot reduce timpul articole cu 30-40%
- **Total timp real**: 4-5 zile lucrătoare (8h/zi)

### Volume Finale

| Metric | Valoare |
|--------|---------|
| **Articole unice** | ~146,900 |
| **Traduceri** | ~29,600 (RU + EN) |
| **Total database rows** | ~176,500 |
| **Imagini** | 155,332 |
| **Thumbnails** | 309,600 (2 formats) |
| **Categorii** | 18-20 |
| **Autori** | 283 |
| **Relații article-image** | 223,780 |

### Disk Usage

| Component | Spațiu | vs Original |
|-----------|--------|-------------|
| **Imagini (originals)** | 12.77 GB | -75.23 GB (-85.5%) |
| **Thumbnails JPG** | 12.77 GB | - |
| **Thumbnails WebP** | 10.21 GB | - |
| **TOTAL** | **35.75 GB** | **-52.25 GB (-59.4%)** 💾 |

**Original Newscoop**: 88 GB
**După import optimizat**: 35.75 GB
**Economie**: **52.25 GB (59.4%)**

---

## ❓ ÎNTREBĂRI PENTRU DECIZIE FINALĂ

### 1. Care strategie de import preferi? ⭐

- 🅰️ **Opțiunea 1: ARHIVĂ COMPLETĂ** (Newscoop + Delta Beta/Webflow)
  - ~147,000 articole unice
  - 2016-2024 (9 ani arhivă completă)
  - 5-7 ore import active
  - **← RECOMANDAT** ⭐

- 🅱️ **Opțiunea 2: RECENT + SELECTIV** (2020+)
  - ~11,500 articole
  - 2020-2024 (4 ani)
  - 2-3 ore import

- 🅲️ **Opțiunea 3: WEBFLOW ONLY**
  - ~2,500 articole
  - 2022-2024 (2 ani)
  - 30-45 min import
  - ⚠️ **NU RECOMANDAT** (site gol)

- 🅳️ **Opțiunea 4: NEWSCOOP ONLY**
  - ~152,500 articole
  - 2016-2024 (9 ani)
  - 2.5-3 ore import
  - ⚠️ Fără enrichment metadata

---

### 2. Draft articles - le importăm?

- ✅ **DA** - Import și drafts (Published='N')
  - +~2,000 articole (volume mai mare)
  - Conținut potențial util de finalizat

- ⛔ **NU** - Doar published (Published='Y')
  - Doar conținut finalizat și publicat
  - Volume mai mici
  - **← RECOMANDAT** ⭐

---

### 3. Articole fără conținut (FContinut=NULL)?

- ✅ **Import oricum** (doar title + lead)
  - Păstrează toate datele

- ⛔ **Skip** - Exclude articole incomplete
  - Doar articole complete
  - **← RECOMANDAT** ⭐

---

### 4. HTML cleanup - când?

- 🅰️ **În timpul importului** (real-time cleanup)
  - Import mai lent (+10-15%)
  - Gata imediat

- 🅱️ **După import** (batch cleanup command)
  - Import rapid
  - Cleanup separat (1-2h)
  - **← RECOMANDAT** ⭐

---

### 5. Deduplicare - cât de strictă?

- 🅰️ **Strictă** (title exact + date exact)
  - Risc duplicates (~100-200 articole)

- 🅱️ **Relaxată** (title normalizat + date ±24h)
  - Deduplicare bună
  - **← RECOMANDAT** ⭐

- 🅲️ **Cu content similarity** (80%+ match)
  - Deduplicare maximă
  - Risc false positives
  - Timp procesare +30%

---

### 6. Workers paraleli - câți?

- **2 workers** (safe, no race conditions)
- **4 workers** (recomandat) **← RECOMANDAT** ⭐
- **8 workers** (foarte rapid, risc race conditions)

---

### 7. Elasticsearch indexing - când?

- ⚠️ **În timpul importului** (real-time)
  - Import FOARTE lent (+100% timp)

- ✅ **După import** (batch reindex)
  - Import rapid
  - Reindex odată la final (1.5-2h)
  - **← RECOMANDAT** ⭐

---

### 8. Imagini - ce profil thumbnails?

- 🅰️ **Doar article_card** (640×427)
  - Spațiu minim (12.77 GB)
  - **← RECOMANDAT pentru MVP** ⭐

- 🅱️ **3 profile** (card + hero + wide)
  - Spațiu mediu (~35 GB)
  - Mai flexibil

- 🅲️ **6 profile complete**
  - Spațiu mare (~60 GB)
  - Maxim flexibilitate

---

## 📝 Next Steps

După confirmarea opțiunilor, voi crea:

1. ✅ **Plan detaliat execuție** - COMPLETAT (acest document)
2. ⬜ **Comenzi Symfony noi** (Beta, Webflow import) - DE IMPLEMENTAT
3. ⬜ **NewscoopContentProcessor service** - PRIORITATE MAXIMĂ (2-3h)
4. ⬜ **Scripts verificare** (duplicates, missing data) - DE IMPLEMENTAT
5. ⬜ **Documentation** comenzi - DE ACTUALIZAT
6. ⬜ **Checklist import** (pas cu pas) - DE CREAT

---

**Aștept confirmarea opțiunilor pentru a continua cu implementarea!** 🚀

---

**Autor:** Claude Code
**Data:** 2025-11-09
**Status:** ⏳ Așteptăm decizie - Strategie Import Complet Articole
**Bazat pe:**
- `categories_complete_import_strategy.md`
- `migration_strategy/PUBLISHED_ARTICLES_ANALYSIS.md`
- `migration_strategy/IMAGE_IMPORT_ANALYSIS.md`
- `migration_strategy/MIGRATION_SUMMARY.md`
- Comenzi existente în `deschide_backend/src/Command/Import/`
