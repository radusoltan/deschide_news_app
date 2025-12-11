# Rezumat Plan Migrare Newscoop → news_app

**Data:** 2025-10-25
**Status:** ✅ Plan finalizat, așteaptă aprobare pentru implementare

---

## 📊 Volumul de Date

### Volum Total vs Volum de Import

| Entitate | Total Newscoop | **Import (Published='Y')** ✅ | Diferență |
|----------|----------------|-------------------------------|-----------|
| **Articles (stiri - total records)** | 175,626 | **173,670** | -1,956 (-1.1%) |
| **Unique articles** | 154,291 | **152,523** ✅ | -1,768 (-1.15%) |
| **Translations** | 21,335 | **21,147** | -188 (-0.9%) |
| **Articles cu shortcodes** | 16,182 (9.2%) | **13,323 (8.73%)** | -2,859 (-17.7%) |
| Categories (Sections) | 43 rânduri | ~15-20 (grupate) | - |
| Authors | 282 | ~283 (+ Unknown) | - |
| **Images (total)** | 165,302 | - | - |
| **Images (folosite Published='Y')** | **155,332 (93.96%)** | **155,332** ✅ | -9,970 (-6%) |
| **Spațiu disk imagini** | 88 GB | **12.77 GB (640×427)** | **-75.23 GB (-85.5%)** 💾 |
| Related Articles | 3,807 relații | ~3,800 | - |
| Languages | 3 (en, ro, ru) | 3 | - |

⚠️ **CRITERII DE IMPORT:** `Type = 'stiri'` + `Published = 'Y'` (doar articole publicate)

⚠️ **DESCOPERIRE CRITICĂ #1:** 13,323 articole conțin shortcodes Newscoop care TREBUIE procesate!

⚠️ **DESCOPERIRE CRITICĂ #2:** 21,147 articole au traduceri (13.8%) care TREBUIE importate corect!

### Distribuție Traduceri (Published='Y'):
- **Monolingve:** 132,059 articole (86.58%) - Doar o limbă
- **Bilingve:** 19,781 articole (12.97%) - 2 limbi (majoritatea ro+ru)
- **Trilingve:** 683 articole (0.45%) - 3 limbi (ro+ru+en)

**Limba dominantă:** Română (83.30% din conținut Published='Y')

### Distribuție pe Limbi (Published='Y'):
- **🇷🇴 Română:** 144,659 articole (83.30%)
- **🇷🇺 Rusă:** 27,865 articole (16.04%)
- **🇬🇧 Engleză:** 1,146 articole (0.66%)

---

## ✅ Decizii Luate

### 1. **Related Articles** ✅ DECISĂ și GATA
**Opțiunea aleasă:** Minimal (Sorting automat)

- **Status:** ✅ **95% Implementat** - Infrastructură completă
- Folosim `ManyToMany` existent din news_app (tabelă `article_related`)
- Entity `Article` are deja `relatedArticles` Collection
- Metode `addRelatedArticle()` / `removeRelatedArticle()` implementate
- Ignorăm `order_number` din Newscoop (arhivă, toate = 0)
- **Lipsește:** Doar logica de import în ImportArticlesCommand
- **Avantaje:** Zero modificări schema, simplu, rapid
- **Timp implementare:** ~30 min (doar import logic)

### 2. **Relația Article-Author** ✅ DECISĂ și IMPLEMENTATĂ
**Opțiunea aleasă:** ManyToMany (Opțiunea C)

- Refactorizare completă Article ↔ Author la ManyToMany
- Tabela junction `article_author` creată
- Suport complet pentru autori multipli per articol
- Backward compatibility via `getPrimaryAuthor()`
- **Status:** ✅ **IMPLEMENTAT** (Version20251025213637)
- **Timp implementare:** 1h (mai rapid decât estimarea inițială de 3-4h)

### 3. **Content Processing** ✅ DECISĂ - ⚠️ OBLIGATORIE!
**Opțiunea aleasă:** Parse & Transform (NewscoopContentProcessor service)

- **13,323 articole** (8.73% din Published='Y') conțin shortcodes `<!** Image [id] ...>`
- Transformare shortcodes → HTML modern (`<figure><img><figcaption>`)
- Regex processing pentru 4 tipuri shortcodes (Image, Internal Link, Title, Snippet)
- Mapare Newscoop IDs → news_app IDs via `newscoop_id_mapping`
- **FĂRĂ ACEASTA:** 13k+ articole vor avea imagini BROKEN!
- **Timp implementare:** 2-3 ore (service) + 15% overhead Faza 5
- **Detalii:** Vezi `CONTENT_PROCESSING_ANALYSIS.md`

