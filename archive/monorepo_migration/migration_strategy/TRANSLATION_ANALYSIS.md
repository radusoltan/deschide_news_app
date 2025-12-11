# Analiza Traducerilor - Newscoop Database

**Data:** 2025-10-26
**Status:** ✅ Analiză completă

---

## 📊 Statistici Generale

| Metric | Valoare | Procent |
|--------|---------|---------|
| **Articole unice** (Number distinct) | 154,805 | 100% |
| **Total rânduri Articles** | 176,207 | - |
| **Traduceri totale** | 21,402 | - |
| **Articole cu traduceri** | 20,703 | 13.37% |
| **Articole fără traduceri** | 134,102 | 86.63% |

---

## 🌍 Distribuția pe Limbi

| Limbă | Articole (rows) | Procent din total |
|-------|-----------------|-------------------|
| **Română (ro)** | 146,914 | 83.38% |
| **Rusă (ru)** | 28,117 | 15.96% |
| **Engleză (en)** | 1,176 | 0.67% |

**Concluzie:** Româna este limba dominantă (83.4% din conținut)

---

## 📈 Distribuție după Număr de Limbi

| Nr. Limbi | Articole | Procent | Descriere |
|-----------|----------|---------|-----------|
| **1 limbă** | 134,102 | 86.63% | Monolingve (fără traduceri) |
| **2 limbi** | 20,004 | 12.92% | Bilingve (1 traducere) |
| **3 limbi** | 699 | 0.45% | Trilingve (2 traduceri) |

---

## 🔄 Combinații de Limbi (Patterns)

| Combinație | Articole | Procent | Observații |
|------------|----------|---------|------------|
| **Doar Română** | 126,211 | 81.53% | Articole monolingve RO |
| **Română + Rusă** | 19,559 | 12.63% | Pattern tradițional (audiență Moldova/Transnistria) |
| **Doar Rusă** | 7,859 | 5.08% | Articole monolingve RU |
| **Română + Rusă + Engleză** | 699 | 0.45% | Toate 3 limbile disponibile |
| **Română + Engleză** | 445 | 0.29% | Fără rusă |
| **Doar Engleză** | 32 | 0.02% | Articole monolingve EN (foarte rare) |

### Observații Importante:

1. **ZERO articole EN+RU fără RO**
   - Confirmă că româna este limba primară
   - Toate traducerile sunt făcute din/în română

2. **Pattern tradițional: RO → RU** (12.63%)
   - Reflectă audiența din Moldova/Transnistria
   - Traducerile sunt consistente

3. **Engleza este minoritară** (0.67%)
   - Doar 1,176 articole totale în EN
   - Majoritatea au și RO+RU (699) sau doar RO (445)
   - Foarte puține articole exclusiv EN (32)

---

## ⚙️ Implicații pentru Migrare

### 1. Strategia de Import

**Limba Default:**
- Prioritate: Română (2) → Engleză (1) → Rusă (15)
- 83.4% din articole sunt deja în română

**Procesare:**

```php
// Grupare articole după Number
$articleGroups = [];
foreach ($newscoopArticles as $row) {
    $articleGroups[$row['Number']][$row['IdLanguage']] = $row;
}

// Pentru fiecare grup
foreach ($articleGroups as $number => $languages) {
    // Determină limba default
    $defaultLangId = $languages[2] ?? $languages[1] ?? $languages[15];
    $defaultLocale = mapLanguageId($defaultLangId); // 2→'ro', 1→'en', 15→'ru'

    // Import articol în limba default
    $article = new Article();
    $article->setTitle($defaultLangData['FTitlu']);
    $article->setContent($processedContent);
    // ... alte câmpuri

    $em->persist($article);

    // Import traduceri în ext_translations
    foreach ($languages as $langId => $langData) {
        if ($langId === $defaultLangId) continue;

        $locale = mapLanguageId($langId); // 'ru', 'en'
        $article->setTranslatableLocale($locale);
        $article->setTitle($langData['FTitlu']);
        $article->setContent($processedContentTranslated);

        $em->persist($article); // Gedmo salvează în ext_translations
    }

    $article->setTranslatableLocale($defaultLocale); // Reset
}
```

### 2. Timp de Procesare

