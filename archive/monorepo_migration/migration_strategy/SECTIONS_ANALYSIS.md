# Analiza Secțiuni (Sections) - Newscoop Database

**Data:** 2025-10-26
**Status:** ✅ Analiză completă

---

## 📊 Statistici Generale

| Metric | Valoare | Observații |
|--------|---------|------------|
| **Total rânduri Sections** | 43 | Total entries în tabelă |
| **Secțiuni unice** (Number) | 18 | Număr distinct de secțiuni |
| **Nume unice** | 35 | Nume distincte (datorită traducerilor) |
| **Secțiuni cu 3 limbi** | 10 | EN + RO + RU (complete) |
| **Secțiuni cu 2 limbi** | 5 | RO + RU (fără EN) |
| **Secțiuni cu 1 limbă** | 3 | Doar RO sau RU (incomplete) |

---

## 🌍 Distribuție pe Limbi

| Limbă | Secțiuni | Procent | Observații |
|-------|----------|---------|------------|
| **Română (IdLanguage=2)** | 17 | 39.5% | Limbă principală |
| **Rusă (IdLanguage=15)** | 16 | 37.2% | Aproape completă |
| **Engleză (IdLanguage=1)** | 10 | 23.3% | Minoritară |

**Concluzie:** Majoritatea secțiunilor au traduceri RO+RU, engleza este parțială.

---

## 📋 Secțiuni cu Toate 3 Limbile (EN + RO + RU)

| # | Number | EN Name | RO Name | RU Name | Articole | Status |
|---|--------|---------|---------|---------|----------|--------|
| 1 | 2 | social | social | общество | 56,830 | ✅ Completă |
| 2 | 4 | international | externe | в мире | 50,354 | ✅ Completă |
| 3 | 1 | political | politic | политика | 27,662 | ✅ Completă |
| 4 | 3 | business | economic | экономика | 7,583 | ✅ Completă |
| 5 | 5 | editorials | editorial | мнения | 3,277 | ✅ Completă |
| 6 | 7 | culture | cultura | культура | 1,566 | ✅ Completă |
| 7 | 8 | sport | sport | спорт | 1,513 | ✅ Completă |
| 8 | 10 | video | opinii | видео | 1,118 | ⚠️ Inconsistentă* |
| 9 | 6 | investigations | investigații | расследования | 74 | ✅ Completă |
| 10 | 15 | special | special | special | 19 | ✅ Completă |

**Total articole în secțiuni complete:** 149,996 (85.4% din totalul de 175,626 articole)

\* **Inconsistență Number 10:**
- EN: "video"
- RO: "opinii" (opinii = opinions, nu video!)
- RU: "видео" (video)

⚠️ **Recomandare:** RO "opinii" pare să fie o greșeală. Ar trebui mapată separat sau corectată.

---

## 📋 Secțiuni cu 2 Limbi (RO + RU, fără EN)

| # | Number | RO Name | RU Name | Articole | Observații |
|---|--------|---------|---------|----------|------------|
| 1 | 22 | Alegeri | Выборы | 2,506 | Secțiune electorală (temporară) |
| 2 | 23 | TRANSNISTRIA | Приднестровье | 1,173 | Secțiune regională specifică |
| 3 | 25 | Advretorial | Advretorial | 320 | Advertorial/Publicitate |
| 4 | 20 | interviu | interviu | 107 | Interviuri (nume identic RO/RU) |
| 5 | 26 | Anti-Fake | Anti-Fake | 101 | Fact-checking (nume identic) |

**Total articole:** 4,207 (2.4% din total)

**Observații:**
- Sunt secțiuni specifice audienței RO+RU (Moldova/Transnistria)
- Nu necesită traducere EN (context local)

---

## 📋 Secțiuni cu 1 Limbă (Incomplete)

| # | Number | Limbă | Name | Articole | Status |
|---|--------|-------|------|----------|--------|
| 1 | 24 | RO | romania2019.eu | 39 | ⚠️ Campanie temporară 2019 |
| 2 | 21 | RU | интервью | 6 | ⚠️ Duplicate de Number 20? |
| 3 | 17 | RO | video | 3 | ⚠️ Duplicate de Number 10? |

**Total articole:** 48 (0.03% din total - neglijabil)

