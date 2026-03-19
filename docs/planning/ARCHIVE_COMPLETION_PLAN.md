# Plan de Completare a Arhivei Deschide.md

**Versiune:** 2.0
**Data:** 2025-12-15
**Autor:** Product Strategy Team
**Status:** Draft - ACTUALIZAT cu analiza completa

---

## Executive Summary

Acest document prezinta planul strategic pentru completarea arhivei de stiri deschide.md. Analiza din 15 Dec 2025 a identificat **lacune semnificative** intre datele din bazele de date sursa si arhiva ES.

### ALERTA: Articole Beta_deschide (2013-2016) COMPLET LIPSA!

**~21,361 articole din perioada 2013-2015 NU EXISTA in arhiva ES!**

Aceste articole se afla in baza de date `beta_deschide` (MariaDB) si trebuie importate separat.

### Metrici Cheie ACTUALIZATE (15 Dec 2025)

| Sursa | Articole | Imagini DB | Imagini Fisiere |
|-------|----------|------------|-----------------|
| **ES Archive (Droplet)** | 167,550 | - | 195,849 |
| **Newscoop (MariaDB)** | 173,670 | 165,302 | - |
| **Beta_deschide (MariaDB)** | 30,242 | 44,686 | - |
| **Total Surse** | ~203,912 | 209,988 | - |

### Gap Analysis

| Categorie | Lipsa Estimat | Prioritate |
|-----------|---------------|------------|
| Articole 2013-2015 (beta) | ~21,361 | CRITICA |
| Articole Newscoop gaps | ~8,000 | INALTA |
| Imagini lipsa pe droplet | ~14,139 | MEDIE |
| Traduceri EN/RU gaps | TBD | MEDIE-JOASA |

### Metrici Vechi (pentru referinta)

| Metrica | Valoare | Sursa |
|---------|---------|-------|
| Articole RO lipsa | 11,874 | `/tmp/missing_ro_ids.txt` |
| Articole RU lipsa | 11,103 | `/tmp/missing_ru_ids.txt` |
| Total de importat | ~22,977 | Analiza comparativa |
| Tipuri articole | stiri (12,740), ultrascurte (172), video (40) | Newscoop DB |

---

## 1. Analiza Situatiei Curente

### 1.1 Infrastructura

| Componenta | Detalii | Status |
|------------|---------|--------|
| **Droplet DO** | 165.22.89.204, SSH root cu cheie | Operational |
| **Elasticsearch** | https://localhost:9200, API Key auth | Operational |
| **MariaDB Local** | WSL, baze: newscoop, beta_deschide | Operational |
| **Imagini HDD Local** | D:\ext-hdd\ (vezi detalii mai jos) | ✅ VERIFICAT |

### 1.1.1 Locatii Imagini pe HDD Local (ACTUALIZAT 15 Dec 2025)

**Drive:** `D:\ext-hdd\` (accesibil din WSL ca `/mnt/d/ext-hdd/`)

| Folder | Continut | Descriere |
|--------|----------|-----------|
| `/mnt/d/ext-hdd/alpha/` | Imagini Newscoop | Hash prefix + filename original |
| `/mnt/d/ext-hdd/beta/images/` | Imagini Beta_deschide | ~9,058 fisiere (SHA1 hash) |
| `/mnt/d/ext-hdd/backups/alpha/newscoop/` | Backup complet Newscoop | Include images + app |
| `/mnt/d/ext-hdd/backups/beta/beta/images/` | Backup imagini Beta | Similar cu beta/images |

**Format filename Alpha (Newscoop):**
```
052f0a98fdd8ea794552a8947d3128b3__teodosie-snagoveanu-mediafax-foto.jpg
[MD5_HASH]__[original_filename].[ext]
```

**Format filename Beta:**
```
128b2c3bfa5b707366d05786a86eed135d74d141.jpg
[SHA1_HASH].[ext]
```

### 1.1.2 Imagini pe Droplet (ACTUALIZAT 15 Dec 2025)

| Folder pe Droplet | Fisiere | Dimensiune |
|-------------------|---------|------------|
| `/var/www/html/alpha/` | 155,323 | 18 GB |
| `/var/www/html/beta/` | 40,526 | 3.3 GB |
| **TOTAL** | **195,849** | **21.3 GB** |

### 1.1.3 Discrepante Imagini

| Sursa | In DB | Pe Disk | Lipsa |
|-------|-------|---------|-------|
| Newscoop | 165,302 | 155,323 | ~9,979 |
| Beta_deschide | 44,686 | 40,526 | ~4,160 |
| **TOTAL** | **209,988** | **195,849** | **~14,139** |

**Actiune necesara:** Transfer ~14,139 imagini lipsa din backup local pe droplet

### 1.2 PROCESARE CONȚINUT - CRITICĂ! (ACTUALIZAT 15 Dec 2025)

**⚠️ FĂRĂ PROCESARE, ARHIVA VA FI INUTILIZABILĂ!**

#### Statistici Shortcodes în Baze de Date

| Shortcode | Newscoop | Beta_deschide | Total | Impact |
|-----------|----------|---------------|-------|--------|
| `<!** Image X>` | 16,182 (9.2%) | 4,046 (13.4%) | **20,228** | ⚠️ CRITIC |
| `<!** Link Internal>` | 3 | 2 | 5 | Minor |
| `<!** Title>` | 0 | 0 | 0 | N/A |
| `<iframe>` embeds | 15,734 | 1,935 | 17,669 | Păstrare |
| YouTube | 8,326 | 1,145 | 9,471 | Păstrare |
| `<img>` HTML | 1,106 | 1 | 1,107 | OK |

**ATENȚIE:** 20,228 articole (10%+) vor avea imagini broken fără procesare!

#### Format Shortcode Image - DESCOPERIRE IMPORTANTĂ!

**Exemplu real din articol 122:**
```html
<p><strong><!** Image 3 align="middle" width="700"></strong></p>
<p><strong><!** Image 1 align="middle" width="700"></strong></p>
```

**IMPORTANT:** Numărul din shortcode (1, 3) este **POZIȚIA în ArticleImages**, NU Image ID!

**Mapare pentru articol 122:**
| Shortcode | Attachment # | Image ID | Filename |
|-----------|--------------|----------|----------|
| `<!** Image 1` | 1 | 158 | cms-image-000000158.jpg |
| `<!** Image 3` | 3 | 160 | cms-image-000000160.jpg |

#### Algoritm de Transformare

```python
# 1. Parse shortcode
# <!** Image 3 align="middle" width="700">
#          ^-- attachment_number (poziție în ArticleImages)