| Categorie | Articole | INSERT-uri | Timp Estimat |
|-----------|----------|------------|--------------|
| Monolingve | 134,102 | 134,102 | 1.5-2h |
| Bilingve | 20,004 | 40,008 | 1.0-1.3h |
| Trilingve | 699 | 2,097 | 0.05h |
| **TOTAL** | **154,805** | **176,207** | **2.5-3.4h** |

**Note:**
- Fiecare traducere = 1 INSERT în `ext_translations`
- Content processing adaugă +15% overhead (~20 min)
- **Timp total Faza 5: 2.8-3.5 ore**

### 3. Verificare Post-Migrare (OBLIGATORIU!)

#### Query 1: Statistici Generale
```sql
SELECT
    COUNT(DISTINCT a.id) as total_articles,
    COUNT(DISTINCT CASE WHEN et.locale IS NOT NULL THEN a.id END) as articles_with_translations,
    COUNT(et.id) as total_translations
FROM article a
LEFT JOIN ext_translations et ON et.foreign_key::text = a.id::text
    AND et.object_class = 'App\Entity\Article';
```

**RESULT AȘTEPTAT:**
- `total_articles`: ~154,805
- `articles_with_translations`: ~20,703
- `total_translations`: ~21,402

#### Query 2: Distribuție pe Limbi
```sql
SELECT
    et.locale,
    COUNT(*) as translation_count,
    COUNT(DISTINCT et.foreign_key) as articles_translated
FROM ext_translations et
WHERE et.object_class = 'App\Entity\Article'
GROUP BY et.locale
ORDER BY translation_count DESC;
```

**RESULT AȘTEPTAT:**
- `ru`: ~28,117 translations
- `en`: ~1,176 translations

#### Query 3: Verificare Bilingve
```sql
-- Newscoop: articole cu exact 2 limbi
SELECT COUNT(*) as expected_bilingual
FROM (
    SELECT Number FROM newscoop.Articles
    GROUP BY Number
    HAVING COUNT(*) = 2
) as bilingual;
-- RESULT: ~20,004

-- news_app: articole cu exact 1 traducere
SELECT COUNT(*) as actual_bilingual
FROM (
    SELECT foreign_key
    FROM ext_translations
    WHERE object_class = 'App\Entity\Article'
    GROUP BY foreign_key
    HAVING COUNT(*) = 1
) as bilingual;
-- RESULT AȘTEPTAT: ~20,004 (TREBUIE SĂ FIE EGAL!)
```

#### Query 4: Verificare Trilingve
```sql
SELECT COUNT(DISTINCT foreign_key) as trilingual_articles
FROM ext_translations
WHERE object_class = 'App\Entity\Article'
GROUP BY foreign_key
HAVING COUNT(DISTINCT locale) = 2;  -- 2 traduceri + default = 3 limbi
```

**RESULT AȘTEPTAT:** ~699 articole

#### Query 5: Căutare Traduceri Lipsă (CRITIC!)
```sql
-- Articole care ar fi trebuit să aibă traduceri dar nu au
WITH newscoop_translated AS (
    SELECT DISTINCT Number
    FROM newscoop.Articles
    GROUP BY Number
    HAVING COUNT(*) > 1
),
newsapp_translated AS (
    SELECT DISTINCT foreign_key::integer as article_id
    FROM ext_translations
    WHERE object_class = 'App\Entity\Article'
)
SELECT COUNT(*) as missing_translations
FROM newscoop_translated nt
LEFT JOIN newscoop_id_mapping nim ON nim.newscoop_id = nt.Number
    AND nim.entity_type = 'article'
LEFT JOIN newsapp_translated nat ON nat.article_id = nim.news_app_id
WHERE nat.article_id IS NULL;
```

**RESULT AȘTEPTAT:** 0 (sau foarte puține - doar cele cu erori la import)

**⚠️ Dacă > 100: PROBLEMA MAJORĂ în procesarea traducerilor!**

---

## ✅ Checklist Post-Migrare

