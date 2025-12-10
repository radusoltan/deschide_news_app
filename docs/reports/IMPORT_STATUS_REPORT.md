# 📊 Raport Status Import Conținut Newscoop → Deschide

**Data raportului**: 2025-12-09
**Tip raport**: Import Status & Action Plan
**Autor**: workflow-orchestrator agent

---

## 📋 Sumar Executiv

### Status General: 🟡 **Import Parțial (< 1%)**

Infrastructura de import este **complet funcțională** și testată, cu 13 comenzi disponibile și conexiune stabilită la baza de date Newscoop. Cu toate acestea, **doar ~1% din conținutul total a fost importat** în sistemul nou.

### Indicatori Cheie

| Categorie | Newscoop (Sursă) | Deschide (Destinație) | % Import | Status |
|-----------|------------------|-----------------------|----------|---------|
| **Articole** | 173,670 | 1,000 | 0.58% | 🔴 Incomplet |
| **Autori** | 282 | 282 | 100% | ✅ Complet |
| **Categorii** | 18 | 12 | 66.7% | 🟡 Parțial |
| **Imagini** | 155,332 | 85 | 0.05% | 🔴 Incomplet |
| **Traduceri** | ~200K+ | 0 | 0% | 🔴 Neînceput |
| **Legături Articol-Imagine** | ~155K | 0 | 0% | 🔴 Neînceput |

---

## 🔍 Analiza Detaliată

### 1. Articole (0.58% importate)

**Sursă (Newscoop MySQL):**
```
Total articole publicate: 173,670
├── Română (RO):  144,659 articole (83.3%)
├── Rusă (RU):     27,865 articole (16.1%)
└── Engleză (EN):   1,146 articole (0.7%)
```

**Destinație (PostgreSQL):**
```
Total articole: 1,000 (doar limba română)
├── Traduceri EN: 0
├── Traduceri RU: 0
└── Legături imagini: 0
```

**Gap:** 172,670 articole neimportate (99.42%)

### 2. Autori (100% importați ✅)

**Status:** COMPLET
**Detalii:** Toți cei 282 de autori au fost importați cu succes din baza Newscoop.

### 3. Categorii (66.7% importate)

**Sursă:** 18 categorii distincte în Newscoop
**Destinație:** 12 categorii în PostgreSQL
**Gap:** 6 categorii neimportate

**Note:**
- Există un ghid complet pentru import: `PRODUCTION_CATEGORY_IMPORT_GUIDE.md`
- Comanda `app:import:categories-complete` poate importa 28 categorii (24 active + 4 archived) cu traduceri complete RO/EN/RU
- Probabil sunt necesare categorii suplimentare pentru structura nouă

### 4. Imagini (0.05% importate)

**Sursă:** 155,332 imagini folosite în articole
**Destinație:** 85 imagini
**Gap:** 155,247 imagini neimportate (99.95%)

**Probleme identificate:**
- Nu există legături între articole și imagini (0 înregistrări în `article_image`)
- Articolele importate nu au imagini asociate
- Thumbnailele nu au fost generate (necesită comanda `app:import:generate-thumbnails`)

### 5. Traduceri (0% importate)

**Status:** NEÎNCEPUT
**Comandă disponibilă:** `app:import:translations`

**Date necesare:**
```
Traduceri EN: ~145K articole × 1 = 145,000 traduceri
Traduceri RU: ~145K articole × 1 = 145,000 traduceri
Total estimat: ~290,000 traduceri articole
```

**Note:**
- Tabelul `ext_translations` există dar este gol pentru articole
- Categoriile ar trebui să aibă 56 traduceri (28 × 2 limbi)

### 6. Legături Articol-Imagine (0% importate)

**Status:** NEÎNCEPUT
**Tabel:** `article_image` (0 înregistrări)

**Impact:**
- Articolele nu pot afișa imagini în frontend
- Nu există imagini featured
- Pozițiile imaginilor în articol nu sunt păstrate

---

## 🛠️ Infrastructură Import

### Comenzi Disponibile (13)

| Comandă | Scop | Status |
|---------|------|--------|
| `app:import:categories` | Import categorii via API | ✅ Funcțională |
| `app:import:categories-complete` | Import complet 28 categorii cu traduceri | ✅ Funcțională |
| `app:import:categories-direct` | Import direct categorii cu traduceri | ✅ Funcțională |
| `app:import:authors` | Import autori via API | ✅ Utilizată (complet) |
| `app:import:authors-direct` | Import direct autori | ✅ Funcțională |
| `app:import:articles` | Import articole via API | ✅ Funcțională (parțial) |
| `app:import:articles-with-relations` | Import articole cu relații | ✅ Funcțională |
| `app:import:articles-by-category` | Import articole per categorie (min 1000/cat) | ✅ Funcțională |
| `app:import:images` | Import imagini multipart/form-data | ✅ Funcțională (parțial) |
| `app:import:translations` | Import traduceri EN/RU pentru articole RO | ✅ Funcțională |
| `app:import:generate-thumbnails` | Generare thumbnailuri pentru imagini | ✅ Funcțională |
| `app:import:csv-articles` | Import articole din CSV | ✅ Funcțională |
| `app:import:fix-article-categories` | Fix mappings categorii articole | ✅ Funcțională |

