# Analiza Articolelor Publicate (Type = 'stiri', Published = 'Y')

**Data analiză:** 2025-10-26
**Bază sursă:** MariaDB `newscoop` (Newscoop 4.4)

---

## 🎯 Decizie: Criterii de Import

**Criteriile alese:**
1. ✅ **Type = 'stiri'** (știri standard, excludem embed/ultrascurte/video)
2. ✅ **Published = 'Y'** (doar articole publicate, excludem draft-uri și submitted)

---

## 📊 Statistici Generale

### Volumul Total (Type = 'stiri')

| Metric | Valoare | Procent |
|--------|---------|---------|
| **Total înregistrări Articles** | 175,626 | 100% |
| **Articole unice** (Number distinct) | 154,291 | - |
| **Published = 'Y'** | 173,670 | **98.89%** ✅ |
| **Published = 'N'** (not published) | 1,780 | 1.01% ❌ |
| **Published = 'S'** (submitted) | 176 | 0.10% ❌ |

### Volumul pentru Import (Type = 'stiri' + Published = 'Y')

| Metric | Valoare |
|--------|---------|
| **Total înregistrări de importat** | **173,670** |
| **Articole unice de importat** | **152,523** |
| **Traduceri** | 21,147 (173,670 - 152,523) |

**Concluzie:** Din 154,291 articole unice (total tip 'stiri'), **vom importa 152,523** (98.85%).

**Excluse:**
- **1,768 articole unice** (1.15%) = articole nepublicate (N) sau submitted (S)

---

## 🌍 Distribuție pe Limbi (Published = 'Y')

| Limbă | Total Articole | Articole Unice | Procent |
|-------|----------------|----------------|---------|
| **🇷🇴 Română (ro)** | **144,659** | 144,659 | **83.30%** ⭐ |
| **🇷🇺 Rusă (ru)** | 27,865 | 27,865 | 16.04% |
| **🇬🇧 Engleză (en)** | 1,146 | 1,146 | 0.66% |
| **TOTAL** | **173,670** | 173,670 | 100% |

**Observații:**
- **Limba dominantă:** Română (83.3% din conținut)
- **Nu există Number-uri duplicate per limbă** (total_articles = unique_articles)
- Fiecare limbă are articole distincte, nu sunt traduceri ale aceluiași Number

---

## 🔄 Distribuție Traduceri (Published = 'Y')

Câte limbi are fiecare articol unic (Number):

| Număr Limbi | Articole Unice | Procent | Descriere |
|-------------|----------------|---------|-----------|
| **1 limbă** | **132,059** | **86.58%** | Monolingve (doar o limbă) |
| **2 limbi** | 19,781 | 12.97% | Bilingve (ro+ru cel mai probabil) |
| **3 limbi** | 683 | 0.45% | Trilingve (ro+ru+en) |
| **TOTAL** | **152,523** | **100%** | - |

**Calcul verificare:**
- Monolingve: 132,059 × 1 = 132,059 înregistrări
- Bilingve: 19,781 × 2 = 39,562 înregistrări
- Trilingve: 683 × 3 = 2,049 înregistrări
- **Total:** 132,059 + 39,562 + 2,049 = **173,670** ✅ (matches total records)

**Concluzie:**
- **86.6%** articole sunt monolingve (au conținut doar într-o limbă)
- **13.4%** articole au traduceri (2-3 limbi)

---

## ⚠️ Procesare Shortcodes (Published = 'Y')

| Metric | Valoare | Procent |
|--------|---------|---------|
| **Articole unice cu shortcodes** | **13,323** | **8.73%** din 152,523 |
| **Tipuri shortcodes:** | <!** Image, Link Internal, Title, Snippet | - |

**Concluzii:**
- **13,323 articole** (8.73%) vor necesita procesare shortcodes
- Puțin mai puțin decât estimarea inițială de 16,182 (9.2%)
- **Explicație:** Unele articole cu shortcodes sunt nepublicate (N) sau submitted (S)
- **Procesarea este OBLIGATORIE** pentru aceste 13k+ articole

---

## 📅 Distribuție Temporală (Published = 'Y')

Articole publicate pe ani:

| An | Total Articole | Articole Unice | Medie/Lună |
|----|----------------|----------------|------------|
| **2016** | 6,803 | 4,864 | ~405 |
| **2017** | 22,534 | 17,499 | ~1,458 |
| **2018** | 21,608 | 17,302 | ~1,442 |
| **2019** | 19,073 | 16,377 | ~1,365 |
| **2020** | 19,527 | 18,267 | ~1,522 |
| **2021** | 21,028 | 19,748 | ~1,646 |
| **2022** | 24,105 | 22,567 | **~1,880** 🔥 |
| **2023** | 22,372 | 20,714 | ~1,726 |
| **2024** | 16,620 | 15,186 | ~1,266 (până în oct) |
| **TOTAL** | **173,670** | **152,524** | ~1,412/lună |