# 2. Lookup în ArticleImages
# SELECT IdImage FROM ArticleImages
# WHERE NrArticle = ? AND Number = attachment_number

# 3. Obține detalii imagine
# SELECT ImageFileName FROM Images WHERE Id = IdImage
# → cms-image-000000160.jpg

# 4. Generează HTML modern
# <figure class="align-middle">
#   <img src="/images/alpha/cms-image-000000160.jpg"
#        width="700" loading="lazy">
# </figure>
```

#### Shortcode Link Internal

**Exemplu din beta_deschide:**
```html
<!** Link Internal IdPublication=1&IdLanguage=2&NrIssue=1&NrSection=1&NrArticle=26 TARGET _blank>CNA a ridicat jumătate de milion de euro<!** EndLink>
```

**Transformare:**
```html
<a href="/arhiva/article/26" target="_blank">CNA a ridicat jumătate de milion de euro</a>
```

#### Serviciu Necesar: ContentProcessor

**Locație:** `src/Service/Archive/ContentProcessor.php`

**Metode:**
- `processContent(string $content, int $articleNumber, string $source): string`
- `processImageShortcodes(string $content, int $articleNumber): string`
- `processInternalLinks(string $content): string`
- `sanitizeHtml(string $content): string`

**Dependințe:**
- Acces la tabel ArticleImages pentru mapare
- Acces la tabel Images pentru filename
- Path imagini: `/var/www/html/alpha/` (pe droplet)

#### Regex Patterns

**Image Shortcode:**
```regex
/<!\\*\\* Image (\\d+)(?: align="([^"]*)")?(?: alt="([^"]*)")?(?: sub="([^"]*)")?(?: width="([^"]*)")?(?: height="([^"]*)")?>/
```

**Internal Link:**
```regex
/<!\\*\\* Link Internal ([^>]+)(?:TARGET ([^>]+))?>(.*?)<!\\*\\* EndLink>/s
```

### 1.3 Mapare Campuri (Newscoop -> Elasticsearch)

```
ES Field          <- Newscoop Source
-------------------------------------------------
article_id        <- Articles.Number
title             <- Xstiri.FTitlu / Articles.Name
slug              <- Generat din titlu (transliterat)
lead              <- Xstiri.Flead
content           <- Xstiri.FContinut (BLOB->UTF8)
language          <- Articles.IdLanguage (2=ro, 15=ru, 1=en)
published_at      <- Articles.PublishDate
category          <- Sections (id, name, slug)
authors           <- ArticleAuthors + Authors
images            <- ArticleImages + Images
```

### 1.3 Tipuri de Articole si Surse

| Tip | Count | Tabel Continut | Campuri Speciale |
|-----|-------|----------------|------------------|
| stiri | 12,740 | Xstiri | FTitlu, Flead, FContinut |
| ultrascurte | 172 | Xultrascurte | FContinut (scurt) |
| video | 40 | Xvideo | FVideoUrl, FDescription |

---

## 2. Faze de Implementare

### FAZA 0 (NOUA): Import Beta_deschide 2013-2016 (Prioritate CRITICA)

**Obiectiv:** Import ~21,361 articole din perioada 2013-2015 care LIPSESC COMPLET din arhiva ES

**ATENTIE:** Aceasta este cea mai importanta faza deoarece aceste articole nu au fost niciodata importate!

#### Analiza Beta_deschide pe Ani

| An | Articole | Status in ES |
|----|----------|--------------|
| 2013 | ~5,234 | LIPSA COMPLET |
| 2014 | ~7,891 | LIPSA COMPLET |
| 2015 | ~8,236 | LIPSA COMPLET |
| 2016 | ~8,881 | PARTIAL (overlapping cu newscoop) |

#### Task 0.1: Explorare Structura Beta_deschide

```sql
-- Verificare tabele continut
SHOW TABLES LIKE 'X%';  -- Xstire, Xcitat, Xembed, etc.

-- Count articole published
SELECT YEAR(PublishDate) as year, COUNT(*) as count
FROM Articles
WHERE Published = 'Y'
GROUP BY YEAR(PublishDate)
ORDER BY year;

-- Verificare camp continut
SELECT NrArticle, LEFT(Fcontinut, 200) as sample
FROM Xstire
LIMIT 5;
```

#### Task 0.2: Creare Script Export Beta_deschide

**Diferente fata de Newscoop:**
- Tabel continut: `Xstire` (nu `Xstiri`)
- Posibil structura diferita a campurilor
- ID-uri diferite pentru limbi (de verificat)

```python
# beta_extractor.py - adaptat pentru beta_deschide
class BetaDeschideExtractor:
    def _get_content(self, article_id, language_id):
        # Try Xstire (beta format)
        self.cursor.execute("""
            SELECT Fcontinut as content, Ftitlu as title
            FROM Xstire
            WHERE NrArticle = %s AND IdLanguage = %s
        """, (article_id, language_id))
        # ... rest of logic
```

#### Task 0.3: Mapare Imagini Beta

```bash
# Imagini beta folosesc format diferit:
# [SHA1_HASH].jpg vs [MD5]__[name].jpg pentru alpha

# Verificare pe droplet
ssh root@165.22.89.204 "ls /var/www/html/beta/ | head -5"
# Output: 128b2c3bfa5b707366d05786a86eed135d74d141.jpg
```

#### Task 0.4: Test Import 100 articole Beta

```bash
# Export 100 articole test
python3 scripts/archive_import/extract_beta_articles.py \
  --limit 100 \
  --output /tmp/beta_test.json

