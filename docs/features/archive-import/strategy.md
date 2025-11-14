# Strategie de Import Arhivă Articole

**Data:** 2025-11-05
**Status:** Draft - În Planificare
**Scop:** Migrarea articolelor din 4 surse diferite către noul sistem Deschide News App

---

## Context

Avem următoarele surse de date pentru arhiva de articole:

1. **MySQL v1** - Versiune veche a bazei de date
2. **MySQL v2** - Versiune mai nouă a bazei de date
3. **Elasticsearch** - Arhiva parțial completată (stare actuală)
4. **Webflow API** - Versiunea actuală a site-ului

**Provocări:**
- Risc de duplicare a articolelor
- Posibile pierderi de date (gap-uri)
- Lipsa unui identificator comun între surse
- Inconsistențe în formatare și metadata
- Volume mari de date

---

## Strategie Propusă

### 1. Identificare Primară prin "Fingerprint" Multi-nivel

Sistem de identificare bazat pe **multiple chei** pentru detecție precisă de duplicate:

**Prioritate identificatori unici:**
1. **ID-uri originale** din fiecare sursă (MySQL1, MySQL2, Elasticsearch, Webflow)
2. **Combinație slug + dată publicare** - pereche unică
3. **Hash MD5/SHA256** din `title + primele 200 caractere content`
4. **URL-uri originale** (dacă există în metadata)

**Algoritm de matching:**
```
Pentru fiecare articol nou:
1. Verifică dacă există ID original în registry
2. Dacă nu → verifică slug + dată
3. Dacă nu → calculează content hash și compară
4. Dacă nu → verifică URL original
5. Dacă nicio potrivire → articol nou
```

---

### 2. Tabel de Mapping/Registry

Creăm o entitate `ArticleImportRegistry` pentru tracking complet al procesului:

**Structură entitate:**
```php
ArticleImportRegistry:
- id (PK - intern)
- source (enum: mysql_v1, mysql_v2, elasticsearch, webflow)
- source_id (string - ID original din sursa respectivă)
- content_hash (string - SHA256 pentru detecție duplicat)
- slug (string - pentru referință rapidă)
- title (string - pentru debugging)
- published_at (datetime - din sursă)
- imported_article_id (FK către Article - nullable)
- import_batch_id (string - UUID pentru batch)
- import_status (enum: pending, imported, duplicate, conflict, skipped, error)
- conflict_notes (JSON - detalii despre conflicte)
- metadata (JSON - orice date suplimentare din sursă)
- imported_at (datetime - când a fost importat)
- created_at (datetime)
- updated_at (datetime)
```

**Avantaje:**
- Tracking complet al fiecărui articol din fiecare sursă
- Posibilitate de rollback per batch
- Audit trail pentru debugging
- Identificare duplicatelor înainte de import
- Reconciliere manuală pentru conflicte

---

### 3. Ordinea de Import - "Cascadă cu Prioritate"

Import secvențial de la sursa cea mai autoritară către cea mai veche:

**Ordine recomandată:**
```
1. Webflow API (prioritate 1)
   - Versiune actuală a site-ului
   - Probabil cele mai curate date
   - Metadata completă și validată

2. Elasticsearch (prioritate 2)
   - Stare "semi-curată"
   - Posibil să aibă articole care nu sunt în Webflow
   - Căutare full-text deja indexată

3. MySQL v2 (prioritate 3)
   - Versiune mai nouă a arhivei
   - Poate conține articole vechi eliminate din Webflow
   - Date structurate în format relațional

4. MySQL v1 (prioritate 4)
   - Versiune cea mai veche
   - DOAR pentru gap-uri (articole care nu apar în celelalte surse)
   - Risc mai mare de date incomplete/corupte
```

**Logica priorității:**
- Dacă același articol (detectat prin fingerprint) apare în multiple surse:
  - Se ia versiunea din sursa cu prioritate mai mare
  - Excepție: dacă versiunea din sursă mai jos are content semnificativ mai lung/complet
  - Se loghează conflictul în `conflict_notes` pentru review manual