### 🔍 Probleme Identificate

**1. Number 17 (video, RO) vs Number 10 (video/opinii/видео)**
- Posibil duplicate sau secțiune abandonată
- Doar 3 articole → poate fi ignorată sau merged

**2. Number 21 (интервью, RU) vs Number 20 (interviu, RO+RU)**
- "интервью" = "interviu" în rusă
- Posibil duplicate, doar 6 articole
- Recomandare: merge în Number 20

**3. Number 24 (romania2019.eu)**
- Secțiune temporară pentru campanie electorală 2019
- Doar 39 articole
- Recomandare: poate fi archived sau merged în "Alegeri" (22)

---

## 📊 Top 10 Secțiuni după Număr de Articole

| Rang | Number | Nume Principal | Articole | % din Total | Limbi |
|------|--------|----------------|----------|-------------|-------|
| 1 | 2 | social | 56,830 | 32.4% | 3 (EN/RO/RU) |
| 2 | 4 | externe / international | 50,354 | 28.7% | 3 (EN/RO/RU) |
| 3 | 1 | politic / political | 27,662 | 15.7% | 3 (EN/RO/RU) |
| 4 | 3 | economic / business | 7,583 | 4.3% | 3 (EN/RO/RU) |
| 5 | 5 | editorial / editorials | 3,277 | 1.9% | 3 (EN/RO/RU) |
| 6 | 22 | Alegeri / Выборы | 2,506 | 1.4% | 2 (RO/RU) |
| 7 | 7 | cultura / culture | 1,566 | 0.9% | 3 (EN/RO/RU) |
| 8 | 8 | sport | 1,513 | 0.9% | 3 (EN/RO/RU) |
| 9 | 23 | TRANSNISTRIA | 1,173 | 0.7% | 2 (RO/RU) |
| 10 | 10 | video/opinii | 1,118 | 0.6% | 3 (EN/RO/RU) |

**Top 3 secțiuni reprezintă 76.8% din toate articolele!**

---

## 🔄 Consistența Traducerilor

### ✅ Secțiuni Consistente (10 secțiuni)

Secțiunile cu Number 1-8, 15, și 6 au traduceri complete și consistente:
- Nume traduse corespunzător în fiecare limbă
- Toate 3 limbile prezente (EN/RO/RU)
- Conținut distribuit pe toate limbile

**Exemple bune:**
- Number 1: political / politic / политика
- Number 2: social / social / общество
- Number 7: culture / cultura / культура

### ⚠️ Secțiuni cu Inconsistențe

**Number 10: video vs opinii**
- EN: "video" ✓
- RO: "opinii" ❌ (ar trebui "video")
- RU: "видео" ✓

Inconsistență: RO folosește "opinii" (opinions) în loc de "video".

**Recomandare:** Verificare dacă este greșeală sau intenționat.

### ⚠️ Secțiuni Duplicate Posibile

| Original | Posibil Duplicate | Articole | Recomandare |
|----------|-------------------|----------|-------------|
| Number 20 (interviu) | Number 21 (интервью) | 107 + 6 | Merge Number 21 → 20 |
| Number 10 (video) | Number 17 (video) | 1,118 + 3 | Merge Number 17 → 10 |

---

## 🎯 Recomandări pentru Migrare news_app

### 1. **Grupare Secțiuni cu Traduceri**

**Strategia:** Grupare după `Number`, traduceri în `ext_translations`

**Exemplu:**
```php
// Section Number 1 (political/politic/политика)
$category = new Category();
$category->setName('politic');  // Default RO
$category->setSlug('politic');

// Traduceri în ext_translations
$category->setTranslatableLocale('en');
$category->setName('political');

$category->setTranslatableLocale('ru');
$category->setName('политика');

$category->setTranslatableLocale('ro');  // Reset to default
```

**Rezultat:** 18 categorii în news_app (în loc de 43 secțiuni Newscoop)

### 2. **Tratarea Secțiunilor Incomplete**

**Opțiune A: Ignore** (Recomandată)
- Number 17, 21, 24 (doar 48 articole - 0.03%)
- Articolele pot fi reatribuite la categorii principale

**Opțiune B: Merge**
- Number 21 (интервью) → Number 20 (interviu)
- Number 17 (video) → Number 10 (video/opinii)
- Number 24 (romania2019.eu) → Number 22 (Alegeri)