# Import in ES
python3 scripts/archive_import/load_elasticsearch.py \
  --input /tmp/beta_test.json \
  --index articles
```

#### Task 0.5: Import Complet Beta_deschide

- Prioritate: 2013 → 2014 → 2015 → 2016
- Batch size: 500 articole
- Checkpoint la fiecare batch

#### Deliverables Faza 0:
- [ ] Script export beta_deschide functional
- [ ] ~21,361 articole 2013-2015 importate
- [ ] Imagini beta linkate corect
- [ ] Validare calitate date beta

---

### Faza 1: Pregatire si Validare (Zile 1-2)

**Obiectiv:** Pregatirea infrastructurii si validarea datelor sursa

#### Task 1.1: Validare Fisiere ID-uri
```bash
# Verificare integritate fisiere
wc -l /tmp/missing_ro_ids.txt  # Expected: 11,874
wc -l /tmp/missing_ru_ids.txt  # Expected: 11,103

# Verificare format (un ID per linie, numeric)
head -20 /tmp/missing_ro_ids.txt
grep -v '^[0-9]*$' /tmp/missing_ro_ids.txt | wc -l  # Should be 0
```

**Agent responsabil:** `database-engineer`

#### Task 1.2: Clarificare Path Imagini
- [ ] Identificare exact path-ul imaginilor pe HDD extern
- [ ] Verificare acces si permisiuni
- [ ] Mapare structura directoare imagini

**Agent responsabil:** `database-engineer`

#### Task 1.3: Backup Elasticsearch
```bash
# SSH pe droplet
ssh root@165.22.89.204

# Creare snapshot repository (daca nu exista)
curl -X PUT "https://localhost:9200/_snapshot/archive_backup" \
  -H "Authorization: ApiKey YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "fs",
    "settings": {
      "location": "/var/backups/elasticsearch/archive"
    }
  }'

# Creare snapshot inainte de import
curl -X PUT "https://localhost:9200/_snapshot/archive_backup/pre_import_$(date +%Y%m%d)" \
  -H "Authorization: ApiKey YOUR_API_KEY"
```

**Agent responsabil:** `database-engineer`

#### Task 1.4: Verificare Conectivitate
```bash
# Test conexiune MariaDB local
mysql -u root -p newscoop -e "SELECT COUNT(*) FROM Articles;"

# Test conexiune ES remote (via SSH tunnel daca necesar)
ssh -L 9201:localhost:9200 root@165.22.89.204
curl -k https://localhost:9201/_cluster/health
```

**Agent responsabil:** `backend-api-tester`

#### Deliverables Faza 1:
- [ ] Fisiere ID-uri validate
- [ ] Path imagini documentat
- [ ] Backup ES creat
- [ ] Conectivitate confirmata

---

### Faza 2: Dezvoltare Script Import (Zile 3-5)

**Obiectiv:** Creare script de import robust si testabil

#### Task 2.1: Script Extragere MariaDB

**Locatie:** `/var/www/deschide_news_app/scripts/archive_import/extract_articles.py`

```python
#!/usr/bin/env python3
"""
Extract articles from Newscoop MariaDB for archive import.
Supports: stiri, ultrascurte, video article types.
"""

import mysql.connector
import json
import logging
from typing import Iterator, Dict, Any
from pathlib import Path

class NewscoopExtractor:
    LANGUAGE_MAP = {2: 'ro', 15: 'ru', 1: 'en'}

    def __init__(self, config: Dict[str, str]):
        self.conn = mysql.connector.connect(**config)
        self.cursor = self.conn.cursor(dictionary=True)

    def extract_article(self, article_id: int, language_id: int) -> Dict[str, Any]:
        """Extract single article with all related data."""
        # Main article query
        query = """
        SELECT
            a.Number as article_id,
            a.Name as title,
            a.PublishDate as published_at,
            a.IdLanguage as language_id,
            s.Id as section_id,
            s.Name as category_name
        FROM Articles a
        LEFT JOIN Sections s ON a.IdSection = s.Id
        WHERE a.Number = %s AND a.IdLanguage = %s
        """
        self.cursor.execute(query, (article_id, language_id))
        article = self.cursor.fetchone()

        if not article:
            return None

        # Get content based on article type
        content = self._get_content(article_id, language_id)

        # Get authors
        authors = self._get_authors(article_id)

        # Get images
        images = self._get_images(article_id)

        return {
            'article_id': article['article_id'],
            'title': content.get('title') or article['title'],
            'slug': self._generate_slug(content.get('title') or article['title']),
            'lead': content.get('lead', ''),
            'content': content.get('content', ''),
            'language': self.LANGUAGE_MAP.get(language_id, 'ro'),
            'published_at': article['published_at'].isoformat() if article['published_at'] else None,
            'category': {
                'id': article['section_id'],
                'name': article['category_name'],
                'slug': self._generate_slug(article['category_name'])
            },
            'authors': authors,
            'images': images
        }

    def _get_content(self, article_id: int, language_id: int) -> Dict[str, str]:
        """Get article content from type-specific table."""
        # Try Xstiri first (most common)
        self.cursor.execute("""
            SELECT FTitlu as title, Flead as lead, FContinut as content
            FROM Xstiri
            WHERE NrArticle = %s AND IdLanguage = %s
        """, (article_id, language_id))
        result = self.cursor.fetchone()
        if result:
            return {
                'title': result['title'],
                'lead': result['lead'],
                'content': self._decode_blob(result['content'])
            }

        # Try Xultrascurte
        self.cursor.execute("""
            SELECT FContinut as content
            FROM Xultrascurte
            WHERE NrArticle = %s AND IdLanguage = %s
        """, (article_id, language_id))
        result = self.cursor.fetchone()
        if result:
            return {'content': self._decode_blob(result['content'])}

        # Try Xvideo
        self.cursor.execute("""
            SELECT FVideoUrl as video_url, FDescription as content
            FROM Xvideo
            WHERE NrArticle = %s AND IdLanguage = %s
        """, (article_id, language_id))
        result = self.cursor.fetchone()
        if result:
            return {'content': result['content'], 'video_url': result['video_url']}

        return {}

    def _decode_blob(self, blob_data) -> str:
        """Convert BLOB to UTF-8 string."""
        if isinstance(blob_data, bytes):
            return blob_data.decode('utf-8', errors='replace')
        return str(blob_data) if blob_data else ''

    def _get_authors(self, article_id: int) -> list:
        """Get article authors."""
        self.cursor.execute("""
            SELECT a.Id, a.Name, a.Email
            FROM Authors a
            JOIN ArticleAuthors aa ON a.Id = aa.fk_author_id
            WHERE aa.fk_article_number = %s
        """, (article_id,))
        return [{'id': r['Id'], 'name': r['Name'], 'email': r['Email']}
                for r in self.cursor.fetchall()]

    def _get_images(self, article_id: int) -> list:
        """Get article images."""
        self.cursor.execute("""
            SELECT i.Id, i.Location as path, i.Description as alt
            FROM Images i
            JOIN ArticleImages ai ON i.Id = ai.IdImage
            WHERE ai.NrArticle = %s
        """, (article_id,))
        return [{'id': r['Id'], 'path': r['path'], 'alt': r['alt']}
                for r in self.cursor.fetchall()]

    def _generate_slug(self, text: str) -> str:
        """Generate URL-friendly slug from text."""
        import re
        import unicodedata
        if not text:
            return ''
        # Normalize and transliterate
        text = unicodedata.normalize('NFKD', text)
        text = text.encode('ascii', 'ignore').decode('ascii')
        text = text.lower()
        text = re.sub(r'[^a-z0-9]+', '-', text)
        text = text.strip('-')
        return text[:200]  # Limit length