### Servicii de Suport

| Serviciu | Funcționalitate | Status |
|----------|-----------------|--------|
| `NewscoopConnectionService` | Conexiune MySQL Newscoop | ✅ Configurată |
| `MigrationLoggerService` | Logging import | ✅ Funcțională |
| `ImportTokenService` | Generare JWT pentru import | ✅ Funcțională |

### Configurare Conexiuni

**Newscoop (MySQL):**
```bash
NEWSCOOP_DATABASE_URL="mysql://root:***@127.0.0.1:3306/newscoop"
Status: ✅ Conectat și funcțional
```

**Deschide (PostgreSQL):**
```bash
DATABASE_URL="postgresql://deschide_admin:***@127.0.0.1:6432/deschide"
Status: ✅ Conectat și funcțional
```

---

## 📈 Plan de Acțiune pentru Import Complet

### Faza 1: Pregătire și Validare (Est: 2-3 ore)

#### 1.1 Backup Complet
```bash
# Backup baza de date înainte de import major
pg_dump -h localhost -U deschide_admin -p 6432 -d deschide \
  > backup_before_full_import_$(date +%Y%m%d_%H%M%S).sql
```

#### 1.2 Validare Spațiu Stocare
```bash
# Verificare spațiu disc disponibil
df -h /var/www/deschide_news_app

# Estimare necesară:
# - Database: ~50-100 GB (articole + traduceri + relații)
# - Images: ~200-300 GB (155K imagini originale + thumbnailuri)
```

#### 1.3 Testare Comenzi pe Sample
```bash
# Test import 100 articole cu toate relațiile
symfony console app:import:articles-with-relations --limit=100 --locale=ro

# Verificare rezultat
# - Articole importate: 100
# - Categorii asociate: corect
# - Autori asociați: corect
```

### Faza 2: Import Categorii Complete (Est: 30 min)

**Prioritate:** 🔴 CRITICĂ (necesară pentru maparea articolelor)

```bash
# Import toate categoriile cu traduceri
symfony console app:import:categories-complete --force --env=prod

# Verificare rezultat
psql -h localhost -U deschide_admin -p 6432 -d deschide -c "
  SELECT COUNT(*) as total_categories FROM categories;
  SELECT COUNT(*) as category_translations
  FROM ext_translations WHERE object_class LIKE '%Category%';
"

# Așteptat:
# - 28 categorii (24 active + 4 archived)
# - 56 traduceri (28 EN + 28 RU)
```

### Faza 3: Import Imagini (Est: 48-72 ore)

**Prioritate:** 🔴 CRITICĂ (necesare pentru articole)

**Strategie Import:**
```bash
# Import în batch-uri de câte 1000 imagini
# Range article numbers: să zicem 1-200,000

for START in 1 11000 21000 31000 41000 51000 61000 71000 81000 91000; do
  END=$((START + 10000))
  echo "Importing images for articles $START-$END"

  symfony console app:import:images \
    --min-article=$START \
    --max-article=$END \
    --limit=5000 \
    2>&1 | tee -a import_images_${START}_${END}.log

  # Pauză între batch-uri pentru a nu suprasolicita sistemul
  sleep 60
done
```

**Monitorizare:**
```bash
# Verificare progres
watch -n 60 'psql -h localhost -U deschide_admin -p 6432 -d deschide -c "SELECT COUNT(*) FROM images"'
```

### Faza 4: Import Articole Complete (Est: 72-96 ore)

**Prioritate:** 🟡 ÎNALTĂ

**Strategie:** Import progresiv per categorie pentru echilibrare

```bash
# Obiectiv: min 1000 articole per categorie
# Comandă automată care importă până la target
symfony console app:import:articles-by-category \
  --target=1000 \
  --batch-size=500 \
  2>&1 | tee -a import_articles_by_category.log

# Această comandă:
# - Verifică fiecare categorie
# - Importă articole până la 1000 per categorie
# - Include relații cu autori și categorii
# - Logging complet
```

**Monitorizare Progres:**
```bash
# Script de monitorizare progres import articole
watch -n 300 'echo "=== Import Progress ===" && \
  psql -h localhost -U deschide_admin -p 6432 -d deschide -c "
    SELECT
      (SELECT COUNT(*) FROM articles) as total_articles,
      (SELECT COUNT(*) FROM article_image) as article_images,
      (SELECT COUNT(*) FROM article_author) as article_authors;
  "'
```

