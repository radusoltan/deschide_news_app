# Plan de Import Fresh - Deschide News

**Data**: 2025-12-10
**Status**: GATA PENTRU EXECUTIE

---

## Analiza Completata

### HDD Extern
- **Locatie**: `/mnt/d/ext-hdd/backups/alpha/newscoop/images`
- **Total imagini**: **166,170 fisiere**
- **Format**: Fisiere direct in folder (fara subdirectoare)
- **Exemplu fisiere**: `052f0a98fdd8ea794552a8947d3128b3__teodosie-snagoveanu.jpg`

### Spatiu Disk Local
- **Disponibil**: **759 GB** (suficient pentru import + thumbnails)
- **Locatie uploads**: `/var/www/deschide_news_app/apps/backend/public/uploads/`

### Stare Baza de Date Curenta
| Tabel | Total |
|-------|-------|
| articles | 1,000 |
| categories | 12 |
| authors | 282 |
| images | 85 |
| article_image | 0 |
| thumbnails | 0 |

---

## MODIFICARI CRITICE APLICATE

### 1. Calea imaginilor actualizata
```php
// Inainte:
private const NEWSCOOP_IMAGES_PATH = '/home/radu/ext-hdd/backups/alpha/newscoop/images';

// Dupa:
private const NEWSCOOP_IMAGES_PATH = '/mnt/d/ext-hdd/backups/alpha/newscoop/images';
```

### 2. STERGEREA IMAGINILOR DEZACTIVATA
```php
// Inainte (PERICULOS!):
unlink($sourcePath);  // STERGEA imaginea originala!

// Dupa (SIGUR):
// unlink($sourcePath); // DISABLED - keep originals safe!
```

**GARANTIE**: Imaginile de pe HDD-ul extern NU vor fi sterse!

---

## Secventa de Import

### Faza 0: Backup (OBLIGATORIU)
```bash
cd /var/www/deschide_news_app/apps/backend

# Creare backup baza de date
mkdir -p /var/www/deschide_news_app/backups/$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/www/deschide_news_app/backups/$(date +%Y%m%d_%H%M%S)"

PGPASSWORD='iIzmHACi7+W+yq9NFRT2FeadUPAmgEna' pg_dump \
  -h 127.0.0.1 -U deschide_admin -d deschide \
  -F c -f $BACKUP_DIR/deschide_pre_import.dump

echo "Backup creat in: $BACKUP_DIR"
```

### Faza 1: Curatare Date Existente (OPTIONAL - pentru fresh import)
```bash
cd /var/www/deschide_news_app/apps/backend

# ATENTIE: Aceasta sterge toate datele existente!
symfony console doctrine:query:sql "TRUNCATE TABLE article_image CASCADE"
symfony console doctrine:query:sql "TRUNCATE TABLE article_tag CASCADE"
symfony console doctrine:query:sql "TRUNCATE TABLE article_author CASCADE"
symfony console doctrine:query:sql "TRUNCATE TABLE thumbnails CASCADE"
symfony console doctrine:query:sql "TRUNCATE TABLE articles CASCADE"
symfony console doctrine:query:sql "TRUNCATE TABLE images CASCADE"
symfony console doctrine:query:sql "TRUNCATE TABLE authors CASCADE"
symfony console doctrine:query:sql "TRUNCATE TABLE categories CASCADE"
symfony console doctrine:query:sql "TRUNCATE TABLE migration_log CASCADE"
symfony console doctrine:query:sql "TRUNCATE TABLE newscoop_id_mapping CASCADE"

# Verificare
symfony console doctrine:query:sql "SELECT COUNT(*) FROM articles"
```

### Faza 2: Import Categorii (~5 minute)
```bash
cd /var/www/deschide_news_app/apps/backend

# Test dry-run
symfony console app:import:categories --dry-run

# Import real
symfony console app:import:categories

# Verificare
symfony console doctrine:query:sql "SELECT id, name, slug FROM categories ORDER BY id LIMIT 20"
```

### Faza 3: Import Autori (~10 minute)
```bash
# Import autori
symfony console app:import:authors

# Verificare
symfony console doctrine:query:sql "SELECT id, name, slug FROM authors ORDER BY id LIMIT 20"
```

### Faza 4: Import Imagini (~4-8 ore pentru 166K imagini)

**IMPORTANT**: Aceasta este operatia cea mai lunga!

```bash
cd /var/www/deschide_news_app/apps/backend

# Test cu 10 imagini
symfony console app:import:images --limit=10 --dry-run
symfony console app:import:images --limit=10

# Verificare ca imaginile originale sunt intacte
ls /mnt/d/ext-hdd/backups/alpha/newscoop/images/ | wc -l
# Trebuie sa ramana 166,170!

# Import in batch-uri de 1000 (pentru monitorizare)
symfony console app:import:images --limit=1000 --offset=0
symfony console app:import:images --limit=1000 --offset=1000
# ... continua pana la completare

# SAU import complet (va dura mult!)
symfony console app:import:images
```