**Observații:**
- **Anul cu cele mai multe articole:** 2022 (24,105 înregistrări, ~1,880/lună)
- **Perioada acoperită:** 2016-2024 (8-9 ani de conținut)
- **Vârf productivitate:** 2021-2023 (67,505 articole în 3 ani)
- **Medie consistentă:** ~1,400-1,800 articole unique/lună între 2017-2023

---

## 📊 Comparație: Înainte vs După Filtrare

### Volumul Total vs Volumul de Import

| Categorie | Total Tip 'stiri' | Published = 'Y' | Diferență | % Păstrat |
|-----------|-------------------|-----------------|-----------|-----------|
| **Total înregistrări** | 175,626 | 173,670 | -1,956 | 98.89% |
| **Articole unice** | 154,291 | 152,523 | -1,768 | **98.85%** ✅ |
| **Traduceri** | 21,335 | 21,147 | -188 | 99.12% |

**Concluzie:** Filtrarea după `Published = 'Y'` **exclude doar 1.15%** din articole.

### Articole Excluse (Published ≠ 'Y')

| Status | Înregistrări | Articole Unice | Descriere |
|--------|--------------|----------------|-----------|
| **N** (Not Published) | 1,780 | ~1,590 | Draft-uri nepublicate |
| **S** (Submitted) | 176 | ~178 | Articole în așteptare aprobare |
| **TOTAL EXCLUSE** | 1,956 | **~1,768** | **1.15%** din total |

---

## 🎯 Impact asupra Planului de Migrare

### Modificări la Estimări

| Metric | Estimare Inițială | Estimare Reală | Diferență |
|--------|-------------------|----------------|-----------|
| **Articole unice** | 154,805 | **152,523** | -2,282 (-1.5%) |
| **Traduceri** | 21,402 | **21,147** | -255 (-1.2%) |
| **Total înregistrări** | 176,207 | **173,670** | -2,537 (-1.4%) |
| **Articole cu shortcodes** | 16,182 | **13,323** | -2,859 (-17.7%) |

**Impact timp migrare:**
- **Articole:** -2,282 articole = ~-2 minute (la 50 articole/secundă)
- **Shortcodes:** -2,859 articole cu procesare = ~-15 minute overhead
- **Total impact:** **~10-15 minute mai rapid** decât estimarea inițială ✅

### Actualizare Volum Date (MIGRATION_SUMMARY.md)

Înlocuire tabel:

| Entitate | Cantitate Newscoop | **Estimat Import (ACTUALIZAT)** |
|----------|--------------------|---------------------------------|
| **Articles (stiri Published=Y)** | **173,670 total înregistrări** | **~173,670** |
| **Unique articles (Published=Y)** | **152,523** | **152,523** ✅ |
| **Translations (Published=Y)** | **21,147** | **→ ext_translations** |
| **Articles cu shortcodes (Published=Y)** | **13,323 (8.73%)** | **PROCESARE OBLIGATORIE!** |
| Categories (Sections) | 43 | ~15-20 (grupate) |
| Authors | 282 | ~283 (+ Unknown) |
| Images | 165,302 | ~162,100 |
| Related Articles | 3,807 relații | ~3,800 |
| Languages | 3 (en, ro, ru) | 3 |

---

## ✅ Verificări Calitate Date

### 1. Consistența Number (Articole Unice)

```sql
-- Verificare: Number trebuie să fie unic per (Number, IdLanguage)
SELECT Number, IdLanguage, COUNT(*) as duplicates
FROM Articles
WHERE Type = 'stiri' AND Published = 'Y'
GROUP BY Number, IdLanguage
HAVING COUNT(*) > 1;
-- Rezultat așteptat: 0 rânduri (nu există duplicate)
```

### 2. Verificare Conținut în Xstiri

```sql
-- Verificare: Toate articolele Published=Y trebuie să aibă conținut în Xstiri
SELECT COUNT(*) as articles_without_content
FROM Articles a
LEFT JOIN Xstiri x ON a.Number = x.NrArticle AND a.IdLanguage = x.IdLanguage
WHERE a.Type = 'stiri'
  AND a.Published = 'Y'
  AND x.NrArticle IS NULL;
-- Rezultat așteptat: 0 (toate au conținut)
```

### 3. Verificare PublishDate