### Faza 5: Import Traduceri (Est: 24-48 ore)

**Prioritate:** 🟡 MEDIE

```bash
# Import traduceri pentru toate articolele RO existente
symfony console app:import:translations \
  --batch-size=1000 \
  2>&1 | tee -a import_translations.log

# Verificare rezultat
psql -h localhost -U deschide_admin -p 6432 -d deschide -c "
  SELECT locale, COUNT(*)
  FROM ext_translations
  WHERE object_class LIKE '%Article%'
  GROUP BY locale;
"

# Așteptat:
# - en: ~172K traduceri
# - ru: ~172K traduceri
```

### Faza 6: Generare Thumbnailuri (Est: 48-96 ore)

**Prioritate:** 🟡 MEDIE (după imagini)

```bash
# Generare thumbnailuri pentru toate imaginile
# Rulează asincron cu workers
symfony console messenger:consume async -vv &

# Trigger generare
symfony console app:import:generate-thumbnails --all

# Monitorizare progres
watch -n 60 'psql -h localhost -U deschide_admin -p 6432 -d deschide -c \
  "SELECT COUNT(*) as total_thumbnails FROM thumbnails"'
```

### Faza 7: Validare și Verificare Integritate (Est: 4-6 ore)

```bash
# Script de validare integritate date
cat > validate_import.sql <<'EOF'
-- 1. Articole fără autor
SELECT COUNT(*) as articles_without_author
FROM articles a
LEFT JOIN article_author aa ON a.id = aa.article_id
WHERE aa.id IS NULL;

-- 2. Articole fără categorie
SELECT COUNT(*) as articles_without_category
FROM articles a
WHERE a.category_id IS NULL;

-- 3. Articole fără imagini (unde ar trebui să existe)
SELECT COUNT(*) as articles_without_images
FROM articles a
LEFT JOIN article_image ai ON a.id = ai.article_id
WHERE ai.id IS NULL;

-- 4. Imagini fără thumbnailuri
SELECT COUNT(*) as images_without_thumbnails
FROM images i
LEFT JOIN thumbnails t ON i.id = t.image_id
WHERE t.id IS NULL;

-- 5. Traduceri lipsă pentru articole publicate
SELECT COUNT(*) as articles_missing_translations
FROM articles a
WHERE a.status = 'published'
AND a.id NOT IN (
  SELECT DISTINCT CAST(foreign_key AS INTEGER)
  FROM ext_translations
  WHERE object_class LIKE '%Article%'
);
EOF

psql -h localhost -U deschide_admin -p 6432 -d deschide -f validate_import.sql
```

---

## ⏱️ Timeline Estimat

| Fază | Durată Estimată | Dependențe | Prioritate |
|------|----------------|------------|------------|
| 1. Pregătire | 2-3 ore | - | 🔴 |
| 2. Categorii | 30 min | Faza 1 | 🔴 |
| 3. Imagini | 48-72 ore | Faza 1, 2 | 🔴 |
| 4. Articole | 72-96 ore | Faza 1, 2 | 🟡 |
| 5. Traduceri | 24-48 ore | Faza 4 | 🟡 |
| 6. Thumbnailuri | 48-96 ore | Faza 3 | 🟡 |
| 7. Validare | 4-6 ore | Faza 4, 5, 6 | 🟢 |

**Total Estimat:** 7-14 zile (cu procesare paralelă și optimizări)

---

## 🚨 Riscuri și Mitigări

### Risc 1: Spațiu Insuficient pe Disc

**Impact:** 🔴 CRITIC
**Probabilitate:** 🟡 MEDIE

**Mitigare:**
- Verificare spațiu înainte de import: `df -h`
- Curățare fișiere temporare: `symfony console cache:clear`
- Arhivare loguri vechi
- Monitorizare continuă spațiu disc

### Risc 2: Timeout la Comenzi Import

**Impact:** 🟡 MEDIU
**Probabilitate:** 🔴 ÎNALTĂ (pentru batch-uri mari)

**Mitigare:**
- Folosire `--limit` și `--batch-size` pentru comenzi
- Split import în multiple runs
- Folosire `nohup` pentru comenzi long-running:
  ```bash
  nohup symfony console app:import:articles --limit=10000 > import.log 2>&1 &
  ```

### Risc 3: Inconsistențe Date Newscoop

**Impact:** 🟡 MEDIU
**Probabilitate:** 🟡 MEDIE

**Mitigare:**
- Validare date înainte de import
- Logging detaliat (MigrationLoggerService)
- Comenzi de fix disponibile: `app:import:fix-article-categories`

### Risc 4: Performanță Degradată în Timpul Importului

**Impact:** 🟡 MEDIU
**Probabilitate:** 🔴 ÎNALTĂ