```

**Agent responsabil:** `database-engineer`

#### Task 2.2: Script Incarcare Elasticsearch

**Locatie:** `/var/www/deschide_news_app/scripts/archive_import/load_elasticsearch.py`

```python
#!/usr/bin/env python3
"""
Load extracted articles into Elasticsearch on remote server.
Uses bulk API for performance.
"""

from elasticsearch import Elasticsearch, helpers
import json
import logging
from typing import Iterator, Dict, Any
from pathlib import Path

class ElasticsearchLoader:
    BATCH_SIZE = 500
    INDEX_NAME = 'deschide_articles'  # Adjust based on actual index

    def __init__(self, es_host: str, api_key: str, verify_ssl: bool = True):
        self.es = Elasticsearch(
            es_host,
            api_key=api_key,
            verify_certs=verify_ssl
        )

    def bulk_index(self, articles: Iterator[Dict[str, Any]]) -> Dict[str, int]:
        """Bulk index articles with progress tracking."""
        stats = {'success': 0, 'failed': 0, 'errors': []}

        def generate_actions():
            for article in articles:
                yield {
                    '_index': self.INDEX_NAME,
                    '_id': f"{article['language']}_{article['article_id']}",
                    '_source': article
                }

        for ok, result in helpers.streaming_bulk(
            self.es,
            generate_actions(),
            chunk_size=self.BATCH_SIZE,
            raise_on_error=False
        ):
            if ok:
                stats['success'] += 1
            else:
                stats['failed'] += 1
                stats['errors'].append(result)

        return stats

    def verify_import(self, article_ids: list, language: str) -> Dict[str, list]:
        """Verify which articles were successfully imported."""
        result = {'found': [], 'missing': []}

        for article_id in article_ids:
            doc_id = f"{language}_{article_id}"
            if self.es.exists(index=self.INDEX_NAME, id=doc_id):
                result['found'].append(article_id)
            else:
                result['missing'].append(article_id)

        return result
```

**Agent responsabil:** `database-engineer`

#### Task 2.3: Script Orchestrare Import

**Locatie:** `/var/www/deschide_news_app/scripts/archive_import/orchestrate_import.py`

```python
#!/usr/bin/env python3
"""
Orchestrate the complete archive import process.
Handles batching, progress tracking, and error recovery.
"""

import argparse
import json
import logging
from datetime import datetime
from pathlib import Path
from typing import List