### 4. **Tipuri Articole + Status** ✅ DECISĂ
**Opțiunea aleasă:** Import doar `Type = 'stiri'` + `Published = 'Y'`

- **Filtrare:** Doar articole publicate (excludem draft-uri și submitted)
- **Volum:** 152,523 articole unice (98.85% din total tip 'stiri')
- **Traduceri:** 21,147 înregistrări suplimentare
- **Total înregistrări:** 173,670
- **Excluse:** 1,768 articole (1.15%) = draft-uri (N) + submitted (S)
- **Justificare:**
  - Focus pe conținut publicat și complet
  - 98.85% coverage = pierdere minimă
  - Articole nepublicate = conținut incomplet sau învechit
- **Timp migrare:** ~2.5-3 ore (10-15 min mai rapid decât estimarea inițială)
- **Detalii:** Vezi `PUBLISHED_ARTICLES_ANALYSIS.md` ⭐ NOU!

---

### 5. **Imagini** ✅ DECISĂ
**Opțiunea aleasă:** Import doar imagini folosite în articole Published='Y', resize 640×427 optimizat

- **Filtrare:** Doar imagini din `ArticleImages` pentru articole Published='Y'
- **Volum:** 155,332 imagini (93.96% din total, 100% coverage articole)
- **Profil unic:** article_card (640×427, aspect ratio 3:2)
- **Optimizare:** JPEG quality 85% + WebP format
- **Spațiu disk:** 12.77 GB (vs 88 GB original = **85.5% economie**) 💾
- **Dimensiune medie:** ~86 KB/imagine (vs ~533 KB original)
- **Justificare:**
  - Coverage perfect: 99.8% articole au imagini
  - Spațiu minim: 12.77 GB vs 88 GB (economie masivă)
  - Quality suficient: 640×427 @ 85% = optim pentru web (cards, thumbnails)
  - Consistență: doar pentru articole Published='Y'
  - Performance: ~86 KB = încărcare rapidă
- **Timp import:** ~8.6 ore (background, paralel cu Faza 5)
- **Detalii:** Vezi `IMAGE_IMPORT_ANALYSIS.md` ⭐ NOU!

---

## ⏳ Decizii în Așteptare

**Status:** ✅ **TOATE DECIZIILE MAJORE LUATE!**

Decizii minore rămase (opționale, nu blochează migrarea):
- Secțiuni duplicate (verificare în Faza 1)
- Author fields suplimentare (skype/jabber - probabil skip)

---

### 2. **Secțiuni Duplicate**
**Necesită verificare:** Sections cu același Name în limbi diferite

**Query verificare:**
```sql
SELECT Name, GROUP_CONCAT(DISTINCT IdLanguage) as languages, COUNT(*) as count
FROM Sections
GROUP BY Name
HAVING count > 1;
```

**Dacă `count > 1`:**
- [ ] Sunt traduceri → merge în news_app ca o categorie cu ext_translations
- [ ] Sunt categorii separate → import ca entități distincte

**Acțiune:** Rulare query în Faza 1 (Validare) → decizie după rezultate

---

## 📋 Structura Plan (6 Faze)

| Fază | Durata | Descriere |
|------|--------|-----------|
| **0. Pregătire** | 1-2h | Setup conexiuni, servicii, migration_log table |
| **0.1. NewscoopContentProcessor** | **2-3h** | **⚠️ CRITIC! Implementare service shortcode processing** |
| **1. Validare** | 30min | Statistici complete, verificare Sections duplicate |
| **2. Categorii** | 15min | Import 43 sections → ~15-20 categories |
| **3. Autori** | 20min | Import 282 authors (+ Unknown fallback) |
| **4. Imagini** | 6-10h | Import 165k imagini + async thumbnails |
| **5. Articole + Processing** | **2.8-3.5h** | Import 154k unique articles + **21k translations** + **shortcode processing** |
| **6. Post-Validare + Check** | **45min** | Verificare integritate + **shortcode + translation validation** |

**Total timp:** **14-20 ore** (7-10h active + 6-10h background)
**+3 ore** față de planul inițial pentru procesare shortcodes - **OBLIGATORIU!**

**Optimizare:** Rulare Faza 4 (imagini) în background în timp ce rulezi Faza 5

---

## 🎯 Recomandări Finale

### Pentru Start Rapid (MVP):