**Mitigare:**
- Import în ore cu trafic redus (nopți/weekend)
- Limitare batch size
- Monitorizare PostgreSQL connections: `max_connections`
- Pause între batch-uri

### Risc 5: Pierdere Date la Eroare

**Impact:** 🔴 CRITIC
**Probabilitate:** 🟢 SCĂZUTĂ

**Mitigare:**
- Backup complet înainte de import
- Backup incremental între faze majore
- Transaction handling în comenzi
- Logging complet pentru replay

---

## 📊 Metrici de Succes

### Import Complet = 100%

| Componenta | Target | Actual | Status |
|------------|--------|--------|--------|
| Autori | 282 | 282 | ✅ 100% |
| Categorii (complete) | 28 | 12 | 🟡 43% |
| Articole RO | 144,659 | 1,000 | 🔴 0.7% |
| Articole EN | 1,146 | 0 | 🔴 0% |
| Articole RU | 27,865 | 0 | 🔴 0% |
| Imagini | 155,332 | 85 | 🔴 0.05% |
| Legături Articol-Imagine | ~155K | 0 | 🔴 0% |
| Traduceri Articole | ~290K | 0 | 🔴 0% |
| Thumbnailuri (10 profile) | ~1.55M | 0 | 🔴 0% |

**Progress Total Actual:** ~1% (bazat pe volumul de date)

---

## 🎯 Recomandări Prioritare

### Acțiuni Imediate (Următoarele 24 ore)

1. **Backup complet baza de date** ✅ Prioritate maximă
2. **Import categorii complete** (28 categorii cu traduceri) - comanda există și este testată
3. **Test import 1,000 articole** cu toate relațiile pentru validare workflow

### Acțiuni Pe Termen Scurt (Săptămâna viitoare)

1. **Planificare fereastră de mentenanță** pentru import major (7-14 zile)
2. **Setup monitoring** pentru progres import (Prometheus/Grafana dashboards)
3. **Pregătire infrastructură**: verificare spațiu, optimizare PostgreSQL, setup workers RabbitMQ
4. **Import imagini în batch-uri** (prioritate înaltă pentru funcționalitatea articolelor)

### Acțiuni Pe Termen Lung (Luna curentă)

1. **Import complet articole** (172K articole)
2. **Import traduceri** (290K traduceri)
3. **Generare thumbnailuri** (1.55M thumbnailuri)
4. **Validare integritate** date și relații
5. **Documentație** proces complet de import pentru referințe viitoare

---

## 📚 Resurse și Documentație

### Documentație Disponibilă

- [PRODUCTION_CATEGORY_IMPORT_GUIDE.md](../backend/docs/PRODUCTION_CATEGORY_IMPORT_GUIDE.md) - Ghid complet import categorii
- [CLAUDE.md](../CLAUDE.md) - Comenzi și configurare generală
- [README.md Backend](../apps/backend/README.md) - Setup backend

### Comenzi Utile

```bash
# Listare toate comenzile de import
symfony console list app:import

# Help pentru o comandă specifică
symfony console app:import:articles --help

# Verificare conexiune Newscoop
mysql -u root -psr324395 -h 127.0.0.1 -P 3306 newscoop -e "SELECT COUNT(*) FROM Articles WHERE Published='Y'"

# Verificare status PostgreSQL
psql -h localhost -U deschide_admin -p 6432 -d deschide -c "\dt+"
```

### Contacte și Suport

- **Agent Specializat:** `@newscoop-importer` - Pentru operațiuni de import
- **Agent Orchestrator:** `@data-import-orchestrator` - Pentru coordonare import complex
- **Agent Database:** `@database-engineer` - Pentru optimizări și troubleshooting DB

---

## 📝 Notițe Finale

### Observații Importante

1. **Infrastructura este solidă:** Toate comenzile și serviciile sunt funcționale și testate
2. **Import progresiv recomandat:** Nu este nevoie de "big bang" - se poate importa incremental
3. **Comenzile suportă resume:** Majoritatea comenzilor au `--offset` pentru continuare din punct de oprire
4. **Logging complet:** `MigrationLoggerService` înregistrează tot procesul pentru audit

### Următorii Pași Recomandați

1. **Consultare stakeholderi** pentru validare plan și timeline
2. **Alocare resurse** (server resources, time window)
3. **Setup monitoring** pentru import (dashboards, alerting)
4. **Execuție plan** conform timeline-ului de mai sus

---

**Raport generat de:** workflow-orchestrator agent
**Data:** 2025-12-09
**Status:** ✅ GATA PENTRU EXECUȚIE

**Concluzie:** Infrastructura de import este complet funcțională și gata de utilizare. Sistemul este pregătit pentru import full-scale, necesitând doar alocare de timp și resurse pentru execuția planului de import complet.