---

### 4. Proces de Import în 3 Faze

#### **FAZA 1 - Inventar (Dry Run)**

Scopuri:
- Analiza tuturor surselor FĂRĂ modificări în baza de date de producție
- Popularea tabelului `ArticleImportRegistry`
- Identificarea conflictelor și duplicatelor
- Generarea rapoartelor de diagnostic

**Comenzi:**
```bash
# Analiza fiecărei surse
symfony console app:import:analyze mysql-v1
symfony console app:import:analyze mysql-v2
symfony console app:import:analyze elasticsearch
symfony console app:import:analyze webflow

# Generare raport global
symfony console app:import:report
```

**Output raport:**
```
Total articole găsite: 15,000
  - Webflow: 5,000
  - Elasticsearch: 7,500
  - MySQL v2: 8,000
  - MySQL v1: 10,000

Articole unice (după deduplicare): 12,500
Duplicate detectate: 2,500
Conflicte necesită review manual: 150

Gap-uri temporale:
  - 2010-2012: 200 articole doar în MySQL v1
  - 2015: 50 articole doar în Elasticsearch
  - 2023-2024: 1,000 articole doar în Webflow
```

**Tabele generate:**
- `article_import_registry` - toate articolele inventariate
- `import_conflicts.csv` - lista conflictelor pentru review
- `import_statistics.json` - metrici detaliate

#### **FAZA 2 - Reconciliere**

Scopuri:
- Review manual al conflictelor critice
- Definirea regulilor automate de rezolvare
- Validarea strategiei de import
- Aprobare finală

**Activități:**

1. **Review manual conflicte:**
   ```bash
   # Export conflicte pentru review
   symfony console app:import:export-conflicts conflicts.xlsx

   # După review, import decizii
   symfony console app:import:resolve-conflicts decisions.xlsx
   ```

2. **Definire reguli automate:**
   ```yaml
   # config/import_rules.yaml
   conflict_resolution:
     same_article_multiple_sources:
       # Ia versiunea cu content mai lung
       rule: longest_content

     missing_metadata:
       # Completează din surse secundare
       rule: merge_metadata

     duplicate_slugs:
       # Adaugă suffix numeric
       rule: append_number
   ```

3. **Validare:**
   ```bash
   # Simulare import cu regulile definite
   symfony console app:import:simulate --dry-run
   ```

#### **FAZA 3 - Import Efectiv**

Scopuri:
- Import real în baza de date de producție
- Logging detaliat
- Rollback capability
- Verificare integritate

**Comenzi de import:**
```bash
# Import per sursă (în ordinea priorității)
symfony console app:import:execute webflow --batch-size=100
symfony console app:import:execute elasticsearch --batch-size=100
symfony console app:import:execute mysql-v2 --batch-size=100
symfony console app:import:execute mysql-v1 --batch-size=100

# Sau import complet automat
symfony console app:import:execute all --batch-size=100
```

**Caracteristici implementare:**
- **Batch processing** - 100 articole per batch (configurabil)
- **Progress bar** - feedback vizual în CLI
- **Error handling** - continuare la eroare + logging
- **Rollback** - posibilitate de rollback per batch
- **Checkpoints** - salvare progres pentru reluare după eroare
- **Verificare post-import** - count, integrity checks

**Logging:**
```
logs/import_2025_11_05_webflow.log
logs/import_2025_11_05_elasticsearch.log
logs/import_errors.log
logs/import_summary.json
```

---

## Informații Necesare Pentru Implementare

### Pentru Fiecare Sursă de Date