**Opțiune C: Import complet** (Nu recomandată)
- Importăm toate 43 de rânduri ca categorii separate
- Creează duplicate și inconsistențe

**Recomandare:** **Opțiunea A** - Ignorăm secțiunile incomplete, reatribuim articolele.

### 3. **Rezolvarea Inconsistenței Number 10**

**Problema:** RO "opinii" vs EN "video" / RU "видео"

**Opțiuni:**
- **A:** Folosim "video" pentru toate limbile (corectăm RO)
- **B:** Split în 2 categorii: "Video" și "Opinii"
- **C:** Păstrăm "opinii" ca nume RO (inconsistent dar prezervăm originaul)

**Verificare necesară:**
```sql
-- Check ce conține această secțiune
SELECT a.Number, a.IdLanguage, x.FTitlu
FROM Articles a
JOIN Xstiri x ON a.Number = x.NrArticle AND a.IdLanguage = x.IdLanguage
WHERE a.NrSection = 10 AND a.IdLanguage = 2
LIMIT 10;
```

**Recomandare:** Verificăm manual conținutul înainte de decizie.

### 4. **Mapare Recomandată news_app**

| Newscoop Number | news_app Category Name (RO) | Slug | Translations | Articole | Prioritate |
|-----------------|----------------------------|------|--------------|----------|------------|
| 2 | Social | social | EN: social, RU: общество | 56,830 | ⭐⭐⭐ |
| 4 | Externe | externe | EN: international, RU: в мире | 50,354 | ⭐⭐⭐ |
| 1 | Politic | politic | EN: political, RU: политика | 27,662 | ⭐⭐⭐ |
| 3 | Economic | economic | EN: business, RU: экономика | 7,583 | ⭐⭐ |
| 5 | Editorial | editorial | EN: editorials, RU: мнения | 3,277 | ⭐⭐ |
| 22 | Alegeri | alegeri | RU: Выборы | 2,506 | ⭐ |
| 7 | Cultură | cultura | EN: culture, RU: культура | 1,566 | ⭐ |
| 8 | Sport | sport | EN: sport, RU: спорт | 1,513 | ⭐ |
| 23 | Transnistria | transnistria | RU: Приднестровье | 1,173 | ⭐ |
| 10 | Video* | video | EN: video, RU: видео | 1,118 | ⭐ |
| 25 | Advertorial | advertorial | RU: Advretorial | 320 | - |
| 20 | Interviu | interviu | RU: interviu | 107+ | - |
| 26 | Anti-Fake | anti-fake | RU: Anti-Fake | 101 | - |
| 6 | Investigații | investigatii | EN: investigations, RU: расследования | 74 | - |
| 15 | Special | special | EN/RU: special | 19 | - |

\* Number 10: Necesită verificare pentru inconsistența "opinii"

**Total categorii importate: 15-18** (vs 43 rânduri Newscoop)

---

## 📈 Distribuția Articolelor pe Secțiuni

```
Social (2):         ████████████████████████████████ 32.4% (56,830)
Externe (4):        ████████████████████████████     28.7% (50,354)
Politic (1):        ███████████████                  15.7% (27,662)
Economic (3):       ████                              4.3% (7,583)
Editorial (5):      ██                                1.9% (3,277)
Alegeri (22):       █                                 1.4% (2,506)
Cultură (7):        █                                 0.9% (1,566)
Sport (8):          █                                 0.9% (1,513)
Transnistria (23):  █                                 0.7% (1,173)
Video (10):         █                                 0.6% (1,118)
Altele:             ██                                2.5% (4,420)
```

**Observație:** Top 3 secțiuni (Social, Externe, Politic) conțin 76.8% din articole.

---

## ⚠️ Probleme și Soluții

### Problema 1: Secțiuni Duplicate

**Identificate:**
- Number 17 (video, RO) - 3 articole
- Number 21 (интервью, RU) - 6 articole

**Soluție:**
```php
// În ImportArticlesCommand
$sectionMergeMap = [
    17 => 10,  // video RO → video/opinii
    21 => 20,  // интервью → interviu
];

$newscoopSectionId = $sectionMergeMap[$article['NrSection']] ?? $article['NrSection'];
$categoryId = $this->getMappedId('section', $newscoopSectionId);
```