**Monitorizare progres**:
```bash
# In alt terminal
watch -n 10 'PGPASSWORD="iIzmHACi7+W+yq9NFRT2FeadUPAmgEna" psql -h 127.0.0.1 -U deschide_admin -d deschide -c "SELECT COUNT(*) as imported_images FROM images"'
```

### Faza 5: Import Articole (~30-60 minute)
```bash
# Import articole cu relatii
symfony console app:import:articles-with-relations

# SAU import simplu
symfony console app:import:articles

# Verificare
symfony console doctrine:query:sql "SELECT COUNT(*) FROM articles"
```

### Faza 6: Import Traduceri (~20-30 minute)
```bash
# Import traduceri engleza
symfony console app:import:translations --locale=en

# Import traduceri rusa
symfony console app:import:translations --locale=ru

# Verificare
symfony console doctrine:query:sql "SELECT locale, COUNT(*) FROM ext_translations WHERE object_class LIKE '%Article%' GROUP BY locale"
```

### Faza 7: Generare Thumbnails (ASYNC - 2-4 ore)
```bash
# Porneste worker-ul messenger
symfony console messenger:consume async -vv &

# Genereaza thumbnails
symfony console app:import:generate-thumbnails

# Monitorizare
watch -n 30 'PGPASSWORD="iIzmHACi7+W+yq9NFRT2FeadUPAmgEna" psql -h 127.0.0.1 -U deschide_admin -d deschide -c "SELECT COUNT(*) as thumbnails FROM thumbnails"'
```

---

## Verificare Finala

### 1. Verificare Baza de Date
```bash
PGPASSWORD='iIzmHACi7+W+yq9NFRT2FeadUPAmgEna' psql -h 127.0.0.1 -U deschide_admin -d deschide -c "
SELECT 'articles' as tabel, COUNT(*) FROM articles
UNION ALL SELECT 'categories', COUNT(*) FROM categories
UNION ALL SELECT 'authors', COUNT(*) FROM authors
UNION ALL SELECT 'images', COUNT(*) FROM images
UNION ALL SELECT 'thumbnails', COUNT(*) FROM thumbnails
UNION ALL SELECT 'article_image', COUNT(*) FROM article_image;"
```

### 2. VERIFICARE CRITICA - Imagini HDD Intacte
```bash
# Numara imaginile pe HDD (TREBUIE sa fie 166,170!)
ls /mnt/d/ext-hdd/backups/alpha/newscoop/images/ | wc -l

# Daca numarul e diferit de 166,170, OPRESTE si investigheaza!
```

### 3. Verificare API
```bash
curl http://127.0.0.1:8081/api/articles | jq '.["hydra:totalItems"]'
curl http://127.0.0.1:8081/api/images | jq '.["hydra:totalItems"]'
```

### 4. Verificare Frontend
```bash
# Deschide in browser
# http://localhost:3005/ro
# http://localhost:3005/en
# http://localhost:3005/ru
```

---

## Rollback (daca ceva merge gresit)

```bash
# Restaureaza din backup
BACKUP_FILE="/var/www/deschide_news_app/backups/YYYYMMDD_HHMMSS/deschide_pre_import.dump"

PGPASSWORD='iIzmHACi7+W+yq9NFRT2FeadUPAmgEna' pg_restore \
  -h 127.0.0.1 -U deschide_admin -d deschide \
  -c $BACKUP_FILE

# Sterge fisierele uploadate (daca e necesar)
rm -rf /var/www/deschide_news_app/apps/backend/public/uploads/images/*
rm -rf /var/www/deschide_news_app/apps/backend/public/uploads/thumbnails/*
```

---

## Timeline Estimat

| Faza | Durata | Note |
|------|--------|------|
| Backup | 5 min | Obligatoriu |
| Curatare | 2 min | Optional |
| Categorii | 5 min | ~50 categorii |
| Autori | 10 min | ~300 autori |
| **Imagini** | **4-8 ore** | **166,170 imagini** |
| Articole | 30-60 min | ~10,000 articole |
| Traduceri | 20-30 min | 2 limbi |
| Thumbnails | 2-4 ore | ASYNC |
| **TOTAL** | **8-14 ore** | |

---

## Comenzi Import Disponibile

```bash
symfony console list app:import

# Rezultat:
app:import:articles               # Import articole
app:import:articles-by-category   # Import pe categorii
app:import:articles-with-relations # Import cu relatii
app:import:authors                # Import autori
app:import:authors-direct         # Import autori direct
app:import:categories             # Import categorii
app:import:categories-direct      # Import categorii direct
app:import:complete-categories    # Import categorii complet
app:import:csv-articles           # Import din CSV
app:import:images                 # Import imagini
app:import:translations           # Import traduceri
```

---

## GARANTII DE SIGURANTA

1. **Backup creat** inainte de orice modificare
2. **Imaginile originale NU vor fi sterse** (unlink() dezactivat)
3. **Operatii COPY**, nu MOVE
4. **Verificare la fiecare pas** inainte de continuare
5. **Procedura de rollback** documentata

---

**GATA PENTRU EXECUTIE!**

Doresti sa incep importul? Spune "DA" si voi incepe cu:
1. Crearea backup-ului
2. Import categorii (test)
3. Continuare secventiala