- [ ] **Total articole importate:** ~154,805 (verificat în `article` table)
- [ ] **Traduceri în ext_translations:** ~21,402 (verificat cu Query 1)
- [ ] **Articole cu traduceri:** ~20,703 (verificat cu Query 1)
- [ ] **Distribuție limbi:** RU ~28k, EN ~1.1k (verificat cu Query 2)
- [ ] **Bilingve matching:** ~20,004 Newscoop = ~20,004 news_app (Query 3)
- [ ] **Trilingve matching:** ~699 Newscoop = ~699 news_app (Query 4)
- [ ] **Zero traduceri lipsă:** 0 missing (Query 5)
- [ ] **Sample manual:** 5-10 articole verificate manual (titluri diferite în limbi diferite)

---

## 🚨 Probleme Potențiale

### Problema 1: Traduceri Lipsă
**Semn:** Query 5 returnează > 100

**Cauză posibilă:**
- Bug în logica de grupare după `Number`
- `setTranslatableLocale()` nu apelat corect
- `$em->persist()` lipsă pentru traduceri
- `$em->flush()` apelat prea devreme

**Soluție:**
- Review cod în `ImportArticlesCommand` (lines 1480-1511 în plan)
- Verifică că traducerile sunt procesate ÎNAINTE de flush
- Asigură-te că locale-ul se resetează la default după fiecare traducere

### Problema 2: Traduceri Duplicate
**Semn:** `total_translations` > 21,402

**Cauză posibilă:**
- Articole procesate de mai multe ori
- Flush/clear cycle incorect

**Soluție:**
- Verifică `migration_log` pentru duplicate
- Asigură batch processing corect (50 articles/batch)

### Problema 3: Content Incomplet în Traduceri
**Semn:** Traducerile au titlu dar content gol

**Cauză posibilă:**
- BLOB decoding failure pentru traduceri
- `stream_get_contents()` returneză empty pentru traduceri

**Soluție:**
- Verifică că fiecare traducere decodează BLOB-ul separat
- Nu reutiliza `$rawContent` din limba default

---

## 📝 Note Tehnice

### Gedmo Translatable Behavior

**Cum funcționează:**
1. Entitatea `Article` are câmpuri marcate cu `#[Gedmo\Translatable]`
2. Când setezi `$article->setTranslatableLocale('ru')` și apoi `setTitle()`, Gedmo salvează în `ext_translations`
3. Tabela `ext_translations` structură:
   ```sql
   CREATE TABLE ext_translations (
       id SERIAL PRIMARY KEY,
       locale VARCHAR(8) NOT NULL,
       object_class VARCHAR(255) NOT NULL,
       field VARCHAR(32) NOT NULL,
       foreign_key VARCHAR(64) NOT NULL,
       content TEXT
   );
   ```

4. Exemplu row:
   ```
   locale: 'ru'
   object_class: 'App\Entity\Article'
   field: 'title'
   foreign_key: '12345'  (article.id)
   content: 'Заголовок статьи'
   ```

### Mapare Language IDs

```php
private function mapLanguageId(int $newscoopLangId): string
{
    return match($newscoopLangId) {
        1 => 'en',   // English
        2 => 'ro',   // Romanian
        15 => 'ru',  // Russian
        default => 'en',
    };
}
```

### Performance Considerations

- **Nu folosi eager loading** pentru traduceri (memory overhead)
- **Batch processing:** 50 articole/batch (optimizează flush/clear)
- **Memory usage:** ~10 MB per 50 articles cu traduceri
- **Content processing:** +10ms/article cu shortcodes

---

## 🎯 Recomandări Finale

1. **Testare pe sample mic ÎNAINTE de import complet:**
   ```bash
   php bin/console app:newscoop:import:articles --limit=100 --dry-run
   ```
   - Verifică că toate 100 articole au traducerile corecte
   - Rulează toate 5 query-urile de verificare
   - Dacă totul OK → continuă cu import complet

2. **Monitoring în timpul importului:**
   - Log fiecare traducere procesată
   - Count traduceri în memory: `translationsProcessed++`
   - Compară cu expected la sfârșitul fiecărei batch-uri

3. **Post-validare OBLIGATORIE:**
   - Rulează TOATE query-urile de verificare
   - Sample manual: 10 articole cu traduceri
   - Verifică că frontend poate accesa traducerile

4. **Backup ÎNAINTE de import:**
   ```bash
   pg_dump news_app > backup_before_migration.sql
   ```

---

**⚠️ IMPORTANT:** Traducerile sunt o componentă CRITICĂ a migrării! Fără verificare riguroasă, poți pierde 13.37% din conținut (21,402 traduceri)!