#### **MySQL v1 & v2:**
```sql
-- Schema bazelor de date
SHOW TABLES;
DESCRIBE articles; -- sau numele tabelului principal

-- Volume
SELECT COUNT(*) FROM articles;

-- Sample data
SELECT * FROM articles LIMIT 5;

-- Informații cheie necesare:
-- ✓ Există ID unic?
-- ✓ Există slug/URL?
-- ✓ Format câmpuri dată (published_at, updated_at)?
-- ✓ Cum sunt stocate traducerile (ro/en/ru)?
--   - Tabele separate?
--   - Coloane separate (title_ro, title_en)?
--   - JSON?
-- ✓ Relații cu alte tabele:
--   - Autori (users/authors)?
--   - Categorii?
--   - Imagini?
--   - Tags?
-- ✓ Status articole (draft/published/archived)?
-- ✓ Metadata suplimentară (views, comments, ratings)?
```

#### **Elasticsearch:**
```bash
# Mapping index
curl http://localhost:9200/articles/_mapping | jq '.'

# Count documente
curl http://localhost:9200/articles/_count

# Sample documents
curl http://localhost:9200/articles/_search?size=5 | jq '.hits.hits'

# Informații necesare:
# ✓ Nume index exact
# ✓ Structură document (câmpuri, tipuri)
# ✓ Există _id original salvat?
# ✓ Cum sunt indexate traducerile?
# ✓ Metadata disponibilă
```

#### **Webflow API:**
```bash
# Informații necesare:
# ✓ Endpoints API disponibile
# ✓ Autentificare (API key, OAuth)?
# ✓ Rate limits (requests/minute)
# ✓ Pagination (limit, offset sau cursor-based?)
# ✓ Structură răspuns JSON
# ✓ Filtre disponibile (date, categorii, status)
# ✓ Volume total articole
# ✓ Câmpuri disponibile per articol

# Exemple comenzi test:
curl -H "Authorization: Bearer API_KEY" \
  https://api.webflow.com/collections/COLLECTION_ID/items

# Documentație API:
# https://developers.webflow.com/reference/list-collection-items
```

---

## Întrebări Critice

Înainte de implementare, trebuie clarificate:

### 1. Volume și Distribuție
- **Câte articole** aproximativ în fiecare sursă?
- Există o **distribuție temporală** clară? (ex: MySQL1 = 2010-2015, MySQL2 = 2015-2020, etc.)
- Care este **rata de overlap** estimată?

### 2. Identificatori și Relații
- Există vreun **identificator comun** între surse?
- Cum sunt legate **imaginile** de articole în fiecare sursă?
- Există **URL-uri canonice** salvate undeva?
- Se păstrează **istoric de versiuni** sau doar versiunea curentă?

### 3. Consistență și Autoritate
- Care sursă este **"source of truth"** pentru diferite perioade?
- Există **articole șterse intenționat** care nu trebuie reimportate?
- Cum identificăm articolele **draft vs published**?

### 4. Metadata și Conținut
- Ce **metadata** sunt importante să păstrăm?
  - View counts / statistics?
  - Comments (dacă există)?
  - Ratings/likes?
  - SEO metadata (meta description, keywords)?
  - Autori multipli?
- Există **rich media** în content (embeds, video, galerii)?
- Cum sunt formatate **call-to-actions** sau **article badges**?

### 5. Traduceri
- Toate sursele au **toate limbile** (ro/en/ru)?
- Există articole **doar în anumite limbi**?
- Cum gestionăm **traduceri incomplete**?

### 6. Performanță și Timing
- Există **perioadă de mentenanță** pentru import?
- Import poate rula **în background** sau trebuie finalizat rapid?
- Sunt **resurse server** suficiente pentru procesare în paralel?

---

## Structura Comenzilor de Import

### Comenzi Propuse

```bash
# FAZA 1 - ANALIZA
app:import:analyze <source>              # Analiza unei surse
app:import:analyze-all                   # Analiza tuturor surselor
app:import:report                        # Generare raport global
app:import:export-conflicts              # Export conflicte pentru review

# FAZA 2 - RECONCILIERE
app:import:resolve-conflicts             # Import decizii de rezolvare
app:import:simulate                      # Simulare import (dry-run)
app:import:validate-mapping              # Validare mapping înainte de import

# FAZA 3 - IMPORT
app:import:execute <source>              # Import dintr-o sursă
app:import:execute-all                   # Import din toate sursele
app:import:rollback <batch-id>           # Rollback batch specific
app:import:verify                        # Verificare integritate post-import

# UTILITARE
app:import:cleanup-registry              # Curățare registry după import
app:import:stats                         # Statistici import
app:import:fix-duplicates                # Fix duplicate găsite post-import
```