from extract_articles import NewscoopExtractor
from load_elasticsearch import ElasticsearchLoader

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(f'import_{datetime.now().strftime("%Y%m%d_%H%M%S")}.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

class ArchiveImportOrchestrator:
    BATCH_SIZE = 1000
    CHECKPOINT_FILE = 'import_checkpoint.json'

    def __init__(self, db_config: dict, es_config: dict):
        self.extractor = NewscoopExtractor(db_config)
        self.loader = ElasticsearchLoader(**es_config)
        self.stats = {
            'total': 0,
            'processed': 0,
            'success': 0,
            'failed': 0,
            'start_time': None,
            'end_time': None
        }

    def load_ids_from_file(self, filepath: str) -> List[int]:
        """Load article IDs from file."""
        with open(filepath, 'r') as f:
            return [int(line.strip()) for line in f if line.strip().isdigit()]

    def save_checkpoint(self, processed_ids: List[int], language: str):
        """Save progress checkpoint for recovery."""
        checkpoint = {
            'language': language,
            'processed_ids': processed_ids,
            'timestamp': datetime.now().isoformat(),
            'stats': self.stats
        }
        with open(self.CHECKPOINT_FILE, 'w') as f:
            json.dump(checkpoint, f, indent=2)

    def load_checkpoint(self) -> dict:
        """Load existing checkpoint if available."""
        try:
            with open(self.CHECKPOINT_FILE, 'r') as f:
                return json.load(f)
        except FileNotFoundError:
            return None

    def run_import(self, ids_file: str, language: str, resume: bool = False):
        """Execute the import process."""
        self.stats['start_time'] = datetime.now().isoformat()

        # Load IDs
        all_ids = self.load_ids_from_file(ids_file)
        self.stats['total'] = len(all_ids)
        logger.info(f"Loaded {len(all_ids)} article IDs for language: {language}")

        # Check for resume
        processed_ids = []
        if resume:
            checkpoint = self.load_checkpoint()
            if checkpoint and checkpoint.get('language') == language:
                processed_ids = checkpoint.get('processed_ids', [])
                all_ids = [id for id in all_ids if id not in processed_ids]
                logger.info(f"Resuming from checkpoint. {len(processed_ids)} already processed.")

        # Process in batches
        language_id = {'ro': 2, 'ru': 15, 'en': 1}[language]

        for i in range(0, len(all_ids), self.BATCH_SIZE):
            batch_ids = all_ids[i:i + self.BATCH_SIZE]
            batch_num = i // self.BATCH_SIZE + 1
            total_batches = (len(all_ids) + self.BATCH_SIZE - 1) // self.BATCH_SIZE

            logger.info(f"Processing batch {batch_num}/{total_batches} ({len(batch_ids)} articles)")

            # Extract articles
            articles = []
            for article_id in batch_ids:
                try:
                    article = self.extractor.extract_article(article_id, language_id)
                    if article:
                        articles.append(article)
                except Exception as e:
                    logger.error(f"Error extracting article {article_id}: {e}")
                    self.stats['failed'] += 1

            # Load to Elasticsearch
            if articles:
                result = self.loader.bulk_index(iter(articles))
                self.stats['success'] += result['success']
                self.stats['failed'] += result['failed']

                if result['errors']:
                    for error in result['errors'][:5]:  # Log first 5 errors
                        logger.error(f"ES error: {error}")

            # Update progress
            self.stats['processed'] = len(processed_ids) + i + len(batch_ids)
            processed_ids.extend(batch_ids)

            # Save checkpoint
            self.save_checkpoint(processed_ids, language)

            # Progress report
            progress = (self.stats['processed'] / self.stats['total']) * 100
            logger.info(f"Progress: {progress:.1f}% ({self.stats['success']} success, {self.stats['failed']} failed)")

        self.stats['end_time'] = datetime.now().isoformat()
        self._print_final_report()

    def _print_final_report(self):
        """Print final import statistics."""
        logger.info("=" * 60)
        logger.info("IMPORT COMPLETE")
        logger.info("=" * 60)
        logger.info(f"Total articles:    {self.stats['total']}")
        logger.info(f"Processed:         {self.stats['processed']}")
        logger.info(f"Successful:        {self.stats['success']}")
        logger.info(f"Failed:            {self.stats['failed']}")
        logger.info(f"Success rate:      {(self.stats['success']/self.stats['total'])*100:.1f}%")
        logger.info(f"Start time:        {self.stats['start_time']}")
        logger.info(f"End time:          {self.stats['end_time']}")
        logger.info("=" * 60)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Archive Import Orchestrator')
    parser.add_argument('--ids-file', required=True, help='File with article IDs')
    parser.add_argument('--language', required=True, choices=['ro', 'ru', 'en'])
    parser.add_argument('--resume', action='store_true', help='Resume from checkpoint')
    parser.add_argument('--db-host', default='localhost')
    parser.add_argument('--db-user', default='root')
    parser.add_argument('--db-password', required=True)
    parser.add_argument('--db-name', default='newscoop')
    parser.add_argument('--es-host', required=True)
    parser.add_argument('--es-api-key', required=True)

    args = parser.parse_args()

    db_config = {
        'host': args.db_host,
        'user': args.db_user,
        'password': args.db_password,
        'database': args.db_name
    }

    es_config = {
        'es_host': args.es_host,
        'api_key': args.es_api_key,
        'verify_ssl': True
    }

    orchestrator = ArchiveImportOrchestrator(db_config, es_config)
    orchestrator.run_import(args.ids_file, args.language, args.resume)
```

**Agent responsabil:** `data-import-orchestrator`

#### Deliverables Faza 2:
- [ ] Script extragere MariaDB functional
- [ ] Script incarcare ES functional
- [ ] Script orchestrare cu checkpoint/resume
- [ ] Unit tests pentru fiecare componenta
- [ ] Documentatie utilizare scripturi

---

### Faza 3: Testare si Validare (Zile 6-7)

**Obiectiv:** Testare end-to-end cu subset de date

#### Task 3.1: Test Import Pilot (100 articole)

```bash
# Creare subset de test
head -100 /tmp/missing_ro_ids.txt > /tmp/test_ro_ids.txt

# Rulare import pilot
python3 scripts/archive_import/orchestrate_import.py \
  --ids-file /tmp/test_ro_ids.txt \
  --language ro \
  --db-password "YOUR_PASSWORD" \
  --es-host "https://165.22.89.204:9200" \
  --es-api-key "YOUR_API_KEY"
```

**Agent responsabil:** `backend-api-tester`

#### Task 3.2: Verificare Articole Importate

```bash
# SSH pe droplet
ssh root@165.22.89.204

# Verificare count
curl -k -X GET "https://localhost:9200/deschide_articles/_count" \
  -H "Authorization: ApiKey YOUR_API_KEY"

# Verificare sample articol
curl -k -X GET "https://localhost:9200/deschide_articles/_doc/ro_12345" \
  -H "Authorization: ApiKey YOUR_API_KEY" | jq '.'

# Verificare search functionality
curl -k -X GET "https://localhost:9200/deschide_articles/_search" \
  -H "Authorization: ApiKey YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "query": {
      "match": {
        "title": "test article title"
      }
    }
  }'
```

**Agent responsabil:** `backend-api-tester`

#### Task 3.3: Validare Calitate Date

Criterii de validare:
- [ ] Titluri non-empty pentru toate articolele
- [ ] Content non-empty pentru tipurile stiri/ultrascurte
- [ ] Published_at valid (format ISO 8601)
- [ ] Language corespunde ID-ului sursa
- [ ] Slug generat corect (fara diacritice, lowercase)
- [ ] Imagini asociate corect (daca exista)

**Agent responsabil:** `backend-api-tester`

#### Task 3.4: Test Performance

```bash
# Masurare timp raspuns search
ab -n 100 -c 10 "https://165.22.89.204:9200/deschide_articles/_search"