✅ **Decizii luate:**
1. **Related Articles:** Opțiunea 1 - Minimal ✅ DECISĂ (95% implementat)
2. **Content Processing:** NewscoopContentProcessor service ✅ DECISĂ - **OBLIGATORIE!**
3. **Translation Import:** Gedmo Translatable cu ext_translations ✅ **OBLIGATORIU!**
4. **Article-Author:** Opțiunea C - ManyToMany ✅ **IMPLEMENTAT!**
5. **Tipuri + Status:** Doar `Type='stiri' + Published='Y'` ✅ **DECISĂ!**
6. **Sections:** Analizate - 18 secțiuni unice → import 15-20 categorii ✅ DECISĂ
7. **Imagini:** Doar folosite, resize 640×427 optimizat ✅ **DECISĂ!**

✅ **Status decizii:** **7/7 COMPLETE** - Gata pentru implementare! 🚀

✅ **Modificări necesare în news_app:**
1. **NewscoopContentProcessor service** - **2-3 ore** ⚠️ **PRIORITATE MAXIMĂ!**
2. ~~Article-Author: refactorizare ManyToMany~~ - ✅ **COMPLETAT!** (1h)
3. **Total modificări rămase:** **2-3 ore** (doar content processor)

✅ **Timp total execuție (ACTUALIZAT FINAL):**
- Pregătire + modificări: **2-3 ore** (doar NewscoopContentProcessor - ManyToMany deja implementat!)
- Execuție migrare: **10-15 ore** (majoritatea background)
  - Faza 1-3: ~1h (validare + categorii + autori)
  - Faza 4: ~8.6h (155k imagini resize, **background**)
  - Faza 5: ~2.5-3h (152k articole + traduceri + shortcodes)
  - Faza 6: ~45min (post-validare)
- **Total: ~12-18 ore** (2-3 zile lucru efectiv)
- **Beneficii optimizări:**
  - Published='Y': -10-15 min (mai puține articole)
  - Imagini optimizate: **-75 GB disk space** (85.5% economie) 💾
  - Profil unic: -70% timp thumbnails

---

## 🚀 Next Steps

### Pas 1: Finalizare Decizii ✅ COMPLETAT 100%
- [x] ~~**Article-Author:** A, B sau C?~~ ✅ **IMPLEMENTAT - Opțiunea C (ManyToMany)**
- [x] ~~**Tipuri articole:** Doar stiri sau toate?~~ ✅ **DECISĂ - Doar Type='stiri' + Published='Y'**
- [x] ~~**Related Articles:** Opțiune 1, 2 sau 3?~~ ✅ **DECISĂ - Opțiunea 1 (Minimal)**
- [x] ~~**Sections:** Analiză necesară~~ ✅ **ANALIZATĂ - 18 secțiuni unice**
- [x] ~~**Imagini:** Complet, metadata sau folosite?~~ ✅ **DECISĂ - Folosite + resize 640×427**

### Pas 2: Implementare Infrastructură
```bash
# Setup conexiuni dual DB
- config/packages/doctrine.yaml (PostgreSQL + MariaDB)
- .env.local (NEWSCOOP_DATABASE_URL)

# Creare servicii
- NewscoopContentProcessor (PRIORITATE!)
- NewscoopDatabaseService
- NewscoopMapperService
- MigrationLoggerService

# Creare migration_log table
```

### Pas 3: Implementare Comenzi (Faze 1-6)
```bash
src/Command/Newscoop/
├── ValidateCommand.php
├── ImportCategoriesCommand.php
├── ImportAuthorsCommand.php
├── ImportImagesCommand.php
├── ImportArticlesCommand.php
└── PostValidateCommand.php
```

### Pas 4: Testing Incremental
```bash
# Test fiecare fază pe sample mic
php bin/console app:newscoop:validate
php bin/console app:newscoop:import:categories --dry-run
php bin/console app:newscoop:import:authors --dry-run --limit=10
php bin/console app:newscoop:import:images --dry-run --limit=100
php bin/console app:newscoop:import:articles --dry-run --limit=50
```

### Pas 5: Execuție Completă
```bash
# Pe DB clonată (staging)
php bin/console app:newscoop:validate
php bin/console app:newscoop:import:categories
php bin/console app:newscoop:import:authors
php bin/console app:newscoop:import:images --batch-size=100 &  # Background
php bin/console app:newscoop:import:articles --batch-size=50
php bin/console app:newscoop:post-validate
```

---

## 📁 Documentație Disponibilă

1. **NEWSCOOP_MIGRATION_PLAN.md** - Plan detaliat complet (1800+ linii)
   - Structura Newscoop vs news_app
   - Mapare entități
   - Pseudo-cod pentru fiecare fază
   - NewscoopContentProcessor implementation completă
   - Translation processing logic
   - Exemple query SQL
   - Output așteptat pentru fiecare comandă