### Problema 2: Inconsistență "opinii" vs "video"

**Necesită decizie:** Verificare manuală conținut Number 10

**Query verificare:**
```sql
SELECT
    a.IdLanguage,
    COUNT(*) as count,
    GROUP_CONCAT(DISTINCT LEFT(x.FTitlu, 50) SEPARATOR ' | ') as sample_titles
FROM Articles a
JOIN Xstiri x ON a.Number = x.NrArticle AND a.IdLanguage = x.IdLanguage
WHERE a.NrSection = 10
GROUP BY a.IdLanguage;
```

**După verificare:**
- Dacă conține video → corectăm la "video" pentru RO
- Dacă conține opinii → split în 2 categorii separate

### Problema 3: Secțiuni Temporare

**Number 24 (romania2019.eu) - 39 articole**

**Soluție:**
- Opțiune A: Mapăm la categoria "Alegeri" (22)
- Opțiune B: Creăm categoria "Arhivă" pentru conținut temporar
- Opțiune C: Ignorăm (reatribuim la categoria default)

---

## 🎯 Plan Import Categorii

### Faza 1: Validare

```bash
php bin/console app:newscoop:validate:sections
```

**Output așteptat:**
```
Section Analysis:
  Total Newscoop sections: 43
  Unique sections (Number): 18
  Sections with 3 languages: 10
  Sections with 2 languages: 5
  Sections with 1 language: 3

Inconsistencies detected:
  ⚠ Number 10: Inconsistent RO name "opinii" vs EN "video"
  ⚠ Number 17: Possible duplicate of Number 10 (3 articles)
  ⚠ Number 21: Possible duplicate of Number 20 (6 articles)

Recommendations:
  → Merge Number 17 into Number 10
  → Merge Number 21 into Number 20
  → Verify Number 10 content (opinii vs video)
  → Total categories to import: 15-18
```

### Faza 2: Import

```php
// ImportCategoriesCommand
foreach ($sections as $number => $translations) {
    // Skip duplicates
    if (in_array($number, [17, 21, 24])) {
        $this->logger->warning("Skipping duplicate section", ['number' => $number]);
        continue;
    }

    $category = new Category();

    // Default language (RO)
    $defaultLang = $translations[2] ?? $translations[15] ?? $translations[1];
    $category->setName($defaultLang['Name']);
    $category->setSlug($this->slugify($defaultLang['Name']));

    $em->persist($category);

    // Translations
    foreach ($translations as $langId => $data) {
        if ($langId == $defaultLang['IdLanguage']) continue;

        $locale = $this->mapLanguageId($langId);
        $category->setTranslatableLocale($locale);
        $category->setName($data['Name']);
        $em->persist($category);
    }

    $category->setTranslatableLocale('ro');  // Reset

    // Mapping
    INSERT INTO newscoop_id_mapping VALUES ('section', $number, $category->getId());
}
```

---

## ✅ Checklist Post-Import

- [ ] **15-18 categorii** create în news_app
- [ ] **Toate traducerile** în ext_translations
- [ ] **Mapare completă** în newscoop_id_mapping
- [ ] **Zero duplicate** (17, 21 merge-uite sau skipped)
- [ ] **Verificat Number 10** (opinii/video resolved)
- [ ] **Sample check:** Top 3 categorii au nume corecte în toate limbile

---

## 📝 Concluzii

**✅ Puncte Tari:**
- Majoritate secțiuni (10/18) au traduceri complete EN+RO+RU
- Top 3 secțiuni foarte consistente (76.8% articole)
- Traduceri majoritare sunt corecte și consistente

**⚠️ Puncte Slabe:**
- 3 secțiuni incomplete (17, 21, 24) - neglijabile (48 articole)
- 1 inconsistență (Number 10: opinii vs video)
- Posibile duplicate (necesită merge)

**🎯 Recomandare Finală:**
- Import **15-18 categorii** (nu 43)
- Grupare după `Number` cu traduceri în `ext_translations`
- Merge secțiuni duplicate (17→10, 21→20)
- Verificare manuală Number 10 înainte de import

**Calitate generală:** ⭐⭐⭐⭐☆ (4/5) - Bună, cu mici inconsistențe ușor de rezolvat