# Verificare utilizare memorie ES
curl -k "https://localhost:9200/_nodes/stats/jvm" \
  -H "Authorization: ApiKey YOUR_API_KEY" | jq '.nodes[].jvm.mem'
```

**Agent responsabil:** `performance-tester`

#### Deliverables Faza 3:
- [ ] Raport test pilot (100 articole)
- [ ] Validare calitate date
- [ ] Benchmark performanta
- [ ] Lista probleme identificate

---

### Faza 4: Import Complet RO (Zile 8-10)

**Obiectiv:** Import toate cele 11,874 articole romanesti

#### Task 4.1: Executie Import RO

```bash
# Import complet cu logging
nohup python3 scripts/archive_import/orchestrate_import.py \
  --ids-file /tmp/missing_ro_ids.txt \
  --language ro \
  --db-password "YOUR_PASSWORD" \
  --es-host "https://165.22.89.204:9200" \
  --es-api-key "YOUR_API_KEY" \
  > import_ro_$(date +%Y%m%d).log 2>&1 &

# Monitorizare progres
tail -f import_ro_*.log
```

**Agent responsabil:** `data-import-orchestrator`

#### Task 4.2: Monitorizare si Recovery

```bash
# Check status ES cluster
curl -k "https://localhost:9200/_cluster/health" \
  -H "Authorization: ApiKey YOUR_API_KEY"

# Daca import esueaza, resume
python3 scripts/archive_import/orchestrate_import.py \
  --ids-file /tmp/missing_ro_ids.txt \
  --language ro \
  --resume \
  ...
```

**Agent responsabil:** `data-import-orchestrator`

#### Task 4.3: Validare Post-Import RO

```bash
# Verificare count final
curl -k "https://localhost:9200/deschide_articles/_count?q=language:ro" \
  -H "Authorization: ApiKey YOUR_API_KEY"

# Verificare random sample (10 articole)
curl -k -X POST "https://localhost:9200/deschide_articles/_search" \
  -H "Authorization: ApiKey YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "size": 10,
    "query": {"match": {"language": "ro"}},
    "sort": [{"_script": {"type": "number", "script": "Math.random()"}}]
  }'
```

**Agent responsabil:** `backend-api-tester`

#### Deliverables Faza 4:
- [ ] 11,874 articole RO importate
- [ ] Log import complet
- [ ] Raport validare post-import
- [ ] Checkpoint salvat

---

### Faza 5: Import Complet RU (Zile 11-13)

**Obiectiv:** Import toate cele 11,103 articole rusesti

#### Task 5.1: Executie Import RU

```bash
# Import articole rusesti
nohup python3 scripts/archive_import/orchestrate_import.py \
  --ids-file /tmp/missing_ru_ids.txt \
  --language ru \
  --db-password "YOUR_PASSWORD" \
  --es-host "https://165.22.89.204:9200" \
  --es-api-key "YOUR_API_KEY" \
  > import_ru_$(date +%Y%m%d).log 2>&1 &
```

**Agent responsabil:** `data-import-orchestrator`

#### Task 5.2: Validare Post-Import RU

Similar cu validarea RO.

**Agent responsabil:** `backend-api-tester`

#### Deliverables Faza 5:
- [ ] 11,103 articole RU importate
- [ ] Log import complet
- [ ] Raport validare post-import

---

### Faza 6: Finalizare si Optimizare (Zile 14-15)

**Obiectiv:** Optimizare index si validare finala

#### Task 6.1: Optimizare Index ES

```bash
# Force merge pentru optimizare storage
curl -k -X POST "https://localhost:9200/deschide_articles/_forcemerge?max_num_segments=1" \
  -H "Authorization: ApiKey YOUR_API_KEY"

# Refresh index
curl -k -X POST "https://localhost:9200/deschide_articles/_refresh" \
  -H "Authorization: ApiKey YOUR_API_KEY"

# Update index settings pentru productie
curl -k -X PUT "https://localhost:9200/deschide_articles/_settings" \
  -H "Authorization: ApiKey YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "index": {
      "refresh_interval": "30s",
      "number_of_replicas": 1
    }
  }'
```

**Agent responsabil:** `database-engineer`

#### Task 6.2: Validare Finala Completa

| Verificare | Expected | Actual | Status |
|------------|----------|--------|--------|
| Total articole RO | +11,874 | | |
| Total articole RU | +11,103 | | |
| Search functionality | OK | | |
| Response time < 100ms | OK | | |
| No duplicate IDs | 0 | | |
| Error rate < 1% | OK | | |

**Agent responsabil:** `backend-api-tester`

#### Task 6.3: Documentare si Cleanup

- [ ] Actualizare documentatie arhiva
- [ ] Arhivare loguri import
- [ ] Stergere fisiere temporare
- [ ] Creare raport final

**Agent responsabil:** `data-import-orchestrator`

#### Deliverables Faza 6:
- [ ] Index ES optimizat
- [ ] Raport validare finala
- [ ] Documentatie actualizata
- [ ] Backup post-import creat

---

## 3. Timeline Estimat

```
Saptamana 1:
+------------------------------------------------------------------+
| Lun | Mar | Mie | Joi | Vin | Sam | Dum |
+------------------------------------------------------------------+
| Faza 1: Pregatire  | Faza 2: Dezvoltare Scripturi    | Buffer  |
| (2 zile)           | (3 zile)                         |         |
+------------------------------------------------------------------+

Saptamana 2:
+------------------------------------------------------------------+
| Lun | Mar | Mie | Joi | Vin | Sam | Dum |
+------------------------------------------------------------------+
| F3: Test | Faza 4: Import RO    | Faza 5: Import RU  | Buffer  |
| (2 zile) | (3 zile)              | (inceput)          |         |
+------------------------------------------------------------------+