2. **PUBLISHED_ARTICLES_ANALYSIS.md** - Analiza articole publicate
   - Criterii import: Type='stiri' + Published='Y'
   - Statistici: 152,523 articole unice (98.85% coverage)
   - Distribuție limbi: 83.3% ro, 16% ru, 0.66% en
   - Distribuție traduceri: 86.6% monolingve, 13.4% bilingve/trilingve
   - Shortcodes: 13,323 articole (8.73%)
   - Distribuție temporală: 2016-2024 (9 ani)
   - Impact timp migrare: -10-15 minute

3. **IMAGE_IMPORT_ANALYSIS.md** - Analiza import imagini ⭐ NOU!
   - Criterii: Doar imagini folosite în articole Published='Y'
   - Volum: 155,332 imagini (93.96% coverage, 99.8% articole au imagini)
   - Profil: article_card (640×427, quality 85%, JPG+WebP)
   - Spațiu disk: 12.77 GB (vs 88 GB = 85.5% economie)
   - Dimensiune medie: ~86 KB/imagine (vs ~533 KB original)
   - Distribuție: 90% articole cu 1 imagine, medie 1.47 imagini/articol
   - Format: 92% JPEG, 8% PNG, 0.03% GIF
   - Timp import: 8.6 ore (background)

4. **CONTENT_PROCESSING_ANALYSIS.md** - Analiza procesare shortcodes
   - Identificare 4 tipuri shortcodes (Image, Link, Title, Snippet)
   - Regex patterns pentru parsing
   - Impact: 13,323 articole (8.73% din Published='Y')
   - Strategie transformare HTML5

5. **TRANSLATION_ANALYSIS.md** - Analiza traduceri multilingual
   - Statistici complete: 21,147 traduceri (13.8% din Published='Y')
   - Distribuție: 132k monolingve, 19.7k bilingve, 683 trilingve
   - Limba dominantă: Română (83.30%)
   - Strategia de import Gedmo Translatable
   - Query-uri de verificare post-migrare
   - Checklist validare traduceri

6. **RELATED_ARTICLES_ANALYSIS.md** - Analiza related articles
   - Comparație Newscoop vs news_app
   - 3 opțiuni implementare
   - Opțiunea 1 aleasă (Minimal) ✅ IMPLEMENTATĂ
   - Status: 95% gata, lipsește doar import logic

7. **SECTIONS_ANALYSIS.md** - Analiza secțiuni (categorii)
   - 43 rânduri Newscoop → 18 secțiuni unice
   - 10 secțiuni cu 3 limbi (EN+RO+RU)
   - Inconsistențe identificate (video/opinii, duplicate)
   - Recomandări mapare: 15-18 categorii în news_app
   - Top 3 secțiuni = 76.8% articole

8. **MIGRATION_SUMMARY.md** (acest document) - Rezumat executiv
   - **7/7 decizii majore luate** ✅
   - Volum final: 152k articole + 155k imagini
   - Timp total: 12-18 ore
   - Economie spațiu: 75 GB (85.5%)
   - Ready pentru implementare! 🚀

---

## ✅ Checklist Pre-Implementare

### Decizii Strategice (7/7 Complete)
- [x] **Article-Author:** ManyToMany ✅ IMPLEMENTAT
- [x] **Tipuri + Status:** Type='stiri' + Published='Y' ✅
- [x] **Related Articles:** Opțiunea 1 (Minimal) ✅
- [x] **Sections:** 18 secțiuni → 15-18 categorii ✅
- [x] **Content Processing:** NewscoopContentProcessor ✅
- [x] **Translations:** Gedmo Translatable ✅
- [x] **Imagini:** Folosite + resize 640×427 ✅

### Analize Complete (8/8)
- [x] Newscoop structure analysis
- [x] Published articles analysis (152k)
- [x] Images usage analysis (155k)
- [x] Sections analysis (18 unique)
- [x] Related articles analysis
- [x] Content processing analysis (13k shortcodes)
- [x] Translation analysis (21k)
- [x] Migration summary

### Modificări Necesare (1/2)
- [x] ~~Article-Author ManyToMany~~ ✅ COMPLETAT
- [ ] NewscoopContentProcessor service ⚠️ **2-3 ore - SINGURA TASK RĂMASĂ**

---

## 🚀 Ready pentru Implementare!

**Status:** ✅ **TOATE DECIZIILE LUATE, ANALIZE COMPLETE**

**Poate începe implementarea când ești gata:**
1. Implementare NewscoopContentProcessor (2-3 ore)
2. Rulare migrare (12-18 ore, majoritatea background)

**Next Step:** Implementare Faza 0 (Pregătire Infrastructură)

---

**Planificare finalizată: 2025-10-26** 📋
**Gata pentru execuție!** 🚀