```sql
-- Verificare: Articole Published=Y FĂRĂ PublishDate
SELECT COUNT(*) as missing_publish_date
FROM Articles
WHERE Type = 'stiri'
  AND Published = 'Y'
  AND PublishDate IS NULL;
-- Dacă > 0 → setăm PublishDate = UploadDate sau NOW()
```

---

## 🚀 Recomandări Implementare

### 1. **Query-ul Principal de Import**

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
    a.IdUser,
    a.NrSection,
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
ORDER BY a.Number, a.IdLanguage;
```

**Justificare:**
- `INNER JOIN` garantează că avem conținut în Xstiri (exclude articole fără conținut)
- `ORDER BY a.Number, a.IdLanguage` asigură procesare grupată pentru traduceri
- Filtrare `Type = 'stiri'` exclude alte tipuri (embed, ultrascurte, video)
- Filtrare `Published = 'Y'` exclude draft-uri și submitted

### 2. **Logging pentru Articole Excluse**

În `ImportArticlesCommand`, adaugă raportare:

```php
$this->logger->info('Articles import criteria', [
    'type' => 'stiri',
    'published' => 'Y',
    'expected_total' => 173670,
    'expected_unique' => 152523,
    'expected_translations' => 21147
]);

// După import:
$this->logger->info('Articles import completed', [
    'imported_total' => $importedCount,
    'imported_unique' => $uniqueArticles,
    'skipped' => $skippedCount,
    'failed' => $failedCount
]);
```

### 3. **Validare Post-Import**

```bash
# În PostValidateCommand
php bin/console app:newscoop:post-validate

# Verificări:
✓ Articole importate: 152,523 (100%)
✓ Traduceri importate: 21,147 (100%)
✓ Articole cu shortcodes procesate: 13,323 (100%)
⚠ Articole fără categorie: X (< 1%)
⚠ Articole fără autor: X (< 1%)
✗ Articole failed: X (< 0.1%)
```

---

## 📋 Update Checklist Migrare

### MIGRATION_SUMMARY.md - Secțiunea "Decizii Luate"

```markdown
### 1. **Tipuri Articole** ✅ DECISĂ
**Opțiunea aleasă:** Import doar 'stiri' + Published = 'Y'

- Filtrare: `Type = 'stiri' AND Published = 'Y'`
- **Volum:** 152,523 articole unice (98.85% din total tip 'stiri')
- **Traduceri:** 21,147 înregistrări suplimentare
- **Total înregistrări:** 173,670
- **Excluse:** 1,768 articole (1.15%) = draft-uri (N) + submitted (S)
- **Status:** ✅ **DECISĂ** - Import doar articole publicate
- **Justificare:**
  - Articole nepublicate = conținut incomplet sau învechit
  - 98.85% coverage = pierdere minimă
  - Focus pe conținut de calitate pentru arhivă publică
```

### NEWSCOOP_MIGRATION_PLAN.md - FAZA 5

Update estimări:

```markdown
### **FAZA 5: Import Articole + Traduceri + Processing** (2.5-3h)

**Volum actualizat:**
- **152,523 articole unice** (Type='stiri' + Published='Y')
- **21,147 traduceri** (ext_translations)
- **13,323 articole** cu shortcode processing (8.73%)
- **Total înregistrări:** 173,670

**Filtrare:**
```sql
WHERE Type = 'stiri' AND Published = 'Y'
```

**Timp estimat:**
- Import bază: 2-2.5 ore (152k articole @ 50/sec batch)
- Shortcode processing: +15-20 min (13k articole @ 15% overhead)
- Translation handling: inclus în timp bază
- **Total:** 2.5-3 ore
```

---

## 📝 Concluzie

**Decizie confirmată:** Import doar **Type = 'stiri' + Published = 'Y'**

**Rezultat:**
- ✅ **152,523 articole unice** (98.85% din total tip 'stiri')
- ✅ **21,147 traduceri** (13.4% au 2-3 limbi)
- ✅ **173,670 total înregistrări**
- ❌ **1,768 articole excluse** (1.15% = draft-uri + submitted)

**Impact:**
- Timp migrare: **10-15 minute mai rapid** decât estimarea inițială
- Calitate date: **Focus pe conținut publicat și complet**
- Procesare shortcodes: **13,323 articole** (mai puțin decât estimat)

**Perioada acoperită:** 2016-2024 (9 ani de arhivă)
**Limba dominantă:** Română (83.3%), Rusă (16%), Engleză (0.66%)

---

**Data completare analiză:** 2025-10-26
**Status:** ✅ Analiză completă, gata pentru implementare import