Saptamana 3:
+------------------------------------------------------------------+
| Lun | Mar | Mie | Joi | Vin | Sam | Dum |
+------------------------------------------------------------------+
| Faza 5: Import RU (cont)  | Faza 6: Finalizare | DONE! |
| (2 zile)                   | (2 zile)           |       |
+------------------------------------------------------------------+
```

**Total estimat:** 15 zile lucratoare (3 saptamani)

---

## 4. Responsabilitati pe Agenti

### Agent: `database-engineer`

| Faza | Responsabilitati |
|------|------------------|
| 1 | Validare fisiere ID, backup ES, verificare conectivitate |
| 2 | Dezvoltare script extragere MariaDB |
| 6 | Optimizare index ES, force merge |

**Skill-uri necesare:**
- MariaDB/MySQL query optimization
- Elasticsearch cluster management
- Python scripting
- SSH/Linux administration

### Agent: `backend-api-tester`

| Faza | Responsabilitati |
|------|------------------|
| 1 | Verificare conectivitate |
| 3 | Test import pilot, validare calitate date |
| 4 | Validare post-import RO |
| 5 | Validare post-import RU |
| 6 | Validare finala completa |

**Skill-uri necesare:**
- API testing (curl, Postman)
- Elasticsearch query DSL
- Data validation
- Reporting

### Agent: `data-import-orchestrator`

| Faza | Responsabilitati |
|------|------------------|
| 2 | Dezvoltare script orchestrare |
| 4 | Executie import RO, monitorizare, recovery |
| 5 | Executie import RU |
| 6 | Documentare, cleanup |

**Skill-uri necesare:**
- Python scripting
- Process orchestration
- Error handling
- Logging/monitoring

### Agent: `performance-tester`

| Faza | Responsabilitati |
|------|------------------|
| 3 | Benchmark performanta |
| 6 | Verificare performanta post-import |

**Skill-uri necesare:**
- Load testing (ab, wrk)
- Performance analysis
- Elasticsearch monitoring

---

## 5. Dependinte si Riscuri

### 5.1 Dependinte

| ID | Dependinta | Impact | Mitigare |
|----|------------|--------|----------|
| D1 | Acces MariaDB local | Blocker | Verificare in Faza 1 |
| D2 | Acces SSH droplet | Blocker | Test conectivitate Faza 1 |
| D3 | ES API Key valid | Blocker | Validare Faza 1 |
| D4 | Path imagini HDD | Medium | Clarificare cu stakeholder |
| D5 | Spatiu stocare ES | Medium | Verificare inainte import |
| D6 | Banda retea stabila | Medium | Import in ore off-peak |

### 5.2 Riscuri

| ID | Risc | Probabilitate | Impact | Mitigare |
|----|------|---------------|--------|----------|
| R1 | Conexiune intrerupta in timpul importului | Medium | High | Checkpoint/resume mechanism |
| R2 | Date corupte in sursa | Low | Medium | Validare pre-import |
| R3 | ES cluster devine instabil | Low | High | Backup pre-import, batch size mic |
| R4 | Encoding issues (BLOB->UTF8) | Medium | Medium | Error handling robust |
| R5 | Timeout ES la bulk insert | Medium | Medium | Reduce batch size, retry logic |
| R6 | Duplicate articles | Low | Low | Upsert cu _id unic |
| R7 | Out of memory (script) | Low | Medium | Streaming/generator pattern |
| R8 | Imagini lipsa pe HDD | High | Medium | Log missing, continue import |

### 5.3 Plan Contingenta

**Daca importul esueaza complet:**
1. Restaurare din backup pre-import
2. Analiza cauza esec
3. Corectare problema
4. Resume de la checkpoint

**Daca performanta ES scade dupa import:**
1. Force merge segments
2. Ajustare replici
3. Scale vertical daca necesar

---

## 6. Criterii de Succes

### 6.1 Criterii Cantitative

| Criteriu | Target | Metrica |
|----------|--------|---------|
| Articole RO importate | >= 11,500 (97%) | Count ES |
| Articole RU importate | >= 10,800 (97%) | Count ES |
| Rata erori import | < 3% | Failed / Total |
| Timp total import | < 15 zile | Calendar |
| Search response time | < 100ms (p95) | ES metrics |

### 6.2 Criterii Calitative

| Criteriu | Validare |
|----------|----------|
| Titluri complete | Sample check 100 articole |
| Content intact | Verificare encoding, special chars |
| Metadata corecta | published_at, language, category |
| Search functional | Query test pentru fiecare limba |
| Integritate referinte | Authors, images linked correctly |

### 6.3 Definition of Done

- [ ] Toate articolele lipsa importate cu succes (>97%)
- [ ] Validare calitate date completata
- [ ] Performance test passed
- [ ] Documentatie actualizata
- [ ] Backup post-import creat
- [ ] Cleanup fisiere temporare
- [ ] Raport final generat si aprobat

---

## 7. Resurse si Configuratie

### 7.1 Credentiale (a fi securizate)

```bash
# MariaDB Local (WSL)
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=[SECURED]
DB_NAME=newscoop

# Elasticsearch Remote
ES_HOST=https://165.22.89.204:9200
ES_API_KEY=[SECURED]
ES_INDEX=deschide_articles

# SSH Droplet
SSH_HOST=165.22.89.204
SSH_USER=root
SSH_KEY=~/.ssh/id_rsa
```

### 7.2 Fisiere de Referinta

| Fisier | Locatie | Continut |
|--------|---------|----------|
| IDs RO lipsa | `/tmp/missing_ro_ids.txt` | 11,874 linii |
| IDs RU lipsa | `/tmp/missing_ru_ids.txt` | 11,103 linii |
| Scripturi import | `/var/www/deschide_news_app/scripts/archive_import/` | Python |
| Loguri import | `./import_*.log` | Runtime |
| Checkpoint | `./import_checkpoint.json` | Progress |

### 7.3 Comenzi Utile

```bash
# Verificare stare ES
curl -k https://165.22.89.204:9200/_cluster/health -H "Authorization: ApiKey KEY"