---

## Entități Necesare

### 1. ArticleImportRegistry
Vezi detalii la secțiunea 2 "Tabel de Mapping/Registry"

### 2. ImportBatch
```php
ImportBatch:
- id (PK - UUID)
- source (enum)
- started_at (datetime)
- completed_at (datetime - nullable)
- status (enum: running, completed, failed, rolled_back)
- total_items (int)
- imported_items (int)
- failed_items (int)
- skipped_items (int)
- error_log (text - nullable)
- config (JSON - parametri folosiți)
```

### 3. ImportConflict
```php
ImportConflict:
- id (PK)
- registry_entry_1_id (FK către ArticleImportRegistry)
- registry_entry_2_id (FK către ArticleImportRegistry - nullable)
- conflict_type (enum: duplicate, data_mismatch, missing_relation, etc.)
- resolution (enum: auto_resolved, manual_resolved, pending, ignored)
- resolution_rule (string - ce regulă a fost aplicată)
- resolved_by (FK către User - nullable)
- resolved_at (datetime - nullable)
- notes (text)
```

---

## Strategie de Rollback

### Scenarii de Rollback

1. **Rollback complet** - șterge tot ce a fost importat
2. **Rollback per batch** - șterge doar un batch specific
3. **Rollback per sursă** - șterge toate articolele din sursa X

### Implementare

```php
// Soft delete vs hard delete
// Recomandare: soft delete inițial, hard delete după verificare

// Opțiune 1: Flag în Article
Article:
- imported_from (string - nullable)
- import_batch_id (string - nullable)
- is_imported (boolean - default false)

// Opțiune 2: Tracking în registry
ArticleImportRegistry:
- imported_article_id (FK - pe cascade sau nullable)
```

**Comanda rollback:**
```bash
symfony console app:import:rollback --batch=UUID
symfony console app:import:rollback --source=webflow
symfony console app:import:rollback --all --confirm
```

---

## Metrici de Succes

La finalul importului, trebuie verificat:

- [ ] **Completitudine** - toate articolele au fost procesate
- [ ] **Acuratețe** - 0 duplicate în baza finală
- [ ] **Integritate** - toate relațiile (autori, categorii, imagini) sunt corecte
- [ ] **Consistență** - traducerile sunt complete pentru toate limbile
- [ ] **Performanță** - queries pe noul sistem sunt rapide
- [ ] **Audit** - registry complet pentru trasabilitate

**Comenzi verificare:**
```bash
# Count verificare
symfony console app:import:verify --check=counts

# Duplicate verificare
symfony console app:import:verify --check=duplicates

# Relații verificare
symfony console app:import:verify --check=relations

# Raport final
symfony console app:import:verify --full-report
```

---

## Următorii Pași

1. **Colectare informații** despre fiecare sursă (vezi secțiunea "Informații Necesare")
2. **Crearea entităților** ArticleImportRegistry, ImportBatch, ImportConflict
3. **Implementare comenzi FAZA 1** (analyze, report)
4. **Rulare analiză** pe toate sursele
5. **Review raport** și definire reguli de reconciliere
6. **Implementare comenzi FAZA 2** (reconciliere)
7. **Testare pe subset** mic de date
8. **Import complet FAZA 3**
9. **Verificare și validare**
10. **Cleanup și documentare**

---

## Resurse și Referințe

- **Symfony Messenger**: Pentru procesare async (dacă volumul este mare)
- **Doctrine Batch Processing**: https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/batch-processing.html
- **Import existing project**: `sprints/development-plan.md` - referințe la comenzile de import Newscoop

---

**Autor:** Claude Code
**Ultima actualizare:** 2025-11-05
**Status:** Draft - Așteaptă informații despre surse pentru implementare