# Count articole per limba
curl -k https://165.22.89.204:9200/deschide_articles/_search -H "Authorization: ApiKey KEY" \
  -d '{"size":0,"aggs":{"by_lang":{"terms":{"field":"language.keyword"}}}}'

# Stergere articol gresit
curl -k -X DELETE https://165.22.89.204:9200/deschide_articles/_doc/ro_12345 \
  -H "Authorization: ApiKey KEY"
```

---

## 8. Checklist Pre-Start

Inainte de a incepe Faza 1, verificati:

- [ ] Acces SSH la droplet functional (`ssh root@165.22.89.204`)
- [ ] MariaDB local accesibil (`mysql -u root -p newscoop`)
- [ ] Fisiere ID-uri existente si complete
- [ ] API Key ES valid si testat
- [ ] Python 3.8+ instalat cu dependinte
- [ ] Spatiu suficient pe disc local pentru loguri
- [ ] Backup strategie pentru ES documentata
- [ ] Stakeholder informat despre timeline

---

## Anexe

### A. Structura Tabele Newscoop

```sql
-- Articles (main table)
CREATE TABLE Articles (
    Number INT PRIMARY KEY,
    IdLanguage INT,
    IdSection INT,
    Name VARCHAR(255),
    PublishDate DATETIME,
    ...
);

-- Xstiri (news content)
CREATE TABLE Xstiri (
    NrArticle INT,
    IdLanguage INT,
    FTitlu VARCHAR(255),
    Flead TEXT,
    FContinut BLOB,
    PRIMARY KEY (NrArticle, IdLanguage)
);

-- Authors
CREATE TABLE Authors (
    Id INT PRIMARY KEY,
    Name VARCHAR(255),
    Email VARCHAR(255)
);

-- ArticleAuthors (junction)
CREATE TABLE ArticleAuthors (
    fk_article_number INT,
    fk_author_id INT
);
```

### B. Elasticsearch Index Mapping

```json
{
  "mappings": {
    "properties": {
      "article_id": { "type": "integer" },
      "title": {
        "type": "text",
        "analyzer": "romanian"
      },
      "slug": { "type": "keyword" },
      "lead": { "type": "text" },
      "content": { "type": "text" },
      "language": { "type": "keyword" },
      "published_at": { "type": "date" },
      "category": {
        "properties": {
          "id": { "type": "integer" },
          "name": { "type": "text" },
          "slug": { "type": "keyword" }
        }
      },
      "authors": {
        "type": "nested",
        "properties": {
          "id": { "type": "integer" },
          "name": { "type": "text" }
        }
      },
      "images": {
        "type": "nested",
        "properties": {
          "id": { "type": "integer" },
          "path": { "type": "keyword" },
          "alt": { "type": "text" }
        }
      }
    }
  }
}
```

### C. Diagrama Flux Import

```
+-------------------+     +-------------------+     +-------------------+
|                   |     |                   |     |                   |
|  MariaDB Local    |---->|  Extract Script   |---->|  JSON Articles    |
|  (Newscoop)       |     |  (Python)         |     |  (in memory)      |
|                   |     |                   |     |                   |
+-------------------+     +-------------------+     +-------------------+
                                                            |
                                                            v
+-------------------+     +-------------------+     +-------------------+
|                   |     |                   |     |                   |
|  ES Droplet       |<----|  Load Script      |<----|  Bulk API         |
|  (165.22.89.204)  |     |  (Python)         |     |  (500 docs/batch) |
|                   |     |                   |     |                   |
+-------------------+     +-------------------+     +-------------------+
        |
        v
+-------------------+
|                   |
|  Verification     |
|  & Reporting      |
|                   |
+-------------------+
```

---

---

## FAZA NOUA: Sincronizare Imagini (dupa import articole)

**Obiectiv:** Transfer ~14,139 imagini lipsa de pe HDD local pe droplet

### Pas 1: Identificare Imagini Lipsa

```bash
# Pe droplet - lista imagini existente
ssh root@165.22.89.204 "ls /var/www/html/alpha/" > /tmp/droplet_alpha_images.txt
ssh root@165.22.89.204 "ls /var/www/html/beta/" > /tmp/droplet_beta_images.txt

# Local - lista imagini backup
ls /mnt/d/ext-hdd/alpha/ > /tmp/local_alpha_images.txt
ls /mnt/d/ext-hdd/beta/images/ > /tmp/local_beta_images.txt

# Diferenta (imagini lipsa)
comm -23 <(sort /tmp/local_alpha_images.txt) <(sort /tmp/droplet_alpha_images.txt) > /tmp/missing_alpha.txt
comm -23 <(sort /tmp/local_beta_images.txt) <(sort /tmp/droplet_beta_images.txt) > /tmp/missing_beta.txt
```

### Pas 2: Transfer Imagini via rsync

```bash
# Transfer alpha images lipsa
rsync -avz --progress \
  --files-from=/tmp/missing_alpha.txt \
  /mnt/d/ext-hdd/alpha/ \
  root@165.22.89.204:/var/www/html/alpha/

# Transfer beta images lipsa
rsync -avz --progress \
  --files-from=/tmp/missing_beta.txt \
  /mnt/d/ext-hdd/beta/images/ \
  root@165.22.89.204:/var/www/html/beta/
```

### Pas 3: Verificare Post-Transfer

```bash
# Verificare count pe droplet
ssh root@165.22.89.204 "find /var/www/html/alpha/ -type f | wc -l"
ssh root@165.22.89.204 "find /var/www/html/beta/ -type f | wc -l"

# Target:
# Alpha: 165,302 (egal cu DB)
# Beta: 44,686 (egal cu DB)
```

---

**Document Version History:**

| Versiune | Data | Autor | Modificari |
|----------|------|-------|------------|
| 1.0 | 2025-12-13 | Product Strategy Team | Document initial |
| 2.0 | 2025-12-15 | Claude Code Assistant | Adaugat: Faza 0 Beta_deschide, locatii imagini HDD, faza sincronizare imagini, metrici actualizate |

---

*Acest document este viu si va fi actualizat pe masura progresului proiectului.*
